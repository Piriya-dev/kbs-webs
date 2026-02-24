<?php
/**
 * station_status_dashboard.php - Full Features Restored with 4-Card Row Layout
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
    $db_host = '127.0.0.1';
    $db_name = 'kbs_eng_db'; 
    $db_user = 'kbs-ccsonline';               
    $db_pass = '@Kbs2024!#';                   
    
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->query("SELECT temp_limit, humid_limit FROM threshold_logs ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $db_temp_limit = (float)$row['temp_limit'];
        $db_humid_limit = (float)$row['humid_limit'];
    }
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <link rel="icon" type="image/webp" href="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp">
    <title>KBS-IT Server Room Temp Monitoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.rawgit.com/Mikhus/canvas-gauges/master/gauge.min.js"></script>
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
    <style>
        /* จัดวาง 4 Gauge ในแถวเดียว */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .card { padding: 15px; min-width: 0; }
        .card h2 { font-size: 0.9rem; margin-bottom: 10px; color: #fff; font-weight: 700; }
        
        .status-online { color: #22c55e; text-shadow: 0 0 8px rgba(34, 197, 94, 0.8); }
        .status-offline { color: #64748b; }
        .high-temp-alert { animation: pulse-red 1.5s infinite; background: #ef4444 !important; color: white !important; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; font-size: 12px; }
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
        .safe-status { color: #10b981; }
        .threshold-pill { background: rgba(30, 41, 59, 0.7); border: 1px solid #334155; padding: 4px 10px; border-radius: 8px; display: flex; flex-direction: column; align-items: center; min-width: 70px; }
        
        @media (max-width: 1400px) { .dashboard-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="app-container">

<aside class="sidebar">
    <div class="sidebar-nav">
        <a href="/motor_drive_room_dashboard" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false) ? 'active' : ''; ?>">
            <span class="icon">📊</span><span class="text">Dashboard</span>
        </a>

        <a href="/motor_drive_room_report" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'report') !== false) ? 'active' : ''; ?>">
            <span class="icon">📈</span><span class="text">Historical Report</span>
        </a>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="/motor_drive_room_settings" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'settings') !== false) ? 'active' : ''; ?>">
            <span class="icon">⚙️</span><span class="text">Settings</span>
        </a>
        <a href="/motor_drive_room_logs" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'logs') !== false) ? 'active' : ''; ?>">
            <span class="icon">📝</span><span class="text">Access Logs</span>
        </a>
        <?php endif; ?>

        <a href="javascript:void(0);" class="nav-item" onclick="toggleDebug(true)">
            <span class="icon">📝</span><span class="text">Debug Logs</span>
        </a>
    </div>

    <div style="padding: 20px; border-top: 1px solid #334155; margin-top: auto;">
        <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Current User</div>
        <div style="font-weight: 700; color: #fff; margin-top: 5px;"><?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?></div>
        <span style="display: inline-block; padding: 2px 8px; background: #3b82f6; color: #fff; border-radius: 4px; font-size: 0.65rem; font-weight: bold; margin-top: 5px;"><?php echo strtoupper($_SESSION['role']); ?></span>
    </div>

    <div style="padding-bottom: 20px;">
        <a href="/motor_drive_room_logout" class="nav-item" style="color: #ef4444;"><span class="icon">⏻</span><span class="text">Logout</span></a>
    </div>
</aside>

