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
	
	/**
	 
	 */
	Class ORouter extends OObject{
		
		public function route($path,$params=array()){
			
			$obj = parent::route($path,$params);
			
			$status_codes = array(
			 500 => "Internal Server Error",
			 404 => "Not Found",
			 200 => "OK"
			);
			
			header("HTTP/1.0 ".$obj->getStatusCode()." " . $status_codes[$obj->getStatusCode()] );
			header("Content-Type: " . $obj->getContentType() );
			
			switch($obj->getContentType()){
    			
    			 case 'application/json':
    			 
    			     echo json_encode($obj,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
    			     break;
    			 
    			 case 'text/html':
    			 
    			     break;
    			     
    			 case 'application/xml':
    			 
    			     break;
    			
			}
			
			
			
		}
		
		
	}
	
	
