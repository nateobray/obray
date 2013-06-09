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

	Class OPages extends ODBO {

		public $table = "opages";
		public $table_definition = array(
			"opage_id" => 				array( "primary_key" => TRUE ),
			"opage_title" => 			array( "label" => "Page Title",			"required" => TRUE,	"data_type" => "varchar(255)"),
			"opage_description" => 		array( "label" => "Page Description",	"required" => FALSE,"data_type" => "text" ),
			"opage_keywords" => 		array( "label" => "Page Keywords",		"required" => FALSE,"data_type" => "text" ),
			"opage_head" => 			array( "label" => "Page Head",			"required" => FALSE,"data_type" => "text" ),
			"opage_published" => 		array( "label" => "Page Published",		"required" => FALSE,"data_type" => "boolean",		"default"=>FALSE),
			"opage_permission_level" => array( "label" => "Permission level",	"required" => FALSE,"data_type" => "integer",		"default"=>1),
			"opage_secured" => 			array( "label" => "Apply SSL",			"required" => FALSE,"data_type" => "boolean",		"default"=>FALSE),
			"opage_template" => 		array( "label" => "Page Template",		"required" => FALSE,"data_type" => "varchar(75)",	"default"=>"default"),
			"opage_layout" => 			array( "label" => "Page Layout",		"required" => FALSE,"data_type" => "varchar(75)",	"default"=>"default"),
			"opage_deletable" => 		array( "label" => "Page Deleteable",	"required" => FALSE,"data_type" => "boolean")
		);

		public function route($path,$params=array()){

			$path_old = $path;

			if( strstr($path,'/cmd/') != FALSE ){ return parent::route($path_old,$params); }


			$parsed = $this->parsePath($path);
			$path_array = $parsed["path_array"];
			$path = $parsed["path"];




			// get root page
			$this->root = $this->route('/cmd/lib/opages/get/?parent_id=0');

			forEach($path_array as $page){

				//$opage = $this->get(array("slug"=>$page,"parent_id"=>$this->root->data[0]->opage_id));
			}




			return $this;

		}

		public function routePage(){

		}

	}
?>