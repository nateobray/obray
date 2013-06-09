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

	Class OWidgets extends OObject {

		public function __construct(){

			$this->permissions = array(
				"object" => "any",
				"out" => "any",
				"get" => "any"
			);

		}

		public function get(){
			$this->data = $this->sdir("widgets");
			forEach($this->data as $i => $widget){
				$this->data[$i] = new stdClass();
				if( is_file(__SELF__.'widgets/'.$widget.'/config.js') ){
					$this->data[$i] = json_decode(file_get_contents(__SELF__.'widgets/'.$widget.'/config.js'));
					$this->data[$i]->config = TRUE;
					$this->data[$i]->folder = $widget;
				} else {
					$this->data[$i]->config = FALSE;
					$this->data[$i]->folder = $widget;
				}
			}
		}

		public function out($params){

			$this->get($params);
			ob_start();
			include "views/vwidgets.php";
			$this->html = ob_get_clean();


			$this->setContentType("text/html");

		}

		private function sdir( $path='.', $mask='*', $nocache=1 ){
    		$sdir = array(); static $dir = array(); // cache result in memory
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

	}
?>