<?php
/**
 * station_status_dashboard.php - Motor Drive Monitoring with Connection Tracking
 */
$config = require 'config.php';

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <link rel="icon" type="image/webp" href="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp">
    <title>KBS-Drive Control Room Monitoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.rawgit.com/Mikhus/canvas-gauges/master/gauge.min.js"></script>
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
</head>
<body class="app-container">

<aside class="sidebar">
    <div class="sidebar-nav">
        <a href="/motor_drive_room_dashboard" class="nav-item active">
            <span class="icon">📊</span>
            <span class="text">Dashboard</span>
        </a>
        <a href="/motor_drive_room_settings" class="nav-item">
        <span class="icon">⚙️</span><span class="text">Settings</span>
    </a>
        <!-- <a href="#" class="nav-item">
            <span class="icon">📝</span>
            <span class="text">Logs</span>
        </a> -->
        <a href="javascript:void(0);" class="nav-item" onclick="toggleDebug(true)">
    <span class="icon">📝</span>
    <span class="text">Debug Logs</span>
</a>
    </div>
    <div style="padding-bottom: 20px;">
        <a href="/motor_drive_room_logout" class="nav-item" style="color: #ef4444;">
            <span class="icon">⏻</span>
            <span class="text">Logout</span>
        </a>
    </div>
</aside>

<main class="main-content">
    <header class="topbar">
      <div style="display:flex; align-items:center; gap:12px;">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="Logo" style="height:32px;">
        <div>
            <div style="font-weight:700; color: #fff; font-size: 1rem; line-height:1.1;">Motor Drive Control Room Temp Monitoring(Under Construction)</div>
            <div style="font-size: 0.65rem; color: #64748b;">Designed & Created by IT Team</div>
        </div>
      </div>

      <div class="topbar-right">
        <div class="clock-section">
            <div id="liveDate" style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">-- --- ----</div>
            <div id="liveClock" style="font-size: 1.1rem; color: #fff; font-weight: 700; font-family: monospace;">00:00:00</div>
        </div>

        <div id="avgTempCard" style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(59, 130, 246, 0.3); padding: 6px 15px; border-radius: 10px; display: flex; align-items: center; gap: 12px; height: 38px;">
            <div style="display: flex; flex-direction: column; font-size: 0.65rem; color: #94a3b8; border-right: 1px solid #334155; padding-right: 10px; line-height: 1.2;">
                <span>MAX: <span id="maxVal" style="color: #ef4444; font-weight:bold;">--</span></span>
                <span>MIN: <span id="minVal" style="color: #10b981; font-weight:bold;">--</span></span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Avg</span>
                <span id="avgTrendIcon" style="font-size: 1rem; font-weight: bold;">━</span>
                <span id="avgTempValue" style="color: #3b82f6; font-weight: 700; font-size: 1.2rem;">--</span>
                <span style="color: #3b82f6; font-size: 0.8rem;">°C</span>
            </div>
        </div>

        <div class="status-indicator">
            <span id="mqttDot" class="dot"></span>
            <span id="mqttStatus" style="color:#94a3b8; font-size:0.75rem; font-weight:bold; text-transform:uppercase;">Offline</span>
        </div>

        <button class="btn-debug" onclick="toggleDebug(true)">DEBUG</button>
        <a href="/motor_drive_room_logout" style="color: #ef4444; text-decoration: none; font-size: 0.75rem; font-weight: bold; border: 1px solid #ef4444; padding: 5px 12px; border-radius: 4px; margin-left: 10px;">LOGOUT</a>
      </div>
    </header>

    <div class="dashboard-grid">
        <script>
            const sensorNames = ["Drive 1 (ราง A)", "Drive 2 (ราง A)", "Drive 3 (ราง A)", "Area 1 (ราง A)", "Area 2 (ราง A)"];
            sensorNames.forEach((name, i) => {
                document.write(`
                    <div class="card">
                        <div class="card-header">
                            <span id="status${i+1}" class="status-offline" title="Connection Status">●</span> 
                            <span id="alert${i+1}" class="alert-badge safe-status">●</span>
                        </div>
                        <h2>${name}</h2>
                        <canvas id="gauge${i+1}"></canvas>
                        <div class="sensor-footer">
                            <div class="stat"><small>MIN</small><span id="min${i+1}" style="color:#10b981">--</span></div>
                            <div class="stat"><small>AVG <span id="trend${i+1}">━</span></small><span id="avg${i+1}" style="color:#3b82f6">--</span></div>
                            <div class="stat"><small>MAX</small><span id="max${i+1}" style="color:#ef4444">--</span></div>
                        </div>
                    </div>
                `);
            });
        </script>
    </div>

    <div class="chart-container" style="position: relative;">
        <div style="position: absolute; top: 15px; right: 20px; z-index: 10;">
            <select id="timeRange" onchange="filterChart()" style="background: #0f172a; color: #fff; border: 1px solid #334155; padding: 5px; border-radius: 5px; font-size: 12px;">
                <option value="1">Last 1 Hour</option>
                <option value="6">Last 6 Hours</option>
                <option value="24" selected>Last 24 Hours</option>
            </select>
        </div>
        <canvas id="trendChart"></canvas>
    </div>
