<?php

	/*****************************************************************************

	The MIT License (MIT)
	
	Copyright (c) 2014 Nathan A Obray
	
	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the 'Software'), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
	
	*****************************************************************************/

	if (!class_exists( 'oObject' )) { die(); }

	/********************************************************************************************************************

		OUsers:	User/Permission Manager

	********************************************************************************************************************/
	
	Class oUserPermissions extends obray\oDBO{

		public function __construct(){
			
			parent::__construct();

			$this->table = 'oUserPermissions';
			$this->table_definition = array(
				'ouser_permission_id' => 	array( 'primary_key' => TRUE ),
				'opermission_id' => 		array( 'data_type'=>'integer',		'required'=>TRUE    ),
				'ouser_id' => 		        array( 'data_type'=>'integer',		'required'=>TRUE    )
			);
			
			$this->permissions = array(
				'object' => 'any',
				'getArray' => 'any'
			);

        }

        public function getArray(){
            
            $this->get(array(
                "ouser_id" => $_SESSION["ouser"]->ouser_id
            ));

            if( empty($this->data) ){ return array(); }

            $data = array();
            forEach( $this->data as $dati ){
                $data[] = $dati->opermission_id;
            }

            $oPermissions = $this->route("/obray/oPermissions/get/",array(
                "opermission_id" => implode("|",$data)
            ))->data;

            if( empty($oPermissions) ){ return array(); }

            $data = array();
            forEach( $oPermissions as $oPermission ){
                $data[] = $oPermission->opermission_code;
            }

            $this->data = $data;
            return $data;

        }

	}
?>