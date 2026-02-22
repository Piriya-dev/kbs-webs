<?php
/**
 * station_report.php - Full Integrated Version with Statistics
 */
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
    <title>Historical Report - Motor Drive Room</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --bg-dark: #0f172a; --panel-dark: #1e293b; --border-color: #334155; --text-muted: #94a3b8; --primary-blue: #3b82f6; --success-green: #10b981; }
        body { margin: 0; font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: #fff; display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 260px; background: #0f172a; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .nav-item { display: flex; align-items: center; padding: 12px 16px; color: var(--text-muted); text-decoration: none; margin-bottom: 4px; transition: 0.2s; }
        .nav-item.active { background: var(--primary-blue); color: #fff; border-radius: 8px; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; padding: 25px; }
        
        /* Filter Panel */
        .filter-panel { background: var(--panel-dark); padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end; border: 1px solid var(--border-color); }
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { color: var(--text-muted); font-size: 0.75rem; font-weight: bold; }
        input { background: var(--bg-dark); border: 1px solid var(--border-color); color: #fff; padding: 10px; border-radius: 8px; outline: none; }
        
        .btn-query { background: var(--primary-blue); color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-export { background: var(--success-green); color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; margin-left: 10px; }

        /* Statistics Cards */
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stats-card { background: var(--panel-dark); padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); border-top: 4px solid var(--primary-blue); }
        .stats-title { font-size: 0.9rem; font-weight: bold; margin-bottom: 12px; display: flex; justify-content: space-between; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .stats-box { background: rgba(15, 23, 42, 0.4); padding: 10px; border-radius: 8px; text-align: center; }
        .stats-label { font-size: 0.65rem; color: var(--text-muted); display: block; margin-bottom: 4px; }
        .stats-val { font-size: 1rem; font-weight: 800; font-family: monospace; }
        .val-temp { color: #3b82f6; }
        .val-humid { color: #10b981; }

        .report-chart-container { background: var(--panel-dark); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); min-height: 500px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div style="padding: 20px; font-weight: bold; font-size: 1.2rem; color: #fff;">KBS MONITORING</div>
    <nav style="padding: 10px;">
        <a href="/motor_drive_room_dashboard" class="nav-item">📊 Dashboard</a>
        <a href="/motor_drive_room_report" class="nav-item active">📈 Historical Report</a>
        <a href="/motor_drive_room_settings" class="nav-item">⚙️ Settings</a>
    </nav>
</aside>

<main class="main-content">
    <h2 style="margin-top:0;">📈 รายงานวิเคราะห์ข้อมูลและสถิติ</h2>
    
    <div class="filter-panel">
        <div class="input-group">
            <label>เริ่มต้น (From)</label>
            <input type="datetime-local" id="dateFrom">
        </div>
        <div class="input-group">
            <label>สิ้นสุด (To)</label>
            <input type="datetime-local" id="dateTo">
        </div>
        <div class="input-group">
            <label>เลือกเครื่องจักร</label>
            <div style="display: flex; gap: 10px; background: var(--bg-dark); padding: 8px; border-radius: 8px;">
                <label><input type="checkbox" class="sensor-chk" value="1" checked> S1</label>
                <label><input type="checkbox" class="sensor-chk" value="2"> S2</label>
                <label><input type="checkbox" class="sensor-chk" value="3"> S3</label>
                <label><input type="checkbox" class="sensor-chk" value="4"> S4</label>
            </div>
        </div>
        <div>
            <button class="btn-query" onclick="loadReportData()">ดึงข้อมูล</button>
            <button class="btn-export" onclick="exportToExcel()">💾 Export CSV</button>
        </div>
    </div>

    <div id="statsWrapper" class="stats-container"></div>

    <div class="report-chart-container">
        <canvas id="reportChart"></canvas>
    </div>
</main>

<script>
let reportChart;

function initChart() {
    const ctx = document.getElementById('reportChart').getContext('2d');
    reportChart = new Chart(ctx, {
        type: 'line',
        data: { datasets: [] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { grid: { color: '#334155' }, ticks: { color: '#94a3b8' } },
                y: { type: 'linear', position: 'left', title: { display: true, text: 'Temperature (°C)', color: '#3b82f6' } },
                y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Humidity (%)', color: '#10b981' } }
            },
            plugins: { legend: { labels: { color: '#fff' } } }
        }
    });
}

async function loadReportData() {
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    const selectedSensors = Array.from(document.querySelectorAll('.sensor-chk:checked')).map(cb => cb.value);

    try {
        const response = await fetch(`/report_fetch_data?from=${from}&to=${to}&sensors=${selectedSensors.join(',')}`);
        const data = await response.json();
        updateChartAndStats(data, selectedSensors);
    } catch (err) { console.error(err); }
}

function updateChartAndStats(rawData, selectedSensors) {
    const datasets = [];
    const windowSize = 10;
    const statsWrapper = document.getElementById('statsWrapper');
    statsWrapper.innerHTML = '';

    selectedSensors.forEach((sID, idx) => {
        const sensorPoints = rawData.filter(d => d.sensor_id == sID);
        if (sensorPoints.length === 0) return;

        const temps = sensorPoints.map(d => parseFloat(d.temperature));
        const humids = sensorPoints.map(d => parseFloat(d.humidity));

        // --- คำนวณ Min/Max/Avg ---
        const calcStats = (arr) => ({
            min: Math.min(...arr).toFixed(2),
            max: Math.max(...arr).toFixed(2),
            avg: (arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(2)
        });

        const tStats = calcStats(temps);
        const hStats = calcStats(humids);

        // --- สร้าง Stats Card ---
        const card = document.createElement('div');
        card.className = 'stats-card';
        card.innerHTML = `
            <div class="stats-title"><span>Unit ${sID} Summary</span> <span style="color:var(--primary-blue)">${sensorPoints.length} Pts</span></div>
            <div class="stats-grid">
                <div class="stats-box"><span class="stats-label">TEMP AVG</span><span class="stats-val val-temp">${tStats.avg}°C</span></div>
                <div class="stats-box"><span class="stats-label">TEMP MIN/MAX</span><span class="stats-val val-temp">${tStats.min}-${tStats.max}°</span></div>
                <div class="stats-box"><span class="stats-label">HUMID AVG</span><span class="stats-val val-humid">${hStats.avg}%</span></div>
                <div class="stats-box"><span class="stats-label">HUMID MIN/MAX</span><span class="stats-val val-humid">${hStats.min}-${hStats.max}</span></div>
            </div>
        `;
        statsWrapper.appendChild(card);

        // --- ระบบ Smoothing สำหรับกราฟ ---
        const smooth = (arr) => arr.map((val, i, a) => {
            const subset = a.slice(Math.max(0, i - windowSize), i + 1);
            return (subset.reduce((s, v) => s + v, 0) / subset.length).toFixed(2);
        });

        datasets.push({
            label: `U${sID} Temp`, data: smooth(temps), borderColor: '#3b82f6', yAxisID: 'y', tension: 0.4, pointRadius: 0
        });
        datasets.push({
            label: `U${sID} Humid`, data: smooth(humids), borderColor: '#10b981', yAxisID: 'y1', borderDash: [5, 5], tension: 0.4, pointRadius: 0
        });
    });

    reportChart.data.labels = [...new Set(rawData.map(d => d.created_at.substring(11, 16)))];
    reportChart.data.datasets = datasets;
    reportChart.update();
}

function exportToExcel() {
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    const selectedSensors = Array.from(document.querySelectorAll('.sensor-chk:checked')).map(cb => cb.value);
    window.location.href = `/report_fetch_data?from=${from}&to=${to}&sensors=${selectedSensors.join(',')}&export=true`;
}

window.onload = () => {
    const now = new Date();
    document.getElementById('dateTo').value = now.toISOString().slice(0, 16);
    document.getElementById('dateFrom').value = new Date(now - 86400000).toISOString().slice(0, 16);
    initChart();
    loadReportData();
};
</script>
</body>
</html>