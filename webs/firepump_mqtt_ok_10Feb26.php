<?php
/**
 * /opt/lampp/htdocs/pages/gis/station_status_dashboard.php
 * KBS GIS + MQTT Station Status Map & Summary Dashboard
 *
 * Confidential – Internal Use Only
 */

$google_maps_api_key = 'AIzaSyDydCCrnu21a-d-1RMP41c64twP0PsaV0Q';
$mqtt_ws_url = 'ws://203.154.4.209:9001';
$mqtt_user   = 'admin';
$mqtt_pass   = '@Kbs2024!#';
// $mqtt_topic  = 'kbs/iot/fire-pump';

// Change this at the top of your script
const MQTT_TOPICS = [
  '<?php echo $mqtt_topic1; ?>',// {"site_name":"Sikhio","station_id":"Sikhio","sensor_vib1":0.1,"sensor_temp1":35,"sensor_vib2":0.25,"sensor_temp2":32,"sensor_vib3":0.3,"sensor_temp3":37.5}
  '<?php echo $mqtt_topic2; ?>', // {"site_name":"Kornburi","station_name":"Rail C","sensor_vib1":0.1,"sensor_temp1":35,"sensor_vib2":0.25,"sensor_temp2":32,"sensor_vib3":0.3,"sensor_temp3":37.5,"lat":0,"long":0}
  '<?php echo $mqtt_topic3; ?>', //{"site_name":"Kornburi","station_id":"Rail AB","sensor_vib1":0.1,"sensor_temp1":35,"sensor_vib2":0.25,"sensor_temp2":32,"sensor_vib3":0.3,"sensor_temp3":37.5,"lat":0,"long":0}
  '<?php echo $mqtt_topic4; ?>' // {"site_name":"KPP","station_id":"KPP","sensor_vib1":0.1,"sensor_temp1":35,"sensor_vib2":0.25,"sensor_temp2":32,"sensor_vib3":0.3,"sensor_temp3":37.5,"lat":0,"long":0}
];
// const MQTT_TOPICS = [
//   $mqtt_topic1  = 'kbs/firepump/sikhio'; // {"site_name":"Sikhio","station_id":"Sikhio","sensor_vib1":0.1,"sensor_temp1":35,"sensor_vib2":0.25,"sensor_temp2":32,"sensor_vib3":0.3,"sensor_temp3":37.5}
//   $mqtt_topic2  = 'kbs/firepump/korn1'; // {"site_name":"Kornburi","station_name":"Rail C","sensor_vib1":0.1,"sensor_temp1":35,"sensor_vib2":0.25,"sensor_temp2":32,"sensor_vib3":0.3,"sensor_temp3":37.5,"lat":0,"long":0}
//   $mqtt_topic3  = 'kbs/firepump/korn2'; //{"site_name":"Kornburi","station_id":"Rail AB","sensor_vib1":0.1,"sensor_temp1":35,"sensor_vib2":0.25,"sensor_temp2":32,"sensor_vib3":0.3,"sensor_temp3":37.5,"lat":0,"long":0}
//   $mqtt_topic4  = 'kbs/firepump/kpp'; // {"site_name":"KPP","station_id":"KPP","sensor_vib1":0.1,"sensor_temp1":35,"sensor_vib2":0.25,"sensor_temp2":32,"sensor_vib3":0.3,"sensor_temp3":37.5,"lat":0,"long":0}
  
// ];

