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
					
					Router is a way of instantiating objects and calling public function with an object based on
		
	********************************************************************************************************************/
	
	Class OObject {
	
		public $object = '';
		public $status_code = '200';
		private $delegate = FALSE;
		private $starttime;
		public $content_type = 'application/json';
		private $stack = array();
		
		public function __construct(){
			$this->starttime = microtime(TRUE);
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
							$this->throwError(500,"Could not find object: $obj");                   // if we can't find the object class throw an error
							return $obj;                                                            // return object
						} else {                                                                    // if we can find the object start the factory
							try{                                                                    // handle errors and return if necessary
    				    		$obj = new $obj;								                    // dynamically create the specified obj
    				    		$obj->setObject($obj);                                              // set the object name
    					        $obj->route($path,$params);						                    // call the objects route function to call the specified function
					        } catch (Exception $e){                                                 // catch to handle exception
    					        $this->throwError(500,$e->getMessage());                            // set and return error message and status
					        } 
					        return $obj;									                        // return the object (this allows chaining)
						}
						break;                                                                      // if we find an object then we are done with this loop as we only want to find one per path
					} else {
						$path .= $obj . '/';                                                        // set unused $obj from the $path_array to the $path to later be used to call a function in an object
					}
				}
				
				/***********************************************
    				ASSEMBLY LINE: Attempt to call a function
    				               of an object created in the
    				               factory.
    			***********************************************/
				
				if(count($path_array) == 0){
					$path = str_replace('/','',$path);
					if( method_exists($this,$path) ){
					   try{
						$this->$path($params);
						} catch (Exception $e){
    					    $this->throwError(500,$e->getMessage());
					    } 
						return $this;
					} else {
						$this->throwError(404,"Not Found");
					}
				}
			
			/***********************************************
				
				Handle External Routes (HTTP(S))
				
				    e.g. $obj->route('http://www.myhost.com/cmd/widgets/WWidget/WWidget/myFunction/?myQueryString');
				
			***********************************************/
				
			} else {
				
			}
			
			return $this;
			
		}
		
		public function setObject($obj){
			$this->object = get_class($obj);
		}
		
		public function throwError($status_code,$message){
    		$this->status_code = $status_code;
    		$this->error_message = $message;
		}
		
		public function getStatusCode(){ return $this->status_code; }
		public function getContentType(){ return $this->content_type; }
		
		
		
		
		
		
		
	}
	