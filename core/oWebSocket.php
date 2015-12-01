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

	class oClient {
		
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

			$this->console("Binding to ".$this->host.":".$this->port."\n");
			//Create TCP/IP sream socket
			$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			//reuseable port
			socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
			//bind socket to specified host
			socket_bind($this->socket, $this->host, $this->port);
			//listen to port
			socket_listen($this->socket);
			$this->console("Listening on ".$this->host.":".$this->port."\n");

			$this->sockets = array( $this->socket );
			$this->cData = array();

			while(true){

				$changed = $this->sockets;

				socket_select( $changed, $null, $null, 0, 10 );

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
					$new_socket = socket_accept($this->socket); 							//	1.	accpet new socket
					$this->sockets[] = $new_socket; 										//	2.	add socket to socket list
					$request = socket_read($new_socket, 1024); 								//	3.	read data sent by the socket

					$this->console("Performing websocket handshake.\n");
					$ouser = $this->handshake($request, $new_socket); 						//	4.	perform websocket handshake
					$this->cData[ array_search($new_socket,$this->sockets) ] = $ouser;		//	5.	store the client data

					$this->console($ouser->ouser_first_name." ".$ouser->ouser_last_name." has logged on.\n");

					$response = (object)array( 'channel'=>'all', 'type'=>'broadcast', 'message'=>$ouser->ouser_first_name.' '.$ouser->ouser_last_name.' connected.' ); //prepare json data
					$this->send($response); //notify all users about new connection

					$found_socket = array_search($this->socket, $changed);
					unset($changed[$found_socket]);											//	6.	remove new socket from changed array

					$this->console( (count($this->sockets)-1)." users connected.\n" );

				}

				/*************************************************************************************************

					3.	Loop through all the changed sockets

						//	1.	read from changed sockets
						//	2.	if EOF then close connection.

				*************************************************************************************************/
				
				foreach ( array_keys($changed) as $changed_key) {

					$changed_socket = $changed[$changed_key];

					//	1.	read from changed sockets
					while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
					{
						$msg = $this->unmask($buf); //unmask data
						//prepare data to be sent to client
						$msg = json_decode($msg);
						
						if( !empty($msg) ){

							$found_socket = array_search($changed_socket, $this->sockets);

							switch( $msg->type ){
								case 'subscription':
									$this->console("Received subscription, subscribing...");
									$this->cData[ $found_socket ]->subscriptions[ $msg->channel ] = TRUE;
									$this->console("done\n");
									break;
								case 'broadcast': case 'navigate':
									$this->console("Received broadcast, sending...");
									$this->send($msg);
									$this->console("done\n");
									break;
							}
						}
						
						break 2; //exits this loop
					}

					//	2.	if EOF then close connection.
					$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
					if ($buf === false) { // check disconnected client
						// remove client for $clients array
						$found_socket = array_search($changed_socket, $this->sockets);
						$this->console("%s","Attempting to disconnect index: ".$found_socket."\n");
						socket_getpeername($changed_socket, $ip);
						
						$ouser = $this->cData[$found_socket];
						$this->console($ouser->ouser_first_name." ".$ouser->ouser_last_name." has logged off.\n");
						
						//notify all users about disconnected connection
						$response = (object)array( 'channel'=>'all', 'type'=>'broadcast', 'message'=>$ouser->ouser_first_name.' '.$ouser->ouser_last_name.' disconnected.');
						$this->send($response);
						
						unset($this->sockets[$found_socket]);
					}

				}

			}

		}

		/********************************************************************************************************************

			send:  takes a message and passes though to all the connections subscribed to the specified channel

		********************************************************************************************************************/

		function send($msg){			
			foreach( array_keys($this->sockets) as $changed_key){
				$send_socket = $this->sockets[$changed_key];
				if( !empty($this->cData[ $changed_key ]) && !empty($this->cData[ $changed_key ]->subscriptions[$msg->channel]) ){
					$this->console("Sending message to ".$this->cData[ $changed_key ]->ouser_first_name." ".$this->cData[ $changed_key ]->ouser_last_name."\n");
					$message =  $this->mask( json_encode($msg) );
					socket_write($this->sockets[$changed_key], $message, strlen($message));	
				}				
			}
			return true;
		}

		/********************************************************************************************************************

			handshake:  Websocket require a sepcial response and this function is going to construct and send it over
						the established connection.

				1.	Extract the ouser from the connection request
				2.	Extract header information from request
				3.	Prepare/send response

		********************************************************************************************************************/

		function handshake($request,$conn){
			
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
			"WebSocket-Location: ws://$this->host:$this->port/demo/shout.php\r\n".
			"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
			socket_write($conn,$upgrade,strlen($upgrade));

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