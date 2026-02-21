<?php
header('Content-Type: application/json');
try {
    $db_host = '127.0.0.1';
    $db_name = 'kbs_eng_db'; 
    $db_user = 'kbs-ccsonline';               
    $db_pass = '@Kbs2024!#';                   
    
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    
    // ดึงค่าล่าสุดจากตาราง threshold_logs (หรือตารางที่คุณใช้เก็บค่าจาก ESP32)
    $stmt = $conn->query("SELECT temp_limit, humid_limit, created_at FROM threshold_logs ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo json_encode([
            'success' => true,
            'temp' => $row['temp_limit'],
            'humid' => $row['humid_limit'],
            'time' => date('H:i:s', strtotime($row['created_at']))
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>