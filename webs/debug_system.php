<?php
/**
 * debug_system.php - KBS System Health Checker
 */
$config = require 'config.php';

// --- 1. ตรวจสอบค่าล่าสุดจาก Database ทันทีที่โหลดหน้า ---
$db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';                   
$conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);

$stmt = $conn->query("SELECT * FROM threshold_configs WHERE sensor_id = 0");
$db_data = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>KBS DEBUG CONSOLE</title>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <style>
        body { background: #020617; color: #10b981; font-family: 'Courier New', monospace; padding: 30px; line-height: 1.6; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .section { background: #1e293b; border: 1px solid #334155; padding: 20px; border-radius: 12px; }
        h2 { color: #3b82f6; border-bottom: 2px solid #334155; padding-bottom: 10px; margin-top: 0; }
        .data-box { background: #000; padding: 15px; border-radius: 8px; font-size: 0.85rem; color: #34d399; overflow-x: auto; }
        .btn { background: #3b82f6; color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; margin-right: 5px; }
        .btn:hover { background: #2563eb; }
        .log { height: 200px; overflow-y: auto; background: #000; padding: 10px; font-size: 0.8rem; border: 1px solid #334155; }
        .tag { padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 0.7rem; }
        .tag-db { background: #8b5cf6; color: white; }
        .tag-mqtt { background: #f59e0b; color: white; }
    </style>
</head>
<body>

    <h1>🛠️ KBS System Debugging Console</h1>
    <p style="color: #94a3b8;">หน้าทดสอบการทำงานของ Trigger Condition, Warning Output และ Manual Control</p>

    <div class="grid">
        
        <div class="section">
            <h2>📂 1. Database Persistent State</h2>
            <p>ตรวจสอบว่าค่าที่เก็บใน Table (sensor_id=0) ตรงตามที่เซฟไว้หรือไม่</p>
            <div class="data-box">
                <b>Table Status (Current):</b><br>
                - Mode: <span id="db_mode"><?= $db_data['alarm_mode'] ?></span><br>
                - Line API: <span id="db_line"><?= $db_data['alarm_line'] == 1 ? 'ENABLED' : 'DISABLED' ?></span><br>
                - MQTT Light: <span id="db_light"><?= $db_data['alarm_light'] == 1 ? 'ENABLED' : 'DISABLED' ?></span><br>
                - Last SW Status: <span id="db_sw"><?= $db_data['light_status'] ?></span>
            </div>
            <button class="btn" style="margin-top:15px;" onclick="location.reload()">🔄 Refresh DB State</button>
        </div>

        <div class="section">
            <h2>🔗 2. HTTP Fetch Simulator</h2>
            <p>จำลองการส่งค่าจากหน้า Setting ไปยัง <code>update_status.php</code></p>
            <div style="margin-bottom: 10px;">
                <small>Test Alarm Logic:</small><br>
                <button class="btn" onclick="simulateLogic('average', 1, 1)">Set Avg/Line/Light ON</button>
                <button class="btn" onclick="simulateLogic('individual', 0, 0)" style="background:#64748b;">Set Indiv/OFF</button>
            </div>
            <div>
                <small>Test Light Switch:</small><br>
                <button class="btn" onclick="simulateLight('Active')" style="background:#f59e0b;">Set Active</button>
                <button class="btn" onclick="simulateLight('Unactive')" style="background:#475569;">Set Unactive</button>
            </div>
            <div id="fetchLog" class="log" style="margin-top:15px; height: 100px;">Waiting for action...</div>
        </div>

        <div class="section" style="grid-column: span 2;">
            <h2>📡 3. MQTT Live Packet Monitor</h2>
            <p>ดูการเคลื่อนไหวของข้อมูลผ่าน MQTT Broker แบบสดๆ</p>
            <div id="mqttLog" class="log"></div>
        </div>

    </div>

    <script>
        // --- MQTT Config ---
        const client = mqtt.connect('<?= $config["mqtt_ws_url"] ?>', {
            username: '<?= $config["mqtt_user"] ?>',
            password: '<?= $config["mqtt_pass"] ?>',
            clientId: 'DEBUG_UI_' + Math.random().toString(16).substr(2, 4)
        });

        client.on('connect', () => {
            appendLog('mqttLog', 'System', 'MQTT Connected', 'tag-mqtt');
            client.subscribe('kbs/driveroom1/#');
            client.subscribe('kbs/motordriveroom1/#');
        });

        client.on('message', (topic, payload) => {
            appendLog('mqttLog', topic, payload.toString(), 'tag-mqtt');
        });

        // --- HTTP Simulation Functions ---
        function simulateLogic(mode, line, light) {
            const url = `/pages/firepump/update_status.php?mode=${mode}&line=${line}&light=${light}`;
            executeFetch(url, 'Logic Update');
        }

        function simulateLight(status) {
            const url = `/pages/firepump/update_status.php?status=${status}`;
            executeFetch(url, 'Manual Switch Update');
        }

        function executeFetch(url, action) {
            appendLog('fetchLog', 'Browser', 'Fetching: ' + url, 'tag-db');
            fetch(url)
                .then(res => res.text())
                .then(data => {
                    appendLog('fetchLog', 'Server', 'Response: ' + data, 'tag-db');
                    if(data.includes('Success') || data.includes('Updated')) {
                        appendLog('fetchLog', 'Notice', 'Reloading to verify DB in 2s...', 'tag-db');
                        setTimeout(() => location.reload(), 2000);
                    }
                })
                .catch(err => appendLog('fetchLog', 'Error', err, 'tag-db'));
        }

        function appendLog(elementId, source, msg, tagClass) {
            const log = document.getElementById(elementId);
            const time = new Date().toLocaleTimeString();
            log.innerHTML += `<div>[${time}] <span class="tag ${tagClass}">${source}</span> ${msg}</div>`;
            log.scrollTop = log.scrollHeight;
        }
    </script>
</body>
</html>