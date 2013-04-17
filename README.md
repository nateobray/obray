# Obray

Obray is lightweight PHP object oriented MVC framework designed to help you write less code and do more quickly.

## Installation

### Setup Apache

To install Obray prototype demo application on a typical Apache configuration create a site and use this example to create your configuration file:

	<VirtualHost *:80>
        ServerAdmin yoursupportemail@example.com
        ServerName yourservername.com
        DocumentRoot /yourpath/obray/prototype
        
        <Directory /yourpath/obray/prototype >
                
                Options FollowSymLinks MultiViews
                AllowOverride None

                DirectoryIndex obray.php index.php

                <IfModule mod_rewrite.c>
                        RewriteEngine On
                        RewriteBase /
                        RewriteCond %{REQUEST_FILENAME} !-f
                        RewriteCond %{REQUEST_FILENAME} !-d
                        RewriteRule ^.+$ obray.php [QSA,L]
                </IfModule>

        </Directory>

    </VirtualHost>

Restart Apache.

### Configuration

Next you'll want to modify your settings file to accomodate your server settings.  Here's breif explaination of each:

#### Basic Settings

	define('__APP__','prototype');							// The name of your application
	define('__SELF__', dirname(__FILE__).'/');              // This should contain the path to your application
	define('__PATH_TO_CORE__','/yourpath/obray/core/');		// The path to obray's core files - you'll want to change this to the location you put it
	define('__DebugMode__',TRUE);							// Enable or Disable debug mode.  In debug mode things like ?refresh will write tables and rebuild resources
	
#### Route Settings	

This is where you are going to define valid routes in your application.  A route is a shortcut to a path that contains the classes you would like to make available to your application.  In general you should always disable system routes and extend the classes you need.  This will allow you to add or remove functionality from the core that your application needs.  NOTE: Your shortcut cannot be the same as an actual directory name in your application, it relies on the path to not exist to be redirected through ORouter (see apache config above).

	define('__ROUTES__',serialize( array( 
	
		// Custom Routes
		"lib" => __SELF__ . "/lib/"
		
		// System Routes - generally only uncomment these if you are going to debug obray core files
		// "cmd" => __SELF__,
		// "core" => __PATH_TO_CORE__
	) ));

#### Database settings	

Place your basic database settings here

	define('__DBHost__','localhost');						// database server host
	define('__DBPort__','3306');							// database server port
	define('__DBUserName__','yourusername');				// database username
	define('__DBPassword__','yourpassword');				// database password
	define('__DB__','prototype');							// database name
	define('__DBEngine__','MyISAM');						// database engine
	define('__DBCharSet__','utf8');							// database characterset (default: utf8)
	
Below are the definitions of the basic datatypes available when creating a table definition in and ODBO class.  You can add more below, remove ones you don't need, or make changes you do need.

	define ("__DATATYPES__", serialize (array (
	//	data_type	    SQL TO SCRIPT TABLE										My SQL Datatypes for verification	Regex to validate values
	    "varchar"   =>  array("sql"=>" VARCHAR(size) COLLATE utf8_general_ci ",	"my_sql_type"=>"varchar(size)",		"validation_regex"=>""),
	    "text"      =>  array("sql"=>" TEXT COLLATE utf8_general_ci ",			"my_sql_type"=>"text",				"validation_regex"=>""),
	    "integer"   =>  array("sql"=>" int ",									"my_sql_type"=>"int(11)",			"validation_regex"=>"/^([0-9])*$/"),
	    "float"     =>  array("sql"=>" float ",									"my_sql_type"=>"float",				"validation_regex"=>"/[0-9\.]*/"),
	    "boolean"   =>  array("sql"=>" boolean ",								"my_sql_type"=>"boolean",			"validation_regex"=>""),
	    "datetime"  =>  array("sql"=>" datetime ",								"my_sql_type"=>"datetime",			"validation_regex"=>""),
	    "password"  =>  array("sql"=>" varchar(255) ",							"my_sql_type"=>"varchar(255)",		"validation_regex"=>"")
	)));

## Introduction

Obray allows you to map PHP objects directly to URIs even from within your application!  To do this every object in Obray extends the OObject class, for example:

	
	Class MyClass1 extends OObject{
		
		public permissions = array(
			"firstFunction" => "any"
		);
	
		public function firstFunction($params){
			
			$this->result = $params["a"] + $params["b"];
			
		}
		
	}

	Class MyClass2 extends OObject{
	
		public function secondFunction(){
		
			$params = array("a"=>1,"b"=>1);
			$my_instance_1 = $this->route('/lib/MyClass1/firstFunction/',$params);  // instantiate instance of MyClass1 and call firstFunction and return the object
			$this->result_1 = $my_instance_1->result;
			
			$params = array("a"=>1,"b"=>2);
			$my_instance_2 = $this->route('/lib/MyClass1/');						// just initiate an instance of MyClass1
			$my_instance_2->route('/firstFunction/',$params);						// call the firstFunction function through route
			$my_instance_2->firstFunction($params);									// call the firstFunction function direction from the object
			
			$this->result_2 = $my_instance_2->result;
			
		}
	
	}


From within an OObject class you can access any path defined as a __ROUTES__ in your settings file.  In this case the "lib" path and any of it's public methods.

## ORouter

