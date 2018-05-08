<?php

    require_once("settings.php");

    error_reporting(E_ALL);                        // Set error reporting to display all errors and types
    ini_set('display_errors', true);                 // PHP configuration set display errors to true

    session_set_cookie_params(0);
    session_start();                        // Starts a session in PHP

    require_once __OBRAY_PATH_TO_CORE__ . 'ORouter.php';        // include ORouter
    $router = new ORouter();                                       // instatiate ORouter
    $router->addEncoder("application/json", new \obray\encoders\oJSONEncoder());
    $router->useContainer(new \obray\oDIContainer('dependencies\config.php'));
    $router->route($_SERVER["REQUEST_URI"]);                       // call ORouter's "route" function

?>
