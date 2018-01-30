<?php

namespace obray\encoders;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class oHTMLEncoder implements \obray\interfaces\oEncoderInterface
{

    /**
     * Takes some data and encodes it to html.
     * 
     * @param mixed $data The data to be encoded
     * 
     * @return mixed
     */
    public function encode($data, $start_time)
    {
        return $data->html;
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
        return false;
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