</main>

<div id="debugOverlay">
    <div class="debug-window">
        <button class="btn-debug" style="color:#ef4444; border-color:#ef4444" onclick="toggleDebug(false)">CLOSE [X]</button>
        <div id="jsonFeed" style="margin-top:15px;">Waiting for MQTT stream...</div>
    </div>
</div>

<script>
/* === CONFIG & STATE === */
const THRESHOLD = 30.0;
const MQTT_CONFIG = {
    url: '<?php echo $config["mqtt_ws_url"]; ?>',
    user: '<?php echo $config["mqtt_user"]; ?>',
    pass: '<?php echo $config["mqtt_pass"]; ?>',
    topics: ['kbs/driveroom1/temp1', 'kbs/driveroom1/temp2', 'kbs/driveroom1/temp3', 'kbs/driveroom1/temp4','kbs/driveroom1/temp5']
};

const liveState = { 1: null, 2: null, 3: null, 4: null, 5: null };
const sensorPrevValues = { 1: null, 2: null, 3: null, 4: null, 5: null };
const sensorLogs = { 1: [], 2: [], 3: [], 4: [], 5: [] };
const lastSeen = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 }; // Track heartbeats
let previousAverage = null;

const fullHistory = { labels: [], s1: [], s2: [], s3: [], s4: [], s5: [], lastMinute: null };

/* === GAUGE & CHART INIT === */
const gaugeOptions = {
    width: 220, height: 220, units: "°C", minValue: 0, maxValue: 100,
    majorTicks: ["0","20","40","60","80","100"], minorTicks: 2,
    colorPlate: "#1e293b", colorNumbers: "#eee", colorUnits: "#ccc",
    highlights: [
        { "from": 0, "to": 25, "color": "rgba(13, 224, 97, 0.75)" },
        { "from": 25, "to": 50, "color": "rgba(238, 106, 11, 0.75)" },
        { "from": 50, "to": 100, "color": "rgba(234, 22, 29, 0.75)" }
    ],
    animationDuration: 1000, animationRule: "linear"
};

const gauges = [
    new RadialGauge({ ...gaugeOptions, renderTo: 'gauge1', title: 'S1' }).draw(),
    new RadialGauge({ ...gaugeOptions, renderTo: 'gauge2', title: 'S2' }).draw(),
    new RadialGauge({ ...gaugeOptions, renderTo: 'gauge3', title: 'S3' }).draw(),
    new RadialGauge({ ...gaugeOptions, renderTo: 'gauge4', title: 'S4' }).draw(),
    new RadialGauge({ ...gaugeOptions, renderTo: 'gauge5', title: 'S5' }).draw()
];

const trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: [],
        datasets: [
            { label: 'S1', borderColor: '#427CDA', data: [], tension: 0.3, pointRadius: 0 },
            { label: 'S2', borderColor: '#10b981', data: [], tension: 0.3, pointRadius: 0 },
            { label: 'S3', borderColor: '#f59e0b', data: [], tension: 0.3, pointRadius: 0 },
            { label: 'S4', borderColor: '#EDEA0A', data: [], tension: 0.3, pointRadius: 0 },
            { label: 'S5', borderColor: '#444AEF', data: [], tension: 0.3, pointRadius: 0 }
        ]
    },
    options: { 
        responsive: true, maintainAspectRatio: false,
        scales: { 
            x: { grid:{color:'#334155'}, ticks: { color:'#94a3b8', maxTicksLimit: 8 } },
            y: { grid:{color:'#334155'}, ticks: { color:'#94a3b8' }, min: 0, max: 100 }
        },
        plugins: { legend: { labels: { color: '#fff', boxWidth: 10 } } }
    }
});

/* === CORE LOGIC === */
function updateRealtimeAverage() {
    let activeTemps = Object.values(liveState).filter(v => v !== null);
    if (activeTemps.length > 0) {
        const currentAvg = activeTemps.reduce((a,b)=>a+b, 0) / activeTemps.length;
        document.getElementById('minVal').innerText = Math.min(...activeTemps).toFixed(1);
        document.getElementById('maxVal').innerText = Math.max(...activeTemps).toFixed(1);
        const avgEl = document.getElementById('avgTempValue');
        const trendEl = document.getElementById('avgTrendIcon');
        avgEl.innerText = currentAvg.toFixed(2);

        if (previousAverage !== null) {
            const diff = currentAvg - previousAverage;
            if (diff > 0.001) { trendEl.innerText = "▲"; trendEl.style.color = "#ef4444"; avgEl.style.color = "#ef4444"; }
            else if (diff < -0.001) { trendEl.innerText = "▼"; trendEl.style.color = "#22c55e"; avgEl.style.color = "#22c55e"; }
            else { trendEl.innerText = "━"; trendEl.style.color = "#94a3b8"; avgEl.style.color = "#3b82f6"; }
        }
        previousAverage = currentAvg;
    }
}

