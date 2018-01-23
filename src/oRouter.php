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

    public function __construct( \obray\oFactoryInterface $factory, \obray\oInvokerInterface $invoker, \Psr\Container\ContainerInterface $container, $debug=false )
    {
        $this->factory = $factory;
        $this->invoker = $invoker;
        $this->container = $container;
        $this->debug_mode = $debug;
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
        
        $this->start_time = microtime(TRUE);
        $this->consolidateParams($params);

        try{
            $obj = parent::route($path,$params,$direct);                                                // Call the parent class default route function
        } catch( \Exception $e ){
            $obj = new \obray\oObject();
            $obj->throwError($e->getMessage(), $e->getCode());
            if( $this->debug_mode ){
                $obj->throwError($e->getFile(), $e->getCode());
                $obj->throwError($e->getLine(), $e->getCode());
                $obj->throwError($e->getTrace(), $e->getCode());
            }
        }

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
        
        if(!headers_sent()){ header('HTTP/1.1 '.$obj->getStatusCode().' ' . $status_codes[$obj->getStatusCode()] );}    // set HTTP Header
        if( $this->content_type == 'text/table' ){ $tmp_type = 'text/table'; $content_type = 'text/html';  }
        if(!headers_sent()){ header('Content-Type: ' . $this->content_type ); }                                                  // set Content-Type
        if( !empty($tmp_type) ){ $this->content_type = $tmp_type; }

        print_r($this->content_type);
        if( !empty($this->encoders[$this->content_type]) ){
            $encoder = $this->encoders[$this->content_type];
            $encoded = $encoder->encode($obj);
            $encoder->output($encoded);
        } else {
            print_r("Encoder not found.");
        }
        
        return $obj;

    }

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

    public function addEncoder($content_type,$encoder)
    {
        print_r($content_type);
        exit();
        $this->encoders[$content_type] = $encoder;
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