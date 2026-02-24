<?php
/**
 * motor_drive_room_settings.php - Full Integrated Version
 * Features: Individual Threshold, Line API, MQTT Control, SQL Logging
 */
$config = require 'config.php';
session_start();

// --- 1. ตรวจสอบสิทธิ์ ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

// --- 2. ดึงค่า Threshold ทั้งแบบ Global และ Individual ---
// --- 2. ดึงค่า Threshold ทั้งแบบ Global และ Individual จากตารางเดียวกัน ---
$global_temp = 24.0; $global_humid = 60.0;
$sensor_configs = [];
for ($i = 1; $i <= 5; $i++) { $sensor_configs[$i] = ['temp' => 40.0, 'humid' => 60.0]; }

try {
    $db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';                   
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    
    // ✅ แก้ไข: เปลี่ยนมาดึงค่า Global จาก threshold_configs โดยใช้ sensor_id = 0
    $stmt1 = $conn->query("SELECT temp_threshold, humid_threshold FROM threshold_configs WHERE sensor_id = 0");
    if ($r = $stmt1->fetch()) { 
        $global_temp = $r['temp_threshold']; 
        $global_humid = $r['humid_threshold']; 
    }

    // ✅ ดึงค่า Individual (S1-S5) จากตารางเดียวกัน
    $stmt2 = $conn->query("SELECT sensor_id, temp_threshold, humid_threshold FROM threshold_configs WHERE sensor_id > 0");
    while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $sensor_configs[$r['sensor_id']] = ['temp' => $r['temp_threshold'], 'humid' => $r['humid_threshold']];
    }
} catch (PDOException $e) { }

