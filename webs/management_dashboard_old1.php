<?php
ob_start();
session_start();
require_once __DIR__ . '/../../api/hr_db.php'; 
include 'menu.php'; 

// 1. FILTERS (รักษาฟีเจอร์การกรองข้อมูล)
$f_date = $_GET['f_date'] ?? date('Y-m-d');
$f_site = $_GET['f_site'] ?? 'ALL';

$site_res = $mysqli->query("SELECT site_code, site_name FROM master_sites WHERE is_active = 1 ORDER BY site_name ASC");
$all_sites = $site_res->fetch_all(MYSQLI_ASSOC);

// 2. FETCH DATA & AGGREGATE
$sql = "SELECT v.*, m.site_name 
        FROM vehicle_utilization v
        LEFT JOIN master_sites m ON v.site_code = m.site_code
        WHERE v.report_date = ?";
if ($f_site !== 'ALL') $sql .= " AND v.site_code = ?";
$sql .= " ORDER BY v.site_code ASC";

$stmt = $mysqli->prepare($sql);
if ($f_site !== 'ALL') $stmt->bind_param("ss", $f_date, $f_site);
else $stmt->bind_param("s", $f_date);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. CALCULATION LOGIC (New Ready Logic: in_use + assign + standby)
$totals = ['total' => 0, 'ready_y' => 0, 'ready_n' => 0, 'assigned' => 0, 'used' => 0, 'stb' => 0, 'pm' => 0, 'brk' => 0];

foreach ($rows as $r) {
    $in_use   = (int)$r['in_use'];
    $assign   = (int)($r['assign'] ?? 0);
    $standby  = (int)$r['standby'];
    
    // Ready (Yes) Logic
    $row_ready_y = $in_use + $assign + $standby;

    $totals['total']    += (int)$r['total_amount'];
    $totals['ready_y']  += $row_ready_y; 
    $totals['ready_n']  += (int)$r['availability_n'];
    $totals['used']     += $in_use;
    $totals['assigned'] += $assign; 
    $totals['stb']      += $standby;
    $totals['pm']       += (int)$r['pm_plan'];
    $totals['brk']      += (int)$r['breakdown'];
}

// Analytics Calculations
$div = max($totals['total'], 1);
$p_ready_y = round(($totals['ready_y'] / $div) * 100, 1);
$p_ready_n = round(($totals['ready_n'] / $div) * 100, 1);
$p_used    = round(($totals['used'] / $div) * 100, 1);
$p_asgn    = round(($totals['assigned'] / $div) * 100, 1);
$p_stb     = round(($totals['stb'] / $div) * 100, 1);
$p_pm      = round(($totals['pm'] / $div) * 100, 1);
$p_brk     = round(($totals['brk'] / $div) * 100, 1);