function updateChart(datasetIndex, value, dateObj) {
    const currentMinute = `${dateObj.getHours()}:${dateObj.getMinutes()}`;
    if (fullHistory.lastMinute !== currentMinute) {
        fullHistory.labels.push(dateObj);
        ['s1','s2','s3','s4','s5'].forEach((k, i) => {
            const lastVal = fullHistory[k].slice(-1)[0] || null;
            fullHistory[k].push(i === datasetIndex ? value : lastVal);
        });
        fullHistory.lastMinute = currentMinute;
    } else {
        const keys = ['s1','s2','s3','s4','s5'];
        fullHistory[keys[datasetIndex]][fullHistory.labels.length - 1] = value;
    }
    filterChart();
}

function filterChart() {
    const hours = parseInt(document.getElementById('timeRange').value);
    const cutoff = new Date().getTime() - (hours * 60 * 60 * 1000);
    const startIdx = fullHistory.labels.findIndex(d => d.getTime() >= cutoff);
    if (startIdx !== -1) {
        trendChart.data.labels = fullHistory.labels.slice(startIdx).map(d => `${d.getHours()}:${d.getMinutes().toString().padStart(2,'0')}`);
        ['s1','s2','s3','s4','s5'].forEach((k, i) => { trendChart.data.datasets[i].data = fullHistory[k].slice(startIdx); });
    }
    trendChart.update('none');
}

function updateClock() {
    const now = new Date();
    document.getElementById('liveDate').innerText = now.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
    document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-GB', {hour12:false});
}

/* === MQTT LOGIC === */
const client = mqtt.connect(MQTT_CONFIG.url, { username: MQTT_CONFIG.user, password: MQTT_CONFIG.pass });

client.on('connect', () => {
    document.getElementById('mqttDot').classList.add('connected');
    const statusText = document.getElementById('mqttStatus');
    statusText.innerText = "Online";
    statusText.style.color = "#22c55e";
    MQTT_CONFIG.topics.forEach(t => client.subscribe(t));
});

client.on('message', (topic, payload) => {
    try {
        const js = JSON.parse(payload.toString());
        const val = parseFloat(js.temp);
        const sID = parseInt(js.sensor_id);
        const now = new Date();

        if (sID >= 1 && sID <= 5) {
            lastSeen[sID] = Date.now();
            document.getElementById(`status${sID}`).className = "status-online";
            gauges[sID-1].value = val;
            
            const alertEl = document.getElementById(`alert${sID}`);
            if (val >= THRESHOLD) { 
                alertEl.innerText = "⚠"; 
                alertEl.className = "alert-badge high-temp-alert"; 
            } else { 
                alertEl.innerText = "●"; 
                alertEl.className = "alert-badge safe-status"; 
            }

            const trendEl = document.getElementById(`trend${sID}`);
            if (sensorPrevValues[sID] !== null) {
                if (val > sensorPrevValues[sID]) { trendEl.innerText="▲"; trendEl.style.color="#ef4444"; }
                else if (val < sensorPrevValues[sID]) { trendEl.innerText="▼"; trendEl.style.color="#10b981"; }
                else trendEl.innerText="━";
            }
            sensorPrevValues[sID] = val;
            sensorLogs[sID].push(val);
            if(sensorLogs[sID].length > 1440) sensorLogs[sID].shift();
            document.getElementById(`min${sID}`).innerText = Math.min(...sensorLogs[sID]).toFixed(1);
            document.getElementById(`max${sID}`).innerText = Math.max(...sensorLogs[sID]).toFixed(1);
            document.getElementById(`avg${sID}`).innerText = (sensorLogs[sID].reduce((a,b)=>a+b,0) / sensorLogs[sID].length).toFixed(1);

            liveState[sID] = val;
            updateRealtimeAverage();
            updateChart(sID - 1, val, now);

            const feed = document.getElementById('jsonFeed');
            const log = document.createElement('div');
            log.innerHTML = `<span style="color:#666">[${now.toLocaleTimeString()}]</span> ${topic} -> ${payload}`;
            feed.prepend(log);
            if (feed.childNodes.length > 20) feed.removeChild(feed.lastChild);
        }
    } catch (e) { console.error("Parse Error", e); }
});

setInterval(() => {
    const now = Date.now();
    for (let i = 1; i <= 5; i++) {
        if (now - lastSeen[i] > 10000) { 
            const statusEl = document.getElementById(`status${i}`);
            if (statusEl) statusEl.className = "status-offline";
        }
    }
}, 2000);

function toggleDebug(show) { document.getElementById('debugOverlay').style.display = show ? 'block' : 'none'; }

function resizeGauges() {
    const firstCard = document.querySelector('.card');
    if (!firstCard) return;
    const newSize = Math.max(150, Math.min(firstCard.offsetWidth - 40, 220));
    gauges.forEach(g => g.update({ width: newSize, height: newSize }));
}

setInterval(updateClock, 1000);
window.addEventListener('resize', resizeGauges);
window.addEventListener('load', () => { updateClock(); resizeGauges(); });
</script>
</body>
</html>