<?php
if (!class_exists( 'OObject' )) { die(); }

/******************************************************
	SETUP DB CONNECTION - DO NOT MODIFY
******************************************************/

function getDatabaseConnection( $reconnect=FALSE ){

	global $conn;
	if( !isSet( $conn ) || $reconnect ){
		try {
			$conn = new PDO('mysql:host='.__OBRAY_DATABASE_HOST__.';dbname='.__OBRAY_DATABASE_NAME__.';charset=utf8', __OBRAY_DATABASE_USERNAME__,__OBRAY_DATABASE_PASSWORD__,array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch(PDOException $e) { echo 'ERROR: ' . $e->getMessage(); exit(); }
	}
	return $conn;

}

function getReaderDatabaseConnection( $reconnect=FALSE )
{
	global $readConn;
	if(!defined('__OBRAY_DATABASE_HOST_READER__')){
		return getDatabaseConnection($reconnect);
	}
	if( !isSet( $readConn ) || $reconnect ){
		try {
			$readConn = new PDO('mysql:host='.__OBRAY_DATABASE_HOST_READER__.';dbname='.__OBRAY_DATABASE_NAME__.';charset=utf8', __OBRAY_DATABASE_USERNAME__,__OBRAY_DATABASE_PASSWORD__,array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
			$readConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch(PDOException $e) { echo 'ERROR: ' . $e->getMessage(); exit(); }
	}
	return $readConn;
}

/******************************************************
	REMOVE SPECIAL CHARS (cleans a string)
******************************************************/

function removeSpecialChars($string,$space = '',$amp = ''){

	$string = str_replace(' ',$space,$string);
	$string = str_replace('&',$amp,$string);
	$string = preg_replace('/[^a-zA-Z0-9\-_s]/', '', $string);
	return $string;

}

if (!function_exists('getallheaders')){
	function getallheaders(){
		$headers = array();
		foreach ($_SERVER as $name => $value){
			if (substr($name, 0, 5) == 'HTTP_'){
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}

/********************************************************************************************************************

	OOBJECT:

********************************************************************************************************************/

Class OObject {

	// private data members
	private $delegate = FALSE;																	// does this object have a delegate [TO BE IMPLEMENTED]
	private $starttime;																			// records the start time (time the object was created).  Cane be used for performance tuning
	private $is_error = FALSE;																	// error bit
	private $status_code = 200;																	// status code - used to translate to HTTP 1.1 status codes
	private $content_type = 'application/json';													// stores the content type of this class or how it should be represented externally
	private $path = '';																			// the path of this object
	private $missing_path_handler;																// if path is not found by router we can pass it to this handler for another attempt
	private $missing_path_handler_path;															// the path of the missing handler
	private $access;
	private static $container = null;

	// public data members
	public $object = '';                                                                        // stores the name of the class

	public function console(){

		$args = func_get_args();
		if( PHP_SAPI === 'cli' && !empty($args) ){

			if( is_array($args[0]) || is_object($args[0]) ) {
				print_r($args[0]);
			} else if( count($args) === 3 && $args[1] !== NULL && $args[2] !== NULL ){
				$colors = array(
					// text color
					"Black" =>				"\033[30m",
					"Red" => 				"\033[31m",
					"Green" =>				"\033[32m",
					"Yellow" => 			"\033[33m",
					"Blue" => 				"\033[34m",
					"Purple" => 			"\033[35m",
					"Cyan" =>				"\033[36m",
					"White" => 				"\033[37m",
					// text color bold
					"BlackBold" => 			"\033[30m",
					"RedBold" => 			"\033[1;31m",
					"GreenBold" => 			"\033[1;32m",
					"YellowBold" => 		"\033[1;33m",
					"BlueBold" => 			"\033[1;34m",
					"PurpleBold" => 		"\033[1;35m",
					"CyanBold" => 			"\033[1;36m",
					"WhiteBold" => 			"\033[1;37m",
					// text color muted
					"RedMuted" => 			"\033[2;31m",
					"GreenMuted" => 		"\033[2;32m",
					"YellowMuted" => 		"\033[2;33m",
					"BlueMuted" => 			"\033[2;34m",
					"PurpleMuted" => 		"\033[2;35m",
					"CyanMuted" => 			"\033[2;36m",
					"WhiteMuted" => 		"\033[2;37m",
					// text color muted
					"BlackUnderline" => 	"\033[4;30m",
					"RedUnderline" => 		"\033[4;31m",
					"GreenUnderline" => 	"\033[4;32m",
					"YellowUnderline" => 	"\033[4;33m",
					"BlueUnderline" => 		"\033[4;34m",
					"PurpleUnderline" => 	"\033[4;35m",
					"CyanUnderline" => 		"\033[4;36m",
					"WhiteUnderline" => 	"\033[4;37m",
					// text color blink
					"BlackBlink" => 		"\033[5;30m",
					"RedBlink" => 			"\033[5;31m",
					"GreenBlink" => 		"\033[5;32m",
					"YellowBlink" => 		"\033[5;33m",
					"BlueBlink" => 			"\033[5;34m",
					"PurpleBlink" => 		"\033[5;35m",
					"CyanBlink" =>			"\033[5;36m",
					"WhiteBlink" => 		"\033[5;37m",
					// text color background
					"RedBackground" => 		"\033[7;31m",
					"GreenBackground" => 	"\033[7;32m",
					"YellowBackground" => 	"\033[7;33m",
					"BlueBackground" => 	"\033[7;34m",
					"PurpleBackground" => 	"\033[7;35m",
					"CyanBackground" => 	"\033[7;36m",
					"WhiteBackground" => 	"\033[7;37m",
					// reset - auto called after each of the above by default
					"Reset"=> 				"\033[0m"
				);
				$color = $colors[$args[2]];
				printf($color.array_shift($args)."\033[0m",array_shift($args) );
			} else {
				printf( array_shift($args),array_shift($args) );
			}
		}
	}

	/***********************************************************************

		ROUTE FUNCTION

	***********************************************************************/

	public function route( $path , $params = array(), $direct = TRUE ) {

		if( !$direct ){ $params = array_merge($params,$_GET,$_POST); }
		$cmd = $path;
		$this->params = $params;
		$components = parse_url($path); $this->components = $components;
		if( isSet($components['query']) ){
			if( is_string($params) ){ $params = array( "body" => $params ); }
			parse_str($components['query'],$tmp); $params = array_merge($tmp,$params);
			if( !empty($components["scheme"]) && ( $components["scheme"] == "http" || $components["scheme"] == "https" ) ){
				$path = $components["scheme"] ."://". $components["host"] . (!empty($components["port"])?':'.$components["port"]:'') . $components["path"];
			}
		}
	
		/*********************************
			Parse Path & setup params
		*********************************/

		$_REQUEST = $params;
		
		$path_array = explode('/',$components['path']);
		$path_array = array_filter($path_array);
		$path_array = array_values($path_array);
		
		$base_path = $this->getBasePath($path_array);
		
		/*********************************
			Validate Remote Application
		*********************************/

		if( $direct === FALSE ){
			$this->validateRemoteApplication($direct);
		}
		
		/*********************************
			SET CONTENT TYPE FROM ROUTE
		*********************************/

		if( isset($params['ocsv']) ){ $this->setContentType('text/csv'); unset($params['ocsv']); }
		if( isset($params['otsv']) ){ $this->setContentType('text/tsv'); unset($params['otsv']); }
		if( isset($params['otable']) ){ $this->setContentType('text/table'); unset($params['otable']); }

		/*********************************
			CALL FUNCTION
		*********************************/
		
		if( empty($base_path) && count($path_array) == 1 && !empty($this->object) && $this->object != $path_array[0] ){
			return $this->executeMethod($path,$path_array,$direct,$params);
		}

		/*********************************
			CREATE OBJECT
		*********************************/

		$obj = $this->createObject($path_array,$path,$base_path,$params,$direct);
		if( empty($this->errors)  ){ return $obj; }

		/*********************************
			FIND MISSING ROUTE
		*********************************/
		if( $this->status_code == 404 ){
			return $this->findMissingRoute($components['path'],$params);
		}

		return $this;

	}

	/***********************************************************************

		VALIDATE REMOTE APPLICATION

	***********************************************************************/

	public function validateRemoteApplication(&$direct){

		$headers = getallheaders();

		if( isSet($headers['Obray-Token']) ){
			$otoken = $headers['Obray-Token']; unset($headers['Obray-Token']);
			if( defined('__OBRAY_TOKEN__') && $otoken === __OBRAY_TOKEN__ && __OBRAY_TOKEN__ != '' ){ $direct = TRUE;  }
		}
	}

	private function _namespacedClassExists($path,$obj_name){
		$namespace_components = explode("/",$this->path);
		$namespace_components  = array_filter($namespace_components, function($item){
			return $item !== "app";
		});
		array_pop($namespace_components);
		$namespace_str = implode("/", $namespace_components);
		$namespace = str_replace("/","\\", str_replace(__OBRAY_NAMESPACE_ROOT__,__OBRAY_APP_NAME__.'\\',$namespace_str));
		$namespaced_path = "\\".$namespace."\\".$obj_name;
		$namespaced_path = str_replace('\\\\','\\',$namespaced_path);
		$exists = class_exists($namespaced_path);
		if($exists){
			$this->namespaced_path = $namespaced_path;
			return true;
		}
		return false;
	}

	/***********************************************************************

		CREATE OBJECT

	***********************************************************************/

	private function createObject($path_array,$path,$base_path,&$params,$direct){
		
		$path = '';
		$isNamespacedPath = false;
		$namespacedControllersPath = __NAMESPACE_ROOT__ ."controllers/";
		$namespacedModelsPath = __NAMESPACE_ROOT__ . "models/";
		$namespacedDataPath = __NAMESPACE_ROOT__ . "data/";
		$rPath = array();
		$obj_name_loop_counter = 0;
		$obj_name_loop_name_check = "";

		if( empty($path_array) && empty($this->object) && empty($base_path)){
			if(empty($path_array)){	$path_array[] = "index";	}
		}
		while(count($path_array)>0){

			if(empty($base_path)){
				if(is_dir(__OBRAY_SITE_ROOT__.$namespacedControllersPath.implode('/',$path_array))){
					$path_array[] = $path_array[(count($path_array)-1)];
				}
			}

			$obj_name = array_pop($path_array);

			$this->namespaced_controller_path = __OBRAY_SITE_ROOT__.$namespacedControllersPath.implode('/',$path_array).'/c'.str_replace(' ','',ucWords( str_replace('-',' ',$obj_name) ) ).'.php';
			$this->namespaced_model_path = __OBRAY_SITE_ROOT__.$namespacedModelsPath.implode('/',$path_array).'/'.$obj_name.'.php';
			$this->namespaced_data_path = __OBRAY_SITE_ROOT__.$namespacedDataPath.implode('/',$path_array).'/'.$obj_name.'.php';

			if(file_exists($this->namespaced_model_path)){
				$objectType = "model";
				$this->path = $this->namespaced_model_path;
				$isNamespacedPath = true;
			}
			if(file_exists($this->namespaced_data_path)){
				$objectType = "data";
				$this->path = $this->namespaced_data_path;
				$isNamespacedPath = true;
			}
			else if(file_exists($this->namespaced_controller_path) ){
				$objectType = "controller";
				$obj_name = "c".str_replace(' ','',ucWords( str_replace('-',' ',$obj_name) ) );
				$this->path = $this->namespaced_controller_path;
				if(empty($path)){
					$path = "/index/";
				}
				$isNamespacedPath = true;
			}

			if (!empty($objectType)){
				
				$doesNamespaceClassExist = $this->_namespacedClassExists($this->path, $obj_name);
				
				if (!class_exists( $obj_name ) && !$doesNamespaceClassExist) {
					require_once $this->path;
				}
				$class_exists = false;

				if (class_exists( $obj_name )) {
					$class_exists = true;
				}
				else if( $doesNamespaceClassExist ){
					$class_exists = true;
					$obj_name = $this->namespaced_path;
				}

				if ($class_exists){

					try{
						//	CREATE OBJECT
						$obj = new $obj_name($params,$direct,$rPath);
						$obj->objectType = $objectType;
						$obj->setObject(get_class($obj));
						$obj->setContentType($obj->content_type);
						$obj->path_to_object = implode('/',$path_array);
						array_pop($rPath);
						$obj->rPath = $rPath;

						//	CHECK PERMISSIONS
						$params = array_merge($obj->checkPermissions('object',$direct),$params);

						//	SETUP DATABASE CONNECTION
						if(method_exists($obj,'setDatabaseConnection')){
							$obj->setDatabaseConnection(getDatabaseConnection());
							$obj->setReaderDatabaseConnection(getReaderDatabaseConnection());
						}

						//	ROUTE REMAINING PATH - function calls
						if(!empty($path)){
							$obj->route($path,$params,$direct);
						}

						return $obj;

					} catch (Exception $e){
						$this->throwError($e->getMessage());
						$this->logError(oCoreProjectEnum::OOBJECT,$e);
					}

				}
				break;
			} else {
				$rPath[] = strtolower($obj_name);
				$path = '/'.$obj_name;

				if($obj_name_loop_name_check === $obj_name){
					$obj_name_loop_counter++;
					if($obj_name_loop_counter > 10){
						break;
					}
				}

				$obj_name_loop_name_check = $obj_name;
			}

		}
		
		$this->throwError('Route not found object: '.$path,404,'notfound'); return $this;

	}

	/***********************************************************************

		EXECUTE METHOD

	***********************************************************************/

	private function executeMethod($path,$path_array,$direct,&$params) {

		$path = str_replace('-', '', $path_array[0]);

		if (method_exists($this, $path)) {
			try {
				$params = array_merge($this->checkPermissions($path, $direct), $params);
				if (!$this->isError()) {
					$this->$path($params);
				}
			} catch (Exception $e) {
				$this->throwError($e->getMessage());
				$this->logError(oCoreProjectEnum::ODBO,$e);
			}
			return $this;
		} else if (method_exists($this, "index")) {
			try {
				$params = array_merge($this->checkPermissions("index", $direct), $params);
				if (!$this->isError()) {
					$this->index($params);
				}
			} catch (Exception $e) {
				$this->throwError($e->getMessage());
				$this->logError(oCoreProjectEnum::ODBO,$e);
			}
			return $this;
		} else {
			return $this->findMissingRoute($path, $params);
		}
	}

	/***********************************************************************

		CHECK PERMISSIONS

	***********************************************************************/

	private function checkPermissions($object_name,$direct){

		$params = array();

		// only restrict permissions if the call is come from and HTTP request through router $direct === FALSE
		if( !$direct ){

			// retrieve permissions
			$perms = $this->getPermissions();

			// set the "method" permission is set and the specific method has no permis then set the object_name to "method"
			if( !isSet($perms[$object_name]) && isSet($perms['method']) ){ $object_name = 'method'; }

			//This is to add greater flexibility for using custom session variable for storage of user data
			$user_session_key = isset($this->user_session) ? $this->user_session : 'ouser';

			// restrict permissions on undefined keys
			if( !isSet($perms[$object_name]) ){
				
				$this->throwError('You cannot access this resource.',403,'Forbidden');
				
			// restrict access to users that are not logged in if that's required
			} else if( ( $perms[$object_name] === 'user' && !isSet($_SESSION[$user_session_key]) ) || ( is_int($perms[$object_name]) && !isSet($_SESSION[$user_session_key]) ) ){

				if( isSet($_SERVER['PHP_AUTH_USER']) && isSet($_SERVER['PHP_AUTH_PW']) ){
					$login = $this->route('/obray/OUsers/login/',array('ouser_email'=>$_SERVER['PHP_AUTH_USER'],'ouser_password'=>$_SERVER['PHP_AUTH_PW']),TRUE);
					if( !isSet($_SESSION[$user_session_key]) ){ $this->throwError('You cannot access this resource.',401,'Unauthorized');	}
				} else { $this->throwError('You cannot access this resource.',401,'Unauthorized'); }

			// restrict access to users without correct permissions (non-graduated)
			} else if( 
				is_int($perms[$object_name]) && 
				isSet($_SESSION[$user_session_key]) && 
				(
					isset($_SESSION[$user_session_key]->ouser_permission_level) 
					&& !defined("__OBRAY_GRADUATED_PERMISSIONS__") 
					&& $_SESSION[$user_session_key]->ouser_permission_level != $perms[$object_name]
				)
			){ 

					$this->throwError('You cannot access this resource.',403,'Forbidden'); 

			// restrict access to users without correct permissions (graduated)
			} else if( 
				is_int($perms[$object_name]) && 
				isSet($_SESSION[$user_session_key]) && 
				(
					isset($_SESSION[$user_session_key]->ouser_permission_level) 
					&& defined("__OBRAY_GRADUATED_PERMISSIONS__") 
					&& $_SESSION[$user_session_key]->ouser_permission_level > $perms[$object_name]
				)
			){

				$this->throwError('You cannot access this resource.',403,'Forbidden'); 

			// roles & permissions checks
			} else if(
				(
					is_array($perms[$object_name]) && 
					isSet($perms[$object_name]['permissions']) &&
					is_array($perms[$object_name]['permissions']) &&
					count(array_intersect($perms[$object_name]['permissions'],$_SESSION[$user_session_key]->permissions)) == 0
				) || (
					is_array($perms[$object_name]) && 
					isSet($perms[$object_name]['roles']) &&
					is_array($perms[$object_name]['roles']) &&
					count(array_intersect($perms[$object_name]['roles'],$_SESSION[$user_session_key]->roles)) == 0
				) || (
					is_array($perms[$object_name]) && 
					isSet($perms[$object_name]['roles']) &&
					is_array($perms[$object_name]['roles']) &&
					in_array("SUPER",$_SESSION[$user_session_key]->roles)
				) || (
					is_array($perms[$object_name]) && 
					isSet($perms[$object_name]['permissions']) &&
					!is_array($perms[$object_name]['permissions'])
				) || (
					is_array($perms[$object_name]) && 
					isSet($perms[$object_name]['roles']) &&
					!is_array($perms[$object_name]['roles'])
				) || (
					is_array($perms[$object_name]) &&
					!isSet($perms[$object_name]['roles']) &&
					!isSet($perms[$object_name]['permissions'])
				)
			){

				$this->throwError('You cannot access this resource.',403,'Forbidden'); 
			
			// add user_id to params if restriction is based on user
			} else {

				if( isSet($perms[$object_name]) && $perms[$object_name] === 'user' && isSet($_SESSION[$user_session_key]) ){ $params['ouser_id'] = $_SESSION['ouser']->ouser_id; }

			}
		}
		
		return $params;

	}

	/***********************************************************************

		PARSE PATH

	***********************************************************************/

	public function parsePath($path){

		$path = preg_split('([\][?])',$path);
		if(count($path) > 1){ parse_str($path[1],$params); } else { $params = array(); }
		$path = $path[0];

		$path_array = preg_split('[/]',$path,NULL,PREG_SPLIT_NO_EMPTY);
		$path = '/';

		$routes = unserialize(__OBRAY_ROUTES__);
		if( !empty($path_array) && isSet($routes[$path_array[0]]) ){ $base_path = $routes[array_shift($path_array)]; } else { $base_path = ''; }

		return array('path_array'=>$path_array,'path'=>$path,'base_path'=>$base_path,'params'=>$params);

	}

	/***********************************************************************

		GET BASE PATH - returns the path of a specified route

	***********************************************************************/

	private function getBasePath(&$path_array){
		$base_path = '';
		$routes = unserialize(__OBRAY_ROUTES__);
		if(!empty($path_array) && isSet($routes[$path_array[0]])){ 
			$base_path = $routes[array_shift($path_array)]; 
		}
		return $base_path;
	}

	/***********************************************************************

		CLEANUP FUNCTION - removes parameters form object for output

			The idea here is to prevent infromation from 'leaking'
			that's not explicitly intended.

	***********************************************************************/

	public function cleanUp(){
		if( !in_array($this->content_type,['text/csv','text/tsv','text/table']) ){
			// remove all object keys not white listed for output - this is so we don't expose unnecessary information
			$keys = ['object','errors','data','runtime','session','html','recordcount','def']; if( __OBRAY_DEBUG_MODE__ ){ $keys[] = 'sql'; $keys[] = 'filter'; }
			foreach($this as $key => $value) { if( !in_array($key,$keys) ){ unset($this->$key); } }
		}
	}

	/***********************************************************************

		IS OBJECT - Determines if path is an object

	***********************************************************************/

	public function isObject($path){

		$components = $this->parsePath($path);
		$obj_name = array_pop($components['path_array']);
		if( count($components['path_array']) > 0 ){ $seperator = '/'; } else { $seperator = ''; }
		$path = $components['base_path'] . implode('/',$components['path_array']).$seperator.$obj_name.'.php';
		if (file_exists( $path ) ) { 
			if(!class_exists( $obj_name )){
				require_once $path; 
			}
			if (class_exists( $obj_name )){ 
				return TRUE; 
			} 
		}

		return FALSE;

	}

	/***********************************************************************

		FIND MISSING ROUTE

	***********************************************************************/

	private function findMissingRoute($path,$params){

		if( isSet($this->missing_path_handler) ){
			include $this->missing_path_handler_path;

			$obj = new $this->missing_path_handler();
			$obj->setObject($this->missing_path_handler);

			$obj->setContentType($obj->content_type);

			//	CHECK PERMISSIONS
			$params = array_merge($obj->checkPermissions('object',FALSE),$params);

			//	SETUP DATABSE CONNECTION
			if( method_exists($obj,'setDatabaseConnection') ){ 
				$obj->setDatabaseConnection(getDatabaseConnection()); 
				$obj->setReaderDatabaseConnection(getReaderDatabaseConnection()); 
			}

			//	ROUTE REMAINING PATH - function calls
			$obj->missing('/'.ltrim(rtrim($path,'/'),'/').'/',$params,FALSE);

			return $obj;
		}

		return $this;
	}

	/***********************************************************************

		ERROR HANDLING FUNCTIONS

	***********************************************************************/

	public function throwError($message,$status_code=500,$type='general'){
		$this->is_error = TRUE;
		if( empty($this->errors) || !is_array($this->errors) ){ $this->errors = []; }
		$this->errors[$type][] = $message;
		$this->status_code = $status_code;
	}
	public function isError(){ return $this->is_error; }

	public function getStackTrace($exception) {
		$stackTrace = "";
		$count = 0;
		foreach ($exception->getTrace() as $frame) {
			$args = "";
			if (isset($frame['args'])) {
				$args = array();
				foreach ($frame['args'] as $arg) {
					if (is_string($arg)) {
						$args[] = "'" . $arg . "'";
					} elseif (is_array($arg)) {
						$args[] = "Array";
					} elseif (is_null($arg)) {
						$args[] = 'NULL';
					} elseif (is_bool($arg)) {
						$args[] = ($arg) ? "true" : "false";
					} elseif (is_object($arg)) {
						$args[] = get_class($arg);
					} elseif (is_resource($arg)) {
						$args[] = get_resource_type($arg);
					} else {
						$args[] = $arg;
					}
				}
				$args = join(", ", $args);
			}
			$stackTrace .= sprintf( "#%s %s(%s): %s(%s)\n",
				$count,
				$frame['file'],
				$frame['line'],
				$frame['function'],
				$args );
			$count++;
		}
		return $stackTrace;
	}

	/***********************************************************************

		ROLES & PERMISSIONS FUNCTIONS

	***********************************************************************/

	public function hasRole( $code ){
		if( ( !empty($_SESSION['ouser']->roles) && in_array($code,$_SESSION["ouser"]->roles) ) || ( !empty($_SESSION["ouser"]->roles) && in_array("SUPER",$_SESSION["ouser"]->roles) ) ){
			return TRUE;
		}
		return FALSE;
	}

	public function errorOnRole( $code ){
		if( !$this->hasRole($code) ){
			$this->throwError( "Permission denied", 403 );
			return true;
		}
		return false;
	}

	public function hasPermission( $code ){
		if( ( !empty($_SESSION['ouser']->permissions) && in_array($code,$_SESSION["ouser"]->permissions) ) || ( !empty($_SESSION["ouser"]->roles) && in_array("SUPER",$_SESSION["ouser"]->roles) ) ){
			return TRUE;
		}
		return FALSE;
	}

	public function errorOnPermission( $code ){
		if( !$this->hasPermission($code) ){
			$this->throwError( "Permission denied", 403 );
			return true;
		}
		return false;
	}

	/***********************************************************************

		GETTER AND SETTER FUNCTIONS

	***********************************************************************/

	private function setObject($obj){ $this->object = $obj;}
	public function getStatusCode(){ return $this->status_code; }
	public function getContentType(){ return $this->content_type; }
	public function setContentType($type){ if($this->content_type != 'text/html'){ $this->content_type = $type; } }
	public function getPermissions(){ return isset($this->permissions) ? $this->permissions : array(); }
	public function setMissingPathHandler($handler,$path){ $this->missing_path_handler = $handler; $this->missing_path_handler_path = $path; }
	public function redirect($location="/"){ header( 'Location: '.$location ); die(); }

	public function switchDB($db,$uname,$psswd){
		global $conn;

		try {
			$conn = new PDO('mysql:host='.__OBRAY_DATABASE_HOST__.';dbname='.$db.';charset=utf8',$uname,$psswd,array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch(PDOException $e) { echo 'ERROR: ' . $e->getMessage(); exit(); }

		return $conn;
	}

	/***********************************************************************

		RUN ROUTE IN BACKGROUND

	***********************************************************************/

	public function routeBackground( $route ){
		shell_exec("php -d memory_limit=-1 ".__SELF__."tasks.php \"".$route."\" > /dev/null 2>&1 &");
	}

	/***********************************************************************

	LOGGING FUNCTIONS

		***********************************************************************/

	public function logError($oProjectEnum, Exception $exception, $customMessage="") {
		$logger = new oLog();
		$logger->logError($oProjectEnum, $exception, $customMessage);
		return;
	}

	public function logInfo($oProjectEnum, $message) {
		$logger = new oLog();
		$logger->logInfo($oProjectEnum, $message);
		return;
	}

	public function logDebug($oProjectEnum, $message) {
		$logger = new oLog();
		$logger->logDebug($oProjectEnum, $message);
		return;
	}

	}