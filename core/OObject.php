<?php

	/***********************************************************************
	
	Obray - Super lightweight framework.  Write a little, do a lot.
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
		
	********************************************************************************************************************/
	
	Class OObject {
	
		public $delegate = FALSE;
		
		public function route( $path ) {
			
			$cmd = $path;												// store the original path
			
			if( !preg_match('(http[s]?://)',$path) ){
			
				$path = preg_replace('(/obray/|/cmd/)','',$path);		// remove the cmd or obray
				 
				//	Determine which object/function to call by parsing the path
				$path_array = preg_split('@([/][O][A-Z][a-zA-Z0-9]*)@',$path,-1,PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
				$class_path = _SELF_ . $path_array[0] .'/';				// determine the path to the class file
				$obj = str_replace('/','',$path_array[1]);				// parse the object
				
				// If file doesn't exist for the specified path/object attempt to call it as a function of this object
				if (!file_exists( $class_path . $obj . '/' . $obj . '.php' ) ) {
						
						$path = split('\?',$path);
						parse_str($path[1],$params);
						$path = str_replace('/','',$path[0]);
						if( method_exists($this,$path) ){
							$this->$path($params);
							return $this;
						} else {
							echo "The method you attempted does not exist.  You tried to call: ".$cmd;
						}
				
				// If the class and file exist then generate an instance of the object and call objects route to call the function
				} else {
					
					$path = $path_array[2];
					require_once ( $class_path . $obj . '/' . $obj . '.php' );
					if (!class_exists( $obj )) {
						die();
					} else {
						
						/*** Factory ***/ 
			    		$obj = new $obj;								// dynamically create the specified obj
				        $obj = $obj->route($path);						// call the objects route function to call the specified function
				        return $obj;									// return the object (this allows chaining)
						
					}
				}
			
			} else  {
				echo "hello";
			}
			
		}
		
		
		
		
	}
	