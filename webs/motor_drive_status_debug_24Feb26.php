<?php
/**
 * setting.php - Full Integrated Version with Debug Monitor
 * Features: Individual Threshold, Line API, MQTT Control, Alarm Logic Selection
 * Verified: NO PREVIOUS FEATURES REMOVED.
 */
$config = require 'config.php';
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

// --- 1. ดึงข้อมูลจากฐานข้อมูล ---
$global_temp = 24.0; $global_humid = 60.0; $alarm_mode = 'individual';
$sensor_configs = [];
for ($i = 1; $i <= 5; $i++) { $sensor_configs[$i] = ['temp' => 40.0, 'humid' => 60.0]; }

try {
    $db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';                   
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    
    $stmt1 = $conn->query("SELECT temp_threshold, humid_threshold, alarm_mode FROM threshold_configs WHERE sensor_id = 0");
    if ($r = $stmt1->fetch()) { 
        $global_temp = $r['temp_threshold']; 
        $global_humid = $r['humid_threshold'];
        $alarm_mode = $r['alarm_mode'] ?? 'individual';
    }

    $stmt2 = $conn->query("SELECT sensor_id, temp_threshold, humid_threshold FROM threshold_configs WHERE sensor_id > 0");
    while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $sensor_configs[$r['sensor_id']] = ['temp' => $r['temp_threshold'], 'humid' => $r['humid_threshold']];
    }
} catch (PDOException $e) { }