function getVehicleStyle($type) {
    $type = strtolower($type);
    if (strpos($type, 'truck') !== false || strpos($type, 'บรรทุก') !== false) return ['icon' => 'fa-truck', 'color' => 'text-sky-500', 'bg' => 'bg-sky-50'];
    if (strpos($type, 'pickup') !== false || strpos($type, 'กระบะ') !== false) return ['icon' => 'fa-truck-pickup', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50'];
    return ['icon' => 'fa-car-side', 'color' => 'text-slate-400', 'bg' => 'bg-slate-50'];
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Executive Insights - KBS Fleet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; padding-left: 16rem; }
        .kpi-card { border-top-width: 4px; transition: transform 0.2s; }
        @media print { body { padding-left: 0 !important; } .no-pdf { display: none !important; } }
    </style>
</head>
<body class="p-6">

    <main class="max-w-[1600px] mx-auto">
        <header class="mb-8 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-6 no-pdf">
            <div>
                <h1 class="text-3xl font-black text-slate-900 italic tracking-tighter uppercase">Fleet <span class="text-sky-600">Insights</span></h1>
                <p class="text-slate-500 font-bold uppercase text-[10px] tracking-[0.3em]">Operational Readiness Report</p>
            </div>
            <div class="flex items-center gap-4 bg-white p-2 rounded-2xl shadow-sm border border-slate-200">
                <select onchange="location.href='?f_date=<?= $f_date ?>&f_site='+this.value" class="bg-transparent font-bold text-slate-700 outline-none text-sm px-4 border-r">
                    <option value="ALL">🏢 All Business Units</option>
                    <?php foreach ($all_sites as $s): ?>
                        <option value="<?= h($s['site_code']) ?>" <?= ($f_site == $s['site_code']) ? 'selected' : '' ?>><?= h($s['site_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" value="<?= $f_date ?>" onchange="location.href='?f_site=<?= $f_site ?>&f_date='+this.value" class="bg-transparent font-bold text-slate-700 outline-none px-4 text-sm">
                <button onclick="window.print()" class="bg-slate-900 text-white w-10 h-10 rounded-xl flex items-center justify-center hover:bg-sky-600 shadow-lg"><i class="fas fa-print"></i></button>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-10">
            
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100 h-full flex flex-col items-center justify-center relative min-h-[380px]">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest absolute top-8 italic">Fleet Health</p>
                    <div class="flex flex-col items-center">
                        <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mb-4"><i class="fas fa-heart-pulse text-emerald-500 text-4xl animate-pulse"></i></div>
                        <span class="text-4xl font-black text-slate-900"><?= $p_ready_y ?>%</span>
                        <span class="text-[9px] font-black text-emerald-500 uppercase mt-2 italic">Ready for Ops</span>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100 h-full flex flex-col items-center justify-center relative min-h-[380px]">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest absolute top-8 italic">Total Units</p>
                    <div class="relative w-36 h-36">
                        <canvas id="statusDonut"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                            <i class="fas fa-truck text-slate-200 text-xl mb-1"></i>
                            <span class="text-2xl font-black text-slate-900 leading-none"><?= number_format($totals['total']) ?></span>
                            <span class="text-[7px] font-black text-slate-400 uppercase mt-0.5">Units</span>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-between w-full px-2 border-t pt-4 border-slate-50">
                        <div class="text-center flex-1 border-r"><span class="block text-[6px] font-black text-emerald-500 uppercase">Ready</span><span class="text-[10px] font-black text-slate-700"><?= $p_ready_y ?>%</span></div>
                        <div class="text-center flex-1"><span class="block text-[6px] font-black text-rose-400 uppercase">Not Ready</span><span class="text-[10px] font-black text-slate-700"><?= $p_ready_n ?>%</span></div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white p-5 rounded-[2.5rem] shadow-sm border border-slate-100 h-full flex flex-col items-center justify-center relative min-h-[380px]">
                    <p class="text-[9px] font-black text-emerald-600 uppercase tracking-widest absolute top-8 italic text-center leading-tight">Readiness<br>Analysis</p>
                    <div class="relative w-36 h-36">
                        <canvas id="readyBreakdownDonut"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                            <span class="text-xl font-black text-emerald-600 leading-none"><?= $p_ready_y ?>%</span>
                            <span class="text-[9px] font-black text-slate-500 mt-1">(<?= number_format($totals['ready_y']) ?>)</span>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col gap-1 w-full text-[8px] font-bold uppercase italic px-2">
                        <div class="flex justify-between text-sky-600"><span>Used:</span><span><?= $totals['used'] ?> (<?= $p_used ?>%)</span></div>
                        <div class="flex justify-between text-indigo-600"><span>Asgn:</span><span><?= $totals['assigned'] ?> (<?= $p_asgn ?>%)</span></div>
                        <div class="flex justify-between text-amber-600"><span>Stb:</span><span><?= $totals['stb'] ?> (<?= $p_stb ?>%)</span></div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white p-5 rounded-[2.5rem] shadow-sm border border-slate-100 h-full flex flex-col items-center justify-center relative min-h-[380px]">
                    <p class="text-[9px] font-black text-rose-600 uppercase tracking-widest absolute top-8 italic text-center leading-tight">Maintenance<br>Analysis</p>
                    <div class="relative w-36 h-36">
                        <canvas id="maintenanceDonut"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                            <span class="text-xl font-black text-rose-600 leading-none"><?= $p_ready_n ?>%</span>
                            <span class="text-[9px] font-black text-slate-500 mt-1">(<?= number_format($totals['ready_n']) ?>)</span>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col gap-1 w-full text-[8px] font-bold uppercase italic px-2">
                        <div class="flex justify-between text-amber-500"><span>PM Plan:</span><span><?= $totals['pm'] ?> (<?= $p_pm ?>%)</span></div>
                        <div class="flex justify-between text-rose-700"><span>Breakdn:</span><span><?= $totals['brk'] ?> (<?= $p_brk ?>%)</span></div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 grid grid-rows-2 gap-3">
                <div class="grid grid-cols-3 gap-3 italic">
                    <div class="bg-white p-4 rounded-3xl shadow-sm border-t-4 border-emerald-500 kpi-card">
                        <p class="text-[8px] font-black text-emerald-500 uppercase">Ready (Y)</p>
                        <div class="text-2xl font-black text-emerald-600"><?= number_format($totals['ready_y']) ?></div>
                    </div>
                    <div class="bg-white p-4 rounded-3xl shadow-sm border-t-4 border-rose-400 kpi-card">
                        <p class="text-[8px] font-black text-rose-400 uppercase">Ready (N)</p>
                        <div class="text-2xl font-black text-rose-500"><?= number_format($totals['ready_n']) ?></div>
                    </div>
                    <div class="bg-white p-4 rounded-3xl shadow-sm border-t-4 border-indigo-500 kpi-card">
                        <p class="text-[8px] font-black text-indigo-500 uppercase">Assigned</p>
                        <div class="text-2xl font-black text-indigo-600"><?= number_format($totals['assigned']) ?></div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3 italic">
                    <div class="bg-white p-4 rounded-3xl shadow-sm border-t-4 border-sky-500 kpi-card">
                        <p class="text-[8px] font-black text-sky-500 uppercase">Used</p>
                        <div class="text-2xl font-black text-sky-600"><?= number_format($totals['used']) ?></div>
                    </div>
                    <div class="bg-white p-4 rounded-3xl shadow-sm border-t-4 border-amber-500 kpi-card">
                        <p class="text-[8px] font-black text-amber-500 uppercase">Standby</p>
                        <div class="text-2xl font-black text-amber-600"><?= number_format($totals['stb']) ?></div>
                    </div>
                    <div class="bg-white p-4 rounded-3xl shadow-sm border-t-4 border-rose-700 kpi-card">
                        <p class="text-[8px] font-black text-rose-700 uppercase">BRK</p>
                        <div class="text-2xl font-black text-rose-800"><?= number_format($totals['brk']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php 
        $currentSite = '';
        foreach ($rows as $r): 
            $style = getVehicleStyle($r['vehicle_type']);
            if ($currentSite !== $r['site_code']): 
                $currentSite = $r['site_code'];
        ?>
            <div class="flex items-center gap-4 mb-6 mt-12 italic border-b pb-2 no-pdf">
                <h2 class="text-xs font-black text-slate-400 uppercase tracking-[0.4em]">📍 <?= h($r['site_name'] ?: $r['site_code']) ?></h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <?php endif; ?>
            <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100 flex flex-col group hover:shadow-lg transition-all">
                <div class="flex justify-between items-start mb-6">
                    <div class="w-14 h-14 rounded-2xl <?= $style['bg'] ?> flex items-center justify-center shadow-inner group-hover:rotate-3 transition-transform">
                        <i class="fas <?= $style['icon'] ?> <?= $style['color'] ?> text-xl"></i>
                    </div>
                    <div class="text-right">
                        <h4 class="font-black text-slate-800 uppercase italic text-xs tracking-tighter"><?= h($r['vehicle_type']) ?></h4>
                        <p class="text-[7px] font-bold text-slate-400 mt-1 uppercase italic">Total: <?= $r['total_amount'] ?></p>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-emerald-50/50 p-2 rounded-xl text-center"><span class="block text-[6px] font-black text-emerald-400 uppercase italic">Ready</span><span class="font-black text-emerald-600 text-lg"><?= (int)$r['in_use'] + (int)($r['assign'] ?? 0) + (int)$r['standby'] ?></span></div>
                    <div class="bg-sky-50/50 p-2 rounded-xl text-center"><span class="block text-[6px] font-black text-sky-400 uppercase italic">Used</span><span class="font-black text-sky-600 text-lg"><?= $r['in_use'] ?></span></div>
                    <div class="bg-rose-50/50 p-2 rounded-xl text-center"><span class="block text-[6px] font-black text-rose-400 uppercase italic">BRK</span><span class="font-black text-rose-600 text-lg"><?= $r['breakdown'] ?></span></div>
                </div>
            </div>
        <?php 
            $nextRow = next($rows);
            if (!$nextRow || $nextRow['site_code'] !== $currentSite) echo '</div>';
            prev($rows); 
        endforeach; 
        ?>
    </main>

    <script>
        // JS Charts Configuration (All Features Preserved)
        const commonOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };

        // Donut 1: Status Donut
        new Chart(document.getElementById('statusDonut'), {
            type: 'doughnut', data: {
                labels: ['Ready (Y)', 'Ready (N)'],
                datasets: [{ data: [<?= $totals['ready_y'] ?>, <?= $totals['ready_n'] ?>], backgroundColor: ['#10b981', '#fb7185'], borderWidth: 0, cutout: '80%' }]
            }, options: commonOptions
        });

        // Donut 2: Readiness Breakdown
        new Chart(document.getElementById('readyBreakdownDonut'), {
            type: 'doughnut', data: {
                labels: ['Used', 'Assigned', 'Standby', 'Not Ready'],
                datasets: [{ data: [<?= $totals['used'] ?>, <?= $totals['assigned'] ?>, <?= $totals['stb'] ?>, <?= $totals['ready_n'] ?>], backgroundColor: ['#0ea5e9', '#6366f1', '#f59e0b', '#f1f5f9'], borderWidth: 0, cutout: '80%' }]
            }, options: commonOptions
        });

        // Donut 3: Maintenance Breakdown (With White Filler)
        new Chart(document.getElementById('maintenanceDonut'), {
            type: 'doughnut', data: {
                labels: ['PM Plan', 'Breakdown', 'Ready Fleet'],
                datasets: [{ data: [<?= $totals['pm'] ?>, <?= $totals['brk'] ?>, <?= $totals['total'] - ($totals['pm'] + $totals['brk']) ?>], backgroundColor: ['#f59e0b', '#be123c', '#ffffff'], borderWidth: 0, cutout: '80%' }]
            }, options: commonOptions
        });
    </script>
</body>
</html>