ORouter's job is to handle an HTTP request by converting into a routable path and returning the object in an output format such JSON with appropriate status codes and HTTP headers.  For example if you put this in your browser address bar:


	http://www.myobrayapplication.com/lib/MyClass/firstFunction/?a=1&b=2


you will get the response (JSON is the default output format from ORouter):


	{
		"object":"MyClass",
		"result_1":2
		"result_2":3
	}


External HTTP requests that are handled by ORouter are restricted not only by public/private declarations, but also by the permissions array. Unless you explicitely define a functions permissions in the permissions array a request through ORouter will be blocked with 404 Not Found status code.  If an attempt is made to a URI where the permissions are not sufficient you will recieve a 403 Forbidden error.  This way unless the resource is explicity defined with permissions an object can be hidden from ORouter and completely inaccessable except from within an OObject in PHP code.

## ODBO - database access

ODBO (Obray Database Object) is a database abstraction layer that will allow you to create basic database interactions quickly.  This works by defining a tabe_definition which can be used to infur DB interactions SELECT, INSERT, UPDATE, DELETE through 4 basic functions get, add, update, delete respectively.

This looks like the following:

	Class MyDBClass extends ODBO{
	
		private $table_name = "contacts";
		private $table_definition = array(
			"id" => array( "primary_key"=>TRUE ),
			"first_name" => 	array( "data_type"=>"varchar(100)", "required"=>TRUE ),
			"last_name" => 		array( "data_type"=>"varchar(100)",	"required"=>TRUE ),
			"email_address" => 	array( "data_type"=>"varchar(100)",	"required"=>TRUE ),
			"home_phone" => 	array( "data_type"=>"varchar(100)",	"required"=>FALSE )
		);
	
	}

Once you've defined you table_definition you can now call the available public methods:

	$obj = $this->route('/lib/MyDBClass/add/?first_name=John&last_name=Smith&email=johnsmith@example.com');	// add John to the database
	$obj->update(array( "first_name"=>"Johnny" ));															// update his name to Johnny
	$obj->delete();																							// delete Johnny
	
You can also overwrite methods to enhance the default functionality while still maintaining everything existing:

	Class MyDBClass extends ODBO{
	
		private $table_name = "contacts";
		private $table_definition = array(
			"id" => array( "primary_key"=>TRUE ),
			"first_name" => 	array( "data_type"=>"varchar(100)", "required"=>TRUE ),
			"last_name" => 		array( "data_type"=>"varchar(100)",	"required"=>TRUE ),
			"email_address" => 	array( "data_type"=>"varchar(100)",	"required"=>TRUE ),
			"home_phone" => 	array( "data_type"=>"varchar(100)",	"required"=>FALSE )
		);
		
		public function add($params){
			
			// do some pre-database processing here
			
			parent::add($params);
			
			// do some post-database processing here
			
		}
	
	}
	
When you query existing data using get the data gets put into $this->data as an array:

	$this->get();	// gets all records in the table and puts them in $this->data[]
	print_r($this->data);
	
You can also use OObjects extended query string syntax to extract more precise queries:

	$this->route('/get/?first_name=John|Johnny');	// get all records with the first_name 'John' OR 'Johnny'
	print_r($this->data);
	
#OUsers

To help manage permissions a user management class has been built into the core.  This helps restrict access to certain classes, provides an authentication method, and improves overall security of the framework.

The ousers table definition looks like the following:

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

Most of this is self explanitory however there are a few things worth noting described by the following sections.

In general you should not use this class, but create a local version inside your application to add the features you need for your class.  For example, in the prototype application the settings file has a "cmd" route that's set to the lib folder.  A class called AUsers that extends OUsers is placed there. Your extended class should define all the permissions you'd like to have for your class and core objects generally will not have a permissions array assigned (this restricts their access completely except a direct call in PHP code).

###Failed Attempts

This records the number of failed login attempts.  If the number of failed login attempts exceeds the __MAX_FAILED_LOGIN_ATTEMPTS__ variable in the settings file then the login will fail with an error message of "Your account has been locked.".

	define('__MAX_FAILED_LOGIN_ATTEMPTS__',10);				// The maximium allowed failed login attempts before an account is locked

This number is set to 0 every time a successful login attempt is made.

###User Statuses

By the base OUsers class there are only two supported statuses "active" and "disabled".  When the status is set to active the account will function normally, but if it's set to diabled all login attempts will fail.

###Permission Levels

Permission levels are used to determine if the user has enough permission to access a route.  The permission level value is an integer and the lower the value the more permissions will be granted.  For instance if you specify the permissions for a particular route as the integer 2 any user that has permission level less than or equal to 2 will be granted access.  Any permission level higher than 2 will be restricted.

#Permissions

Every OObject should define a permission array that will define the restrictions placed on the accessing the class from a URL.  Usually this is defined in the constructor like in the AUsers class in the prototype demo application:

	$this->permissions = array(
		"object"=>"user",
		"add"=>"any",
		"login"=>"any",
		"get"=>"user",
		"logout"=>"any"
	);

Here are the possible permissions:

1. any: this resource is fully accessible from within PHP code or from an HTTP request handled by ORouter
2. user: this resource is restricted to information specific to the current user.  For most queries this means restricting content that is not associated with the user through their ouser_id (this restriction works both from an HTTP request or from PHP code)
3. any integer: the is explained under the Permissions section under OUsers
4. undefined: if the key for the object or the function is undefined then the resources is completely unaccessible from an HTTP request with a status code of 404

