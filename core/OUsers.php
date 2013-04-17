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
		
		OUsers:	User/Permission Manager
		
	********************************************************************************************************************/
	
	Class OUsers extends ODBO{
	   
		public function __construct(){
		   
			$this->table = "ousers";
			
			$this->table_definition = array(
				"ouser_id" => 				array("primary_key" => TRUE ),
				"ouser_first_name" => 		array("data_type"=>"varchar(128)",		"required"=>FALSE,	"label"=>"First Name",		"error_message"=>"Please enter the user's first name"),
				"ouser_last_name" => 		array("data_type"=>"varchar(128",		"required"=>FALSE,	"label"=>"Last Name",		"error_message"=>"Please enter the user's last name"),
				"ouser_email" => 			array("data_type"=>"varchar(128)",		"required"=>TRUE,	"label"=>"Email Address",	"error_message"=>"Please enter the user's email address"),
				"ouser_permission_level" =>	array("data_type"=>"integer",			"required"=>FALSE,	"label"=>"Permission Level","error_message"=>"Please specify the user's permission level"),
				"ouser_status" =>			array("data_type"=>"varchar(20)",		"required"=>TRUE,	"label"=>"Status",			"error_message"=>"Please specify the user's status"),
				"ouser_password" =>			array("data_type"=>"password",			"required"=>TRUE,	"label"=>"Password",		"error_message"=>"Please specify the user's password"),
				"ouser_failed_attempts" =>	array("data_type"=>"integer",			"required"=>FALSE,	"label"=>"Failed Logins",	"error_message"=>"Your account has been locked.")
			);
			
		}
		
		/********************************************************************************************************************
		
			Login - creates the ouser session variable
			
		********************************************************************************************************************/
		
		public function login($params){
			
			// Validate the required parameters
			
			if( !isSet( $params["ouser_email"] ) ){ $this->throwError("Email is required",500,"ouser_email"); }
			if( !isSet( $params["ouser_password"] ) ){ $this->throwError("Password is required",500,"ouser_password"); }
			
			// if no error attempt to log the user in
			
			if( !$this->isError() ){
			
				// get user based on credentials
				$this->get(array("ouser_email"=>$params["ouser_email"], "ouser_password" => $params["ouser_password"]));
				
				// if the user exists log them in but only if they haven't exceed the max number of failed attempts (set in settings)
				if( count($this->data) === 1 && $this->data[0]->ouser_failed_attempts < __MAX_FAILED_LOGIN_ATTEMPTS__ && $this->data[0]->ouser_status != "disabled"){
					$_SESSION["ouser"] = $this->data[0];
					$this->update( array("ouser_failed_attempts"=>0) );
					
				// if the user has exceeded the allowable login attempts
				} else if( $this->data[0]->ouser_failed_attempts > __MAX_FAILED_LOGIN_ATTEMPTS__ ){	
					$this->throwError('This account has been locked.');
					
				// if the users has been disabled
				} else if( $this->data[0]->ouser_status === "disabled" ){
					$this->throwError('This account has been disabled.');
					
				// if the user is not found then increment failed attempts and throw error
				} else {
					$this->get(array("ouser_email"=>$params["ouser_email"]));
					if( count($this->data) === 1 ){ $this->update( array("ouser_failed_attempts"=>($this->data[0]->ouser_failed_attempts+1)) ); }
					$this->throwError('Invalid login, make sure you have entered a valid email and password.');
				}
			}
			
		}
		
		/********************************************************************************************************************
		
			Logout - destroys the ouser session variable
			
		********************************************************************************************************************/
		
		public function logout($params){ unset($_SESSION["ouser"]);	}
		
	}
	
	
