<?php
/**
 * ส่วนการเชื่อมต่อฐานข้อมูล (ตัวอย่าง)
 */
// $conn = new mysqli("localhost", "root", "", "your_db_name");
// if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// จำลองข้อมูลจาก Database
$currentSales = 488008011;
$budgetSales = 491063233;
$variance = -0.62;
$pastYearSales = 331631509;
$growth = 47.15;

// ข้อมูลสำหรับกราฟ
$months = ['Jan', 'Mar', 'May', 'Jul', 'Sep', 'Nov'];
$actualData = [4, 3, 5, 4, 8, 5];
$budgetData = [3, 5, 3, 4, 7, 2];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <a href="https://www.kbs.co.th/" target="_blank" class="transition-transform hover:scale-105">
                <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="KBS Logo" class="h-12 w-auto object-contain">
            </a>
    <title>Comparative Analysis Dashboard</title>
    <link rel="icon" type="image/webp" href="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0047ff; } /* พื้นหลังสีน้ำเงินตามภาพ */
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .stat-card { border-radius: 6px; padding: 15px; color: white; text-align: center; }
    </style>
</head>
<body class="p-6">

    <h1 class="text-2xl font-semibold text-white mb-6">KBS-Comparative Analysis Dashboard Slide Template</h1>

    <div class="grid grid-cols-5 gap-4 mb-6">
        <div class="stat-card bg-blue-600">
            <h3 class="text-lg font-bold">$<?php echo number_format($currentSales); ?></h3>
            <p class="text-xs opacity-80 uppercase">Current Year Sales</p>
        </div>
        <div class="stat-card bg-teal-500">
            <h3 class="text-lg font-bold">$<?php echo number_format($budgetSales); ?></h3>
            <p class="text-xs opacity-80 uppercase">Budget Sales</p>
        </div>
        <div class="stat-card bg-orange-400">
            <h3 class="text-lg font-bold"><?php echo $variance; ?>%</h3>
            <p class="text-xs opacity-80 uppercase">Budget Variance %</p>
        </div>
        <div class="stat-card bg-green-500">
            <h3 class="text-lg font-bold">$<?php echo number_format($pastYearSales); ?></h3>
            <p class="text-xs opacity-80 uppercase">Past Year Sales</p>
        </div>
        <div class="stat-card bg-emerald-400">
            <h3 class="text-lg font-bold"><?php echo $growth; ?>%</h3>
            <p class="text-xs opacity-80 uppercase">Sales Growth</p>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6 mb-6">
        <div class="col-span-5 card">
            <h4 class="font-bold text-gray-700 mb-4">Actual vs Budget Sales</h4>
            <div id="lineChart"></div>
        </div>
        <div class="col-span-5 card">
            <h4 class="font-bold text-gray-700 mb-4">Current Year vs Past Year Sales</h4>
            <div id="barChartYear"></div>
        </div>
        <div class="col-span-2 card">
            <h4 class="font-bold text-gray-700 mb-4">Year</h4>
            <div class="grid grid-cols-2 gap-2">
                <?php 
                $years = [2023, 2024, 2025, 2026, 2027, 2029];
                foreach($years as $y) {
                    $activeClass = ($y == 2024) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500';
                    echo "<button class='py-2 rounded text-sm font-semibold $activeClass'>$y</button>";
                }
                ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-9 card">
            <h4 class="font-bold text-gray-700 mb-4">Budget vs Actual Sales (By Product)</h4>
            <div id="barChartProduct"></div>
        </div>
        <div class="col-span-3 card flex flex-col items-center justify-center">
            <h4 class="font-bold text-gray-700 mb-4 self-start">Budget</h4>
            <div id="donutChart"></div>
        </div>
    </div>

    <script>
        // 1. Line Chart: Actual vs Budget
        new ApexCharts(document.querySelector("#lineChart"), {
            chart: { type: 'line', height: 250, toolbar: {show:false} },
            stroke: { curve: 'smooth', width: [3, 3] },
            series: [
                { name: 'Actual', data: <?php echo json_encode($actualData); ?> },
                { name: 'Budget', data: <?php echo json_encode($budgetData); ?> }
            ],
            xaxis: { categories: <?php echo json_encode($months); ?> },
            colors: ['#4f46e5', '#10b981']
        }).render();

        // 2. Bar Chart: Current vs Past
        new ApexCharts(document.querySelector("#barChartYear"), {
            chart: { type: 'bar', height: 250, toolbar: {show:false} },
            series: [
                { name: 'Current', data: [35, 42, 48, 45] },
                { name: 'Past', data: [23, 18, 28, 25] }
            ],
            xaxis: { categories: ['2022', '2023', '2024', '2025'] },
            colors: ['#4f46e5', '#06b6d4']
        }).render();

        // 3. Bar Chart: Product Analysis
        new ApexCharts(document.querySelector("#barChartProduct"), {
            chart: { type: 'bar', height: 250, toolbar: {show:false} },
            plotOptions: { bar: { columnWidth: '45%' } },
            series: [
                { name: 'Budget', data: [30, 40, 45, 44, 38, 20] },
                { name: 'Actual', data: [20, 15, 25, 24, 18, 10] }
            ],
            xaxis: { categories: ['Product 1', 'Product 2', 'Product 3', 'Product 4', 'Product 5', 'Product 6'] },
            colors: ['#4f46e5', '#06b6d4']
        }).render();

        // 4. Donut Chart
        new ApexCharts(document.querySelector("#donutChart"), {
            chart: { type: 'donut', height: 250 },
            series: [35, 25, 40],
            labels: ['Used', 'Remaining', 'Overflow'],
            colors: ['#3b82f6', '#10b981', '#fbbf24'],
            plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Budget', formatter: () => '35%' } } } } },
            legend: { show: false }
        }).render();
    </script>
</body>
</html>