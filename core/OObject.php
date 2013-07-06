<?php

	/*****************************************************************************

	The MIT License (MIT)

	Copyright (c) 2013 Nathan A Obray <nathanobray@gmail.com>

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	*****************************************************************************/

	if (!class_exists( "OObject" )) { die(); }

	/********************************************************************************************************************

		OOBJECT:

	********************************************************************************************************************/

	Class OObject {

		// private data members
		private $delegate = FALSE;																	// does this object have a delegate
		private $starttime;																			// records the start time (time the object was created).  Cane be used for performance tuning
		private $is_error = FALSE;																	// error bit
		private $status_code = 200;																	// status code - used to translate to HTTP 1.1 status codes
		private $content_type = "application/json";													// stores the content type of this class or how it should be represented externally
		private $path = "";																			// the path of this object
		private $missing_path_handler;																//
		private $missing_path_handler_path;															//

		// public data members
		public $object = "";                                                                        // stores the name of the class

		/***********************************************************************

			ROUTE FUNCTION

		***********************************************************************/

		public function route( $path , $params = array(), $direct = TRUE ) {

			$cmd = $path;
			$this->operators = ["LIKE",">=","<=","!=",">","<","="];

			$components = parse_url($path);

			/*********************************
				handle remote HTTP(S) calls
			*********************************/
			if( isSet($components["host"]) ){ /* handle remote HTTP(S) calls */ }

    		/*********************************
    			Parse Path
    		*********************************/

    		forEach( $this->operators as $operator  ){ if( !isSet($params[$operator]) ){ $params[$operator] = array(); } }

    		if( isSet($components["query"]) ){ $tmp = $this->getExpressions($components["query"]); if( isSet($tmp["="]) ){ $params = array_merge($tmp["="],$params); }  }

			$path_array = preg_split("[/]",$components["path"],NULL,PREG_SPLIT_NO_EMPTY);
			$base_path = $this->getBasePath($path_array);

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

				return $this->findMissingRoute($cmd,$params);
				//$this->throwError("Route not found: $path.",404,"general");
			}

			return $this;

		}

		private function getExpressions($expression){

			$tmp;
			$expressions = array();
			while( preg_match("/\((.+)\)/",$expression,$tmp) === 1 ){
				$expression = str_replace($tmp[0],"exp".count($expressions),$expression);
				$expressions["exp".count($expressions)] = $this->getExpressions($tmp[1]);
			}
			$expression = $this->parseExpression($expression);
			if( isSet($expression["="]) ){ forEach( $expression["="] as $key => $value ){ if( isSet($expressions[$value]) ){  $expression["="][$key] = $expressions[$value]; } } }

			return $expression;
		}

		private function parseExpression($expression){
			$params = array();
			$pairs = preg_split("[&]",$expression,NULL,PREG_SPLIT_NO_EMPTY);
    		forEach($pairs as $index => $pair){
    			$pair = urldecode($pair);
    			forEach( $this->operators as $operator ){ if( strpos($pair,$operator) !== FALSE ){ $tmp = preg_split("/".$operator."/",$pair,NULL,PREG_SPLIT_NO_EMPTY); if( !empty($tmp) ){ $params[$operator][$tmp[0]] = $tmp[1]; } break; }	 }
    		}
    		return $params;
		}

		/***********************************************************************

			CREATE OBJECT

		***********************************************************************/

		private function createObject($path_array,$path,$base_path,&$params,$direct){
			//echo $path ."<br/>";
			$path = "";
			while(count($path_array)>0){
				$obj_name = array_pop($path_array);

				$this->path = $base_path . implode("/",$path_array)."/".$obj_name.".php";

				if (file_exists( $this->path ) ) {
					require_once $this->path;
					if (!class_exists( $obj_name )) { $this->throwError("File exists, but could not find object: $obj_name",404,"notfound"); return $this; } else {

						try{

				    		//	CREAT OBJECT
				    		$obj = new $obj_name();
				    		$obj->setObject(get_class($obj));
				    		$obj->setContentType($obj->content_type);

				    		//	CHECK PERMISSIONS
				    		$params["="] = array_merge($obj->checkPermissions("object",$direct),$params["="]);

				    		//	SETUP DATABSE CONNECTION
				    		if( method_exists($obj,"setDatabaseConnection") ){ $obj->setDatabaseConnection(getDatabaseConnection()); }

				    		//	ROUTE REMAINING PATH - function calls
					        $obj->route($path,$params,$direct);

				        } catch (Exception $e){ $this->throwError($e->getMessage()); }

				        return $obj;
					}
					break;
				} else {
					$path = "/".$obj_name;
				}

			}

			$this->throwError("Route not fount object: $path",404,"notfound"); return $this;

		}

		private function mergeParams($params){
			$p = array();
			forEach($params as $param){ $p = array_merge($p,$param); }
			return $p;
		}

		/***********************************************************************

			EXECUTE METHOD

		***********************************************************************/

		private function executeMethod($path,$path_array,$direct,&$params){
			$path = $path_array[0];


			if( method_exists($this,$path) ){


			   try{
					$params["="] = array_merge($this->checkPermissions($path,$direct),$params["="]);
					forEach( $this->operators as $operator  ){ if( empty($params[$operator]) ){ unset($params[$operator]); } }
					if( !$this->isError() ){ $this->$path($params); }

				} catch (Exception $e){ $this->throwError($e->getMessage()); }
				return $this;

		    } else {

		    	return $this->findMissingRoute($path,$path_array);

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
		    		$this->throwError("You cannot access this resource.",403,"Forbidden");
	    		// restrict access to users that are not logged in if that"s required
	    		} else if( ( $perms[$object_name] === "user" && !isSet($_SESSION["ouser"]) ) || ( is_int($perms[$object_name]) && !isSet($_SESSION["ouser"]) ) ){

		    		if( isSet($_SERVER["PHP_AUTH_USER"]) && isSet($_SERVER["PHP_AUTH_PW"]) ){
			    		$login = $this->route("/core/OUsers/login/",array("ouser_email"=>$_SERVER["PHP_AUTH_USER"],"ouser_password"=>$_SERVER["PHP_AUTH_PW"]),TRUE);
			    		if( !isSet($_SESSION["ouser"]) ){ $this->throwError("You cannot access this resource.",401,"Unauthorized");	}
		    		} else { $this->throwError("You cannot access this resource.",401,"Unauthorized"); }

		    	// restrict access to users without correct permissions
	    		} else if( is_int($perms[$object_name]) && isSet($_SESSION["ouser"]) && $_SESSION["ouser"]->ouser_permission_level > $perms[$object_name] ){ $this->throwError("You cannot access this resource.",403,"Forbidden"); }

	    		// add user_id to params if restriction is based on user
	    		if( isSet($perms[$object_name]) && $perms[$object_name] === "user" && isSet($_SESSION["ouser"]) ){ $params["ouser_id"] = $_SESSION["ouser"]->ouser_id; }

    		}

    		return $params;

		}

		/***********************************************************************

			PARSE PATH

		***********************************************************************/

		public function parsePath($path){

			$path = preg_split("([\][?])",$path);
			if(count($path) > 1){ parse_str($path[1],$params); } else { $params = array(); }
			$path = $path[0];

			$path_array = preg_split("[/]",$path,NULL,PREG_SPLIT_NO_EMPTY);
			$path = "/";

			$routes = unserialize(__ROUTES__);
			if( !empty($path_array) && isSet($routes[$path_array[0]]) ){ $base_path = $routes[array_shift($path_array)]; } else { $base_path = ""; }

			return array("path_array"=>$path_array,"path"=>$path,"base_path"=>$base_path,"params"=>$params);

		}

		private function getBasePath(&$path_array){
			$routes = unserialize(__ROUTES__);
			if( !empty($path_array) && isSet($routes[$path_array[0]]) ){ $base_path = $routes[array_shift($path_array)]; } else { $base_path = ""; }
			return $base_path;
		}

		/***********************************************************************

			ERROR HANDLING FUNCTIONS

		***********************************************************************/

		public function throwError($message,$status_code=500,$type="general"){
			$this->is_error = TRUE;
    		if( empty($this->errors) ){ $this->errors = []; }
    		$this->errors[$type] = $message;
    		$this->status_code = $status_code;
		}
		public function isError(){ return $this->is_error; }

		/***********************************************************************

			GETTER AND SETTER FUNCTIONS

		***********************************************************************/

		private function setObject($obj){ $this->object = $obj;}
		public  function getStatusCode(){ return $this->status_code; }
		public  function getContentType(){ return $this->content_type; }
		public  function setContentType($type){ if($this->content_type != "text/html"){ $this->content_type = $type; } }
		public  function setCustomRouter($router){ $this->custom_router = $router; }
		public  function getPermissions(){ return $this->permissions; }
		public  function setMissingPathHandler($handler,$path){ $this->missing_path_handler = $handler; $this->missing_path_handler_path = $path; }

		/***********************************************************************

			CLEANUP FUNCTION

		***********************************************************************/

		public function cleanUp(){
			// remove all object keys not white listed for output - this is so we don"t expose unnecessary information
			foreach($this as $key => $value) { if($key != "object" && $key != "errors" && $key != "data" && $key != "runtime" && $key != "html"){ unset($this->$key); } }
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
				$params = array_merge($obj->checkPermissions("object",FALSE),$params);

				//	SETUP DATABSE CONNECTION
				if( method_exists($obj,"setDatabaseConnection") ){ $obj->setDatabaseConnection(getDatabaseConnection()); }

				//	ROUTE REMAINING PATH - function calls
				$obj->missing($path,$params,FALSE);

				return $obj;
			}

			return $this;
		}



	}
?>