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
	
	Class OMediaLinks extends OForm {
		
		public function __construct(){
			
			$this->table = "omedialinks";
			$this->table_definition = array(
				"omedia_link_id" => 			array( "primary_key" => TRUE ),
				"omedia_id" => 					array( "label" => "Select File to Upload",		"required" => TRUE,		"data_type" => "integer",		"type" => "file" ),
				"omedia_link_name" => 			array( "label" => "Name",					"required" => FALSE,	"data_type" => "varchar(250)",	"type" => "hidden" )
			);
			
			parent::__construct();
			
			$this->permissions = array(
				"object" => 1,
				"form" => 1,
				"add" => 1,
				"delete" => 1
			);
			
			$this->base_url = "/cms/OMediaLinks/";
			
		}
		
		/***********************************************************************
			
			PUBLIC: ADD Function
			
		***********************************************************************/
		
		public function add($params=array()){
			
			$this->get(array("omedia_link_name"=>$params["omedia_link_name"]));
			
			if( empty($this->data) ){
				parent::add($params);
			} else {
				$params["omedia_link_id"] = $this->data[0]->omedia_link_id;
				parent::update($params);
			}
			
		}
		
		/***********************************************************************
			
			PUBLIC: OUT Function
			
		***********************************************************************/
		
		public function out($params=array()){
		
			$this->get($params);
			if( isSet($params["omedia_link_name"]) ){ $name = $params["omedia_link_name"];  } else { $name = ""; }
			if( isSet($params["omedia_width"]) ){ $width = $params["omedia_width"]; } else { $width = ""; }
			if( isSet($params["omedia_height"]) ){ $height = $params["omedia_height"]; } else { $height = ""; }
			
			$this->html = "";
			forEach($this->data as $link){
				$this->html .= '<div class="omedia-links" data-link-id="'.$link->omedia_link_id.'" data-link-name="'.$name.'" >';
				$this->html .= $this->route('/cms/OMedia/out/?omedia_id='.$link->omedia_id,array("omedia_width"=>$width,"omedia_height"=>$height))->html;
				$this->html .= '</div>';
			}
			
			if( empty($this->data) ){
				$this->html .= '<div class="omedia-links" data-link-name="'.$name.'" data-omedia-width="'.$width.'" data-omedia-height="'.$height.'" >';
				$this->html .= '</div>';
			}
				
		}
		
	}
	
	?>
	