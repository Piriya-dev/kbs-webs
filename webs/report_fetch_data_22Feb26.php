<?php
$config = require 'config.php';
header('Content-Type: application/json');

// ตั้งค่า Timezone ให้ตรงกับประเทศไทย (ป้องกันเวลาใน DB กับ Server ไม่ตรงกัน)
date_default_timezone_set('Asia/Bangkok');

try {
    $db_host = '127.0.0.1';
    $db_name = 'kbs_eng_db'; 
    $db_user = 'kbs-ccsonline';               
    $db_pass = '@Kbs2024!#';                   
    
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. ✅ รับค่าและจัดการ Format เวลา
    // datetime-local จะส่งมาในรูปแบบ '2026-02-23T19:00' 
    // เราต้องเปลี่ยน 'T' เป็นช่องว่าง เพื่อให้ MySQL ค้นหาได้ถูกต้อง
    $from_raw = $_GET['from'] ?? '';
    $to_raw = $_GET['to'] ?? '';

    $from = !empty($from_raw) ? str_replace('T', ' ', $from_raw) : date('Y-m-d H:i:s', strtotime('-24 hours'));
    $to = !empty($to_raw) ? str_replace('T', ' ', $to_raw) : date('Y-m-d H:i:s');

    // 2. ✅ จัดการ Sensor IDs
    $sensors = isset($_GET['sensors']) && $_GET['sensors'] !== '' ? explode(',', $_GET['sensors']) : [1,2,3,4,5];
    $placeholders = implode(',', array_fill(0, count($sensors), '?'));
    
    // 3. ✅ SQL Query (ดึงข้อมูลเรียงตามเวลา)
    $query = "SELECT sensor_id, temperature, humidity, created_at 
              FROM temp_sensor_logs 
              WHERE created_at BETWEEN ? AND ? 
              AND sensor_id IN ($placeholders)
              ORDER BY created_at ASC";

    $stmt = $conn->prepare($query);
    
    // รวม Parameter: [from, to, s1, s2, s3, ...]
    $params = array_merge([$from, $to], $sensors);
    $stmt->execute($params);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. ✅ ส่งออกข้อมูล (ถ้าไม่มีข้อมูลให้ส่ง Array ว่าง)
    echo json_encode($results ?: []);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>