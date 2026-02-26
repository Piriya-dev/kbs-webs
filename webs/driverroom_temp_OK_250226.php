<?php

/**
 * station_status_dashboard.php - 100% PERFECT MASTER REPLICA
 * Fixed: Global Average Temp & Humid display in Topbar.
 */
$config = require 'config.php';
session_set_cookie_params(31536000);
ini_set('session.gc_maxlifetime', 31536000);
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

// --- DATABASE FETCH (ID 0 for Global, 1-4 for Sensors) ---
$db_temp_limit = 25.00;
$db_humid_limit = 70.00;
$sensor_thresholds = [];
try {
    $db_host = '127.0.0.1';
    $db_name = 'kbs_eng_db';
    $db_user = 'kbs-ccsonline';
    $db_pass = '@Kbs2024!#';
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $stmt = $conn->query("SELECT sensor_id, temp_threshold, humid_threshold FROM threshold_configs WHERE sensor_id BETWEEN 0 AND 4");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$row['sensor_id'];
        if ($sid === 0) {
            $db_temp_limit = (float)$row['temp_threshold'];
            $db_humid_limit = (float)$row['humid_threshold'];
        } else {
            $sensor_thresholds[$sid] = ['temp' => (float)$row['temp_threshold'], 'humid' => (float)$row['humid_threshold']];
        }
    }
} catch (PDOException $e) {
}
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
        html,
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-y: auto;
            background: #0f172a;
            color: #fff;
        }

        body {
            display: flex;
            font-family: sans-serif;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 15px;
            box-sizing: border-box;
        }

        .topbar {
            flex: 0 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e293b;
            padding: 10px 25px;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid #334155;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }

        .card {
            background: #1e293b;
            border-radius: 12px;
            border: 1px solid #334155;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 410px;
        }

        .chart-container {
            background: #1e293b;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #334155;
            height: 350px;
            position: relative;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5px;
            text-align: center;
            font-size: 0.65rem;
            color: #94a3b8;
        }

        .stat-val {
            font-size: 0.85rem;
            color: #fff;
            font-weight: bold;
            margin-top: 2px;
        }

        .trend-up {
            color: #ef4444;
        }

        .trend-down {
            color: #10b981;
        }

        .trend-stable {
            color: #94a3b8;
        }

        /* ไอคอนคำเตือนที่มุมขวาบน */
        .warning-box {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.5rem;
            display: none;
            /* ปิดไว้ก่อน จะเปิดด้วย JS */
            z-index: 10;
        }

        /* เอฟเฟกต์กระพริบ */
        @keyframes blink-red {
            0% {
                opacity: 1;
                color: #ef4444;
                transform: scale(1);
            }

            50% {
                opacity: 0.3;
                color: #7f1d1d;
                transform: scale(1.2);
            }

            100% {
                opacity: 1;
                color: #ef4444;
                transform: scale(1);
            }
        }

        .blink-active {
            display: block !important;
            animation: blink-red 0.8s infinite;
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.8);
        }
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
                <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="Logo" style="height:28px;">
                <div id="realtimeClock" style="font-family:monospace; color:#3b82f6; font-size: 0.9rem; font-weight: bold; margin-left:10px;">--/--/-- --:--:--</div>
            </div>

            <div style="display:flex; gap:12px; align-items:center;">
                <div style="text-align:center; padding:0 15px;">
                    <small style="color:#94a3b8; font-size:0.6rem; text-transform: uppercase;">SET TEMP</small><br>
                    <span style="color:#f59e0b; font-weight:bold; font-size:1.5rem;"><?php echo number_format($db_temp_limit, 2); ?></span>
                </div>
                <div style="text-align:center; padding:0 15px; border-right:1px solid #475569;">
                    <small style="color:#94a3b8; font-size:0.6rem; text-transform: uppercase;">SET HUMID</small><br>
                    <span style="color:#a855f7; font-weight:bold; font-size:1.5rem;"><?php echo number_format($db_humid_limit, 2); ?></span>
                </div>

                <div style="display:flex; align-items:center; gap:10px; background:rgba(15,23,42,0.6); padding:6px 15px; border-radius:10px; border:1px solid rgba(16,185,129,0.3);">
                    <div style="font-size:0.55rem; color:#94a3b8; text-align:right;">MAX: <span id="hMaxVal" style="color:#ef4444;">--</span><br>MIN: <span id="hMinVal" style="color:#10b981;">--</span></div>
                    <div style="font-size:2rem; font-weight:900; color:#10b981;"><span id="avgHumidTrendIcon">━</span> <span id="avgHumidValue">--</span>%</div>
                </div>

                <div style="display:flex; align-items:center; gap:10px; background:rgba(15,23,42,0.6); padding:6px 15px; border-radius:10px; border:1px solid rgba(59,130,246,0.3);">
                    <div style="font-size:0.55rem; color:#94a3b8; text-align:right;">MAX: <span id="maxVal" style="color:#ef4444;">--</span><br>MIN: <span id="minVal" style="color:#10b981;">--</span></div>
                    <div style="font-size:2rem; font-weight:900; color:#3b82f6;"><span id="avgTrendIcon">━</span> <span id="avgTempValue">--</span>°C</div>
                </div>

                <div style="background:#22c55e; color:#fff; font-size:1rem; padding:8px 20px; border-radius:25px; font-weight:bold;">● Online</div>
            </div>
        </header>

        <!-- <div class="dashboard-grid">
        <script>
            let individualThresholds = {
                <?php for ($i = 1; $i <= 4; $i++) {
                    $t = isset($sensor_thresholds[$i]) ? $sensor_thresholds[$i]['temp'] : $db_temp_limit;
                    $h = isset($sensor_thresholds[$i]) ? $sensor_thresholds[$i]['humid'] : $db_humid_limit;
                    echo "$i: { temp: $t, humid: $h }" . ($i < 4 ? "," : "");
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
                            <div style="font-size:0.55rem; color:#94a3b8; font-weight:bold; text-align:right;">
                                T-LIMIT: <span style="color:#f59e0b;">${individualThresholds[i].temp.toFixed(2)}</span><br>
                                H-LIMIT: <span style="color:#a855f7;">${individualThresholds[i].humid.toFixed(2)}</span>
                            </div>
                        </div>
                        <h2 style="color:#fff; font-size:0.95rem; text-align:center; margin:0 0 10px 0; font-weight:bold;">S${i}: Sensor# ${i}</h2>
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
    </div> -->
        <div class="dashboard-grid">
            <script>
                let individualThresholds = {
                    <?php for ($i = 1; $i <= 4; $i++) {
                        $t = isset($sensor_thresholds[$i]) ? $sensor_thresholds[$i]['temp'] : $db_temp_limit;
                        $h = isset($sensor_thresholds[$i]) ? $sensor_thresholds[$i]['humid'] : $db_humid_limit;
                        echo "$i: { temp: $t, humid: $h }" . ($i < 4 ? "," : "");
                    } ?>
                };

                [1, 2, 3, 4].forEach(i => {
                    document.write(`
                <div class="card" id="card${i}" style="position: relative;">
                    <div id="warningIcon${i}" class="warning-box" style="position: absolute; top: 10px; right: 10px; display: none; font-size: 1.5rem; z-index: 10;">⚠️</div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span id="led${i}" style="color:#22c55e;">●</span>
                            <span style="color:#3b82f6; font-weight:bold; font-size:0.85rem;">H: <span id="hmdLive${i}">--</span>%</span>
                        </div>
                        <div style="font-size:0.55rem; color:#94a3b8; font-weight:bold; text-align:right;">
                            T-LIMIT: <span id="tLabel${i}" style="color:#f59e0b;">${individualThresholds[i].temp.toFixed(2)}</span><br>
                            H-LIMIT: <span id="hLabel${i}" style="color:#a855f7;">${individualThresholds[i].humid.toFixed(2)}</span>
                        </div>
                    </div>

                    <h2 style="color:#fff; font-size:0.95rem; text-align:center; margin:0 0 10px 0; font-weight:bold;">S${i}: Sensor# ${i}</h2>
                    
                    <div style="flex:1; display:flex; justify-content:center; align-items:center; min-height:0;">
                        <canvas id="gauge${i}"></canvas>
                    </div>

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
        /* === LOGIC ENGINE === */
        const sensorLogs = {
                1: [],
                2: [],
                3: [],
                4: []
            },
            humidLogs = {
                1: [],
                2: [],
                3: [],
                4: []
            };
        const latestValues = {
            temp: {
                1: null,
                2: null,
                3: null,
                4: null
            },
            humid: {
                1: null,
                2: null,
                3: null,
                4: null
            }
        };
        let lastAvgT = 0,
            lastAvgH = 0;
        let gMaxT = -Infinity,
            gMinT = Infinity,
            gMaxH = -Infinity,
            gMinH = Infinity;

        const getTrendIcon = (cur, prev) => cur > prev ? '▲' : (cur < prev ? '▼' : '━');
        const getTrendClass = (cur, prev) => cur > prev ? 'trend-up' : (cur < prev ? 'trend-down' : 'trend-stable');

        const gauges = [1, 2, 3, 4].map(id => new RadialGauge({
            renderTo: `gauge${id}`,
            width: 210,
            height: 210,
            minValue: 0,
            maxValue: 100,
            highlights: [{
                from: 0,
                to: individualThresholds[id].temp,
                color: "rgba(13, 224, 97, .75)"
            }, {
                from: individualThresholds[id].temp,
                to: 100,
                color: "rgba(234, 22, 29, .75)"
            }]
        }).draw());

        const trendChart = new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [1, 2, 3, 4].map((id, i) => ({
                    label: `S${id}`,
                    data: [],
                    borderColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'][i],
                    tension: 0.3,
                    pointRadius: 0,
                    borderWidth: 2
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#fff',
                            usePointStyle: true,
                            boxWidth: 10
                        }
                    }
                }
            }
        });

        const client = mqtt.connect('<?php echo $config["mqtt_ws_url"]; ?>', {
            username: '<?php echo $config["mqtt_user"]; ?>',
            password: '<?php echo $config["mqtt_pass"]; ?>'
        });

        client.on('connect', () => {
            [1, 2, 3, 4].forEach(i => client.subscribe(`kbs/driveroom1/temp${i}`));
        });
        client.on('message', (topic, payload) => {
            try {
                const js = JSON.parse(payload.toString());
                const sID = parseInt(topic.split('temp')[1]);
                if (sID >= 1 && sID <= 4) {
                    const val = parseFloat(js.temp),
                        humid = parseFloat(js.humid);

                    // 1️⃣ [Real-time UI] Warning Icon & LED Status
                    const tLimit = individualThresholds[sID].temp;
                    const hLimit = individualThresholds[sID].humid;
                    const warningIcon = document.getElementById(`warningIcon${sID}`);
                    const led = document.getElementById(`led${sID}`);
                    const card = document.getElementById(`card${sID}`);

                    if (val > tLimit || humid > hLimit) {
                        if (warningIcon) warningIcon.classList.add('blink-active');
                        if (led) led.style.color = "#ef4444";
                        if (card) card.style.borderColor = "rgba(239, 68, 68, 0.5)";
                    } else {
                        if (warningIcon) warningIcon.classList.remove('blink-active');
                        if (led) led.style.color = "#22c55e";
                        if (card) card.style.borderColor = "#334155";
                    }

                    // 2️⃣ [Data Storage] เก็บค่าล่าสุดเพื่อใช้คำนวณส่วนกลาง (Consistency)
                    latestValues.temp[sID] = val;
                    latestValues.humid[sID] = humid;

                    sensorLogs[sID].push(val);
                    humidLogs[sID].push(humid);
                    if (sensorLogs[sID].length > 100) {
                        sensorLogs[sID].shift();
                        humidLogs[sID].shift();
                    }

                    // 3️⃣ [Card Updates] อัปเดตสถิติรายตัว
                    document.getElementById(`min${sID}`).innerText = Math.min(...sensorLogs[sID]).toFixed(2);
                    document.getElementById(`max${sID}`).innerText = Math.max(...sensorLogs[sID]).toFixed(2);
                    document.getElementById(`avg${sID}`).innerText = (sensorLogs[sID].reduce((a, b) => a + b, 0) / sensorLogs[sID].length).toFixed(2);
                    document.getElementById(`hmin${sID}`).innerText = Math.min(...humidLogs[sID]).toFixed(2);
                    document.getElementById(`hmax${sID}`).innerText = Math.max(...humidLogs[sID]).toFixed(2);
                    document.getElementById(`havg${sID}`).innerText = (humidLogs[sID].reduce((a, b) => a + b, 0) / humidLogs[sID].length).toFixed(2);
                    document.getElementById(`hmdLive${sID}`).innerText = humid.toFixed(2);

                    // 4️⃣ [Topbar Consistency] คำนวณค่ารวมจากค่าล่าสุดของทุก Sensor จริงๆ
                    const activeTemps = Object.values(latestValues.temp).filter(v => v !== null);
                    const activeHumids = Object.values(latestValues.humid).filter(v => v !== null);

                    if (activeTemps.length > 0) {
                        const currentAvgT = activeTemps.reduce((a, b) => a + b, 0) / activeTemps.length;
                        document.getElementById('avgTempValue').innerText = currentAvgT.toFixed(2);
                        document.getElementById('maxVal').innerText = Math.max(...activeTemps).toFixed(2);
                        document.getElementById('minVal').innerText = Math.min(...activeTemps).toFixed(2);

                        document.getElementById('avgTrendIcon').innerText = getTrendIcon(currentAvgT, lastAvgT);
                        document.getElementById('avgTrendIcon').className = getTrendClass(currentAvgT, lastAvgT);
                        lastAvgT = currentAvgT;
                    }

                    if (activeHumids.length > 0) {
                        const currentAvgH = activeHumids.reduce((a, b) => a + b, 0) / activeHumids.length;
                        document.getElementById('avgHumidValue').innerText = currentAvgH.toFixed(2);
                        document.getElementById('hMaxVal').innerText = Math.max(...activeHumids).toFixed(2);
                        document.getElementById('hMinVal').innerText = Math.min(...activeHumids).toFixed(2);

                        document.getElementById('avgHumidTrendIcon').innerText = getTrendIcon(currentAvgH, lastAvgH);
                        document.getElementById('avgHumidTrendIcon').className = getTrendClass(currentAvgH, lastAvgH);
                        lastAvgH = currentAvgH;
                    }

                    // 5️⃣ [Visuals] Chart & Gauge
                    const timeStr = new Date().toLocaleTimeString('en-GB', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    if (!trendChart.data.labels.includes(timeStr)) {
                        trendChart.data.labels.push(timeStr);
                        if (trendChart.data.labels.length > 30) trendChart.data.labels.shift();
                    }
                    trendChart.data.datasets[sID - 1].data.push(val);
                    if (trendChart.data.datasets[sID - 1].data.length > 30) trendChart.data.datasets[sID - 1].data.shift();
                    trendChart.update('none');

                    gauges[sID - 1].value = val;

                    if (typeof checkAndTriggerAlarm === 'function') {
                        checkAndTriggerAlarm();
                    }
                }
            } catch (e) {
                console.error("MQTT Message Error:", e);
            }
        });
        // ฟังก์ชันสำหรับดึงค่า Threshold ล่าสุดจาก Database (ไม่ต้องแก้โค้ดเก่า)
        async function refreshThresholdsAuto() {
            try {
                const response = await fetch('get_thresholds.php'); // ไฟล์ PHP ที่ส่งค่า JSON ออกมา
                const data = await response.json();

                data.forEach(item => {
                    const sid = parseInt(item.sensor_id);
                    const tVal = parseFloat(item.temp_threshold);
                    const hVal = parseFloat(item.humid_threshold);

                    if (sid >= 1 && sid <= 4) {
                        // ✅ อัปเดตตัวแปรในหน่วยความจำ JavaScript ทันที
                        if (individualThresholds[sid]) {
                            individualThresholds[sid].temp = tVal;
                            individualThresholds[sid].humid = hVal;
                        }

                        // ✅ อัปเดตตัวเลข T-LIMIT / H-LIMIT ที่แสดงบนหน้าจอ
                        const tLabel = document.getElementById(`tLabel${sid}`);
                        const hLabel = document.getElementById(`hLabel${sid}`);
                        if (tLabel) tLabel.innerText = tVal.toFixed(2);
                        if (hLabel) hLabel.innerText = hVal.toFixed(2);

                        // ✅ อัปเดตสีของ Gauge (ขีดสีแดง)
                        if (gauges[sid - 1]) {
                            gauges[sid - 1].update({
                                highlights: [{
                                        from: 0,
                                        to: tVal,
                                        color: "rgba(13, 224, 97, .75)"
                                    },
                                    {
                                        from: tVal,
                                        to: 100,
                                        color: "rgba(234, 22, 29, .75)"
                                    }
                                ]
                            });
                        }
                    }
                });
            } catch (error) {
                console.error("Auto-refresh Thresholds Failed:", error);
            }
        }

        // สั่งให้ดึงค่าใหม่ทุก 5 วินาที
        setInterval(refreshThresholdsAuto, 5000);
        // client.on('message', (topic, payload) => {
        //     try {
        //         const js = JSON.parse(payload.toString());
        //         const sID = parseInt(topic.split('temp')[1]);
        //         if (sID >= 1 && sID <= 4) {
        //             const val = parseFloat(js.temp),
        //                 humid = parseFloat(js.humid);

        //             // Store Latest for Global Avg
        //             latestValues.temp[sID] = val;
        //             latestValues.humid[sID] = humid;

        //             sensorLogs[sID].push(val);
        //             humidLogs[sID].push(humid);
        //             if (sensorLogs[sID].length > 100) {
        //                 sensorLogs[sID].shift();
        //                 humidLogs[sID].shift();
        //             }

        //             // Individual Updates
        //             document.getElementById(`min${sID}`).innerText = Math.min(...sensorLogs[sID]).toFixed(2);
        //             document.getElementById(`max${sID}`).innerText = Math.max(...sensorLogs[sID]).toFixed(2);
        //             document.getElementById(`avg${sID}`).innerText = (sensorLogs[sID].reduce((a, b) => a + b, 0) / sensorLogs[sID].length).toFixed(2);
        //             document.getElementById(`hmin${sID}`).innerText = Math.min(...humidLogs[sID]).toFixed(2);
        //             document.getElementById(`hmax${sID}`).innerText = Math.max(...humidLogs[sID]).toFixed(2);
        //             document.getElementById(`havg${sID}`).innerText = (humidLogs[sID].reduce((a, b) => a + b, 0) / humidLogs[sID].length).toFixed(2);
        //             document.getElementById(`hmdLive${sID}`).innerText = humid.toFixed(2);

        //             // ✅ GLOBAL CALCULATION (Topbar Avg)
        //             const activeTemps = Object.values(latestValues.temp).filter(v => v !== null);
        //             const activeHumids = Object.values(latestValues.humid).filter(v => v !== null);

        //             if (activeTemps.length > 0) {
        //                 const currentAvgT = activeTemps.reduce((a, b) => a + b, 0) / activeTemps.length;
        //                 document.getElementById('avgTempValue').innerText = currentAvgT.toFixed(2);
        //                 document.getElementById('avgTrendIcon').innerText = getTrendIcon(currentAvgT, lastAvgT);
        //                 document.getElementById('avgTrendIcon').className = getTrendClass(currentAvgT, lastAvgT);
        //                 lastAvgT = currentAvgT;
        //             }

        //             if (activeHumids.length > 0) {
        //                 const currentAvgH = activeHumids.reduce((a, b) => a + b, 0) / activeHumids.length;
        //                 document.getElementById('avgHumidValue').innerText = currentAvgH.toFixed(2);
        //                 document.getElementById('avgHumidTrendIcon').innerText = getTrendIcon(currentAvgH, lastAvgH);
        //                 document.getElementById('avgHumidTrendIcon').className = getTrendClass(currentAvgH, lastAvgH);
        //                 lastAvgH = currentAvgH;
        //             }

        //             // Global Max/Min Update
        //             if (val > gMaxT) gMaxT = val;
        //             if (val < gMinT) gMinT = val;
        //             if (humid > gMaxH) gMaxH = humid;
        //             if (humid < gMinH) gMinH = humid;
        //             document.getElementById('maxVal').innerText = gMaxT.toFixed(2);
        //             document.getElementById('minVal').innerText = gMinT.toFixed(2);
        //             document.getElementById('hMaxVal').innerText = gMaxH.toFixed(2);
        //             document.getElementById('hMinVal').innerText = gMinH.toFixed(2);

        //             // Chart Update
        //             const timeStr = new Date().toLocaleTimeString('en-GB', {
        //                 hour: '2-digit',
        //                 minute: '2-digit'
        //             });
        //             if (!trendChart.data.labels.includes(timeStr)) {
        //                 trendChart.data.labels.push(timeStr);
        //                 if (trendChart.data.labels.length > 30) trendChart.data.labels.shift();
        //             }
        //             trendChart.data.datasets[sID - 1].data.push(val);
        //             if (trendChart.data.datasets[sID - 1].data.length > 30) trendChart.data.datasets[sID - 1].data.shift();
        //             trendChart.update('none');

        //             gauges[sID - 1].value = val;
        //         }
        //     } catch (e) {}
        // });
        // ฟังก์ชันดึงค่า Threshold ใหม่จาก Database
        // ✅ 1. ฟังก์ชันดึงค่า Threshold ใหม่จาก DB มาอัปเดตตัวแปร JavaScript
        async function syncThresholds() {
            try {
                const response = await fetch('get_thresholds.php'); // ดึงค่า JSON จากไฟล์ PHP
                const data = await response.json();

                data.forEach(item => {
                    const sid = parseInt(item.sensor_id);
                    const tVal = parseFloat(item.temp_threshold);
                    const hVal = parseFloat(item.humid_threshold);

                    if (sid >= 1 && sid <= 4) {
                        // อัปเดตตัวแปรที่ใช้เช็คใน client.on('message')
                        if (individualThresholds[sid]) {
                            individualThresholds[sid].temp = tVal;
                            individualThresholds[sid].humid = hVal;
                        }

                        // อัปเดตตัวเลขขีดจำกัดที่โชว์บนหน้าจอ (UI)
                        const tLabel = document.getElementById(`tLabel${sid}`);
                        const hLabel = document.getElementById(`hLabel${sid}`);
                        if (tLabel) tLabel.innerText = tVal.toFixed(2);
                        if (hLabel) hLabel.innerText = hVal.toFixed(2);

                        // อัปเดตสีแดงใน Gauge ให้ขยับตามค่าใหม่
                        if (gauges && gauges[sid - 1]) {
                            gauges[sid - 1].update({
                                highlights: [{
                                        from: 0,
                                        to: tVal,
                                        color: "rgba(13, 224, 97, .75)"
                                    },
                                    {
                                        from: tVal,
                                        to: 100,
                                        color: "rgba(234, 22, 29, .75)"
                                    }
                                ]
                            });
                        }
                    }
                });
                console.log("🔄 Thresholds Synced Automatically");
            } catch (error) {
                console.error("Sync Error:", error);
            }
        }

        // ✅ 2. สั่งให้ทำงานอัตโนมัติทุกๆ 5 วินาที
        setInterval(syncThresholds, 5000);
        async function refreshThresholds() {
            try {
                const response = await fetch('get_thresholds.php');
                const data = await response.json();

                data.forEach(item => {
                    const sid = parseInt(item.sensor_id);
                    const tVal = parseFloat(item.temp_threshold).toFixed(2);
                    const hVal = parseFloat(item.humid_threshold).toFixed(2);

                    if (sid === 0) {
                        // อัปเดต Global Threshold ใน Topbar
                        document.getElementById('dispTempLimit').innerText = tVal;
                        document.querySelector('.topbar span[style*="color:#a855f7"]').innerText = hVal;

                        // อัปเดตตัวแปร Global เผื่อใช้คำนวณอย่างอื่น
                        db_temp_limit = parseFloat(tVal);
                        db_humid_limit = parseFloat(hVal);
                    } else if (sid >= 1 && sid <= 4) {
                        // อัปเดต T-LIMIT และ H-LIMIT ในแต่ละ Card
                        document.getElementById(`tLabel${sid}`).innerText = tVal;
                        document.getElementById(`hLabel${sid}`).innerText = hVal;

                        // อัปเดตค่าขีดจำกัดในตัวแปร individualThresholds เพื่อให้ Gauge เปลี่ยนสี Highlights ตาม
                        if (individualThresholds[sid]) {
                            individualThresholds[sid].temp = parseFloat(tVal);
                            individualThresholds[sid].humid = parseFloat(hVal);

                            // อัปเดต Highlights ของ Gauge ทันที
                            gauges[sid - 1].update({
                                highlights: [{
                                        from: 0,
                                        to: tVal,
                                        color: "rgba(13, 224, 97, .75)"
                                    },
                                    {
                                        from: tVal,
                                        to: 100,
                                        color: "rgba(234, 22, 29, .75)"
                                    }
                                ]
                            });
                        }
                    }
                });
                console.log("Thresholds updated at " + new Date().toLocaleTimeString());
            } catch (error) {
                console.error("Error refreshing thresholds:", error);
            }
        }
        // ฟังก์ชันดึงค่าจาก DB มาอัปเดตตัวแปรใน JS ตลอดเวลา
        async function syncThresholdsFromDB() {
            try {
                const response = await fetch('get_thresholds.php');
                const data = await response.json();

                data.forEach(item => {
                    const sid = parseInt(item.sensor_id);
                    if (sid >= 1 && sid <= 4) {
                        // อัปเดตตัวแปรในหน่วยความจำ (สำคัญมาก!)
                        individualThresholds[sid].temp = parseFloat(item.temp_threshold);
                        individualThresholds[sid].humid = parseFloat(item.humid_threshold);

                        // อัปเดตตัวเลขขีดจำกัดบนหน้าจอให้ Operator เห็น
                        const tLabel = document.getElementById(`tLabel${sid}`);
                        const hLabel = document.getElementById(`hLabel${sid}`);
                        if (tLabel) tLabel.innerText = individualThresholds[sid].temp.toFixed(2);
                        if (hLabel) hLabel.innerText = individualThresholds[sid].humid.toFixed(2);

                        // อัปเดตสี Gauge
                        if (gauges[sid - 1]) {
                            gauges[sid - 1].update({
                                highlights: [{
                                        from: 0,
                                        to: individualThresholds[sid].temp,
                                        color: "rgba(13, 224, 97, .75)"
                                    },
                                    {
                                        from: individualThresholds[sid].temp,
                                        to: 100,
                                        color: "rgba(234, 22, 29, .75)"
                                    }
                                ]
                            });
                        }
                    }
                });
            } catch (e) {
                console.error("Sync Error:", e);
            }
        }

        // สั่งให้ Sync ทุก 5 วินาที
        setInterval(syncThresholdsFromDB, 5000);
        // สั่งให้ทำงานทุกๆ 5 วินาที (5000 ms)
        //etInterval(refreshThresholds, 5000);
        setInterval(() => {
            document.getElementById('realtimeClock').innerText = new Date().toLocaleString('th-TH');
        }, 1000);
    </script>
</body>

</html>