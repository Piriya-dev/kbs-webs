<?php
/**
 * /opt/lampp/htdocs/pages/gis/station_status_dashboard.php
 * Main Dashboard - Logic & UI
 */

// Load the private configuration
$config = require 'config.php';

$google_maps_api_key = $config['google_maps_api_key'];
$mqtt_ws_url         = $config['mqtt_ws_url'];
$mqtt_user           = $config['mqtt_user'];
$mqtt_pass           = $config['mqtt_pass'];

// Map the topics
$mqtt_topic1 = $config['topics']['sikhio'];
$mqtt_topic2 = $config['topics']['korn1'];
$mqtt_topic3 = $config['topics']['korn2'];
$mqtt_topic4 = $config['topics']['kpp'];

$api_points  = $config['api']['points'];
$api_latest  = $config['api']['latest'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8" />
<title>KBS-Fire pump status Monitoring (Beta)</title>
<link rel="icon" type="image/webp" href="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{
    --bg:#edf2f7; --nav-bg:#0f172a; --nav-active:#1d4ed8;
    --panel:#ffffff; --panel-soft:#f8fafc; --ink:#0f172a;
    --muted:#6b7280; --border:#e2e8f0; --good:#16a34a;
    --bad:#dc2626; --accent:#2563eb; --accent-soft:#dbeafe;
    --shadow:0 18px 40px rgba(15,23,42,.18);
  }
  *{box-sizing:border-box;}
  html,body{ height:100%; margin:0; background:var(--bg); color:var(--ink); font-family:system-ui,-apple-system,sans-serif; }
  .app{display:flex;height:100vh;width:100vw;overflow:hidden;}

/* --- PROFESSIONAL NAV UPGRADE --- */
.nav {
    width: 72px;
    background: #0f172a; 
    color: #94a3b8;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px 0;
    gap: 8px;
    flex-shrink: 0;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-right: 1px solid rgba(255,255,255,0.05);
}

