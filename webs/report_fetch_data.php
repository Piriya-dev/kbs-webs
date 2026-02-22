<?php
$config = require 'config.php';
header('Content-Type: application/json');

try {
    $db_host = '127.0.0.1';
    $db_name = 'kbs_eng_db'; 
    $db_user = 'kbs-ccsonline';               
    $db_pass = '@Kbs2024!#';                   
    
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // รับค่าจาก GET
    $from = $_GET['from'] ?? date('Y-m-d H:i:s', strtotime('-24 hours'));
    $to = $_GET['to'] ?? date('Y-m-d H:i:s');
    $sensors = isset($_GET['sensors']) ? explode(',', $_GET['sensors']) : [1,2,3,4];

    $placeholders = implode(',', array_fill(0, count($sensors), '?'));
    
    // *** ปรับชื่อคอลัมน์ให้ตรงตามรูปภาพที่คุณส่งมา ***
   // SQL สำหรับดึงข้อมูลที่ตรงกับตารางของคุณ
// ดึงข้อมูลโดยระบุช่วงเวลาและกลุ่ม Sensor ID ที่เลือก
$query = "SELECT sensor_id, temperature, humidity, created_at 
          FROM temp_sensor_logs 
          WHERE created_at BETWEEN ? AND ? 
          AND sensor_id IN ($placeholders)
          ORDER BY created_at ASC";
    $stmt = $conn->prepare($query);
    $params = array_merge([$from, $to], $sensors);
    $stmt->execute($params);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>