<?php
/**
 * motor_drive_room_settings.php - KBS Monitoring Settings
 */
$config = require 'config.php';
session_start();

// 1. ตรวจสอบการ Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

// 2. ตรวจสอบสิทธิ์ (เฉพาะ Admin)
if ($_SESSION['role'] !== 'admin') {
    header("Location: /motor_drive_room_dashboard");
    exit;
}

// 3. ดึงค่าล่าสุดจาก DATABASE (ตาราง threshold_logs) เพื่อนำมาโชว์ใน Input ทันที
$current_temp = 24.00; // ค่า Default
$current_humid = 60.00; // ค่า Default

try {
    // ดึงข้อมูลเชื่อมต่อจาก config หรือระบุตรงนี้
    $db_host = '127.0.0.1';
    $db_name = 'kbs_eng_db'; // *** เปลี่ยนเป็นชื่อ DB ของคุณ ***
    $db_user = 'kbs-ccsonline';               // *** เปลี่ยนเป็น User ของคุณ ***
    $db_pass = '@Kbs2024!#';                   // *** เปลี่ยนเป็น Password ของคุณ ***
    
    
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query หาแถวล่าสุดจากตาราง threshold_logs
    $stmt = $conn->query("SELECT temp_limit, humid_limit FROM threshold_logs ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $current_temp = (float)$row['temp_limit'];
        $current_humid = (float)$row['humid_limit'];
    }
} catch (PDOException $e) {
    // กรณีเชื่อมต่อไม่ได้จะใช้ค่า Default ด้านบน
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <title>Settings - KBS Monitoring</title>
    <link rel="icon" type="image/webp" href="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp">
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <style>
        :root {
            --card: #1e293b;
            --accent: #3b82f6;
            --bg: #0f172a;
            --input-bg: #0f172a;
        }
        
        .settings-card {
            background: var(--card);
            padding: 30px;
            border-radius: 15px;
            max-width: 550px;
            width: 100%;
            margin: 20px auto;
            border: 1px solid #334155;
        }

        .status-light-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            border: 1px solid #334155;
        }

        .lightbulb {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #475569; 
            transition: all 0.4s ease;
        }

        .bulb-normal { background-color: #22c55e; box-shadow: 0 0 20px rgba(34, 197, 94, 0.6); }
        .bulb-warning { background-color: #ef4444; box-shadow: 0 0 20px rgba(239, 68, 68, 0.8); animation: pulse-red 1.5s infinite; }

        @keyframes pulse-red { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }

        .control-row { padding: 20px 0; border-top: 1px solid #334155; }
        .flex-row { display: flex; justify-content: space-between; align-items: center; }

        .threshold-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .input-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 5px; }
        .input-group input { width: 100%; background: var(--input-bg); border: 1px solid #334155; color: #fff; padding: 10px; border-radius: 8px; outline: none; font-weight: bold; font-family: monospace; }

        .save-btn { width: 100%; background: var(--accent); color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: opacity 0.2s; }
        .save-btn:hover { opacity: 0.9; }

        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #334155; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #22c55e; }
        input:checked + .slider:before { transform: translateX(26px); }
        .connected { background-color: #22c55e !important; }
    </style>
</head>
<body class="app-container">
    <aside class="sidebar">
        <div class="sidebar-nav">
            <a href="/motor_drive_room_dashboard" class="nav-item">
                <span class="icon">📊</span><span class="text">Dashboard</span>
            </a>
            <a href="#" class="nav-item active">
                <span class="icon">⚙️</span><span class="text">Settings</span>
            </a>
        </div>
        <div style="padding: 20px; border-top: 1px solid #334155; margin-top: auto; color: #94a3b8; font-size: 0.8rem;">
            User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div style="font-weight:700; color: #fff;">Control Settings</div>
            <div class="status-indicator" style="display:flex; align-items:center; gap:8px;">
                <span id="mqttDot" class="dot" style="width:10px; height:10px; background:#475569; border-radius:50%;"></span>
                <span id="mqttStatus" style="color:#94a3b8; font-size:0.75rem; font-weight:bold;">OFFLINE</span>
            </div>
        </header>

        <div class="settings-card">
            <div class="status-light-container">
                <div id="statusBulb" class="lightbulb"></div>
                <div>
                    <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">System Health</div>
                    <div id="statusText" style="font-weight: 800; color: #fff; font-size: 1.1rem;">CONNECTING...</div>
                    <div style="font-size: 0.75rem; color: #64748b;">
                        Active Sensors: <span id="activeCount" style="color: #3b82f6; font-weight:bold;">0</span> / 5
                    </div>
                </div>
            </div>

            <div class="control-row">
                <h3 style="color: #fff; margin-bottom: 15px;">Threshold Settings</h3>
                <div class="threshold-grid">
                    <div class="input-group">
                        <label>Temperature Limit (°C)</label>
                        <input type="number" id="tempThreshold" value="<?php echo number_format($current_temp, 2); ?>" step="0.5">
                    </div>
                    <div class="input-group">
                        <label>Humidity Limit (%)</label>
                        <input type="number" id="humidThreshold" value="<?php echo number_format($current_humid, 0); ?>" step="1">
                    </div>
                </div>
                <button class="save-btn" onclick="saveThresholds()">Save to Node-RED & Database</button>
                <p id="saveStatus" style="font-size: 0.7rem; color: #22c55e; text-align: center; margin-top: 10px; display: none;">✔ Settings Saved & Synchronized</p>
            </div>

            <h3 style="margin: 20px 0 10px; color: #fff;">Remote Controls</h3>
            <div class="control-row flex-row">
                <div>
                    <div style="font-weight: bold; color: #fff;">System Light Control</div>
                    <div style="font-size: 0.75rem; color: #64748b;">Topic: kbs/driveroom1/light1</div>
                </div>
                <label class="switch">
                    <input type="checkbox" id="lightSwitch" onchange="publishLight(this.checked)">
                    <span class="slider"></span>
                </label>
            </div>
        </div>
    </main>

    <script>
        const MQTT_CONFIG = {
            url: '<?php echo $config["mqtt_ws_url"]; ?>',
            user: '<?php echo $config["mqtt_user"]; ?>',
            pass: '<?php echo $config["mqtt_pass"]; ?>'
        };

        const STATUS_TOPIC = "kbs/motordriveroom1/warning"; 
        const CONFIG_TOPIC = "kbs/motordriveroom1/config";

        const client = mqtt.connect(MQTT_CONFIG.url, { 
            username: MQTT_CONFIG.user, 
            password: MQTT_CONFIG.pass,
            clientId: 'KBS_Set_Page_' + Math.random().toString(16).substr(2, 4)
        });

        client.on('connect', () => {
            document.getElementById('mqttDot').classList.add('connected');
            document.getElementById('mqttStatus').innerText = "CONNECTED";
            client.subscribe(STATUS_TOPIC);
            client.subscribe(CONFIG_TOPIC); 
        });

        client.on('message', (topic, payload) => {
            const data = JSON.parse(payload.toString());

            if (topic === STATUS_TOPIC) {
                const bulb = document.getElementById('statusBulb');
                const txt = document.getElementById('statusText');
                document.getElementById('activeCount').innerText = data.active || 0;
                if (data.status === "Normal") {
                    bulb.className = "lightbulb bulb-normal";
                    txt.innerText = "SYSTEM NORMAL"; txt.style.color = "#22c55e";
                } else {
                    bulb.className = "lightbulb bulb-warning";
                    txt.innerText = "OVER THRESHOLD!"; txt.style.color = "#ef4444";
                }
            }
            
            // อัปเดตช่อง Input หากมีการเปลี่ยนแปลงจากที่อื่น
            else if (topic === CONFIG_TOPIC) {
                const tInput = document.getElementById('tempThreshold');
                const hInput = document.getElementById('humidThreshold');
                if (document.activeElement !== tInput) tInput.value = parseFloat(data.temp_limit).toFixed(2);
                if (document.activeElement !== hInput) hInput.value = parseFloat(data.humid_limit).toFixed(0);
            }
        });

        function saveThresholds() {
    const t = document.getElementById('tempThreshold').value;
    const h = document.getElementById('humidThreshold').value;
    
    // ตรวจสอบค่าว่างก่อนส่ง (Validation)
    if (!t || !h) {
        alert("กรุณากรอกข้อมูลให้ครบถ้วน");
        return;
    }

    const payload = JSON.stringify({
        temp_limit: parseFloat(t),
        humid_limit: parseFloat(h),
        updated_by: "<?php echo $_SESSION['username']; ?>"
    });

    // ส่ง MQTT แบบ Retain (ค่าล่าสุดจะไม่หายแม้ Broker Restart)
    client.publish(CONFIG_TOPIC, payload, { qos: 1, retain: true });
    
    // แสดงสถานะการบันทึก (แก้ไขตัวแปร statusMsg)
    const statusMsg = document.getElementById('saveStatus');
    statusMsg.style.display = 'block';
    
    // หน่วงเวลา 3 วินาทีแล้วค่อยซ่อน
    setTimeout(() => { 
        statusMsg.style.display = 'none'; 
    }, 3000);
}
        function publishLight(state) { client.publish("kbs/driveroom1/light1", state ? "Active" : "Unactive", { qos: 1, retain: true }); }
    </script>
</body>
</html>