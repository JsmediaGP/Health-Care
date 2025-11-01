<?php
/**
 * DB_CONFIG.PHP
 * Database connection setup using PDO (PHP Data Objects)
 */

// --- Database Credentials ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');   
define('DB_PASSWORD', '');       
define('DB_NAME', 'maternal_health_db');


$pdo = null;

try {
    // Data Source Name (DSN) for connecting to MySQL
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    // Create the PDO connection instance
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
    
  
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    
    die("Database Connection Error: Could not connect to " . DB_NAME . ". " . $e->getMessage());
}


return $pdo;

?>