<main class="main-content">
    <header class="topbar">
      <div style="display:flex; align-items:center; gap:12px;">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="Logo" style="height:32px;">
        <div><div style="font-weight:700; color: #fff; font-size: 1rem;">IT Server Room Temp Monitoring</div><div style="font-size: 0.65rem; color: #64748b;">Designed & Created by IT Team</div></div>
      </div>

      <div class="topbar-right">
        <div style="display: flex; gap: 8px; margin-right: 10px; border-right: 1px solid #334155; padding-right: 15px;">
            <div class="threshold-pill">
                <span style="font-size: 0.55rem; color: #94a3b8;">Set Temp</span>
                <span id="dispTempLimit" style="color: #f59e0b; font-weight: 700;"><?php echo number_format($db_temp_limit, 2); ?></span>
            </div>
            <div class="threshold-pill">
                <span style="font-size: 0.55rem; color: #94a3b8;">Set Humid</span>
                <span id="dispHumidLimit" style="color: #a855f7; font-weight: 700;"><?php echo number_format($db_humid_limit, 2); ?></span>
            </div>
        </div>

        <div class="clock-section">
            <div id="liveDate" style="font-size: 0.65rem; color: #94a3b8;">-- --- ----</div>
            <div id="liveClock" style="font-size: 1.1rem; color: #fff; font-weight: 700; font-family: monospace;">00:00:00</div>
        </div>
        
        <div id="avgHumidCard" style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(16, 185, 129, 0.3); padding: 6px 15px; border-radius: 10px; display: flex; align-items: center; gap: 12px;">
            <div style="display: flex; flex-direction: column; font-size: 0.65rem; color: #94a3b8; border-right: 1px solid #334155; padding-right: 10px;">
                <span>MAX: <span id="hMaxVal" style="color: #ef4444; font-weight:bold;">--</span></span>
                <span>MIN: <span id="hMinVal" style="color: #10b981; font-weight:bold;">--</span></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span id="avgHumidTrendIcon" style="font-size: 1rem; font-weight: bold;">━</span>
                <span id="avgHumidValue" style="color: #10b981; font-weight: 700; font-size: 1.2rem;">--</span><span style="color: #10b981;">%</span>
            </div>
        </div>

        <div id="avgTempCard" style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(59, 130, 246, 0.3); padding: 6px 15px; border-radius: 10px; display: flex; align-items: center; gap: 12px;">
            <div style="display: flex; flex-direction: column; font-size: 0.65rem; color: #94a3b8; border-right: 1px solid #334155; padding-right: 10px;">
                <span>MAX: <span id="maxVal" style="color: #ef4444; font-weight:bold;">--</span></span>
                <span>MIN: <span id="minVal" style="color: #10b981; font-weight:bold;">--</span></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span id="avgTrendIcon" style="font-size: 1rem; font-weight: bold;">━</span>
                <span id="avgTempValue" style="color: #3b82f6; font-weight: 700; font-size: 1.2rem;">--</span><span style="color: #3b82f6;">°C</span>
            </div>
        </div>

        <div class="status-indicator"><span id="mqttDot" class="dot"></span><span id="mqttStatus" style="color:#94a3b8; font-size:0.75rem; font-weight:bold;">Offline</span></div>
      </div>
    </header>

    <div class="dashboard-grid">
        <script>
            const sensorNames = ["S1: Drive 1", "S2: Drive 2", "S3: Drive 3", "S4: Area 1"];
            sensorNames.forEach((name, i) => {
                document.write(`
                    <div class="card">
                        <div class="card-header">
                            <span id="status${i+1}" class="status-offline">●</span> 
                            <span id="alert${i+1}" class="alert-badge safe-status">●</span>
                            <span style="font-size: 0.75rem; color: #3b82f6; font-weight: bold;">H: <span id="hmdLive${i+1}">--</span>%</span>
                        </div>
                        <h2>${name}</h2>
                        <div style="display:flex; justify-content:center;"><canvas id="gauge${i+1}"></canvas></div>
                        <div class="sensor-footer">
                            <div class="stat"><small>T-MIN</small><span id="min${i+1}" style="color:#10b981">--</span></div>
                            <div class="stat"><small>T-AVG <span id="trend${i+1}">━</span></small><span id="avg${i+1}" style="color:#3b82f6">--</span></div>
                            <div class="stat"><small>T-MAX</small><span id="max${i+1}" style="color:#ef4444">--</span></div>
                        </div>
                        <div class="sensor-footer" style="margin-top: 5px; border-top: 1px dashed #334155; padding-top: 5px;">
                            <div class="stat"><small>H-MIN</small><span id="hmin${i+1}" style="color:#10b981">--</span></div>
                            <div class="stat"><small>H-AVG</small><span id="havg${i+1}" style="color:#3b82f6">--</span></div>
                            <div class="stat"><small>H-MAX</small><span id="hmax${i+1}" style="color:#ef4444">--</span></div>
                        </div>
                    </div>
                `);
            });
        </script>
    </div>

    <div class="chart-container" style="position: relative;">
        <div style="position: absolute; top: 15px; right: 20px; z-index: 10;">
            <select id="timeRange" onchange="filterChart()" style="background: #0f172a; color: #fff; border: 1px solid #334155; padding: 5px; border-radius: 5px; font-size: 12px;">
                <option value="1">Last 1 Hour</option><option value="6">Last 6 Hours</option><option value="24" selected>Last 24 Hours</option>
            </select>
        </div>
        <canvas id="trendChart"></canvas>
    </div>
