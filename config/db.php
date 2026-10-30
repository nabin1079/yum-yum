<?php
define('BASE_PATH', '/yum-yum/');

define('DB_HOST', 'localhost');
define('DB_NAME', 'yum_yum_db');
define('DB_USER', 'root');      
define('DB_PASS', '');           
define('DB_CHARSET', 'utf8mb4');
// ------------------------------------------------------------

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        
    PDO::ATTR_EMULATE_PREPARES   => false,                   
];

// Build the DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST
     . ";dbname=" . DB_NAME
     . ";charset=" . DB_CHARSET;

// Create the PDO instance
// If connection fails, PDOException is thrown and execution stops —
// no silent failures, no blank pages with hidden DB errors.
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('DB Connection failed: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed. Please try again later.']));
}