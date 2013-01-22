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
	
		public $object = '';                                                                        // stores the name of the class
		public $status_code = '200';                                                                // stores the status code of this object 
		private $delegate = FALSE;                                                                  // does this object have a delegate
		private $starttime;                                                                         // records the start time (time the object was created).  Cane be used for performance tuning
		public $content_type = 'application/json';                                                  // stores the content type of this class or how it should be represented externally
		
		public function __construct(){                                                              // object constructor
			$this->starttime = microtime(TRUE);                                                     // start the timer
			 
		}
		
		public function route( $path , $params = array() ) {
			
			$cmd = $path;                                                                           // store the original path
			
			/***********************************************
				
				Handle Internal Routes
				
				    e.g. $new_obj = $obj->route('/cmd/widgets/WClass/myFunction/?myQueryString');   // call function in new object (NOTE: does not modify existing object but will create a new one, also $obj may be $this)
				    e.g. $obj->route('myFunction');                                                 // call function from existing object (NOTE: modifies object potentially)
				
			***********************************************/
			
			if( !preg_match('(http[s]?://)',$path) ){
			
				$path = preg_replace('(/obray/|/cmd/)','',$path);                                   // remove the cmd or obray from path
				$path = preg_split('([\][?])',$path);                                               // split path from query string
				if(count($path) > 1){ parse_str($path[1],$params); }                                // parse query string into $params array
				$path = $path[0];                                                                   // reset path to a clean path string
				
				$path_array = preg_split('[/]',$path,NULL,PREG_SPLIT_NO_EMPTY);                     // split path into an array of paths
				$path = "/";                                                                        // reset path to store only $used path_array elements
				
				/***********************************************
    				FACTORY:  Attempt to create an object from
    				          a path.
    			***********************************************/
				
				while(count($path_array)>0){                                                        // loop through path until we find an valid object
					$obj = array_pop($path_array);                                                  // set object we are going to attempt to find
					$obj_path = _SELF_ . implode('/',$path_array) . '/';                            // setup path to the object we want to find
					if (file_exists( $obj_path . $obj . '/' . $obj . '.php' ) ) {                   // test if object exists (object must be in folder and php file bearing its name
						require_once $obj_path . $obj . '/' . $obj . '.php';                        // if found require_once the file
						if (!class_exists( $obj )) {                                                // see if we can find the object class in the file
							$this->throwError(404,"Could not find object: $obj");                   // if we can't find the object class throw an error
							return $obj;                                                            // return object
						} else {                                                                    // if we can find the object start the factory
							try{                                                                    // handle errors and return if necessary
    				    		$obj = new $obj;								                    // dynamically create the specified obj
    				    		$obj->setObject($obj);                                              // set the object name
    				    		$this->setContentType($obj->content_type);                          // this allows an object to pick up on another objects content type.  This way your objects JSON won't pring in OView
    					        $obj->route($path,$params);						                    // call the objects route function to call the specified function
					        } catch (Exception $e){                                                 // catch to handle exception
    					        $this->throwError(500,$e->getMessage());                            // set and return error message and status
					        } 
					        return $obj;									                        // return the object (this allows chaining)
						}
						break;                                                                      // if we find an object then we are done with this loop as we only want to find one per path
					} else {
						$path = '/'.$obj . $path;                                                   // set unused $obj from the $path_array to the $path to later be used to call a function in an object
					}
				}
				
				/***********************************************
    				ASSEMBLY LINE: Attempt to call a function
    				               of an object created in the
    				               FACTORY.
    			***********************************************/
				
				if(count($path_array) == 0){                                                        // If no objects were found from the path attempt to run it as a function of the current object
					$path = str_replace('/','',$path);                                              // remove the "/"s from the path
					if( method_exists($this,$path) ){                                               // test if method exists in this object and if so attempt to call it
					   try{                                                                         
						$this->$path($params);                                                      // call method in $this
						} catch (Exception $e){                                                     // handle resulting errors
    					    $this->throwError(500,$e->getMessage());                                // throw 500 error if an error occurs and apply the message to this object
					    } 
						return $this;                                                               // return this which will allow chaining
					} else {
						
						// This is where we can handle custom routes.  A good exampel would 
						// be handling a route to a page in a CMS rather than to a specific 
						// object
						if( isSet($this->custom_router) ){                                          // see if a custom router is defined
						  $this->custom_router->route($cmd,$params);                                // call the custom router
						} else {
    						$this->throwError(404,"Route not found: $cmd.");                        // if method not found send 404 error  						
						}                                     
					}
				}
			
			/***********************************************
				
				Handle External Routes (HTTP(S))
				
				    e.g. $obj->route('http://www.myhost.com/cmd/widgets/WWidget/WWidget/myFunction/?myQueryString');
				
			***********************************************/
				
			} else {
				// This is where we want to handle http calls to and external OObject or other web service
			}
			
			return $this;
			
		}
		
		public function setObject($obj){ $this->object = get_class($obj);}                           // set the object type of this class
		
		public function throwError($status_code,$message){                                           // used for error handling to set the proper error parameters
    		$this->status_code = $status_code;                                                       // set the status code parameter
    		$this->error_message = $message;                                                         // set the error message parameter
		}
		
		public function getStatusCode(){ return $this->status_code; }                                // gets the internal status code
		public function getContentType(){ return $this->content_type; }                              // gets the internal content type
		public function setCOntentType($type){ if($this->content_type != 'text/html'){ $this->content_type = $type; } }
		public function setCustomRouter($router){ $this->custom_router = $router; }
		
		
	}
	