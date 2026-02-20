<?php
// สมมติการจำลองข้อมูลจาก Database หรือ API
$productName = "ครบุรี";
$csatCurrent = 65;
$csatLastMonth = 73;
$responseTimeChange = "-2:09 hr";

// ข้อมูลสำหรับกราฟ (ในอนาคตคุณสามารถใช้ mysqli_fetch_all ดึงจาก DB ได้)
$lineChartData = [14, 10, 7, 6, 11, 15];
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>KBS-HR Dashboard </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: white; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .dashboard-card { background-color: #1e293b; border-radius: 0.75rem; padding: 1.5rem; transition: transform 0.2s; }
        .dashboard-card:hover { transform: translateY(-2px); }
        .red-alert-box { border: 2px solid #ef4444; background: rgba(239, 68, 68, 0.1); border-radius: 0.5rem; }
    </style>
</head>
<body class="p-8">

    <div class="max-w-7xl mx-auto">
    <a href="https://www.kbs.co.th/" target="_blank" class="transition-transform hover:scale-105">
                <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="KBS Logo" class="h-12 w-auto object-contain">
            </a>
        <h1 class="text-3xl font-extrabold mb-8 tracking-tight text-green-400">HR Dashboard</h1>

        <div class="grid grid-cols-12 gap-6">
            
            <div class="col-span-12 md:col-span-3 dashboard-card flex flex-col justify-center">
                <span class="text-slate-400 text-xs font-bold uppercase tracking-widest">Product Name</span>
                <h2 class="text-2xl font-black mt-2"><?php echo $productName; ?></h2>
            </div>

            <div class="col-span-12 md:col-span-3 dashboard-card flex justify-between items-center">
                <div>
                    <span class="text-slate-400 text-xs font-bold uppercase">Response Time</span>
                    <div class="mt-2">
                        <p class="text-slate-500 text-xs">Current Month</p>
                        <p class="text-xl font-bold text-blue-300">8:11 hr</p>
                    </div>
                </div>
                <div class="red-alert-box p-3 text-center">
                    <i class="fas fa-arrow-down text-red-500"></i>
                    <p class="text-red-500 font-bold"><?php echo $responseTimeChange; ?></p>
                </div>
            </div>

            <div class="col-span-12 md:col-span-3 dashboard-card flex justify-between items-center">
                <div>
                    <span class="text-slate-400 text-xs font-bold uppercase">CSAT Score</span>
                    <div class="mt-2">
                        <p class="text-slate-500 text-xs">Last: <?php echo $csatLastMonth; ?>%</p>
                        <p class="text-xl font-bold">Current: <?php echo $csatCurrent; ?>%</p>
                    </div>
                </div>
                <div class="red-alert-box w-20 h-20 flex items-center justify-center">
                    <span class="text-2xl font-bold text-white"><?php echo $csatCurrent; ?>%</span>
                </div>
            </div>

            <div class="col-span-12 md:col-span-3 dashboard-card space-y-4">
                <div class="flex justify-between items-center">
                    <i class="far fa-laugh-beam text-green-400 text-2xl"></i>
                    <span class="font-bold text-xl">67.76%</span>
                </div>
                <div class="flex justify-between items-center border-t border-slate-700 pt-2">
                    <i class="far fa-frown text-red-400 text-2xl"></i>
                    <span class="font-bold text-xl">11.81%</span>
                </div>
            </div>

            <div class="col-span-12 md:col-span-4 dashboard-card text-center">
                <p class="text-slate-400 text-xs font-bold uppercase mb-4">CSAT Gauge</p>
                <div id="gauge1"></div>
            </div>
            <div class="col-span-12 md:col-span-4 dashboard-card text-center">
                <p class="text-slate-400 text-xs font-bold uppercase mb-4">Customer Effort (CES)</p>
                <div id="gauge2"></div>
            </div>
            <div class="col-span-12 md:col-span-4 dashboard-card text-center">
                <p class="text-slate-400 text-xs font-bold uppercase mb-4">Net Promoter (NPS)</p>
                <div id="gauge3"></div>
            </div>

            <div class="col-span-12 md:col-span-6 dashboard-card">
                <p class="text-slate-400 text-xs font-bold uppercase mb-4">Response Time Trend</p>
                <div id="mainLineChart"></div>
            </div>
            <div class="col-span-12 md:col-span-6 dashboard-card">
                <p class="text-slate-400 text-xs font-bold uppercase mb-4">Satisfaction Breakdown</p>
                <div id="mainBarChart"></div>
            </div>

        </div>
    </div>

    <script>
        // รับข้อมูลจาก PHP เข้าสู่ JavaScript
        const chartData = <?php echo json_encode($lineChartData); ?>;
        const labels = <?php echo json_encode($months); ?>;

        // Configuration สำหรับ Gauge
        const commonGauge = {
            chart: { type: 'radialBar', height: 280, sparkline: { enabled: true } },
            plotOptions: {
                radialBar: {
                    startAngle: -90, endAngle: 90,
                    track: { background: "#334155" },
                    dataLabels: { name: { show: false }, value: { color: '#fff', fontSize: '24px', offsetY: -10 } }
                }
            },
            grid: { padding: { bottom: 20 } },
            colors: ['#38bdf8']
        };

        new ApexCharts(document.querySelector("#gauge1"), {...commonGauge, series: [65]}).render();
        new ApexCharts(document.querySelector("#gauge2"), {...commonGauge, series: [75.9], colors: ['#fbbf24']}).render();
        new ApexCharts(document.querySelector("#gauge3"), {...commonGauge, series: [59], colors: ['#22c55e']}).render();

        // Line Chart
        new ApexCharts(document.querySelector("#mainLineChart"), {
            chart: { type: 'area', height: 250, toolbar: {show:false}, background: 'transparent' },
            theme: { mode: 'dark' },
            stroke: { curve: 'smooth', width: 3 },
            series: [{ name: 'Hours', data: chartData }],
            xaxis: { categories: labels },
            colors: ['#38bdf8'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.5, opacityTo: 0 } }
        }).render();

        // Stacked Bar Chart
        new ApexCharts(document.querySelector("#mainBarChart"), {
            chart: { type: 'bar', height: 250, stacked: true, stackType: '100%', toolbar: {show:false} },
            theme: { mode: 'dark' },
            series: [
                { name: 'Satisfied', data: [44, 55, 41, 67] },
                { name: 'Neutral', data: [13, 23, 20, 8] },
                { name: 'Unsatisfied', data: [11, 17, 15, 15] }
            ],
            xaxis: { categories: ['Q1', 'Q2', 'Q3', 'Q4'] },
            colors: ['#0ea5e9', '#64748b', '#ef4444']
        }).render();
    </script>
</body>
</html>