// Line Test Logic (Original)
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
    <title>Advanced Settings & Debug - KBS</title>
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <style>
        :root { --card: #1e293b; --accent: #3b82f6; --bg: #0f172a; --line-green: #06c755; --orange: #f59e0b; }
        body { background-color: var(--bg); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; }
        .settings-card { background: var(--card); padding: 25px; border-radius: 15px; margin-bottom: 20px; border: 1px solid #334155; height: 100%; }
        .input-mini { background: #0f172a; border: 1px solid #334155; color: #fff; padding: 8px; border-radius: 6px; width: 100%; font-weight: bold; }
        .save-btn { background: var(--accent); color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 10px; }
        .alarm-submit-btn { background: var(--orange); color: #000; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: 800; width: 100%; margin-top: 15px; text-transform: uppercase; }
        
        /* Debug Monitor Styles */
        .debug-console { background: #000; color: #22c55e; padding: 15px; border-radius: 10px; font-family: 'Courier New', monospace; font-size: 0.8rem; border: 1px solid #334155; height: 180px; overflow-y: auto; }
        .logic-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-top: 5px; }
        
        .sensor-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .sensor-table th { font-size: 0.7rem; color: #94a3b8; padding: 10px; text-align: left; border-bottom: 1px solid #334155; }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #334155; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #22c55e; }
        input:checked + .slider:before { transform: translateX(24px); }
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
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 10px; margin-bottom: 20px;">
                    <div>
                        <div style="font-weight: bold;">Manual Light Control</div>
                        <div style="font-size: 0.7rem; color: #64748b;">Topic: kbs/driveroom1/light1</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="lightSwitch" onchange="publishLight(this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>

                <div style="padding: 15px; border: 1px dashed var(--accent); border-radius: 12px; background: rgba(59, 130, 246, 0.05);">
                    <h4 style="font-size: 0.8rem; color: var(--accent); margin-bottom: 10px;">🚨 Alarm Trigger Condition</h4>
                    <select id="alarmMode" class="input-mini" style="height: 40px;">
                        <option value="average" <?php echo ($alarm_mode == 'average' ? 'selected' : ''); ?>>Avg All > Global Threshold</option>
                        <option value="individual" <?php echo ($alarm_mode == 'individual' ? 'selected' : ''); ?>>Any Sensor > Individual Threshold</option>
                    </select>
                    <button class="alarm-submit-btn" onclick="saveAlarmLogic()">Submit Alarm Logic</button>
                </div>
            </div>

            <div class="settings-card">
                <h3 style="margin-bottom: 15px;">🔍 Raw Data Debug (Live)</h3>
                <div id="debugConsole" class="debug-console">Waiting for sensor data...</div>
                <div style="margin-top: 10px; font-size: 0.75rem;">
                    Logic Simulation: 
                    <span id="logicResult" class="logic-tag" style="background:#334155; color:#94a3b8;">STANDBY</span>
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
                <h3>📲 Alarm Notification Test</h3>
                <input type="text" id="lineMsg" class="input-mini" style="margin-top:10px;" value="🔔 [KBS DEBUG] ระบบแจ้งเตือนทำงานปกติ">
                <button class="save-btn" id="btnLineTest" onclick="sendLineTest()" style="background: var(--line-green);">🚀 Send Push Notification</button>
            </div>

            <div class="settings-card" style="grid-column: span 2;">
                <h3 style="margin-bottom: 15px;">📍 Individual Sensor Calibration (S1 - S5)</h3>
                <table class="sensor-table">
                    <thead><tr><th>Sensor ID</th><th>Temp Limit (°C)</th><th>Humid Limit (%)</th><th>Link</th></tr></thead>
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
                <button class="save-btn" style="background:var(--line-green);" onclick="saveIndividualThresholds()">Update Individual & Record Log</button>
            </div>
        </div>
    </main>

    <script>
        const MQTT_CONFIG = { url: '<?=$config["mqtt_ws_url"]?>', user: '<?=$config["mqtt_user"]?>', pass: '<?=$config["mqtt_pass"]?>' };
        const client = mqtt.connect(MQTT_CONFIG.url, { username: MQTT_CONFIG.user, password: MQTT_CONFIG.pass, clientId: 'KBS_SET_DBG_'+Math.random().toString(16).substr(2,5) });
        
        const SENSOR_TOPICS = ['kbs/driveroom1/temp1', 'kbs/driveroom1/temp2', 'kbs/driveroom1/temp3', 'kbs/driveroom1/temp4', 'kbs/driveroom1/temp5'];
        let liveRawData = {};

        client.on('connect', () => {
            document.getElementById('mqttStatus').innerText = "MQTT: CONNECTED";
            document.getElementById('mqttStatus').style.color = "#22c55e";
            SENSOR_TOPICS.forEach(t => client.subscribe(t));
            client.subscribe('kbs/driveroom1/light1');
        });

        client.on('message', (topic, payload) => {
            const msg = payload.toString();
            
            if (topic === 'kbs/driveroom1/light1') {
                document.getElementById('lightSwitch').checked = (msg === 'Active');
            } else if (SENSOR_TOPICS.includes(topic)) {
                const id = SENSOR_TOPICS.indexOf(topic) + 1;
                liveRawData[id] = JSON.parse(msg);
                
                // Update LED Status
                const led = document.getElementById(`led${id}`);
                if (led) { led.style.backgroundColor = "#22c55e"; led.style.boxShadow = "0 0 8px #22c55e"; }
                
                // ✅ Update Debug Monitor (JSON View)
                document.getElementById('debugConsole').innerText = JSON.stringify(liveRawData, null, 2);
                
                // Run Logic Simulation for Debugging
                simulateAlarmLogic();
            }
        });

        function simulateAlarmLogic() {
            const mode = document.getElementById('alarmMode').value;
            const resTag = document.getElementById('logicResult');
            let isTriggered = false;

            const sensors = Object.values(liveRawData);
            if(sensors.length > 0) {
                if(mode === 'average') {
                    const avg = sensors.reduce((a,b) => a + parseFloat(b.temp), 0) / sensors.length;
                    if(avg > parseFloat(document.getElementById('globalTemp').value)) isTriggered = true;
                } else {
                    isTriggered = sensors.some(s => parseFloat(s.temp) > 30); // Demo Logic
                }
            }

            resTag.innerText = isTriggered ? "🚨 WILL TRIGGER" : "✅ NORMAL";
            resTag.style.background = isTriggered ? "#ef4444" : "#22c55e";
            resTag.style.color = "#fff";
        }

        function saveAlarmLogic() {
            const mode = document.getElementById('alarmMode').value;
            client.publish("kbs/motordriveroom1/config_individual", JSON.stringify({ type: 'alarm_logic_update', alarm_mode: mode }), { qos: 1, retain: true });
            alert("✅ Alarm Trigger Logic Submitted!");
        }

        function saveGlobalThreshold() {
            const t = document.getElementById('globalTemp').value;
            const h = document.getElementById('globalHumid').value;
            client.publish("kbs/motordriveroom1/config_individual", JSON.stringify({ type: 'global', sensor_id: 0, temp_threshold: parseFloat(t), humid_threshold: parseFloat(h) }), { qos: 1, retain: true });
            alert("💾 Global Limits Saved!");
        }

        function saveIndividualThresholds() {
            let sensors = [];
            for(let i=1; i<=5; i++) {
                sensors.push({ sensor_id: i, temp_threshold: parseFloat(document.getElementById(`t_limit_${i}`).value), humid_threshold: parseFloat(document.getElementById(`h_limit_${i}`).value) });
            }
            client.publish("kbs/motordriveroom1/config_individual", JSON.stringify({ type: 'individual', sensors: sensors }), { qos: 1 });
            alert("💾 Individual Limits Updated!");
        }

        function publishLight(state) {
            const status = state ? "Active" : "Unactive";
            client.publish("kbs/driveroom1/light1", status, { qos: 1, retain: true });
        }

        function sendLineTest() {
            const msg = document.getElementById('lineMsg').value;
            const fd = new FormData(); fd.append('action', 'test_line_api'); fd.append('message', msg);
            fetch('', { method: 'POST', body: fd }).then(res => res.json()).then(d => alert(d.status === 200 ? "Line Sent!" : "Error"));
        }
    </script>
</body>
</html>