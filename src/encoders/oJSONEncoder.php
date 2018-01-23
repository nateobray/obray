<?php

namespace obray;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class oJSONEncoder implements \obray\encoders\oEncoderInterface
{

    /**
     * Takes some data and encodes it to json.
     * 
     * @param mixed $data The data to be encoded
     * 
     * @return mixed
     */
    public function encode($data)
    {
        return json_encode($data);
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

}

?>