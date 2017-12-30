<?php
	/*****************************************************************************

	The MIT License (MIT)

	Copyright (c) 2013 Nathan A Obray <nathanobray@gmail.com>

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the 'Software'), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	*****************************************************************************/

	namespace obray;
	if (!class_exists( 'obray\oObject' )) { die(); }

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

		oOBJECT:

	********************************************************************************************************************/

	Class oObject {

		// private data members
		private $starttime;				// records the start time (time the object was created).  Cane be used for performance tuning
		private $is_error = FALSE;			// error bit
		private $status_code = 200;			// status code - used to translate to HTTP 1.1 status codes
		private $content_type = 'application/json';	// stores the content type of this class or how it should be represented externally
		
		protected $oDBOConnection;			// stores information about a connection or the connection itself for the purpose of establishing a connection to DB
		protected $debug_mode = FALSE;			// specify if we are in debug mode or not
		protected $user_session_key = "oUser";		// the users table

		// public data members
		public $object = '';                            // stores the name of the class

		public function __construct(){

			$dependencies = include "dependencies/config.php";
			forEach( $dependencies as $key => $dependency ){
				$this->$key = $dependency;
			}
			
		}

		/***********************************************************************

			ROUTE

			//	1)	parase path
			//		a)	merge our paramf rom $_GET and $_POST
			//		b)	parse incoming path as a URL (see PHP parse_url)
			//		c)	parse query parameters from url components
			//	2)	route remote HTTP(S) calls
			//		a)	check if obray\oCURL is installed
			//		b)	call obray\oCURL
			//	3)	validate Remote Application
			//	4)	set content type from params: oRouter will use
			//		these predefined values determine which encoder
			//		to use for output (default application/json).
			//		a)	ocsv: set content type to text/csv
			//		b)	otsv: set content type to text/tsv
			//		c)	otable: set content type to text/table
			//	5)	attempt to Find Object
			//		a)	find object only and return
			//		b)	find object and method and return
			//	6)	throw error: unable to find object


		***********************************************************************/

		public function route( $path , $params = array(), $direct = TRUE ) {

			/***************************************************************
			//	1)	parase path
			***************************************************************/
			//		a)	merge our paramf rom $_GET and $_POST
			if( !$direct ){ 
				$params = array_merge($params,$_GET,$_POST); 
			}

			//		b)	parse incoming path as a URL (see PHP parse_url)
			$components = parse_url($path); $this->components = $components;

			//		c)	parse query parameters from url components
			if( isSet($components['query']) ){
				parse_str($components['query'],$tmp_params);
				$params = array_merge($tmp_params,$params);
			}

			//		d)	parse component path into array
			$path_array = explode('/',$components['path']);
			$path_array = array_filter($path_array);
			$path_array = array_values($path_array);

			/***************************************************************
			//	2)	handle remote HTTP(S) calls
			//		a)	check if obray\oCURL is installed
			//		b)	call obray\oCURL
			***************************************************************/
			if( isSet($components['host']) && $direct ){
			//		a)	check if obray\oCURL is installed
				if (!class_exists( 'obray\oCURL' )) { 
					$this->throwError("obray\oCURL is not defined/installed.");
					return;
				}
			//		b)	call obray\oCURL
				$this->data = new obray\oCURL($components);
				return $this;
			}

			/***************************************************************
			//	3)	validate Remote Application
			***************************************************************/

			if( $direct === FALSE ){
				$this->validateRemoteApplication($direct);
			}

			/***************************************************************
			//	4)	set content type from params: oRouter will use
			//		these predefined values determine which encoder
			//		to use for output (default application/json).
			//		a)	ocsv: set content type to text/csv
			//		b)	otsv: set content type to text/tsv
			//		c)	otable: set content type to text/table
			***************************************************************/

			if( isset($params['ocsv']) ){ $this->setContentType('text/csv'); unset($params['ocsv']); }
			if( isset($params['otsv']) ){ $this->setContentType('text/tsv'); unset($params['otsv']); }
			if( isset($params['otable']) ){ $this->setContentType('text/table'); unset($params['otable']); }

			/***************************************************************
			//	5)	attempt to Find Object
			//		a)	find object only and return
			//		b)	find object and method and return
			***************************************************************/
			//		a)	find object only and return
			if( class_exists( '\\' . implode('\\',$path_array) ) ){
				$obj = $this->createObject( '\\' . implode('\\',$path_array), NULL, $params, $direct );
				return $obj;
			}

			//		b)	find object and method and return
			$function = array_pop($path_array);
			if( class_exists( '\\' . implode('\\',$path_array) ) ){
				$obj = $this->createObject( '\\' . implode('\\',$path_array), $function, $params, $direct );
				return $obj;
			}
			/***************************************************************
			//	6)	throw error: unable to find object
			***************************************************************/
			$this->throwError("Could not find " . $components['path']);

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

		/***********************************************************************

			CREATE OBJECT

			//	1)	create and setup object
			//	2)	set object properties
			//	3)	check object permissions
			//	4)	setup Database connection
			//	5)	if there is a function to call, then execute method

		***********************************************************************/

		private function createObject( $path, $function=NULL, $params=array(), $direct=FALSE, $object_type="model" ){

			//	1)	create and setup object
			try{
				$obj = new $path($params,$direct);
			} catch (Exception $e){
				$this->throwError($e->getMessage());
				$this->logError(oCoreProjectEnum::OOBJECT,$e);
				exit();
			}

			try{

				//	2)	set object properties
				$obj->objectType = $object_type;
				$obj->setObject(get_class($obj));
				$obj->setContentType($obj->content_type);
				$obj->path_to_object = $path;
				if( $this->debug_mode ){ $obj->enableDebugMode(); }

				//	3)	check object permissions
				$obj->checkPermissions('object',$direct);

				//	4)	setup Database connection
				//if( method_exists($obj,'setDatabaseConnection') ){ $obj->setDatabaseConnection( $this->oDBOConnection ); }

			} catch (Exception $e){
				$obj->throwError($e->getMessage());
				$obj->logError(oCoreProjectEnum::OOBJECT,$e);
				return $obj;
			}

			//	5)	if there is a function to call, then execute method
			if( !empty($function) ){
				$this->executeMethod($obj,$function,$params,$direct);
			}

			return $obj;

		}

		/***********************************************************************

			EXECUTE METHOD

			//	1)	check permission on function call (return if not permitted)
			//	2)	create new reflector to map parameters
			//	3)	if params is passed in then use old style params array passed into function
			//	4)	if there are parameters other than params then pass them through to the function
			//	5)	if no parameters passed in simply call function
			//	6)	handle errors on on the object itself

		***********************************************************************/

		private function executeMethod( $obj,$function,$params,$direct=FALSE ){

			try {
				//	1)	check permission on function call (return if not permitted)
				$obj->checkPermissions($function, $direct);
				if ( $obj->isError() ) { return; }

				//	2)	create new reflector to map parameters
				$reflector = new \ReflectionMethod($obj, $function);
				$function_parameters = $reflector->getParameters();

				//	3)	if params is passed in then use old style params array passed into function
				if( count($function_parameters) === 1 && $function_parameters[0]->name === 'params' ){
					$obj->$function($params);

				//	4)	if there are parameters other than params then pass them through to the function
				} else if( count($function_parameters) > 0 ) {

					$parameters = array();
					forEach( $function_parameters as $function_parameter ){
						if( !empty($params[$function_parameter->name]) ){
							$parameters[] = $params[$function_parameter->name];
						} else if( !$function_parameter->isOptional() ) {
							$obj->throwError("Missing method parameter.", 500, $function_parameter->name );
						}
					}
					if( empty($obj->errors) ){
						call_user_func_array(array($obj, $function), $parameters);
					}

				//	5)	if no parameters passed in simply call function
				} else {
					$obj->$function();
				}

			//	6)	handle errors on on the object itself
			} catch (Exception $e) {
				$obj->throwError($e->getMessage());
				$obj->logError(oCoreProjectEnum::ODBO,$e);
			}

		}

		/***********************************************************************

			CHECK PERMISSIONS

			//	1)	only restrict permissions if the call is come from and HTTP request through router $direct === FALSE
			//      2)      retrieve permissions
			//      3)      set the "method" permission is set and the specific method has no permis then set the object_name to "method"
			//      4)      This is to add greater flexibility for using custom session variable for storage of user data
			//      5)      restrict permissions on undefined keys
			//      6)      restrict access to users that are not logged in if that's required
			//      7)      restrict access to users without correct permissions (non-graduated)
			//      8)      restrict access to users without correct permissions (graduated)
			//      9)      roles & permissions checks

		***********************************************************************/

		protected function checkPermissions($object_name,$direct){

			if ( class_exists( '\obray\oUsers' ) ) {
				$oUsers = new \obray\oUsers( NULL, $direct, $this->oDBOConnection, $this->debug_mode );
				$oUsers->checkPermissions($object_name,$direct);
				if( !empty($oUsers->errors) ){
					$this->throwError('');
					$this->errors = $oUsers->errors;
					return;
				}
			}

			//	1)	only restrict permissions if the call is coming from and HTTP request through router $direct === FALSE
			if( $direct ){ return; }

	    		//	2)	retrieve permissions
	    		$perms = $this->getPermissions();

	    		//	3)	restrict permissions on undefined keys
	    		if( !isSet($perms[$object_name]) ){
				$this->throwError('You cannot access this resource.',403,'Forbidden');

			//	4)	restrict permissions on anything that does not have permission set to any
			} else if ( isSet($perms[$object_name]) && $perms[$object_name] !== 'any' ){
				$this->throwError('You cannot access this resource.',403,'Forbidden');
			}
			
		}

		/***********************************************************************

			CLEANUP FUNCTION - removes parameters form object for output

				The idea here is to prevent infromation from 'leaking'
				that's not explicitly intended.

			//	1)	remove all object keys not white listed for
			//		output - this is so we don't expose unnecessary
			//		information
			// 	2) 	if in debug mode allow some additiona information
                        //              through
			// 	3) 	based on our allowed keys unset valus from public
                        //              data members

		***********************************************************************/

		public function cleanUp(){
			if( !in_array($this->content_type,['text/csv','text/tsv','text/table']) ){

				// 	1) 	remove all object keys not white listed for
                        	// 		output - this is so we don't expose unnecessary
                        	//              information

				$keys = ['object','errors','data','runtime','html','recordcount'];

				//	2)	if in debug mode allow some additiona information
				//		through

				if( $this->debug_mode ){
					$keys[] = 'sql'; $keys[] = 'filter'; 
				}

				//	3)	based on our allowed keys unset valus from public
				//		data members

				foreach($this as $key => $value) {
					if( !in_array($key,$keys) ){
						unset($this->$key);
					}
				}

			}
		}

		/***********************************************************************

			ERROR HANDLING FUNCTIONS

			//	1)	Throw Error
			//		a)	Set is_error to TRUE
			//		b)	initialize this->errors if not intialized
			//		c)	add error of type to errors array
			//		d)	set status code

			//	2)	Is Error: returns TRUE/FALSE if error on object

			//	3)	Get Stack Trace

		***********************************************************************/

		////////////////////////////////////////////////////////////////////////
		//
		//	1)	Throw Error
                //		a)	Set is_error to TRUE
                //		b)	initialize this->errors if not intialized
                //		c)	add error of type to errors array
                //		d)      set status code
		//
		////////////////////////////////////////////////////////////////////////

		public function throwError($message,$status_code=500,$type='general'){

			//              a)      Set is_error to TRUE
			$this->is_error = TRUE;
			//              b)      initialize this->errors if not intialized
			if( empty($this->errors) || !is_array($this->errors) ){
				$this->errors = [];
			}
			//              c)      add error of type to errors array
	        	$this->errors[$type][] = $message;
			//              d)      set status code
	        	$this->status_code = $status_code;

	    	}

		////////////////////////////////////////////////////////////////////////
		//
		//      2)      Is Error: returns TRUE/FALSE if error on object
		//
		////////////////////////////////////////////////////////////////////////

		public function isError(){
			return $this->is_error;
		}

		////////////////////////////////////////////////////////////////////////
		//
		//      3)      Get Stack Trace
		//
		////////////////////////////////////////////////////////////////////////

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
		public function redirect($location="/"){ header( 'Location: '.$location ); die(); }
		public function enableDebugMode(){
			$this->debug_mode = TRUE;
		}
		public function setDatabaseConnection( $oDBOConnection ){
			$this->oDBOConnection = $oDBOConnection;
		}

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

		public function getMessageQueue( $queue ){
			$this->message_queue = msg_get_queue($queue);
		}

		public function messageQueueSend( $msgType, $message ){


			if( empty($this->message_queue) || !msg_send( $this->message_queue, $msgType, $message, FALSE, TRUE, $error_code ) ){
				$this->throwError("Error (".$error_code."): Unable to queue message.");
			}
		}

		function messageQueueReceive( $msgType ){
			$received_type = 0;
			$error_code;
			$message = FALSE;
			if( empty($this->message_queue) || msg_receive( $this->message_queue, $msgType, $received_type, 8192000, $message, FALSE, MSG_IPC_NOWAIT, $error_code ) ){
				return $message;
			} else {
				if( $error_code !== 42 ){
					$this->console("%s","Error receiving message from queue (".$error_code.")!\n","RedBold");
				}
				return FALSE;
			}
		}

		public function console(){

			$args = func_get_args();
			if( PHP_SAPI === 'cli' && !empty($args) ){

				if( is_array($args[0]) || is_object($args[0]) ) {
					print_r($args[0]);
				} else if( count($args) === 3 && $args[1] !== NULL && $args[2] !== NULL ){
					$colors = array(
						// text color
						"Black" =>			"\033[30m",
						"Red" => 			"\033[31m",
						"Green" =>			"\033[32m",
						"Yellow" => 			"\033[33m",
						"Blue" => 			"\033[34m",
						"Purple" => 			"\033[35m",
						"Cyan" =>			"\033[36m",
						"White" => 			"\033[37m",
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
						// text color underlined
						"BlackUnderline" => 		"\033[4;30m",
						"RedUnderline" => 		"\033[4;31m",
						"GreenUnderline" => 		"\033[4;32m",
						"YellowUnderline" => 		"\033[4;33m",
						"BlueUnderline" => 		"\033[4;34m",
						"PurpleUnderline" =>	 	"\033[4;35m",
						"CyanUnderline" => 		"\033[4;36m",
						"WhiteUnderline" =>	 	"\033[4;37m",
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
						"GreenBackground" => 		"\033[7;32m",
						"YellowBackground" => 		"\033[7;33m",
						"BlueBackground" => 		"\033[7;34m",
						"PurpleBackground" => 		"\033[7;35m",
						"CyanBackground" => 		"\033[7;36m",
						"WhiteBackground" => 		"\033[7;37m",
						// reset - auto called after each of the above by default
						"Reset"=> 			"\033[0m"
					);
					$color = $colors[$args[2]];
					printf($color.array_shift($args)."\033[0m",array_shift($args) );
				} else {
					printf( array_shift($args),array_shift($args) );
				}
			}
		}

	}
?>
