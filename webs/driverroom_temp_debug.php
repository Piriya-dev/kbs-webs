<?php
/**
 * mqtt_debug_monitor.php
 * Cleaned version with dynamic topic subscription
 */
$config = require 'config.php';

// Extracting credentials from your existing config structure
$mqtt_ws_url = $config['mqtt_ws_url'];
$mqtt_user   = $config['mqtt_user'];
$mqtt_pass   = $config['mqtt_pass'];

// Map your 3 newer topics here
$topic1 = "kbs/driveroom1/temp1";
$topic2 = "kbs/driveroom1/temp2";
$topic3 = "kbs/driveroom1/temp3";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MQTT JSON Live Debugger</title>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --accent: #38bdf8; --terminal: #000; }
        body { background: var(--bg); color: #f1f5f9; font-family: 'Cascadia Code', 'Courier New', monospace; margin: 0; padding: 20px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; padding-bottom: 10px; margin-bottom: 20px; }
        .status { font-size: 14px; padding: 5px 15px; border-radius: 20px; border: 1px solid #475569; }
        .status.online { border-color: #22c55e; color: #22c55e; box-shadow: 0 0 10px rgba(34,197,94,0.2); }

        .layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .panel { background: var(--card); border-radius: 12px; padding: 20px; border: 1px solid #334155; }
        
        /* Terminal Style Logger */
        #terminal { height: 450px; overflow-y: auto; background: var(--terminal); color: #10b981; padding: 15px; border-radius: 8px; font-size: 13px; line-height: 1.4; }
        .log-entry { margin-bottom: 8px; border-bottom: 1px solid #111; padding-bottom: 4px; }
        .meta { color: #64748b; font-size: 11px; }

        /* Real-time Status Table */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; color: var(--accent); font-size: 12px; text-transform: uppercase; padding: 10px; border-bottom: 1px solid #334155; }
        td { padding: 12px 10px; border-bottom: 1px solid #1e293b; font-size: 14px; }
        .temp-val { font-size: 18px; font-weight: bold; color: #fb7185; }
        .pulse { animation: flash 0.5s ease-out; }
        @keyframes flash { from { background: #38bdf822; } to { background: transparent; } }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1 style="margin:0; font-size: 20px;">MQTT Inspector <span style="font-weight:normal; color:#64748b;">(v2.0)</span></h1>
        <div style="font-size: 12px; margin-top:5px; color:#94a3b8;">Broker: <?php echo $mqtt_ws_url; ?></div>
    </div>
    <div id="mqttStatus" class="status">DISCONNECTED</div>
</div>

<div class="layout">
    <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h3 style="margin:0;">Incoming Stream</h3>
            <button onclick="document.getElementById('terminal').innerHTML=''" style="background:transparent; border:1px solid #475569; color:#94a3b8; cursor:pointer; font-size:10px; padding:2px 8px; border-radius:4px;">Clear</button>
        </div>
        <div id="terminal">> Initializing connection...</div>
    </div>

    <div class="panel">
        <h3 style="margin:0; margin-bottom:10px;">Sensor Snapshot</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Room / Sensor</th>
                    <th>Temp</th>
                    <th>Last Update</th>
                </tr>
            </thead>
            <tbody id="statBody">
                </tbody>
        </table>
    </div>
</div>



<script>
const CONFIG = {
    url: '<?php echo $mqtt_ws_url; ?>',
    user: '<?php echo $mqtt_user; ?>',
    pass: '<?php echo $mqtt_pass; ?>',
    topics: [
        '<?php echo $topic1; ?>',
        '<?php echo $topic2; ?>',
        '<?php echo $topic3; ?>'
    ]
};

const client = mqtt.connect(CONFIG.url, {
    username: CONFIG.user,
    password: CONFIG.pass,
    clientId: 'debug_' + Math.random().toString(16).substr(2, 6)
});

const terminal = document.getElementById('terminal');
const statusBadge = document.getElementById('mqttStatus');

client.on('connect', () => {
    statusBadge.innerText = 'ONLINE';
    statusBadge.classList.add('online');
    terminal.innerHTML = '<div style="color:#22c55e">> Broker Connected. Subscribing to topics...</div>';
    
    CONFIG.topics.forEach(t => {
        client.subscribe(t);
        terminal.innerHTML += `<div style="color:#38bdf8">> Subscribed: ${t}</div>`;
    });
});

client.on('message', (topic, payload) => {
    const raw = payload.toString();
    const time = new Date().toLocaleTimeString();
    
    // 1. Terminal Logging
    const entry = document.createElement('div');
    entry.className = 'log-entry';
    entry.innerHTML = `<span class="meta">[${time}] ${topic}</span><br><code>${raw}</code>`;
    terminal.prepend(entry);
    if (terminal.childNodes.length > 15) terminal.removeChild(terminal.lastChild);

    // 2. Table Updates
    try {
        const js = JSON.parse(raw);
        if(js.sensor_id) {
            updateUI(js, time);
        }
    } catch(e) {
        entry.style.color = '#ef4444';
        entry.innerHTML += `<br>!! JSON Parse Error`;
    }
});

function updateUI(data, time) {
    const rowId = `row-${data.sensor_id}`;
    let row = document.getElementById(rowId);
    
    if(!row) {
        row = document.createElement('tr');
        row.id = rowId;
        document.getElementById('statBody').appendChild(row);
    }
    
    row.className = 'pulse';
    row.innerHTML = `
        <td><span style="color:#94a3b8">#</span>${data.sensor_id}</td>
        <td>
            <div style="font-weight:bold">${data.room_name || 'Room'}</div>
            <div style="font-size:11px; color:#64748b">${data.sensor_name || 'Sensor'}</div>
        </td>
        <td class="temp-val">${data.temp} <small>°C</small></td>
        <td style="font-size:11px; color:#64748b">${time}</td>
    `;
    
    setTimeout(() => row.classList.remove('pulse'), 500);
}

client.on('error', (err) => {
    statusBadge.innerText = 'CONNECTION ERROR';
    statusBadge.style.color = '#ef4444';
    console.error(err);
});
</script>
</body>
</html>