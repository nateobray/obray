<?php
	
    /***********************************************************************
	
    Obray - Super lightweight framework.  Write a little, do a lot, fast.
    Copyright (C) 2013  Nathan A Obray

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    ***********************************************************************/
	
	if (!class_exists( 'OObject' )) { die(); }
	
	/********************************************************************************************************************
		
		ORC (Obray Resource Concatenator):    This object searches your views in a specified directory and concatenates
		                                      a specified file type into one file and places it in the /assets/[extension]/
		                                      folder.
		                                      
		                                      An example of its usage is to concatenate all your views CSS into:
		                                      
		                                          /assets/css/resources-[timestamp].css
		                                          
		                                      This way if you write a widget using Obray you can simply include this
		                                      file in the head of your HTML template.  This class also contains a view
		                                      to output tags for you:
		                                      
		                                          e.g. $this->route('/cmd/lib/ORC/output/?ext=css');
		                                          
		                                      This would output: <link href="/assets/css/resources-[timestamp].css" />
		
	********************************************************************************************************************/
	
	Class ORC extends OView{
		
       public function concatenate($params){
           
           $extension = $params["extension"];
           if( isSet($_REQUEST["refresh"]) ){ 
               
               $resource = '';	
               
               $d = $this->sdir(_SELF_.'widgets/');
            	
               $del = $this->sdir('assets/'.$extension.'/','[!.]*.'.$extension);
               forEach($del as $file){	unlink('./assets/'.$extension.'/'.$file);	}
            	
               forEach($d as $dir){
                   $v = $this->sdir(_SELF_.'widgets/'.$dir.'/views/');
                   forEach($v as $sub_dir){
                       $r = $this->sdir(_SELF_.'widgets/'.$dir.'/views/'.$sub_dir.'/'.$extension.'/','[!.~]*.'.$extension);
                       forEach($r as $file){ 
                           $resource .= $this->get_include_contents(_SELF_.'widgets/'.$dir.'/views/'.$sub_dir.'/'.$extension.'/'.$file); 
                       }   
                    }
                }
            	$new_file = 'assets/'.$extension.'/resources-'.time().'.'.$extension;
            	file_put_contents($new_file,$resource);
            	
        	}
        	
        	$f = $this->sdir('assets/'.$extension.'/','[!.]*.'.$extension);
        	
        	forEach($f as $file){
            	switch($extension){
                	case "css": echo '<link rel="stylesheet" type="text/css" href="/assets/css/'.$file.'"/>'; break;
                	case "js":  echo '<script src="/assets/js/'.$file.'"></script>'; break;
            	}
        	}
        	
		}
		
		private function sdir( $path='.', $mask='*', $nocache=0 ){ 
    		$sdir = array(); //static $dir = array(); // cache result in memory 
		    if ( !isset($dir[$path]) || $nocache) { 
		        $dir[$path] = scandir($path); 
		    } 
		    foreach ($dir[$path] as $i=>$entry) { 
		        if ($entry!='.' && $entry!='..' && fnmatch($mask, $entry) ) { 
		            $sdir[] = $entry; 
		        } 
		    } 
		    return ($sdir); 
		} 
		
		private function get_include_contents($filename) {
		    if (is_file($filename)) {
		        ob_start();
		        include $filename;
		        return ob_get_clean();
		    }
		    return false;
		}
		
	}
	
	
