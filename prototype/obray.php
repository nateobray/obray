<?php
	
	
	
	// enable debugging
	error_reporting(E_ALL);									   // Set error reporting to display all errors and types
	ini_set('display_errors', true); 						   // PHP configuration set display errors to true
	
	session_set_cookie_params(0);
	session_start();										   // Starts a session in PHP
	
	
	require_once '/www/obray/core/ORouter.php';                // include ORouter
	//include "/www/obray/lib/OPages/OPages.php";
	$router = new ORouter();                                   // instatiate ORouter
	
	//$router->setCustomRouter(new OPages);
	$router->route($_SERVER["REQUEST_URI"]);                   // call ORouter's "route" function
			
?>