$api_points  = '/api/iot/crud_gis_points.php';
$api_latest  = '/api/iot/iot_station_status_latest.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8" />
<title>KBS-Fire pump status Monitoring (Beta)</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{
    --bg:#edf2f7;
    --nav-bg:#0f172a;
    --nav-active:#1d4ed8;
    --panel:#ffffff;
    --panel-soft:#f8fafc;
    --ink:#0f172a;
    --muted:#6b7280;
    --border:#e2e8f0;
    --good:#16a34a;
    --bad:#dc2626;
    --accent:#2563eb;
    --accent-soft:#dbeafe;
    --shadow:0 18px 40px rgba(15,23,42,.18);
  }
  *{box-sizing:border-box;}
  html,body{
    height:100%;
    margin:0;
    background:var(--bg);
    color:var(--ink);
    font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
  }

  .app{display:flex;height:100vh;width:100vw;overflow:hidden;}

  /* NAV */
  .nav{
    width:72px;
    background:var(--nav-bg);
    color:#e5e7eb;
    display:flex;
    flex-direction:column;
    align-items:center;
    padding:14px 8px;
    gap:10px;
    flex-shrink:0;
    transition:width .2s ease;
  }
  .nav-toggle{
    width:40px;height:40px;border-radius:12px;
    background:#1e293b;border:none;color:#e5e7eb;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;font-size:18px;margin-bottom:8px;
  }
  .nav-logo{
    width:44px;height:44px;border-radius:12px;
    background:#1e293b;
    display:flex;align-items:center;justify-content:center;
    font-size:18px;font-weight:700;color:#e5e7eb;
    margin-bottom:10px;
  }
  .nav-group-label{
    font-size:10px;
    text-transform:uppercase;
    color:#64748b;
    margin-top:4px;margin-bottom:6px;
    display:none;
  }
  .nav-item{
    width:100%;
    border-radius:12px;
    padding:8px 6px;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:4px;
    font-size:11px;
    color:#cbd5f5;
    cursor:pointer;
    transition:background .15s ease;
  }
  .nav-item-icon{
    width:28px;height:28px;border-radius:999px;
    background:#1e293b;
    display:flex;align-items:center;justify-content:center;
    font-size:14px;
  }
  .nav-item span{display:none;}
  .nav-item.active{
    background:var(--nav-active);
    color:#fff;
  }
  .nav-item.active .nav-item-icon{background:#1d4ed8;}
  .nav-spacer{flex:1;}
  .nav-avatar{
    width:32px;height:32px;border-radius:999px;
    background:#e5e7eb;
    display:flex;align-items:center;justify-content:center;
    font-size:13px;color:#1f2937;
    margin-top:8px;
  }

  body.nav-expanded .nav{
    width:220px;
    align-items:flex-start;
    padding-left:14px;
  }
  body.nav-expanded .nav-logo{align-self:flex-start;}
  body.nav-expanded .nav-item{align-items:flex-start;}
  body.nav-expanded .nav-item span{display:block;}
  body.nav-expanded .nav-group-label{display:block;}

  .main{
    flex:1;
    display:flex;
    flex-direction:column;
    min-width:0;
    overflow:hidden;
  }
  .topbar{
    height:60px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:0 22px;
    border-bottom:1px solid var(--border);
    background:rgba(255,255,255,0.95);
    backdrop-filter:blur(10px);
    flex-shrink:0;
  }
  .topbar-title-th{font-size:20px;font-weight:700;}
  .topbar-title-en{font-size:12px;color:var(--muted);}
  .topbar-right{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--muted);}
  .topbar-pill{
    padding:4px 10px;border-radius:999px;
    background:var(--accent-soft);color:var(--accent);
    font-size:12px;font-weight:500;
  }

  .content{
    flex:1;
    padding:16px 18px 18px;
    min-height:0;
    overflow:hidden;
  }
  .card{
    background:var(--panel);
    border-radius:18px;
    box-shadow:var(--shadow);
    padding:14px 16px 16px;
    display:flex;
    flex-direction:column;
    gap:10px;
  }
  .card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:4px;
  }
  .card-title{font-size:14px;font-weight:700;}
  .card-subtitle{font-size:11px;color:var(--muted);}

  /* MAP page */
  #pageMap{display:flex;}
  .maps-wrap{display:flex;width:100%;gap:14px;}
  .map-card{padding:0;overflow:hidden;flex:1;min-height:0;}
  .map-card-header{
    padding:12px 14px 8px;
    border-bottom:1px solid var(--border);
    display:flex;
    justify-content:space-between;
    align-items:center;
    cursor:pointer;
  }
  .map-card-title{font-size:14px;font-weight:700;}
  .map-card-sub{font-size:11px;color:var(--muted);}
  .map-inner{position:relative;flex:1;min-height:0;}
  #mapA,#mapB{width:100%;height:100%;}

  .maps-wrap.max-zone-0 #mapCardA{flex:1;}
  .maps-wrap.max-zone-0 #mapCardB{flex:0 0 130px;}
  .maps-wrap.max-zone-1 #mapCardB{flex:1;}
  .maps-wrap.max-zone-1 #mapCardA{flex:0 0 130px;}

  /* table */
  .tw-body{overflow:auto;}
  table{width:100%;border-collapse:collapse;}
  thead th{
    position:sticky;top:0;background:#f1f5f9;color:var(--muted);text-align:left;
    font-size:11px;padding:7px 8px;border-bottom:1px solid var(--border);
  }
  tbody td{
    font-size:12px;padding:7px 8px;border-bottom:1px solid var(--border);
  }
  tbody tr:hover{background:#f9fafb;}
  .badge-ok{
    color:#166534;background:#dcfce7;
    padding:2px 7px;border-radius:999px;font-size:11px;font-weight:500;
  }
  .badge-bad{
    color:#b91c1c;background:#fee2e2;
    padding:2px 7px;border-radius:999px;font-size:11px;font-weight:500;
  }
  .fbtn{
    padding:3px 7px;border-radius:999px;
    border:1px solid var(--border);
    background:#eff6ff;color:#1d4ed8;
    font-size:11px;cursor:pointer;
  }

  /* InfoWindow popup */
  .gm-style-iw,
  .gm-style-iw-d{background:var(--panel)!important;color:var(--ink)!important;}
  .pp{background:var(--panel);padding:10px;border-radius:14px;color:var(--ink);}
  .pp small{color:var(--muted);}
  .pp .row{display:flex;gap:8px;align-items:center;margin:6px 0;flex-wrap:wrap;}
  .pp input,.pp select{
    background:var(--panel-soft);
    border:1px solid var(--border);
    color:var(--ink);
    border-radius:8px;
    padding:6px 8px;
    font-size:12px;
  }
  .pp select{
    appearance:none;
    background-image:
      linear-gradient(45deg,var(--ink) 50%,transparent 50%),
      linear-gradient(135deg,transparent 50%,var(--ink) 50%);
    background-position:
      calc(100% - 15px) calc(50% - 3px),
      calc(100% - 10px) calc(50% - 3px);
    background-size:5px 5px;
    background-repeat:no-repeat;
  }
  .pp button{
    background:var(--panel-soft);
    border:1px solid var(--border);
    color:var(--ink);
    border-radius:8px;
    padding:6px 10px;
    font-size:12px;
    cursor:pointer;
  }
  #pp-save{background:var(--accent);border-color:var(--accent);color:#fff;}
  #pp-del{background:#fee2e2;border-color:#fecaca;color:#b91c1c;}
  #pp-cancel{background:#e5e7eb;border-color:#d1d5db;color:#374151;}
  .pp button:hover{filter:brightness(1.02);}
  .alert-svg .ring{transform-origin:14px 14px;animation:ring 1.6s ease-out infinite;opacity:.9}
  .alert-svg .core{transform-origin:14px 14px;animation:beat .9s ease-in-out infinite;filter: drop-shadow(0 0 2px rgba(198,40,40,.75))}
  @keyframes ring{0%{transform:scale(.6);opacity:.65}60%{transform:scale(3.6);opacity:.08}100%{transform:scale(4);opacity:0}}
  @keyframes beat{0%,100%{transform:scale(1)}50%{transform:scale(1.18)}}

  /* SUMMARY PAGE */
  #pageSummary{display:none;}
  .content-summary{display:flex;flex-direction:column;gap:14px;overflow:hidden;}
  .summary-top-row{display:flex;flex-wrap:wrap;gap:10px;}
  .metric-card{
    flex:1;min-width:150px;background:var(--panel-soft);
    border-radius:14px;padding:8px 10px;display:flex;flex-direction:column;gap:2px;
  }
  .metric-label{font-size:11px;color:var(--muted);}
  .metric-value{font-size:18px;font-weight:700;}
  .metric-sub{font-size:10px;color:var(--muted);}
  .summary-main-row{
    display:grid;
    grid-template-columns:minmax(240px,320px) minmax(0,1fr);
    grid-gap:14px;
    min-height:0;flex:1;
  }
  .gauge-body{display:flex;gap:12px;align-items:center;justify-content:space-between;}
  .gauge-vis{width:180px;max-width:100%;}
  .gauge-bg{fill:none;stroke:#e5e7eb;stroke-width:10;}
  .gauge-val{
    fill:none;stroke:#22c55e;stroke-width:10;stroke-linecap:round;
    transform:rotate(-90deg);transform-origin:60px 60px;
    transition:stroke-dashoffset .35s ease;
  }
  .gauge-text-main{font-size:20px;font-weight:700;fill:#111827;}
  .gauge-text-sub{font-size:11px;fill:#6b7280;}
  .gauge-legend{flex:1;font-size:12px;color:var(--muted);}
  .gauge-legend-row{display:flex;justify-content:space-between;margin-bottom:4px;}

  /* TEMP REALTIME PAGE */
  #pageTemp{display:none;}
  .temp-header-actions{display:flex;align-items:center;gap:8px;}
  .temp-lock-btn{
    padding:5px 10px;border-radius:999px;border:1px solid var(--border);
    background:#eff6ff;color:#1d4ed8;font-size:11px;cursor:pointer;
  }
  .temp-lock-btn.locked{background:#e5e7eb;color:#374151;}
  .temp-board{
    position:relative;
    border-radius:14px;
    background:var(--panel-soft);
    overflow:visible;
    padding-bottom:10px;
  }
  .temp-card{
    position:absolute;
    width:260px;
    background:#ffffff;
    border-radius:16px;
    padding:10px 12px;
    display:flex;
    align-items:center;
    gap:10px;
    box-shadow:0 12px 25px rgba(15,23,42,.18);
    cursor:grab;
    transition:box-shadow .15s ease,transform .1s ease;
  }
  .temp-card.dragging{cursor:grabbing;transform:scale(1.02);box-shadow:0 18px 35px rgba(15,23,42,.3);}
  .temp-card.locked{cursor:default;}
  .temp-card-left{width:110px;max-width:40%;}
  .temp-card-right{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px;font-size:12px;}
  .temp-gauge-bg{fill:none;stroke:#e5e7eb;stroke-width:10;}
  .temp-gauge-circle{
    fill:none;stroke:#f97316;stroke-width:10;stroke-linecap:round;
    transform:rotate(-90deg);transform-origin:60px 60px;
    transition:stroke-dashoffset .35s ease;
  }
  .temp-gauge-text-main{font-size:16px;font-weight:700;fill:#111827;}
  .temp-gauge-text-sub{font-size:10px;fill:#6b7280;}
  .temp-row-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:6px;
  }
  .temp-row-top-right{
    display:flex;
    align-items:center;
    gap:6px;
  }
  .temp-station-label{font-size:13px;font-weight:600;}
  .temp-status-text-line{font-size:11px;color:var(--muted);}
  .temp-indicators{display:flex;align-items:center;gap:8px;}
  .temp-bulb{
    width:28px;height:28px;border-radius:999px;background:#9ca3af;
    box-shadow:0 0 0 3px rgba(148,163,184,.4);flex-shrink:0;
  }
  .temp-bulb.ok{background:#22c55e;box-shadow:0 0 14px rgba(34,197,94,.85);}
  .temp-bulb.bad{background:#ef4444;box-shadow:0 0 14px rgba(239,68,68,.9);}
  .temp-bulb.unknown{background:#6b7280;box-shadow:0 0 10px rgba(148,163,184,.7);}
  .temp-meta{font-size:11px;color:var(--muted);display:flex;flex-direction:column;gap:1px;}

  .temp-raw{font-size:11px;color:#6b7280;}
  .temp-raw-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;}
  .temp-raw-box{
    background:#0f172a;color:#e5e7eb;border-radius:12px;
    padding:8px 10px;
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New",monospace;
    font-size:11px;
    max-height:320px;
    overflow:auto;
    white-space:pre-wrap;
  }

  /* Maximize behaviour for temp widgets */
  .temp-card.maximized{
    width:calc(100% - 20px) !important;
    left:10px !important;
    top:10px !important;
    z-index:2000;
    transform:scale(1.02);
  }
  .temp-card.hidden-when-max{
    display:none;
  }
  .temp-max-btn{
    background:#e2e8f0;
    border:none;
    padding:3px 6px;
    border-radius:6px;
    cursor:pointer;
    font-size:11px;
    line-height:1;
  }
  .temp-max-btn:hover{
    background:#cbd5e1;
  }

  /* RAW PAGE */
  #pageRaw{display:none;}

  /* SETTINGS */
  #pageSettings{display:none;}
  .settings-content{display:flex;flex-direction:column;gap:14px;max-width:520px;}
  .toolbar{display:grid;gap:8px;}
  .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
  .toolbar label{font-size:12px;color:var(--muted);}
  .toolbar input,.toolbar select,.toolbar button{
    background:var(--panel-soft);
    border:1px solid var(--border);
    color:var(--ink);
    border-radius:999px;
    padding:7px 11px;
    font-size:12px;
  }
  .toolbar button{cursor:pointer;}
  .toolbar .pill{background:var(--accent);border-color:var(--accent);color:#fff;}
  .toggle-on{background:#16a34a;border-color:#16a34a;color:#f0fdf4;}
  .statusline{font-size:11px;color:var(--muted);margin-top:2px;}
  .mqttDot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;background:#9ca3af;}
  .mqttDot.ok{background:#22c55e;}

  /* responsive */
  @media (max-width:1024px){
    #pageMap{padding:12px 12px 16px;}
    .maps-wrap{flex-direction:column;}
    .map-card{min-height:260px;}
    .map-inner{min-height:220px;}
    #mapA,#mapB{height:100%;min-height:220px;}
  }
  @media (max-width:768px){
    .app{flex-direction:column;height:auto;min-height:100vh;overflow:visible;}
    .nav{
      width:100%;height:auto;flex-direction:row;
      align-items:center;justify-content:space-between;
      padding:8px 12px;gap:8px;
    }
    .nav-toggle{display:none;}
    .nav-logo{width:36px;height:36px;margin-bottom:0;}
    .nav-group-label{display:none;}
    .nav-spacer{display:none;}
    .nav-avatar{margin-top:0;}
    .nav-item{
      flex-direction:column;font-size:10px;
      padding:6px 4px;gap:2px;align-items:center;
    }
    .nav-item-icon{width:26px;height:26px;font-size:13px;}
    .nav-item span{display:block;}

    .main{overflow:visible;}
    .topbar{padding:6px 12px;height:auto;flex-wrap:wrap;gap:4px;}
    .topbar-title-th{font-size:16px;}
    .topbar-title-en{font-size:11px;}
    .topbar-right{font-size:11px;gap:6px;}
    .topbar-pill{padding:3px 8px;font-size:11px;}

    #pageMap{padding:10px 10px 14px;}
    .card{padding:10px 12px 12px;border-radius:14px;}
    .map-card{min-height:260px;}
    .map-inner{min-height:220px;}
    #mapA,#mapB{height:100%;min-height:220px;}

    .summary-main-row{grid-template-columns:1fr;}

    .temp-board{
      position:relative;
      width:100%;
      padding:6px 0;
      background:transparent;
      height:auto !important;
    }
    .temp-card{
      position:static;
      width:100%;
      margin-bottom:8px;
      cursor:default;
      left:auto !important;
      top:auto !important;
    }
    .temp-card.maximized{
      width:100% !important;
      left:auto !important;
      top:auto !important;
    }
  }
</style>
</head>
<body class="nav-expanded">
<div id="diag" style="display:none"></div>

<div class="app">
  <!-- NAV -->
  <nav class="nav">
    <button class="nav-toggle" id="navToggle">☰</button>
    <div class="nav-logo">K</div>
    <div style="display:flex;flex-direction:column;align-items:flex-start;gap:8px;width:100%;">
      <div class="nav-group-label">เมนู</div>
      <div class="nav-item active" id="navMap">
        <div class="nav-item-icon">📍</div><span>แผนที่</span>
      </div>
      <div class="nav-item" id="navSummary">
        <div class="nav-item-icon">📊</div><span>สรุป</span>
      </div>
      <div class="nav-item" id="navTemp">
        <div class="nav-item-icon">🌡️</div><span>Temp</span>
      </div>
      <div class="nav-item" id="navRaw">
        <div class="nav-item-icon">🧾</div><span>Raw JSON</span>
      </div>
      <div class="nav-item" id="navSettings">
        <div class="nav-item-icon">⚙️</div><span>ตั้งค่า</span>
      </div>
    </div>
    <div class="nav-spacer"></div>
    <div class="nav-item">
      <div class="nav-item-icon">❓</div><span>ช่วยเหลือ</span>
    </div>
    <div class="nav-avatar">PW</div>
  </nav>

  <!-- MAIN -->
  <div class="main">
    <header class="topbar">
      <div>
        <!-- <div class="topbar-title-th">KBS-Fire pump status Monitoring (Beta)</div> -->
        <div class="topbar-title-en">KBS-Fire pump status Monitoring (Beta)</div>
      </div>
      <div class="topbar-right">
        <span class="topbar-pill">Realtime MQTT</span>
        <span>เวอร์ชันทดสอบภายใน</span>
      </div>
    </header>

    <!-- MAP PAGE -->
    <div class="content" id="pageMap">
      <div class="maps-wrap" id="mapsWrap">
        <section class="card map-card" id="mapCardA">
          <div class="map-card-header" id="mapCardAHeader">
            <div>
              <div class="map-card-title">Sikhio</div>
              <div class="map-card-sub">กลุ่มหมุดพื้นที่หลัก</div>
            </div>
            <div class="map-card-sub">คลิกเพื่อขยาย / ย่อ</div>
          </div>
          <div class="map-inner"><div id="mapA"></div></div>
        </section>

        <section class="card map-card" id="mapCardB">
          <div class="map-card-header" id="mapCardBHeader">
            <div>
              <div class="map-card-title">Kornburi</div>
              <div class="map-card-sub">กลุ่มหมุดที่อยู่ห่างออกไป</div>
            </div>
            <div class="map-card-sub">คลิกเพื่อขยาย / ย่อ</div>
          </div>
          <div class="map-inner"><div id="mapB"></div></div>
        </section>
      </div>
    </div>

    <!-- SUMMARY PAGE -->
    <div class="content content-summary" id="pageSummary">
      <section class="card">
        <div class="card-header">
          <div>
            <div class="card-title">2.1 ภาพรวมสถานี (จาก API)</div>
            <div class="card-subtitle">จำนวนสถานีทั้งหมด, ปกติ, แจ้งเตือน และสถานีที่ผูกกับหมุด (อ้างอิงจากฐานข้อมูล API)</div>
          </div>
        </div>
        <div class="summary-top-row">
          <div class="metric-card">
            <div class="metric-label">สถานีทั้งหมด</div>
            <div class="metric-value" id="sumTotal">0</div>
            <div class="metric-sub">นับจากข้อมูล API ล่าสุด</div>
          </div>
          <div class="metric-card">
            <div class="metric-label">สถานีปกติ</div>
            <div class="metric-value" id="sumNormal">0</div>
            <div class="metric-sub">status = 0</div>
          </div>
          <div class="metric-card">
            <div class="metric-label">สถานีแจ้งเตือน</div>
            <div class="metric-value" id="sumAlert">0</div>
            <div class="metric-sub">status = 1</div>
          </div>
          <div class="metric-card">
            <div class="metric-label">สถานีที่ผูกกับหมุด</div>
            <div class="metric-value" id="sumBound">0</div>
            <div class="metric-sub">มี Station ID ผูกกับ Pin</div>
          </div>
        </div>
      </section>

      <div class="summary-main-row">
        <section class="card">
          <div class="card-header">
            <div>
              <div class="card-title">อัตราสถานีปกติ (จาก API)</div>
              <div class="card-subtitle">คิดจากจำนวนสถานีปกติ / สถานีทั้งหมด (ข้อมูลจาก API)</div>
            </div>
          </div>
          <div class="gauge-body">
            <div class="gauge-vis">
              <svg viewBox="0 0 120 120">
                <circle class="gauge-bg" cx="60" cy="60" r="52"></circle>
                <circle id="gaugeCircle" class="gauge-val" cx="60" cy="60" r="52"
                        stroke-dasharray="326" stroke-dashoffset="326"></circle>
                <text id="gaugePercent" x="60" y="64" text-anchor="middle" class="gauge-text-main">0%</text>
                <text x="60" y="80" text-anchor="middle" class="gauge-text-sub">สถานีปกติ</text>
              </svg>
            </div>
            <div class="gauge-legend">
              <div class="gauge-legend-row"><span>สถานีทั้งหมด</span><span id="gTotal">0</span></div>
              <div class="gauge-legend-row"><span>ปกติ</span><span id="gNormal">0</span></div>
              <div class="gauge-legend-row"><span>แจ้งเตือน</span><span id="gAlert">0</span></div>
              <div class="gauge-legend-row"><span>เปอร์เซ็นต์ปกติ</span><span id="gPercentLabel">0%</span></div>
            </div>
          </div>
        </section>

        <section class="card">
          <div class="card-header">
            <div>
              <div class="card-title">สถานีจากฐานข้อมูล (Top 10)</div>
              <div class="card-subtitle">เรียงสถานีแจ้งเตือนก่อน จากนั้นเรียงตาม Station ID (ข้อมูลจาก API)</div>
            </div>
          </div>
          <div class="tw-body">
            <table>
              <thead>
                <tr>
                  <th style="width:72px;">Station</th>
                  <th>Pin Name (if bound)</th>
                  <th>Status</th>
                  <th>Temp</th>
                  <th>Humid</th>
                  <th>Timestamp</th>
                </tr>
              </thead>
              <tbody id="summaryTableBody"></tbody>
            </table>
          </div>
        </section>
      </div>
    </div>

    <!-- TEMP REALTIME PAGE -->
    <div class="content" id="pageTemp">
      <section class="card">
        <div class="card-header">
          <div>
            <div class="card-title">อุณหภูมิแบบ Realtime (MQTT)</div>
            <div class="card-subtitle">
              แสดงทุกสถานีจาก MQTT แบบเรียลไทม์
              <span id="tempCountLabel" style="margin-left:6px;color:#4b5563">(0 sensors)</span>
            </div>
          </div>
          <div class="temp-header-actions">
            <button id="tempLockBtn" class="temp-lock-btn">🔓 Free layout</button>
          </div>
        </div>
        <div class="temp-board" id="tempBoard">
          <!-- cards created by JS -->
        </div>
      </section>
    </div>

    <!-- RAW JSON PAGE -->
    <div class="content" id="pageRaw">
      <section class="card">
        <div class="card-header">
          <div>
            <div class="card-title">ข้อมูลดิบจาก MQTT (Raw JSON)</div>
            <div class="card-subtitle">
              ใช้สำหรับตรวจสอบ payload ที่ส่งมาจาก broker — เวลาแปลงเป็นเขตเวลาไทย (Asia/Bangkok)
            </div>
          </div>
        </div>
        <div class="temp-raw">
          <div class="temp-raw-header">
            <span>Payload ล่าสุด</span>
          </div>
          <pre id="mqttRawBox" class="temp-raw-box">{}</pre>
        </div>
      </section>
    </div>

    <!-- SETTINGS PAGE -->
    <div class="content" id="pageSettings">
      <div class="settings-content">
        <section class="card">
          <div class="card-header">
            <div>
              <div class="card-title">4.1 การตั้งค่า MQTT และหมุด</div>
              <div class="card-subtitle">หัวข้อควบคุม MQTT, ค้นหา และการจัดการหมุดบนแผนที่</div>
            </div>
          </div>
          <div class="toolbar" id="toolbar">
            <div class="row">
              <span id="mqttDot" class="mqttDot"></span>
              <strong id="mqttState">Disconnected</strong>
            </div>
            <div class="row" style="gap:6px">
              <label>Topic:</label>
              <input id="topicInput" value="<?php echo htmlspecialchars($mqtt_topic); ?>" style="flex:1" />
              <button id="btnResub">Re-sub</button>
            </div>
            <div class="row">
              <input id="searchName" placeholder="Search pin name…" style="flex:1" />
              <button id="btnSearch">Search</button>
              <button id="btnReload">Reload</button>
            </div>
            <div class="row">
              <label for="selPin">Pin:</label>
              <select id="selPin" style="flex:1;min-width:180px">
                <option value="">— Select pin —</option>
              </select>
              <button id="btnPinGo">Focus</button>
            </div>
            <div class="row">
              <button id="btnAddPin" class="pill">+ Add pin here (active map center)</button>
            </div>
            <div class="row">
              <label>Refresh:</label>
              <select id="selRefresh" style="flex:1">
                <option value="0">Off</option>
                <option value="1000">1s</option>
                <option value="30000">30s</option>
                <option value="60000">1m</option>
                <option value="1800000">30m</option>
                <option value="3600000">1h</option>
              </select>
            </div>
            <div class="row">
              <button id="btnMovePins">Move Pins: OFF</button>
              <button id="btnTogglePoi">POI: OFF</button>
              <button id="btnToggleTable">Table</button>
            </div>
            <div class="statusline" id="statusText">Ready</div>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_maps_api_key; ?>&callback=initMap"></script>

<script>
/* === CONFIG / HELPERS === */
const MQTT_WS_URL = '<?php echo $mqtt_ws_url; ?>';
const MQTT_USER   = '<?php echo $mqtt_user; ?>';
const MQTT_PASS   = '<?php echo $mqtt_pass; ?>';
let   MQTT_TOPIC  = '<?php echo $mqtt_topic; ?>';

const API_POINTS  = '<?php echo $api_points; ?>';
const API_LATEST  = '<?php echo $api_latest; ?>';
const API_NAMES   = API_POINTS + '?action=list_names';

const DEFAULT_CENTER = {lat:15.0,lng:101.0};
const TH_TZ = 'Asia/Bangkok';

function diag(msg){
  const d=document.getElementById('diag');
  d.textContent=msg;
  d.style.display='block';
  console.error(msg);
}
function setStatus(t){
  const el=document.getElementById('statusText');
  if(el) el.textContent=t;
}
function esc(s){
  return String(s ?? '').replace(/[&<>"]/g,m=>({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'
  }[m]));
}
function formatTs(ts){
  if (!ts) return '-';
  try{
    const d=new Date(ts);
    if (isNaN(d.getTime())) return ts;
    return d.toLocaleString('th-TH',{timeZone:TH_TZ,hour12:false});
  }catch{ return ts; }
}

/* === MAPS & POI === */
let maps=[];
let infoWindow;
let activeZone=0;
let showPois=false;
const POI_OFF_STYLES=[
  {featureType:"poi",stylers:[{visibility:"off"}]},
  {featureType:"transit",stylers:[{visibility:"off"}]}
];
function applyPoiStyles(){maps.forEach(m=>{if(m)m.setOptions({styles:showPois?[]:POI_OFF_STYLES});});}
window.initMap=function(){
  const opts={center:DEFAULT_CENTER,zoom:7,mapTypeId:'terrain'};
  maps[0]=new google.maps.Map(document.getElementById('mapA'),opts);
  maps[1]=new google.maps.Map(document.getElementById('mapB'),opts);
  infoWindow=new google.maps.InfoWindow();
  applyPoiStyles();
  initApp();
};
function getMapForZone(z){return maps[z]||maps[0];}

/* ICONS */
const greenPin={url:'https://maps.google.com/mapfiles/ms/icons/green-dot.png'};
function redBlinkIconSvg(){
  const svg=`<svg xmlns="http://www.w3.org/2000/svg" class="alert-svg" viewBox="0 0 28 38" aria-hidden="true" style="overflow:visible; filter: drop-shadow(0 0 2px rgba(198,40,40,.75));">
    <g transform="translate(0, 5)">
      <circle class="ring" cx="14" cy="14" r="6" fill="rgba(198,40,40,0.25)" stroke="rgba(198,40,40,0.25)" stroke-width="2"/>
      <circle class="core" cx="14" cy="14" r="6" fill="#e53935"/>
      <circle cx="12" cy="12" r="1.8" fill="rgba(255,255,255,0.85)"/>
    </g>
    <text x="14" y="36" font-size="12" text-anchor="middle" fill="#e53935">▼</text></svg>`;
  return{
    url:'data:image/svg+xml;charset=UTF-8,'+encodeURIComponent(svg),
    scaledSize:new google.maps.Size(28,38),
    anchor:new google.maps.Point(14,38)
  };
}
const iconByStatus=s=>Number(s)===1?redBlinkIconSvg():greenPin;

/* STATE */
const pinById              = new Map();
const pinConfig            = new Map();
const readingApiByStation  = new Map();
const readingMqttByStation = new Map();
let refreshTimer=null;
let moveMode=false;

/* API */
async function apiJson(url,opt){
  const res=await fetch(url,opt||{});
  const txt=await res.text();
  let js;try{js=JSON.parse(txt);}catch{throw new Error(`Non-JSON (${res.status}): `+txt.slice(0,180));}
  if(!res.ok||js.success===false) throw new Error(js.message||`HTTP ${res.status}`);
  return js;
}
const listPoints=async(name=null)=>{
  let url=API_POINTS+'?action=list&per_page=1000';
  if(name && name.trim()) url+='&name='+encodeURIComponent(name.trim());
  const js=await apiJson(url,{headers:{'Accept':'application/json'}});
  return js.data||[];
};
const createPoint=(name,lat,lng,station_id=null,station_name='')=>
  apiJson(API_POINTS+'?action=create',{
    method:'POST',
    headers:{'Content-Type':'application/json','Accept':'application/json'},
    body:JSON.stringify({name,lat,lng,station_id,station_name})
  });
const updatePoint=(id,payload)=>
  apiJson(API_POINTS+'?action=update',{
    method:'POST',
    headers:{'Content-Type':'application/json','Accept':'application/json'},
    body:JSON.stringify(Object.assign({id:Number(id)},payload))
  });
const deletePoint=id=>
  apiJson(API_POINTS+'?action=delete',{
    method:'POST',
    headers:{'Content-Type':'application/json','Accept':'application/json'},
    body:JSON.stringify({id:Number(id)})
  });

/* Snapshot from API for dashboard */
const loadLatestSnapshot=async()=>{
  try{
    readingApiByStation.clear();
    const js=await apiJson(API_LATEST,{headers:{'Accept':'application/json'}});
    const arr=Array.isArray(js.data)?js.data:[];
    arr.forEach(s=>{
      const id=Number(s.station_id);
      if(!Number.isFinite(id))return;
      readingApiByStation.set(id,{
        status:s.status!=null?Number(s.status):null,
        temp:  s.temp  !=null?Number(s.temp)  :null,
        humid: s.humid!=null?Number(s.humid):null,
        timestamp:s.timestamp||null
      });
    });
    renderTable();
  }catch(e){console.warn('Latest snapshot load failed:',e.message);}
};

/* Names */
let NAME_CACHE=[];let NAMES_LOADED=false;
async function fetchNames(force=false){
  if(NAMES_LOADED && !force) return NAME_CACHE;
  try{
    const js=await apiJson(API_NAMES,{headers:{'Accept':'application/json'}});
    NAME_CACHE=Array.isArray(js.data)?js.data.filter(x=>x && x.name):[];
    NAMES_LOADED=true;
  }catch(e){console.warn('list_names failed:',e.message);NAME_CACHE=[];}
  return NAME_CACHE;
}

/* MQTT RAW VIEW */
let lastMqttRaw='';
function updateMqttRawView(raw){
  lastMqttRaw=raw;
  const box=document.getElementById('mqttRawBox');
  if(!box) return;
  try{
    const js=JSON.parse(raw);
    box.textContent=JSON.stringify(js,null,2);
  }catch{
    box.textContent=raw;
  }
}

/* MQTT */
/const mqttDot=document.getElementById('mqttDot');
 const mqttState=document.getElementById('mqttState');
 let mqttClient=null;

// function mqttConnect(){
//   try{
//     if(mqttClient){try{mqttClient.end(true);}catch(_){}}    
//     mqttClient=mqtt.connect(MQTT_WS_URL,{
//       clientId:'web-'+Math.random().toString(16).slice(2),
//       username:MQTT_USER,
//       password:MQTT_PASS,
//       keepalive:60,
//       reconnectPeriod:2000
//     });
//     mqttClient.on('connect',()=>{
//       mqttDot.classList.add('ok');
//       mqttState.textContent='Connected';
//       try{mqttClient.subscribe(MQTT_TOPIC);}catch(_){}
//     });
//     mqttClient.on('reconnect',()=>{
//       mqttDot.classList.remove('ok');
//       mqttState.textContent='Reconnecting…';
//     });
//     mqttClient.on('close',()=>{
//       mqttDot.classList.remove('ok');
//       mqttState.textContent='Disconnected';
//     });
//     mqttClient.on('error',e=>{
//       mqttDot.classList.remove('ok');
//       mqttState.textContent='Error';
//       console.error('MQTT error:',e?.message||e);
//     });
//     mqttClient.on('message',(topic,payload)=>{
//       try{
//         const raw=payload.toString();
//         updateMqttRawView(raw);

//         const js=JSON.parse(raw);
//         let stationsArr=[];let ts=new Date().toISOString();

//         if(Array.isArray(js)) stationsArr=js;
//         else if(Array.isArray(js.stations)){
//           stationsArr=js.stations;
//           if(js.timestamp) ts=js.timestamp;
//         }else{
//           console.warn('MQTT JSON not in expected format:',js);
//           return;
//         }

//         // --- combine status1/status2 into status (1/1 = normal, other = alert) ---
//         stationsArr.forEach(s => {
//           const id = Number(s.station_id);
//           if (!Number.isFinite(id)) return;

//           const s1 = s.status1 != null ? Number(s.status1) : null;
//           const s2 = s.status2 != null ? Number(s.status2) : null;

//           let status = null;
//           if (s.status != null) {
//             status = Number(s.status);               // legacy single status
//           } else if (s1 === 1 && s2 === 1) {
//             status = 0;                              // 1/1 = normal (green)
//           } else if (s1 != null || s2 != null) {
//             status = 1;                              // any 0 / mismatch = alert (red)
//           }

//           readingMqttByStation.set(id,{
//             status,
//             status1: s1,
//             status2: s2,
//             temp:   s.temp  != null ? Number(s.temp)  : null,
//             humid:  s.humid!= null ? Number(s.humid) : null,
//             timestamp:s.timestamp || ts
//           });
//         });
//         // ----------------------------------------------------------------------

//         refreshMarkersFromReadings();
//         renderTempPage();
//       }catch(e){console.error('Bad MQTT JSON:',e);}
//     });
//   }catch(e){diag('MQTT connect error: '+(e?.message||e));}
// }

function mqttConnect(){
  try{
    if(mqttClient){try{mqttClient.end(true);}catch(_){}}    
    mqttClient = mqtt.connect(MQTT_WS_URL, {
      clientId: 'web-' + Math.random().toString(16).slice(2),
      username: MQTT_USER,
      password: MQTT_PASS,
      keepalive: 60,
      reconnectPeriod: 2000
    });

    mqttClient.on('connect', () => {
      mqttDot.classList.add('ok');
      mqttState.textContent = 'Connected';
      // Subscribe to all 4 stations
      MQTT_TOPICS.forEach(t => {
        mqttClient.subscribe(t);
        console.log('Subscribed to:', t);
      });
    });

    mqttClient.on('reconnect', () => {
      mqttDot.classList.remove('ok');
      mqttState.textContent = 'Reconnecting…';
    });

    mqttClient.on('close', () => {
      mqttDot.classList.remove('ok');
      mqttState.textContent = 'Disconnected';
    });

    mqttClient.on('error', e => {
      mqttDot.classList.remove('ok');
      mqttState.textContent = 'Error';
      console.error('MQTT error:', e?.message || e);
    });

    mqttClient.on('message', (topic, payload) => {
      try {
        const raw = payload.toString();
        updateMqttRawView(raw);
        const js = JSON.parse(raw);
        let ts = new Date().toISOString();

        // 1. Identify Numeric Station ID based on topic name
        // (Ensure these IDs match the "Station ID" you set for your Map Pins)
        let sid;
        if (topic.includes('sikhio')) sid = 101;
        else if (topic.includes('korn1')) sid = 102;
        else if (topic.includes('korn2')) sid = 103;
        else if (topic.includes('kpp'))   sid = 104;

        if (!sid) return;

        // 2. Logic: Determine Alert Status (0=Normal, 1=Alert)
        // Example: If any vibration is > 0.5 or Temp > 50, trigger Red status
        const v1 = js.sensor_vib1 || 0;
        const v2 = js.sensor_vib2 || 0;
        const v3 = js.sensor_vib3 || 0;
        const t1 = js.sensor_temp1 || 0;
        
        let status = 0; 
        if (v1 > 0.5 || v2 > 0.5 || v3 > 0.5 || t1 > 50) {
          status = 1; 
        }

        // 3. Save to the reading map
        // We map your new keys (sensor_temp1) to the existing keys (temp)
        // so the rest of your dashboard functions still work.
        readingMqttByStation.set(sid, {
          status: status,
          temp: Number(t1), 
          vibration: Number(v1),
          // Store extra data for potential custom tooltips
          details: js, 
          timestamp: ts
        });

        refreshMarkersFromReadings();
        renderTempPage();
      } catch (e) {
        console.error('Bad IIoT MQTT JSON:', e);
      }
    });
  } catch (e) {
    diag('MQTT connect error: ' + (e?.message || e));
  }
}
/* Marker logic */
function markerStatusForRow(row){
  const cfg=pinConfig.get(row.id)||{};
  const sid=cfg.station_id!=null?Number(cfg.station_id):null;
  const readM=sid!=null?readingMqttByStation.get(sid):null;
  const readA=sid!=null?readingApiByStation.get(sid):null;
  if(readM && readM.status!=null) return Number(readM.status);
  if(readA && readA.status!=null) return Number(readA.status);
  return Number(row.status||0);
}
function miniTipText(row){
  const cfg=pinConfig.get(row.id)||{};
  const sid=cfg.station_id!=null?Number(cfg.station_id):null;
  const readM=sid!=null?readingMqttByStation.get(sid):null;
  const readA=sid!=null?readingApiByStation.get(sid):null;
  const use=readM || readA || null;
  const st=use && use.status!=null?Number(use.status):(row.status||0);
  const temp=use && use.temp!=null?`${use.temp.toFixed(2)}°C`:'-';
  const hum =use && use.humid!=null?`${use.humid.toFixed(2)}%`:'-';
  const ts=formatTs(use?.timestamp || row.status_timestamp || '');
  return `${esc(row.name || ('#'+row.id))} / SID: ${sid ?? '-'} / St: ${st} / T: ${temp} / H: ${hum} / ${ts}`;
}

/* station label from pinConfig */
function getStationLabel(stationId){
  let label=null;
  const sidNum=Number(stationId);
  pinConfig.forEach((cfg,gid)=>{
    if(!cfg || cfg.station_id==null) return;
    if(Number(cfg.station_id)!==sidNum) return;
    if(label!==null) return;
    const marker=pinById.get(gid);
    const pinName=marker ? (marker._row?.name || null) : null;
    label=(cfg.station_name && cfg.station_name.trim())
        || (pinName && pinName.trim())
        || null;
  });
  return label || `สถานี ${sidNum}`;
}

/* popup html & wiring */
function editorHtml(row){
  const cfg=pinConfig.get(row.id)||{};
  const sid  =cfg.station_id ?? '';
  const sname=cfg.station_name ?? '';
  const mergedIds=new Set([
    ...Array.from(readingApiByStation.keys()),
    ...Array.from(readingMqttByStation.keys())
  ]);
  const sidOptions=['<option value="">- None -</option>']
    .concat(Array.from(mergedIds).sort((a,b)=>a-b)
      .map(stid=>`<option value="${stid}" ${Number(stid)===Number(sid)?'selected':''}>${stid}</option>`))
    .join('');
  return `
    <div class="pp" data-id="${row.id}">
      <b>Edit Pin</b>
      <div class="row"><small>ID:</small> <code>#${row.id}</code></div>
      <div class="row">
        <small>Lat:</small> <input id="pp-lat" value="${row.lat.toFixed(6)}" style="width:120px">
        <small>Lng:</small> <input id="pp-lng" value="${row.lng.toFixed(6)}" style="width:120px">
      </div>
      <div class="row" style="flex-wrap:nowrap;gap:6px;align-items:center">
        <small>Name:</small>
        <select id="pp-name-select" style="flex:1"><option value="">Loading…</option></select>
        <label style="display:flex;gap:6px;align-items:center;">
          <input type="checkbox" id="pp-name-custom"> Custom
        </label>
      </div>
      <div class="row">
        <input id="pp-name-input" value="${esc(row.name||'')}" placeholder="Type custom name…" style="flex:1;display:none">
        <button id="pp-nrefresh" title="Refresh name list">↻</button>
      </div>
      <div class="row">
        <small>Station ID:</small>
        <select id="pp-sid" style="width:140px">${sidOptions}</select>
        <small>Station name:</small>
        <input id="pp-sname" value="${esc(sname)}" style="flex:1">
      </div>
      <div class="row">
        <button id="pp-save">💾 Save</button>
        <button id="pp-del">🗑 Delete</button>
        <button id="pp-cancel">✖ Close</button>
      </div>
      <div style="margin-top:6px"><small>Use “Move Pins” toggle to reposition markers.</small></div>
    </div>`;
}
function wirePopupHandlers(marker,row){
  const root=document.querySelector(`.pp[data-id="${row.id}"]`);
  if(!root) return;
  const $=sel=>root.querySelector(sel);

  const nameSelect=$('#pp-name-select');
  const nameInput =$(`#pp-name-input`);
  const customChk =$(`#pp-name-custom`);

  (async ()=>{
    await fetchNames(false);
    const opts=['<option value="" disabled selected>— Select name —</option>']
      .concat(NAME_CACHE.map(o=>`<option value="${esc(o.name)}">${esc(o.name)}</option>`))
      .join('');
    nameSelect.innerHTML=opts;
    const current=(row.name||'').trim();
    if(current){
      const found=Array.from(nameSelect.options).find(op=>op.value===current);
      if(found){
        nameSelect.value=current;
        customChk.checked=false;
        nameInput.style.display='none';
      }else{
        customChk.checked=true;
        nameInput.style.display='block';
        nameInput.value=current;
      }
    }
  })();

  customChk?.addEventListener('change',()=>{
    if(customChk.checked){
      nameInput.style.display='block';nameInput.focus();
    }else{
      nameInput.style.display='none';nameInput.value=nameSelect.value || '';
    }
  });
  nameSelect?.addEventListener('change',()=>{
    if(!customChk.checked) nameInput.value=nameSelect.value || '';
  });
  $('#pp-nrefresh')?.addEventListener('click',async ev=>{
    ev.preventDefault();ev.stopPropagation();
    setStatus('Refreshing names…');
    await fetchNames(true);
    const keep=nameSelect.value;
    const opts=['<option value="" disabled>— Select name —</option>']
      .concat(NAME_CACHE.map(o=>`<option value="${esc(o.name)}">${esc(o.name)}</option>`))
      .join('');
    nameSelect.innerHTML=opts;
    if(keep) nameSelect.value=keep;
    setStatus('Names updated');
  });

  const btnSave=$('#pp-save');
  const btnDel =$(`#pp-del`);

  btnSave?.addEventListener('click',async ev=>{
    ev.preventDefault();ev.stopPropagation();
    try{
      const name = customChk.checked ? (nameInput.value || '') : (nameSelect.value || '');
      const lat  = parseFloat($('#pp-lat')?.value ?? row.lat);
      const lng  = parseFloat($('#pp-lng')?.value ?? row.lng);
      const sid  = $('#pp-sid')?.value;
      const sname= $('#pp-sname')?.value ?? '';

      setStatus('Saving…');
      await updatePoint(row.id,{name,lat,lng,station_id:sid!==''?Number(sid):null,station_name:sname || ''});

      row.name=name;row.lat=lat;row.lng=lng;
      marker.setPosition({lat,lng});
      marker._row=row;

      if(sid!=='') pinConfig.set(row.id,{station_id:Number(sid),station_name:sname});
      else pinConfig.delete(row.id);

      updateMarkerVisual(marker);
      setStatus('Saved');

      infoWindow.setContent(editorHtml(row));
      infoWindow.open(getMapForZone(marker._zone||0),marker);
      google.maps.event.addListenerOnce(infoWindow,'domready',()=>wirePopupHandlers(marker,row));
      renderTable();
      refreshPinSearchDropdown();
    }catch(e){setStatus('Save failed: '+e.message);}
  });

  btnDel?.addEventListener('click',async ev=>{
    ev.preventDefault();ev.stopPropagation();
    if(!confirm('Delete this pin?'))return;
    try{
      setStatus('Deleting…');
      await deletePoint(row.id);
      marker.setMap(null);
      pinById.delete(row.id);
      pinConfig.delete(row.id);
      infoWindow.close();
      setStatus('Deleted');
      renderTable();
      refreshPinSearchDropdown();
    }catch(e){setStatus('Delete failed: '+e.message);}
  });

  $('#pp-cancel')?.addEventListener('click',ev=>{
    ev.preventDefault();ev.stopPropagation();
    infoWindow.close();
  });
}

function addMarker(row,zone){
  const s=markerStatusForRow(row);
  const map=getMapForZone(zone);
  const marker=new google.maps.Marker({
    position:{lat:row.lat,lng:row.lng},
    map,
    icon:iconByStatus(s),
    title:miniTipText(row),
    draggable:false,
    zIndex:(s===1?1400:500)
  });
  marker._row=row;
  marker._zone=zone;

  marker.addListener('click',()=>{
    infoWindow.close();
    infoWindow.setContent(editorHtml(row));
    infoWindow.open(getMapForZone(marker._zone||0),marker);
    google.maps.event.addListenerOnce(infoWindow,'domready',()=>wirePopupHandlers(marker,row));
  });
  marker.addListener('dragstart',()=>setStatus('Moving pin… (release to save)'));
  marker.addListener('dragend',async e=>{
    try{
      const lat=e.latLng.lat();
      const lng=e.latLng.lng();
      setStatus('Updating position…');
      await updatePoint(row.id,{lat,lng});
      row.lat=lat;row.lng=lng;
      marker._row=row;
      marker.setTitle(miniTipText(row));
      setStatus('Position saved');
      if(infoWindow.getMap()){
        infoWindow.setContent(editorHtml(row));
        infoWindow.open(getMapForZone(marker._zone||0),marker);
        google.maps.event.addListenerOnce(infoWindow,'domready',()=>wirePopupHandlers(marker,row));
      }
      refreshPinSearchDropdown();
    }catch(err){setStatus('Move failed: '+err.message);}
  });

  pinById.set(row.id,marker);
  applyMoveModeToMarker(marker);
}
function applyMoveModeToMarker(marker){marker.setDraggable(!!moveMode);}
function updateAllMarkersMoveMode(){pinById.forEach(m=>applyMoveModeToMarker(m));}
function updateMarkerVisual(marker){
  const row=marker._row;
  const s=markerStatusForRow(row);
  marker.setIcon(iconByStatus(s));
  marker.setZIndex(s===1?1400:500);
  marker.setTitle(miniTipText(row));
}
function refreshMarkersFromReadings(){pinById.forEach(m=>updateMarkerVisual(m));}

/* clustering into 2 zones */
function clusterRowsIntoZones(rows){
  const zones=[[],[]],byId={};const n=rows.length;
  if(!n) return{byId,zones};
  if(n===1){byId[rows[0].id]=0;zones[0].push(rows[0]);return{byId,zones};}
  let minIdx=0,maxIdx=0;
  for(let i=1;i<n;i++){
    if(rows[i].lng<rows[minIdx].lng)minIdx=i;
    if(rows[i].lng>rows[maxIdx].lng)maxIdx=i;
  }
  let c1={lat:rows[minIdx].lat,lng:rows[minIdx].lng};
  let c2={lat:rows[maxIdx].lat,lng:rows[maxIdx].lng};
  const d2=(r,c)=>{const dx=r.lat-c.lat,dy=r.lng-c.lng;return dx*dx+dy*dy;};
  for(let iter=0;iter<5;iter++){
    zones[0]=[];zones[1]=[];
    rows.forEach(r=>{
      const k=d2(r,c1)<=d2(r,c2)?0:1;
      byId[r.id]=k;zones[k].push(r);
    });
    if(zones[0].length){
      let sx=0,sy=0;zones[0].forEach(r=>{sx+=r.lat;sy+=r.lng;});
      c1={lat:sx/zones[0].length,lng:sy/zones[0].length};
    }
    if(zones[1].length){
      let sx=0,sy=0;zones[1].forEach(r=>{sx+=r.lat;sy+=r.lng;});
      c2={lat:sx/zones[1].length,lng:sy/zones[1].length};
    }
  }
  return{byId,zones};
}
function fitMapsToZones(zones){
  zones.forEach((rows,zone)=>{
    const map=getMapForZone(zone);if(!map)return;
    if(!rows || !rows.length){map.setCenter(DEFAULT_CENTER);map.setZoom(7);return;}
    const bounds=new google.maps.LatLngBounds();
    rows.forEach(r=>bounds.extend({lat:r.lat,lng:r.lng}));
    map.fitBounds(bounds);
  });
}

/* Pin dropdown */
function refreshPinSearchDropdown(){
  const sel=document.getElementById('selPin');if(!sel)return;
  const prev=sel.value;const items=[];
  pinById.forEach((marker,id)=>{
    const r=marker._row||{};
    const label=(r.name && r.name.trim())?r.name.trim():('#'+id);
    items.push({id,label});
  });
  items.sort((a,b)=>a.label.localeCompare(b.label,'th-TH'));
  let html='<option value="">— Select pin —</option>';
  items.forEach(it=>{html+=`<option value="${it.id}">${esc(it.label)} (#${it.id})</option>`;});
  sel.innerHTML=html;
  if(prev && pinById.has(Number(prev))) sel.value=prev;
}

/* card maximize for maps */
const mapsWrapEl=document.getElementById('mapsWrap');
document.getElementById('mapCardAHeader').addEventListener('click',()=>toggleZoneMax(0));
document.getElementById('mapCardBHeader').addEventListener('click',()=>toggleZoneMax(1));
function toggleZoneMax(zone){
  const cls='max-zone-'+zone;
  if(mapsWrapEl.classList.contains(cls)){
    mapsWrapEl.classList.remove('max-zone-0','max-zone-1');
  }else{
    mapsWrapEl.classList.remove('max-zone-0','max-zone-1');
    mapsWrapEl.classList.add(cls);activeZone=zone;
  }
}
function focusMarker(marker){
  if(!marker)return;
  const zone=marker._zone||0;activeZone=zone;
  mapsWrapEl.classList.remove('max-zone-0','max-zone-1');
  mapsWrapEl.classList.add('max-zone-'+zone);
  const map=getMapForZone(zone);
  map.setCenter(marker.getPosition());
  map.setZoom(Math.max(map.getZoom(),16));
  google.maps.event.trigger(marker,'click');
}
function focusSelectedPin(){
  const sel=document.getElementById('selPin');const v=sel.value;if(!v)return;
  const gid=Number(v);const m=pinById.get(gid);if(m)focusMarker(m);
}

/* load pins */
async function loadPins(useName=null){
  try{
    setStatus('Loading pins…');
    const raw=await listPoints(useName);
    const rows=raw.map(r=>({
      id:Number(r.id),
      name:r.name||null,
      lat:Number(r.lat),
      lng:Number(r.lng),
      status:Number(r.status||0),
      status_timestamp:r.status_timestamp||null,
      station_id:r.station_id ?? null,
      station_name:r.station_name ?? null
    }));
    const {byId,zones}=clusterRowsIntoZones(rows);
    const cardB=document.getElementById('mapCardB');
    if(!zones[1] || !zones[1].length){
      cardB.style.display='none';
      mapsWrapEl.classList.remove('max-zone-1');
      if(activeZone===1) activeZone=0;
    }else cardB.style.display='flex';

    pinById.forEach(m=>m.setMap(null));
    pinById.clear();
    pinConfig.clear();
    rows.forEach(r=>{
      if(r.station_id!==null || (r.station_name && r.station_name!=='')){
        const sid=r.station_id!==null && r.station_id!=='' ? Number(r.station_id):null;
        pinConfig.set(r.id,{station_id:sid,station_name:r.station_name || ''});
      }
    });
    rows.forEach(row=>{
      const zone=byId[row.id] || 0;
      addMarker(row,zone);
    });
    fitMapsToZones(zones);
    setStatus(`Loaded ${rows.length} pins • Move Pins: ${moveMode?'ON':'OFF'}`);
    refreshMarkersFromReadings();
    renderTable();
    refreshPinSearchDropdown();
  }catch(e){setStatus('Pin load failed: '+e.message);}
}

/* === SUMMARY / TABLE (API only) === */
const GAUGE_RADIUS=52;
const GAUGE_CIRC=2*Math.PI*GAUGE_RADIUS;
const gaugeCircle=document.getElementById('gaugeCircle');
function updateGauge(percent){
  if(!gaugeCircle)return;
  const p=Math.max(0,Math.min(100,percent||0));
  const offset=GAUGE_CIRC*(1-p/100);
  gaugeCircle.style.strokeDasharray=GAUGE_CIRC.toFixed(1);
  gaugeCircle.style.strokeDashoffset=offset.toFixed(1);
  const t=document.getElementById('gaugePercent');if(t)t.textContent=`${Math.round(p)}%`;
  const lbl=document.getElementById('gPercentLabel');if(lbl)lbl.textContent=`${Math.round(p)}%`;
}
function renderSummary(){
  const total=readingApiByStation.size;
  let alerts=0,normals=0;
  readingApiByStation.forEach(r=>{
    if(r.status===1 || r.status==='1')alerts++;
    else normals++;
  });
  const bound=new Set();
  pinConfig.forEach(cfg=>{
    if(cfg && cfg.station_id!=null) bound.add(Number(cfg.station_id));
  });
  const set=(id,val)=>{const el=document.getElementById(id);if(el)el.textContent=String(val);};
  set('sumTotal',total);set('sumNormal',normals);set('sumAlert',alerts);set('sumBound',bound.size);
  set('gTotal',total);set('gNormal',normals);set('gAlert',alerts);
  const pct=total>0?(normals*100/total):0;
  updateGauge(pct);

  const tbody=document.getElementById('summaryTableBody');if(!tbody)return;
  tbody.innerHTML='';
  const stationToPin=new Map();
  pinConfig.forEach((cfg,gid)=>{
    if(cfg && cfg.station_id!=null){
      const sid=Number(cfg.station_id);
      if(!stationToPin.has(sid)) stationToPin.set(sid,gid);
    }
  });
  const items=[];
  readingApiByStation.forEach((read,sid)=>{
    const gid=stationToPin.get(Number(sid)) ?? null;
    const pin=gid!=null ? pinById.get(gid) : null;
    const pinName=pin ? (pin._row?.name || ('#'+gid)) : '';
    items.push({
      station_id:Number(sid),
      pin_name:pinName,
      status:read.status!=null?Number(read.status):0,
      temp:read.temp!=null?Number(read.temp):null,
      humid:read.humid!=null?Number(read.humid):null,
      timestamp:read.timestamp || ''
    });
  });
  items.sort((a,b)=>{
    if(a.status!==b.status) return b.status-a.status;
    return a.station_id-b.station_id;
  });
  items.slice(0,10).forEach(it=>{
    const tr=document.createElement('tr');
    const stCell=it.status===1?'<span class="badge-bad">Alert</span>':'<span class="badge-ok">Normal</span>';
    tr.innerHTML=`
      <td>${it.station_id}</td>
      <td>${esc(it.pin_name || '—')}</td>
      <td>${stCell}</td>
      <td>${it.temp!=null?it.temp.toFixed(2):'-'}</td>
      <td>${it.humid!=null?it.humid.toFixed(2):'-'}</td>
      <td>${esc(formatTs(it.timestamp))}</td>`;
    tbody.appendChild(tr);
  });
}
function renderTable(){renderSummary();}

/* === TEMP PAGE – cards from MQTT === */
const TEMP_GAUGE_RADIUS=52;
const TEMP_GAUGE_CIRC=2*Math.PI*TEMP_GAUGE_RADIUS;
const tempGaugeStates=new Map();
let lastTempIds=[];
let tempDragEnabled=true;

const tempBoard=document.getElementById('tempBoard');
const tempLockBtn=document.getElementById('tempLockBtn');
const tempCountLabel=document.getElementById('tempCountLabel');

/* localStorage for card positions */
const LS_TEMP_POS_KEY='kbs_temp_card_positions';
function loadTempPositions(){
  try{
    const raw=localStorage.getItem(LS_TEMP_POS_KEY);
    if(!raw) return {};
    const obj=JSON.parse(raw);
    return (obj && typeof obj==='object') ? obj : {};
  }catch(_){return {};}
}
function saveTempPositions(){
  if(!tempBoard) return;
  const obj={};
  tempBoard.querySelectorAll('.temp-card').forEach(card=>{
    const sid=card.dataset.sid;
    if(!sid) return;
    const left=parseFloat(card.style.left)||0;
    const top =parseFloat(card.style.top)||0;
    obj[sid]={left,top};
  });
  try{localStorage.setItem(LS_TEMP_POS_KEY,JSON.stringify(obj));}
  catch(e){console.warn('saveTempPositions failed:',e.message);}
}

if(tempLockBtn){
  tempLockBtn.addEventListener('click',()=>{
    tempDragEnabled=!tempDragEnabled;
    if(tempDragEnabled){
      tempLockBtn.textContent='🔓 Free layout';
      tempLockBtn.classList.remove('locked');
      tempBoard.querySelectorAll('.temp-card').forEach(c=>c.classList.remove('locked'));
    }else{
      tempLockBtn.textContent='🔒 Locked';
      tempLockBtn.classList.add('locked');
      tempBoard.querySelectorAll('.temp-card').forEach(c=>c.classList.add('locked'));
    }
  });
}

/* NEW updateTempCard with 1/1 = green logic */
function updateTempCard(stationId){
  const state = tempGaugeStates.get(stationId);
  if (!state) return;

  const read = readingMqttByStation.get(stationId);
  let temp = null, status = null, s1 = null, s2 = null, ts = '';
  if (read) {
    temp   = read.temp   != null ? read.temp   : null;
    status = read.status != null ? Number(read.status) : null;
    s1     = read.status1 != null ? Number(read.status1) : null;
    s2     = read.status2 != null ? Number(read.status2) : null;
    ts     = read.timestamp || '';
  }

  const pct    = temp != null ? Math.max(0, Math.min(100, (temp / 80) * 100)) : 0;
  const offset = TEMP_GAUGE_CIRC * (1 - pct / 100);
  state.circle.style.strokeDasharray  = TEMP_GAUGE_CIRC.toFixed(1);
  state.circle.style.strokeDashoffset = offset.toFixed(1);
  state.tempText.textContent = temp != null ? `${temp.toFixed(1)}°C` : '--°C';

  const labelText = getStationLabel(stationId);
  state.label.textContent = labelText;
  if (state.stationLabelEl) state.stationLabelEl.textContent = labelText;

  state.valueEl.textContent = temp != null ? `${temp.toFixed(2)} °C` : '--';
  state.tsEl.textContent    = formatTs(ts);

  state.bulb.classList.remove('ok','bad','unknown');
  if (status === 1) {
    state.bulb.classList.add('bad');      // Alert → red
  } else if (status === 0) {
    state.bulb.classList.add('ok');       // Normal → green
  } else {
    state.bulb.classList.add('unknown');  // Unknown
  }

  if (s1 !== null || s2 !== null) {
    const s1txt = s1 !== null ? s1 : '-';
    const s2txt = s2 !== null ? s2 : '-';
    let label;
    if (status === 1)      label = 'Alert';
    else if (status === 0) label = 'ปกติ';
    else                   label = 'Unknown';
    state.statusText.textContent = `สถานะ: ${s1txt}/${s2txt} (${label})`;
  } else {
    if (status === 1)      state.statusText.textContent = 'สถานะ: Alert';
    else if (status === 0) state.statusText.textContent = 'สถานะ: ปกติ';
    else                   state.statusText.textContent = 'สถานะ: Unknown';
  }
}

/* layout – supports saved positions + 2×2 grid + auto-height */
function layoutTempCards(ids, savedPositions=null){
  if(!tempBoard) return;

  if(window.innerWidth<=768){
    // mobile: reset to normal flow
    tempBoard.style.height='auto';
    tempBoard.querySelectorAll('.temp-card').forEach(c=>{
      c.style.left='';c.style.top='';
    });
    return;
  }

  const boardRect  = tempBoard.getBoundingClientRect();
  const boardWidth = boardRect.width || 800;
  const cardWidth  = 260;
  const gapX = 20, gapY = 16;

  const colCount = (ids.length === 4 && (!savedPositions || Object.keys(savedPositions).length===0))
    ? 2
    : Math.max(1, Math.floor((boardWidth - 20) / (cardWidth + gapX)));

  ids.forEach((id, idx) => {
    const card = tempBoard.querySelector(`.temp-card[data-sid="${id}"]`);
    if (!card) return;
    if (card.classList.contains('maximized')) return;

    let left, top;
    const saved = savedPositions ? savedPositions[String(id)] : null;

    if(saved && typeof saved.left==='number' && typeof saved.top==='number'){
      left = saved.left;
      top  = saved.top;
    }else{
      const col = idx % colCount;
      const row = Math.floor(idx / colCount);
      left = 10 + col * (cardWidth + gapX);
      top  = 10 + row * (140 + gapY); // 140 ≈ card height
    }

    card.style.left = left + 'px';
    card.style.top  = top  + 'px';
  });

  // adjust board height so background covers all cards
  let maxBottom = 0;
  tempBoard.querySelectorAll('.temp-card').forEach(card=>{
    const bottom = card.offsetTop + card.offsetHeight;
    if(bottom > maxBottom) maxBottom = bottom;
  });
  tempBoard.style.height = (maxBottom + 20) + 'px';

  // persist layout
  saveTempPositions();
}

/* drag */
function makeTempCardDraggable(card){
  const header=card.querySelector('.temp-row-top');
  if(!header)return;
  header.addEventListener('mousedown',e=>{
    if(!tempDragEnabled) return;
    if(window.innerWidth<=768) return;
    if(card.classList.contains('maximized')) return;

    e.preventDefault();
    let dragging=true;
    card.classList.add('dragging');
    const boardRect=tempBoard.getBoundingClientRect();
    const rect=card.getBoundingClientRect();
    const offsetX=e.clientX-rect.left;
    const offsetY=e.clientY-rect.top;
    function onMove(ev){
      if(!dragging)return;
      const x=ev.clientX-boardRect.left-offsetX;
      const y=ev.clientY-boardRect.top-offsetY;
      card.style.left=x+'px';
      card.style.top =y+'px';
    }
    function onUp(){
      dragging=false;card.classList.remove('dragging');
      window.removeEventListener('mousemove',onMove);
      window.removeEventListener('mouseup',onUp);
      saveTempPositions(); // save new layout
      layoutTempCards(lastTempIds, loadTempPositions()); // recompute height
    }
    window.addEventListener('mousemove',onMove);
    window.addEventListener('mouseup',onUp);
  });
}

/* render temp cards */
function renderTempPage(force=false){
  if(!tempBoard)return;
  const ids=Array.from(readingMqttByStation.keys()).sort((a,b)=>a-b);
  if(tempCountLabel) tempCountLabel.textContent=`(${ids.length} sensors from MQTT)`;

  const changed = force ||
    ids.length!==lastTempIds.length ||
    ids.some((id,i)=>id!==lastTempIds[i]);

  if(!ids.length){
    tempBoard.innerHTML='<div style="font-size:12px;color:#6b7280;padding:10px;">ยังไม่มีข้อมูลจาก MQTT</div>';
    tempGaugeStates.clear();
    lastTempIds=[];
    tempBoard.style.height='auto';
    return;
  }

  const savedPositions = loadTempPositions();

  if(changed){
    tempBoard.innerHTML='';
    tempGaugeStates.clear();
    ids.forEach(id=>{
      const labelText=getStationLabel(id);
      const card=document.createElement('div');
      card.className='temp-card';
      card.dataset.sid=String(id);
      if(!tempDragEnabled)card.classList.add('locked');
      card.innerHTML=`
        <div class="temp-card-left">
          <svg viewBox="0 0 120 120">
            <circle class="temp-gauge-bg" cx="60" cy="60" r="52"></circle>
            <circle class="temp-gauge-circle" cx="60" cy="60" r="52"
                    stroke-dasharray="326" stroke-dashoffset="326"></circle>
            <text class="temp-gauge-text-main" x="60" y="64" text-anchor="middle">--°C</text>
            <text class="temp-gauge-text-sub"  x="60" y="80" text-anchor="middle">${esc(labelText)}</text>
          </svg>
        </div>
        <div class="temp-card-right">
          <div class="temp-row-top">
            <span class="temp-station-label">${esc(labelText)}</span>
            <div class="temp-row-top-right">
              <span class="temp-status-text-line">สถานะ: Unknown</span>
              <button class="temp-max-btn" data-sid="${id}" title="ขยาย/ย่อ">⤢</button>
            </div>
          </div>
          <div class="temp-indicators">
            <div class="temp-bulb unknown"></div>
            <div class="temp-meta">
              <span>Station ID: <span class="temp-sid">${id}</span></span>
              <span>Temp: <span class="temp-value">--</span></span>
              <span>เวลา: <span class="temp-ts">-</span></span>
            </div>
          </div>
        </div>`;
      tempBoard.appendChild(card);
      const circle  =card.querySelector('.temp-gauge-circle');
      const tempTxt =card.querySelector('.temp-gauge-text-main');
      const label   =card.querySelector('.temp-gauge-text-sub');
      const bulb    =card.querySelector('.temp-bulb');
      const stTxt   =card.querySelector('.temp-status-text-line');
      const valEl   =card.querySelector('.temp-value');
      const tsEl    =card.querySelector('.temp-ts');
      const stationLabelEl=card.querySelector('.temp-station-label');
      tempGaugeStates.set(id,{circle,tempText:tempTxt,label,bulb,statusText:stTxt,valueEl:valEl,tsEl,stationLabelEl});
      makeTempCardDraggable(card);
      updateTempCard(id);
    });
    layoutTempCards(ids, savedPositions);
    lastTempIds=ids;
  }else{
    ids.forEach(id=>updateTempCard(id));
  }
}

/* Maximize / restore temp widget */
if(tempBoard){
  tempBoard.addEventListener('click',ev=>{
    const btn=ev.target.closest('.temp-max-btn');
    if(!btn) return;
    const sid=btn.dataset.sid;
    const card=tempBoard.querySelector(`.temp-card[data-sid="${sid}"]`);
    if(!card) return;

    const cards=tempBoard.querySelectorAll('.temp-card');
    const isMax=card.classList.contains('maximized');

    if(isMax){
      cards.forEach(c=>{
        c.classList.remove('maximized','hidden-when-max');
      });
      layoutTempCards(lastTempIds, loadTempPositions());
    }else{
      cards.forEach(c=>{
        if(c===card){
          c.classList.add('maximized');
          c.classList.remove('hidden-when-max');
        }else{
          c.classList.remove('maximized');
          c.classList.add('hidden-when-max');
        }
      });
      tempBoard.style.height='auto';
    }
  });
}

/* TOOLBAR & NAV */
document.getElementById('btnReload').addEventListener('click',()=>loadPins());
document.getElementById('btnSearch').addEventListener('click',()=>{
  const q=document.getElementById('searchName').value || null;
  loadPins(q);
});
document.getElementById('btnAddPin').addEventListener('click',async ()=>{
  try{
    const map=getMapForZone(activeZone);
    const center=map.getCenter();
    const name=prompt('Name for new pin:','');if(name===null)return;
    setStatus('Creating pin…');
    await createPoint(name||'',center.lat(),center.lng(),null,'');
    setStatus('Created (reloading)…');
    await loadPins();
  }catch(e){setStatus('Create failed: '+e.message);}
});
document.getElementById('btnResub').addEventListener('click',()=>{
  const t=(document.getElementById('topicInput').value || '').trim();if(!t)return;
  try{
    if(mqttClient){
      try{mqttClient.unsubscribe(MQTT_TOPIC);}catch(_){}
      MQTT_TOPIC=t;
      mqttClient.subscribe(MQTT_TOPIC);
      setStatus('Subscribed: '+MQTT_TOPIC);
    }
  }catch(e){setStatus('Subscribe failed: '+e.message);}
});
const btnMovePins=document.getElementById('btnMovePins');
btnMovePins.addEventListener('click',()=>{
  moveMode=!moveMode;
  btnMovePins.textContent=`Move Pins: ${moveMode?'ON':'OFF'}`;
  btnMovePins.classList.toggle('toggle-on',moveMode);
  setStatus(`Move Pins ${moveMode?'enabled':'disabled'}`);
  updateAllMarkersMoveMode();
});
const btnTogglePoi=document.getElementById('btnTogglePoi');
btnTogglePoi.addEventListener('click',()=>{
  showPois=!showPois;
  btnTogglePoi.textContent=`POI: ${showPois?'ON':'OFF'}`;
  btnTogglePoi.classList.toggle('toggle-on',!showPois);
  applyPoiStyles();
});
document.getElementById('btnPinGo').addEventListener('click',focusSelectedPin);
document.getElementById('selPin').addEventListener('change',focusSelectedPin);

document.getElementById('btnToggleTable').addEventListener('click',()=>showSummaryPage());

function setRefresh(ms){
  if(refreshTimer){clearInterval(refreshTimer);refreshTimer=null;}
  if(ms>0){
    refreshTimer=setInterval(async ()=>{
      const q=document.getElementById('searchName').value || null;
      await loadPins(q);
      await loadLatestSnapshot();
      renderTempPage();
    },ms);
  }
}
document.getElementById('selRefresh').addEventListener('change',e=>{
  const ms=parseInt(e.target.value,10) || 0;
  setRefresh(ms);
});

/* nav pages */
const navMap=document.getElementById('navMap');
const navSummary=document.getElementById('navSummary');
const navTemp=document.getElementById('navTemp');
const navRaw=document.getElementById('navRaw');
const navSettings=document.getElementById('navSettings');

const pageMapEl=document.getElementById('pageMap');
const pageSummary=document.getElementById('pageSummary');
const pageTemp=document.getElementById('pageTemp');
const pageRaw=document.getElementById('pageRaw');
const pageSettings=document.getElementById('pageSettings');

function setActiveNav(t){
  [navMap,navSummary,navTemp,navRaw,navSettings].forEach(n=>n.classList.remove('active'));
  t.classList.add('active');
}
function showMapPage(){
  pageMapEl.style.display='flex';
  pageSummary.style.display='none';
  pageTemp.style.display='none';
  pageRaw.style.display='none';
  pageSettings.style.display='none';
  setActiveNav(navMap);
}
function showSummaryPage(){
  pageMapEl.style.display='none';
  pageSummary.style.display='flex';
  pageTemp.style.display='none';
  pageRaw.style.display='none';
  pageSettings.style.display='none';
  setActiveNav(navSummary);
}
function showTempPage(){
  pageMapEl.style.display='none';
  pageSummary.style.display='none';
  pageTemp.style.display='flex';
  pageRaw.style.display='none';
  pageSettings.style.display='none';
  setActiveNav(navTemp);
  renderTempPage(true);
}
function showRawPage(){
  pageMapEl.style.display='none';
  pageSummary.style.display='none';
  pageTemp.style.display='none';
  pageRaw.style.display='flex';
  pageSettings.style.display='none';
  setActiveNav(navRaw);
}
function showSettingsPage(){
  pageMapEl.style.display='none';
  pageSummary.style.display='none';
  pageTemp.style.display='none';
  pageRaw.style.display='none';
  pageSettings.style.display='flex';
  setActiveNav(navSettings);
}

navMap.addEventListener('click',showMapPage);
navSummary.addEventListener('click',showSummaryPage);
navTemp.addEventListener('click',showTempPage);
navRaw.addEventListener('click',showRawPage);
navSettings.addEventListener('click',showSettingsPage);

document.getElementById('navToggle').addEventListener('click',()=>{
  document.body.classList.toggle('nav-expanded');
});

/* INIT */
async function initApp(){
  await fetchNames(false);
  await loadLatestSnapshot();
  await loadPins();
  mqttConnect();
  showMapPage();
}
</script>
</body>
</html>
