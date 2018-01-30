<?php

/**
 * @license MIT
 */

namespace obray;
if (!class_exists( 'obray\oObject' )) { die(); }

/**
 * This class handles incoming HTTP requests by routing them to the
 * associated object and then providing the response.
 */

Class oRouter extends oObject
{
    
    private $status_code = 200;
    private $content_type = "application/json";
    private $start_time = 0;
    private $encoders = [];

    /**
     * The constructor take a a factory, invoker, and container.  Debug mode is also set in
     * the constructor.
     * 
     * @param \obray\oFactoryInterface $factory Takes a factory interface
     * @param \obray\oInvokerInterface $invoker Takes an invoker interface
     * @param \Psr\Container\ContainerInterface $container Variable that contains the container
     */

    public function __construct( \obray\interfaces\oFactoryInterface $factory, \obray\interfaces\oInvokerInterface $invoker, \Psr\Container\ContainerInterface $container, $debug=false )
    {
        $this->factory = $factory;
        $this->invoker = $invoker;
        $this->container = $container;
        $this->debug_mode = $debug;
        $this->mode = "http";
    }

    /**
     * This function is used to route an incoming URL to the associated object and formulates
     * the corresponding response.
     * 
     * @param string $path This is the path to the object, usually passed from a URL, but could also come from the console
     * @param array $params This is a keyed array of parameters we ultimately want to pass to a function
     * @param bool $direct Specifies if this is a direct calls (all permissions), or remote (specified permissions)
     */

    public function route($path,$params=array(),$direct=false){
        
        // record application start time
        $this->start_time = microtime(TRUE);
        // conslidate all POST and GET into params
        $this->consolidateParams($params);
        // set the mode
        if( PHP_SAPI === 'cli' ){ 
            $this->mode = 'console'; 
        }

        // attempt to route the request with the set factory, invoker, and container
        try{
            $obj = parent::route($path,$params,$direct);
        } catch( \Exception $e ){
            $obj = new \obray\oObject();
            $obj->throwError($e->getMessage(), $e->getCode());
            if( $this->debug_mode ){
                $obj->throwError($e->getFile(), $e->getCode(),"file");
                $obj->throwError($e->getLine(), $e->getCode(),"line");
                $obj->throwError($e->getTrace(), $e->getCode(),"trace");
            }
        }

        // prepare output method
        switch( $this->mode ){
            case 'http':
                $this->prepareHTTP($obj);
                break;
            case 'console':
                $this->prepareConsole($obj);
                break;
        }
        
        // encode data and output
        if( !empty($this->encoders[$this->content_type]) ){
            $encoder = $this->encoders[$this->content_type];
            $encoded = $encoder->encode($obj, $this->start_time);
            $encoder->out($encoded);
        } else {
            throw new \Exception("Unable to find encoder for this content type.");
        }
        
        // return object
        return $obj;

    }

    /**
     * This function is used to add to the list of encodres for a given content
     * type.  Only one encoder per type is allowed.
     * 
     * @param string $content_type This should be a valid HTTP content type
     * @param \obray\encoders\oEncoderInterface $encoder Stores the object that will be used to encode/decode/out
     */

    public function addEncoder($content_type,$encoder)
    {
        $this->encoders[$content_type] = $encoder;
    }

    /**
     * This function takes the parameters passed in and combines them with
     * posted data.  I also will decode any incoming json and put it in as
     * data in the params.
     * 
     * @param array $params This should contain a keyed list of parameters
     */

    private function consolidateParams( &$params ){
        $php_input = file_get_contents("php://input");
        if( !empty($php_input) && empty($params['data']) ){
            if( $_SERVER["CONTENT_TYPE"] === 'application/json' ){
                $params = (array)json_decode($php_input);
            } else {
                $params["data"] = $php_input;
            }                
        }
    }

    /**
     * The pepareHTTP function sets up headers for and HTTP response
     * back to the client.
     * 
     * @param mixed $obj This should contain the obj off of which we will formulate a response
     */

    private function prepareHTTP($obj){

        $status_codes = include("oRouterStatusCodes.php");
        if( method_exists($obj,'getStatusCode') && $obj->getStatusCode() == 401 ) {
            header('WWW-Authenticate: Basic realm="application"');
            if( method_exists($obj, "auth") ) {
                $obj->auth();
            }
        }
    
        if( method_exists($obj,'getContentType') ){
            $this->content_type = $obj->getContentType();
        }
        
        if(!headers_sent()){ 
            header('HTTP/1.1 '.$obj->getStatusCode().' ' . $status_codes[$obj->getStatusCode()] );
            if( $this->content_type == 'text/table' ){ 
                $tmp_type = 'text/table'; $content_type = 'text/html';  
            }
            header('Content-Type: ' . $this->content_type );
            if( !empty($tmp_type) ){ 
                $this->content_type = $tmp_type; 
            }
        }

        if( method_exists($obj,'cleanUp') ){
            $obj->cleanUp();
        }

    }

    /**
     * This will prepare for output to console by setting content_type to console
     * 
     * @param mixed $obj This should contain the obj off of which we will formulate a response
     */

    private function prepareConsole($obj){
        $this->content_type = "console";
    }
    
}