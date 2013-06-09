<?php

function getDatabaseConnection(){

    try {
        $conn = new PDO('mysql:host='.__DBHost__.';dbname='.__DB__, __DBUserName__,__DBPassword__);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        echo 'ERROR: ' . $e->getMessage();
        exit();
    }

    return $conn;

}

function removeSpecialChars($string,$space = '',$amp = ''){

	$string = str_replace(' ',$space,$string);
	$string = str_replace('&',$amp,$string);
	$string = preg_replace("/[^a-zA-Z0-9\-_s]/", "", $string);
	return $string;

}
?>