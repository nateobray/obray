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

	if (!class_exists( 'OObject' )) { die(); }

	class oSocketFrame {

		public $FIN = FALSE;
		public $opcode;
		public $mask;
		public $len;
		public $msg = "";

	}

	/********************************************************************************************************************

		oWebSocketServer:

			1.  Establish a connection on specified host and port
			2.	Handle new connections
			3.	Loop through all the changed sockets

				//	1.	Get changed socket
				//	2.	Read from changed socket
				//	3.	if EOF then close connection.

	********************************************************************************************************************/

	Class oWebSocketServer extends ODBO {

		public function __construct($params){

			/*************************************************************************************************

				1.  Establish a connection on specified host and port

				//	1.	retreive host and ports or set them to defaults
				//	2.	determine the protocol to connect (essentially on client side ws or wss) and create
				//		context.
				//	3.	establish connection or abort on error
				//	4.	listen for data on our connections

			*************************************************************************************************/

			//	1.	retreive host and ports or set them to defaults
			$this->host = !empty($params["host"])?$params["host"]:"localhost";
			$this->port = !empty($params["port"])?$params["port"]:"80";
			$this->debug = FALSE;
			if( !empty($params["debug"]) ){
				$this->debug = TRUE;
			}

			//	2.	determine the protocol to connect (essentially on client side ws or wss) and create
			//		context.
			if( __WEB_SOCKET_PROTOCOL__ == "ws" ){

				$protocol = "tcp";
				$context = 		stream_context_create();

			} else {

				$protocol = "ssl";
				try{
					$context = 	stream_context_create( array( "ssl" => array( "local_cert"=>__WEB_SOCKET_CERT__, "local_pk"=>__WEB_SOCKET_KEY__, "passphrase" => __WEB_SOCKET_KEY_PASS__ ) ) );
				} catch( Exception $err ){
					$this->console("Unable to create stream context: ".$err->getMessage()."\n");
					$this->throwError("Unable to create stream context: ".$err->getMessage());
					return;
				}

			}

			//	3.	establish connection or abort on error
			$listenstr = 	$protocol."://".$this->host.":".$this->port;
			$this->console("Binding to ".$this->host.":".$this->port." over ".$protocol."\n");
			$this->socket = @stream_socket_server($listenstr,$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$context);

			if( !is_resource($this->socket) ){
				$this->console("%s",$errstr."\n","RedBold");
				$this->throwError($errstr);
				return;
			}

			$this->console("%s","Listening...\n","GreenBold");

			$this->sockets = array( $this->socket );
			$this->cData = array();
			$this->subscriptions = array();
			$this->obray_clients = array();

			//	4.	listen for data on our connections
			while(true){

				$changed = $this->sockets; $null = NULL;
				stream_select( $changed, $null, $null, 0, 200000 );

				/*************************************************************************************************

					2. Handle new connections

				*************************************************************************************************/

				if( in_array($this->socket,$changed) ){

					$new_socket = $this->connect($this->socket, $changed);

					// removes original socket from the changed array (so we don't keep looking for a new connections)
					$found_socket = array_search($this->socket, $changed);
					unset($changed[$found_socket]);
					if( !$new_socket ){	continue; }

				}

				/*************************************************************************************************

					3.	Handle changes to existing sockets

						//	1.	Get changed socket
						//	2.	Read from changed socket
						//	3.	if EOF then close connection.

				*************************************************************************************************/

				foreach ( array_keys($changed) as $changed_key) {

					//	1.	Get changed socket
					$changed_socket = $changed[$changed_key];
					$info = stream_get_meta_data($changed_socket);

					//	2.	Read from changed socket
					if( !feof($changed_socket) ){

						$buf = $this->fread_stream($changed_socket,100000*1024);
						if( $buf == FALSE ){
							$this->console("%s","Unable to read form socket\n","RedBold");
							$this->disconnect($changed_socket);
							break;
						}

						if( !empty($this->obray_clients[ array_search($changed_socket,$this->sockets) ]) ){

							 $msg = json_decode(trim($buf,"\x00\xff"));
							 $this->console("%s","\tobray-client sending message...");
							 $response = $this->send($msg);
							 if( !empty($response) ){
								 $this->console("%s","done.\n","GreenBold");
							 } else {
								 $this->console("%s","No one listening.\n","RedBold");
							 }

							 $obj = new stdClass();
							 $obj->channels = $response;
							 $obj->type = 'delivery-receipt';
							 $message = json_encode($obj);

							 $this->fwrite_stream( $this->sockets[ array_search($changed_socket,$this->sockets) ] , $message, strlen($message));

						} else {
							$this->decode($buf,$changed_socket);
						}

						// this prevents possible endless loop
         				if( $info['unread_bytes'] <= 0 ){ break; }
						break;

					//	3.	if EOF then close connection.
					} else if( feof($changed_socket) || $info['timed_out'] ){

						// disconnect socket
						$this->disconnect($changed_socket);

						// remove from changed socket array
						$found_socket = array_search($changed_socket, $changed);
						unset($changed[$found_socket]);
						break;

					}

				}

			}

		}

		/********************************************************************************************************************

			connect:

				Check for new connections: Basically we're checking to see if our original socket has
				been added to the changed array and if so we know it has a new connection waiting to be
				handled.

				//	1.	accpet new socket
				//	2.	add socket to socket list
				//	3.	read data sent by the socket
				//	4.	perform websocket handshake
				//	5.	store the client data
				//	6.	notify all users of newely connected user
				//	7.	remove new socket from changed array

		********************************************************************************************************************/

		private function connect( &$socket, &$changed ){

			//	1.	accpet new socket
			$this->console("%s","\nAttempting to connect a new client.\n","YellowBold");
			$new_socket = @stream_socket_accept($socket,5);

			if( !$new_socket ){
				$this->console("%s","\tUnable to connect.\n","RedBold");
				$found_socket = array_search($socket, $changed);
				unset($changed[$found_socket]);
				return FALSE;
			}

			stream_set_blocking($new_socket, false);
			stream_set_read_buffer($new_socket,0);
			stream_set_write_buffer($new_socket,0);

			//	2.	add socket to socket list
			$this->console("\tReading from socket.\n");

			$request = $this->fread_stream($new_socket,100000*1024);
			if( !$request ){
				$found_socket = array_search($socket, $changed);
				unset($changed[$found_socket]);
				return FALSE;
			}
			$this->sockets[] = $new_socket;

			//	4.	perform websocket handshake and retreive user data
			$this->console("\tPerforming websocket handshake.\n");
			$client = $this->handshake($request, $new_socket);
			$this->console($client);
			if( !empty($client->type) && $client->type == 'Browser' && !empty($client->ouser)  ){

				//	5.	store the user data
				$client->websocket_login_datetime = strtotime('now');
				$client->subscriptions = array();
				$found_socket = array_search($new_socket,$this->sockets);
				$this->cData[ $found_socket ] = $client;
				$this->subscribe($found_socket,md5("all"));
				$this->subscribe($found_socket,md5($client->ouser->ouser_id));
				$this->console("%s","\t".$client->ouser->ouser_first_name." ".$client->ouser->ouser_last_name,"BlueBold");
				$this->console("%s"," has logged on.\n","GreenBold");

				//	6.	notify all users of newely connected user
				$response = (object)array( 'channel'=>'all', 'type'=>'broadcast', 'message'=>$client->ouser->ouser_first_name.' '.$client->ouser->ouser_last_name.' connected.' );
				$this->send($response);

				//	7.	remove new socket from changed array
				// removes original socket from changed array (so we don't keep looking for a new connections)
				$found_socket = array_search($socket, $changed);
				unset($changed[$found_socket]);
				$this->console( "\t".(count($this->sockets)-1)." users connected.\n" );
				return $new_socket;

			} else if ( !empty($client->type) && $client->type == 'Device' && !empty($client->odevice) ) {

				//	5.	store the user data
				$client->websocket_login_datetime = strtotime('now');
				$client->subscriptions = array();
				$found_socket = array_search($new_socket,$this->sockets);
				$this->cData[ $found_socket ] = $client;
				$this->subscribe($found_socket,md5("devices"));
				$this->subscribe($found_socket,md5($client->odevice->odevice_UUID));
				$this->console("%s","\t".$client->odevice->odevice_name,"BlueBold");
				$this->console("%s"," has logged on.\n","GreenBold");

				//	6.	notify all users of newely connected user
				$response = (object)array( 'channel'=>'devices', 'type'=>'broadcast', 'message'=>$client->odevice->odevice_name.' connected.' );
				$this->send($response);

				//	7.	remove new socket from changed array
				// removes original socket from changed array (so we don't keep looking for a new connections)
				$found_socket = array_search($socket, $changed);
				unset($changed[$found_socket]);
				$this->console( "\t".(count($this->sockets)-1)." users/devices connected.\n" );
				return $new_socket;

			} else if ( !empty($client->type) && $client->type == 'obray-client' ){

				// keep track of obray client connections
				$this->console("%s","\tConnected obray-client\n","GreenBold");
				$this->obray_clients[array_search($new_socket,$this->sockets)] = TRUE;

			} else {

				$new_headers = array( 0 => "HTTP/1.1 403 Forbidden" );
				$new_headers[] = "Sec-WebSocket-Version: 13";
				$new_headers[] = "\r\n";
				$request = implode("\r\n",$new_headers);
				$this->fwrite_stream($new_socket,$request,strlen($request));

				// abort if unable to find user
				$this->console("%s","\tConnection failed, unable to connect user (not found).\n","RedBold");
				// removes our newely connected socket from our sockets array (aborting the connection)
				$found_socket = array_search($new_socket, $this->sockets);
				unset($this->sockets[$found_socket]);
				return FALSE;

			}

		}

		/********************************************************************************************************************

			disconnect: takes the changed socket and the changed socket array, disconnects the user, the removes user data
						and the socket from the sockets array.

			//	1.	remove the changes socket from the list of sockets
			//	2.	shutdown the socket connection
			//	3.	if client is obray disconnect and return
			//	4.	remove all subscriptions
			//	5.	remove the connection data and socket
			//	6.	notify all users about disconnected connection
			//	7.	broadcasting list of users

		********************************************************************************************************************/

		private function disconnect( $changed_socket ){

			$found_socket = array_search($changed_socket, $this->sockets);
			if( !empty($found_socket) ){

				$this->console("%s","\nAttempting to disconnect index: ".$found_socket."\n","YellowBold");


				//	1.	remove the changes socket from the list of sockets
				unset($this->sockets[$found_socket]);

				//	2.	shutdown the socket connection
				stream_socket_shutdown($changed_socket,STREAM_SHUT_RDWR);

				//	3.	if client is obray disconnect and return
				if( !empty($this->obray_clients[ $found_socket ]) ){
					$this->console("%s","\tobray-client disconnected.\n","RedBold");
					unset($this->obray_clients[ $found_socket ]);
					return;
				}

				//	4.	remove all subscriptions
				forEach( $this->cData[ $found_socket ]->subscriptions as $key => $value ){
					$this->unsubscribe($found_socket,$key);
				}

				//	5.	remove the connection data and socket
				$client = $this->cData[$found_socket];
				if( !empty($client->ouser) ){
					$this->console("%s","\t".$client->ouser->ouser_first_name." ".$client->ouser->ouser_last_name,"BlueBold");	
				} else {
					$this->console("%s","\t".$client->odevice->odevice_name,"BlueBold");	
				}
				
				$this->console("%s"," has logged off.\n","RedBold");
				unset($this->cData[$found_socket]);

				//	6.	notify all users about disconnected connection
				if( !empty($client->ouser) ){
					$response = (object)array( 'channel'=>'all', 'type'=>'broadcast', 'message'=>$client->ouser->ouser_first_name.' '.$client->ouser->ouser_last_name.' disconnected.');
				}
				$this->send($response);

				//	7.	broadcasting list of users
				$this->sendList();

			} else {
				$this->console("%s","\tSocket not found, unable to disconnect.\n","RedBold");
			}

		}

		/********************************************************************************************************************

			onData: this function is called when we are done decoding a full message.  It determines how to handle
					the incoming message.

				//	1.	validate message
				//	2.	switch based on message type
				//		a.	subscribe to channel if not already subscribed
				//		b.	unsubscribe from channel if not already subscribed
				//		c.	send list of users logged in
				//		d.	handle an unknown message type but requires specific message format

		********************************************************************************************************************/

		public function onData( $frame, $changed_socket ){

			//	1.	validate message
			if( $frame->len == 0 ){ return; }
			$msg = json_decode($frame->msg);
			if( empty($msg) || empty($msg->type) ){ return; }
			$channel_hash = FALSE;
			if( !empty($msg->channel) ){ $channel_hash = md5($msg->channel); }
			$found_socket = array_search($changed_socket, $this->sockets);

			//	2.	switch based on message type
			switch( $msg->type ){

				//		a.	subscribe to channel if not already subscribed
				case 'subscribe':

					$this->subscribe($found_socket,$channel_hash);
					break;

				//		b.	unsubscribe from channel if not already subscribed
				case 'unsubscribe':

					$this->unsubscribe($found_socket,$channel_hash);
					break;

				//		c.	send list of users logged in
				case 'list':

					$this->sendList();
					break;

				//		d.	handle an unknown message type but requires specific message format
				default:

					$this->console("Received broadcast, sending...");
					if( empty($msg->channel) ){ $this->console("%s","Invalid messsage format.\n","RedBold"); break; }
					if( empty($msg->type) ){ $this->console("%s","Invalid messsage format.\n","RedBold"); break; }
					if( empty($msg->message) ){ $this->console("%s","Invalid messsage format.\n","RedBold"); break; }

					$response = $this->send($msg);
					if( !empty($response) ){
						$this->console("%s","done\n","GreenBold");
					} else {
						$this->console("%s","No subscribers on ".$msg->channel."\n","RedBold");
					}
					break;

			}

		}

		private function subscribe($found_socket,$channel_hash){

			$this->console("\tReceived subscription, subscribing...");
			if( empty($channel_hash) ){
				$this->console("%s","failed","RedBold"); break;
			}
			if( empty($this->subscriptions[$channel_hash]) ){
				$this->subscriptions[$channel_hash] = array();
			}
			$this->subscriptions[ $channel_hash ][ $found_socket ] = TRUE;
			$this->cData[ $found_socket ]->subscriptions[$channel_hash] = TRUE;
			$this->console("%s","done\n","GreenBold");

		}

		private function unsubscribe($found_socket,$channel_hash){

			$this->console("%s","\tReceived unsubscribe, unsubcribing...","RedBold");
			if( !empty($channel_hash) && !empty($this->subscriptions[ $channel_hash ][ $found_socket ]) ){
				unset($this->subscriptions[ $channel_hash ][ $found_socket ]);
				$this->console("%s","done\n","GreenBold");
			} else {
				$this->console("%s","failed","RedBold");
			}

			if( !empty($this->cData[ $found_socket ]->subscriptions[$channel_hash]) ){
				unset($this->cData[ $found_socket ]->subscriptions[$channel_hash]);
			}

			if( count($this->subscriptions[ $channel_hash ]) === 0 ){ unset($this->subscriptions[ $channel_hash ]); }

		}

		private function sendList(){
			$this->console("\tReceived list, sending...");
			$data = array();
			forEach( $this->cData as $user ){
				if( count($data) > 100 ){ break; }
				$data[] = $user;
			}
			$msg = (object)array('channel'=>'all', 'type'=>'list', 'message'=>$data);
			$response = $this->send($msg);
			if( !empty($response) ){
				$this->console("%s","done\n","GreenBold");
			} else {
				$this->console("%s","Unable to deliver message.\n","RedBold");
			}
		}

		private function fwrite_stream($socket, $string) {
		    for ($written = 0; $written < strlen($string); $written += $fwrite) {
				try {
		        	$fwrite = fwrite($socket, substr($string, $written),8192*10);
				} catch (Exception $err){
					$this->console("%s","Write failed (try/catch).","RedBold");
					return FALSE;
				}
				if ($fwrite == FALSE) {
					$this->console("%s","Write failed.","RedBold");
		            return FALSE;
		        }

		    }
		    return $written;
		}

		private function fread_stream($socket,$length){

			$request = ''; $start = microtime(TRUE); $timeout = 2;
			while( !feof($socket) ){
				$new_content = fread($socket, $length);

				if( !empty($new_content) ){
					$fields = unpack( 'Cheader/Csize' , substr($new_content, 0, 16) );
					$fields["size"] -= 128;
					$this->console($fields);
				}

				$request .= $new_content;
				$this->console("%s", "Content :".strlen($new_content)."/".strlen($request)."\n", "GreenBold" );
				if( strlen($new_content) === 0 && strlen($request) !== 0 ){ return $request; }
				$current = microtime(TRUE);
				if( $timeout <= $current-$start ){ $this->console("%s","\tSocket read timed out.\n","RedBold"); return FALSE; }
				usleep(50000);
			}
			return $request;
			
		}

		/********************************************************************************************************************

			send:  takes a message and passes though to all the connections subscribed to the specified channel

			//	1.	valid message
			//	2.	loop through channels
			//	3.	loop through array of sockets in the channel
			//	3.	make sure the socket has not timed out or lost it's connections
			//	4.	send message

		********************************************************************************************************************/

		function send($msg){
			$msg_sent = array();

			//	1.	valid message and channel
			if( empty($msg->channel) ){ return $msg_sent; }
			if( empty($msg->type) ){ return $msg_sent; }
			if( empty($msg->message) ){ return $msg_sent; }

			//	2.	loop through channels
			$channels = explode('|',$msg->channel);
			forEach( $channels as $channel ){

				$channel_hash = md5($channel);
				if( empty($this->subscriptions[ $channel_hash ]) ){ continue; }

				//	2.	loop through array of sockets in the channel
				forEach( $this->subscriptions[ $channel_hash ] as $key => $value ){
					$send_socket = $this->sockets[ $key ];

					//	3.	make sure the socket has not timed out or lost it's connections
					$info = stream_get_meta_data($send_socket);
					if( feof($send_socket) || $info['timed_out'] ){
						$this->disconnect($send_socket);
						continue;
					}

					//	4.	send message
					$msg->channel = $channel;
					$message =  $this->mask( json_encode($msg) );
					if( $this->fwrite_stream($send_socket,$message) == FALSE ){
						$this->disconnect($send_socket);
						$msg_sent[$channel] = FALSE;
					} else {
						$msg_sent[$channel] = TRUE;
					}

				}

			}
			return $msg_sent;

		}

		private function parseRequestHeader( $request ){

			$headers = new stdClass();

			$header_lines = explode("\n",$request);
			if( empty($header_lines) ){ return $headers; }

			$request_info = explode(" ", array_shift($header_lines) );

			//	1.	retreive method and validate
			if( empty($request_info) ){ return $headers; }
			if( trim($request_info[0]) !== 'GET' ){ return $headers; }
			$headers->method = trim($request_info[0]);

			//	2.	retreive path and validate
			if( empty(trim($request_info[1])) ){ return $headers; }
			$headers->uri = trim($request_info[1]);
			
			//	3.	retreive HTTP version major
			if( empty($request_info[2]) ){ return $headers; }
			$http_version = explode(".",str_replace(["HTTPS/","HTTP/"],"",$request_info[2]) );
			if( empty($http_version[0]) ){ return $headers; }
			$headers->http_version_major = intval($http_version[0]);
			if( empty($http_version[1]) ){ return $headers; }
			$headers->http_version_minor =  intval($http_version[1]);

			//	4.	retreive all other headers
			forEach( $header_lines as $header_line ){
				$line = explode(":",$header_line,2);
				if( empty($line) || empty(trim($line[0])) ){ continue; }
				$key = trim($line[0]);
				$headers->$key = !empty($line[1])?trim($line[1]):'';
			}

			return $headers;

		}

		/********************************************************************************************************************

			readHandshake:	

				4.2.1.  Reading the Client's Opening Handshake

				When a client starts a WebSocket connection, it sends its part of the
				opening handshake.  The server must parse at least part of this
				handshake in order to obtain the necessary information to generate
				the server part of the handshake.

				The client's opening handshake consists of the following parts.  If
				the server, while reading the handshake, finds that the client did
				not send a handshake that matches the description below (note that as
				per [RFC2616], the order of the header fields is not important),
				including but not limited to any violations of the ABNF grammar
				specified for the components of the handshake, the server MUST stop
				processing the client's handshake and return an HTTP response with an
				appropriate error code (such as 400 Bad Request).

				//	PARSE HEADER
			
				//	1.  An HTTP/1.1 or higher GET request, including a "Request-URI"
			    //    	[RFC2616] that should be interpreted as a /resource name/
			    //    	defined in Section 3 (or an absolute HTTP/HTTPS URI containing
			    //    	the /resource name/).				
			    //	2.  A |Host| header field containing the server's authority.
			    //	3.  An |Upgrade| header field containing the value "websocket",
        		//		treated as an ASCII case-insensitive value.
        		//	4.  A |Connection| header field that includes the token "Upgrade",
        		//		treated as an ASCII case-insensitive value.
        		//	5.  A |Sec-WebSocket-Key| header field with a base64-encoded (see
        		//		Section 4 of [RFC4648]) value that, when decoded, is 16 bytes in
        		//		length.
        		//	6.   A |Sec-WebSocket-Version| header field, with a value of 13.

        		

		********************************************************************************************************************/

		private function readHandshake( $request ){

			//	PARSE HEADER
			$headers = $this->parseRequestHeader( $request );

			if( 

				//	1.  An HTTP/1.1 or higher GET request, including a "Request-URI"
			    //    	[RFC2616] that should be interpreted as a /resource name/
			    //    	defined in Section 3 (or an absolute HTTP/HTTPS URI containing
			    //    	the /resource name/).
				
				empty($headers->method) || $headers->method !== 'GET' ||
				empty($headers->http_version_major) || $headers->http_version_major < 1 ||
				empty($headers->http_version_minor) || $headers->http_version_minor < 1 ||
				empty($headers->uri) ||

				//	2.  A |Host| header field containing the server's authority.
				
				empty($headers->Host || $headers->Host === __WEB_SOCKET_HOST__.':'.__WEB_SOCKET_PORT__) ||

				//	3.  An |Upgrade| header field containing the value "websocket",
        		//		treated as an ASCII case-insensitive value.
        		
        		empty($headers->Upgrade) || strcasecmp($headers->Upgrade,"websocket") !== 0 ||

        		//	4.  A |Connection| header field that includes the token "Upgrade",
        		//		treated as an ASCII case-insensitive value.
        		
        		empty($headers->Connection) || strcasecmp($headers->Connection,"Upgrade") !== 0 ||

        		//	5.  A |Sec-WebSocket-Key| header field with a base64-encoded (see
        		//		Section 4 of [RFC4648]) value that, when decoded, is 16 bytes in
        		//		length.

        		empty($headers->{'Sec-WebSocket-Key'}) || strlen(base64_decode($headers->{'Sec-WebSocket-Key'})) !== 16 ||

        		//	6.   A |Sec-WebSocket-Version| header field, with a value of 13.
				empty($headers->{'Sec-WebSocket-Version'}) || intval($headers->{'Sec-WebSocket-Version'}) !== 13

			){
				$this->console("%s","Invalid WebSocket Headers!\n","RedBold");
				return FALSE;
			}

			return $headers;


		}

		/********************************************************************************************************************

			sendHandshake:	4.2.2.  Sending the Server's Opening Handshake

	    		More information can be found on this section on what information
	    		is required during the handshake process.
				
				//	1.	N/A
	    		//	2.  The server can perform additional client authentication, for
				//		example, by returning a 401 status code with the corresponding
				//		|WWW-Authenticate| header field as described in [RFC2616].  For
				//		our case we're authenticating anyone with and ouser_id for now.
				//
				//	3&4	N/A
				//
				//	5.  If the server chooses to accept the incoming connection, it MUST
		       	//		reply with a valid HTTP response indicating the following.
				//
					//	1.	A Status-Line with a 101 response code as per RFC 2616
					//		[RFC2616].  Such a response could look like "HTTP/1.1 101
					//		Switching Protocols".
					//
					//	2.	An |Upgrade| header field with value "websocket" as per RFC
					//		2616 [RFC2616].
					//		We're authenticating 
					//
					//	3.	A |Connection| header field with value "Upgrade".
					//
					//	4.	A |Sec-WebSocket-Accept| header field.  The value of this
					//		header field is constructed by concatenating /key/, defined
					//		above in step 4 in Section 4.2.2, with the string "258EAFA5-
					//		E914-47DA-95CA-C5AB0DC85B11", taking the SHA-1 hash of this
					//		concatenated value to obtain a 20-byte value and base64-
					//		encoding (see Section 4 of [RFC4648]) this 20-byte hash.
					//
					//		The ABNF [RFC2616] of this header field is defined as
					//		follows:
					//
					//		Sec-WebSocket-Accept     = base64-value-non-empty
					//		base64-value-non-empty = (1*base64-data [ base64-padding ]) |
					//	                            base64-padding
					//		base64-data      = 4base64-character
					//		base64-padding   = (2base64-character "==") |
					//	                      (3base64-character "=")
	   				//		base64-character = ALPHA | DIGIT | "+" | "/"
				

		********************************************************************************************************************/


		private function sendHandshake( $headers, $conn ){

			//	2.  The server can perform additional client authentication, for
			//		example, by returning a 401 status code with the corresponding
			//		|WWW-Authenticate| header field as described in [RFC2616].  For
			//		our case we're authenticating anyone with and ouser_id for now.

			parse_str( str_replace('/?',"",$headers->uri), $vars );
			$ouser = "obray-client";

			$client = new stdClass();
			$client->type = "obray-client";

			if( !empty($vars["ouser_id"]) ){

				$client->type = "Browser";
				$this->setDatabaseConnection(getDatabaseConnection(true));
				$this->console( "\tretreiving user: /obray/OUsers/get/?ouser_id=".$vars["ouser_id"].'&with=options'."\n" );
				$new_user = $this->route('/obray/OUsers/get/?ouser_id='.$vars["ouser_id"].'&with=options');

				if( !empty($new_user->data[0]) ){
					$client->ouser = new stdClass();
					$client->ouser->ouser_id = $new_user->data[0]->ouser_id;
					$client->ouser->ouser_first_name = $new_user->data[0]->ouser_first_name;
					$client->ouser->ouser_last_name = $new_user->data[0]->ouser_last_name;
					$client->ouser->ouser_group = $new_user->data[0]->ouser_group;
				}

				$client->subscriptions = array( "all" => 1 );
			}

			if( !empty($vars["odevice_UUID"]) ){

				$client->type = "Device";
				$this->setDatabaseConnection(getDatabaseConnection(true));
				$this->console( "\tretreiving device: /m/iOS/oDevices/get/?odevice_UUID=".$vars["odevice_UUID"]."\n" );
				$new_device = $this->route('/m/iOS/oDevices/get/?odevice_UUID='.$vars["odevice_UUID"] );

				if( !empty($new_device->data[0]) ){
					$client->odevice = new stdClass();
					$client->odevice->odevice_id = $new_device->data[0]->odevice_id;
					$client->odevice->odevice_UUID = $new_device->data[0]->odevice_UUID;
					$client->odevice->odevice_name = $new_device->data[0]->odevice_name;
					$client->odevice->odevice_operator = $new_device->data[0]->odevice_operator;
					$client->odevice->odevice_location = $new_device->data[0]->odevice_location;
					$client->odevice->odevice_GPS = $new_device->data[0]->odevice_GPS;
					$client->odevice->odevice_battery = $new_device->data[0]->odevice_battery;
					$client->odevice->odevice_version = $new_device->data[0]->odevice_version;
				} else {
					$client->device = new stdClass();
				}

				$client->subscriptions = array( "devices" => 1 );
			}

			//	5.  If the server chooses to accept the incoming connection, it MUST
	       	//		reply with a valid HTTP response indicating the following.
			//

				//	1.	A Status-Line with a 101 response code as per RFC 2616
				//		[RFC2616].  Such a response could look like "HTTP/1.1 101
				//		Switching Protocols".

				$new_headers = array( 0 => "HTTP/1.1 101 Switching Protocols" );

				//	2.	An |Upgrade| header field with value "websocket" as per RFC
				//		2616 [RFC2616].
				//		We're authenticating 

				$new_headers[] = "Upgrade: websocket";

				//	3.	A |Connection| header field with value "Upgrade".

				$new_headers[] = "Connection: Upgrade";

				//	4.	A |Sec-WebSocket-Accept| header field.  The value of this
				//		header field is constructed by concatenating /key/, defined
				//		above in step 4 in Section 4.2.2, with the string "258EAFA5-
				//		E914-47DA-95CA-C5AB0DC85B11", taking the SHA-1 hash of this
				//		concatenated value to obtain a 20-byte value and base64-
				//		encoding (see Section 4 of [RFC4648]) this 20-byte hash.
				//
				//		The ABNF [RFC2616] of this header field is defined as
				//		follows:
				//
				//		Sec-WebSocket-Accept     = base64-value-non-empty
				//		base64-value-non-empty = (1*base64-data [ base64-padding ]) |
				//	                            base64-padding
				//		base64-data      = 4base64-character
				//		base64-padding   = (2base64-character "==") |
				//	                      (3base64-character "=")
   				//		base64-character = ALPHA | DIGIT | "+" | "/"

				$secAccept = base64_encode(pack('H*', sha1($headers->{'Sec-WebSocket-Key'} . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
				$new_headers[] = "Sec-WebSocket-Accept:$secAccept";
				$new_headers[] = "\r\n";

			$upgrade = implode("\r\n",$new_headers);

			if( $this->debug ){
				$this->console("Send upgrade request headers.\n");
				$this->console("%s","\n---------------------------------------------------------------------------------------\n","BlueBold");
				$this->console( $upgrade );
				$this->console("%s","\n---------------------------------------------------------------------------------------\n\n","BlueBold");
				$this->console($conn);
			}
			$this->fwrite_stream($conn,$upgrade,strlen($upgrade));

			return $client;

		}

		/********************************************************************************************************************

			handshake:  Websocket require a sepcial response and this function is going to construct and send it over
						the established connection.

				//	1.	REF: 4.2.1.  Reading the Client's Opening Handshake
				//	2.	REF: 4.2.2.  Sending the Server's Opening Handshake

		********************************************************************************************************************/

		function handshake($request,$conn){

			if( $this->debug ){
				$this->console("%s","\n---------------------------------------------------------------------------------------\n","BlueBold");
				$this->console($request);
				$this->console("%s","\n---------------------------------------------------------------------------------------\n\n","BlueBold");
			}

			//	1.	REF: 4.2.1.  Reading the Client's Opening Handshake
			$headers = $this->readHandshake($request);
			if( empty($headers) ){ return; }

			//	2.	REF: 4.2.2.  Sending the Server's Opening Handshake
			$ouser = $this->sendHandshake($headers,$conn);

			return $ouser;
			
		}
		
		/********************************************************************************************************************

			Decode: We have to manipulate some bits based on the spec.  

			 		0                   1                   2                   3
					0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
					+-+-+-+-+-------+-+-------------+-------------------------------+
					|F|R|R|R| opcode|M| Payload len |    Extended payload length    |
					|I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
					|N|V|V|V|       |S|             |   (if payload len==126/127)   |
					| |1|2|3|       |K|             |                               |
					+-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
					|     Extended payload length continued, if payload len == 127  |
					+ - - - - - - - - - - - - - - - +-------------------------------+
					|                               |Masking-key, if MASK set to 1  |
					+-------------------------------+-------------------------------+
					| Masking-key (continued)       |          Payload Data         |
					+-------------------------------- - - - - - - - - - - - - - - - +
					:                     Payload Data continued ...                :
					+ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
					|                     Payload Data continued ...                |
					+---------------------------------------------------------------+

				//	1.	FIN:  1 bit
				//
				//		Indicates that this is the final fragment in a message.  The first
				//		fragment MAY also be the final fragment.
				//
				//	2.	RSV1, RSV2, RSV3:  1 bit each
				//
				//		MUST be 0 unless an extension is negotiated that defines meanings
				//		for non-zero values.  If a nonzero value is received and none of
				//		the negotiated extensions defines the meaning of such a nonzero
				//		value, the receiving endpoint MUST _Fail the WebSocket
				//		Connection_.
				//
				//	3.	Opcode:  4 bits
				//
				//		Defines the interpretation of the "Payload data".  If an unknown
				//		opcode is received, the receiving endpoint MUST _Fail the
				//		WebSocket Connection_.  The following values are defined.
				//
				//		*  %x0 denotes a continuation frame
				//
				//		*  %x1 denotes a text frame
				//
				//		*  %x2 denotes a binary frame
				//
				//		*  %x3-7 are reserved for further non-control frames
				//
				//		*  %x8 denotes a connection close
				//
				//		*  %x9 denotes a ping
				//
				//		*  %xA denotes a pong
				//
				//		*  %xB-F are reserved for further control frames
				//
				//	4.	Mask:  1 bit
				//
				//		Defines whether the "Payload data" is masked.  If set to 1, a
				//		masking key is present in masking-key, and this is used to unmask
				//		the "Payload data" as per Section 5.3.  All frames sent from
				//		client to server have this bit set to 1.
				//
				//	5.	Payload length:  7 bits, 7+16 bits, or 7+64 bits
				//
				//		The length of the "Payload data", in bytes: if 0-125, that is the
				//		payload length.  If 126, the following 2 bytes interpreted as a
				//		16-bit unsigned integer are the payload length.  If 127, the
				//		following 8 bytes interpreted as a 64-bit unsigned integer (the
				//		most significant bit MUST be 0) are the payload length.  Multibyte
				//		length quantities are expressed in network byte order.  Note that
				//		in all cases, the minimal number of bytes MUST be used to encode
				//		the length, for example, the length of a 124-byte-long string
				//		can't be encoded as the sequence 126, 0, 124.  The payload length
				//		is the length of the "Extension data" + the length of the
				//		"Application data".  The length of the "Extension data" may be
				//		zero, in which case the payload length is the length of the
				//		"Application data".
				//
				//	6.	Masking-key:  0 or 4 bytes
				//
				//		All frames sent from the client to the server are masked by a
				//		32-bit value that is contained within the frame.  This field is
				//		present if the mask bit is set to 1 and is absent if the mask bit
				//		is set to 0.  See Section 5.3 for further information on client-
				//		to-server masking.
				//
				//	7.	Payload data:  (x+y) bytes
				//
				//		The "Payload data" is defined as "Extension data" concatenated
				//		with "Application data".
				//
				//		Extension data:  x bytes
				//
				//		The "Extension data" is 0 bytes unless an extension has been
				//		negotiated.  Any extension MUST specify the length of the
				//		"Extension data", or how that length may be calculated, and how
				//		the extension use MUST be negotiated during the opening handshake.
				//		If present, the "Extension data" is included in the total payload
				//		length.
				//
				//		Application data:  y bytes
				//
				//		Arbitrary "Application data", taking up the remainder of the frame
				//		after any "Extension data".  The length of the "Application data"
				//		is equal to the payload length minus the length of the "Extension
				//		data".
				//	8.	Mask the payload data:
				//		Octet i of the transformed data ("transformed-octet-i") is the XOR of
				//		octet i of the original data ("original-octet-i") with octet at index
				//		i modulo 4 of the masking key ("masking-key-octet-j"):
				//
				//		j                   = i MOD 4
				//		transformed-octet-i = original-octet-i XOR masking-key-octet-j
				//
				//		The payload length, indicated in the framing as frame-payload-length,
				//		does NOT include the length of the masking key.  It is the length of
				//		the "Payload data", e.g., the number of bytes following the masking
				//		key.

		********************************************************************************************************************/

		private function decode( $msg,$changed_socket ){

			$this->console("In Decode\n");

			$fields = unpack( 'Cheader/Csize' , substr($msg, 0, 16) );
			$fields["size"] -= 128;
			$this->console($fields);

			$frame = new stdClass();
			if( !is_array($msg) ){
				$ascii_array = array_map("ord",str_split( $msg ));
			} else {
				$ascii_array = $msg;
			}
			$binary_array = array_map("decbin",$ascii_array);
			$binary_array = str_split(implode("",$binary_array));

			$first_bits = implode("",array_slice($binary_array,0,64));
			$this->console("first_bits :".$first_bits."\n");
			
			
			//	1.	FIN:  1 bit
			//
			//		Indicates that this is the final fragment in a message.  The first
			//		fragment MAY also be the final fragment.

			$FIN_bit = array_shift($binary_array);
			$frame->FIN = (int)$FIN_bit;

			//	2.	RSV1, RSV2, RSV3:  1 bit each
			//
			//		MUST be 0 unless an extension is negotiated that defines meanings
			//		for non-zero values.  If a nonzero value is received and none of
			//		the negotiated extensions defines the meaning of such a nonzero
			//		value, the receiving endpoint MUST _Fail the WebSocket
			//		Connection_.

			$RSV_1 = array_shift($binary_array);
			$RSV_2 = array_shift($binary_array);
			$RSV_3 = array_shift($binary_array);

			//	3.	Opcode:  4 bits
			//
			//		Defines the interpretation of the "Payload data".  If an unknown
			//		opcode is received, the receiving endpoint MUST _Fail the
			//		WebSocket Connection_.  The following values are defined.
			//
			//		*  %x0 denotes a continuation frame
			//
			//		*  %x1 denotes a text frame
			//
			//		*  %x2 denotes a binary frame
			//
			//		*  %x3-7 are reserved for further non-control frames
			//
			//		*  %x8 denotes a connection close
			//
			//		*  %x9 denotes a ping
			//
			//		*  %xA denotes a pong
			//
			//		*  %xB-F are reserved for further control frames

			$opcode_bits = implode("",array_splice($binary_array,0,4));
			$frame->opcode =  bindec( $opcode_bits );

			//	4.	Mask:  1 bit
			//
			//		Defines whether the "Payload data" is masked.  If set to 1, a
			//		masking key is present in masking-key, and this is used to unmask
			//		the "Payload data" as per Section 5.3.  All frames sent from
			//		client to server have this bit set to 1.
			
			$mask_bit = array_shift($binary_array);
			$frame->mask = (int)$mask_bit;

			//	5.	Payload length:  7 bits, 7+16 bits, or 7+64 bits
			//
			//		The length of the "Payload data", in bytes: if 0-125, that is the
			//		payload length.  If 126, the following 2 bytes interpreted as a
			//		16-bit unsigned integer are the payload length.  If 127, the
			//		following 8 bytes interpreted as a 64-bit unsigned integer (the
			//		most significant bit MUST be 0) are the payload length.  Multibyte
			//		length quantities are expressed in network byte order.  Note that
			//		in all cases, the minimal number of bytes MUST be used to encode
			//		the length, for example, the length of a 124-byte-long string
			//		can't be encoded as the sequence 126, 0, 124.  The payload length
			//		is the length of the "Extension data" + the length of the
			//		"Application data".  The length of the "Extension data" may be
			//		zero, in which case the payload length is the length of the
			//		"Application data".

			array_splice($ascii_array,0,2); // remove header from ascii array
			$len_bits = implode("",array_splice($binary_array,0,7));
			$frame->len = bindec( $len_bits );

			if( $frame->opcode == 15 ){
				$frame->len = 127;
			}

			if( $frame->len === 126 ){
				
				array_splice($ascii_array,0,2);	// remove additional header from ascii array
				$len_bits = implode("",array_splice($binary_array,0,16));
				$frame->len = bindec( $len_bits ) - 32767;
				
			} else if( $frame->len === 127 ){

				array_splice($ascii_array,0,8);	// remove additional header from ascii array
				$len_bits = implode("",array_splice($binary_array,0,16));
				$frame->len = bindec( $len_bits );

			}

			if( $frame->opcode !== 0 && $frame->opcode !== 1 && $frame->opcode !== 2 ){
				$this->console("%s","Not a valid opcode. Aborting message decoding.\n","RedBold");
				$this->console("OPCode Bits: ".$opcode_bits."\n");
				$this->console($frame);
				return;
			}

			//	6.	Masking-key:  0 or 4 bytes
			//
			//		All frames sent from the client to the server are masked by a
			//		32-bit value that is contained within the frame.  This field is
			//		present if the mask bit is set to 1 and is absent if the mask bit
			//		is set to 0.  See Section 5.3 for further information on client-
			//		to-server masking.

			$mask_key_bits = array();
			$mask_key = array_splice($ascii_array,0,4);

			//	7.	Payload data:  (x+y) bytes
			//
			//		The "Payload data" is defined as "Extension data" concatenated
			//		with "Application data".
			//
			//		Extension data:  x bytes
			//
			//		The "Extension data" is 0 bytes unless an extension has been
			//		negotiated.  Any extension MUST specify the length of the
			//		"Extension data", or how that length may be calculated, and how
			//		the extension use MUST be negotiated during the opening handshake.
			//		If present, the "Extension data" is included in the total payload
			//		length.
			//
			//		Application data:  y bytes
			//
			//		Arbitrary "Application data", taking up the remainder of the frame
			//		after any "Extension data".  The length of the "Application data"
			//		is equal to the payload length minus the length of the "Extension
			//		data".

			$encoded_bits = array();
			$encoded = array_splice($ascii_array,0);

			//	8.	Mask the payload data:
			//		Octet i of the transformed data ("transformed-octet-i") is the XOR of
			//		octet i of the original data ("original-octet-i") with octet at index
			//		i modulo 4 of the masking key ("masking-key-octet-j"):
			//
			//		j                   = i MOD 4
			//		transformed-octet-i = original-octet-i XOR masking-key-octet-j
			//
			//		The payload length, indicated in the framing as frame-payload-length,
			//		does NOT include the length of the masking key.  It is the length of
			//		the "Payload data", e.g., the number of bytes following the masking
			//		key.
			
			$frame->msg = array();
			for( $i=0;$i<count($encoded);++$i ){
				$char = chr($encoded[$i] ^ $mask_key[$i%4]);
				$frame->msg[] = $char;
			}
			$frame->msg = implode("",$frame->msg);

			
			if( $frame->FIN === 1 && $frame->opcode == 1 ){
				//$this->console($frame);
				$this->onData( $frame,$changed_socket );
			}

		}

		/********************************************************************************************************************

			mask: the data we send over websocket needs to be obfuscated correctly.  This does that.

		********************************************************************************************************************/

		private function mask($text){

			$b1 = 0x80 | (0x1 & 0x0f);
			$length = strlen($text);

			if($length <= 125)
				$header = pack('CC', $b1, $length);
			elseif($length > 125 && $length < 65536)
				$header = pack('CCn', $b1, 126, $length);
			elseif($length >= 65536)
				$header = pack('CCQ', $b1, 127, $length);
			return $header.$text;

		}


	}
?>
