<?php

	/***********************************************************************
	
	Obray - Super lightweight framework.  Write a little, do a lot, fast.
    Copyright (C) 2013  Nathan A Obray

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    ***********************************************************************/

	if (!class_exists( 'OObject' )) { die(); }
	
	/********************************************************************************************************************
		
		OOBJECT:	Object provides the basic routing functionality of an Obray application.  Every object that would
					like to have this capability should extend this object.
					
					OObject is a way of instantiating objects and calling public function with an object based on
					a simple URI.
		
		
		
		
	********************************************************************************************************************/
	
	Class OObject {
	
		// private data members
		private $delegate = FALSE;																	// does this object have a delegate
		private $starttime;																			// records the start time (time the object was created).  Cane be used for performance tuning
		private $is_error = FALSE;																	// error bit
		private $status_code = 200;																	// status code - used to translate to HTTP 1.1 status codes
		private $content_type = 'application/json';													// stores the content type of this class or how it should be represented externally
		private $path = '';																			// the path of this object
		//private $permissions;																		// stores permissions of a particular object
		
		// public data members
		public $object = '';                                                                        // stores the name of the class
		
		/***********************************************************************
		
			OBJECT CONSTRUCTOR
			
				This may be overridden to add additional functionality to
				the oobject at the time of instantiation.
		
		***********************************************************************/
		
		public function __construct(){ $this->starttime = microtime(TRUE); }
		
		/***********************************************************************
		
			ROUTE FUNCTION
			
		***********************************************************************/
		
		public function route( $path , $params = array(), $direct = TRUE ) {
			
			$cmd = $path;                                                                           
						
			if( !preg_match('(http[s]?://)',$path) ){
			    
				$parsed = $this->parsePath($path);
				$path_array = $parsed["path_array"]; $path = $parsed["path"]; $base_path = $parsed["base_path"]; $params = array_merge($params,$parsed["params"]);
				
				if( !empty($base_path) ){
				
					while(count($path_array)>0){																	// loop through path until we find an valid object
						$obj_name = array_pop($path_array);
						$this->path = $base_path . implode('/',$path_array).'/'.$obj_name.'.php';													// the path to the object
						if (file_exists( $this->path ) ) {															// test if object exists (object must be in folder and php file bearing its name
							require_once $this->path;																// if found require_once the file
							if (!class_exists( $obj_name )) {															// see if we can find the object class in the file
								$this->throwError("Could not find object: $obj_name",404,"notfound"); return $this;        // if we can't find the object class throw an error
							} else {                                                                    // if we can find the object start the factory
								try{                                                                    // handle errors and return if necessary
	    				    		
	    				    		/*******************************************************************************
	    				    			
	    				    			CREAT OBJECT
	    				    			
	    				    				1.  Create new object
	    				    				2.	Set the object property
	    				    				3.	Set the object content type (json|none)
	    				    			
	    				    		*******************************************************************************/
	    				    		
	    				    		$obj = new $obj_name();
	    				    		$obj->setObject(get_class($obj));
	    				    		$obj->setContentType($obj->content_type);
	    				    		
	    				    		/*******************************************************************************
	    				    			
	    				    			CHECK PERMISSIONS
	    				    			
	    				    				1.  Make sure permission exist to access this resource
	    				    			
	    				    		*******************************************************************************/
	    				    		
	    				    		if( !$direct ){
	    				    			
		    				    		$perms = $obj->getPermissions();
		    				    		
		    				    		if( !isSet($perms["object"])  ){ 
			    				    		$obj->throwError("You cannot access this resource.",403,"Forbidden"); 
		    				    		} else if( ( $perms["object"] === "user" && !isSet($_SESSION["ouser"]) ) || ( is_int($perms["object"]) && !isSet($_SESSION["ouser"]) ) ){
		    				    		
			    				    		if( isSet($_SERVER["PHP_AUTH_USER"]) && isSet($_SERVER["PHP_AUTH_PW"]) ){
				    				    		$login = $obj->route('/cmd/AUsers/login/',array("ouser_email"=>$_SERVER["PHP_AUTH_USER"],"ouser_password"=>$_SERVER["PHP_AUTH_PW"]),TRUE);
				    				    		
				    				    		if( !isSet($_SESSION["ouser"]) ){
					    				    		$obj->throwError("You cannot access this resource.",401,"Unauthorized");	
				    				    		}
			    				    		} else {
				    				    		$obj->throwError("You cannot access this resource.",401,"Unauthorized");	
			    				    		}
			    				    		
		    				    		} else if( is_int($perms["object"]) && isSet($_SESSION["ouser"]) && $_SESSION["ouser"]->ouser_permission_level > $perms["object"] ){
			    				    		$obj->throwError("You cannot access this resource.",403,"Forbidden");	
		    				    		}
		    				    		
	    				    		}
	    				    		
	    				    		/*******************************************************************************
	    				    			SETUP DATABSE CONNECTION
	    				    		*******************************************************************************/
	    				    		
	    				    		if( method_exists($obj,'setDatabaseConnection') ){ $obj->setDatabaseConnection(getDatabaseConnection()); }
	    					        
	    					        /*******************************************************************************
	    				    			ROUTE REMAINING PATH - function calls
	    				    		*******************************************************************************/
	    					        
	    					        $obj->route($path,$params,$direct);
	    					        
						        } catch (Exception $e){                                                 // catch to handle exception
	    					        $this->throwError($e->getMessage());				                // set and return error message and status
						        } 
						        return $obj;								                        	// return the object (this allows chaining)
							}
							break;                                                                      // if we find an object then we are done with this loop as we only want to find one per path
						} else {
							$path = '/'.$obj_name . $path;                                                   // set unused $obj from the $path_array to the $path to later be used to call a function in an object
						}
					}
				
				} else if( count($path_array) == 1 ) {
					
					$path = $path_array[0];
					if( method_exists($this,$path) ){                                               // test if method exists in this object and if so attempt to call it
					   try{
							
							if( !$direct ){
	    				    			
    				    		$perms = $this->getPermissions();
    				    		
    				    		if( !isSet($perms[$path])  ){ 
	    				    		$obj->throwError("You cannot access this resource.",403,"Forbidden"); 
    				    		} else if( ( $perms[$path] === "user" && !isSet($_SESSION["ouser"]) ) || ( is_int($perms[$path]) && !isSet($_SESSION["ouser"]) ) ){
    				    		
	    				    		if( isSet($_SERVER["PHP_AUTH_USER"]) && isSet($_SERVER["PHP_AUTH_PW"]) ){
		    				    		$login = $obj->route('/cmd/AUsers/login/',array("ouser_email"=>$_SERVER["PHP_AUTH_USER"],"ouser_password"=>$_SERVER["PHP_AUTH_PW"]),TRUE);
		    				    		
		    				    		if( !isSet($_SESSION["ouser"]) ){
			    				    		$this->throwError("You cannot access this resource.",401,"Unauthorized");	
		    				    		}
	    				    		} else {
		    				    		$this->throwError("You cannot access this resource.",401,"Unauthorized");	
	    				    		}
	    				    		
    				    		} else if( is_int($perms[$path]) && isSet($_SESSION["ouser"]) && $_SESSION["ouser"]->ouser_permission_level > $perms[$path] ){
	    				    		$this->throwError("You cannot access this resource.",403,"Forbidden");	
    				    		}
    				    		
    				    		
    				    		
				    		}
				    		
				    		if( $perms[$path] === "user" ){ $params["ouser_id"] = $_SESSION["ouser"]->ouser_id; }
							
							if( !$this->isError() ){
								$this->$path($params);                                                  // call method in $this
							}
						} catch (Exception $e){                                                     // handle resulting errors
    					    $this->throwError($e->getMessage());                      				// throw 500 error if an error occurs and apply the message to this object
					    }
						return $this;                                                               // return this which will allow chaining
				    } else if( $path == "" ) {
				        return $this;
					} else {
						
    					$this->throwError("Route not found: $cmd.",404,"general");						// if method not found send 404 error  						
						                                     
					}
					
				
				} else if( !empty($path_array)) {$this->throwError("Route not found: $path.",404,"general"); }
			
			/***********************************************
				
				Handle External Routes (HTTP(S))
				
				    e.g. $obj->route('http://www.myhost.com/cmd/widgets/WWidget/WWidget/myFunction/?myQueryString');
				
			***********************************************/
				
			} else {
				// This is where we want to handle http calls to an external OObject or other web service
			}
			
			return $this;
			
		}
		
		/***********************************************************************
		
			PARSE PATH
			
		***********************************************************************/
		
		public function parsePath($path){
			
			
			$path = preg_split('([\][?])',$path);                                               // split path from query string
			if(count($path) > 1){ parse_str($path[1],$params); } else { $params = array(); }    // parse query string into $params array
			$path = $path[0];                                                                   // reset path to a clean path string
			
			$path_array = preg_split('[/]',$path,NULL,PREG_SPLIT_NO_EMPTY);                     // split path into an array of paths
			$path = "/";                                                                        // reset path to store only $used path_array elements
			
			$routes = unserialize(__ROUTES__);
			if( !empty($path_array) && isSet($routes[$path_array[0]]) ){ $base_path = $routes[array_shift($path_array)]; } else { $base_path = ""; }
			
			return array("path_array"=>$path_array,"path"=>$path,"base_path"=>$base_path,"params"=>$params);
			
		}
		
		/***********************************************************************
		
			HAS PERMISSION
			
		***********************************************************************/
		
		public function hasPermission($object){ 
			
		}
		
		public function addCustomPath($path,$keyword){ $this->custom_paths[$keyword] = new stdClass; $this->custom_paths[$keyword]->path = $path; }
		
		private function setObject($obj){ $this->object = $obj;}                      // set the object type of this class
		
		// used for general error handling
		
		public function throwError($message,$status_code=500,$type='general'){
			$this->is_error = TRUE;
    		if( empty($this->errors) ){ $this->errors = []; }
    		$this->errors[$type] = $message;
    		$this->status_code = $status_code;
		}
		
		public function isError(){ return $this->is_error; }
		
		public function getStatusCode(){ return $this->status_code; }                                // gets the internal status code
		public function getContentType(){ return $this->content_type; }                              // gets the internal content type
		public function setCOntentType($type){ if($this->content_type != 'text/html'){ $this->content_type = $type; } }
		public function setCustomRouter($router){ $this->custom_router = $router; }
		public function cleanUp(){}
		
		public function getPermissions(){ return $this->permissions; }
		
		
		
		
		
	}
	