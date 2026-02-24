<?php
// update_thresholds.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    
    if (isset($data['type']) && $data['type'] === 'global') {
        // บันทึก Global Limit
        $stmt = $pdo->prepare("INSERT INTO threshold_logs (temp_limit, humid_limit) VALUES (?, ?)");
        $stmt->execute([$data['temp'], $data['humid']]);
    } else {
        // บันทึก Individual Limit (รายเซนเซอร์)
        $sql = "INSERT INTO threshold_configs (sensor_id, temp_threshold, humid_threshold) 
                VALUES (:id, :temp, :humid) ON DUPLICATE KEY UPDATE 
                temp_threshold = VALUES(temp_threshold), humid_threshold = VALUES(humid_threshold)";
        $stmt = $pdo->prepare($sql);
        foreach ($data['sensors'] as $s) {
            $stmt->execute([':id' => $s['sensor_id'], ':temp' => $s['temp_threshold'], ':humid' => $s['humid_threshold']]);
        }
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}