// --- 3. ส่วนประมวลผล Line Test (เดิม) ---
if (isset($_POST['action']) && $_POST['action'] === 'test_line_api') {
    header('Content-Type: application/json');
    $accessToken = 'C2wBOtd3y8bXw7m8TCPU6kE3y8cMFi1w4J98wC1SZiqirrYWMqCSrPcQKjwus39B/f/9Ev1bpE1FAWoDN4/Nq2zcACx0r0K88juxk+Rq4fbZgTQCRUgM5of+rl2tOsFR0URBFmSeVHeOAfhTe0xhQQdB04t89/1O/w1cDnyilFU='; 
    $userId = 'Ub4e26942b3c80454751b2d60939fb2ec'; 
    $userMsg = $_POST['message'] ?? "KBS Test Message";
    $data = ['to' => $userId, 'messages' => [['type' => 'text', 'text' => $userMsg]]];
    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    echo json_encode(['status' => $httpCode]); exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <title>Advanced Settings - KBS Monitoring</title>
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <style>
        :root { --card: #1e293b; --accent: #3b82f6; --bg: #0f172a; --line-green: #06c755; }
        body { background-color: var(--bg); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; }
        .settings-card { background: var(--card); padding: 25px; border-radius: 15px; margin-bottom: 20px; border: 1px solid #334155; }
        .input-mini { background: #0f172a; border: 1px solid #334155; color: #fff; padding: 8px; border-radius: 6px; width: 100%; font-weight: bold; }
        .save-btn { background: var(--accent); color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 10px; }
        .line-btn { background: var(--line-green); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .lightbulb { width: 18px; height: 18px; border-radius: 50%; background-color: #475569; margin: 0 auto; transition: 0.3s; }
        .sensor-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .sensor-table th { font-size: 0.7rem; color: #94a3b8; padding: 10px; text-align: left; border-bottom: 1px solid #334155; }
        .sensor-table td { padding: 10px; border-bottom: 1px solid #1e293b; }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #334155; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #22c55e; }
        input:checked + .slider:before { transform: translateX(24px); }
    </style>
</head>
<body class="app-container">
    <aside class="sidebar">
        <div class="sidebar-nav">
            <a href="/motor_drive_room_dashboard" class="nav-item">📊 Dashboard</a>
            <a href="/motor_drive_room_report" class="nav-item">📈 Report</a>
            <a href="#" class="nav-item active">⚙️ Settings</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div style="font-weight:700;">Control & Configuration</div>
            <div id="mqttStatus" style="color:#94a3b8; font-size:0.75rem;">MQTT: OFFLINE</div>
        </header>

        <div class="grid-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 20px;">
            
            <div class="settings-card">
                <h3 style="margin-bottom: 15px;">🕹️ Remote Hardware Control</h3>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                    <div>
                        <div style="font-weight: bold;">System Main Light</div>
                        <div style="font-size: 0.7rem; color: #64748b;">ESP8266 / ESP32 Relay</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="lightSwitch" onchange="publishLight(this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                <div style="margin-top: 20px;">
                    <h4 style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 10px;">Global System Threshold</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <input type="number" id="globalTemp" class="input-mini" value="<?=$global_temp?>" step="0.5">
                        <input type="number" id="globalHumid" class="input-mini" value="<?=$global_humid?>" step="1">
                    </div>
                    <button class="save-btn" onclick="saveGlobalThreshold()">Save Global Limit</button>
                </div>
            </div>

            <div class="settings-card" style="border-left: 5px solid var(--line-green);">
                <h3>📲 Alarm System Test</h3>
                <div style="margin-top: 15px;">
                    <label style="font-size: 0.7rem; color: #94a3b8;">Test Message Content</label>
                    <input type="text" id="lineMsg" class="input-mini" style="margin-top:5px;" value="🔔 [KBS TEST] ระบบแจ้งเตือนทำงานปกติ">
                    <button class="line-btn" id="btnLineTest" onclick="sendLineTest()" style="margin-top:15px; width: 100%;">🚀 Send Push Notification</button>
                </div>
            </div>

            
            <div class="settings-card" style="grid-column: span 2;">
                <h3 style="margin-bottom: 15px;">📍 Individual Sensor Calibration (S1 - S5)</h3>
                <table class="sensor-table">
                    <thead>
                        <tr>
                            <th>Sensor ID</th>
                            <th>Temp Limit (°C)</th>
                            <th>Humid Limit (%)</th>
                            <th>Live Connection</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($i=1; $i<=5; $i++): ?>
                        <tr>
                            <td style="color:var(--accent); font-weight:bold;">Sensor <?=$i?></td>
                            <td><input type="number" id="t_limit_<?=$i?>" class="input-mini" value="<?=$sensor_configs[$i]['temp']?>" step="0.1"></td>
                            <td><input type="number" id="h_limit_<?=$i?>" class="input-mini" value="<?=$sensor_configs[$i]['humid']?>" step="1"></td>
                            <td><div id="led<?=$i?>" class="lightbulb"></div></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                <button class="save-btn" style="background:#06c755;" onclick="saveIndividualThresholds()">Update Individual & Record Log</button>
            </div>

        </div>
    </main>

    <script>
        // MQTT Config
        const MQTT_CONFIG = { url: '<?=$config["mqtt_ws_url"]?>', user: '<?=$config["mqtt_user"]?>', pass: '<?=$config["mqtt_pass"]?>' };
        const client = mqtt.connect(MQTT_CONFIG.url, { username: MQTT_CONFIG.user, password: MQTT_CONFIG.pass, clientId: 'KBS_SET_'+Math.random().toString(16).substr(2,5) });
        const SENSOR_TOPICS = ['kbs/driveroom1/temp1', 'kbs/driveroom1/temp2', 'kbs/driveroom1/temp3', 'kbs/driveroom1/temp4', 'kbs/driveroom1/temp5'];
        const sensorLastSeen = {};

        client.on('connect', () => {
            document.getElementById('mqttStatus').innerText = "MQTT: CONNECTED";
            document.getElementById('mqttStatus').style.color = "#22c55e";
            SENSOR_TOPICS.forEach(t => client.subscribe(t));
        });

        client.on('message', (topic, payload) => {
            if (SENSOR_TOPICS.includes(topic)) {
                const id = SENSOR_TOPICS.indexOf(topic) + 1;
                sensorLastSeen[id] = Date.now();
                const led = document.getElementById(`led${id}`);
                if (led) { led.style.backgroundColor = "#22c55e"; led.style.boxShadow = "0 0 8px #22c55e"; }
            }
        });

        // 🔘 ฟีเจอร์ที่คุณต้องการ: Switch Control ESP8266
        function publishLight(state) {
            const status = state ? "Active" : "Unactive";
            // ส่งไปหา ESP8266 ผ่าน MQTT
            client.publish("kbs/driveroom1/light1", status, { qos: 1, retain: true });
            // อัปเดตสถานะลง Database
            fetch('/pages/firepump/update_status.php?status=' + status).catch(e => console.error(e));
        }

 // บันทึก Global Limit ลง MySQL
// --- 🌡️ ฟังก์ชันสำหรับบันทึก Global Threshold ---
function saveGlobalThreshold() {
    const t = document.getElementById('globalTemp').value;
    const h = document.getElementById('globalHumid').value;
    
    // ส่งข้อมูลในรูปแบบ Object ที่ Node-RED เข้าใจง่าย
    const payload = JSON.stringify({
        type: 'global',
        sensor_id: 0, 
        temp_threshold: parseFloat(t),
        humid_threshold: parseFloat(h)
    });

    // ใช้ Topic หลักตัวเดียวกับ Individual เพื่อให้ Node-RED จัดการที่เดียว
    client.publish("kbs/motordriveroom1/config_individual", payload, { qos: 1, retain: true });
    alert("💾 Global Threshold Sent!");
}

// --- 📍 ฟังก์ชันสำหรับบันทึก Individual Threshold (S1-S5) ---
function saveIndividualThresholds() {
    let sensors = [];
    for(let i=1; i<=5; i++) {
        sensors.push({
            sensor_id: i,
            temp_threshold: parseFloat(document.getElementById(`t_limit_${i}`).value),
            humid_threshold: parseFloat(document.getElementById(`h_limit_${i}`).value)
        });
    }

    const payload = JSON.stringify({
        type: 'individual',
        sensors: sensors
    });

    // ส่งค่าผ่าน MQTT เพื่อให้ Node-RED บันทึกลง threshold_configs
    client.publish("kbs/motordriveroom1/config_individual", payload, { qos: 1 });
    alert("💾 กำลังบันทึกค่ารายเซนเซอร์ลงตาราง threshold_configs...");
}

// 🔘 ฟีเจอร์เดิม: Switch Control ESP8266 (รักษาไว้ 100%)
function publishLight(state) {
    const status = state ? "Active" : "Unactive";
    client.publish("kbs/driveroom1/light1", status, { qos: 1, retain: true });
    fetch('/pages/firepump/update_status.php?status=' + status).catch(e => console.error(e));
}

        function sendLineTest() {
            const msg = document.getElementById('lineMsg').value;
            const fd = new FormData(); fd.append('action', 'test_line_api'); fd.append('message', msg);
            fetch('', { method: 'POST', body: fd }).then(res => res.json()).then(d => alert(d.status === 200 ? "Line Sent!" : "Error"));
        }

        // Watchdog เช็คสถานะเซนเซอร์
        setInterval(() => {
            const now = Date.now();
            for(let i=1; i<=5; i++) {
                if(now - sensorLastSeen[i] > 15000) {
                    const led = document.getElementById(`led${i}`);
                    if(led) { led.style.backgroundColor = "#475569"; led.style.boxShadow = "none"; }
                }
            }
        }, 5000);
    </script>
</body>
</html>