</main>

<div id="debugOverlay">
    <div class="debug-window">
        <button class="btn-debug" style="color:#ef4444; border-color:#ef4444" onclick="toggleDebug(false)">CLOSE [X]</button>
        <div id="jsonFeed" style="margin-top:15px; font-family: monospace; font-size: 12px;">Waiting...</div>
    </div>
</div>

<script>
/* === MQTT & DATA STATE === */
let currentThresholds = { temp: <?php echo $db_temp_limit; ?>, humid: <?php echo $db_humid_limit; ?> };
const MQTT_CONFIG = {
    url: '<?php echo $config["mqtt_ws_url"]; ?>',
    user: '<?php echo $config["mqtt_user"]; ?>',
    pass: '<?php echo $config["mqtt_pass"]; ?>',
    topics: ['kbs/driveroom1/temp1', 'kbs/driveroom1/temp2', 'kbs/driveroom1/temp3', 'kbs/driveroom1/temp4', 'kbs/motordriveroom1/config']
};

const liveState = { 1: null, 2: null, 3: null, 4: null };
const sensorLogs = { 1: [], 2: [], 3: [], 4: [] };
const humidLogs = { 1: [], 2: [], 3: [], 4: [] }; 
const lastSeen = { 1: 0, 2: 0, 3: 0, 4: 0 }; 
let previousAverage = null; let previousHumidAverage = null;
let globalMinT = Infinity; let globalMaxT = -Infinity;
let globalMinH = Infinity; let globalMaxH = -Infinity;
const fullHistory = { labels: [], s1: [], s2: [], s3: [], s4: [], lastMinute: null };

/* === GAUGES === */
function getGaugeHighlights(tempLimit) {
    return [
        { "from": 0, "to": tempLimit - 5, "color": "rgba(13, 224, 97, 0.75)" },
        { "from": tempLimit - 5, "to": tempLimit, "color": "rgba(238, 106, 11, 0.75)" },
        { "from": tempLimit, "to": 100, "color": "rgba(234, 22, 29, 0.75)" }
    ];
}

const gaugeOptions = {
    width: 200, height: 200, units: "°C", minValue: 0, maxValue: 100,
    colorPlate: "#1e293b", colorNumbers: "#eee", colorUnits: "#ccc",
    highlights: getGaugeHighlights(currentThresholds.temp),
    animationDuration: 1000, animationRule: "linear"
};

const gauges = [
    new RadialGauge({ ...gaugeOptions, renderTo: 'gauge1', title: 'S1' }).draw(),
    new RadialGauge({ ...gaugeOptions, renderTo: 'gauge2', title: 'S2' }).draw(),
    new RadialGauge({ ...gaugeOptions, renderTo: 'gauge3', title: 'S3' }).draw(),
    new RadialGauge({ ...gaugeOptions, renderTo: 'gauge4', title: 'S4' }).draw()
];

