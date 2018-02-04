<?php

namespace obray\encoders;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class oJSONEncoder implements \obray\interfaces\oEncoderInterface
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
        $json = json_encode($data,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
        if( $json === FALSE ){ $json = json_encode($data,JSON_PRETTY_PRINT); }
        if( $json ){ echo $json; } else { echo 'There was en error encoding JSON.'; }
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
        echo $data;
    }

}

?>