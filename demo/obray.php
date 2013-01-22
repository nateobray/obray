<?php

	// enable debugging
	error_reporting(E_ALL);									   // Set error reporting to display all errors and types
	ini_set('display_errors', true); 						   // PHP configuration set display errors to true
	
	session_set_cookie_params(0);
	session_start();												// Starts a session in PHP
	
	define('_SELF_', dirname(__FILE__).'/');                   // The should contain the path to your application
	require_once '../core/ORouter.php';                        // include ORouter
	$router = new ORouter();                                   // instatiate ORouter
	$router->route($_SERVER["REQUEST_URI"]);                   // call ORouter's "route" function
			
?>