/* === CHART === */
const trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: { labels: [], datasets: [
        { label: 'S1', borderColor: '#427CDA', data: [], tension: 0.3, pointRadius: 0 },
        { label: 'S2', borderColor: '#10b981', data: [], tension: 0.3, pointRadius: 0 },
        { label: 'S3', borderColor: '#f59e0b', data: [], tension: 0.3, pointRadius: 0 },
        { label: 'S4', borderColor: '#EDEA0A', data: [], tension: 0.3, pointRadius: 0 }
    ]},
    options: { responsive: true, maintainAspectRatio: false, scales: { x: { grid:{color:'#334155'}, ticks:{color:'#94a3b8'} }, y: { grid:{color:'#334155'}, ticks:{color:'#94a3b8'}, min:0, max:100 } }, plugins: { legend:{labels:{color:'#fff'}} } }
});

/* === LOGIC FUNCTIONS (ALL RESTORED) === */
function updateRealtimeAverage() {
    let allStoredTemps = Object.values(sensorLogs).flat();
    if (allStoredTemps.length > 0) {
        document.getElementById('minVal').innerText = globalMinT.toFixed(2);
        document.getElementById('maxVal').innerText = globalMaxT.toFixed(2);
        const currentAvgT = allStoredTemps.reduce((a, b) => a + b, 0) / allStoredTemps.length;
        document.getElementById('avgTempValue').innerText = currentAvgT.toFixed(2);
        if (previousAverage !== null) {
            const diff = currentAvgT - previousAverage;
            const trendEl = document.getElementById('avgTrendIcon');
            if (diff > 0.001) { trendEl.innerText = "▲"; trendEl.style.color = "#ef4444"; }
            else if (diff < -0.001) { trendEl.innerText = "▼"; trendEl.style.color = "#22c55e"; }
            else trendEl.innerText = "━";
        }
        previousAverage = currentAvgT;
    }
    
    let allStoredHumid = Object.values(humidLogs).flat();
    if (allStoredHumid.length > 0) {
        document.getElementById('hMinVal').innerText = globalMinH.toFixed(2);
        document.getElementById('hMaxVal').innerText = globalMaxH.toFixed(2);
        const currentAvgH = allStoredHumid.reduce((a, b) => a + b, 0) / allStoredHumid.length;
        document.getElementById('avgHumidValue').innerText = currentAvgH.toFixed(2);
        if (previousHumidAverage !== null) {
            const diffH = currentAvgH - previousHumidAverage;
            const hTrendEl = document.getElementById('avgHumidTrendIcon');
            if (diffH > 0.001) { hTrendEl.innerText = "▲"; hTrendEl.style.color = "#ef4444"; }
            else if (diffH < -0.001) { hTrendEl.innerText = "▼"; hTrendEl.style.color = "#22c55e"; }
            else hTrendEl.innerText = "━";
        }
        previousHumidAverage = currentAvgH;
    }
}

function updateChart(idx, val, date) {
    const curMin = `${date.getHours()}:${date.getMinutes()}`;
    if (fullHistory.lastMinute !== curMin) {
        fullHistory.labels.push(date);
        ['s1','s2','s3','s4'].forEach((k, i) => fullHistory[k].push(i === idx ? val : (fullHistory[k].slice(-1)[0] || null)));
        fullHistory.lastMinute = curMin;
    } else {
        const keys = ['s1','s2','s3','s4'];
        fullHistory[keys[idx]][fullHistory.labels.length - 1] = val;
    }
    filterChart();
}

function filterChart() {
    const hrs = parseInt(document.getElementById('timeRange').value);
    const cutoff = new Date().getTime() - (hrs * 3600000);
    const startIdx = fullHistory.labels.findIndex(d => d.getTime() >= cutoff);
    if (startIdx !== -1) {
        trendChart.data.labels = fullHistory.labels.slice(startIdx).map(d => `${d.getHours()}:${d.getMinutes().toString().padStart(2,'0')}`);
        ['s1','s2','s3','s4'].forEach((k, i) => trendChart.data.datasets[i].data = fullHistory[k].slice(startIdx));
    }
    trendChart.update('none');
}

/* === MQTT CLIENT === */
const client = mqtt.connect(MQTT_CONFIG.url, { username: MQTT_CONFIG.user, password: MQTT_CONFIG.pass });

