<?php
/**
 * motor_drive_status_debug.php - 100% COMPLETE
 * Features: Live Data + Global/Individual Threshold + Timestamps + Status Comparison
 */
$config = require 'config.php';
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}

// ✅ 1. ดึงค่า Threshold จาก DB ทั้งหมด
$db_global = ['temp' => 24.0, 'humid' => 60.0];
$db_sensors = []; 
for($i=1; $i<=4; $i++) { $db_sensors[$i] = ['t_limit' => 24.0, 'h_limit' => 60.0]; }

try {
    $db_host = '127.0.0.1'; $db_name = 'kbs_eng_db'; $db_user = 'kbs-ccsonline'; $db_pass = '@Kbs2024!#';                   
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $stmt = $conn->query("SELECT sensor_id, temp_threshold, humid_threshold FROM threshold_configs");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($r['sensor_id'] == 0) {
            $db_global['temp'] = (float)$r['temp_threshold'];
            $db_global['humid'] = (float)$r['humid_threshold'];
        } else if ($r['sensor_id'] >= 1 && $r['sensor_id'] <= 4) {
            $db_sensors[$r['sensor_id']] = [
                't_limit' => (float)$r['temp_threshold'],
                'h_limit' => (float)$r['humid_threshold']
            ];
        }
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <title>JSON Debug - KBS Monitoring</title>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <style>
        body { background: #0f172a; color: #22c55e; font-family: monospace; padding: 20px; margin: 0; }
        .nav-header { background: #1e293b; padding: 15px; margin: -20px -20px 20px -20px; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .back-btn { background: #3b82f6; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-family: sans-serif; font-weight: bold; font-size: 14px; }
        .debug-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: #1e293b; border-radius: 10px; overflow: hidden; border: 1px solid #334155; }
        .debug-table th { background: #334155; color: #94a3b8; padding: 12px; text-align: left; font-family: sans-serif; font-size: 11px; }
        .debug-table td { padding: 12px; border-bottom: 1px solid #334155; color: #fff; font-size: 14px; }
        .val-box { color: #22c55e; font-weight: bold; font-size: 16px; }
        .limit-box { color: #f59e0b; font-size: 13px; font-weight: bold; }
        
        /* Status Badges */
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-ok { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid #22c55e; }
        .status-warn { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; animation: pulse 1s infinite; }
        .status-humid { background: rgba(168, 85, 247, 0.2); color: #a855f7; border: 1px solid #a855f7; }

        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        pre { background: #1e293b; padding: 15px; border-radius: 10px; border: 1px solid #334155; line-height: 1.6; }
    </style>
</head>
<body>

    <div class="nav-header">
        <span style="color: #fff; font-family: sans-serif; font-weight: bold;">🔍 Debugging Hub: Threshold Comparison Logic</span>
        <a href="/motor_drive_room_dashboard" class="back-btn">⬅ Back to Dashboard</a>
    </div>

    <div style="background: #1e293b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #334155; display: flex; gap: 30px;">
        <div><small style="color: #64748b;">GLOBAL TEMP SET:</small> <span style="color: #f59e0b;"><?php echo $db_global['temp']; ?>°C</span></div>
        <div><small style="color: #64748b;">GLOBAL HUMID SET:</small> <span style="color: #a855f7;"><?php echo $db_global['humid']; ?>%</span></div>
    </div>

    <table class="debug-table">
        <thead>
            <tr>
                <th>Sensor</th>
                <th>Live Temp</th>
                <th>Threshold (T)</th>
                <th>Live Humid</th>
                <th>Threshold (H)</th>
                <th>✅ Status Check</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php for($i=1; $i<=4; $i++): ?>
            <tr>
                <td style="color:#3b82f6; font-weight:bold;">ID 0<?php echo $i; ?></td>
                <td id="t_val_<?php echo $i; ?>" class="val-box">--</td>
                <td id="t_limit_<?php echo $i; ?>" class="limit-box"><?php echo $db_sensors[$i]['t_limit']; ?>°C</td>
                <td id="h_val_<?php echo $i; ?>" class="val-box" style="color:#10b981;">--</td>
                <td id="h_limit_<?php echo $i; ?>" class="limit-box" style="color:#a855f7;"><?php echo $db_sensors[$i]['h_limit']; ?>%</td>
                <td><span id="status_<?php echo $i; ?>" class="status-badge status-ok">Waiting</span></td>
                <td id="time_<?php echo $i; ?>" style="color:#64748b; font-size:11px;">--</td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <pre id="jsonContent">Initializing JSON Payload...</pre>

    <script>
        let systemData = {
            sensors: {
                <?php for($i=1; $i<=4; $i++): ?>
                <?php echo $i; ?>: { temp: 0, t_limit: <?php echo $db_sensors[$i]['t_limit']; ?>, humid: 0, h_limit: <?php echo $db_sensors[$i]['h_limit']; ?>, status: "N/A" },
                <?php endfor; ?>
            }
        };

        const MQTT_CONFIG = {
            url: '<?php echo $config["mqtt_ws_url"]; ?>',
            user: '<?php echo $config["mqtt_user"]; ?>',
            pass: '<?php echo $config["mqtt_pass"]; ?>',
            topics: ['kbs/driveroom1/temp1','kbs/driveroom1/temp2','kbs/driveroom1/temp3','kbs/driveroom1/temp4','kbs/motordriveroom1/config_individual']
        };

        const client = mqtt.connect(MQTT_CONFIG.url, { username: MQTT_CONFIG.user, password: MQTT_CONFIG.pass, clientId: 'KBS_DBG_'+Math.random().toString(16).substr(2,5) });

        client.on('connect', () => { MQTT_CONFIG.topics.forEach(t => client.subscribe(t)); });

        client.on('message', (topic, payload) => {
            try {
                const js = JSON.parse(payload.toString());
                const now = new Date().toLocaleTimeString('en-GB');

                if (topic.includes('config_individual')) {
                    if (js.type === 'individual') {
                        js.sensors.forEach(s => {
                            if(systemData.sensors[s.sensor_id]) {
                                systemData.sensors[s.sensor_id].t_limit = s.temp_threshold;
                                systemData.sensors[s.sensor_id].h_limit = s.humid_threshold;
                                document.getElementById(`t_limit_${s.sensor_id}`).innerText = s.temp_threshold + "°C";
                                document.getElementById(`h_limit_${s.sensor_id}`).innerText = s.humid_threshold + "%";
                            }
                        });
                    }
                } else {
                    const sID = topic.match(/\d+$/)[0];
                    if (systemData.sensors[sID]) {
                        const s = systemData.sensors[sID];
                        s.temp = parseFloat(js.temp);
                        s.humid = parseFloat(js.humid);
                        
                        // ✅ Logic ตรวจสอบ Status
                        let statusText = "NORMAL";
                        let statusClass = "status-badge status-ok";

                        if (s.temp > s.t_limit && s.humid > s.h_limit) {
                            statusText = "CRITICAL!!";
                            statusClass = "status-badge status-warn";
                        } else if (s.temp > s.t_limit) {
                            statusText = "OVER TEMP";
                            statusClass = "status-badge status-warn";
                        } else if (s.humid > s.h_limit) {
                            statusText = "OVER HUMID";
                            statusClass = "status-badge status-humid";
                        }

                        s.status = statusText;

                        // UI Update
                        document.getElementById(`t_val_${sID}`).innerText = js.temp;
                        document.getElementById(`h_val_${sID}`).innerText = js.humid;
                        document.getElementById(`time_${sID}`).innerText = now;
                        
                        const statusEl = document.getElementById(`status_${sID}`);
                        statusEl.innerText = statusText;
                        statusEl.className = statusClass;
                    }
                }
                document.getElementById('jsonContent').innerText = JSON.stringify(systemData, null, 4);
            } catch (e) {}
        });
    </script>
</body>
</html>