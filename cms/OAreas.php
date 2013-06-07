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
	
	Class OAreas extends ODBO {
		
		public function __construct(){
			
			$this->table = "oareas";
			$this->table_definition = array(
				"oarea_id" => 				array( "primary_key" => TRUE ),
				"oarea_name" => 			array( "label" => "Area Name",			"required" => TRUE,	"data_type" => "varchar(255)",	"slug" => TRUE),
				"oarea_classes" => 			array( "label" => "Area CSS Classes",	"required" => FALSE,"data_type" => "text" ),
				"opage_id" => 				array( "label" => "Page",				"required" => FALSE,"data_type" => "integer" ),
				"oarea_locked" => 			array( "label" => "Area Locked?",		"required" => FALSE,"data_type" => "boolean" )
			);
			
			parent::__construct();
			
			$this->permissions = array(
				
			);
			
		}
		
		/***********************************************************************
		
			ADD Function
			
		***********************************************************************/
		
		public function add($params=array()){
			
			if( !isSet($params["oarea_name"]) ){ $this->throwError("oarea_name is required."); }
			if( !isSet($params["opage_id"]) ){ $this->throwError("opage_id is required."); }
			
			if( !$this->isError() ){
				
				$this->get(array("oarea_name"=>$params["oarea_name"],"opage_id"=>$params["opage_id"]));
				
				if( count($this->data) === 0 ){	parent::add($params); }
				
			}
			
		}
		
		/***********************************************************************
		
			OUT Function
			
		***********************************************************************/
		
		public function out($params=array()){
			
			$this->add($params);
			
			$this->html = '';
			if(!$this->isError()){
				
				$this->html .= '<div class="oarea ' . $this->data[0]->oarea_classes . '" id="oarea-'.$this->data[0]->oarea_id.'" data-oarea-id="'.$this->data[0]->oarea_id.'">';
				$oparts = $this->route('/cms/OParts/out/?where=(oarea_id='.$this->data[0]->oarea_id.')');
				$this->html .= $oparts->html;
				$this->html .= '</div>';
				
				
			}
			
			return $this;
			
		}
		
	}
	
	?>
	