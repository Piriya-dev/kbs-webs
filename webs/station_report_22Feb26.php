<?php
session_start();

// 1. ตรวจสอบสิทธิ์ (ป้องกันหน้าขาวถ้า Session หลุด)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /motor_drive_room_login");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <title>Historical Report - Motor Drive Room</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --bg-dark: #0f172a; --panel-dark: #1e293b; --border-color: #334155; --primary-blue: #3b82f6; }
        body { margin: 0; font-family: sans-serif; background-color: var(--bg-dark); color: #fff; display: flex; height: 100vh; }
        .sidebar { width: 250px; border-right: 1px solid var(--border-color); padding: 20px; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .filter-panel { background: var(--panel-dark); padding: 20px; border-radius: 12px; display: flex; gap: 15px; align-items: flex-end; margin-bottom: 20px; }
        .report-chart-container { background: var(--panel-dark); padding: 20px; border-radius: 12px; height: 500px; }
        .btn { padding: 10px 20px; border-radius: 6px; cursor: pointer; border: none; font-weight: bold; }
        .btn-blue { background: var(--primary-blue); color: #white; }
        .btn-green { background: #10b981; color: white; margin-left: 10px; }
        input { background: #0f172a; border: 1px solid var(--border-color); color: white; padding: 8px; border-radius: 5px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <h3>KBS Co., Ltd.</h3>
    <a href="/motor_drive_room_dashboard" style="color:#94a3b8; text-decoration:none;">⬅ กลับหน้า Dashboard</a>
</aside>

<main class="main-content">
    <h2>📈 Historical Analysis</h2>
    
    <div class="filter-panel">
        <div>
            <label style="display:block; font-size:12px; color:#94a3b8;">FROM</label>
            <input type="datetime-local" id="dateFrom">
        </div>
        <div>
            <label style="display:block; font-size:12px; color:#94a3b8;">TO</label>
            <input type="datetime-local" id="dateTo">
        </div>
        <div>
        <div>
    <label style="display:block; font-size:12px; color:#94a3b8;">SENSORS</label>
    <div style="font-size:14px; display:flex; gap:10px; background:#0f172a; padding:8px; border-radius:5px; border:1px solid #334155;">
        <label><input type="checkbox" class="sensor-chk" value="1" checked> Unit 1</label>
        <label><input type="checkbox" class="sensor-chk" value="2"> Unit 2</label>
        <label><input type="checkbox" class="sensor-chk" value="3"> Unit 3</label>
        <label><input type="checkbox" class="sensor-chk" value="4"> Unit 4</label>
    </div>
</div>
        </div>
        <button class="btn btn-blue" onclick="loadReportData()">ดึงข้อมูล</button>
        <button class="btn btn-green" onclick="exportToExcel()">💾 Export CSV</button>
    </div>

    <div class="report-chart-container">
        <canvas id="reportChart"></canvas>
    </div>
</main>

<script>
let reportChart;

// 1. เริ่มต้นสร้างกราฟ
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
                y: { 
                    type: 'linear', display: true, position: 'left',
                    title: { display: true, text: 'Temperature (°C)', color: '#3b82f6' },
                    ticks: { color: '#3b82f6' }
                },
                y1: { 
                    type: 'linear', display: true, position: 'right',
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Humidity (%)', color: '#10b981' },
                    ticks: { color: '#10b981' }
                }
            }
        }
    });
}

// 2. ดึงข้อมูลจาก API
async function loadReportData() {
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    const selectedSensors = Array.from(document.querySelectorAll('.sensor-chk:checked')).map(cb => cb.value);

    try {
        const response = await fetch(`/report_fetch_data?from=${from}&to=${to}&sensors=${selectedSensors.join(',')}`);
        const data = await response.json();
        updateChart(data, selectedSensors);
    } catch (err) {
        console.error("Error loading data:", err);
    }
}

// 3. อัปเดตกราฟ (Smoothing + Dual Axis)
function updateChart(rawData, selectedSensors) {
    const windowSize = 10;
    const datasets = [];

    selectedSensors.forEach((sID) => {
        const sensorPoints = rawData.filter(d => d.sensor_id == sID);
        if(sensorPoints.length === 0) return;

        const temps = sensorPoints.map(d => parseFloat(d.temperature));
        const humids = sensorPoints.map(d => parseFloat(d.humidity));

        const smooth = (arr) => arr.map((val, i, a) => {
            const subset = a.slice(Math.max(0, i - windowSize), i + 1);
            return (subset.reduce((s, v) => s + v, 0) / subset.length).toFixed(2);
        });

        datasets.push({
            label: `Unit ${sID} Temp`,
            data: smooth(temps),
            borderColor: '#3b82f6',
            yAxisID: 'y',
            tension: 0.4,
            pointRadius: 0
        });

        datasets.push({
            label: `Unit ${sID} Humid`,
            data: smooth(humids),
            borderColor: '#10b981',
            yAxisID: 'y1',
            borderDash: [5, 5],
            tension: 0.4,
            pointRadius: 0
        });
    });

    const labels = [...new Set(rawData.map(d => d.created_at.substring(11, 16)))];
    reportChart.data.labels = labels;
    reportChart.data.datasets = datasets;
    reportChart.update();
}

function exportToExcel() {
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    const selectedSensors = Array.from(document.querySelectorAll('.sensor-chk:checked')).map(cb => cb.value);
    window.location.href = `/report_fetch_data?from=${from}&to=${to}&sensors=${selectedSensors.join(',')}&export=true`;
}

// โหลดค่าเริ่มต้น
window.onload = () => {
    const now = new Date();
    document.getElementById('dateTo').value = now.toISOString().slice(0, 16);
    document.getElementById('dateFrom').value = new Date(now - 86400000).toISOString().slice(0, 16);
    initChart();
};
</script>

</body>
</html>