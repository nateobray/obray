
<?php

/******************************************************
    GENERAL SETTINGS
******************************************************/

define('__APP__','Prototype');
define('__SELF__', dirname(__FILE__).'/');              // The should contain the path to your application
define('__PATH_TO_CORE__','/www/obray/core/');			// The path to obray's core files
define('__DebugMode__',TRUE);							// Enable Debug Mode

/******************************************************
    DEFINE AVAILABLE ROUTES
******************************************************/

define('__ROUTES__',serialize( array( 
	
	// Custom Routes
	"cmd" => __SELF__."lib/",
	// System Routes
	// "cmd" => __SELF__,
	"core" => __PATH_TO_CORE__
) ));

/******************************************************
    DATABASE SETTINGS
******************************************************/

define('__DBHost__','localhost');						// database server host
define('__DBPort__','3306');							// database server port
define('__DBUserName__','root');						// database username
define('__DBPassword__','Mal0uf..2004');				// database password
define('__DB__','prototype');							// database name
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
