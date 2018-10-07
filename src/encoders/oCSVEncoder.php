<?php

namespace obray\encoders;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class oCSVEncoder implements \obray\interfaces\oEncoderInterface
{

    private $extension = "csv";
    private $separator = ",";

    /**
     * returns the class property that if found will envoke
     * the encoder
     *
     * @return string The name of the class property.
     */
    public function getProperty(){
        return 'table';
    }

    /**
     * returns the content type for the encoder
     *
     * @return string the valid content type that will be returned in the response.
     */
    public function getContentType(){
        return 'text/html';
    }

    public function __construct($extension="csv", $separator=",")
    {
        $this->extension = $extension;
        $this->seperator = $separator;
    }

    /**
     * Takes some data and encodes it to html.
     * 
     * @param mixed $data The data to be encoded
     * 
     * @return mixed
     */

    public function encode($data, $start_time)
    {
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
        return false;
    }

    /**
     * Takes some data and outputs it appropariately
     * 
     * @param mixed $data The data to be displayed
     * 
     * @return null
     */

    public function out($obj)
    {
        
        header("Content-disposition: attachment; filename=\"".$obj->object.".".$this->extension."\"");
        header('Content-Type: application/octet-stream; charset=utf-8;');
        header("Content-Transfer-Encoding: utf-8");

        $fp = fopen('php://output', 'w');
        if( !empty($obj->data) ){ 
            $obj->data = $this->getCSVRows($obj->data);
            forEach( $obj->data as $index => $row_data ){
                $row = array_fill_keys($columns,'');
                $row = array_merge($row,$row_data);
                fputcsv($fp,$row,$this->separator);
            }
            if( $content_type = 'text/html' ){ echo '</table></body>'; }
        }
    }

    /**
     * Gets rows for CSV from data and return those reows
     * 
     * @param mixed $data The data to be formed into CSV rows
     * 
     * @return array
     */

    private function getCSVRows($data)
    {    
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
    
    /**
     * Flattens a nested data structure into multiple rows for a CSV
     * 
     * @param mixed $obj Data to be flattened
     * @param string $prefix Prefix to appended
     * @param array $columns stores an array of the columns we have
     * 
     * @return array return data as falttened array of rows
     */

    private function flattenForCSV($obj, $prefix='', $columns=array())
    {    
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

?>