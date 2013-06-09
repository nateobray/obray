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

	Class OForm extends ODBO {

		/***********************************************************************

			OUT Function

		***********************************************************************/

		public function form($params=array()){

			$this->html = "";
			$this->setContentType("text/html");

			if( isSet($params["base_url"]) ){ $this->base_url = $params["base_url"]; }
			if( isSet($params["definition"]) ){ $def = $params["definition"]; }
			if( isSet($this->base_url) ){ $def = $this->route($this->base_url.'getTableDefinition/')->data; }
			if( isSet($params["primary_key_value"]) && $params["primary_key_value"] > 0 ){ $primary_key_value = $params["primary_key_value"]; $fn = "update/"; } else { $primary_key_value = 0; $fn = "add/"; }
			if( isSet($params["id"]) ){ $id = $params["id"]; } else { $id = ""; }

			if( !isSet($def) ){ $this->throwError("You must provide a definition to build this form.",501); }

			if( !$this->isError() ){

				if( $primary_key_value > 0 ){
					forEach($def as $name => $details){ if( isSet($details["primary_key"]) && $details["primary_key"] == TRUE ){ $primary_key_column = $name; }	}
					$data = $this->route($this->base_url.'get/?'.$primary_key_column.'='.$primary_key_value)->data;
					if( count($data) === 0 ){ unset($data); } else { $data = $data[0]; }
				}

				forEach( $params as $key => $param ){ if( array_key_exists($key,$def) ){ $def[$key]["value"] = $param; } }

				$classes = '';
				if( isSet($params["layout"]) ){ $classes .= ' ' . $params["layout"]; $this->layout = $params["layout"]; } else { $this->layout= ""; }

				$this->html .= '<form id="oform-'.$id.'" class="oform '.$classes.'">';

				forEach($def as $name => $details){

					if( $primary_key_value > 0 && $name == $primary_key_column ){ $details["type"] = "hidden"; $details["label"] = ""; }

					unset($details["data_type"]);
					unset($details["default"]);
					unset($details["slug"]);
					if( isSet($details["required"]) && $details["required"] === FALSE ){ unset($details["required"]); }
					unset($details["required"]);

					if( isSet($details["help"]) ){ $help = '<span class="help-inline">'.$details["help"].'</span>'; unset($details["help"]); } else { $help = ''; }

					if( isSet($details["type"]) ){

						$attributes = $this->getAttributes($details);

						switch($details["type"]){

							case "checkbox":

								$this->html .= isSet($data)?$data->$name:'';
								if( isSet($data) && $data->$name == TRUE ){ $value = 'checked'; } else { $value = ''; }
								$this->controlGroup('<label class="checkbox"><input '.$value.' id="'.str_replace("_", "-", $name).'" name="'.$name.'" '.$attributes.' value="1">'.$details["label"].'</label>'.$help,$details["label"],$name,$details["type"]);
								break;

							case "radio":

								$input_string = '';

								forEach( $details["labels"] as $index => $label ){ if( isSet($data) && $details["values"][$index] == $data->$name ){ $value = 'checked'; } $input_string .= '<label class="radio"><input '.$value.' type="radio" name="'.$name.'" value="'.$details["values"][$index].'" />'.$label.'</label>';	}
								$this->controlGroup($input_string,$details["label"],$name,$details["type"]);
								break;

							case "textarea":

								$value = isSet($data)?$data->$name:'';
								$this->controlGroup('<textarea id="'.str_replace("_", "-", $name).'" name="'.$name.'" '.$attributes.'>'.$value.'</textarea>'.$help,$details["label"],$name,$details["type"]);
								break;

							case "select":

								$options = '<option value="">Select One</option>';
								if( isSet($details["labels"]) && isSet($details["values"]) ){ forEach( $details["labels"] as $index => $label ){ if( isSet($data) && $details["values"][$index] === $data->$name ){ $value = isSet($data)?"selected":''; } $options .= '<option '.$value.' value="'.$details["values"][$index].'">'.$details["labels"][$index].'</options>'; } }
								$this->controlGroup('<select id="'.str_replace("_", "-", $name).'" name="'.$name.'" '.$attributes.'>'.$options.'</select>'.$help,$details["label"],$name,$details["type"]);
								break;

							case "file":

								$value = isSet($data)?' value="'.$data->$name.'"':'';
								$html =  '<div id="'.str_replace("_", "-", $name).'-file-progress" class="progress progress-striped active hide">';
								$html .=	'<div class="bar" style="width: 0%;"></div>';
								$html .= '</div>';
								$html .= '<button id="'.str_replace("_", "-", $name).'-file-btn" class="btn"><i class="icon-upload">&nbsp; </i>Select File</button>';
								$html .= '<span id="'.str_replace("_", "-", $name).'-file-name" class="omedia-file-name-container"></span>';
								$html .= '<input class="file-input" id="'.str_replace("_", "-", $name).'-file" name="'.$name.'_file" '.$attributes.' '.$value.'>'.$help;
								$html .= '<input id="'.str_replace("_", "-", $name).'" name="'.$name.'" type="hidden" '.$value.'/>'.$help;

								$this->controlGroup($html,$details["label"],$name,$details["type"]);
								break;

							case "hidden":

								$value = isSet($data)?' value="'.$data->$name.'"':'';
								$this->html .= '<input id="'.str_replace("_", "-", $name).'" name="'.$name.'" '.$attributes.' '.$value.'>'.$help;
								break;

							default:

								$value = isSet($data)?' value="'.$data->$name.'"':'';
								$this->controlGroup('<input id="'.str_replace("_", "-", $name).'" name="'.$name.'" '.$attributes.' '.$value.'>'.$help,$details["label"],$name,$details["type"]);
								break;

						}

					}

				}

				if( isSet($params["button"]) ){
					if( isSet($params["button_type"]) ){ $button_class = $params["button_type"]; } else { $button_class = ""; }
					$this->controlGroup('<button id="oform-btn" type="Submit" class="btn submit-btn btn-footer '.$button_class.'" data-loading-text="Saving..." >'.$params["button"].'</button>','','','');
				}

				$this->html .= '</form>';

			}

			$this->html .= "<script>$('#oform-".$id."').OForm('".$this->base_url.$fn."/');</script>";


			$this->content_type = "text/html";

		}

		private function getAttributes($details){

			$attributes = '';
			forEach($details as $attr => $detail){ if( $attr != "labels" && $attr != "values" && $attr != "label" ){ $attributes .= ' '.$attr . '="'.$detail.'" '; } }
			return $attributes;

		}

		private function controlGroup($input,$label,$name,$type){

			switch($this->layout){

				case "form-horizontal":

					$this->html .= '<div class="control-group">';
					if( isSet($label) && $type != 'checkbox' ){ $this->html .= '<label class="control-label" for="'.$name.'">'.$label.'</label>'; }
					$this->html .= '<div class="controls">';
					$this->html .= $input;
					$this->html .= '</div>';
					$this->html .= '</div>';
					break;

				case "form-inline":
				case "form-search":
				default:
					if( isSet($label) ){ $this->html .= '<label class="control-label" for="'.$name.'">'.$label.'</label>'; }
					$this->html .= $input;
					break;



			}


		}

	}
?>