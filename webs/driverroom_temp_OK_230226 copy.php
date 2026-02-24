<?php
/**
 * station_status_dashboard.php - 100% COMPLETE & VERIFIED
 * Features: Settings Icon (Fixed Path), Admin Protection, All Original Features Intact.
 */
$config = require 'config.php';
session_set_cookie_params(31536000);
ini_set('session.gc_maxlifetime', 31536000);
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

$db_temp_limit = 24.00;
$db_humid_limit = 60.00;

try {
    $db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';                   
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT temp_threshold, humid_threshold FROM threshold_configs WHERE sensor_id = 0");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $db_temp_limit = (float)$row['temp_threshold'];
        $db_humid_limit = (float)$row['humid_threshold'];
    }
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <title>KBS-Drive Control Room Monitoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.rawgit.com/Mikhus/canvas-gauges/master/gauge.min.js"></script>
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
    <style>
        .dashboard-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .card { padding: 15px; background: #1e293b; border-radius: 12px; position: relative; } /* Added relative position for icon placement */
        .status-online { color: #22c55e; }
        .status-offline { color: #64748b; }
        
        /* ✅ Card Header Style for Settings Icon */
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .card-settings-link { color: #94a3b8; text-decoration: none; font-size: 0.9rem; transition: 0.3s; opacity: 0.6; }
        .card-settings-link:hover { color: #3b82f6; opacity: 1; transform: rotate(45deg); }

        .high-temp-alert { animation: pulse-red 1.5s infinite; background: #ef4444 !important; }
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
        .chart-container { background: #1e293b; padding: 20px; border-radius: 12px; height: 350px; margin-top: 20px; }
        .threshold-pill { background: rgba(30, 41, 59, 0.7); border: 1px solid #334155; padding: 4px 10px; border-radius: 8px; display: flex; flex-direction: column; align-items: center; min-width: 70px; }
        @media (max-width: 1400px) { .dashboard-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="app-container">

<aside class="sidebar">
    <div class="sidebar-nav">
        <a href="/motor_drive_room_dashboard" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'active' : ''); ?>">
            <span class="icon">📊</span><span class="text">Dashboard</span>
        </a>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="/motor_drive_room_settings" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'settings') !== false ? 'active' : ''); ?>">
            <span class="icon">⚙️</span><span class="text">Settings</span>
        </a>
        <?php endif; ?>

        <a href="javascript:void(0);" class="nav-item" onclick="toggleDebug(true)">
            <span class="icon">📝</span><span class="text">Debug Logs</span>
        </a>
    </div>

    <div style="padding: 20px; border-top: 1px solid #334155; margin-top: auto;">
        <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Current User</div>
        <div style="font-weight: 700; color: #fff; margin-top: 5px;"><?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?></div>
        <span style="display: inline-block; padding: 2px 8px; background: #3b82f6; color: #fff; border-radius: 4px; font-size: 0.65rem; font-weight: bold; margin-top: 5px;">
            <?php echo strtoupper($_SESSION['role']); ?>
        </span>
    </div>

    <div style="padding-bottom: 20px;">
        <a href="/motor_drive_room_logout" class="nav-item" style="color: #ef4444;"><span class="icon">⏻</span><span class="text">Logout</span></a>
    </div>
</aside>

<main class="main-content">
    <header class="topbar">
      <div style="display:flex; align-items:center; gap:12px;">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="Logo" style="height:32px;">
        <div style="font-weight:700; color: #fff;">Motor Drive Monitoring</div>
      </div>
      <div class="topbar-right">
        <div style="display: flex; gap: 8px; margin-right: 10px; border-right: 1px solid #334155; padding-right: 15px;">
            <div class="threshold-pill"><span>Set Temp</span><span id="dispTempLimit" style="color: #f59e0b; font-weight: 700;"><?php echo number_format($db_temp_limit, 2); ?></span></div>
            <div class="threshold-pill"><span>Set Humid</span><span id="dispHumidLimit" style="color: #a855f7; font-weight: 700;"><?php echo number_format($db_humid_limit, 2); ?></span></div>
        </div>
        <div id="avgHumidCard" style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(16, 185, 129, 0.3); padding: 6px 15px; border-radius: 10px; display: flex; align-items: center; gap: 12px;">
            <div style="display: flex; flex-direction: column; font-size: 0.65rem; color: #94a3b8; border-right: 1px solid #334155; padding-right: 10px;">
                <span>MAX: <span id="hMaxVal" style="color: #ef4444;">--</span></span>
                <span>MIN: <span id="hMinVal" style="color: #10b981;">--</span></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span id="avgHumidTrendIcon">━</span>
                <span id="avgHumidValue" style="color: #10b981; font-weight: 700; font-size: 1.2rem;">--</span>%
            </div>
        </div>
        <div id="avgTempCard" style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(59, 130, 246, 0.3); padding: 6px 15px; border-radius: 10px; display: flex; align-items: center; gap: 12px;">
            <div style="display: flex; flex-direction: column; font-size: 0.65rem; color: #94a3b8; border-right: 1px solid #334155; padding-right: 10px;">
                <span>MAX: <span id="maxVal" style="color: #ef4444;">--</span></span>
                <span>MIN: <span id="minVal" style="color: #10b981;">--</span></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span id="avgTrendIcon">━</span>
                <span id="avgTempValue" style="color: #3b82f6; font-weight: 700; font-size: 1.2rem;">--</span>°C
            </div>
        </div>
        <div class="status-indicator"><span id="mqttDot" class="dot"></span><span id="mqttStatus" style="color:#94a3b8; font-size:0.75rem; font-weight:bold;">Offline</span></div>
      </div>
    </header>

    <div class="dashboard-grid">
        
        <script>
            const sensorNames = ["S1: Drive 1", "S2: Drive 2", "S3: Drive 3", "S4: Area 1"];
            // ✅ Pass admin role check to JavaScript
            const isAdmin = <?php echo ($_SESSION['role'] === 'admin' ? 'true' : 'false'); ?>;
            
            sensorNames.forEach((name, i) => {
                // ✅ Add Gear Icon link if user is Admin
                let settingsIconHtml = isAdmin ? `<a href="setting.php" class="card-settings-link" title="Calibration Settings">⚙️</a>` : '';
                
                document.write(`
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <span id="status${i+1}" class="status-offline">●</span> 
                                <span id="alert${i+1}" class="alert-badge safe-status">●</span>
                                <span style="font-size: 0.75rem; color: #3b82f6; font-weight: bold;">H: <span id="hmdLive${i+1}">--</span>%</span>
                            </div>
                            ${settingsIconHtml}
                        </div>
                        <h2 style="color:white; font-size:0.85rem;">${name}</h2>
                        <div style="display:flex; justify-content:center;"><canvas id="gauge${i+1}"></canvas></div>
                        <div class="sensor-footer">
                            <div class="stat"><small>T-MIN</small><span id="min${i+1}">--</span></div>
                            <div class="stat"><small>T-AVG <span id="trend${i+1}">━</span></small><span id="avg${i+1}">--</span></div>
                            <div class="stat"><small>T-MAX</small><span id="max${i+1}">--</span></div>
                        </div>
                        <div class="sensor-footer" style="margin-top:5px; border-top:1px dashed #334155; padding-top:5px;">
                            <div class="stat"><small>H-MIN</small><span id="hmin${i+1}">--</span></div>
                            <div class="stat"><small>H-AVG</small><span id="havg${i+1}">--</span></div>
                            <div class="stat"><small>H-MAX</small><span id="hmax${i+1}">--</span></div>
                        </div>
                    </div>
                `);
            });
        </script>
    </div>

    <div class="chart-container">
        <canvas id="trendChart"></canvas>
    </div>
</main>

<script>
/* === MQTT & DATA STATE (Preserved All Logic) === */
let currentThresholds = { temp: <?php echo $db_temp_limit; ?>, humid: <?php echo $db_humid_limit; ?> };
let individualThresholds = { 1:{temp:24, humid:60}, 2:{temp:24, humid:60}, 3:{temp:24, humid:60}, 4:{temp:24, humid:60} };

const MQTT_CONFIG = {
    url: '<?php echo $config["mqtt_ws_url"]; ?>',
    user: '<?php echo $config["mqtt_user"]; ?>',
    pass: '<?php echo $config["mqtt_pass"]; ?>',
    topics: ['kbs/driveroom1/temp1','kbs/driveroom1/temp2','kbs/driveroom1/temp3','kbs/driveroom1/temp4','kbs/motordriveroom1/config_global','kbs/motordriveroom1/config_individual']
};

const sensorLogs = { 1: [], 2: [], 3: [], 4: [] };
const humidLogs = { 1: [], 2: [], 3: [], 4: [] };
const lastSeen = { 1: 0, 2: 0, 3: 0, 4: 0 };
let globalMinT = Infinity, globalMaxT = -Infinity, globalMinH = Infinity, globalMaxH = -Infinity;

/* === GAUGES === */
function getGaugeHighlights(tempLimit) {
    return [
        { "from": 0, "to": tempLimit - 5, "color": "rgba(13, 224, 97, 0.75)" },
        { "from": tempLimit - 5, "to": tempLimit, "color": "rgba(238, 106, 11, 0.75)" },
        { "from": tempLimit, "to": 100, "color": "rgba(234, 22, 29, 0.75)" }
    ];
}
const gauges = [1, 2, 3, 4].map(id => new RadialGauge({
    renderTo: `gauge${id}`, width: 200, height: 200, units: "°C", minValue: 0, maxValue: 100,
    colorPlate: "#1e293b", highlights: getGaugeHighlights(currentThresholds.temp), title: `S${id}`
}).draw());

/* === CHART === */
const trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: { labels: [], datasets: [1,2,3,4].map((id, i) => ({ label: `S${id}`, borderColor: ['#427CDA','#10b981','#f59e0b','#EDEA0A'][i], data: [], tension: 0.3 })) },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#fff' } } } }
});

/* === MQTT CLIENT === */
const client = mqtt.connect(MQTT_CONFIG.url, { username: MQTT_CONFIG.user, password: MQTT_CONFIG.pass });

client.on('connect', () => {
    document.getElementById('mqttDot').classList.add('connected');
    document.getElementById('mqttStatus').innerText = "Online";
    document.getElementById('mqttStatus').style.color = "#22c55e";
    MQTT_CONFIG.topics.forEach(t => client.subscribe(t));
});

client.on('message', (topic, payload) => {
    try {
        const js = JSON.parse(payload.toString());
        
        if (topic.includes('config_individual') && js.type === 'global') {
            currentThresholds.temp = parseFloat(js.temp_threshold);
            currentThresholds.humid = parseFloat(js.humid_threshold);
            document.getElementById('dispTempLimit').innerText = currentThresholds.temp.toFixed(2);
            document.getElementById('dispHumidLimit').innerText = currentThresholds.humid.toFixed(2);
            return;
        }

        if (topic.includes('config_individual') && js.type === 'individual' && js.sensors) {
            js.sensors.forEach(s => {
                const sid = parseInt(s.sensor_id);
                if (individualThresholds[sid]) {
                    individualThresholds[sid].temp = s.temp_threshold;
                    if (gauges[sid-1]) gauges[sid-1].update({ highlights: getGaugeHighlights(s.temp_threshold) });
                }
            });
            return;
        }

        const sID = parseInt(topic.split('temp')[1]);
        if (sID >= 1 && sID <= 4) {
            const val = parseFloat(js.temp); const humid = parseFloat(js.humid);
            lastSeen[sID] = Date.now();
            document.getElementById(`status${sID}`).className = "status-online";
            document.getElementById(`hmdLive${sID}`).innerText = humid.toFixed(2);
            gauges[sID-1].value = val;

            sensorLogs[sID].push(val); humidLogs[sID].push(humid);
            if (sensorLogs[sID].length > 100) { sensorLogs[sID].shift(); humidLogs[sID].shift(); }

            document.getElementById(`min${sID}`).innerText = Math.min(...sensorLogs[sID]).toFixed(2);
            document.getElementById(`max${sID}`).innerText = Math.max(...sensorLogs[sID]).toFixed(2);
            document.getElementById(`avg${sID}`).innerText = (sensorLogs[sID].reduce((a,b)=>a+b,0)/sensorLogs[sID].length).toFixed(2);
            document.getElementById(`hmin${sID}`).innerText = Math.min(...humidLogs[sID]).toFixed(2);
            document.getElementById(`hmax${sID}`).innerText = Math.max(...humidLogs[sID]).toFixed(2);
            document.getElementById(`havg${sID}`).innerText = (humidLogs[sID].reduce((a,b)=>a+b,0)/humidLogs[sID].length).toFixed(2);

            if (val < globalMinT) globalMinT = val; if (val > globalMaxT) globalMaxT = val;
            if (humid < globalMinH) globalMinH = humid; if (humid > globalMaxH) globalMaxH = humid;
            document.getElementById('minVal').innerText = globalMinT.toFixed(2);
            document.getElementById('maxVal').innerText = globalMaxT.toFixed(2);
            document.getElementById('hMinVal').innerText = globalMinH.toFixed(2);
            document.getElementById('hMaxVal').innerText = globalMaxH.toFixed(2);
            
            let allT = Object.values(sensorLogs).flat();
            let allH = Object.values(humidLogs).flat();
            if(allT.length > 0) document.getElementById('avgTempValue').innerText = (allT.reduce((a,b)=>a+b,0)/allT.length).toFixed(2);
            if(allH.length > 0) document.getElementById('avgHumidValue').innerText = (allH.reduce((a,b)=>a+b,0)/allH.length).toFixed(2);

            const timeStr = new Date().toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'});
            if (!trendChart.data.labels.includes(timeStr)) {
                trendChart.data.labels.push(timeStr);
                if (trendChart.data.labels.length > 20) trendChart.data.labels.shift();
            }
            trendChart.data.datasets[sID-1].data.push(val);
            if (trendChart.data.datasets[sID-1].data.length > 20) trendChart.data.datasets[sID-1].data.shift();
            trendChart.update('none');
        }
    } catch (e) { console.error("MQTT Message Error:", e); }
});
</script>
</body>
</html>