client.on('connect', () => {
    document.getElementById('mqttDot').classList.add('connected');
    document.getElementById('mqttStatus').innerText = "Online";
    MQTT_CONFIG.topics.forEach(t => client.subscribe(t));
});

client.on('message', (topic, payload) => {
    try {
        const js = JSON.parse(payload.toString());
        if (topic === 'kbs/motordriveroom1/config') {
            currentThresholds.temp = parseFloat(js.temp_limit);
            document.getElementById('dispTempLimit').innerText = currentThresholds.temp.toFixed(2);
            gauges.forEach(g => g.update({ highlights: getGaugeHighlights(currentThresholds.temp) }));
            return;
        }

        const val = parseFloat(js.temp); const humid = parseFloat(js.humid); const sID = parseInt(js.sensor_id);
        if (sID >= 1 && sID <= 4) {
            lastSeen[sID] = Date.now();
            document.getElementById(`status${sID}`).className = "status-online";
            document.getElementById(`hmdLive${sID}`).innerText = humid.toFixed(2);
            gauges[sID-1].value = val;

            const alertEl = document.getElementById(`alert${sID}`);
            if (val >= currentThresholds.temp) { alertEl.innerText = "⚠"; alertEl.className = "alert-badge high-temp-alert"; }
            else { alertEl.innerText = "●"; alertEl.className = "alert-badge safe-status"; }

            if (val < globalMinT) globalMinT = val; if (val > globalMaxT) globalMaxT = val;
            if (humid < globalMinH) globalMinH = humid; if (humid > globalMaxH) globalMaxH = humid;

            sensorLogs[sID].push(val); humidLogs[sID].push(humid);
            if(sensorLogs[sID].length > 1440) { sensorLogs[sID].shift(); humidLogs[sID].shift(); }

            // Update stats for each card
            document.getElementById(`min${sID}`).innerText = Math.min(...sensorLogs[sID]).toFixed(2);
            document.getElementById(`max${sID}`).innerText = Math.max(...sensorLogs[sID]).toFixed(2);
            document.getElementById(`avg${sID}`).innerText = (sensorLogs[sID].reduce((a,b)=>a+b,0)/sensorLogs[sID].length).toFixed(2);
            document.getElementById(`hmin${sID}`).innerText = Math.min(...humidLogs[sID]).toFixed(2);
            document.getElementById(`hmax${sID}`).innerText = Math.max(...humidLogs[sID]).toFixed(2);
            document.getElementById(`havg${sID}`).innerText = (humidLogs[sID].reduce((a,b)=>a+b,0)/humidLogs[sID].length).toFixed(2);

            updateRealtimeAverage();
            updateChart(sID - 1, val, new Date());

            const feed = document.getElementById('jsonFeed');
            const log = document.createElement('div');
            log.innerHTML = `<span style="color:#666">[${new Date().toLocaleTimeString()}]</span> ${topic} -> ${payload}`;
            feed.prepend(log);
            if (feed.childNodes.length > 20) feed.removeChild(feed.lastChild);
        }
    } catch (e) { console.error(e); }
});

/* === UTILS === */
function updateClock() {
    const now = new Date();
    document.getElementById('liveDate').innerText = now.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
    document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-GB', {hour12:false});
}
function resizeGauges() {
    const card = document.querySelector('.card');
    if (!card) return;
    const size = Math.max(140, Math.min(card.offsetWidth - 30, 220));
    gauges.forEach(g => g.update({ width: size, height: size }));
}
function toggleDebug(show) { document.getElementById('debugOverlay').style.display = show ? 'flex' : 'none'; }

setInterval(() => {
    const now = Date.now();
    for (let i = 1; i <= 4; i++) {
        if (now - lastSeen[i] > 15000) document.getElementById(`status${i}`).className = "status-offline";
    }
}, 5000);

setInterval(updateClock, 1000);
window.addEventListener('resize', resizeGauges);
window.addEventListener('load', () => { updateClock(); resizeGauges(); });
</script>
</body>
</html>