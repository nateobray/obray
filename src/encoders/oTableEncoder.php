<?php

namespace obray\encoders;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class oTableEncoder implements \obray\interfaces\oEncoderInterface
{

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

        $withs = array();
        if( !empty($obj->table_definition) ){
            forEach( $obj->table_definition as $name => $col ){
                forEach( $col as $key => $prop ){ if( !in_array($key,['primary_key','label','required','data_type','type','slug_key','slug_value']) ){ $withs[] = $key; } }
            }
        }
        
        if( !empty($extension) ){ $fp = fopen('php://output', 'w'); }
        if( !empty($obj->data) ){ 
            $obj->data = $this->getCSVRows($obj->data);
            
            $columns = array();
            $biggest_row = new stdClass(); $biggest_row->index = 0; $biggest_row->count = 0;
            forEach( $obj->data as $i => $array ){
                $new = array_keys($array);
                $columns = array_merge($columns,$new);
                $columns = array_unique($columns);
            }
            
            $path = preg_replace('/with=[^&]*/','',$path);$path = str_replace('?&','?',$path);$path = str_replace('&&','&',$path);
            
            if( !empty($extension) ){ fputcsv($fp,$columns,$separator); } else { 
                echo '<html>';
                echo '<head><link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css"><script src="https://code.jquery.com/jquery-1.11.2.min.js"></script><script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script></head>';
                echo '<body>';
                $csv_path = str_replace('otable','ocsv',$path);
                $tsv_path = str_replace('otable','otsv',$path);
                $json_path = str_replace(['?otable','&otable'],'',$path);
                
                
                $col_dropdown = '<div class="btn-group" role="group"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Cols <span class="caret"></span></button><ul class="dropdown-menu" role="menu">';
                forEach( $columns as $col ){
                    $col_dropdown .='<li><a href="'.$path.'">'.$col.'</a></li>';
                }
                $col_dropdown .='</ul></div>';
                
                $with_dropdown = '<div class="btn-group" role="group"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">With <span class="caret"></span></button><ul class="dropdown-menu" role="menu">';
                forEach( $withs as $with ){
                    $with_dropdown .='<li><a href="'.$path.'&with='.$with.'">'.$with.'</a></li>';
                }
                $with_dropdown .='</ul></div>';
                
                
                echo '<div class="pull-right"><div class="btn-group">'.$col_dropdown.$with_dropdown.'<a class="btn btn-default" target="_blank" href="'.$csv_path.'">Download CSV</a><a target="_blank" class="btn btn-default" href="'.$tsv_path.'">Download TSV</a><a target="_blank" class="btn btn-default" href="'.$json_path.'">Show JSON</a>&nbsp;</div></div> <h2>'.$obj->object.'</h2> <table class="table table-bordered table-striped table-condensed" cellpadding="3" cellspacing="0">'; $this->putTableRow($columns,'tr','th'); }
            
            forEach( $obj->data as $index => $row_data ){
                $row = array_fill_keys($columns,'');
                $row = array_merge($row,$row_data);
                if( !empty($extension) ){ fputcsv($fp,$row,$separator); } else { $this->putTableRow($row); }
                flush();
            }
            if( $content_type = 'text/html' ){ echo '</table></body>'; }
            
        }

        private function putTableRow( $row,$r='tr',$d='td' ){
            echo '<'.$r.'>';
            forEach( $row as $value ){ echo '<'.$d.' style="white-space: nowrap;">'.$value.'</'.$d.'>'; }
            echo '</'.$r.'>';
        }

    }

}

?>