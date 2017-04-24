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
			2.	Check for new connections: Basically we're checking to see if our original socket has been added to the
				changed array and if so we now it has a new connection waiting to be handled.

				//	1.	accpet new socket
				//	2.	add socket to socket list
				//	3.	read data sent by the socket
				//	4.	perform websocket handshake
				//	5.	store the client data
				//	6.	remove new socket from changed array

			3.	Loop through all the changed sockets

				//	1.	Get changed socket
				//	2.	Read from changed socket
				//	3.	if EOF then close connection.

			4.	Cleanup old connects

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

					2.	Check for new connections: Basically we're checking to see if our original socket has
						been added to the changed array and if so we know it has a new connection waiting to be
						handled.

						//	1.	accpet new socket
						//	2.	add socket to socket list
						//	3.	read data sent by the socket
						//	4.	perform websocket handshake
						//	5.	store the client data
						//	6.	notify all users of newely connected user
						//	7.	remove new socket from changed array


				*************************************************************************************************/

				if( in_array($this->socket,$changed) ){

					//	1.	accpet new socket
					$this->console("Attempting to connect a new client.\n");
					$new_socket = stream_socket_accept($this->socket,5);

					if( !$new_socket ){
						$this->console("%s","Unable to connect.\n","RedBold");
						$found_socket = array_search($this->socket, $changed);
						unset($changed[$found_socket]);
						continue;
					}

					stream_set_timeout($new_socket,5);
					//socket_set_option($new_socket, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>5, 'usec'=>0));
					//socket_set_option($new_socket, SOL_SOCKET, SO_SNDTIMEO, array('sec'=>5, 'usec'=>0));

					if( $new_socket !== FALSE ){

						//	2.	add socket to socket list
						$this->console("Reading from socket.\n");
						$this->sockets[] = $new_socket;
						$request = fread($new_socket, 8184);

						//	4.	perform websocket handshake and retreive user data
						$this->console("Performing websocket handshake.\n");
						$ouser = $this->handshake($request, $new_socket);
						if( is_object($ouser)  ){

							//	5.	store the user data
							$ouser->websocket_login_datetime = strtotime('now');
							$ouser->subscriptions = array();
							$found_socket = array_search($new_socket,$this->sockets);
							$this->cData[ $found_socket ] = $ouser;
							$this->subscribe($found_socket,md5("all"));
							$this->subscribe($found_socket,md5($ouser->ouser_id));
							$this->console("%s",$ouser->ouser_first_name." ".$ouser->ouser_last_name." has logged on.\n","GreenBold");

							//	6.	notify all users of newely connected user
							$response = (object)array( 'channel'=>'all', 'type'=>'broadcast', 'message'=>$ouser->ouser_first_name.' '.$ouser->ouser_last_name.' connected.' );
							$this->send($response);

							//	7.	remove new socket from changed array
							// removes original socket from changed array (so we don't keep looking for a new connections)
							$found_socket = array_search($this->socket, $changed);
							unset($changed[$found_socket]);
							$this->console( (count($this->sockets)-1)." users connected.\n" );
						} else if ( is_string($ouser) ){

							// do nothing
							$this->console("%s","Connected obray-client\n","GreenBold");
							$found_socket = array_search($this->socket, $changed);
							unset($changed[ $found_socket ]);
							$this->obray_clients[array_search($new_socket,$this->sockets)] = TRUE;

						} else {

							// abort if unable to find user
							$this->console("%s","Connection failed, unable to connect user (not found).\n","RedBold");
							// removes original socket from the changed array (so we don't keep looking for a new connections)
							$found_socket = array_search($this->socket, $changed);
							unset($changed[$found_socket]);
							// removes our newely connected socket from our sockets array (aborting the connection)
							$found_socket = array_search($new_socket, $this->sockets);
							unset($this->sockets[$found_socket]);

						}

					} else {

						// if connection failed, remove socket from changed list
						$this->console("%s","Connection failed, unable to connect user.\n","RedBold");
						// removes original socket from changed array (so we don't keep looking for a new connections)
						$found_socket = array_search($this->socket, $changed);
						unset($changed[$found_socket]);

					}

				}

				/*************************************************************************************************

					3.	Loop through all the changed sockets

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

						try{
							$buf = fread($changed_socket, 2048);
						} catch(Exception $err) {
							$this->console("%s","Unable to read form socket: ".$err->getMessage()."\n","RedBold");
							$this->disconnect($changed_socket);
							break;
						}

						if( !empty($this->obray_clients[ array_search($changed_socket,$this->sockets) ]) ){

							 $msg = json_decode(trim($buf,"\x00\xff"));
							 $this->console("%s","obray-client sending message...");
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

							 @fwrite( $this->sockets[ array_search($changed_socket,$this->sockets) ] , $message, strlen($message));

						} else {
							$this->decode($buf,$changed_socket);
						}



						// this prevent possible endless loops
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

				$this->console("%s","Attempting to disconnect index: ".$found_socket."\n","RedBold");


				//	1.	remove the changes socket from the list of sockets
				unset($this->sockets[$found_socket]);

				//	2.	shutdown the socket connection
				stream_socket_shutdown($changed_socket,STREAM_SHUT_RDWR);

				//	3.	if client is obray disconnect and return
				if( !empty($this->obray_clients[ $found_socket ]) ){
					$this->console("%s","obray-client disconnected.\n","RedBold");
					unset($this->obray_clients[ $found_socket ]);
					return;
				}

				//	4.	remove all subscriptions
				forEach( $this->cData[ $found_socket ]->subscriptions as $key => $value ){
					$this->unsubscribe($found_socket,$key);
				}

				//	5.	remove the connection data and socket
				$ouser = $this->cData[$found_socket];
				$this->console("%s",$ouser->ouser_first_name." ".$ouser->ouser_last_name." has logged off.\n","Red");
				unset($this->cData[$found_socket]);

				//	6.	notify all users about disconnected connection
				$response = (object)array( 'channel'=>'all', 'type'=>'broadcast', 'message'=>$ouser->ouser_first_name.' '.$ouser->ouser_last_name.' disconnected.');
				$this->send($response);

				//	7.	broadcasting list of users
				$this->sendList();

			} else {
				$this->console("%s","Socket not found, unable to disconnect.\n","RedBold");
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

			$this->console("Received subscription, subscribing...");
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

			$this->console("%s","Received unsubscribe, unsubcribing...","RedBold");
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
			$this->console("Received list, sending...");
			$data = array();
			forEach( $this->cData as $user ){
				if( count($data) > 50 ){ break; }
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
		        	$fwrite = fwrite($socket, substr($string, $written));
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
					$this->console("%s"," writing ","YellowBold");
					if( $this->fwrite_stream($send_socket,$message) == FALSE ){
						$this->console("%s"," failed ","RedBold");
						$this->disconnect($send_socket);
						$this->console("%s"," failed ","RedBold");
						$msg_sent[$channel] = FALSE;
					} else {

						$this->console("%s"," succeeded ","GreenBold");
						$msg_sent[$channel] = TRUE;
					}

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

			if( $this->debug ){
				$this->console("%s","\n---------------------------------------------------------------------------------------\n","BlueBold");
				$this->console($request);
				$this->console("%s","\n---------------------------------------------------------------------------------------\n\n","BlueBold");
			}

			preg_match('/(?<=GET \/\?ouser_id=)([0-9]*)/',$request,$matches);

			// 1.	Extract the ouser from the connection request
			$ouser = "obray-client";
			if( !empty($matches) ){
				$ouser_id = $matches[0];
				$this->setDatabaseConnection(getDatabaseConnection(true));
				$this->console( 'retreiving user: /obray/OUsers/get/?ouser_id='.$ouser_id.'&with=options'."\n" );
				$new_user = $this->route('/obray/OUsers/get/?ouser_id='.$ouser_id.'&with=options');

				if( !empty($new_user->data[0]) ){
					$ouser = new stdClass();
					$ouser->ouser_id = $new_user->data[0]->ouser_id;
					$ouser->ouser_first_name = $new_user->data[0]->ouser_first_name;
					$ouser->ouser_last_name = $new_user->data[0]->ouser_last_name;
					$ouser->ouser_group = $new_user->data[0]->ouser_group;
				} else {
					$ouser = new stdClass();
				}

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
					if( is_object($ouser) && !empty($ouser->connection) ){
						$ouser->connection->{$matches[1]} = $matches[2];
					}
				}
			}

			// 3.	Prepare/send response
			if( empty($headers['Sec-WebSocket-Key']) ){ return; }
			$secKey = $headers['Sec-WebSocket-Key'];
			$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
			//hand shaking header
			$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
			"Upgrade: websocket\r\n" .
			"Connection: Upgrade\r\n" .
			"WebSocket-Origin: $this->host\r\n" .
			"WebSocket-Location: ws://$this->host:$this->port/\r\n".
			"Sec-WebSocket-Accept:$secAccept\r\n\r\n";

			if( $this->debug ){
				$this->console("Send upgrade request headers.\n");
				$this->console("%s","\n---------------------------------------------------------------------------------------\n","BlueBold");
				$this->console( $upgrade );
				$this->console("%s","\n---------------------------------------------------------------------------------------\n\n","BlueBold");
				$this->console($conn);
			}
			fwrite($conn,$upgrade,strlen($upgrade));

			return $ouser;

		}

		/********************************************************************************************************************

			Decode: We have to manipulate some bits based on the spec.  Currently there is a limit to the number of
			 		bits we can send, but that is easily remedied by modifying this function.

		********************************************************************************************************************/

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
                                                                                                                                                                                                                                                                                                                        