.nav-logo {
    width: 48px;
    height: 48px;
    background: #ffffff;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    margin-bottom: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}

.nav-logo img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.nav-item {
    width: calc(100% - 20px);
    height: 48px;
    border-radius: 12px;
    display: flex;
    flex-direction: row; /* Fixed: Icon and span side by side */
    align-items: center;
    justify-content: center; 
    gap: 15px;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.nav-item-icon {
    font-size: 18px;
    min-width: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-item span {
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    display: none;
    opacity: 0;
}

.nav-item:hover {
    background: rgba(255,255,255,0.08);
    color: #fff;
}

.nav-item.active {
    background: rgba(29, 78, 216, 0.2);
    color: #3b82f6;
}

.nav-item.active::before {
    content: "";
    position: absolute;
    left: 0;
    height: 24px;
    width: 4px;
    background: #3b82f6;
    border-radius: 0 4px 4px 0;
}

/* --- TOOLTIP SYSTEM --- */
.nav-item::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 80px;
    background: #1e293b;
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    z-index: 9999;
}

.nav-item::before {
    content: '';
    position: absolute;
    left: 74px;
    border: 6px solid transparent;
    border-right-color: #1e293b;
    opacity: 0;
    transition: all 0.2s ease;
    z-index: 9999;
}

body:not(.nav-expanded) .nav-item:hover::after,
body:not(.nav-expanded) .nav-item:hover::before {
    opacity: 1;
    left: 75px;
}

body:not(.nav-expanded) .nav-item:hover::before {
    left: 63px;
}

body.nav-expanded .nav-item::after,
body.nav-expanded .nav-item::before {
    display: none;
}

/* --- EXPANDED STATE LOGIC --- */
body.nav-expanded .nav {
    width: 240px;
    align-items: flex-start;
}

body.nav-expanded .nav-logo {
    margin-left: 20px;
}

body.nav-expanded .nav-item {
    justify-content: flex-start;
    padding-left: 15px;
    margin-left: 10px;
}

body.nav-expanded .nav-item span {
    display: block;
    opacity: 1;
}

  .main{ flex:1; display:flex; flex-direction:column; min-width:0; overflow:hidden; }
  .topbar{ height:60px; display:flex; justify-content:space-between; align-items:center; padding:0 22px; border-bottom:1px solid var(--border); background:rgba(255,255,255,0.95); backdrop-filter:blur(10px); flex-shrink:0; }

  .content{ flex:1; padding:16px 18px 18px; min-height:0; overflow:hidden; }
  .card{ background:var(--panel); border-radius:18px; box-shadow:var(--shadow); padding:14px 16px 16px; display:flex; flex-direction:column; gap:10px; }
  .card-title{font-size:14px;font-weight:700;}

  /* MAPS */
  #pageMap{display:flex;}
  .maps-wrap{display:flex;width:100%;gap:14px;}
  .map-card{padding:0;overflow:hidden;flex:1;min-height:0;}
  .map-card-header{ padding:12px 14px 8px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; cursor:pointer; }
  .map-inner{position:relative;flex:1;min-height:0;}
  #mapA,#mapB{width:100%;height:100%;}

  /* SUMMARY */
  #pageSummary{display:none;}
  .metric-card{ flex:1;min-width:150px;background:var(--panel-soft); border-radius:14px;padding:8px 10px;display:flex;flex-direction:column;gap:2px; }
  .metric-value{font-size:18px;font-weight:700;}

  /* TEMP CARDS */
  #pageTemp{display:none;}
  .temp-board{ position:relative; border-radius:14px; background:var(--panel-soft); overflow:visible; padding:20px; min-height:400px; }
  .temp-card{ position:absolute; width:260px; background:#fff; border-radius:16px; padding:10px 12px; display:flex; align-items:center; gap:10px; box-shadow:0 12px 25px rgba(15,23,42,.18); }
  .temp-bulb{ width:28px;height:28px;border-radius:999px; background:#9ca3af; }
  .temp-bulb.ok{background:#22c55e;box-shadow:0 0 14px rgba(34,197,94,.85);}
  .temp-bulb.bad{background:#ef4444;box-shadow:0 0 14px rgba(239,68,68,.9);}

  /* RAW DEBUG MONITOR */
  #pageRaw{display:none;}
  .temp-raw-box{
    background:#0f172a; color:#10b981; border-radius:12px; padding:15px;
    font-family:ui-monospace, monospace; font-size:12px; line-height:1.6;
    max-height:550px; overflow:auto; white-space:pre-wrap; border:1px solid #1e293b;
  }

  /* INSERT DATA PAGE */
  #pageInsert{display:none;}
  .json-preview{ background:#0f172a; color:#10b981; padding:15px; border-radius:8px; overflow:auto; min-height:150px; font-family:monospace; }

  /* SETTINGS */
  #pageSettings{display:none;}
  .mqttDot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;background:#9ca3af;}
  .mqttDot.ok{background:#22c55e;}
</style>
</head>
<body class="nav-expanded">
<div id="diag" style="display:none"></div>

<div class="app">
  <?php include 'sidebar.php'; ?>
 
  <div class="main">
    <header class="topbar">
      <div style="display:flex; align-items:center; gap:12px;">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" 
             alt="KBS Logo" 
             style="height:32px; width:auto; object-fit:contain;">
        <div style="font-weight:700;">KBS-Fire pump status Monitoring (Beta)</div>
      </div>
      
      <div class="topbar-right">
        <span class="topbar-pill">MQTT Live</span>
        <span>4-Stations Fire Pump Realtime Monitoring</span>
      </div>
    </header>

    <div class="content" id="pageMap">
      <div class="maps-wrap" id="mapsWrap">
        <section class="card map-card" id="mapCardA">
          <div class="map-card-header"><div><div class="map-card-title">Sikhio</div></div></div>
          <div class="map-inner"><div id="mapA"></div></div>
        </section>
        <section class="card map-card" id="mapCardB">
          <div class="map-card-header"><div><div class="map-card-title">Kornburi</div></div></div>
          <div class="map-inner"><div id="mapB"></div></div>
        </section>
      </div>
    </div>

    <div class="content" id="pageSummary">
        <section class="card">
            <div style="display:flex; gap:10px;">
                <div class="metric-card"><div>Stations</div><div class="metric-value" id="sumTotal">0</div></div>
                <div class="metric-card"><div>Normal</div><div class="metric-value" id="sumNormal">0</div></div>
                <div class="metric-card"><div>Alert</div><div class="metric-value" id="sumAlert">0</div></div>
            </div>
        </section>
        <section class="card" style="flex:1; overflow:auto;">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Temp1</th><th>Vib1</th><th>Time</th></tr></thead>
                <tbody id="summaryTableBody"></tbody>
            </table>
        </section>
    </div>

    <div class="content" id="pageTemp">
      <section class="card">
        <div class="card-header"><div class="card-title">Realtime Gauges</div><span id="tempCountLabel"></span></div>
        <div class="temp-board" id="tempBoard"></div>
      </section>
    </div>

    <div class="content" id="pageRaw">
      <section class="card">
        <div class="card-header"><div class="card-title">IIoT Multi-Topic Monitor</div></div>
        <div class="temp-raw">
          <pre id="mqttRawBox" class="temp-raw-box">Waiting for MQTT broadcast...</pre>
        </div>
      </section>
    </div>

    <div class="content" id="pageInsert">
      <section class="card">
        <div class="card-header"><div class="card-title">Insert MQTT Record to Database</div></div>
        <div style="background:#f8fafc; padding:20px; border-radius:12px;">
            <p style="margin-bottom:10px; font-weight:600;">Latest Incoming JSON Payload:</p>
            <pre id="insertJsonPreview" class="json-preview">Waiting for MQTT broadcast...</pre>
            <button id="btnInsertRecord" onclick="insertFirepumpRecord()" style="margin-top:15px; background:var(--accent); color:#fff; border:none; padding:12px 20px; border-radius:8px; cursor:pointer;">Save to Database (firepump_station_status)</button>
        </div>
      </section>
    </div>

    <div class="content" id="pageSettings">
      <div class="settings-content">
        <section class="card">
          <div class="toolbar">
            <div class="row"><span id="mqttDot" class="mqttDot"></span><strong id="mqttState">Disconnected</strong></div>
            <div class="row">
                <input id="searchName" placeholder="Search..." style="flex:1" />
                <button id="btnReload">Reload Pins</button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_maps_api_key; ?>&libraries=drawing&callback=initMap"></script>

<script>
/* === CONFIG === */
const MQTT_WS_URL = '<?php echo $mqtt_ws_url; ?>';
const MQTT_USER   = '<?php echo $mqtt_user; ?>';
const MQTT_PASS   = '<?php echo $mqtt_pass; ?>';
const MQTT_TOPICS = [
  '<?php echo $mqtt_topic1; ?>',
  '<?php echo $mqtt_topic2; ?>',
  '<?php echo $mqtt_topic3; ?>',
  '<?php echo $mqtt_topic4; ?>'
];

const API_POINTS = '<?php echo $api_points; ?>';
const DEFAULT_CENTER = {lat:14.9, lng:102.1};

/* STATE */
const pinById = new Map();
const pinConfig = new Map();
const readingMqttByStation = new Map();
const mqttDebugStore = {}; 
let latestMqttJson = null; 
let lastTempIds = [];

/* MQTT CONNECT & MULTI-TOPIC LOGIC */
let mqttClient = null;

function mqttConnect(){
  try {
    if(mqttClient){ try{mqttClient.end(true);}catch(_){} }
    mqttClient = mqtt.connect(MQTT_WS_URL, {
      clientId: 'web-' + Math.random().toString(16).slice(2,8),
      username: MQTT_USER, password: MQTT_PASS,
      keepalive: 60, reconnectPeriod: 2000
    });

    mqttClient.on('connect', () => {
      const dot = document.getElementById('mqttDot');
      const state = document.getElementById('mqttState');
      if(dot) dot.classList.add('ok');
      if(state) state.textContent = 'Connected';
      MQTT_TOPICS.forEach(t => mqttClient.subscribe(t));
    });

    mqttClient.on('message', (topic, payload) => {
      const raw = payload.toString();
      updateDebugMonitor(topic, raw);

      try {
        const js = JSON.parse(raw);
        latestMqttJson = js; 

        const preview = document.getElementById('insertJsonPreview');
        if(preview) preview.textContent = JSON.stringify(js, null, 2);

        // --- NEW ROUTING LOGIC BASED ON YOUR NODE-RED DATA ---
        let sid = null;
        
        if (js.site_name === "Srikhio") {
            sid = 101; // Site 1
        } else if (js.site_name === "Kronburi") {
            // Distinguish between Rail AB (station_id 1) and Rail C (station_id 2)
            sid = (js.station_id === 1) ? 102 : 103; 
        } else if (js.site_name === "KPP") {
            sid = 104; // Site 3
        }

        if (!sid) return;

        // Update the Real-time Widget (The 3-site cards)
        if (typeof updateWidgetUI === "function") {
            updateWidgetUI(sid, js);
        }

        // Calculate status and store readings
        const v1 = parseFloat(js.sensor_vib1) || 0;
        const t1 = parseFloat(js.sensor_temp1) || 0;
        
        // Vibration > 0.5 or Temp > 50 triggers Alert status
        let status = (v1 > 0.5 || t1 > 50) ? 1 : 0;

        readingMqttByStation.set(sid, {
          status: status,
          temp: t1, 
          vibration: v1,
          details: js,
          timestamp: new Date().toISOString()
        });

        // Global UI Refreshes
        refreshMarkersFromReadings();
        renderTempPage();
        renderTable();
        
      } catch (e) { 
        console.error('Parse error', e); 
      }
    });
  } catch (e) { 
    console.error('Connect error', e); 
  }
}
/* DATABASE INSERTION LOGIC */
async function insertFirepumpRecord() {
    if (!latestMqttJson) {
        alert("No data to save. Waiting for MQTT message...");
        return;
    }

    const record = {
        site_name: latestMqttJson.site_name,
        station_name: latestMqttJson.station_id,
        value1: parseFloat(latestMqttJson.sensor_vib1) || 0,
        value2: parseFloat(latestMqttJson.sensor_temp1) || 0,
        value3: parseFloat(latestMqttJson.sensor_vib2) || 0,
        value4: parseFloat(latestMqttJson.sensor_temp2) || 0,
        lat: parseFloat(latestMqttJson.lat) || 0,
        long: parseFloat(latestMqttJson.long) || 0
    };

    try {
        const response = await fetch(API_POINTS + '?action=insert_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(record)
        });
        const result = await response.json();
        if(result.success) {
            alert("Record successfully saved to firepump_station_status!");
        } else {
            alert("Error: " + (result.message || "Failed to save record"));
        }
    } catch (e) {
        console.error("Insert failed", e);
        alert("Connection error while saving record.");
    }
}

/* DEBUG MONITOR */
function updateDebugMonitor(topic, raw) {
  const box = document.getElementById('mqttRawBox');
  if (!box) return;
  const now = new Date();
  mqttDebugStore[topic] = {
    dateTime: now.toLocaleDateString('th-TH') + ' ' + now.toLocaleTimeString('th-TH'),
    data: raw
  };
  let output = "=== IIoT LIVE SYSTEM MONITOR ===\n\n";
  Object.keys(mqttDebugStore).sort().forEach(t => {
    const entry = mqttDebugStore[t];
    output += `TOPIC: [${t}]\nLAST_SEEN: ${entry.dateTime}\n`;
    try {
      output += `PAYLOAD: ${JSON.stringify(JSON.parse(entry.data), null, 2)}\n`;
    } catch(e) { output += `RAW_DATA: ${entry.data}\n`; }
    output += "\n" + "=".repeat(45) + "\n\n";
  });
  box.textContent = output;
}

/* MAPS & PIN LOGIC */
let maps = [];
let activePolygons = [null, null];

window.initMap = function(){
    const opts = { center: DEFAULT_CENTER, zoom: 8 };
    maps[0] = new google.maps.Map(document.getElementById('mapA'), opts);
    maps[1] = new google.maps.Map(document.getElementById('mapB'), opts);
    loadPins();
    mqttConnect();
    initDrawingTools();
};

function initDrawingTools() {
    maps.forEach((map, index) => {
        const dm = new google.maps.drawing.DrawingManager({
            drawingMode: null,
            drawingControl: true,
            drawingControlOptions: {
                position: google.maps.ControlPosition.TOP_CENTER,
                drawingModes: ['polygon']
            },
            polygonOptions: { fillColor: "#3b82f6", fillOpacity: 0.3, strokeWeight: 2, editable: true }
        });
        dm.setMap(map);
        google.maps.event.addListener(dm, 'polygoncomplete', (poly) => syncPolygon(poly, index));
    });
}

function syncPolygon(sourcePoly, sourceIdx) {
    const path = sourcePoly.getPath().getArray();
    const otherIdx = sourceIdx === 0 ? 1 : 0;
    activePolygons.forEach(p => p && p.setMap(null));
    activePolygons[sourceIdx] = sourcePoly;
    activePolygons[otherIdx] = new google.maps.Polygon({
        path: path, map: maps[otherIdx], fillColor: "#3b82f6", fillOpacity: 0.3, strokeWeight: 2
    });
}

async function loadPins() {
    try {
        const res = await fetch(API_POINTS + '?action=list');
        const js = await res.json();
        const rows = js.data || [];
        pinById.forEach(m => m.setMap(null));
        pinById.clear(); pinConfig.clear();
        const boundsA = new google.maps.LatLngBounds(), boundsB = new google.maps.LatLngBounds();
        let hasA = false, hasB = false;
        rows.forEach(r => {
            const zone = r.lng > 102.1 ? 1 : 0;
            const pos = {lat: Number(r.lat), lng: Number(r.lng)};
            const marker = new google.maps.Marker({ position: pos, map: maps[zone] });
            marker._row = r;
            pinById.set(Number(r.id), marker);
            if(r.station_id) pinConfig.set(Number(r.id), {station_id: Number(r.station_id)});
            if (zone === 0) { boundsA.extend(pos); hasA = true; } 
            else { boundsB.extend(pos); hasB = true; }
            updateMarkerVisual(marker);
        });
        if (hasA) maps[0].fitBounds(boundsA);
        if (hasB) maps[1].fitBounds(boundsB);
    } catch(e) { console.error('Load Pins Error:', e); }
}

function updateMarkerVisual(marker){
    const sid = pinConfig.get(marker._row.id)?.station_id;
    const read = readingMqttByStation.get(Number(sid));
    const status = read ? read.status : marker._row.status;
    marker.setIcon(status == 1 ? {url:'http://maps.google.com/mapfiles/ms/icons/red-dot.png'} : {url:'http://maps.google.com/mapfiles/ms/icons/green-dot.png'});
}
function refreshMarkersFromReadings(){ pinById.forEach(m => updateMarkerVisual(m)); }

/* UI TAB LOGIC (Master Highlight Fix) */
function showPage(id) {
    const pages = ['pageMap', 'pageSummary', 'pageTemp', 'pageRaw', 'pageInsert', 'pageSettings'];
    pages.forEach(p => {
        const el = document.getElementById(p);
        if (el) el.style.display = 'none';
    });
    const targetPage = document.getElementById(id);
    if (targetPage) targetPage.style.display = (id === 'pageMap') ? 'flex' : 'block';

    // Highlight update
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    const navId = 'nav' + id.replace('page', '');
    const activeNav = document.getElementById(navId);
    if (activeNav) activeNav.classList.add('active');
}

/* GAUGES & TABLES */
function renderTempPage(){
    const board = document.getElementById('tempBoard');
    if (!board) return;
    const ids = Array.from(readingMqttByStation.keys()).sort();
    if(ids.length !== lastTempIds.length){
        board.innerHTML = '';
        ids.forEach((id, idx) => {
            const card = document.createElement('div');
            card.className = 'temp-card';
            card.style.left = (10 + (idx%2)*280) + 'px';
            card.style.top = (10 + Math.floor(idx/2)*160) + 'px';
            card.innerHTML = `<div class="temp-bulb" id="bulb-${id}"></div><div><b>Station ${id}</b><br>Temp: <span id="v-t-${id}">-</span>°C</div>`;
            board.appendChild(card);
        });
        lastTempIds = ids;
    }
    ids.forEach(id => {
        const data = readingMqttByStation.get(id);
        const valEl = document.getElementById(`v-t-${id}`);
        const bulbEl = document.getElementById(`bulb-${id}`);
        if(valEl) valEl.textContent = data.temp;
        if(bulbEl) bulbEl.className = 'temp-bulb ' + (data.status === 1 ? 'bad' : 'ok');
    });
}

function renderTable(){
    const tbody = document.getElementById('summaryTableBody');
    if(!tbody) return;
    tbody.innerHTML = '';
    readingMqttByStation.forEach((v, sid) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${sid}</td><td>Pump ${sid}</td><td>${v.status==1?'ALERT':'OK'}</td><td>${v.temp}</td><td>${v.vibration}</td><td>${new Date(v.timestamp).toLocaleTimeString()}</td>`;
        tbody.appendChild(tr);
    });
}

/* --- MASTER TOGGLE & EVENT DELEGATION --- */
document.addEventListener('click', function(e) {
    const toggleBtn = e.target.closest('#navToggle');
    if (toggleBtn) {
        document.body.classList.toggle('nav-expanded');
        setTimeout(() => {
            maps.forEach(m => { if(m) google.maps.event.trigger(m, "resize"); });
        }, 300);
    }
});
</script>
</body>
</html>
