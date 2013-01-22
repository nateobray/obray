<?php

	
	// enable debugging
	error_reporting(E_ALL);									   // Set error reporting to display all errors and types
	ini_set('display_errors', true); 						   // PHP configuration set display errors to true
	
	define('_SELF_', '/www/v1.2/obray/demo/');                                // The should contain the path to your application
	define('_CORE_', '../core/');
	
	require_once _CORE_.'ORouter.php';
	
	$router = new ORouter();
	$router->route($_SERVER["REQUEST_URI"]);
	
			
?>