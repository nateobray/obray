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
		
		WDictionary:	This is our controller object for our Dictionary application.
		
	********************************************************************************************************************/
	
	Class OUsers extends ODBO{
	   
		private $permissions = array(
			"object"=>"any",
			"add"=>"any",
			"login"=>"any",
			"get"=>"any"
		);
	   
		public function __construct(){
		   
			$this->table = "ousers";
			
			$this->table_definition = array(
				"ouser_id" => 				array("primary_key" => TRUE ),
				"ouser_first_name" => 		array("data_type"=>"varchar(128)",		"required"=>FALSE,	"label"=>"First Name",		"error_message"=>"Please enter the user's first name"),
				"ouser_last_name" => 		array("data_type"=>"varchar(128",		"required"=>FALSE,	"label"=>"Last Name",		"error_message"=>"Please enter the user's last name"),
				"ouser_email" => 			array("data_type"=>"varchar(128)",		"required"=>TRUE,	"label"=>"Email Address",	"error_message"=>"Please enter the user's email address"),
				"ouser_permission_level" =>	array("data_type"=>"integer",			"required"=>FALSE,	"label"=>"Permission Level","error_message"=>"Please specify the user's permission level"),
				"ouser_status" =>			array("data_type"=>"varchar(20)",		"required"=>TRUE,	"label"=>"Status",			"error_message"=>"Please specify the user's status"),
				"ouser_password" =>			array("data_type"=>"password",			"required"=>TRUE,	"label"=>"Password",		"error_message"=>"Please specify the user's password")
			);
			
			parent::__construct();
		   
		}
		
		public function login($params){
		
			if( !isSet( $params["ouser_email"] ) ){ $this->throwError("Email is required",500,"ouser_email"); }
			if( !isSet( $params["ouser_password"] ) ){ $this->throwError("Password is required",500,"ouser_password"); }
		
			if( !$this->isError() ){
				$this->data = $this->route('/core/OUsers/get/?ouser_email='.$params["ouser_email"].'&ouser_password='.$params["ouser_password"])->data;
				if( count($this->data) === 1 ){
					$_SESSION["ouser"] = $this->data[0];
				} else {
					$this->throwError('Invalid login, make sure you have entered a valid email and password.');
				}
			}
		
			
		}
		
		public function logout($params){
			
		}
		
		public function hasPermission($object){ if( isSet($this->permissions[$object]) && $this->permissions[$object] === 'any'){ return TRUE; } else { return FALSE; }	}
		
	}
	
	
