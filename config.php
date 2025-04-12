<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
  
    $host = 'localhost';
    $dbname = 'examonline_db';
    $username = 'root'; 
    $password = ''; 


    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);


    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
 
    die("Database connection failed: " . $e->getMessage());
}
?>