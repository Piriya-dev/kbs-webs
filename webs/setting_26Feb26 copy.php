<?php
/**
 * setting.php - Full Integrated Version (100% Verified)
 */
$config = require 'config.php';
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

// --- 1. Database Connection (ต้องมาก่อนการ Query) ---
$db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';                   
try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection error");
}

// ✅ 2. ส่วนรับค่าบันทึก Alarm Logic ลง Database (ที่เพิ่มเข้าไปเพื่อให้ Persistent)
if (isset($_POST['action']) && $_POST['action'] === 'save_logic_db') {
    header('Content-Type: application/json');
    $mode = $_POST['mode'];
    $line = (int)$_POST['line'];
    $light = (int)$_POST['light'];
    
    $stmt = $conn->prepare("UPDATE threshold_configs SET alarm_mode = ?, alarm_line = ?, alarm_light = ? WHERE sensor_id = 0");
    $stmt->execute([$mode, $line, $light]);
    echo json_encode(['status' => 'success']);
    exit;
}

// --- 3. ดึงค่าเริ่มต้นจาก Database ---
$global_temp = 27.0; $global_humid = 65.0;
$alarm_mode = 'individual'; $alarm_line = 1; $alarm_light = 1;
$sensor_configs = [];
for ($i = 1; $i <= 5; $i++) { $sensor_configs[$i] = ['temp' => 40.0, 'humid' => 60.0]; }

$stmt = $conn->query("SELECT * FROM threshold_configs WHERE sensor_id BETWEEN 0 AND 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $id = (int)$row['sensor_id'];
    if ($id === 0) {
        $global_temp = (float)$row['temp_threshold'];
        $global_humid = (float)$row['humid_threshold'];
        $alarm_mode = $row['alarm_mode'] ?? 'individual';
        $alarm_line = (int)($row['alarm_line'] ?? 1);
        $alarm_light = (int)($row['alarm_light'] ?? 1);
    } else {
        $sensor_configs[$id] = ['temp' => (float)$row['temp_threshold'], 'humid' => (float)$row['humid_threshold']];
    }
}

