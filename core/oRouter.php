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

	namespace obray;
	if (!class_exists( 'obray\oObject' )) { die(); }

	/********************************************************************************************************************

		ORouter:	ORouter is an OObject class that adds additional response handling.  Router is primarily responsible
		            for handling incoming HTTP/1.1 requests from the server and handing back appropriate responses from
		            OOjbects.  This includes:

		            1.	Setting the HTTP/1.1 status codes
		            2.	Setting the content-type
		            3.	Returning the final object for output

	********************************************************************************************************************/

	Class oRouter extends oObject{

		public function route($path,$params=array(),$direct=FALSE){

			$php_input = file_get_contents("php://input");
			if( !empty($php_input) && empty($params['data']) ){
				if( $_SERVER["CONTENT_TYPE"] === 'application/json' ){
					$params = (array)json_decode($php_input);
				} else {
					$params["data"] = $php_input;
				}				
			}

			$start_time = microtime(TRUE);
			if(defined('__TIMEZONE__')){ date_default_timezone_set(__TIMEZONE__); }
			$obj = parent::route($path,$params,$direct);												// Call the parent class default route function

			/*****************************************************************************************

				1.	Setting the HTTP/1.1 status codes

					Hypertext Transfer Protocol -- HTTP/1.1
					SRC: http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html

					(please see source for additional information on how these should be used)

			*****************************************************************************************/

			$status_codes = array(																	// available status codes that the application can return to the browser
			 
			 // 
			 101 => 'Permission Denied',

			 // Successful 2xx

			 200 => 'OK',																			// 200 - OK (default)
			 201 => 'Created',																		// 201 - The request has been fulfilled and resulted in a new resource being created.
			 202 => 'Accepted',																		// 202 - The request has been accepted for processing, but the processing has not been completed.
			 203 => 'Non-Authoritative Information',												// 203 - The returned metainformation in the entity-header is not the definitive set as available from the origin server, but is gathered from a local or a third-party copy.
			 204 => 'No Content',																	// 204 - The server has fulfilled the request but does not need to return an entity-body, and might want to return updated metainformation.
			 205 => 'Reset Content',																// 205 - The server has fulfilled the request and the user agent SHOULD reset the document view which caused the request to be sent.
			 206 => 'Partial Content',																// 206 - The server has fulfilled the partial GET request for the resource.

			 // Redirection 3xx

			 300 => 'Multiple Choices',																// 300 - The requested resource corresponds to any one of a set of representations, each with its own specific location, and agent- driven negotiation information (section 12) is being provided so that the user (or user agent) can select a preferred representation and redirect its request to that location.
			 301 => 'Moved Permanently',															// 301 - The requested resource has been assigned a new permanent URI and any future references to this resource SHOULD use one of the returned URIs.
			 302 => 'Found',																		// 302 - The requested resource resides temporarily under a different URI.
			 303 => 'See Other',																	// 303 - The response to the request can be found under a different URI and SHOULD be retrieved using a GET method on that resource.
			 304 => 'Not Modified',																	// 304 - If the client has performed a conditional GET request and access is allowed, but the document has not been modified, the server SHOULD respond with this status code.
			 305 => 'Use Proxy',																	// 305 - The requested resource MUST be accessed through the proxy given by the Location field.
			 307 => 'Temporary Redirect',															// 307 - The requested resource resides temporarily under a different URI.

			 // Client Error 4xx

			 400 => 'Bad Request',																	// 400 - The request could not be understood by the server due to malformed syntax. The client SHOULD NOT repeat the request without modifications.
			 401 => 'Unauthorized',																	// 401 - The request requires user authentication. The response MUST include a WWW-Authenticate header field (section 14.47) containing a challenge applicable to the requested resource.
			 402 => 'Payment Required',																// 402 - This code is reserved for future use.
			 403 => 'Forbidden',																	// 403 - The server understood the request, but is refusing to fulfill it. Authorization will not help and the request SHOULD NOT be repeated.
			 404 => 'Not Found',																	// 404 - The server has not found anything matching the Request-URI. No indication is given of whether the condition is temporary or permanent.
			 405 => 'Method Not Allowed',															// 405 - The method specified in the Request-Line is not allowed for the resource identified by the Request-URI. The response MUST include an Allow header containing a list of valid methods for the requested resource.
			 406 => 'Not Acceptable',																// 406 - The resource identified by the request is only capable of generating response entities which have content characteristics not acceptable according to the accept headers sent in the request.
			 407 => 'Proxy Authentication Required',												// 407 - This code is similar to 401 (Unauthorized), but indicates that the client must first authenticate itself with the proxy.
			 408 => 'Request Timeout',																// 408 - The client did not produce a request within the time that the server was prepared to wait. The client MAY repeat the request without modifications at any later time.
			 409 => 'Conflict',																		// 409 - The request could not be completed due to a conflict with the current state of the resource. This code is only allowed in situations where it is expected that the user might be able to resolve the conflict and resubmit the request.
			 410 => 'Gone',																			// 410 - The requested resource is no longer available at the server and no forwarding address is known. This condition is expected to be considered permanent. Clients with link editing capabilities SHOULD delete references to the Request-URI after user approval.
			 411 => 'Length Required',																// 411 - The server refuses to accept the request without a defined Content- Length. The client MAY repeat the request if it adds a valid Content-Length header field containing the length of the message-body in the request message.
			 412 => 'Precondition Failed',															// 412 - The precondition given in one or more of the request-header fields evaluated to false when it was tested on the server. This response code allows the client to place preconditions on the current resource metainformation (header field data) and thus prevent the requested method from being applied to a resource other than the one intended.
			 413 => 'Request Entity Too Large',														// 413 - The server is refusing to process a request because the request entity is larger than the server is willing or able to process. The server MAY close the connection to prevent the client from continuing the request.
			 414 => 'Request-URI Too Long',															// 414 - The server is refusing to service the request because the Request-URI is longer than the server is willing to interpret.
			 415 => 'Unsupported Media Type',														// 415 - The server is refusing to service the request because the entity of the request is in a format not supported by the requested resource for the requested method.
			 416 => 'Requested Range Not Satisfiable',												// 416 - A server SHOULD return a response with this status code if a request included a Range request-header field (section 14.35), and none of the range-specifier values in this field overlap the current extent of the selected resource, and the request did not include an If-Range request-header field.
			 417 => 'Expectation Failed',															// 417 - The expectation given in an Expect request-header field (see section 14.20) could not be met by this server, or, if the server is a proxy, the server has unambiguous evidence that the request could not be met by the next-hop server.

			 // Server Error 5xx
			 //Response status codes beginning with the digit '5' indicate cases in which the server is aware that it has erred or is incapable of performing the request. Except when responding to a HEAD request, the server SHOULD include an entity containing an explanation of the error situation, and whether it is a temporary or permanent condition. User agents SHOULD display any included entity to the user. These response codes are applicable to any request method.

			 500 => 'Internal Server Error',														// 500 - The server encountered an unexpected condition which prevented it from fulfilling the request.
			 501 => 'Not Implemented', 																// 501 - The server does not support the functionality required to fulfill the request. This is the appropriate response when the server does not recognize the request method and is not capable of supporting it for any resource.
			 502 => 'Bad Gateway',																	// 502 - The server, while acting as a gateway or proxy, received an invalid response from the upstream server it accessed in attempting to fulfill the request.
			 503 => 'Service Unavailable',															// 503 - The server is currently unable to handle the request due to a temporary overloading or maintenance of the server. The implication is that this is a temporary condition which will be alleviated after some delay. If known, the length of the delay MAY be indicated in a Retry-After header. If no Retry-After is given, the client SHOULD handle the response as it would for a 500 response.
			 504 => 'Gateway Timeout',																// 504 - The server, while acting as a gateway or proxy, did not receive a timely response from the upstream server specified by the URI (e.g. HTTP, FTP, LDAP) or some other auxiliary server (e.g. DNS) it needed to access in attempting to complete the request.
			 505 => 'HTTP Version Not Supported'													// 505 - The server does not support, or refuses to support, the HTTP protocol version that was used in the request message.

			);
			
			if( $obj->getStatusCode() == 401 ) {
				
				header('WWW-Authenticate: Basic realm="application"');
				if( method_exists($obj, "auth") ) {
					$obj->auth();
				}
			}
			$content_type = $obj->getContentType();

			if(!headers_sent()){ header('HTTP/1.1 '.$obj->getStatusCode().' ' . $status_codes[$obj->getStatusCode()] );}    // set HTTP Header
			if( $content_type == 'text/table' ){ $tmp_type = 'text/table'; $content_type = 'text/html';  }
			if(!headers_sent()){ header('Content-Type: ' . $content_type ); }                              					// set Content-Type
			if( !empty($tmp_type) ){ $content_type = $tmp_type; }

			//if(!headers_sent()){ header('Content-Type: ' . 'application/json' ); }
			//$content_type = 'text/csv';
			
			//if(!headers_sent()){ header('Access-Control-Allow-Origin: *'); }
			//if(!headers_sent()){ header('Access-Control-Allow-Headers: Obray-Token'); }

			/*****************************************************************************************

				2.	Setting the content-type

			*****************************************************************************************/

			$obj->cleanUp();
			if( PHP_SAPI === 'cli' ){ $content_type = 'console'; }
			switch($content_type){  		                                                         // handle OObject content types

    			 case 'application/json':                                                            // Handle JSON (default)

					$obj->runtime = (microtime(TRUE) - $start_time)*1000;
					$json = json_encode($obj,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
					if( $json === FALSE ){ $json = json_encode($obj,JSON_PRETTY_PRINT); }
					if( $json ){ echo $json; } else { echo 'There was en error encoding JSON.'; }
					break;

				case "application/msgpack":

					$obj->runtime = (microtime(TRUE) - $start_time)*1000;
					$msg = msgpack_pack($obj);
					if( !empty($msg) ){ echo $msg; } else { echo 'There was en error encoding JSON.'; }
					break;

    			 case 'text/html':                                                                   // Handle HTML

    			 	$obj->runtime = (microtime(TRUE) - $start_time)*1000;
    			 	if(!headers_sent()){ header('Server-Runtime: ' . $obj->runtime . 'ms' ); }    	 // set header runtime
    			 	echo $obj->html;
					break;
				
				case 'text/csv': $extension = 'csv'; $separator = ',';
				case 'text/tsv': 
					
					if( empty($extension) ){ $extension = 'tsv'; }
					if( empty($separator) ){ $separator = "\t"; }
				
					header("Content-disposition: attachment; filename=\"".$obj->object.".".$extension."\"");
					header('Content-Type: application/octet-stream; charset=utf-8;');
					header("Content-Transfer-Encoding: utf-8");
					
				case 'text/table':
					
					$withs = array();
					if( !empty($obj->table_definition) ){
						
						forEach( $obj->table_definition as $name => $col ){
							forEach( $col as $key => $prop ){ if( !in_array($key,['primary_key','label','required','data_type','type','slug_key','slug_value']) ){ $withs[] = $key; } }
						}
					
					 }
					
					if( !empty($extension) ){ $fp = fopen('php://output', 'w'); }
					if( !empty($obj->data) ){ 
						$obj->data = $this->getCSVRows($obj->data);
						
						$columns = array();
						$biggest_row = new stdClass(); $biggest_row->index = 0; $biggest_row->count = 0;
						forEach( $obj->data as $i => $array ){
							$new = array_keys($array);
							$columns = array_merge($columns,$new);
							$columns = array_unique($columns);
						}
						
						$path = preg_replace('/with=[^&]*/','',$path);$path = str_replace('?&','?',$path);$path = str_replace('&&','&',$path);
						
						if( !empty($extension) ){ fputcsv($fp,$columns,$separator); } else { 
							echo '<html>';
							echo '<head><link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css"><script src="https://code.jquery.com/jquery-1.11.2.min.js"></script><script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script></head>';
							echo '<body>';
							$csv_path = str_replace('otable','ocsv',$path);
							$tsv_path = str_replace('otable','otsv',$path);
							$json_path = str_replace(['?otable','&otable'],'',$path);
							
							
							$col_dropdown = '<div class="btn-group" role="group"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Cols <span class="caret"></span></button><ul class="dropdown-menu" role="menu">';
							forEach( $columns as $col ){
								$col_dropdown .='<li><a href="'.$path.'">'.$col.'</a></li>';
							}
							$col_dropdown .='</ul></div>';
							
							$with_dropdown = '<div class="btn-group" role="group"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">With <span class="caret"></span></button><ul class="dropdown-menu" role="menu">';
							forEach( $withs as $with ){
								$with_dropdown .='<li><a href="'.$path.'&with='.$with.'">'.$with.'</a></li>';
							}
							$with_dropdown .='</ul></div>';
							
							
							echo '<div class="pull-right"><div class="btn-group">'.$col_dropdown.$with_dropdown.'<a class="btn btn-default" target="_blank" href="'.$csv_path.'">Download CSV</a><a target="_blank" class="btn btn-default" href="'.$tsv_path.'">Download TSV</a><a target="_blank" class="btn btn-default" href="'.$json_path.'">Show JSON</a>&nbsp;</div></div> <h2>'.$obj->object.'</h2> <table class="table table-bordered table-striped table-condensed" cellpadding="3" cellspacing="0">'; $this->putTableRow($columns,'tr','th'); }
						
						forEach( $obj->data as $index => $row_data ){
							$row = array_fill_keys($columns,'');
							$row = array_merge($row,$row_data);
							if( !empty($extension) ){ fputcsv($fp,$row,$separator); } else { $this->putTableRow($row); }
							flush();
						}
						if( $content_type = 'text/html' ){ echo '</table></body>'; }
						
					}
					
					break;

				case 'console':

					$obj->runtime = (microtime(TRUE) - $start_time)*1000;
					$this->console("%s","\n\n****************************************************\n","WhiteBold");
					$this->console("%s","\tResponse Object\n","WhiteBold");
					$this->console("%s","****************************************************\n\n","WhiteBold");
					$this->console($obj);
					$this->console("\n");
					break;
					
    			case 'application/xml':                                                             // Handle XML
    				break;
    			case 'text/css';
    				echo $obj->html;
    			case 'image/jpeg':
    				echo $obj->html;
    			    break;
					
			}
			
			/*****************************************************************************************
				
				3.	Returning the final object for output
				
			*****************************************************************************************/
			
			return $obj;

		}
		
		private function putTableRow( $row,$r='tr',$d='td' ){
			echo '<'.$r.'>';
			forEach( $row as $value ){ echo '<'.$d.' style="white-space: nowrap;">'.$value.'</'.$d.'>'; }
			echo '</'.$r.'>';
		}
		
		private function getCSVRows( $data ){
			
			$columns = array();
			$rows = array();
			if( is_array($data) ){
				forEach( $data as $row => $obj ){
					$rows[] = $this->flattenForCSV($obj,'',$columns);
				}
				
			} else {
				$rows[] = $this->flattenForCSV($data,'',$columns);
			}
			return $rows;
			
		}
		
		private function flattenForCSV($obj,$prefix='',$columns=array()){
			
			$prefix .= (!empty($prefix)?'_':'');
			$flat = array_fill_keys($columns,'');
			if( is_object($obj) || is_array($obj) ){
				forEach( $obj as $key => $value ){
					if( is_object($value) || is_array($value) ){  
						$flat = array_merge($flat,$this->flattenForCSV($value,$prefix.$key));
					} else {
						$flat[$prefix.$key] = $value;
					}
					
				}
			}
			return $flat;
			
			
		}

		

		

	}
?>
