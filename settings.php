
<?php

	/*****************************************************************************

	The MIT License (MIT)
	
	Copyright (c) 2013 Nathan A Obray <nathanobray@gmail.com>
	
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


/******************************************************
    GENERAL SETTINGS
******************************************************/

define('__APP__','obray');
define('__SELF__', dirname(__FILE__).'/');              // The should contain the path to your application
define('__PATH_TO_CORE__',dirname(__FILE__).'/core/');	// The path to obray's core files
define('__DebugMode__',TRUE);							// Enable Debug Mode
define(__OTOKEN__,''); // OBRAY TOKEN
//define('__REMOTE_HOSTS__',serialize(array(0=>''));


/******************************************************
    DEFINE AVAILABLE ROUTES
******************************************************/

define('__ROUTES__',serialize( array( 
	
	// Custom Routes - put your custom routes here
	"d" => __SELF__ . "demo/",
	// System Routes
	"c" => __PATH_TO_CORE__
) ));

/******************************************************
    DATABASE SETTINGS
******************************************************/

define('__DBHost__','localhost');						// database server host
define('__DBPort__','3306');							// database server port
define('__DBUserName__','yourdbusername');				// database username
define('__DBPassword__','yourdbpassword');				// database password
define('__DB__','yourdbname');							// database name
define('__DBEngine__','MyISAM');						// database engine
define('__DBCharSet__','utf8');							// database characterset (default: utf8)

define ("__DATATYPES__", serialize (array (
//	table_def data_type	  SQL TO SCRIPT TABLE								My SQL Datatypes for verification	Regex to validate values
    "varchar"   =>  array("sql"=>" VARCHAR(size) COLLATE utf8_general_ci ",	"my_sql_type"=>"varchar(size)",		"validation_regex"=>""),
    "text"      =>  array("sql"=>" TEXT COLLATE utf8_general_ci ",			"my_sql_type"=>"text",				"validation_regex"=>""),
    "integer"   =>  array("sql"=>" int ",									"my_sql_type"=>"int(11)",			"validation_regex"=>"/^([0-9])*$/"),
    "float"     =>  array("sql"=>" float ",									"my_sql_type"=>"float",				"validation_regex"=>"/[0-9\.]*/"),
    "boolean"   =>  array("sql"=>" boolean ",								"my_sql_type"=>"boolean",			"validation_regex"=>""),
    "datetime"  =>  array("sql"=>" datetime ",								"my_sql_type"=>"datetime",			"validation_regex"=>""),
    "password"  =>  array("sql"=>" varchar(255) ",							"my_sql_type"=>"varchar(255)",		"validation_regex"=>"")
)));

/******************************************************
    User Settings
******************************************************/

define('__MAX_FAILED_LOGIN_ATTEMPTS__',10);				// The maximium allowed failed login attempts before an account is locked



