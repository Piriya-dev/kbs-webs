<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

// Database Connection
$db_host = '127.0.0.1';
$db_name = 'kbs_eng_db'; 
$db_user = 'kbs-ccsonline';               
$db_pass = '@Kbs2024!#';                   

echo "<h2>🛠️ Database Debugger</h2>";

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green;'>✅ Connected to Database Successfully!</p>";

    // ทดสอบดึงข้อมูลล่าสุด 5 แถวจากตารางจริงของคุณ
    $query = "SELECT sensor_id, temperature, humidity, created_at 
              FROM temp_sensor_logs 
              ORDER BY created_at DESC 
              LIMIT 5";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>📊 Latest 5 Records in 'temp_sensor_logs'</h3>";
    if (count($rows) > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
        echo "<tr style='background:#eee;'><th>Sensor ID</th><th>Temperature</th><th>Humidity</th><th>Created At</th></tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . $row['sensor_id'] . "</td>";
            echo "<td>" . $row['temperature'] . "</td>";
            echo "<td>" . $row['humidity'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>❌ No data found in table 'temp_sensor_logs'.</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>