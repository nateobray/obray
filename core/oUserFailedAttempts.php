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

	if (!class_exists( 'OObject' )) { die(); }

	/********************************************************************************************************************

		OUsers:	User/Permission Manager

	********************************************************************************************************************/
	
	Class oUserFailedAttempts extends ODBO{

		public function __construct(){
			
			parent::__construct();

			$this->table = 'oUserFailedAttempts';
			$this->table_definition = array(
                'ouser_attempt_id' => 			array('primary_key' => TRUE ),
                'ouser_email' => 		        array('data_type'=>'varchar(50)'),
                'ouser_password' => 		    array('data_type'=>'varchar(50)'),
				'ouser_attempt_ip' => 		    array('data_type'=>'varchar(39)'),
				'ouser_attempt_agent' => 		array('data_type'=>'varchar(128)')
			);
			
			$this->permissions = array(
				'object' => 1,
				'add' => 1,
				'get' => 1,
				'update' => 1,
				'count' => 1
			);

		}

	}
?>