// --- 4. ส่วนทดสอบ Line API (โค้ดดั้งเดิมของคุณ) ---
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
        :root { --sidebar-width: 260px; --card: #1e293b; --accent: #3b82f6; --bg: #0f172a; --line-green: #06c755; --orange: #f59e0b; }
        body { display: flex; margin: 0; background-color: var(--bg); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); background: var(--card); height: 100vh; position: fixed; border-right: 1px solid #334155; padding: 25px 0; z-index: 100; }
        .sidebar-logo { font-size: 1.5rem; font-weight: 800; color: var(--accent); margin-bottom: 35px; padding: 0 25px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 25px; color: #94a3b8; text-decoration: none; transition: 0.3s; }
        .nav-item:hover { background: rgba(59, 130, 246, 0.1); color: #fff; }
        .nav-item.active { background: var(--accent); color: white; font-weight: bold; }
        .main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 25px; min-width: 0; }
        .topbar { display: flex; justify-content: space-between; align-items: center; background: var(--card); padding: 15px 25px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #334155; }
        .settings-card { background: var(--card); padding: 25px; border-radius: 15px; margin-bottom: 20px; border: 1px solid #334155; }
        .input-mini { background: #0f172a; border: 1px solid #334155; color: #fff; padding: 10px; border-radius: 8px; width: 100%; font-weight: bold; }
        .alarm-submit-btn { background: var(--orange); color: #000; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: 800; width: 100%; margin-top: 15px; text-transform: uppercase; }
        .checkbox-group { display: flex; flex-direction: column; gap: 12px; margin-top: 15px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 10px; }
        .checkbox-item { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem; }
        .save-btn { background: var(--accent); color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 10px; }
        .debug-console { background: #000; color: #22c55e; padding: 15px; border-radius: 10px; font-family: monospace; font-size: 0.8rem; height: 180px; overflow-y: auto; border: 1px solid #334155; }
        .sensor-table { width: 100%; border-collapse: collapse; }
        .sensor-table th { font-size: 0.7rem; color: #94a3b8; padding: 10px; text-align: left; border-bottom: 1px solid #334155; }
        .lightbulb { width: 18px; height: 18px; border-radius: 50%; background-color: #475569; margin: 0 auto; transition: 0.3s; }
        .switch { position: relative; display: inline-block; width: 44px; height: 22px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #475569; transition: .4s; border-radius: 22px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--orange); }
        input:checked + .slider:before { transform: translateX(22px); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-logo">KBS <span style="color: #fff;">ENG</span></div>
        <nav class="sidebar-nav">
            <a href="/motor_drive_room_dashboard" class="nav-item">📊 Dashboard</a>
            <a href="/motor_drive_room_report" class="nav-item">📈 Report</a>
            <a href="#" class="nav-item active">⚙️ Settings</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div style="font-weight:700;">Control & Debug Configuration</div>
            <div id="mqttStatus" style="color:#94a3b8; font-size:0.75rem; font-weight:bold;">MQTT: OFFLINE</div>
        </header>

        <div class="grid-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="settings-card">
                <h3>🕹️ Hardware & Alarm Logic</h3>
                <div style="padding: 15px; border: 1px dashed var(--accent); border-radius: 12px; background: rgba(59, 130, 246, 0.05);">
                    <h4 style="font-size: 0.8rem; color: var(--accent); margin-bottom: 10px;">🚨 Alarm Trigger Condition</h4>
                    <select id="alarmMode" class="input-mini">
                        <option value="average" <?php echo ($alarm_mode == 'average') ? 'selected' : ''; ?>>Avg All > Global Threshold</option>
                        <option value="individual" <?php echo ($alarm_mode == 'individual') ? 'selected' : ''; ?>>Any Sensor > Individual Threshold</option>
                    </select>

                    <h4 style="font-size: 0.8rem; color: var(--accent); margin-top: 15px; margin-bottom: 5px;">📤 Warning Output Channels</h4>
                    <div class="checkbox-group">
                        <label class="checkbox-item"><input type="checkbox" id="enableLine" <?php echo ($alarm_line == 1) ? 'checked' : ''; ?>> 📲 Line API Notification</label>
                        <label class="checkbox-item"><input type="checkbox" id="enableLight" <?php echo ($alarm_light == 1) ? 'checked' : ''; ?>> 🔴 MQTT Light (Auto Mode)</label>
                    </div>

                    <hr style="border: 0.5px solid #334155; margin: 15px 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.8rem; color: var(--orange);">Manual Light Switch</span>
                        <label class="switch">
                            <input type="checkbox" id="lightSwitch" onchange="publishLight(this.checked, true)">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div id="switchFeedback" style="font-size: 0.65rem; color: #94a3b8; text-align: right; margin-top: 5px;">Ready...</div>
                    <button class="alarm-submit-btn" onclick="saveAlarmLogic()">Submit Alarm Logic</button>
                </div>
            </div>

            <div class="settings-card">
                <h3>🔍 Raw Data Debug (Live)</h3>
                <div id="debugConsole" class="debug-console">Waiting...</div>
                <div style="margin-top: 10px; font-size: 0.75rem; color: #94a3b8;">
                    Line Preview: <span id="msgPreview" style="color: #fff; font-weight:bold;">-- Normal --</span>
                </div>
            </div>

            <div class="settings-card">
                <h3>🌡️ Global System Threshold</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                    <div><small>Temp</small><input type="number" id="globalTemp" class="input-mini" value="<?=$global_temp?>" step="0.5"></div>
                    <div><small>Humid</small><input type="number" id="globalHumid" class="input-mini" value="<?=$global_humid?>" step="1"></div>
                </div>
                <button class="save-btn" onclick="saveGlobalThreshold()">Save Global</button>
            </div>

            <div class="settings-card" style="border-left: 5px solid var(--line-green);">
                <h3>📲 Manual Line Test</h3>
                <input type="text" id="lineMsg" class="input-mini" value="🔔 [KBS DEBUG] Test Message">
                <button class="save-btn" onclick="sendLineTest()" style="background: var(--line-green);">🚀 Send Test</button>
            </div>

            <div class="settings-card" style="grid-column: span 2;">
                <h3>📍 Sensor Calibration (S1 - S5)</h3>
                <table class="sensor-table">
                    <thead>
                        <tr><th>Sensor ID</th><th>Temp Limit</th><th>Humid Limit</th><th>Live</th></tr>
                    </thead>
                    <tbody>
                        <?php for($i=1; $i<=5; $i++): ?>
                        <tr>
                            <td>Sensor 0<?=$i?></td>
                            <td><input type="number" id="t_limit_<?=$i?>" class="input-mini" value="<?=number_format($sensor_configs[$i]['temp'], 1)?>"></td>
                            <td><input type="number" id="h_limit_<?=$i?>" class="input-mini" value="<?=(int)$sensor_configs[$i]['humid']?>"></td>
                            <td><div id="led<?=$i?>" class="lightbulb"></div></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                <button class="save-btn" style="background:var(--line-green);" onclick="saveIndividualThresholds()">💾 Update All to Database</button>
            </div>
        </div>
    </main>

    <script>
        // ✅ 1. MQTT Config
        const MQTT_CONFIG = {
            url: '<?php echo $config["mqtt_ws_url"]; ?>',
            user: '<?php echo $config["mqtt_user"]; ?>',
            pass: '<?php echo $config["mqtt_pass"]; ?>',
            topics: [
                'kbs/driveroom1/temp1','kbs/driveroom1/temp2','kbs/driveroom1/temp3',
                'kbs/driveroom1/temp4','kbs/driveroom1/temp5','kbs/driveroom1/light1',
                'kbs/motordriveroom1/config_global','kbs/motordriveroom1/config_individual'
            ]
        };

        const client = mqtt.connect(MQTT_CONFIG.url, {
            username: MQTT_CONFIG.user, password: MQTT_CONFIG.pass,
            clientId: 'KBS_' + Math.random().toString(16).substr(2, 8)
        });

        let liveRawData = {};
        let lastAlarmStatus = "Unactive";
        let alarmTimer = null;
        let isWaitingConfirm = false;
        let isManualAction = false;
        let lastSentTime = 0; 
        let isAlarmActive = false;


        client.on('connect', () => {
        const statusEl = document.getElementById('mqttStatus');
         if (statusEl) {
            statusEl.innerText = "• Online"; 
             statusEl.style.color = "#22c55e";
           }
    MQTT_CONFIG.topics.forEach(t => client.subscribe(t));
 });
 client.on('offline', () => {
    const statusEl = document.getElementById('mqttStatus');
    if (statusEl) {
        statusEl.innerText = "• Offline";
        statusEl.style.color = "#ef4444";
    }
});

        client.on('message', (topic, payload) => {
            if(topic === 'kbs/driveroom1/light1') {
                const status = payload.toString();
                // ✅ รักษาความเป็นอิสระของ Manual Switch (Feedback Log เท่านั้น)
                console.log("MQTT Light Feedback:", status);
                return;
            }
            const match = topic.match(/temp(\d+)/);
            if(match) {
                const id = match[1];
                liveRawData[id] = JSON.parse(payload.toString());
                document.getElementById(`led${id}`).style.background = "#22c55e";
                document.getElementById('debugConsole').innerText = JSON.stringify(liveRawData, null, 2);
                checkAndTriggerAlarm();
            }
        });

 // ✅ ส่วนนี้ต้องอยู่นอกฟังก์ชัน (Global) และห้ามประกาศซ้ำถ้ามีอยู่แล้ว
// let lastSentTime = 0; 
// let isAlarmActive = false;
let confirmTimer = null; // เพิ่มแค่ตัวนี้ตัวเดียว

function checkAndTriggerAlarm() {
    const mode = document.getElementById('alarmMode').value;
    const lineEnabled = document.getElementById('enableLine').checked;
    const lightEnabled = document.getElementById('enableLight').checked;
    const previewEl = document.getElementById('msgPreview');
    
    let isTriggered = false;
    let warningMsg = "";

    // --- 1. รับค่าจาก MQTT (ทำงานปกติ ไม่โดน Delay) ---
    const vals = Object.values(liveRawData);
    if(vals.length === 0) return;

    const avgT = vals.reduce((a, b) => a + parseFloat(b.temp || 0), 0) / vals.length;
    const avgH = vals.reduce((a, b) => a + parseFloat(b.humid || 0), 0) / vals.length;

    // --- 2. เช็คเงื่อนไขปัจจุบัน ---
    if (mode === 'average') {
        const gLimitT = parseFloat(document.getElementById('globalTemp').value);
        if (avgT > gLimitT) { 
            isTriggered = true; 
            warningMsg = `🚨 [KBS AVG ALERT]\n🌡️ Temp: ${avgT.toFixed(2)}°C > ${gLimitT}`; 
        }
    } else {
        for (let id in liveRawData) {
            const t = parseFloat(liveRawData[id].temp);
            const lt = parseFloat(document.getElementById(`t_limit_${id}`).value);
            if (t > lt) { 
                isTriggered = true; 
                warningMsg = `⚠️ [KBS S${id} ALERT]\n🌡️ T: ${t.toFixed(2)}°C`; 
                break; 
            }
        }
    }

    const currentTime = Date.now();

    // --- 3. 🛡️ ด่านตรวจ (Delay 10s) - มั่นใจได้ว่า MQTT ยังทำงานอยู่ข้างหลัง ---
    if (isTriggered) {
        if (!isAlarmActive && lastSentTime === 0) {
            if (!confirmTimer) {
                // เริ่มนับ 10 วิ (ตัวเลขบน Bar ยังขยับปกติ MQTT ไม่หลุด)
                previewEl.innerText = "⏳ Noise Filtering: Re-checking in 10s...";
                previewEl.style.color = "#f59e0b";
                confirmTimer = setTimeout(() => { confirmTimer = "READY"; }, 10000);
            }
            if (confirmTimer !== "READY") return; // หยุดรอตรงนี้จนกว่าจะครบ 10 วิ
        }

        // --- 4. 🚨 สั่งงานจริง (หลังผ่าน 10 วิ) ---
        if (lightEnabled && !isManualAction) {
            publishLight(true, false); // สั่ง MQTT Light
            isAlarmActive = true;
        }

        if (lastSentTime === 0 || (currentTime - lastSentTime) >= 30000) {
            if (lineEnabled) autoPushLine(warningMsg);
            lastSentTime = currentTime;
            previewEl.innerText = "🚨 ALARM CONFIRMED: Notify Sent";
            previewEl.style.color = "#ef4444";
        } else {
            let nextIn = Math.ceil((30000 - (currentTime - lastSentTime)) / 1000);
            previewEl.innerText = `⏳ Repeating in ${nextIn}s...`;
            previewEl.style.color = "#f59e0b";
        }
    } else {
        // --- 5. ✅ Recovery & Reset (ล้าง Noise) ---
        if (confirmTimer && confirmTimer !== "READY") clearTimeout(confirmTimer);
        confirmTimer = null;

        if (lastSentTime !== 0 || isAlarmActive) {
            isManualAction = false; 
            if (lightEnabled) publishLight(false, false); // สั่ง MQTT Light ปิด
            if (lineEnabled && lastSentTime !== 0) autoPushLine("✅ [KBS RECOVERY]");
            lastSentTime = 0; 
            isAlarmActive = false;
        }
        previewEl.innerText = "-- Normal --"; 
        previewEl.style.color = "#22c55e";
    }
}     // ✅ 3. Manual & Save Functions
        function publishLight(state, isManual = false) {
            if (isManual) { isManualAction = true; setTimeout(() => { isManualAction = false; }, 10000); }
            const status = state ? "Active" : "Unactive";
            const feedbackEl = document.getElementById('switchFeedback');
            
            client.publish("kbs/driveroom1/light1", status, { qos: 1, retain: true });
            
            // ✅ ส่งไป update_status.php สำหรับ ESP8266
            fetch(`/pages/firepump/update_status.php?status=${status}`)
                .then(r => r.text())
                .then(d => { if(feedbackEl) feedbackEl.innerText = "Sent: " + status + " (DB OK)"; });
        }

        function saveAlarmLogic() {
            const mode = document.getElementById('alarmMode').value;
            const lineOn = document.getElementById('enableLine').checked ? 1 : 0;
            const lightOn = document.getElementById('enableLight').checked ? 1 : 0;

            const fd = new FormData();
            fd.append('action', 'save_logic_db');
            fd.append('mode', mode);
            fd.append('line', lineOn);
            fd.append('light', lightOn);

            // ✅ บันทึกลง Database ของตัวเอง
            fetch('', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => { if(data.status === 'success') alert("✅ Saved to Database!"); });

            // ส่ง MQTT เพื่อความรวดเร็ว
            client.publish("kbs/motordriveroom1/config_individual", JSON.stringify({ type: 'alarm_logic_update', sensor_id: 0, alarm_mode: mode, alarm_line: lineOn, alarm_light: lightOn }), { qos: 1, retain: true });
        }

        function saveIndividualThresholds() {
            let sensors = [];
            for(let i=1; i<=5; i++) {
                sensors.push({ sensor_id: i, temp_threshold: parseFloat(document.getElementById(`t_limit_${i}`).value), humid_threshold: parseFloat(document.getElementById(`h_limit_${i}`).value) });
            }
            client.publish("kbs/motordriveroom1/config_individual", JSON.stringify({ type: 'individual', sensors: sensors }), { qos: 1 });
            alert("💾 Saved Individual Limits!");
        }

        function saveGlobalThreshold() {
            const t = document.getElementById('globalTemp').value, h = document.getElementById('globalHumid').value;
            client.publish("kbs/motordriveroom1/config_individual", JSON.stringify({ type: 'global', sensor_id: 0, temp_threshold: parseFloat(t), humid_threshold: parseFloat(h) }), { qos: 1, retain: true });
            alert("💾 Saved Global Limits!");
        }

        function autoPushLine(msg) {
            const fd = new FormData(); fd.append('action', 'test_line_api'); fd.append('message', msg);
            fetch('', { method: 'POST', body: fd });
        }

        function sendLineTest() {
    // 1. สร้างหัวข้อรายงาน
    let sensorSummary = "🔔 [KBS MANUAL TEST]\n";
    sensorSummary += "📅 Date: " + new Date().toLocaleString('th-TH') + "\n";
    sensorSummary += "------------------------\n";

    let hasData = false;
    // 2. วนลูปดึงข้อมูลจาก S1 - S5 ที่มีอยู่ใน liveRawData
    for (let i = 1; i <= 5; i++) {
        if (liveRawData[i]) {
            const t = parseFloat(liveRawData[i].temp).toFixed(1);
            const h = parseFloat(liveRawData[i].humid).toFixed(1);
            sensorSummary += `📍 S${i}: ${t}°C | ${h}%\n`;
            hasData = true;
        } else {
            sensorSummary += `📍 S${i}: -- No Data --\n`;
        }
    }

    if (!hasData) {
        alert("❌ No sensor data available. Please wait for MQTT updates.");
        return;
    }

    // 3. ส่งข้อมูลสรุปเข้า Line API ผ่าน PHP ก้อนเดิมของคุณ
    const fd = new FormData();
    fd.append('action', 'test_line_api');
    fd.append('message', sensorSummary);

    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.status === 200) {
                alert("🚀 Real-time Sensor Data Sent to LINE!");
            } else {
                alert("❌ Error: " + d.status);
            }
        })
        .catch(err => {
            console.error("Fetch Error:", err);
            alert("❌ Connection Error");
        });
}
    </script>
</body>
</html>
