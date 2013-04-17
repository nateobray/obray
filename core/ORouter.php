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
	
	
	require_once 'settings.php';										                  // see if a setting file exists for a given application (looks at the base path where your obray.php file exists)
	
	require_once 'dbug.php';                                                                        // easy readout function (i.e. new dBug($yourvariable);
	require_once 'functions.php';
	require_once 'OObject.php';                                                                     // the base object for all obray objects (basically everything will extend this or a class that has already extended it)
	require_once 'OView.php';                                                                       // object that extends OObject but is specifically for an HTML view
	require_once 'ODBO.php';                                                                        // object that extends OObject but includes database functionality and table definition support
	
	if (!class_exists( 'OObject' )) { die(); }
	
	/********************************************************************************************************************
		
		ORouter:	ORouter is an OObject class that adds additional response handling.  Router is primarily responsible
		            for handling incoming HTTP/1.1 requests from the server and handing back appropriate responses from
		            OOjbects.  This includes:
		            
		            1.	Setting the HTTP/1.1 status codes
		            2.	Setting the content-type
		            3.	Returning the final object for output
		            
	********************************************************************************************************************/
	
	Class ORouter extends OObject{
		
		public function route($path,$params=array(),$direct=FALSE){
			
			$obj = parent::route($path,$params,$direct);												// Call the parent class default route function
			$obj->cleanUp();
			
			/*****************************************************************************************
				
				1.	Setting the HTTP/1.1 status codes
					
					Hypertext Transfer Protocol -- HTTP/1.1
					SRC: http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
					
					(please see source for additional information on how these should be used)
				
			*****************************************************************************************/
			
			$status_codes = array(																	// available status codes that the application can return to the browser
			 
			 // Successful 2xx
			 
			 200 => "OK",																			// 200 - OK (default)
			 201 => "Created",																		// 201 - The request has been fulfilled and resulted in a new resource being created.
			 202 => "Accepted",																		// 202 - The request has been accepted for processing, but the processing has not been completed.
			 203 => "Non-Authoritative Information",												// 203 - The returned metainformation in the entity-header is not the definitive set as available from the origin server, but is gathered from a local or a third-party copy.
			 204 => "No Content",																	// 204 - The server has fulfilled the request but does not need to return an entity-body, and might want to return updated metainformation.
			 205 => "Reset Content",																// 205 - The server has fulfilled the request and the user agent SHOULD reset the document view which caused the request to be sent.
			 206 => "Partial Content",																// 206 - The server has fulfilled the partial GET request for the resource. 
			 
			 // Redirection 3xx
			 
			 300 => "Multiple Choices",																// 300 - The requested resource corresponds to any one of a set of representations, each with its own specific location, and agent- driven negotiation information (section 12) is being provided so that the user (or user agent) can select a preferred representation and redirect its request to that location.
			 301 => "Moved Permanently",															// 301 - The requested resource has been assigned a new permanent URI and any future references to this resource SHOULD use one of the returned URIs.
			 302 => "Found",																		// 302 - The requested resource resides temporarily under a different URI.
			 303 => "See Other",																	// 303 - The response to the request can be found under a different URI and SHOULD be retrieved using a GET method on that resource.
			 304 => "Not Modified",																	// 304 - If the client has performed a conditional GET request and access is allowed, but the document has not been modified, the server SHOULD respond with this status code.
			 305 => "Use Proxy",																	// 305 - The requested resource MUST be accessed through the proxy given by the Location field.
			 307 => "Temporary Redirect",															// 307 - The requested resource resides temporarily under a different URI.
			 
			 // Client Error 4xx
			 
			 400 => "Bad Request",																	// 400 - The request could not be understood by the server due to malformed syntax. The client SHOULD NOT repeat the request without modifications.
			 401 => "Unauthorized",																	// 401 - The request requires user authentication. The response MUST include a WWW-Authenticate header field (section 14.47) containing a challenge applicable to the requested resource.
			 402 => "Payment Required",																// 402 - This code is reserved for future use.
			 403 => "Forbidden",																	// 403 - The server understood the request, but is refusing to fulfill it. Authorization will not help and the request SHOULD NOT be repeated. 
			 404 => "Not Found",																	// 404 - The server has not found anything matching the Request-URI. No indication is given of whether the condition is temporary or permanent.
			 405 => "Method Not Allowed",															// 405 - The method specified in the Request-Line is not allowed for the resource identified by the Request-URI. The response MUST include an Allow header containing a list of valid methods for the requested resource.
			 406 => "Not Acceptable",																// 406 - The resource identified by the request is only capable of generating response entities which have content characteristics not acceptable according to the accept headers sent in the request.
			 407 => "Proxy Authentication Required",												// 407 - This code is similar to 401 (Unauthorized), but indicates that the client must first authenticate itself with the proxy.
			 408 => "Request Timeout",																// 408 - The client did not produce a request within the time that the server was prepared to wait. The client MAY repeat the request without modifications at any later time.
			 409 => "Conflict",																		// 409 - The request could not be completed due to a conflict with the current state of the resource. This code is only allowed in situations where it is expected that the user might be able to resolve the conflict and resubmit the request.
			 410 => "Gone",																			// 410 - The requested resource is no longer available at the server and no forwarding address is known. This condition is expected to be considered permanent. Clients with link editing capabilities SHOULD delete references to the Request-URI after user approval.
			 411 => "Length Required",																// 411 - The server refuses to accept the request without a defined Content- Length. The client MAY repeat the request if it adds a valid Content-Length header field containing the length of the message-body in the request message.
			 412 => "Precondition Failed",															// 412 - The precondition given in one or more of the request-header fields evaluated to false when it was tested on the server. This response code allows the client to place preconditions on the current resource metainformation (header field data) and thus prevent the requested method from being applied to a resource other than the one intended.
			 413 => "Request Entity Too Large",														// 413 - The server is refusing to process a request because the request entity is larger than the server is willing or able to process. The server MAY close the connection to prevent the client from continuing the request.
			 414 => "Request-URI Too Long",															// 414 - The server is refusing to service the request because the Request-URI is longer than the server is willing to interpret.
			 415 => "Unsupported Media Type",														// 415 - The server is refusing to service the request because the entity of the request is in a format not supported by the requested resource for the requested method.
			 416 => "Requested Range Not Satisfiable",												// 416 - A server SHOULD return a response with this status code if a request included a Range request-header field (section 14.35), and none of the range-specifier values in this field overlap the current extent of the selected resource, and the request did not include an If-Range request-header field.
			 417 => "Expectation Failed",															// 417 - The expectation given in an Expect request-header field (see section 14.20) could not be met by this server, or, if the server is a proxy, the server has unambiguous evidence that the request could not be met by the next-hop server.
			 
			 // Server Error 5xx
			 //Response status codes beginning with the digit "5" indicate cases in which the server is aware that it has erred or is incapable of performing the request. Except when responding to a HEAD request, the server SHOULD include an entity containing an explanation of the error situation, and whether it is a temporary or permanent condition. User agents SHOULD display any included entity to the user. These response codes are applicable to any request method.
			 
			 500 => "Internal Server Error",														// 500 - The server encountered an unexpected condition which prevented it from fulfilling the request.
			 501 => "Not Implemented", 																// 501 - The server does not support the functionality required to fulfill the request. This is the appropriate response when the server does not recognize the request method and is not capable of supporting it for any resource.
			 502 => "Bad Gateway",																	// 502 - The server, while acting as a gateway or proxy, received an invalid response from the upstream server it accessed in attempting to fulfill the request.
			 503 => "Service Unavailable",															// 503 - The server is currently unable to handle the request due to a temporary overloading or maintenance of the server. The implication is that this is a temporary condition which will be alleviated after some delay. If known, the length of the delay MAY be indicated in a Retry-After header. If no Retry-After is given, the client SHOULD handle the response as it would for a 500 response.
			 504 => "Gateway Timeout",																// 504 - The server, while acting as a gateway or proxy, did not receive a timely response from the upstream server specified by the URI (e.g. HTTP, FTP, LDAP) or some other auxiliary server (e.g. DNS) it needed to access in attempting to complete the request.
			 505 => "HTTP Version Not Supported"													// 505 - The server does not support, or refuses to support, the HTTP protocol version that was used in the request message. 
			 
			);
			
			if( $obj->getStatusCode() == 401 ){	header('WWW-Authenticate: Basic realm="'.__APP__.'"');
			}
			
			if(!headers_sent()){ header("HTTP/1.1 ".$obj->getStatusCode()." " . $status_codes[$obj->getStatusCode()] );}    // set HTTP Header
			
			/*****************************************************************************************
				
				2.	Setting the content-type
				
			*****************************************************************************************/
			
			switch($obj->getContentType()){                                                          // handle OObject content types
    			
    			 case 'application/json':                                                            // Handle JSON (default)
    			 
    			     echo json_encode($obj,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
    			     break;
    			 
    			 case 'text/html':                                                                   // Handle HTML
    			 
    			     break;
    			     
    			 case 'application/xml':                                                             // Handle XML
    			 
    			     break;
    			
			}
			
			/*****************************************************************************************
				
				3.	Returning the final object for output
				
			*****************************************************************************************/
			
			if(!headers_sent()){ header("Content-Type: " . $obj->getContentType() ); }                                      // set Content-Type
			
			return $obj;
			
		}
		
	}
	
	
