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

		oWebSocketClient:

		1.  Establish a connection on specified host and port

			//	1.	check that we have what we need to send a message
			//	2.	retreive host and ports or set them to defaults
			//	3.	determine the protocol to connect (essentially on client side ws or wss) and create
			//		context.
			//	4.	establish connection or abort on error
			//	5.	form message object and encode json
			//	6.	create header and upgrade connection
			//	7.	write message to the socket connection, close connection

	********************************************************************************************************************/

	Class oWebSocketClient extends obray\oDBO {

		public function __construct($params){

			/*************************************************************************************************

				1.  Establish a connection on specified host and port

				//	1.	check that we have what we need to send a message
				//	2.	retreive host and ports or set them to defaults
				//	3.	determine the protocol to connect (essentially on client side ws or wss) and create
				//		context.
				//	4.	establish connection or abort on error
				//	5.	form message object and encode json
				//	6.	create header and upgrade connection
				//	7.	write message to the socket connection, close connection

			*************************************************************************************************/

			//	1.	check that we have what we need to send a message
			if( empty($params["channel"]) ){ $this->throwError("Please specify the channel for your message."); }
			if( empty($params["type"]) ){ $this->throwError("Please specify the type of message you are sending."); }
			if( empty($params["message"]) ){ $this->throwError("Please include a message."); }

			if( !empty($this->errors) ){
				return;
			}

			//	2.	retreive host and ports or set them to defaults
			$this->host = !empty($params["host"])?$params["host"]:"localhost";
			$this->port = !empty($params["port"])?$params["port"]:"80";
			$this->debug = FALSE;
			if( !empty($params["debug"]) ){
				$this->debug = TRUE;
			}

			//	3.	determine the protocol to connect (essentially on client side ws or wss) and create
			//		context.
			if( __WEB_SOCKET_PROTOCOL__ == "ws" ){

				$protocol = "tcp";
				$context = 	stream_context_create();

			} else {

				$protocol = "ssl";
				try{
					$context = stream_context_create( array( "ssl" => array( "local_cert"=>__WEB_SOCKET_CERT__, "local_pk"=>__WEB_SOCKET_KEY__, "passphrase" => __WEB_SOCKET_KEY_PASS__ ) ) );
				} catch( Exception $err ){
					$this->console("Unable to create stream context: ".$err->getMessage()."\n");
					$this->throwError("Unable to create stream context: ".$err->getMessage());
					return;
				}

			}

			//	4.	establish connection or abort on error
			$listenstr = 	$protocol."://".$this->host.":".$this->port;
			$this->console("Binding to ".$this->host.":".$this->port." over ".$protocol."\n");
			$this->socket = @stream_socket_client($listenstr,$errno,$errstr,5,STREAM_CLIENT_CONNECT,$context);

			if( !is_resource($this->socket) ){
				$this->console("%s",$errstr."\n","RedBold");
				$this->throwError($errstr);
				return;
			}

			//	5.	form message object and encode json
			$data = json_encode((object)array(
				"channel" => $params["channel"],
				"type" => $params["type"],
				"message" => $params["message"]
			));

			//	6.	create header and upgrade connection
			$upgrade = "GET / HTTP/1.1\r\n".
		    "Host: $this->host\r\n".
		    "Upgrade: websocket\r\n".
		    "Connection: Upgrade\r\n".
		    "Sec-WebSocket-Key: ". base64_encode( substr( md5(strtotime("now").__OBRAY_TOKEN__), 0, 16 ) )."\r\n".
		    "Sec-WebSocket-Version: 13\r\n".
		    "Content-Length: ".strlen($data)."\r\n\r\n";
			fwrite($this->socket, $upgrade );
			$headers = fread($this->socket, 2000);

			//	7.	write message to the socket connection, close connection
			fwrite($this->socket, "\x00$data\xff" );

			$response = fread($this->socket, 2000);  //receives the data included in the websocket package "\x00DATA\xff"
			$this->data = trim($response,"\x00\xff"); //extracts data
			$this->console($this->data);
			$this->data = explode("\n",$this->data);
			$this->data = json_decode(array_pop($this->data) );

			fclose($this->socket);

		}

	}
?>
