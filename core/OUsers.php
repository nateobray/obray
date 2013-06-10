<?php

	/*****************************************************************************

	The MIT License (MIT)
	
	Copyright (c) 2013 Nathan A Obray
	
	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
	
	*****************************************************************************/

	if (!class_exists( 'OObject' )) { die(); }

	/********************************************************************************************************************

		OUsers:	User/Permission Manager

	********************************************************************************************************************/

	Class OUsers extends ODBO{

		public function __construct(){

			parent::__construct();

			$this->table = "ousers";
			$this->table_definition = array(
				"ouser_id" => 				array("primary_key" => TRUE ),
				"ouser_first_name" => 		array("data_type"=>"varchar(128)",		"required"=>FALSE,	"label"=>"First Name",		"error_message"=>"Please enter the user's first name"),
				"ouser_last_name" => 		array("data_type"=>"varchar(128",		"required"=>FALSE,	"label"=>"Last Name",		"error_message"=>"Please enter the user's last name"),
				"ouser_email" => 			array("data_type"=>"varchar(128)",		"required"=>TRUE,	"label"=>"Email Address",	"error_message"=>"Please enter the user's email address"),
				"ouser_permission_level" =>	array("data_type"=>"integer",			"required"=>FALSE,	"label"=>"Permission Level","error_message"=>"Please specify the user's permission level"),
				"ouser_status" =>			array("data_type"=>"varchar(20)",		"required"=>TRUE,	"label"=>"Status",			"error_message"=>"Please specify the user's status"),
				"ouser_password" =>			array("data_type"=>"password",			"required"=>TRUE,	"label"=>"Password",		"error_message"=>"Please specify the user's password"),
				"ouser_failed_attempts" =>	array("data_type"=>"integer",			"required"=>FALSE,	"label"=>"Failed Logins"),
				"ouser_last_login" =>		array("data_type"=>"datetime",			"required"=>FALSE,	"label"=>"Last Login")
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
					$this->update( array("ouser_failed_attempts"=>0,"ouser_last_login"=>date("Y-m-d H:i:s")) );
				} else if( empty($this->data) ){
					$this->get(array("ouser_email"=>$params["ouser_email"]));
					if( count($this->data) === 1 ){ $this->update( array("ouser_failed_attempts"=>($this->data[0]->ouser_failed_attempts+1)) ); }
					$this->throwError('Invalid login, make sure you have entered a valid email and password.');

				// if the user has exceeded the allowable login attempts
				} else if( $this->data[0]->ouser_failed_attempts > __MAX_FAILED_LOGIN_ATTEMPTS__ ){
					$this->throwError('This account has been locked.');

				// if the user has been disabled
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

		public function authorize($params=array()){


			if( !isSet( $_SESSION["ouser"] ) ){
				$this->throwError("Forbidden",403);
			} else if( isSet($params["level"]) && $params["level"] < $_SESSION["ouser"]->ouser_permission_level ){
				$this->throwError("Forbidden",403);
			}

		}

	}
?>