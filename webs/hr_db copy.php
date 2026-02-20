<?php
// db.php
// Confidential - Internal Use Only

// Load DB credentials securely
require_once __DIR__ . '/configs/hr_config.php'; // adjust if db.php is in v1 => use '../configs/config.php'

// Connect to MySQL
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($mysqli->connect_error) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]));
}

// Set charset
$mysqli->set_charset("utf8mb4");
?>

