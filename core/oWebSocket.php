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

		oWebSocket:

			1.  Establish a connection on specified host and port
			2.	Check for new connections: Basically we're checking to see if our original socket has been added to the
				changed array and if so we now it has a new connection waiting to be handled.

				//	1.	accpet new socket
				//	2.	add socket to socket list
				//	3.	read data sent by the socket
				//	4.	perform websocket handshake
				//	5.	store the client data
				//	6.	remove new socket from changed array

			3.	Loop through all the changed sockets

				//	1.	read from changed sockets
				//	2.	if EOF then close connection.

	********************************************************************************************************************/

	Class oWebSocket extends ODBO {

		public function __construct($params){

			/*************************************************************************************************
				
				1.  Establish a connection on specified host and port

			*************************************************************************************************/

			$this->host = !empty($params["host"])?$params["host"]:"localhost";
			$this->port = !empty($params["port"])?$params["port"]:"80";

			if( __WEB_SOCKET_PROTOCOL__ == "ws" ){
				$protocol = "tcp";
				$context = 		stream_context_create();	
			} else {
				$protocol = "ssl";
				$context = 		stream_context_create( array( "ssl" => array( "local_cert"=>__WEB_SOCKET_CERT__, "local_pk"=>__WEB_SOCKET_KEY__, "passphrase" => __WEB_SOCKET_KEY_PASS__ ) ) );	
			}
			$listenstr = 	$protocol."://".$this->host.":".$this->port;
			$this->console("Binding to ".$this->host.":".$this->port." over ".$protocol."\n");
			$this->socket = stream_socket_server($listenstr,$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$context);
			$this->console("%s","Listending...\n","GreenBold");

			$this->sockets = array( $this->socket );
			$this->cData = array();
			//$changed = $this->sockets;

			while(true){

				$changed = $this->sockets;

				stream_select( $changed, $null, $null, 0, 10 );

				/*************************************************************************************************

					2.	Check for new connections: Basically we're checking to see if our original socket has 
						been added to the changed array and if so we now it has a new connection waiting to be 
						handled.

						//	1.	accpet new socket
						//	2.	add socket to socket list
						//	3.	read data sent by the socket
						//	4.	perform websocket handshake
						//	5.	store the client data
						//	6.	remove new socket from changed array

				*************************************************************************************************/

				if( in_array($this->socket,$changed) ){
					
					$this->console("Attempting to connect a new client.\n");
					$new_socket = @stream_socket_accept($this->socket);								//	1.	accpet new socket
					if( $new_socket !== FALSE ){
						
						$this->sockets[] = $new_socket; 											//	2.	add socket to socket list
						$request = fread($new_socket, 2046);
						$this->console($request);
						$this->console("Performing websocket handshake.\n");
						$ouser = $this->handshake($request, $new_socket); 							//	4.	perform websocket handshake
						if( !empty($ouser) ){

							$this->console($ouser);

							$this->cData[ array_search($new_socket,$this->sockets) ] = $ouser;		//	5.	store the client data
							$this->console($ouser->ouser_first_name." ".$ouser->ouser_last_name." has logged on.\n");

							$response = (object)array( 'channel'=>'all', 'type'=>'broadcast', 'message'=>$ouser->ouser_first_name.' '.$ouser->ouser_last_name.' connected.' ); //prepare json data
							$this->send($response); //notify all users about new connection

							$found_socket = array_search($this->socket, $changed);
							unset($changed[$found_socket]);											//	6.	remove new socket from changed array
							$this->console( (count($this->sockets)-1)." users connected.\n" );

						} else {
							$this->console("%s","Connection failed, unable to connect user (not found).\n","RedBold");
							$found_socket = array_search($this->socket, $changed);
							unset($changed[$found_socket]);
							$found_socket = array_search($this->sockets, $new_socket);
							unset($this->sockets[$found_socket]);
						}
					} else {
						$this->console("%s","Connection failed, unable to connect user.\n","RedBold");
						$found_socket = array_search($this->socket, $changed);
						unset($changed[$found_socket]);
					}
					
				}

				/*************************************************************************************************

					3.	Loop through all the changed sockets

						//	1.	read from changed sockets
						//	2.	if EOF then close connection.

				*************************************************************************************************/
				
				
				if( !empty($changed) ){
					//$this->console("%s","\n***********************************************\n","WhiteBold");
					//$this->console("%s","\tMessage Received: ".count($changed)."\n","WhiteBold");
					//$this->console("%s","***********************************************\n","WhiteBold");
				}

				foreach ( array_keys($changed) as $changed_key) {

					$changed_socket = $changed[$changed_key];

					//	1.	read from changed sockets
					$buf = fread($changed_socket, 2048);
					if( $buf !== FALSE && !empty($buf) ){

						$this->console("Buffer read.\n");
						$this->decode($buf,$changed_socket);
						
						break;	
					} else if( $buf === FALSE || empty($buf) ){
						$this->console("Disconnecting user.\n");
						// remove client for $clients array
						$found_socket = array_search($changed_socket, $this->sockets);

						$this->console("%s","Attempting to disconnect index: ".$found_socket."\n","Red");
						stream_socket_shutdown($changed_socket,STREAM_SHUT_RDWR);
						
						$ouser = $this->cData[$found_socket];
						$this->console("%s",$ouser->ouser_first_name." ".$ouser->ouser_last_name." has logged off.\n","Red");
						
						unset($this->cData[$found_socket]);
						unset($this->sockets[$found_socket]);

						$found_socket = array_search($changed_socket, $changed);
						unset($changed[$found_socket]);											//	6.	remove new socket from changed array

						//notify all users about disconnected connection
						$response = (object)array( 'channel'=>'all', 'type'=>'broadcast', 'message'=>$ouser->ouser_first_name.' '.$ouser->ouser_last_name.' disconnected.');
						$this->send($response);
						
						
						
					}
					
				}


				

			}

		}

		/*****************************************************************************
			
			Decode: We have to manipulate some bits based on the spec.  Currently
					there is a limit to the number of bits we can send, but that
					is easily remedied by modifying this function.

		*****************************************************************************/
		private function decode( $msg,$changed_socket ){

			$frame = new stdClass();
			if( !is_array($msg) ){
				$ascii_array = array_map("ord",str_split( $msg ));
			} else {
				$ascii_array = $msg;
			}
			$binary_array = array_map("decbin",$ascii_array);
			$binary_array = str_split(implode("",$binary_array));

			// FIN bit
			$FIN_bit = array_shift($binary_array);
			$frame->FIN = (int)$FIN_bit;

			// RSV 1
			$RSV_1 = array_shift($binary_array);
			// RSV 2
			$RSV_2 = array_shift($binary_array);
			// RSV 3
			$RSV_3 = array_shift($binary_array);

			// opcode
			$opcode_bits = implode("",array_splice($binary_array,0,4));
			$frame->opcode =  bindec( $opcode_bits );

			// MASK Bit
			$mask_bit = array_shift($binary_array);
			$frame->mask = (int)$mask_bit;

			// Length Bits
			$len_bits = implode("",array_splice($binary_array,0,7));
			$frame->len = bindec( $len_bits );

			$header = array_splice($ascii_array,0,2);

			$mask_key_bits = array();
			$mask_key = array_splice($ascii_array,0,4);
			
			$encoded_bits = array();
			$encoded = array_splice($ascii_array,0,$frame->len);
			
			$frame->msg = array();
			for( $i=0;$i<count($encoded);++$i ){
				$frame->msg[] = chr($encoded[$i] ^ $mask_key[$i%4]);
			}
			$frame->msg = implode("",$frame->msg);

			if( $frame->FIN === 1 && $frame->opcode = 1 ){
				$this->onData( $frame,$changed_socket );
			}

			if( !empty($ascii_array) ){
				$this->decode($ascii_array,$changed_socket);
			}
			
		}

		/********************************************************************************************************************

			onData: this function is called when we are done decoding a full message.  It determines how to handle
					the incoming message.

		********************************************************************************************************************/


		public function onData( $frame, $changed_socket ){

			$msg = json_decode($frame->msg);
			
			$found_socket = array_search($changed_socket, $this->sockets);

			if( !empty($msg->type) ){

				switch( $msg->type ){
					case 'subscription':
						$this->console("Received subscription, subscribing...");
						$this->cData[ $found_socket ]->subscriptions[ $msg->channel ] = TRUE;
						$this->console("done\n");
						break;
					case 'unsubscribe':
						$this->console("Received unsubscribe, unsubcribing...");
						forEach( $this->cData[ $found_socket ]->subscriptions as $key => $subscription ){
							if( $key != "all" ){ unset( $this->cData[ $found_socket ]->subscriptions[ $key ] ); }
						}
						$this->console("done\n");
						break;
					case 'broadcast': case 'navigate':
						$this->console("Received broadcast, sending...");
						$response = $this->send($msg);
						if( $response ){
							$this->console("%s","done\n","GreenBold");
						} else {
							$this->console("%s","No subscribers on ".$msg->channel."\n","RedBold");
						}
						break;

					case 'list':
						$this->console("Received list, sending...");
						$msg = (object)array( 'channel'=>'all', 'type'=>'list', 'message'=>$this->cData);
						$this->send($msg);
						break;

					default:
						$this->console("Unknown message received:\n");
						$this->console("%s","\n---------------------------------------------------------------------------------------\n","BlueBold");
						$this->console( $frame->msg );
						$this->console("%s","\n---------------------------------------------------------------------------------------\n\n","BlueBold");
						break;

				}

			} else {
				$this->console("Unknown message received:\n");
				$this->console("%s","\n---------------------------------------------------------------------------------------\n","BlueBold");
				$this->console( $frame->msg );
				$this->console("%s","\n---------------------------------------------------------------------------------------\n\n","BlueBold");
			}

		}

		/********************************************************************************************************************

			send:  takes a message and passes though to all the connections subscribed to the specified channel

		********************************************************************************************************************/

		function send($msg){			
			$msg_sent = FALSE;
			foreach( array_keys($this->sockets) as $changed_key){
				$send_socket = $this->sockets[$changed_key];
				if( !empty($this->cData[ $changed_key ]) && !empty($this->cData[ $changed_key ]->subscriptions[$msg->channel]) ){
					$this->console("Sending message to ".$this->cData[ $changed_key ]->ouser_first_name." ".$this->cData[ $changed_key ]->ouser_last_name."\n");
					$this->console("%s","\n---------------------------------------------------------------------------------------\n","BlueBold");
					$this->console( json_encode($msg) );
					$this->console("%s","\n---------------------------------------------------------------------------------------\n\n","BlueBold");
					$message =  $this->mask( json_encode($msg) );
					fwrite($this->sockets[$changed_key], $message, strlen($message));
					$msg_sent = TRUE;
				}
			}
			
			return $msg_sent;
		}

		/********************************************************************************************************************

			handshake:  Websocket require a sepcial response and this function is going to construct and send it over
						the established connection.

				1.	Extract the ouser from the connection request
				2.	Extract header information from request
				3.	Prepare/send response

		********************************************************************************************************************/

		function handshake($request,$conn){
			
			$this->console("%s","\n---------------------------------------------------------------------------------------\n","BlueBold");
			$this->console($request);
			$this->console("%s","\n---------------------------------------------------------------------------------------\n\n","BlueBold");

			preg_match('/(?<=GET \/\?ouser_id=)([0-9]*)/',$request,$matches);

			// 1.	Extract the ouser from the connection request
			$ouser = new stdClass();
			if( !empty($matches) ){
				$ouser_id = $matches[0];
				$ouser = $this->route('/obray/OUsers/get/?ouser_id='.$ouser_id)->getFirst();
				$ouser->subscriptions = array( "all" => 1 );
				$ouser->connection = new stdClass();
			}

			// 2.	Extract header information from request
			$lines = explode("\r\n",$request);
			$headers = array();
			foreach($lines as $line){
				$line = chop($line);
				if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)){  
					$headers[$matches[1]] = $matches[2];  
					$ouser->connection->{$matches[1]} = $matches[2];
				}
			}

			// 3.	Prepare/send response			
			$secKey = $headers['Sec-WebSocket-Key'];
			$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
			//hand shaking header
			$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
			"Upgrade: websocket\r\n" .
			"Connection: Upgrade\r\n" .
			"WebSocket-Origin: $this->host\r\n" .
			"WebSocket-Location: ws://$this->host:$this->port/\r\n".
			"Sec-WebSocket-Accept:$secAccept\r\n\r\n";

			$this->console("Send upgrade request headers.\n");
			$this->console("%s","\n---------------------------------------------------------------------------------------\n","BlueBold");
			$this->console( $upgrade );
			$this->console("%s","\n---------------------------------------------------------------------------------------\n\n","BlueBold");
			$this->console($conn);
			fwrite($conn,$upgrade,strlen($upgrade));
			
			return $ouser;

		}

		/********************************************************************************************************************

			unmask: data received from the websocket connection is obfuscated. This fixes that.

		********************************************************************************************************************/

		private function unmask($text) {

			$length = ord($text[1]) & 127;
			if($length == 126) {
				$masks = substr($text, 4, 4);
				$data = substr($text, 8);
			}
			elseif($length == 127) {
				$masks = substr($text, 10, 4);
				$data = substr($text, 14);
			}
			else {
				$masks = substr($text, 2, 4);
				$data = substr($text, 6);
			}
			$text = "";
			for ($i = 0; $i < strlen($data); ++$i) {
				$text .= $data[$i] ^ $masks[$i%4];
			}
			return $text;

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
				$header = pack('CCNN', $b1, 127, $length);
			return $header.$text;

		}


	}
?>