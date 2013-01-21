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
	
		public $delegate = FALSE;
		public $class = '';
		
		public function __construct(){
			$this->starttime = microtime(TRUE);
		}
		
		public function route( $path , $params = array() ) {
			
			$cmd = $path;												// store the original path
			
			/***********************************************
				Handle Internal Routes
			***********************************************/
			
			
			if( !preg_match('(http[s]?://)',$path) ){
			
				$path = preg_replace('(/obray/|/cmd/)','',$path);		// remove the cmd or obray
				$path = preg_split('([\][?])',$path);
				if(count($path) > 1){ parse_str($path[1],$params); }
				$path = $path[0];
				
				
				$path_array = preg_split('[/]',$path,NULL,PREG_SPLIT_NO_EMPTY);
				$path = "/";
				while(count($path_array)>0){
					$obj = array_pop($path_array);
					$obj_path = _SELF_ . implode('/',$path_array) . '/';
					if (file_exists( $obj_path . $obj . '/' . $obj . '.php' ) ) { 
						require_once $obj_path . $obj . '/' . $obj . '.php';
						
						if (!class_exists( $obj )) {
							echo "Could not find object: " .$obj;
						} else {
							/*** Factory ***/ 
				    		$obj = new $obj;								// dynamically create the specified obj
				    		$obj->setClass($obj);
					        $obj->route($path,$params);						// call the objects route function to call the specified function
					        return $obj;									// return the object (this allows chaining)
						}
						
						break; 
					} else {
						$path .= $obj . '/';
					}
					
				}
				
				// call function
				if(count($path_array) == 0){
					$path = str_replace('/','',$path);
					if( method_exists($this,$path) ){
						$this->$path($params);
						return $this;
					} else {
						// This is where you can put in a hook for a CMS to parse the path of a page
						echo "The method or object you attempted does not exist.  You tried to call: ".$cmd;
					}
				}
			
			/***********************************************
				Handle External Routes (REST)
			***********************************************/
				
			} else {
				
			}
			
		}
		
		public function setClass($obj){
			$this->class = get_class($obj);
		}
		
		
		
		
	}
	