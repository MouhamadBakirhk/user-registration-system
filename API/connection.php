<?php
$servername = "localhost\\SQLEXPRESS";

$connectionOptions = [
    "Database" => "mydb",
    "Uid" => "mouhamad",      
    "PWD" => "12345",         
    "CharacterSet" => "UTF-8" 
];

$conn = sqlsrv_connect($servername, $connectionOptions);

if ($conn === false) {
    die(json_encode(["success" => false, "message" => "Connection failed", "error" => sqlsrv_errors()]));
}


?>
