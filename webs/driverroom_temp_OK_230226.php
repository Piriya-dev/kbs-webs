<?php
/**
 * station_status_dashboard.php - MASTER REFERENCE VERSION
 * Features: 1-Row Auto-fit, Individual T/H Stats, Trend Chart, Spaced Design.
 */
$config = require 'config.php';
session_set_cookie_params(31536000);
ini_set('session.gc_maxlifetime', 31536000);
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

// --- DATABASE FETCH (Global ID 0 & Individual ID 1-4) ---
$db_temp_limit = 24.00;
$db_humid_limit = 60.00;
$sensor_thresholds = [];

try {
    $db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';                   
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->query("SELECT sensor_id, temp_threshold, humid_threshold FROM threshold_configs WHERE sensor_id BETWEEN 0 AND 4");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$row['sensor_id'];
        if ($sid === 0) { 
            $db_temp_limit = (float)$row['temp_threshold']; 
            $db_humid_limit = (float)$row['humid_threshold']; 
        } else { 
            $sensor_thresholds[$sid] = [
                'temp' => (float)$row['temp_threshold'], 
                'humid' => (float)$row['humid_threshold']
            ]; 
        }
    }
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <title>KBS-Drive Monitoring (Perfect Version)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.rawgit.com/Mikhus/canvas-gauges/master/gauge.min.js"></script>
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
    <style>
        body { display: flex; min-height: 100vh; margin: 0; background: #0f172a; font-family: sans-serif; }
        .main-content { flex: 1; display: flex; flex-direction: column; padding: 15px; width: 100%; box-sizing: border-box; overflow: hidden; }
        
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 12px; 
            margin-bottom: 15px; 
        }

        @media (min-width: 1600px) { .dashboard-grid { grid-template-columns: repeat(4, 1fr); } }

        .card { padding: 15px; background: #1e293b; border-radius: 12px; border: 1px solid #334155; position: relative; display: flex; flex-direction: column; }
        .chart-container { flex: 1; min-height: 320px; background: #1e293b; padding: 20px; border-radius: 12px; border: 1px solid #334155; }
        
        .topbar { display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 12px 25px; border-radius: 12px; margin-bottom: 15px; border: 1px solid #334155; }
        .header-stat-box { display: flex; align-items: center; gap: 12px; padding: 6px 15px; border-radius: 10px; background: rgba(15, 23, 42, 0.8); border: 1px solid #334155; }
        
        .high-temp-alert { animation: pulse-red 1.5s infinite; background: #ef4444 !important; }
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 100% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); } }
        
        canvas { max-width: 100%; height: auto !important; }
    </style>
</head>
<body class="app-container">

<aside class="sidebar">
    <div class="sidebar-nav">
        <a href="/motor_drive_room_dashboard" class="nav-item active">📊 Dashboard</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="/motor_drive_room_settings" class="nav-item">⚙️ Settings</a>
            <a href="/motor_drive_room_status" class="nav-item">🌐 Status</a>
            <a href="/motor_drive_debug_system" class="nav-item">🔍 Debug</a>
            <a href="/motor_drive_room_report" class="nav-item">📈 Report</a>
        <?php endif; ?>
    </div>
</aside>

<main class="main-content">
    <header class="topbar">
      <div style="display:flex; align-items:center; gap:15px;">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="Logo" style="height:28px;">
        <div id="realtimeClock" style="font-family:monospace; color:#3b82f6; font-size:1.1rem; font-weight:bold;">--:--:--</div>
      </div>
      
      <div style="display:flex; gap:12px; align-items:center;">
        <div style="background:rgba(0,0,0,0.2); padding:8px 15px; border-radius:10px; border:1px solid #334155; text-align:center;">
            <small style="color:#94a3b8; font-size:0.65rem;">SET T/H</small><br>
            <span style="color:#f59e0b; font-weight:700; font-size:1.1rem;"><span id="dispTempLimit"><?php echo $db_temp_limit; ?></span> / <span id="dispHumidLimit"><?php echo $db_humid_limit; ?></span></span>
        </div>

        <div class="header-stat-box" style="border-color: rgba(16,185,129,0.3);">
            <div style="font-size:0.65rem; color:#94a3b8;">H-MAX: <span id="hMaxVal" style="color:#ef4444;">--</span><br>H-MIN: <span id="hMinVal" style="color:#10b981;">--</span></div>
            <span id="avgHumidTrendIcon" style="font-size:1.2rem;">━</span> <span id="avgHumidValue" style="color:#10b981; font-weight:800; font-size:1.4rem;">--</span>%
        </div>

        <div class="header-stat-box" style="border-color: rgba(59,130,246,0.3);">
            <div style="font-size:0.65rem; color:#94a3b8;">T-MAX: <span id="maxVal" style="color:#ef4444;">--</span><br>T-MIN: <span id="minVal" style="color:#10b981;">--</span></div>
            <span id="avgTrendIcon" style="font-size:1.2rem;">━</span> <span id="avgTempValue" style="color:#3b82f6; font-weight:800; font-size:1.4rem;">--</span>°C
        </div>
        <div id="mqttStatus" style="font-size:0.8rem; font-weight:bold; color:#22c55e;">Online</div>
      </div>
    </header>

    <div class="dashboard-grid">
        <script>
            const sensorNames = ["S1: Drive 1", "S2: Drive 2", "S3: Drive 3", "S4: Area 1"];
            sensorNames.forEach((name, i) => {
                document.write(`
                    <div class="card" id="card${i+1}">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span id="status${i+1}" style="color:#64748b; font-size:1.2rem;">●</span>
                                <span style="color:#fff; font-weight:bold; font-size:0.9rem;">${name}</span>
                            </div>
                            <div style="color:#f59e0b; font-size:0.75rem; font-weight:bold;">
                                LMT: <span id="tLimit${i+1}">--</span> / <span id="hLimit${i+1}">--</span>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:center; flex:1; padding:10px 0;"><canvas id="gauge${i+1}"></canvas></div>
                        
                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:5px; font-size:0.65rem; text-align:center; border-top:1px solid #334155; padding-top:10px;">
                            <div><small style="color:#94a3b8">T-MIN</small><br><b id="min${i+1}" style="color:#10b981">--</b></div>
                            <div><small style="color:#94a3b8">T-AVG <span id="trend${i+1}">━</span></small><br><b id="avg${i+1}" style="color:#3b82f6">--</b></div>
                            <div><small style="color:#94a3b8">T-MAX</small><br><b id="max${i+1}" style="color:#ef4444">--</b></div>
                        </div>
                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:5px; font-size:0.65rem; text-align:center; margin-top:8px; border-top:1px dashed #334155; padding-top:8px;">
                            <div><small style="color:#94a3b8">H-MIN</small><br><b id="hmin${i+1}">--</b></div>
                            <div><small style="color:#94a3b8">H-AVG</small><br><b id="havg${i+1}">--</b></div>
                            <div><small style="color:#94a3b8">H-MAX</small><br><b id="hmax${i+1}">--</b></div>
                        </div>
                    </div>
                `);
            });
        </script>
    </div>

    <div class="chart-container"><canvas id="trendChart"></canvas></div>
</main>

<script>
/* === MQTT & DATA LOGIC (100% RESTORED) === */
let individualThresholds = {
    <?php for ($i=1;$i<=4;$i++) {
        $t = isset($sensor_thresholds[$i]) ? $sensor_thresholds[$i]['temp'] : $db_temp_limit;
        $h = isset($sensor_thresholds[$i]) ? $sensor_thresholds[$i]['humid'] : $db_humid_limit;
        echo "$i: { temp: $t, humid: $h }".($i<4?",":"");
    } ?>
};

const sensorLogs = {1:[],2:[],3:[],4:[]}, humidLogs = {1:[],2:[],3:[],4:[]};
let globalMinT = Infinity, globalMaxT = -Infinity, globalMinH = Infinity, globalMaxH = -Infinity;

const gauges = [1,2,3,4].map(id => new RadialGauge({
    renderTo: `gauge${id}`, width: 220, height: 220, minValue: 0, maxValue: 100,
    colorPlate: "#1e293b", colorNumbers: "#fff", borderShadowWidth: 0,
    highlights: [{ from: 0, to: individualThresholds[id].temp, color: "rgba(13,224,97,.75)" }, { from: individualThresholds[id].temp, to: 100, color: "rgba(234,22,29,.75)" }]
}).draw());

const trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'line', data: { labels: [], datasets: [1,2,3,4].map((id,i) => ({ label: `S${id}`, data: [], borderColor: ['#3b82f6','#10b981','#f59e0b','#ef4444'][i], tension:0.3 })) },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#fff' } } } }
});

