<?php
// ข้อมูลจำลองสำหรับ Financial Dashboard
$sales = 229669;
$gross_profit = 109669;
$revenue_growth = 25;

// ข้อมูลสำหรับ Progress Charts (Donut)
$yearly_growth = [
    2022 => 21,
    2023 => 31,
    2024 => 46
];

// ข้อมูลสำหรับ Bar Charts ด้านบน
$metrics = [
    ['label' => 'Revenue', 'value' => '50%', 'color' => 'bg-blue-500'],
    ['label' => 'EBITDA', 'value' => '60%', 'color' => 'bg-blue-400'],
    ['label' => 'Free Cash Flow', 'value' => '45%', 'color' => 'bg-blue-300'],
    ['label' => 'Net Profit', 'value' => '35%', 'color' => 'bg-blue-200']
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Summary Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; }
        .sidebar { background: linear-gradient(180deg, #4fd1c5 0%, #38b2ac 100%); }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .progress-circle { width: 100px; height: 100px; }
    </style>
</head>
<body class="flex min-h-screen">

    <div class="w-1/4 sidebar p-8 text-white">
        <h1 class="text-3xl font-bold mb-4 uppercase tracking-wider">Financial Summary</h1>
        <p class="text-sm opacity-90 mb-12">ข้อมูลสรุปผลการดำเนินงานทางการเงินและเป้าหมายความสำเร็จขององค์กร</p>

        <div class="bg-blue-600/30 p-6 rounded-lg mb-6 border border-white/20">
            <h3 class="text-xs font-bold uppercase opacity-80">Sales</h3>
            <p class="text-3xl font-bold">$<?php echo number_format($sales); ?></p>
            <div class="flex justify-between mt-4 text-sm">
                <span>Target Achievements</span>
                <span class="font-bold">50%</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Change Over Last Year</span>
                <span class="font-bold text-green-300">▲ 3%</span>
            </div>
        </div>

        <div class="bg-white/10 p-6 rounded-lg border border-white/20">
            <h3 class="text-xs font-bold uppercase opacity-80">Gross Profit</h3>
            <p class="text-3xl font-bold">$<?php echo number_format($gross_profit); ?></p>
            <div class="flex justify-between mt-4 text-sm">
                <span>Target Achievements</span>
                <span class="font-bold">75%</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Change Over Last Year</span>
                <span class="font-bold text-green-300">▲ 6%</span>
            </div>
        </div>
    </div>

    <div class="w-3/4 p-8">
        
        <div class="grid grid-cols-4 gap-4 mb-8">
            <?php foreach($metrics as $item): ?>
            <div class="card p-6 flex flex-col items-center">
                <div class="flex items-center justify-between w-full mb-4">
                    <span class="text-2xl font-bold"><?php echo $item['value']; ?></span>
                    <div class="w-6 h-6 bg-green-400 rounded-full flex items-center justify-center text-white text-xs">↗</div>
                </div>
                <div class="w-full bg-gray-100 h-24 rounded flex items-end overflow-hidden mb-4">
                    <div class="<?php echo $item['color']; ?> w-1/2 h-full opacity-30"></div>
                    <div class="<?php echo $item['color']; ?> w-1/2 h-2/3"></div>
                </div>
                <h4 class="font-bold text-gray-700"><?php echo $item['label']; ?></h4>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-3 gap-8">
            <div class="col-span-2 card bg-gradient-to-r from-blue-600 to-blue-500 p-8 text-white text-center">
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <?php foreach($yearly_growth as $year => $percent): ?>
                    <div class="flex flex-col items-center">
                        <div id="chart-<?php echo $year; ?>"></div>
                        <span class="text-2xl font-bold mt-2"><?php echo $percent; ?>%</span>
                        <span class="text-sm opacity-80"><?php echo $year; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t border-white/20 pt-4">
                    <h3 class="text-xl font-bold tracking-wide">Revenue Growth (YoY <?php echo $revenue_growth; ?>%)</h3>
                </div>
            </div>

            <div class="col-span-1 space-y-4">
                <div class="card p-4 flex justify-between items-center">
                    <span class="text-3xl font-bold text-blue-500">31%</span>
                    <span class="text-xs text-right text-gray-500 font-bold uppercase">Net Profit<br>Margin</span>
                </div>
                <div class="card p-4 flex justify-between items-center">
                    <span class="text-3xl font-bold text-blue-500">42%</span>
                    <span class="text-xs text-right text-gray-500 font-bold uppercase">Debt-to-<br>Equity Ratio</span>
                </div>
                <div class="card p-4 flex justify-between items-center">
                    <span class="text-3xl font-bold text-blue-500">55%</span>
                    <span class="text-xs text-right text-gray-500 font-bold uppercase">Return on<br>Equity (ROE)</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        const commonOptions = {
            chart: { type: 'radialBar', height: 150, sparkline: { enabled: true } },
            plotOptions: {
                radialBar: {
                    hollow: { size: '60%' },
                    track: { background: 'rgba(255,255,255,0.2)' },
                    dataLabels: {
                        name: { show: true, color: '#fff', offsetY: 20 },
                        value: { show: false }
                    }
                }
            },
            colors: ['#4ade80'],
            stroke: { lineCap: 'round' }
        };

        // Render Yearly Charts
        new ApexCharts(document.querySelector("#chart-2022"), {...commonOptions, series: [21], labels: ['2022']}).render();
        new ApexCharts(document.querySelector("#chart-2023"), {...commonOptions, series: [31], labels: ['2023']}).render();
        new ApexCharts(document.querySelector("#chart-2024"), {...commonOptions, series: [46], labels: ['2024']}).render();
    </script>
</body>
</html>