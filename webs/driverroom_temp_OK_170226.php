<?php
/**
 * station_status_dashboard.php - Professional Motor Drive Monitoring
 */
$config = require 'config.php';
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

<style>
  :root{ --bg:#0f172a; --card:#1e293b; --accent:#3b82f6; --text:#f1f5f9; --debug:#10b981; }
  body { background: var(--bg); color: var(--text); font-family: sans-serif; margin: 0; padding: 20px; }
  
  .topbar { 
    height: 70px; display: flex; justify-content: space-between; align-items: center; 
    padding: 0 20px; border-bottom: 1px solid #334155; background: rgba(30, 41, 59, 0.95); 
    backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 100; margin: -20px -20px 20px -20px;
  }

  .topbar-right { display: flex; align-items: center; gap: 20px; }

  .clock-section {
    text-align: right; border-right: 1px solid #334155; padding-right: 15px; line-height: 1.1; min-width: 110px;
  }

  /* NEW: Connection Pill Styling */
  .status-indicator {
    display: flex; align-items: center; gap: 8px; background: rgba(30, 41, 59, 0.5);
    padding: 6px 14px; border-radius: 20px; border: 1px solid #334155;
  }

  .dot { height: 10px; width: 10px; background-color: #ef4444; border-radius: 50%; transition: 0.3s; }
  .dot.connected { background-color: #22c55e; box-shadow: 0 0 10px #22c55e; }

  /* Grid & Cards */
  .dashboard-grid { display: grid; gap: 15px; margin-bottom: 20px; grid-template-columns: 1fr; }
  @media (min-width: 992px) { .dashboard-grid { grid-template-columns: repeat(5, 1fr); } }
/* Safe Status Dot */
.safe-status {
    color: #22c55e;
    opacity: 0.8;
    font-size: 0.8rem;
    filter: drop-shadow(0 0 2px rgba(34, 197, 94, 0.4));
}
  .card { 
    background: var(--card); border-radius: 15px; padding: 15px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); 
    display: flex; flex-direction: column; align-items: center; position: relative; overflow: hidden;
  }
  .card h2 { font-size: 0.85rem; margin-bottom: 5px; color: #94a3b8; white-space: nowrap; }

  .alert-badge { position: absolute; top: 10px; right: 10px; font-size: 1.1rem; color: #334155; }
  .high-temp-alert { color: #ef4444 !important; text-shadow: 0 0 10px #ef4444; animation: blink 1s infinite; }
  @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }

  .sensor-footer { display: flex; justify-content: space-around; width: 100%; margin-top: 10px; padding-top: 10px; border-top: 1px solid #334155; }
  .stat { display: flex; flex-direction: column; align-items: center; font-family: monospace; }
  .stat small { font-size: 0.6rem; color: #64748b; }
  .stat span { font-weight: bold; font-size: 0.9rem; }

  .chart-container { width: 100%; background: var(--card); border-radius: 15px; padding: 15px; height: 45vh; min-height: 300px; }
  
  #debugOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; padding: 40px; }
  .debug-window { background: #000; border: 1px solid var(--debug); border-radius: 12px; height: 100%; display: flex; flex-direction: column; padding: 20px; }
  #jsonFeed { flex: 1; overflow-y: auto; color: var(--debug); font-family: monospace; font-size: 12px; }
  .btn-debug { background: transparent; color: var(--debug); border: 1px solid var(--debug); padding: 5px 12px; border-radius: 4px; cursor: pointer; transition: 0.2s; }
  .btn-debug:hover { background: var(--debug); color: #000; }
  /* Position the status dots in opposite corners */
.status-online { color: #22c55e; font-size: 0.8rem; opacity: 0.9; }
.status-offline { color: #ef4444; font-size: 0.8rem; opacity: 0.9; text-shadow: 0 0 5px #ef4444; }

/* Adjust header layout inside card */
.card-header {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: absolute;
    top: 10px;
    padding: 0 12px;
    box-sizing: border-box;
}
</style>
</head>
<body>

<header class="topbar">
  <div style="display:flex; align-items:center; gap:12px;">
    <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="Logo" style="height:32px;">
    <div>
        <div style="font-weight:700; color: #fff; font-size: 1rem; line-height:1.1;">Motor Drive Control Room Monitoring (Under Development)</div>
        <div style="font-size: 0.65rem; color: #64748b;">Designed & Created by IT Team</div>
    </div>
  </div>

  <div class="topbar-right">
    <div class="clock-section">
        <div id="liveDate" style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; letter-spacing:0.5px;">-- --- ----</div>
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
  </div>
</header>

<div class="dashboard-grid">
    <script>
     const sensorNames = ["Drive 1 (ราง A)", "Drive 2 (ราง A)", "Drive 3 (ราง A)", "Area 1 (ราง A)", "Area 2 (ราง A)"];
sensorNames.forEach((name, i) => {
    document.write(`
        <div class="card">
            <span id="alert${i+1}" class="alert-badge safe-status">●</span>
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

/* === LOGIC FUNCTIONS === */

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
            gauges[sID-1].value = val;
            
            const alertEl = document.getElementById(`alert${sID}`);
            if (val >= THRESHOLD) { alertEl.innerText = "⚠"; alertEl.classList.add('high-temp-alert'); }
            else { alertEl.innerText = "●"; alertEl.classList.remove('high-temp-alert'); }

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