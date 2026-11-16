<?php
/*
 * Every page starts by requiring this file. It loads the settings, the shared
 * bootstrap (session, CSRF, error handling) and opens the database connection.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/bootstrap.php';

$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

// MySQL uses the same clock as PHP, and rejects bad data instead of
// silently truncating it.
$tz_offset = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '$tz_offset', "
        . "sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    render_error_page(503, 'Service unavailable', 'We cannot reach the database right now. Please try again in a few minutes.');
}
