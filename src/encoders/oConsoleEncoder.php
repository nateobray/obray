<?php

namespace obray\encoders;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class oConsoleEncoder implements \obray\interfaces\oEncoderInterface
{

    /**
     * Takes some data and encodes it to json.
     * 
     * @param mixed $data The data to be encoded
     * 
     * @return mixed
     */
    public function encode($data, $start_time)
    {
        $data->runtime = (microtime(TRUE) - $start_time)*1000;
        return $data;
    }

    /**
     * Takes some data and decodes it
     * 
     * @param mixed $data The data to be decoded
     * 
     * @return mixed
     */
    public function decode($data)
    {
        return json_decode($data);
    }

    /**
     * Takes some data and outputs it appropariately
     * 
     * @param mixed $data The data to be displayed
     * 
     * @return null
     */
    public function out($data)
    {
        $obj = new \obray\oObject();
        $obj->cleanUp();
        if( isSet($data->object) ){ $obj->object = $data->object; } else { $obj->object = ""; }
        if( isSet($data->data) ){ $obj->data = $data->data; }
        if( isSet($data->errors) ){ $obj->errors = $data->errors; }
        if( isSet($data->html) ){ $obj->errors = $data->html; }
        if( isSet($data->runtime) ){ $obj->runtime = $data->runtime; }
        print_r($obj);
    }

}

?>