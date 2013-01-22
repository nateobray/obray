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
	
	require_once 'dbug.php';
	require_once 'OObject.php';
	require_once 'ORouter.php';
	require_once 'OView.php';
	
	if (!class_exists( 'OObject' )) { die(); }
	
	/********************************************************************************************************************
		
		ORouter:	ORouter is an OObject class that adds additional response handling.  Router is primarily responsible
		            for handling incoming HTTP requests from the server and handing back appropriate responses from
		            OOjbects.
		
	********************************************************************************************************************/
	
	Class ORouter extends OObject{
		
		public function route($path,$params=array()){
			
			$obj = parent::route($path,$params);                                                     // Call the parent class default route function
			
			$status_codes = array(                                                                   // available status codes that the application can return to the browser
			 500 => "Internal Server Error",                                                         // 500 - Internal Server Error
			 404 => "Not Found",                                                                     // 404 - Not Found
			 200 => "OK"                                                                             // 200 - OK (default)
			);
			
			if(!headers_sent()){ header("HTTP/1.0 ".$obj->getStatusCode()." " . $status_codes[$obj->getStatusCode()] );}    // set HTTP Header
			if(!headers_sent()){ header("Content-Type: " . $obj->getContentType() ); }                                      // set Content-Type
			
			switch($obj->getContentType()){                                                          // handle OObject content types
    			
    			 case 'application/json':                                                            // Handle JSON (default)
    			 
    			     echo json_encode($obj,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
    			     break;
    			 
    			 case 'text/html':                                                                   // Handle HTML
    			 
    			     break;
    			     
    			 case 'application/xml':                                                             // Handle XML
    			 
    			     break;
    			
			}
			
			return $obj;
			
		}
		
	}
	
	
