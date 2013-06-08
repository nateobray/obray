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
	
	require_once("OCMS.php");
	require_once("OForm.php");
	
	Class OMedia extends OForm {
		
		public function __construct(){
			
			$this->table = "omedia";
			$this->table_definition = array(
				"omedia_id" => 				array( "primary_key" => TRUE ),
				"omedia_file" => 			array( "label" => "File",			"required" => FALSE,	"data_type" => "varchar(250)",	"type" => "file" ),
				"omedia_ext" => 			array( "label" => "Extension",		"required" => TRUE,		"data_type" => "varchar(25)" ),
				"omedia_type" => 			array( "label" => "Type",			"required" => TRUE,		"data_type" => "varchar(25)" ),	
				"omedia_description" =>		array( "label" => "Description",	"required" => FALSE,	"data_type" => "text",			"type" => "textarea" ),
				"omedia_width" =>			array( "label" => "Width",			"required" => FALSE,	"data_type" => "integer" ),
				"omedia_height" => 			array( "label" => "Height",			"required" => FALSE,	"data_type" => "integer" ),
				"omedia_path" =>			array( "label" => "Path",			"required" => FALSE,	"data_type" => "varchar(250)" ),
				"omedia_code" =>			array( "label" => "Code",			"required" => FALSE,	"data_type" => "text" )
			);
			
			parent::__construct();
			
			$this->permissions = array(
				"object" => 1,
				"form" => 1,
				"upload" => 1
			);
			
			$this->base_url = "/cms/OMedia/";
			
		}
		
		/***********************************************************************
		
			PUBLIC: UPLOAD Function
				
				1)	Handle file from URL
				2)	Process files & generate omedia record
			
		***********************************************************************/
		
		public function upload($params=array()){
			
			$this->data = array();
			
			if( empty($_FILES) && !isSet($params["url"]) ){ $this->throwError("Unable to upload because file is missing.",501); }
			
			if( empty($this->errors) ){
			
				// 1)	Handle file from URL
				
				$_FILES = array();
				if( isSet($params["url"]) ){
					
					$url = $params["url"];
					$file = explode('/',$params["url"]); $file = $file[count($file)-1];
					$img = __SELF__.'assets/tmp/'.$file;
					$_FILES[] = array("name"=>$img,"tmp_name"=>$img,"error"=>UPLOAD_ERR_OK,"upload"=>FALSE);
					try{ $tmp = @file_get_contents($url);
					} catch(Exception $ERR){ $this->throwError($ERR,501);  }
					if( $tmp === FALSE || file_put_contents($img, $tmp) === FALSE ){ 
						$this->throwError("There was an error saving from $url.",501); 
						$_FILES[count($_FILES)-1]["error"] = TRUE;
					}
					unset($params["url"]);unset($_REQUEST["url"]);
					
				}
				
				// 2)	Process files & generate omedia record
				
				forEach($_FILES as $file){
				
					$this->data[] = $file;
					
					if ( $file["error"] === UPLOAD_ERR_OK) {
						
						$ext = explode('.',basename($file["name"]));
						
						$_REQUEST["omedia_ext"] = $ext_str = $this->data["ext"] = array_pop($ext);
						$_REQUEST["omedia_file"] = $this->data["file"] = implode('.',$ext) . time(); 
						$ext = $ext_str;
						
						$this->data["ext"] = $ext;
						
						if( $ext == 'JPG' || $ext == 'PNG' || $ext == "GIF" || $ext == "JPEG" || $ext == 'jpg' || $ext == 'png' || $ext == 'gif' || $ext == 'jpeg' ){
							$_REQUEST["omedia_type"] = "image";
							$path = __SELF__.'assets/images/' . $this->data["file"] . '_original' . '.' . $this->data["ext"];
						} else if( $ext == "MOV" || $ext == "MPG" || $ext == "MPEG" || $ext == "MP4" || $ext == "FLV" || $ext == 'mov' || $ext == 'mpg' || $ext == 'mpeg' || $ext == 'mp4' || $ext == 'flv' ){
							$_REQUEST["omedia_type"] = "video";
							$path = __SELF__.'assets/videos/' . $this->data["file"] . '_original' . '.' . $this->data["ext"];
						} else {
							if( $_REQUEST["omedia_ext"] != 'pdf' ){ exit(); }
							$_REQUEST["omedia_type"] = "file";
							$path = __SELF__.'assets/files/' . $this->data["file"] . '_original' . '.' . $this->data["ext"];
						}
			    		if( !isSet($file["upload"]) ){ move_uploaded_file($file["tmp_name"], $path); } else { if (copy($file["tmp_name"],$path)) {  unlink($file["tmp_name"]); }  }
			    		$_REQUEST["omedia_path"] = $this->path = str_replace(__SELF__,"",$path);
			    		
			    		$this->add($_REQUEST);
			    		
			    		
		    		}
		    		
	    		}
    		
    		}
    		
    		return $this;
    		
		}
		
		/***********************************************************************
		
			PUBLIC: ADD Function
			
		***********************************************************************/
		
		public function add($params=array()){
			
			if( isSet($params["omedia_width"]) && isSet($params["omedia_height"]) && isSet($params["parent_id"]) ){
				
				$this->get(array("omedia_id"=>$params["parent_id"]));
				
				$size = $this->getSize(__SELF__.$this->data[0]->omedia_path);
				$src = imagecreatefromjpeg(__SELF__.$this->data[0]->omedia_path);
			    $dst = imagecreatetruecolor($params["omedia_width"], $params["omedia_height"]);
			    imagecopyresampled($dst, $src, 0, 0, 0, 0, $params["omedia_width"], $params["omedia_height"], $size["width"], $size["height"]);
			    
			    switch(strtolower($this->data[0]->omedia_ext)){
				    case 'jpg': imagejpeg( $dst, __SELF__ . "assets/images/" . $this->data[0]->omedia_file.'-'.$params["omedia_width"].'x'.$params["omedia_height"].'.'.$this->data[0]->omedia_ext ); break;
				    case 'png': imagepng(  $dst, __SELF__ . "assets/images/" . $this->data[0]->omedia_file.'-'.$params["omedia_width"].'x'.$params["omedia_height"].'.'.$this->data[0]->omedia_ext ); break;
				    case 'gif': imagegif(  $dst, __SELF__ . "assets/images/" . $this->data[0]->omedia_file.'-'.$params["omedia_width"].'x'.$params["omedia_height"].'.'.$this->data[0]->omedia_ext ); break;
			    }
			    
			    $this->data[0]->omedia_width = $params["omedia_width"];
			    $this->data[0]->omedia_height = $params["omedia_height"];
			    $this->data[0]->parent_id = $this->data[0]->omedia_id;
			    unset($this->data[0]->omedia_id);
			    $this->data[0]->omedia_path = "assets/images/" . $this->data[0]->omedia_file.'-'.$params["omedia_width"].'x'.$params["omedia_height"].'.'.$this->data[0]->omedia_ext;
			    $params = (array)$this->data[0];
			    
			} else {
				
				$size = $this->getSize(__SELF__.$_REQUEST["omedia_path"]);
				$params["omedia_width"] = $size["width"];
				$params["omedia_height"] = $size["height"];
				
			}
			
			if( !isSet($params["omedia_description"]) ){ $params["omedia_description"] = ""; }
			
			switch($params["omedia_type"]){
				
				case "image":
					$params["omedia_code"] = '<img class="omedia-'.$params["omedia_ext"].' omedia-image" src="/'.$params["omedia_path"].'" width="'.$params["omedia_width"].'" height="'.$params["omedia_height"].'" alt="'.$params["omedia_description"].'" />';
					break;
				case "video":
				
				case "file":
					$params["omedia_code"] = '<a class="omedia-'.$params["omedia_ext"].' omedia-file" href="'.$params["omedia_path"].'"> target="_blank" >'.$params["omedia_description"].'</a>';
					break;
			}
			
			$params["omedia_path"] = "/".$params["omedia_path"];
			
			parent::add($params);
			
			return $this;
		}
		
		/***********************************************************************
		
			PUBLIC: OUT Function
			
		***********************************************************************/
		
		public function out($params=array()){
			
			$this->html = "";
			
			$parent = $this->route('/cms/OMedia/get/?omedia_id='.$params["omedia_id"]);
			$params["parent_id"] = $params["omedia_id"];
			unset($params["omedia_id"]);
			
			$this->get($params);
			
			if( empty($this->data) ){
				
				$this->add($params);
				
				$this->get($params);
			}
			
			
			forEach($this->data as $omedia){ $this->html .= $omedia->omedia_code; }
			
		}
		
		/***********************************************************************
		
			PRIVATE: getSize Function
			
		***********************************************************************/
		
		private function getSize($path){
			
			$props = getImageSize($path);
			return array("width"=>$props[0],"height"=>$props[1]);
			
		}
		
	}
	
	?>
	