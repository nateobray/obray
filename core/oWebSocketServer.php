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

		oWebSocketServer:

			1.  Establish a connection on specified host and port
			2.	Run main Event loop
				1. 	
			
	********************************************************************************************************************/

	Class oWebSocketServer extends obray\oDBO {

		public $MSGQUEUE = 2678;

		public function __construct($params){

			/*************************************************************************************************

				1.  Establish a connection on specified host and port

				//	1.	retreive host and ports or set them to defaults
				//	2.	determine the protocol to connect (essentially on client side ws or wss) and create
				//		context.
				//	3.	establish connection or abort on error
				//	4. 	setup some server data stores

			*************************************************************************************************/

			//	1.	retreive host and ports or set them to defaults
			$this->host = !empty($params["host"])?$params["host"]:"localhost";
			$this->port = !empty($params["port"])?$params["port"]:"80";
			$this->debug_mode = FALSE;
			if( !empty($params["debug"]) ){
				$this->debug_mode = TRUE;
				unset($params["debug"]);
			}

			//	2.	determine the protocol to connect (essentially on client side ws or wss) and create
			//		context.
			if( __WEB_SOCKET_PROTOCOL__ == "ws" ){

				$protocol = "tcp";
				$context = 		stream_context_create();

			} else if( in_array(__WEB_SOCKET_PROTOCOL__,['wss','ssl']) ) {

				$protocol = "ssl";
				try{
					
					$context = 	stream_context_create( array( "ssl" => array( 
						"verify_peer" => FALSE,
						"local_cert"=>__WEB_SOCKET_CERT__, 
						"local_pk"=>__WEB_SOCKET_KEY__, 
						"passphrase" => __WEB_SOCKET_KEY_PASS__,
						"ciphers" => "HIGH:!SSLv2:!SSLv3",
						"disable_compression" => TRUE
					) ) );

				} catch( Exception $err ){

					$this->debug("Unable to create stream context: ".$err->getMessage()."\n");
					$this->throwError("Unable to create stream context: ".$err->getMessage());
					return;

				}

			} else {
				$this->debug("%s","Bad protocal requested!\n","RedBold");
				return;
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
			
			//	4. 	setup some server data stores

			// keeps track of the parent process PID, we use this for sending messages through the queue to the parent
			$this->parent_process_pid = getmypid();

			// stores fragmented messages so they can be reconstructed
			$this->fragments = array();

			// stores threads for independent connections
			$this->socketNumbers = array();

			// message queue for communication between forked processes
			$this->message_queue = msg_get_queue($this->MSGQUEUE);
			msg_remove_queue($this->message_queue);
			$this->message_queue = msg_get_queue($this->MSGQUEUE);

			/*************************************************************************************************

				2.	Run main Event loop
				//	1. 	stream_select: look for changes on the socket to process incoming connections
				//	2. 	If new connection fork process to child and pass off connection, continue loop
				//	3. 	check message queue for messages for the parent process
				//	4. 	check if child processes have terminated and update list

			*************************************************************************************************/

			while(true){

				//	1. 	stream_select: look for changes on the socket to process incoming connections
				$changed = array( 0 => $this->socket ); $null = NULL;
				@stream_select( $changed, $null, $null, 0, 20000 );

				//	2. 	If new connection fork process to child and pass off connection, continue loop
				if( in_array($this->socket,$changed) ){

					// put new connection onto new thread
					$this->console("%s","Starting new process.\n","YellowBold");
					
					$pid = pcntl_fork();

					if( $pid === -1 ){
						$this->debug("%s","Could not create process!\n","RedBold");
					} else if( $pid ) {
						$this->debug("%s","\tParent thread done spawning child: ".$pid."\n","GreenBold");
						$this->socketNumbers[] = $pid;
						// removes original socket from the changed array (so we don't keep looking for a new connections)
						$found_socket = array_search($this->socket, $changed);
						$this->onForked();
						unset($changed[$found_socket]);
						usleep(20000);
					} else if( $pid === 0 ) {
						$new_socket = $this->connect($this->socket);
						if( $new_socket ){  
							$this->select( $new_socket );
						}
						exit();
					}

				}

				//	3. 	check message queue for messages for the parent process

				$message = $this->messageQueueReceive( $this->parent_process_pid );
				if( $message !== FALSE ){
					$this->onQueueReceiveParent( $message );
				}

				//	4. 	check if child processes have terminated and update list

				$exited_pid = pcntl_waitpid(0,$status,WNOHANG);
				if( $exited_pid > 0 ){
					$index = array_search( $exited_pid, $this->socketNumbers );
					if( $index !== FALSE ){
						unset($this->socketNumbers[$index]);
						$this->socketNumbers = array_values($this->socketNumbers);
					}
					$this->debug("%s","\nProcess " . $exited_pid . " killed, number left: ".count($this->socketNumbers)."\n","YellowBold");
				}

				$this->onParentLoop();
				
			}

			pcntl_wait();

		}

		/********************************************************************************************************************

			messageQueueSend: 	queues a message onto the queue with type (must be an integer)

		********************************************************************************************************************/

		protected function messageQueueSend( $msgType, $message ){
			if( !msg_send( $this->message_queue, $msgType, $message, FALSE, TRUE, $error_code ) ){
				$this->console("%s","Error (".$error_code."): Unable to queue message.\n","RedBold");
			}
		}

		/********************************************************************************************************************

			messageQueueReceive: 	receives a message from the queue with the specified type (must be an integer).  When
									a message is received it is removed from the queue.

		********************************************************************************************************************/

		protected function messageQueueReceive( $msgType ){
			$received_type = 0;
			$message = FALSE;
			if( msg_receive( $this->message_queue, $msgType, $received_type, 8192000, $message, FALSE, MSG_IPC_NOWAIT, $error_code ) ){
				return $message;
			} else {
				if( $error_code !== 42 ){
					$this->console("%s","Error receiving message from queue (".$error_code.")!\n","RedBold");
				}
				return FALSE;
			}
		}

		/********************************************************************************************************************

			select: 	Select receives a socket and does a stream_select on it to wait for incoming messages from the
						socket.  It's assumed it working with a child process.

			//	1.	Run child process main event loop
			//		1.	stream select on child socket
			//		2.	for any changes on the socket attempt to process the message

		********************************************************************************************************************/

		private function select( $socket ){

			$this->child_process_pid = getmypid();
			$this->debug("%s","\tStarting child stream_select()\n","GreenBold");
			$connected = TRUE;

			/*************************************************************************************************

				//	1.	Run child process main event loop
				//		1.	stream select on child socket
				//		2.	for any changes on the socket attempt to process the message
				//			1.	Get changed socket
				//			2.	Read from changed socket
				//			3.	if EOF then close connection.
				//		3. 	check for messages on the queue for the child process

			*************************************************************************************************/

			while($connected){
				
				//		1.	stream select on child socket
				$changed = array( 0 => $socket ); $null = NULL;
				@stream_select( $changed, $null, $null, 0, 20000 );
			
				//		2.	for any changes on the socket attempt to process the message
				foreach ( array_keys($changed) as $changed_key) {
					
					//	1.	Get changed socket
					$changed_socket = $changed[$changed_key];
					$info = stream_get_meta_data($changed_socket);

					//	2.	Read from changed socket
					if( !feof($changed_socket) ){

						$buf = $this->fread_stream($changed_socket,100000*1024);
						if( $buf == FALSE ){
							$this->debug("%s","\nUnable to read from socket\n","RedBold");
							$this->disconnect($changed_socket);
							break;
						}

						$this->debug("%s","\nNew message Received.\n","YellowBold");
						$this->decode($buf,$changed_socket);

						unset($changed[$changed_key]);

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
						$connected = FALSE;
						break;

					}

				}

				//		3. 	check for messages on the queue for the child process
				$message_to_send = $this->messageQueueReceive( $this->child_process_pid );
				if( $message_to_send !== FALSE ){
					$this->onQueueReceiveChild( $message_to_send, $socket );
				}

				$this->onChildLoop();

			}

		}

		/********************************************************************************************************************

			OVERWRITEABLE FUNCTIONS: CHILD PROCESS

			It's expected that these function will be overwritten by the extending socket server.

			onSocketReceive:		by default will send back whatever messages was sent from the client (basic echo server).
									Overwrite this method to handle the data received by the server in your own way.

			onQueueReceiveChild:	By default this function simply sends the received message out the child process's
									socket connection.

			onConnect: 				Called when connection is established and handshake completed successfully.  Overwrite this
									method when you need to do more setup or authenticat on the connection.

			onDisconnect: 			Called after a connection is successfully disconnected. Overwrite this method to do whatever
									cleanup you need to after a connect is closed

		********************************************************************************************************************/

		public function onSocketReceive($frame, $changed_socket){
			
			$this->messageQueueSend( $this->parent_process_pid, $frame->msg );
			$this->console("%s","\tEchoed message!\n","GreenBold");
			return;
			
		}

		public function onQueueReceiveChild($message){
			
			$this->send( $message );
			return;
			
		}

		public function onConnect( $headers ){
			return TRUE;
		}

		public function onConnected( ){

		}

		public function onDisconnect( $index ){
			return TRUE;
		}

		public function onChildLoop(){

		}

		/********************************************************************************************************************

			OVERWRITEABLE FUNCTIONS: PARENT PROCESS

			onQueueRecieveParent: 	by default will loop through each process and add the message to that processes message
									queue.  From there each process will write the message out on the socket connection.

		********************************************************************************************************************/

		public function onQueueReceiveParent( $message ){


			
			forEach( $this->socketNumbers as $i => $num ){

				$this->console("\tRelaying message on process: ".$num."\n");
				$this->messageQueueSend( $num, $message );

			}

		}

		public function onForked(){

		}

		public function onParentLoop(){
			
			sleep(5);

		}
		

		/********************************************************************************************************************

			connect:

				Check for new connections: Basically we're checking to see if our original socket has
				been added to the changed array and if so we know it has a new connection waiting to be
				handled.

				//	1.	accpet new socket
				//	2. 	handle error on socket accept
				//	3. 	Set settings on socket (used to tune the server)
				//	4.	read data sent by the socket
				//	5.	perform websocket handshake
				//	6. 	handle handshake failure
				// 	7. 	call on connected
				
		********************************************************************************************************************/

		private function connect( &$socket ){

			//	1.	accept new socket
			$this->debug("%s","\nAttempting to connect to a new socket.\n","YellowBold");
			$new_socket = @stream_socket_accept($socket,1);

			//	2. 	handle error on socket accept
			if( !$new_socket ){
				$this->debug("%s","\tUnable to connect.\n","RedBold");
				return FALSE;
			}

			//	3. 	Set settings on socket (used to tune the server)
			stream_set_blocking($new_socket, false);

			//	4.	read data sent by the socket
			$this->debug("\tReading from socket.\n");
			$request = $this->fread_stream($new_socket,100000*1024,2);
			if( !$request ){
				$this->debug("%s","Unable to read from socket, disconnecting.\n","RedBold");
				return FALSE;
			}

			$this->childSocket = $new_socket;

			//	5.	perform websocket handshake
			$this->debug("\tPerforming websocket handshake.\n");
			$headers = $this->handshake($request, $new_socket);

			//	6. 	handle handshake failure
			if( empty($headers) ) {

				$this->debug("%s","\tConnection failed, handshake failed.  Returning 403 forbidden.\n","RedBold");
				$this->sendForbidden($new_socket);
				$this->disconnect($new_socket);
				// removes our newely connected socket from our sockets array (aborting the connection)
				return FALSE;

			}

			// 	7. 	call on connected
			$this->onConnected();
			return $new_socket;
			
		}

		/********************************************************************************************************************

			disconnect: takes the changed socket and the changed socket array, disconnects the user, the removes user data
						and the socket from the sockets array.

			//	1.	shutdown the socket connection
			//	2.	call onDisconnect
			//	3.	Terminate the process

		********************************************************************************************************************/

		protected function disconnect( ){

			$this->debug("%s","\nAttempting to disconnect.\n","YellowBold");

			//	1.	shutdown the socket connection
			stream_socket_shutdown($this->childSocket,STREAM_SHUT_RDWR);

			//	2.	call onDisconnect
			$this->debug("%s","\tDisconnect successful, calling onDisconnect.\n","GreenBold");
			$this->onDisconnect( $this->childSocket );

			//	3.	Terminate the process
			exit();

		}

		/********************************************************************************************************************

			fwrite_stream: 	takes a socket and a string and attempts to write the string to the socket.  This is setup
							to be non-blocking, so we must loop through until the full string is written to the socket
							or we exhaust our retries or the socket connection fails

			//	1.	loop until data is all written
			//		1.	attempt to write, handle error
			//		2. 	if nothing written or failure
			//			1. 	retries maxed then abort write attempt returning FALSE
			//			2. 	retires not maxed then incement retries and continue loop
			//	2.	all data written return bytes written to the socket

		********************************************************************************************************************/

		private function fwrite_stream($socket, $string) {

			$retries = 0;

			//	1.	loop until data is all written
		    for ($written = 0; $written < strlen($string); $written += $fwrite) {
				
				//		1.	attempt to write, handle error
				try {
		        	$fwrite = @fwrite($socket, substr($string, $written),10240);
				} catch (Exception $err){
					return FALSE;
				}

				//		2. 	if nothing written or failure
				if( $fwrite === 0 || $fwrite === FALSE ){

					//			1. 	retries maxed then abort write attempt returning FALSE
					if( $retries > 10 ){
						return FALSE;

					//			2. 	retires not maxed then incement retries and continue loop
					} else {
						++$retries;
						usleep(100000);
						continue;
					}
					return FALSE;
				}

				$retries = 0;

			}
			
			//	2.	all data written return bytes written to the socket
			return $written;
			
		}

		private function fread_stream($socket,$length,$timeout=30){

			$request = ''; $start = microtime(TRUE);
			while( !feof($socket) ){
				$new_content = fread($socket, $length);

				if( !empty($new_content) ){
					$fields = unpack( 'Cheader/Csize' , substr($new_content, 0, 16) );
					$fields["size"] -= 128;
				}

				$request .= $new_content;
				if( strlen($new_content) === 0 && strlen($request) !== 0 ){ return $request; }
				$current = microtime(TRUE);
				if( $timeout <= $current-$start ){ $this->debug("%s","\tSocket read timed out.\n","RedBold"); return FALSE; }
				usleep(50000);
			}
			return $request;
			
		}

		/********************************************************************************************************************

			send:  takes a message and passes though to all the connections subscribed to the specified channel

			//	1.	make sure the socket has not timed out or lost it's connection
			//	2.	send message

		********************************************************************************************************************/

		public function send($msg){
			
			//	1.	make sure the socket has not timed out or lost it's connection
			$this->debug("\tMaking sure socket hasn't timed out...");
			$info = stream_get_meta_data($this->childSocket);
			if( feof($this->childSocket) || $info['timed_out'] ){
				$this->disconnect();
				return FALSE;
			}
			$this->debug("%s","done\n","GreenBold");

			//	2.	send message
			$this->debug("\tWriting to socket...");
			if( $this->fwrite_stream($this->childSocket,$this->mask($msg)) == FALSE ){
				$this->disconnect();
				return FALSE;				
			}
			$this->debug("%s","done\n","GreenBold");
			
			return TRUE;
			
		}

		/********************************************************************************************************************

			parseRequestHeader: 	parses the header information from the handshake request.  These headers
								 	are used to validate a valid request. It can also be used to pass additional information
								 	into the socket server for authentication or other purpsoses when overriding onConnected.

			//	1.	retreive method and validate
			//	2.	retreive path and validate
			//	3.	retreive HTTP version major
			//	4.	retreive all other headers

		********************************************************************************************************************/

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
				$this->debug("%s","Invalid WebSocket Headers!\n","RedBold");
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
				//		|WWW-Authenticate| header field as described in [RFC2616].
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

		private function sendHandshake( $headers ){

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
				//		Sec-WebSocket-Accept    = base64-value-non-empty
				//		base64-value-non-empty 	= (1*base64-data [ base64-padding ]) | base64-padding
				//		base64-data      		= 4base64-character
				//		base64-padding   		= (2base64-character "==") | (3base64-character "=")
   				//		base64-character 		= ALPHA | DIGIT | "+" | "/"

				$secAccept = base64_encode(pack('H*', sha1($headers->{'Sec-WebSocket-Key'} . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
				$new_headers[] = "Sec-WebSocket-Accept:$secAccept";
				$new_headers[] = "\r\n";

			$upgrade = implode("\r\n",$new_headers);

			$this->fwrite_stream($this->childSocket,$upgrade,strlen($upgrade));

		}

		/********************************************************************************************************************

			handshake:  Websocket require a sepcial response and this function is going to construct and send it over
						the established connection.

				//	1.	REF: 4.2.1.  Reading the Client's Opening Handshake
				//	2.	REF: 4.2.2.  Sending the Server's Opening Handshake

		********************************************************************************************************************/

		function handshake($request){

			//	1.	REF: 4.2.1.  Reading the Client's Opening Handshake

			$headers = $this->readHandshake($request);
			if( empty($headers) ){ return FALSE; }

			if( $this->onConnect($headers) === FALSE ){
				$headers = array(); 
				return FALSE;
			}

			//	2.	REF: 4.2.2.  Sending the Server's Opening Handshake
			$this->sendHandshake($headers);

			return $headers;

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

				//	5.4.  Fragmentation
				//
				//	1. 	For a text message sent as three fragments, the first
				//		fragment would have an opcode of 0x1 and a FIN bit clear, 
				//	2. 	the second fragment would have an opcode of 0x0 and a FIN bit clear,
				//	3.	and the third fragment would have an opcode of 0x0 and a FIN bit
				//		that is set.


		********************************************************************************************************************/

		private function decode( $msg,$changed_socket ){
			
			$frame = new stdClass();
			if( !is_array($msg) ){
				$ascii_array = array_map("ord",str_split( $msg ));
			} else {
				$ascii_array = $msg;
			}

			$header = array_slice($ascii_array,0,13);

			$binary_array = array_map("decbin",$header);
			$binary_array = array_map(function($item){
				return str_pad($item,8,"0",STR_PAD_LEFT);
			},$binary_array);
			$binary_array = str_split(implode("",$binary_array));

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

			$frame->rsv1 = array_shift($binary_array);
			$frame->rsv2 = array_shift($binary_array);
			$frame->rsv3 = array_shift($binary_array);

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

			if( $frame->opcode == 2 ){
				$this->debug("%s","\tReceived binary frame... not supported, aborting.\n","RedBold");
				return;
			}

			if( $frame->opcode == 8 ){
				$this->debug("%s","\tReceived close... closing!\n","RedBold");
				$this->sendClose( $changed_socket);
				return;
			}

			if( $frame->opcode == 11 ){
				$this->debug("%s","\tReceived ping request... send pong.\n","GreenBold");
				$this->sendPong( $changed_socket, $msg);
				return;
			}

			if( $frame->opcode == 10 ){

				$this->debug("%s","\tReceived pong.\n","GreenBold");
				
				return;
			}
			
			if( $frame->opcode > 10 ){

				$this->debug("%s","\tUnsupported control frame received... aborting.\n","RedBold");
				return;

			}

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
			
			if( $frame->len === 126 ){
				
				$this->debug("%s","\tMedium message: 126\n","BlueBold");
				array_splice($ascii_array,0,2);	// remove additional header from ascii array
				$frame->len = bindec( implode( "", array_splice($binary_array,0,16) ) );
				
			} else if( $frame->len === 127 ){

				$this->debug("%s","\tLarge message: 127\n","BlueBold");
				array_splice($ascii_array,0,8);	// remove additional header from ascii array
				$frame->len = bindec( implode( "", array_splice($binary_array,0,64) ) );
				
			}

			if( $frame->opcode !== 0 && $frame->opcode !== 1 && $frame->opcode !== 2 ){
				$this->debug("%s","Not a valid opcode. Aborting message decoding.\n","RedBold");
				$this->debug("OPCode Bits: ".$opcode_bits."\n");
				$this->debug($frame);
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
			$encoded = array_splice($ascii_array,0,$frame->len);

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
			//	

			$frame->msg = array();
			for( $i=0;$i<count($encoded);++$i ){
				$char = chr($encoded[$i] ^ $mask_key[$i%4]);
				$frame->msg[] = $char;
			}
			$frame->msg = implode("",$frame->msg);

			//	5.4.  Fragmentation
			//
			//	1. 	For a text message sent as three fragments, the first
			//		fragment would have an opcode of 0x1 and a FIN bit clear, 
			//	2. 	the second fragment would have an opcode of 0x0 and a FIN bit clear,
			//	3.	and the third fragment would have an opcode of 0x0 and a FIN bit
			//		that is set.

			//	1. 	For a text message sent as three fragments, the first
			//		fragment would have an opcode of 0x1 and a FIN bit clear, 

			if( $frame->FIN === 0 && $frame->opcode === 1 ){

				$this->debug("\tReceived first fragmented message.\n");
				$this->fragments = array();
				$this->fragments[] = $frame->msg;

			}

			//	2. 	the second fragment would have an opcode of 0x0 and a FIN bit clear,
			if( $frame->FIN === 0 && $frame->opcode === 0 ){

				$this->debug("\tReceived middle fragmented message.\n");
				$this->fragments[] = $frame->msg;

			}

			//	3.	and the third fragment would have an opcode of 0x0 and a FIN bit
			//		that is set.
			if( $frame->FIN === 1 && $frame->opcode === 0 ){
				
				$this->debug("\tReceived final fragmented message.\n");
				$this->fragments[] = $frame->msg;
				$frame->msg = implode("",$this->fragments);
				unset($this->fragments);

				// set opcode to 1 so it process the frame as the full message
				$frame->opcode = 1;

			}

			// if this is the final frame and the opcode is text then process as completed message
			if( $frame->FIN === 1 && $frame->opcode === 1 ){
				$this->debug("%s","\tReceived full text frame... calling onData.\n","GreenBold");
				$this->onSocketReceive( $frame,$changed_socket );
			}

			if( !empty($ascii_array) ){
				$this->decode($ascii_array,$changed_socket);
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
				$header = pack('CCJ', $b1, 127, $length);
			return $header.$text;

		}

		/********************************************************************************************************************

			sendClose: echos the close request and disconnects

		********************************************************************************************************************/

		private function sendClose( $send_socket ){

			$b1 = 0x88;
			$length = strlen("");
			$header = pack('CC', $b1, $length);
			
			$this->fwrite_stream($send_socket,$header) == FALSE;
			$this->disconnect($send_socket);
			
		}

		/********************************************************************************************************************

			sendForbidden: sends a response back with a 403 forbidden

		********************************************************************************************************************/

		protected function sendForbidden(){
			
			$new_headers = array( 0 => "HTTP/1.1 403 Forbidden" );
			$new_headers[] = "Sec-WebSocket-Version: 13";
			$new_headers[] = "\r\n";
			$request = implode("\r\n",$new_headers);
			$this->fwrite_stream($this->childSocket,$request,strlen($request));

		}

		/********************************************************************************************************************

			sendBadRequest: sends a response back with a 400 Bad Request

		********************************************************************************************************************/

		protected function sendBadRequest(){
			$new_headers = array( 0 => "HTTP/1.1 400 Bad Request" );
			$new_headers[] = "Sec-WebSocket-Version: 13";
			$new_headers[] = "\r\n";
			$request = implode("\r\n",$new_headers);
			$this->fwrite_stream($socket,$request,strlen($request));
		}

		protected function debug($format,$text=NULL,$color=NULL){

			if( !empty($this->debug_mode) ){
				$this->console($format,$text,$color);
			}

		}

	}
?>