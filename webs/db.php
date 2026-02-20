<?php
require_once __DIR__ . '/../configs/config_iot.php';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    error_log('MySQL Connection Error: ' . $mysqli->connect_error); // secure logging
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]));
}

$mysqli->set_charset("utf8mb4");
?>



