<?php
/**
 * station_report.php - Dual Axis (Temp & Humid) Smoothing Report
 * สำหรับ Khun Khim - ปรับปรุงเพื่อแสดงความสัมพันธ์ของข้อมูล
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
        
        .sidebar { width: 260px; background: #0f172a; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; transition: all 0.3s ease; }
        .sidebar-nav { padding: 20px 10px; flex-grow: 1; }
        .nav-item { display: flex; align-items: center; padding: 12px 16px; border-radius: 8px; color: var(--text-muted); text-decoration: none; margin-bottom: 4px; transition: all 0.2s; }
        .nav-item:hover { background: #1e293b; color: #fff; }
        .nav-item.active { background: var(--primary-blue); color: #fff; }
        .nav-item .icon { margin-right: 12px; font-size: 1.2rem; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .report-container { padding: 25px; }
        
        .filter-panel { background: var(--panel-dark); padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end; border: 1px solid var(--border-color); }
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { color: var(--text-muted); font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        input[type="datetime-local"] { background: var(--bg-dark); border: 1px solid var(--border-color); color: #fff; padding: 10px; border-radius: 8px; font-size: 0.9rem; outline: none; }
        
        .sensor-selector { display: flex; gap: 15px; background: var(--bg-dark); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border-color); }
        .checkbox-item { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; cursor: pointer; }
        
        .btn-query { background: var(--primary-blue); color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; height: 42px; }
        .btn-query:hover { background: #2563eb; }
        .btn-export { background: var(--success-green); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; height: 42px; margin-left: 10px; }

        .report-chart-container { background: var(--panel-dark); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); min-height: 500px; position: relative; }
        #chartLoader { display: none; color: var(--primary-blue); font-size: 0.85rem; margin-left: 10px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-nav">
        <a href="/motor_drive_room_dashboard" class="nav-item"><span class="icon">📊</span><span class="text">Dashboard</span></a>
        <a href="/motor_drive_room_report" class="nav-item active"><span class="icon">📈</span><span class="text">Historical Report</span></a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="/motor_drive_room_settings" class="nav-item"><span class="icon">⚙️</span><span class="text">Settings</span></a>
        <a href="/motor_drive_room_logs" class="nav-item"><span class="icon">📝</span><span class="text">Access Logs</span></a>
        <?php endif; ?>
    </div>

    <div style="padding: 20px; border-top: 1px solid var(--border-color); margin-top: auto;">
        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Current User</div>
        <div style="font-weight: 700; color: #fff; margin-top: 5px;"><?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?></div>
        <span style="display: inline-block; padding: 2px 8px; background: var(--primary-blue); color: #fff; border-radius: 4px; font-size: 0.65rem; font-weight: bold; margin-top: 5px;"><?php echo strtoupper($_SESSION['role']); ?></span>
    </div>

    <div style="padding-bottom: 20px;">
        <a href="/motor_drive_room_logout" class="nav-item" style="color: #ef4444;"><span class="icon">⏻</span><span class="text">Logout</span></a>
    </div>
</aside>

<main class="main-content">
    <div class="report-container">
        <h2 style="margin-top:0;">📈 รายงานความสัมพันธ์ อุณหภูมิและความชื้น</h2>
        
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
                <div class="sensor-selector">
                    <label class="checkbox-item"><input type="checkbox" class="sensor-chk" value="1" checked> Drive 1</label>
                    <label class="checkbox-item"><input type="checkbox" class="sensor-chk" value="2" checked> Drive 2</label>
                    <label class="checkbox-item"><input type="checkbox" class="sensor-chk" value="3" checked> Drive 3</label>
                    <label class="checkbox-item"><input type="checkbox" class="sensor-chk" value="4" checked> Drive 4</label>
                </div>
            </div>
            <div>
                <button class="btn-query" onclick="loadReportData()">ดึงข้อมูล</button>
                <button class="btn-export" onclick="exportToExcel()">💾 Export CSV</button>
                <span id="chartLoader">⌛ กำลังโหลด...</span>
            </div>
        </div>

        <div class="report-chart-container">
            <canvas id="reportChart"></canvas>
        </div>
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
                y: { 
                    type: 'linear', display: true, position: 'left',
                    grid: { color: '#334155' }, 
                    ticks: { color: '#3b82f6' },
                    title: { display: true, text: 'Temperature (°C)', color: '#3b82f6' }
                },
                y1: { 
                    type: 'linear', display: true, position: 'right',
                    grid: { drawOnChartArea: false }, 
                    ticks: { color: '#10b981' },
                    title: { display: true, text: 'Humidity (%)', color: '#10b981' }
                }
            },
            plugins: {
                legend: { labels: { color: '#fff', usePointStyle: true } },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    });
}

async function loadReportData() {
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    const loader = document.getElementById('chartLoader');
    const selectedSensors = Array.from(document.querySelectorAll('.sensor-chk:checked')).map(cb => cb.value);

    if (selectedSensors.length === 0) { alert("กรุณาเลือกเซนเซอร์อย่างน้อย 1 รายการ"); return; }
    loader.style.display = 'inline';

    try {
        const response = await fetch(`/report_fetch_data?from=${from}&to=${to}&sensors=${selectedSensors.join(',')}`);
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        updateChart(data, selectedSensors);
    } catch (err) {
        console.error("Fetch Error:", err);
        alert("Error: " + err.message);
    } finally {
        loader.style.display = 'none';
    }
}

function updateChart(rawData, selectedSensors) {
    const windowSize = 10; // ความสมูท (Smoothing Level)
    const datasets = [];

    // ฟังก์ชันช่วยทำ Smoothing
    const getSmoothedArray = (arr) => arr.map((val, i, a) => {
        const subset = a.slice(Math.max(0, i - windowSize + 1), i + 1);
        return (subset.reduce((sum, v) => sum + v, 0) / subset.length).toFixed(2);
    });

    selectedSensors.forEach((sID) => {
        const sensorPoints = rawData.filter(d => d.sensor_id == sID);
        if(sensorPoints.length === 0) return;

        const temps = sensorPoints.map(d => parseFloat(d.temperature));
        const humids = sensorPoints.map(d => parseFloat(d.humidity));

        // Dataset สำหรับ Temperature (แกนซ้าย)
        datasets.push({
            label: `Unit ${sID} Temp (°C)`,
            data: getSmoothedArray(temps),
            borderColor: '#3b82f6',
            backgroundColor: 'transparent',
            yAxisID: 'y',
            tension: 0.5,
            pointRadius: 0,
            borderWidth: 2
        });

        // Dataset สำหรับ Humidity (แกนขวา)
        datasets.push({
            label: `Unit ${sID} Humid (%)`,
            data: getSmoothedArray(humids),
            borderColor: '#10b981',
            backgroundColor: 'transparent',
            yAxisID: 'y1',
            borderDash: [5, 5], // เส้นประสำหรับความชื้น
            tension: 0.5,
            pointRadius: 0,
            borderWidth: 2
        });
    });

    const allLabels = [...new Set(rawData.map(d => {
        const date = new Date(d.created_at);
        return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }))];

    reportChart.data.labels = allLabels;
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
    const yesterday = new Date(now.getTime() - (24 * 60 * 60 * 1000));
    const formatDateTime = (date) => {
        const tzOffset = date.getTimezoneOffset() * 60000;
        return new Date(date - tzOffset).toISOString().slice(0, 16);
    };

    document.getElementById('dateFrom').value = formatDateTime(yesterday);
    document.getElementById('dateTo').value = formatDateTime(now);

    initChart();
    loadReportData();
};
</script>

</body>
</html>