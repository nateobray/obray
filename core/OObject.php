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

	if (!class_exists( 'OObject' )) { die(); }

	/******************************************************
	    SETUP DB CONNECTION - DO NOT MODIFY
	******************************************************/

	function getDatabaseConnection(){

		global $conn;

		if( !isSet( $conn ) ){
			try {
		        $conn = new PDO('mysql:host='.__OBRAY_DATABASE_HOST__.';dbname='.__OBRAY_DATABASE_NAME__.';charset=utf8', __OBRAY_DATABASE_USERNAME__,__OBRAY_DATABASE_PASSWORD__,array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
		        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		    } catch(PDOException $e) { echo 'ERROR: ' . $e->getMessage(); exit(); }
		}
	    return $conn;

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

		// public data members
		public $object = '';                                                                        // stores the name of the class

		/***********************************************************************

			ROUTE FUNCTION

		***********************************************************************/

		public function route( $path , $params = array(), $direct = TRUE ) {

			if( !$direct ){ $params = array_merge($params,$_GET,$_POST); }
			//$_GET = array(); $_POST = array();
			$cmd = $path;
			$components = parse_url($path);

			/*********************************
				handle remote HTTP(S) calls
			*********************************/
			if( isSet($components['host']) && $direct ){

				$ch = curl_init();
				if( defined('__OBRAY_REMOTE_HOSTS__') && defined('__OBRAY_TOKEN__') && in_array($components['host'],unserialize(__OBRAY_REMOTE_HOSTS__)) ){ curl_setopt($ch, CURLOPT_HTTPHEADER, array('obray-token: '.__OBRAY_TOKEN__)); }
				$timeout = 5;
				curl_setopt($ch, CURLOPT_URL, $path);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
				curl_setopt($ch,CURLOPT_POST, count($params));
				curl_setopt($ch,CURLOPT_POSTFIELDS, $params);
				$this->data = curl_exec($ch);

				$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

				$this->data = json_decode($this->data);
				if( !empty($this->data) ){
					if( isSet($this->data->errors) ){ $this->errors = $this->data->errors; }
					if( isSet($this->data->html) ){ $this->html = $this->data->html; }
					if( isSet($this->data->data) ){ $this->data = $this->data->data; }
				}

			} else {

	    		/*********************************
	    			Parse Path & setup params
	    		*********************************/

	    		if( isSet($components['query']) ){ parse_str($components['query'],$tmp); $params = array_merge($tmp,$params);  }
	    		$_REQUEST = $params;

				$path_array = preg_split('[/]',$components['path'],NULL,PREG_SPLIT_NO_EMPTY);
				$base_path = $this->getBasePath($path_array);

				/*********************************
					Validate Remote Application
				*********************************/

				$this->validateRemoteApplication($direct);

	    		/*********************************
	    			Create Object
	    		*********************************/
				if( !empty($base_path) ){

					$obj = $this->createObject($path_array,$path,$base_path,$params,$direct);
					if( isSet($obj) ){ return $obj; }

	    		/*********************************
	    			Call Function
	    		*********************************/

				} else if( count($path_array) == 1 ) {

					return $this->executeMethod($path,$path_array,$direct,$params);

	    		/*********************************
	    			Handle Unknown Routes
	    		*********************************/
				} else {

					return $this->findMissingRoute($components['path'],$params);

				}

			}

			return $this;

		}

		/***********************************************************************

			VALIDATE REMOTE APPLICATION

		***********************************************************************/

		private function validateRemoteApplication(&$direct){
			$headers = getallheaders();
			if( isSet($headers['obray-token']) ){
				$otoken = $headers['obray-token']; unset($headers['obray-token']);
				if( defined('__OBRAY_TOKEN__') && $otoken === __OBRAY_TOKEN__ && __OBRAY_TOKEN__ != '' ){ $direct = TRUE;  }
			}
		}

		/***********************************************************************

			CREATE OBJECT

		***********************************************************************/

		private function createObject($path_array,$path,$base_path,&$params,$direct){

			$path = '';
			while(count($path_array)>0){
				$obj_name = array_pop($path_array);

				$this->path = $base_path . implode('/',$path_array).'/'.$obj_name.'.php';

				if (file_exists( $this->path ) ) {
					require_once $this->path;
					if (!class_exists( $obj_name )) { $this->throwError('File exists, but could not find object: $obj_name',404,'notfound'); return $this; } else {

						try{

				    		//	CREAT OBJECT
				    		$obj = new $obj_name();
				    		$obj->setObject(get_class($obj));
				    		$obj->setContentType($obj->content_type);

				    		//	CHECK PERMISSIONS
				    		$params = array_merge($obj->checkPermissions('object',$direct),$params);

				    		//	SETUP DATABSE CONNECTION
				    		if( method_exists($obj,'setDatabaseConnection') ){ $obj->setDatabaseConnection(getDatabaseConnection()); }

				    		//	ROUTE REMAINING PATH - function calls
					        $obj->route($path,$params,$direct);

				        } catch (Exception $e){ $this->throwError($e->getMessage()); }

				        return $obj;
					}
					break;
				} else {
					$path = '/'.$obj_name;
				}

			}

			$this->throwError('Route not fount object: '.$path,404,'notfound'); return $this;

		}

		/***********************************************************************

			EXECUTE METHOD

		***********************************************************************/

		private function executeMethod($path,$path_array,$direct,&$params){
			$path = $path_array[0];

			if( method_exists($this,$path) ){

			   try{

					$params = array_merge($this->checkPermissions($path,$direct),$params);
					if( !$this->isError() ){ $this->$path($params); }

				} catch (Exception $e){ $this->throwError($e->getMessage()); }
				return $this;

		    } else {

		    	return $this->findMissingRoute($path,$params);

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

	    		// restrict permissions on undefined keys
	    		if( !isSet($perms[$object_name])  ){
		    		$this->throwError('You cannot access this resource.',403,'Forbidden');
	    		// restrict access to users that are not logged in if that's required
	    		} else if( ( $perms[$object_name] === 'user' && !isSet($_SESSION['ouser']) ) || ( is_int($perms[$object_name]) && !isSet($_SESSION['ouser']) ) ){

		    		if( isSet($_SERVER['PHP_AUTH_USER']) && isSet($_SERVER['PHP_AUTH_PW']) ){
			    		$login = $this->route('/obray/OUsers/login/',array('ouser_email'=>$_SERVER['PHP_AUTH_USER'],'ouser_password'=>$_SERVER['PHP_AUTH_PW']),TRUE);
			    		if( !isSet($_SESSION['ouser']) ){ $this->throwError('You cannot access this resource.',401,'Unauthorized');	}
		    		} else { $this->throwError('You cannot access this resource.',401,'Unauthorized'); }

		    	// restrict access to users without correct permissions
	    		} else if( is_int($perms[$object_name]) && isSet($_SESSION['ouser']) && $_SESSION['ouser']->ouser_permission_level > $perms[$object_name] ){ $this->throwError('You cannot access this resource.',403,'Forbidden'); }

	    		// add user_id to params if restriction is based on user
	    		if( isSet($perms[$object_name]) && $perms[$object_name] === 'user' && isSet($_SESSION['ouser']) ){ $params['ouser_id'] = $_SESSION['ouser']->ouser_id; }

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
			$routes = unserialize(__OBRAY_ROUTES__);
			if( !empty($path_array) && isSet($routes[$path_array[0]]) ){ $base_path = $routes[array_shift($path_array)]; } else { $base_path = ''; }
			return $base_path;
		}

		/***********************************************************************

			CLEANUP FUNCTION - removes parameters form object for output

				The idea here is to prevent infromation from 'leaking'
				that's not explicitly intended.

		***********************************************************************/

		public function cleanUp(){
			// remove all object keys not white listed for output - this is so we don't expose unnecessary information
			$keys = ['object','errors','data','runtime','html','recordcount']; if( __OBRAY_DEBUG_MODE__ ){ $keys[] = 'sql'; $keys[] = 'filter'; }
			foreach($this as $key => $value) { if( !in_array($key,$keys) ){ unset($this->$key); } }
		}

		/***********************************************************************

			IS OBJECT - Determines if path is an object

		***********************************************************************/

		public function isObject($path){

			$components = $this->parsePath($path);
			$obj_name = array_pop($components['path_array']);
			if( count($components['path_array']) > 0 ){ $seperator = '/'; } else { $seperator = ''; }
			$path = $components['base_path'] . implode('/',$components['path_array']).$seperator.$obj_name.'.php';
			if (file_exists( $path ) ) { require_once $path; if (class_exists( $obj_name )){ return TRUE; } }

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
				if( method_exists($obj,'setDatabaseConnection') ){ $obj->setDatabaseConnection(getDatabaseConnection()); }

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

		/***********************************************************************

			GETTER AND SETTER FUNCTIONS

		***********************************************************************/

		private function setObject($obj){ $this->object = $obj;}
		public  function getStatusCode(){ return $this->status_code; }
		public  function getContentType(){ return $this->content_type; }
		public  function setContentType($type){ if($this->content_type != 'text/html'){ $this->content_type = $type; } }
		public  function getPermissions(){ return $this->permissions; }
		public  function setMissingPathHandler($handler,$path){ $this->missing_path_handler = $handler; $this->missing_path_handler_path = $path; }

	}
?>