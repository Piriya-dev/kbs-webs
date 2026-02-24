<?php
/**
 * setting.php - Full Integrated Version (DB-Value Synced)
 * Verified: ALL PREVIOUS FEATURES PRESERVED & NO MOVEMENTS.
 */
$config = require 'config.php';
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

// --- 1. ดึงข้อมูลจากฐานข้อมูล ---
$global_temp = 24.0; $global_humid = 60.0; $alarm_mode = 'individual';
$alarm_line = 1; $alarm_light = 1; 
$sensor_configs = [];
for ($i = 1; $i <= 5; $i++) { $sensor_configs[$i] = ['temp' => 27.0, 'humid' => 65.0]; }

try {
    // --- 2. เชื่อมต่อ DB ---
    $db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';                   
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // --- 3. ดึงค่าจริงมาทับ (ตามที่ Debug สำเร็จ) ---
    $stmt = $conn->query("SELECT sensor_id, temp_threshold, humid_threshold FROM threshold_configs WHERE sensor_id BETWEEN 1 AND 5");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = (int)$row['sensor_id'];
        // เขียนทับค่า Default ด้วยค่าจาก Database
        $sensor_configs[$id] = [
            'temp'  => (float)$row['temp_threshold'],
            'humid' => (float)$row['humid_threshold']
        ];
    }
} catch (PDOException $e) {
    // ถ้าพลาดให้ใช้ค่า Default 27/65
}

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
    <title>Advanced Settings - KBS</title>
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <style>
        :root { --card: #1e293b; --accent: #3b82f6; --bg: #0f172a; --line-green: #06c755; --orange: #f59e0b; }
        body { background-color: var(--bg); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; }
        .settings-card { background: var(--card); padding: 25px; border-radius: 15px; margin-bottom: 20px; border: 1px solid #334155; }
        .input-mini { background: #0f172a; border: 1px solid #334155; color: #fff; padding: 10px; border-radius: 8px; width: 100%; font-weight: bold; }
        .alarm-submit-btn { background: var(--orange); color: #000; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: 800; width: 100%; margin-top: 15px; text-transform: uppercase; }
        .checkbox-group { display: flex; flex-direction: column; gap: 12px; margin-top: 15px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 10px; }
        .checkbox-item { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem; }
        .checkbox-item input { width: 18px; height: 18px; cursor: pointer; }
        .save-btn { background: var(--accent); color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 10px; }
        .debug-console { background: #000; color: #22c55e; padding: 15px; border-radius: 10px; font-family: monospace; font-size: 0.8rem; height: 180px; overflow-y: auto; border: 1px solid #334155; }
        .sensor-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .sensor-table th { font-size: 0.7rem; color: #94a3b8; padding: 10px; text-align: left; border-bottom: 1px solid #334155; }
        .lightbulb { width: 18px; height: 18px; border-radius: 50%; background-color: #475569; margin: 0 auto; transition: 0.3s; }
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
            <div style="font-weight:700;">Control & Debug Configuration</div>
            <div id="mqttStatus" style="color:#94a3b8; font-size:0.75rem;">MQTT: OFFLINE</div>
        </header>

        <div class="grid-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 20px;">
            
            <div class="settings-card">
                <h3 style="margin-bottom: 15px;">🕹️ Hardware & Alarm Logic</h3>
                <div style="padding: 15px; border: 1px dashed var(--accent); border-radius: 12px; background: rgba(59, 130, 246, 0.05);">
                    <h4 style="font-size: 0.8rem; color: var(--accent); margin-bottom: 10px;">🚨 Alarm Trigger Condition</h4>
                    <select id="alarmMode" class="input-mini">
                        <option value="average" <?php echo ($alarm_mode == 'average' ? 'selected' : ''); ?>>Avg All > Global Threshold</option>
                        <option value="individual" <?php echo ($alarm_mode == 'individual' ? 'selected' : ''); ?>>Any Sensor > Individual Threshold</option>
                    </select>
                    <h4 style="font-size: 0.8rem; color: var(--accent); margin-top: 15px; margin-bottom: 5px;">📤 Warning Output Channels</h4>
                    <div class="checkbox-group">
                        <label class="checkbox-item"><input type="checkbox" id="enableLine" <?php echo ($alarm_line ? 'checked' : ''); ?>> 📲 Line API Notification</label>
                        <label class="checkbox-item"><input type="checkbox" id="enableLight" <?php echo ($alarm_light ? 'checked' : ''); ?>> 🔴 MQTT Light (kbs/driveroom1/light1)</label>
                    </div>
                    <button class="alarm-submit-btn" onclick="saveAlarmLogic()">Submit Alarm Logic</button>
                </div>
            </div>

            <div class="settings-card">
                <h3 style="margin-bottom: 15px;">🔍 Raw Data Debug (Live)</h3>
                <div id="debugConsole" class="debug-console">Waiting for sensor data...</div>
                <div style="margin-top: 10px; font-size: 0.75rem; color: #94a3b8;">
                    Next Line Msg Preview: <span id="msgPreview" style="color: #fff; font-weight: bold;">-- Normal --</span>
                </div>
            </div>

            <div class="settings-card">
                <h3>🌡️ Global System Threshold</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                    <div><small>Temp (°C)</small><input type="number" id="globalTemp" class="input-mini" value="<?=$global_temp?>" step="0.5"></div>
                    <div><small>Humid (%)</small><input type="number" id="globalHumid" class="input-mini" value="<?=$global_humid?>" step="1"></div>
                </div>
                <button class="save-btn" onclick="saveGlobalThreshold()">Save Global Limit</button>
            </div>

            <div class="settings-card" style="border-left: 5px solid var(--line-green);">
                <h3>📲 Manual Line Test</h3>
                <input type="text" id="lineMsg" class="input-mini" style="margin-top:10px;" value="🔔 [KBS DEBUG] Test Notification">
                <button class="save-btn" onclick="sendLineTest()" style="background: var(--line-green);">🚀 Send Push Notification</button>
            </div>

            <div class="settings-card" style="grid-column: span 2;">
                <h3>📍 Sensor Calibration (S1 - S5)</h3>
                <table class="sensor-table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                    <thead>
                        <tr style="text-align: left; font-size: 0.75rem; color: #94a3b8; border-bottom: 1px solid #334155;">
                            <th style="padding: 10px;">Sensor ID</th>
                            <th>Temp Limit (°C)</th>
                            <th>Humid Limit (%)</th>
                            <th style="text-align: center;">Live Status</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php for($i=1; $i<=5; $i++): 
        // ดึงค่าที่เราเตรียมไว้จาก DB (ถ้า DB ไม่มี จะได้ค่า 40.0/60.0 จากตอน Initialize)
        $actual_t = $sensor_configs[$i]['temp'];
        $actual_h = $sensor_configs[$i]['humid'];
    ?>
    <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 10px; font-weight: bold; color: var(--accent);">Sensor 0<?=$i?></td>
        <td>
            <input type="number" id="t_limit_<?=$i?>" class="input-mini" style="width: 100px;" 
                   value="<?php echo number_format($actual_t, 1, '.', ''); ?>" step="0.1">
        </td>
        <td>
            <input type="number" id="h_limit_<?=$i?>" class="input-mini" style="width: 100px;" 
                   value="<?php echo (int)$actual_h; ?>" step="1">
        </td>
        <td><div id="led<?=$i?>" class="lightbulb"></div></td>
    </tr>
    <?php endfor; ?>
</tbody>
                </table>
                <button class="save-btn" style="width:100%; margin-top:15px; background:var(--line-green);" onclick="saveIndividualThresholds()">
                    💾 Update All Thresholds to Database
                </button>
            </div>
        </div>
    </main>

    <script>
        const MQTT_CONFIG = { url: '<?=$config["mqtt_ws_url"]?>', user: '<?=$config["mqtt_user"]?>', pass: '<?=$config["mqtt_pass"]?>' };
        const client = mqtt.connect(MQTT_CONFIG.url, { username: MQTT_CONFIG.user, password: MQTT_CONFIG.pass, clientId: 'KBS_SET_FULL_'+Math.random().toString(16).substr(2,5) });
        
        const SENSOR_TOPICS = ['kbs/driveroom1/temp1', 'kbs/driveroom1/temp2', 'kbs/driveroom1/temp3', 'kbs/driveroom1/temp4', 'kbs/driveroom1/temp5'];
        let liveRawData = {};
        let lastAlarmStatus = "Unactive";

        client.on('connect', () => {
            document.getElementById('mqttStatus').innerText = "MQTT: CONNECTED";
            document.getElementById('mqttStatus').style.color = "#22c55e";
            SENSOR_TOPICS.forEach(t => client.subscribe(t));
            client.subscribe('kbs/driveroom1/light1');
        });

        client.on('message', (topic, payload) => {
            const id = SENSOR_TOPICS.indexOf(topic) + 1;
            if(id > 0) {
                const data = JSON.parse(payload.toString());
                liveRawData[id] = data;
                const led = document.getElementById(`led${id}`);
                if(led) led.style.background = "#22c55e";
                document.getElementById('debugConsole').innerText = JSON.stringify(liveRawData, null, 2);
                checkAndTriggerAlarm();
            }
            if(topic === 'kbs/driveroom1/light1') {
                const status = payload.toString();
                document.getElementById('lightSwitch')?.setAttribute('checked', status === 'Active');
            }
        });

        function checkAndTriggerAlarm() {
            const mode = document.getElementById('alarmMode').value;
            const lineEnabled = document.getElementById('enableLine').checked;
            const lightEnabled = document.getElementById('enableLight').checked;
            let isTriggered = false;
            let warningMsg = "";
            const sensors = Object.keys(liveRawData);
            if(sensors.length === 0) return;

            if (mode === 'average') {
                const totalT = Object.values(liveRawData).reduce((a, b) => a + parseFloat(b.temp), 0);
                const avgT = totalT / sensors.length;
                const gLimitT = parseFloat(document.getElementById('globalTemp').value);
                if (avgT > gLimitT) {
                    isTriggered = true;
                    warningMsg = `🚨 [KBS AVG ALERT] อุณหภูมิเฉลี่ยห้อง (${avgT.toFixed(1)}°C) สูงเกินเกณฑ์!`;
                }
            } else {
                for (let id in liveRawData) {
                    const currentT = parseFloat(liveRawData[id].temp);
                    const currentH = parseFloat(liveRawData[id].humid);
                    const limitT = parseFloat(document.getElementById(`t_limit_${id}`).value);
                    const limitH = parseFloat(document.getElementById(`h_limit_${id}`).value);
                    if (currentT > limitT || currentH > limitH) {
                        isTriggered = true;
                        warningMsg = `⚠️ [KBS SENSOR ALERT] Sensor 0${id} พบค่าสูงผิดปกติ! (T:${currentT}/H:${currentH})`;
                        break; 
                    }
                }
            }

            const previewEl = document.getElementById('msgPreview');
            previewEl.innerText = isTriggered ? warningMsg : "-- สถานะปกติ --";
            previewEl.style.color = isTriggered ? "#ef4444" : "#22c55e";

            const currentCommand = isTriggered ? "Active" : "Unactive";
            if (currentCommand !== lastAlarmStatus) {
                if (lightEnabled) client.publish("kbs/driveroom1/light1", currentCommand, { qos: 1, retain: true });
                if (lineEnabled && isTriggered) autoPushLine(warningMsg);
                lastAlarmStatus = currentCommand;
            }
        }

        function autoPushLine(msg) {
            const fd = new FormData();
            fd.append('action', 'test_line_api');
            fd.append('message', msg);
            fetch('', { method: 'POST', body: fd });
        }

        function saveAlarmLogic() {
            const mode = document.getElementById('alarmMode').value;
            const lineOn = document.getElementById('enableLine').checked ? 1 : 0;
            const lightOn = document.getElementById('enableLight').checked ? 1 : 0;
            client.publish("kbs/motordriveroom1/config_individual", JSON.stringify({ type: 'alarm_logic_update', alarm_mode: mode, alarm_line: lineOn, alarm_light: lightOn }), { qos: 1, retain: true });
            alert("✅ บันทึกเงื่อนไขและช่องทางแจ้งเตือนสำเร็จ!");
        }

        function saveIndividualThresholds() {
            let sensors = [];
            for(let i=1; i<=5; i++) {
                sensors.push({ sensor_id: i, temp_threshold: parseFloat(document.getElementById(`t_limit_${i}`).value), humid_threshold: parseFloat(document.getElementById(`h_limit_${i}`).value) });
            }
            client.publish("kbs/motordriveroom1/config_individual", JSON.stringify({ type: 'individual', sensors: sensors }), { qos: 1 });
            alert("💾 บันทึกค่ารายเซนเซอร์แล้ว");
        }

        function saveGlobalThreshold() {
            const t = document.getElementById('globalTemp').value;
            const h = document.getElementById('globalHumid').value;
            client.publish("kbs/motordriveroom1/config_individual", JSON.stringify({ type: 'global', sensor_id: 0, temp_threshold: parseFloat(t), humid_threshold: parseFloat(h) }), { qos: 1, retain: true });
            alert("💾 บันทึก Global Limit แล้ว");
        }

        function sendLineTest() {
            const msg = document.getElementById('lineMsg').value;
            const fd = new FormData(); fd.append('action', 'test_line_api'); fd.append('message', msg);
            fetch('', { method: 'POST', body: fd }).then(res => res.json()).then(d => alert(d.status === 200 ? "Line Sent!" : "Error"));
        }

        function publishLight(state) {
            const status = state ? "Active" : "Unactive";
            client.publish("kbs/driveroom1/light1", status, { qos: 1, retain: true });
        }
    </script>
</body>
</html>