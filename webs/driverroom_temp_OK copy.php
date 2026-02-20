<?php
/**
 * station_status_dashboard.php - 3 Sensor Temperature Monitor
 */
$config = require 'config.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8" />
<title>KBS-Fire pump | Temperature Monitoring</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.rawgit.com/Mikhus/canvas-gauges/master/gauge.min.js"></script>

<style>
  :root{
    --bg:#0f172a; --card:#1e293b; --accent:#3b82f6; --text:#f1f5f9;
  }
  body { background: var(--bg); color: var(--text); font-family: sans-serif; margin: 0; padding: 20px; }
  .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px; }
  .card { background: var(--card); border-radius: 15px; padding: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); text-align: center; }
  .chart-container { background: var(--card); border-radius: 15px; padding: 20px; height: 400px; margin-top: 20px;}
  h2 { margin-top: 0; font-size: 1.2rem; color: #94a3b8; }
  .status-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 0.9rem; }
  .dot { height: 10px; width: 10px; background-color: #ef4444; border-radius: 50%; display: inline-block; }
  .dot.connected { background-color: #22c55e; box-shadow: 0 0 10px #22c55e; }
  
  /* Debug Log Styling */
  .debug-panel { background: #020617; border-radius: 12px; padding: 15px; text-align: left; margin-bottom: 20px; border: 1px solid #1e293b; }
  #jsonFeed { font-family: monospace; font-size: 12px; max-height: 150px; overflow-y: auto; color: #10b981; }
</style>
</head>
<body>

<div class="status-bar">
    <span id="mqttDot" class="dot"></span>
    <span id="mqttStatus">Connecting to MQTT...</span>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h2>Sensor 1 (Room 1)</h2>
        <canvas id="gauge1"></canvas>
    </div>
    <div class="card">
        <h2>Sensor 2 (Room 1)</h2>
        <canvas id="gauge2"></canvas>
    </div>
    <div class="card">
        <h2>Sensor 3 (Room 1)</h2>
        <canvas id="gauge3"></canvas>
    </div>
</div>

<div class="debug-panel">
    <h3 style="margin-top:0; font-size: 0.9rem; color: #38bdf8;">// LIVE JSON STREAM</h3>
    <div id="jsonFeed">Waiting for MQTT broadcast...</div>
</div>

<div class="chart-container">
    <canvas id="trendChart"></canvas>
</div>

<script>
/* === CONFIGURATION === */
const MQTT_CONFIG = {
    url: '<?php echo $config["mqtt_ws_url"]; ?>',
    user: '<?php echo $config["mqtt_user"]; ?>',
    pass: '<?php echo $config["mqtt_pass"]; ?>',
    topics: [
        'kbs/driveroom1/temp1',
        'kbs/driveroom1/temp2',
        'kbs/driveroom1/temp3'
    ]
};

/* === INITIALIZE GAUGES === */
const gaugeOptions = {
    width: 250, height: 250, units: "°C", minValue: 0, maxValue: 100,
    majorTicks: ["0","20","40","60","80","100"],
    minorTicks: 2, strokeTicks: true,
    highlights: [{ "from": 70, "to": 100, "color": "rgba(200, 50, 50, .75)" }],
    colorPlate: "#1e293b", colorNumbers: "#eee", colorUnits: "#ccc",
    borderShadowWidth: 0, borders: false, needleType: "arrow", needleWidth: 2,
    animationDuration: 1500, animationRule: "linear"
};

const g1 = new RadialGauge({ ...gaugeOptions, renderTo: 'gauge1', title: 'TEMP 1' }).draw();
const g2 = new RadialGauge({ ...gaugeOptions, renderTo: 'gauge2', title: 'TEMP 2' }).draw();
const g3 = new RadialGauge({ ...gaugeOptions, renderTo: 'gauge3', title: 'TEMP 3' }).draw();

/* === INITIALIZE TREND CHART === */
const ctx = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [], 
        datasets: [
            { label: 'Sensor 1', borderColor: '#3b82f6', backgroundColor: '#3b82f622', data: [], tension: 0.3, fill: true },
            { label: 'Sensor 2', borderColor: '#10b981', backgroundColor: '#10b98122', data: [], tension: 0.3, fill: true },
            { label: 'Sensor 3', borderColor: '#f59e0b', backgroundColor: '#f59e0b22', data: [], tension: 0.3, fill: true }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { min: 0, max: 100, grid: { color: '#334155' }, ticks: { color: '#94a3b8' } },
            x: { grid: { color: '#334155' }, ticks: { color: '#94a3b8' } }
        },
        plugins: { legend: { labels: { color: '#f1f5f9' } } }
    }
});

/* === MQTT LOGIC === */
const client = mqtt.connect(MQTT_CONFIG.url, {
    username: MQTT_CONFIG.user,
    password: MQTT_CONFIG.pass
});

client.on('connect', () => {
    document.getElementById('mqttDot').classList.add('connected');
    document.getElementById('mqttStatus').innerText = "System Live - Connected";
    MQTT_CONFIG.topics.forEach(t => client.subscribe(t));
});

client.on('message', (topic, payload) => {
    const rawData = payload.toString();
    const feedEl = document.getElementById('jsonFeed');
    const time = new Date().toLocaleTimeString();

    try {
        const js = JSON.parse(rawData);
        
        // --- 1. UPDATE DEBUG FEED ---
        const logEntry = document.createElement('div');
        logEntry.style.marginBottom = "5px";
        logEntry.innerHTML = `<span style="color:#64748b">[${time}]</span> <span style="color:#38bdf8">${topic}</span>: ${rawData}`;
        
        if (feedEl.innerHTML === "Waiting for MQTT broadcast...") feedEl.innerHTML = "";
        feedEl.insertBefore(logEntry, feedEl.firstChild);
        if (feedEl.childNodes.length > 10) feedEl.removeChild(feedEl.lastChild);

        // --- 2. UPDATE GAUGES & CHART ---
        // Ensuring parsing of your specific JSON structure: {"sensor_id":1, "temp":68.83}
        const val = parseFloat(js.temp);
        const sID = parseInt(js.sensor_id);

        if (sID === 1) { g1.value = val; updateChart(0, val, time); }
        else if (sID === 2) { g2.value = val; updateChart(1, val, time); }
        else if (sID === 3) { g3.value = val; updateChart(2, val, time); }

    } catch (e) {
        if (feedEl) feedEl.innerHTML = `<div style="color:#ef4444;">[${time}] PARSE ERROR: ${rawData}</div>` + feedEl.innerHTML;
    }
});

function updateChart(datasetIndex, value, time) {
    const data = trendChart.data;
    if (data.labels.length === 0 || data.labels[data.labels.length - 1] !== time) {
        data.labels.push(time);
    }
    data.datasets[datasetIndex].data.push(value);
    if (data.labels.length > 20) {
        data.labels.shift();
        data.datasets.forEach(ds => ds.data.shift());
    }
    trendChart.update('none'); 
}
</script>
</body>
</html>