const client = mqtt.connect('<?php echo $config["mqtt_ws_url"]; ?>', { username: '<?php echo $config["mqtt_user"]; ?>', password: '<?php echo $config["mqtt_pass"]; ?>' });

client.on('connect', () => {
    document.getElementById('mqttStatus').innerText = "Online";
    [1,2,3,4].forEach(i => client.subscribe(`kbs/driveroom1/temp${i}`));
});

client.on('message', (topic, payload) => {
    try {
        const js = JSON.parse(payload.toString());
        const sID = parseInt(topic.split('temp')[1]);
        if (sID >= 1 && sID <= 4) {
            const val = parseFloat(js.temp), humid = parseFloat(js.humid);
            
            const card = document.getElementById(`card${sID}`);
            if (val > individualThresholds[sID].temp) card.classList.add('high-temp-alert');
            else card.classList.remove('high-temp-alert');

            sensorLogs[sID].push(val); humidLogs[sID].push(humid);
            if (sensorLogs[sID].length > 50) { sensorLogs[sID].shift(); humidLogs[sID].shift(); }

            document.getElementById(`min${sID}`).innerText = Math.min(...sensorLogs[sID]).toFixed(1);
            document.getElementById(`max${sID}`).innerText = Math.max(...sensorLogs[sID]).toFixed(1);
            document.getElementById(`avg${sID}`).innerText = (sensorLogs[sID].reduce((a,b)=>a+b,0)/sensorLogs[sID].length).toFixed(1);
            document.getElementById(`hmin${sID}`).innerText = Math.min(...humidLogs[sID]).toFixed(1);
            document.getElementById(`hmax${sID}`).innerText = Math.max(...humidLogs[sID]).toFixed(1);
            document.getElementById(`havg${sID}`).innerText = (humidLogs[sID].reduce((a,b)=>a+b,0)/humidLogs[sID].length).toFixed(1);

            if (val > globalMaxT) globalMaxT = val; if (val < globalMinT) globalMinT = val;
            if (humid > globalMaxH) globalMaxH = humid; if (humid < globalMinH) globalMinH = humid;
            document.getElementById('maxVal').innerText = globalMaxT.toFixed(1);
            document.getElementById('minVal').innerText = globalMinT.toFixed(1);
            document.getElementById('hMaxVal').innerText = globalMaxH.toFixed(1);
            document.getElementById('hMinVal').innerText = globalMinH.toFixed(1);
            
            let allT = Object.values(sensorLogs).flat();
            let allH = Object.values(humidLogs).flat();
            document.getElementById('avgTempValue').innerText = (allT.reduce((a,b)=>a+b,0)/allT.length).toFixed(1);
            document.getElementById('avgHumidValue').innerText = (allH.reduce((a,b)=>a+b,0)/allH.length).toFixed(1);

            gauges[sID-1].value = val;
            document.getElementById(`status${sID}`).style.color = "#22c55e";
            trendChart.update('none');
        }
    } catch (e) {}
});

setInterval(() => { document.getElementById('realtimeClock').innerText = new Date().toLocaleString('th-TH'); }, 1000);
for(let k=1;k<=4;k++){
    document.getElementById(`tLimit${k}`).innerText = individualThresholds[k].temp.toFixed(1); 
    document.getElementById(`hLimit${k}`).innerText = individualThresholds[k].humid.toFixed(1); 
}
</script>
</body>
</html>