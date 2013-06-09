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

	Class OPages extends OForm {

		public $html;

		public function __construct(){

			$this->table = "opages";
			$this->table_definition = array(
				"opage_id" => 				array( "primary_key" => TRUE ),
				"opage_title" => 			array( "label" => "Page Title",			"required" => TRUE,	"data_type" => "varchar(255)",	"slug" => TRUE,			"type"=>"text"),
				"opage_description" => 		array( "label" => "Page Description",	"required" => FALSE,"data_type" => "text",									"type"=>"textarea",	"rows"=>5 ),
				"opage_keywords" => 		array( "label" => "Page Keywords",		"required" => FALSE,"data_type" => "text",									"type"=>"textarea",	"rows"=>5 ),
				"opage_head" => 			array( "label" => "Page Head",			"required" => FALSE,"data_type" => "text",									"type"=>"textarea",	"rows"=>5,	"help"=>"Add additional markup to the head section of the HTML document." ),
				"opage_published" => 		array( "label" => "Page Published",		"required" => FALSE,"data_type" => "boolean",		"default"=>FALSE,		"type"=>"checkbox"),
				"opage_permission_level" => array( "label" => "Permission level",	"required" => TRUE,	"data_type" => "integer",		"default"=>1,			"type"=>"radio",	"labels"=>["Super Admin","Administrator","User","Anyone"], "values"=>[0,1,2,100]),
				"opage_secured" => 			array( "label" => "Apply SSL",			"required" => FALSE,"data_type" => "boolean",		"default"=>FALSE,		"type"=>"checkbox"),
				"opage_template" => 		array( "label" => "Page Template",		"required" => TRUE,	"data_type" => "varchar(75)",	"default"=>"default",	"type"=>"select",	"labels"=>["Default"],	"values"=>["default"]),
				"opage_layout" => 			array( "label" => "Page Layout",		"required" => TRUE,	"data_type" => "varchar(75)",	"default"=>"default",	"type"=>"select",	"labels"=>["Default"],	"values"=>["default"]),
				"opage_deletable" => 		array( "label" => "Page Deleteable",	"required" => FALSE,"data_type" => "boolean",								"type"=>"checkbox")
			);

			parent::__construct();

			//$this->general_error = "";

			$this->permissions = array(
				"object" => "any",
				"add" => 1,
				"update" => 1,
				"form" => 1
			);

			$this->base_url = "/cms/OPages/";

		}

		public function missing($path,$params=array(),$direct=TRUE){
			$parsed = $this->parsePath($path);
			$path_array = $parsed["path_array"];
			$_SESSION["path_array"] = $path_array;

			if(empty($path_array)){
				$this->get(array("parent_id"=>0));

				if( count($this->data) == 0 ){
					$this->throwError("Page Not Found",404);
				} else {
					$this->data = $this->data[0];
				}

			} else {

				$current = $this->route('/cms/OPages/get/?where=(parent_id=0)')->data[0];
				$parent_id = $current->opage_id;
				forEach( $path_array as $key => $path ){
					$page = $this->route('/cms/OPages/get/?where=(slug='.urlencode($path).'&parent_id='.$parent_id.')')->data;

					if(!empty($page)){
						$page[0]->path = array_shift($_SESSION["path_array"]);
						$page[0]->parent = $current;
						$current = $page[0];
						$parent_id = $page[0]->opage_id;
					} else { break; }
				}

				$this->data = $current;

			}

			// get content
			ob_start();
			include __SELF__ . 'layouts/'.$this->data->opage_layout.'/layout.php';
			$this->layout = ob_get_clean();

			if( count($_SESSION["path_array"]) > 0 ){ $this->throwError("Page Not Found",404); }

			if( $this->isError() ){

				// get error content
				$this->data = new stdClass();
				$this->data->opage_title = "404 Page Not Found";
				$this->data->opage_template = "default";
				$this->data->opage_layout = "default";

				$this->layout = "";
				if( count($_SESSION["path_array"]) == 1 ){
					$this->layout .= '<div class="alert alert-block">';
					$this->layout .= '<button type="button" class="close" data-dismiss="alert">&times;</button>';
					$this->layout .= '<h4>Page Doesn\'t Exist!</h4><p>This page hasn\'t been created yet, but you can create it.  Click on the &quot;Create Page&quot; button below to generate this page and start adding content.</p><br/>';
					$this->layout .= '<a id="btn-create-page" class="btn btn-warning">Create Page</a>';
					$this->layout .= '</div>';
					$this->layout .= '<script>$("#btn-create-page").on("click",function(){ $("body").OPage({"opage_title":"'.$_SESSION["path_array"][0].'"}); })</script>';
				}

				ob_start();
				include __SELF__ . 'templates/'.$this->data->opage_template.'/error.php';
				$this->layout .= ob_get_clean();

			}

			// get template
			ob_start();
			include __SELF__ . 'templates/'.$this->data->opage_template.'/template.php';
			$this->html = ob_get_clean();

			//output
			$this->setContentType("text/html");

		}

		public function outRrc($type){

			if( is_dir(__SELF__.'assets/'.$type.'/'.$this->data->opage_template.'/') ){
				$r = $this->sdir(__SELF__.'assets/'.$type.'/'.$this->data->opage_template.'/','*.'.$type);
			} else {
				mkdir(__SELF__.'assets/'.$type.'/'.$this->data->opage_template.'/');
				$r = $this->sdir(__SELF__.'assets/'.$type.'/'.$this->data->opage_template.'/','*.'.$type);
			}

			if( empty($r) || isSet($_REQUEST["refresh"]) ){
				$this->generateResource($type);



				$r = $this->sdir(__SELF__.'assets/'.$type.'/'.$this->data->opage_template.'/','*.'.$type);
			}

			switch($type){
				case "js": echo '<script src="/assets/'.$type.'/'.$this->data->opage_template.'/'.$r[count($r)-1].'"></script>'."\n"; break;
				case "css": echo '<link rel="stylesheet" type="text/css" href="/assets/'.$type.'/'.$this->data->opage_template.'/'.$r[count($r)-1].'">'."\n"; break;
			}


		}

		private function generateResource($type){

			$resource = '';

			// get cms resources

			$dir = dirname(__FILE__) .'/'.$type.'/';
			$r = $this->sdir($dir,'*.'.$type);

			forEach($r as $file){ $resource .= file_get_contents($dir.$file); }

			// get template resources

			$dir = __SELF__ .'/templates/'.$this->data->opage_template.'/'.$type.'/';
			if( is_dir($dir) ){
				$r = $this->sdir($dir,'*.'.$type);
				forEach($r as $file){ $resource .= file_get_contents($dir.$file); }
			}

			// get layout resources
			$dir = __SELF__ .'/layouts/'.$this->data->opage_layout.'/'.$type.'/';
			if( is_dir($dir) ){
				$r = $this->sdir($dir,'*.'.$type);
				forEach($r as $file){ $resource .= file_get_contents($dir.$file); }
			}

			// get layout resources
			$dir = __SELF__ .'widgets/';
			if( is_dir($dir) ){
				$w = $this->sdir($dir);
				forEach($w as $i => $widget){
					if( is_dir(__SELF__.'widgets/'.$widget.'/'.$type.'/') ){
						$r = $this->sdir(__SELF__.'widgets/'.$widget.'/'.$type.'/','*.'.$type);
						forEach($r as $file){ $resource .= file_get_contents(__SELF__.'widgets/'.$widget.'/'.$type.'/'.$file); }
					}
				}
			}

			if($type === "css"){
				$oparts = $this->route('/cms/OParts/get/')->data;
				forEach($oparts as $opart){ $resource .= "\n" . $opart->opart_css; }
			}

			file_put_contents(__SELF__.'assets/'.$type.'/'.$this->data->opage_template.'/resource-'.time().'.'.$type,$resource);

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