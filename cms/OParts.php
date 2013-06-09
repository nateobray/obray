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

	Class OParts extends ODBO {

		public function __construct(){

			$this->table = "oparts";
			$this->table_definition = array(
				"opart_id" => 				array( "primary_key" => TRUE ),
				"oarea_id" => 				array( "label" => "Content Area",	"required" => FALSE,	"data_type" => "integer"),
				"opart_type" => 			array( "label" => "Content Type",	"required" => TRUE,		"data_type" => "varchar(25)" ),
				"opart_content" => 			array( "label" => "Content",		"required" => TRUE,		"data_type" => "text" ),
				"opart_classes" =>			array( "label" => "Content Classes","required" => FALSE,	"data_type" => "varchar(255)" ),
				"omedia_id" => 				array( "label" => "Media",			"required" => FALSE,	"data_type" => "integer" ),
				"opart_width" =>			array( "label" => "% Width",		"required" => FALSE,	"data_type" => "integer"),
				"opart_css" =>				array( "label" => "CSS",			"required" => FALSE,	"data_type" => "text"),
				"opart_margins" =>			array( "label" => "Margins",		"required" => FALSE,	"data_type" => "varchar(25)"),
				"opart_padding" =>			array( "label" => "Padding",		"required" => FALSE,	"data_type" => "varchar(25)")
			);

			parent::__construct();

			$this->permissions = array(
				"object" => 1,
				"add" => 1,
				"update" => 1,
				"delete" => 1,
				"out" => 1,
				"get" => "any",
				"toolbar" => 1,
				"getForEditor" => 1
			);

		}

		public function add($params=array()){
			parent::add($params);
			$this->out(array("opart_id"=>$this->data[0]->opart_id));
		}

		public function update($params=array()){
			parent::update($params);
			$this->out($params);
		}

		public function out($params){

			$this->get($params);
			$this->html = '';

			if( !empty($this->data) ){

				forEach($this->data as $i => $opart){

					$this->html .= '<div class="opart '.$opart->opart_classes.'" style="width:'.$opart->opart_width.'%;" id="opart-'.$opart->opart_id.'" data-opart-id="'.$opart->opart_id.'" >';

					switch($opart->opart_type){

						/***********************************************************************
							HTML CONTENT
						***********************************************************************/

						case "html":

							$widgets = array();

							preg_match("([-][-][a-zA-Z0-9-_]*[-][-])",$opart->opart_content,$widgets);

							forEach($widgets as $i => $widget){

								$this->widget = $this->route('/w/'.str_replace("-","",$widget).'/'.str_replace("-","",str_replace('W','O',$widget)).'/out/',array("opart_id"=>$opart->opart_id));
								$opart->opart_content = str_replace($widget,'<div class="widget" data-widget="'.str_replace("-","",$widget).'">'.$this->widget->html.'</div>',$opart->opart_content);

							}

							$this->html .= $opart->opart_content;

							break;

						/***********************************************************************
							OMEDIA CONTENT
						***********************************************************************/

						case "omedia":

							$this->route('/cms/OMedia/out/?omedia_id='.$opart->omedia_id);
							break;

						/***********************************************************************
							WIDGET CONTENT
						***********************************************************************/

						case "widget":

							$this->route('/cms/OWidget/out/?owidget='.$opart->opart_content);
							break;

					}
					$this->html .= '</div>';
				}
			} else {




			}

			return $this;

		}

		public function toolbar(){

			$this->data = $this->route('/cms/OWidgets/get/')->data;

			ob_start();
			include 'views/voeditortoolbar.php';
			$this->html = ob_get_clean();

		}

		public function getForEditor($params){

			$this->data = new stdClass;
			$data = $this->route('/cms/OParts/get/',$params)->data[0];
			$this->data->css = $data->opart_css;
			$this->data->html = $data->opart_content;

			ob_start();
			include 'views/voeditortoolbar.php';
			$this->data->toolbar = ob_get_clean();

		}

		public function marginsAndPadding(){

			$this->data = $this->route('/cms/OWidgets/get/')->data;

			ob_start();
			include 'views/vmarginsandpadding.php';
			$this->html = ob_get_clean();

		}

	}
?>