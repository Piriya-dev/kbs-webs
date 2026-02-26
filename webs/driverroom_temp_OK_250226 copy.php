<?php
/**
 * station_status_dashboard.php - 100% MASTER TEMPLATE REPLICA
 * Features: Fixed Alignment, Scrollable Trend, Individual Limits, Full Stats.
 */
$config = require 'config.php';
session_set_cookie_params(31536000);
ini_set('session.gc_maxlifetime', 31536000);
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

// --- DATABASE FETCH (ID 0-4) ---
$db_temp_limit = 25.00; $db_humid_limit = 70.00; $sensor_thresholds = []; 
try {
    $db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';                   
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $stmt = $conn->query("SELECT sensor_id, temp_threshold, humid_threshold FROM threshold_configs WHERE sensor_id BETWEEN 0 AND 4");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$row['sensor_id'];
        if ($sid === 0) { $db_temp_limit = (float)$row['temp_threshold']; $db_humid_limit = (float)$row['humid_threshold']; }
        else { $sensor_thresholds[$sid] = ['temp' => (float)$row['temp_threshold'], 'humid' => (float)$row['humid_threshold']]; }
    }
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <title>KBS Motor Drive Monitoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.rawgit.com/Mikhus/canvas-gauges/master/gauge.min.js"></script>
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
    <style>
        /* ✅ STRICT ALIGNMENT & SCROLLABLE SYSTEM */
        html, body { min-height: 100vh; margin: 0; padding: 0; overflow-y: auto; background: #0f172a; color: #fff; }
        body { display: flex; font-family: sans-serif; }
        .main-content { flex: 1; display: flex; flex-direction: column; padding: 15px; box-sizing: border-box; }
        
        /* Topbar Alignment */
        .topbar { flex: 0 0 auto; display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 10px 25px; border-radius: 12px; margin-bottom: 15px; border: 1px solid #334155; }
        
        /* Dashboard Grid Alignment */
        .dashboard-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 15px; }
        .card { background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 15px; display: flex; flex-direction: column; justify-content: space-between; min-height: 400px; }
        
        /* Trend Chart Alignment */
        .chart-container { background: #1e293b; padding: 20px; border-radius: 12px; border: 1px solid #334155; height: 350px; position: relative; }
        
        /* Stat Grid Alignment */
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; text-align: center; font-size: 0.65rem; color: #94a3b8; }
        .stat-val { font-size: 0.85rem; color: #fff; font-weight: bold; margin-top: 2px; }
        
        .trend-up { color: #ef4444; } .trend-down { color: #10b981; } .trend-stable { color: #94a3b8; }
        .high-temp-alert { animation: pulse-red 1.5s infinite; background: #ef4444 !important; }
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 100% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-nav">
        <a href="/motor_drive_room_dashboard" class="nav-item active">📊 Dashboard</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="/motor_drive_room_settings" class="nav-item">⚙️ Settings</a>
            <a href="/motor_drive_room_report" class="nav-item">📈 Report</a>
        <?php endif; ?>
    </div>
</aside>

<main class="main-content">
    <header class="topbar">
      <div style="display:flex; align-items:center; gap:12px;">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="Logo" style="height:28px;">
        <div style="font-weight:bold; font-size:1rem; letter-spacing: 0.5px;">Motor Drive Monitoring</div>
      </div>
      
      <div style="display:flex; gap:12px; align-items:center;">
        <div style="text-align:center; padding:0 15px; border-right:1px solid #475569;">
            <small style="color:#94a3b8; font-size:0.6rem;">Set Temp</small><br>
            <span style="color:#f59e0b; font-weight:bold; font-size:1.2rem;"><?php echo number_format($db_temp_limit, 2); ?></span>
        </div>
        <div style="display:flex; align-items:center; gap:10px; background:rgba(15,23,42,0.6); padding:6px 15px; border-radius:10px; border:1px solid rgba(16,185,129,0.3);">
            <div style="font-size:0.55rem; color:#94a3b8; text-align:right; line-height:1.2;">MAX: <span id="hMaxVal" style="color:#ef4444;">--</span><br>MIN: <span id="hMinVal" style="color:#10b981;">--</span></div>
            <div style="font-size:1.4rem; font-weight:900; color:#10b981;"><span id="avgHumidTrendIcon">━</span> <span id="avgHumidValue">--</span>%</div>
        </div>
        <div style="display:flex; align-items:center; gap:10px; background:rgba(15,23,42,0.6); padding:6px 15px; border-radius:10px; border:1px solid rgba(59,130,246,0.3);">
            <div style="font-size:0.55rem; color:#94a3b8; text-align:right; line-height:1.2;">MAX: <span id="maxVal" style="color:#ef4444;">--</span><br>MIN: <span id="minVal" style="color:#10b981;">--</span></div>
            <div style="font-size:1.4rem; font-weight:900; color:#3b82f6;"><span id="avgTrendIcon">━</span> <span id="avgTempValue">--</span>°C</div>
        </div>
        <div style="background:#22c55e; color:#fff; font-size:0.75rem; padding:5px 15px; border-radius:20px; font-weight:bold;">● Online</div>
      </div>
    </header>

    <div class="dashboard-grid">
        <script>
            let individualThresholds = {
                <?php for ($i=1;$i<=4;$i++) {
                    $t = isset($sensor_thresholds[$i]) ? $sensor_thresholds[$i]['temp'] : $db_temp_limit;
                    $h = isset($sensor_thresholds[$i]) ? $sensor_thresholds[$i]['humid'] : $db_humid_limit;
                    echo "$i: { temp: $t, humid: $h }".($i<4?",":"");
                } ?>
            };
            [1,2,3,4].forEach(i => {
                document.write(`
                    <div class="card" id="card${i}">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="color:#22c55e;">●</span>
                                <span style="color:#3b82f6; font-weight:bold; font-size:0.85rem;">H: <span id="hmdLive${i}">--</span>%</span>
                            </div>
                            <div style="font-size:0.55rem; color:#94a3b8; font-weight:bold; text-align:right; line-height:1.4;">
                                T-LIMIT: <span id="tLabel${i}" style="color:#f59e0b;">${individualThresholds[i].temp.toFixed(2)}</span><br>
                                H-LIMIT: <span id="hLabel${i}" style="color:#a855f7;">${individualThresholds[i].humid.toFixed(2)}</span>
                            </div>
                        </div>
                        <h2 style="color:#fff; font-size:0.95rem; text-align:center; margin:0 0 10px 0; font-weight:bold;">S${i}: Drive ${i}</h2>
                        <div style="flex:1; display:flex; justify-content:center; align-items:center; min-height:0;"><canvas id="gauge${i}"></canvas></div>
                        
                        <div class="stat-grid" style="border-top:1px solid #334155; padding-top:10px; margin-top:8px;">
                            <div>T-MIN<br><span class="stat-val" id="min${i}">--</span></div>
                            <div>T-AVG <span id="trend${i}">━</span><br><span class="stat-val" id="avg${i}" style="color:#3b82f6">--</span></div>
                            <div>T-MAX<br><span class="stat-val" id="max${i}">--</span></div>
                        </div>
                        <div class="stat-grid" style="border-top:1px dashed #475569; padding-top:6px; margin-top:6px;">
                            <div>H-MIN<br><span class="stat-val" id="hmin${i}">--</span></div>
                            <div>H-AVG <span id="htrend${i}">━</span><br><span class="stat-val" id="havg${i}" style="color:#10b981">--</span></div>
                            <div>H-MAX<br><span class="stat-val" id="hmax${i}">--</span></div>
                        </div>
                    </div>
                `);
            });
        </script>
    </div>
    <div class="chart-container"><canvas id="trendChart"></canvas></div>
</main>

<script>
const sensorLogs = {1:[],2:[],3:[],4:[]}, humidLogs = {1:[],2:[],3:[],4:[]};
let lastVals = {1:{t:0,h:0},2:{t:0,h:0},3:{t:0,h:0},4:{t:0,h:0}};
let gMaxT = -Infinity, gMinT = Infinity, gMaxH = -Infinity, gMinH = Infinity;

const getTrendIcon = (cur, prev) => cur > prev ? '▲' : (cur < prev ? '▼' : '━');
const getTrendClass = (cur, prev) => cur > prev ? 'trend-up' : (cur < prev ? 'trend-down' : 'trend-stable');

const gauges = [1,2,3,4].map(id => new RadialGauge({
    renderTo: `gauge${id}`, width: 210, height: 210, minValue: 0, maxValue: 100,
    highlights: [{ from: 0, to: individualThresholds[id].temp, color: "rgba(13, 224, 97, .75)" }, { from: individualThresholds[id].temp, to: 100, color: "rgba(234, 22, 29, .75)" }]
}).draw());

const trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'line', data: { labels: [], datasets: [1,2,3,4].map((id,i) => ({ label: `S${id}`, data: [], borderColor: ['#3b82f6','#10b981','#f59e0b','#ef4444'][i], tension:0.3, pointRadius:0, borderWidth:2 })) },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#fff', usePointStyle: true, boxWidth: 10 } } } }
});

const client = mqtt.connect('<?php echo $config["mqtt_ws_url"]; ?>', { username: '<?php echo $config["mqtt_user"]; ?>', password: '<?php echo $config["mqtt_pass"]; ?>' });

client.on('connect', () => { [1,2,3,4].forEach(i => client.subscribe(`kbs/driveroom1/temp${i}`)); });

client.on('message', (topic, payload) => {
    try {
        const js = JSON.parse(payload.toString());
        const sID = parseInt(topic.split('temp')[1]);
        if (sID >= 1 && sID <= 4) {
            const val = parseFloat(js.temp), humid = parseFloat(js.humid);
            sensorLogs[sID].push(val); humidLogs[sID].push(humid);
            if (sensorLogs[sID].length > 100) { sensorLogs[sID].shift(); humidLogs[sID].shift(); }

            document.getElementById(`min${sID}`).innerText = Math.min(...sensorLogs[sID]).toFixed(2);
            document.getElementById(`max${sID}`).innerText = Math.max(...sensorLogs[sID]).toFixed(2);
            document.getElementById(`avg${sID}`).innerText = (sensorLogs[sID].reduce((a,b)=>a+b,0)/sensorLogs[sID].length).toFixed(2);
            document.getElementById(`hmin${sID}`).innerText = Math.min(...humidLogs[sID]).toFixed(2);
            document.getElementById(`hmax${sID}`).innerText = Math.max(...humidLogs[sID]).toFixed(2);
            document.getElementById(`havg${sID}`).innerText = (humidLogs[sID].reduce((a,b)=>a+b,0)/humidLogs[sID].length).toFixed(2);
            document.getElementById(`hmdLive${sID}`).innerText = humid.toFixed(2);

            document.getElementById(`trend${sID}`).innerText = getTrendIcon(val, lastVals[sID].t);
            document.getElementById(`trend${sID}`).className = getTrendClass(val, lastVals[sID].t);
            document.getElementById(`htrend${sID}`).innerText = getTrendIcon(humid, lastVals[sID].h);
            document.getElementById(`htrend${sID}`).className = getTrendClass(humid, lastVals[sID].h);

            if (val > gMaxT) gMaxT = val; if (val < gMinT) gMinT = val;
            if (humid > gMaxH) gMaxH = humid; if (humid < gMinH) gMinH = humid;
            document.getElementById('maxVal').innerText = gMaxT.toFixed(2);
            document.getElementById('minVal').innerText = gMinT.toFixed(2);
            document.getElementById('hMaxVal').innerText = gMaxH.toFixed(2);
            document.getElementById('hMinVal').innerText = gMinH.toFixed(2);

            const allT = Object.values(sensorLogs).flat(), allH = Object.values(humidLogs).flat();
            if(allT.length > 0) {
                const curAvgT = (allT.reduce((a,b)=>a+b,0)/allT.length).toFixed(2);
                const prevAvgT = parseFloat(document.getElementById('avgTempValue').innerText) || 0;
                document.getElementById('avgTrendIcon').innerText = getTrendIcon(curAvgT, prevAvgT);
                document.getElementById('avgTempValue').innerText = curAvgT;
            }
            if(allH.length > 0) document.getElementById('avgHumidValue').innerText = (allH.reduce((a,b)=>a+b,0)/allH.length).toFixed(2);

            const timeStr = new Date().toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'});
            if (!trendChart.data.labels.includes(timeStr)) {
                trendChart.data.labels.push(timeStr);
                if (trendChart.data.labels.length > 30) trendChart.data.labels.shift();
            }
            trendChart.data.datasets[sID-1].data.push(val);
            if (trendChart.data.datasets[sID-1].data.length > 30) trendChart.data.datasets[sID-1].data.shift();
            trendChart.update('none');

            lastVals[sID] = {t: val, h: humid};
            gauges[sID-1].value = val;
        }
    } catch (e) {}
});
</script>
</body>
</html>