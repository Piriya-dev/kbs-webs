<?php
ob_start();
session_start();
require_once __DIR__ . '/../../api/hr_db.php'; 
include 'menu.php'; 

// 1. AUTO FETCH LAST DATE
$date_query = $mysqli->query("SELECT MAX(report_date) as last_date FROM vehicle_utilization");
$date_row = $date_query->fetch_assoc();
$f_date = $date_row['last_date'] ?? date('Y-m-d');

// 2. SITE LISTING
$site_res = $mysqli->query("SELECT site_code, site_name FROM master_sites WHERE is_active = 1 ORDER BY site_name ASC");
$all_sites = ($site_res) ? $site_res->fetch_all(MYSQLI_ASSOC) : [];
$f_site = $_GET['f_site'] ?? ($all_sites[0]['site_code'] ?? '');

$current_site_name = "Unknown Site";
foreach ($all_sites as $s) {
    if ($s['site_code'] == $f_site) { $current_site_name = $s['site_name']; break; }
}

// 3. FETCH DATA
$sql = "SELECT * FROM vehicle_utilization WHERE report_date = ? AND site_code = ? ORDER BY vehicle_type ASC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ss", $f_date, $f_site);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. CALCULATION LOGIC
$totals = ['total' => 0, 'ready_y' => 0, 'ready_n' => 0, 'assigned' => 0, 'used' => 0, 'stb' => 0, 'pm' => 0, 'brk' => 0];
foreach ($rows as $r) {
    $in_use = (int)$r['in_use'];
    $assign = (int)($r['assign_reuire'] ?? 0); 
    $stb    = (int)$r['standby'];
    $row_ready_y = $in_use + $assign + $stb;

    $totals['total']    += (int)$r['total_amount'];
    $totals['ready_y']  += $row_ready_y; 
    $totals['ready_n']  += (int)$r['availability_n'];
    $totals['used']     += $in_use;
    $totals['assigned'] += $assign; 
    $totals['stb']      += $stb;
    $totals['pm']       += (int)$r['pm_plan'];
    $totals['brk']      += (int)$r['breakdown'];
}
$div = max($totals['total'], 1);
$p_ready_y = round(($totals['ready_y'] / $div) * 100, 1);
$p_ready_n = round(($totals['ready_n'] / $div) * 100, 1);
$p_used    = round(($totals['used'] / $div) * 100, 1);
$p_asgn    = round(($totals['assigned'] / $div) * 100, 1);
$p_stb     = round(($totals['stb'] / $div) * 100, 1);
$p_pm      = round(($totals['pm'] / $div) * 100, 1);
$p_brk     = round(($totals['brk'] / $div) * 100, 1);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getVehicleStyle($type) {
    $type = strtolower($type);
    if (strpos($type, 'truck') !== false) return ['icon' => 'fa-truck', 'color' => 'text-sky-400', 'bg' => 'bg-sky-500/10'];
    if (strpos($type, 'pickup') !== false) return ['icon' => 'fa-truck-pickup', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'];
    return ['icon' => 'fa-car-side', 'color' => 'text-slate-400', 'bg' => 'bg-slate-500/10'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/webp" href="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp">
    <title>Executive Insights - KBS Fleet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
    
    body { 
        font-family: 'Plus Jakarta Sans', sans-serif; 
        background-color: #0f172a; /* Deep Navy Background */
        color: #f1f5f9; 
        margin: 0; 
        transition: all 0.3s ease;
    }

    .flat-card { 
        border: 1px solid rgba(255, 255, 255, 0.1); 
        border-radius: 1.5rem; 
        background: rgba(30, 41, 59, 0.7); 
        backdrop-filter: blur(12px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .flat-card:hover {
        border-color: rgba(14, 165, 233, 0.6);
        transform: translateY(-4px);
    }

    .header-container { padding-left: 5rem; }
    
    select { background-color: #1e293b !important; color: #f1f5f9 !important; border: 1px solid #334155 !important; }
    
    @media print { .no-pdf { display: none !important; } .header-container { padding-left: 0; } }
</style>
</head>
<body class="p-6" onclick="handleBodyClick(event)">

    <main class="max-w-[1600px] mx-auto">

    <header class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6 no-pdf header-container">
        <div class="flex items-center gap-4">
            <a href="https://www.kbs.co.th/" target="_blank" class="transition-transform hover:scale-105">
                <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="KBS Logo" class="h-12 w-auto object-contain">
            </a>
            <div class="border-l-2 border-slate-700 pl-4">
                <h1 class="text-xl font-extrabold text-white uppercase italic leading-none">
                    KBS <span class="text-sky-500">Fleet Dashboard</span>
                </h1>
                <div class="mt-2 flex flex-col gap-1">
                    <p class="text-slate-500 font-bold uppercase text-[10px] tracking-[0.2em] leading-none">
                        ข้อมูลล่าสุด: <span class="text-sky-400"><?= date('d M Y | H:i:s') ?></span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 bg-slate-800 p-2 rounded-2xl border border-slate-700 shadow-sm">
            <select onchange="location.href='?f_site='+this.value" class="bg-transparent font-black text-white outline-none text-xs px-6 cursor-pointer">
                <?php foreach ($all_sites as $s): ?>
                    <option value="<?= h($s['site_code']) ?>" <?= ($f_site == $s['site_code']) ? 'selected' : '' ?>><?= h($s['site_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="window.print()" class="bg-slate-700 text-white w-10 h-10 rounded-xl flex items-center justify-center hover:bg-sky-600 transition-colors shadow-sm">
                <i class="fas fa-print"></i>
            </button>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-12">
        
        <div class="lg:col-span-7 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flat-card p-6 flex flex-col items-center justify-center relative min-h-[380px]">
                <p class="text-[14px] font-bold text-yellow-300 uppercase absolute top-8 italic tracking-widest">% Fleet Health</p>
                <div class="relative w-40 h-40 mt-4">
                    <canvas id="healthDonut"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                        <span class="text-lg font-bold text-white leading-none"><?= number_format($totals['total']) ?> คัน</span>
                        <span class="text-2xl font-black text-lime-400 mt-1"><?= $p_ready_y ?>%</span>
                    </div>
                </div>
                <div class="mt-8 w-full text-[13px] font-bold space-y-2 px-4">
                    <div class="flex justify-between text-lime-400 border-b border-white/5 pb-1"><span>พร้อมใช้งาน:</span><span><?= $p_ready_y ?>%</span></div>
                    <div class="flex justify-between text-red-400"><span>ไม่พร้อมใช้งาน:</span><span><?= $p_ready_n ?>%</span></div>
                </div>
            </div>

            <div class="flat-card p-6 flex flex-col items-center justify-center relative min-h-[380px]">
                <p class="text-[14px] font-bold text-emerald-300 uppercase absolute top-8 italic">พร้อมใช้งาน(คัน)</p>
                <div class="relative w-36 h-36 mt-4">
                    <canvas id="readyBreakdownDonut"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-3xl font-black text-emerald-400"><?= number_format($totals['ready_y']) ?></span>
                    </div>
                </div>
                <div class="mt-8 w-full text-[11px] font-bold uppercase space-y-2 px-2">
                <div class="flex justify-between text-sky-400">
    <span>กำลังใช้งาน:</span>
    <span><?= number_format($totals['used']) ?> คัน (<?= $p_used ?>%)</span>
</div>
<div class="flex justify-between text-indigo-400">
        <span>รอมอบหมาย:</span>
        <span><?= number_format($totals['assigned']) ?> คัน (<?= $p_asgn ?>%)</span>
    </div>

    <div class="flex justify-between text-amber-500">
        <span>รอใช้งาน:</span>
        <span><?= number_format($totals['stb']) ?> คัน (<?= $p_stb ?>%)</span>
    </div>
                </div>
            </div> 


            <div class="flat-card p-6 flex flex-col items-center justify-center relative min-h-[380px]">
                <p class="text-[14px] font-bold text-rose-300 uppercase absolute top-8 italic">ไม่พร้อมใช้งาน(คัน)</p>
                <div class="relative w-36 h-36 mt-4">
                    <canvas id="maintenanceDonut"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                        <span class="text-2xl font-black text-rose-400 leading-none"><?= number_format($totals['ready_n']) ?></span>
                    </div>
                </div>
                <div class="mt-8 w-full text-[11px] font-bold uppercase space-y-2 px-2">
                <div class="mt-8 w-full text-[11px] font-bold uppercase space-y-2 px-2">
    <div class="flex justify-between text-amber-500">
        <span>ซ่อมบำรุง:</span>
        <span><?= number_format($totals['pm']) ?> คัน (<?= $p_pm ?>%)</span>
    </div>

    <div class="flex justify-between text-rose-400">
        <span>เสีย:</span>
        <span><?= number_format($totals['brk']) ?> คัน (<?= $p_brk ?>%)</span>
    </div>
</div>
                </div>
            </div>
        </div>


        <div class="lg:col-span-5 flex flex-col gap-4">
    <div class="flat-card p-6 flex flex-col justify-between h-full bg-emerald-500/5 border-t-4 border-emerald-500">
        <div class="flex items-center gap-2 mb-4 border-b border-white/10 pb-2">
            <i class="fas fa-check-circle text-emerald-400"></i>
            <span class="text-[14px] text-emerald-400 font-black uppercase italic tracking-widest">สถานะพร้อมใช้งาน: <?= number_format($totals['ready_y']) ?> คัน</span>
        </div>
        
        <div class="space-y-4">
            <div class="flex justify-between items-end">
                <span class="text-[14px] text-sky-400 font-bold">กำลังใช้งาน</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-white italic leading-none"><?= number_format($totals['used']) ?></span>
                    <span class="text-[10px] text-slate-500 font-bold uppercase">คัน</span>
                </div>
            </div>

            <div class="flex justify-between items-end">
                <span class="text-[14px] text-indigo-400 font-bold">รอมอบหมาย</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-white italic leading-none"><?= number_format($totals['assigned']) ?></span>
                    <span class="text-[10px] text-slate-500 font-bold uppercase">คัน</span>
                </div>
            </div>

            <div class="flex justify-between items-end">
                <span class="text-[14px] text-amber-400 font-bold">รอใช้งาน</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-white italic leading-none"><?= number_format($totals['stb']) ?></span>
                    <span class="text-[10px] text-slate-500 font-bold uppercase">คัน</span>
                </div>
            </div>
        </div>
    </div>

    <div class="flat-card p-6 flex flex-col justify-between h-full bg-rose-900/10 border-t-4 border-rose-600">
        <div class="flex items-center gap-2 mb-4 border-b border-white/10 pb-2">
            <i class="fas fa-tools text-rose-500"></i>
            <span class="text-[14px] text-rose-500 font-black uppercase italic tracking-widest">สถานะไม่พร้อมใช้งาน: <?= number_format($totals['ready_n']) ?> คัน</span>
        </div>

        <div class="space-y-4">
            <div class="flex justify-between items-end">
                <span class="text-[14px] text-amber-500 font-bold">ซ่อมแซม (PM)</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-white italic leading-none"><?= number_format($totals['pm']) ?></span>
                    <span class="text-[10px] text-slate-500 font-bold uppercase">คัน</span>
                </div>
            </div>

            <div class="flex justify-between items-end">
                <span class="text-[14px] text-rose-500 font-bold">เสีย (Breakdown)</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-white italic leading-none"><?= number_format($totals['brk']) ?></span>
                    <span class="text-[10px] text-slate-500 font-bold uppercase">คัน</span>
                </div>
            </div>
        </div>
    </div>
</div>
       
    </div>

    <div class="flex items-center gap-4 mb-6 mt-16 italic border-b border-slate-700 pb-3 no-pdf">
        <h2 class="text-sm font-black text-slate-400 uppercase tracking-[0.4em] leading-none">📍 Fleet Detail: <?= h($current_site_name) ?></h2>
    </div>
    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6 mb-16">
        <?php foreach ($rows as $r): $style = getVehicleStyle($r['vehicle_type']); ?>
            <div class="flat-card p-6 flex flex-col items-center text-center group border border-slate-700">
                <div class="w-14 h-14 rounded-2xl bg-slate-800 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas <?= $style['icon'] ?> <?= $style['color'] ?> text-2xl"></i>
                </div>
                <h4 class="font-bold text-slate-200 uppercase italic text-[12px] leading-tight mb-5 tracking-tight"><?= h($r['vehicle_type']) ?></h4>
                
                <div class="w-full space-y-4">
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-emerald-400 uppercase italic leading-none">พร้อมใช้งาน</p>
                        <div class="grid grid-cols-3 gap-1.5 font-bold italic">
                            <div class="bg-slate-900/80 p-2 rounded-lg text-sky-400 text-[14px]"><span class="block text-[8px] text-slate-500 uppercase">ใช้งาน</span><?= (int)$r['in_use'] ?></div>
                            <div class="bg-slate-900/80 p-2 rounded-lg text-indigo-400 text-[14px]"><span class="block text-[8px] text-slate-500 uppercase">มอบหมาย</span><?= (int)($r['assign_reuire'] ?? 0) ?></div>
                            <div class="bg-slate-900/80 p-2 rounded-lg text-amber-400 text-[14px]"><span class="block text-[8px] text-slate-500 uppercase">รอใช้</span><?= (int)$r['standby'] ?></div>
                        </div>
                    </div>
                    <div class="space-y-1 border-t border-slate-800 pt-3">
                        <p class="text-[10px] font-bold text-rose-400 uppercase italic leading-none">ไม่พร้อมใช้งาน</p>
                        <div class="grid grid-cols-2 gap-1.5 font-bold italic">
                            <div class="bg-slate-900/80 p-2 rounded-lg text-orange-400 text-[14px]"><span class="block text-[8px] text-slate-500 uppercase">ซ่อม</span><?= (int)$r['pm_plan'] ?></div>
                            <div class="bg-slate-900/80 p-2 rounded-lg text-rose-500 text-[14px]"><span class="block text-[8px] text-slate-500 uppercase">เสีย</span><?= (int)$r['breakdown'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    </main>

    <script>
        const cfg = { responsive: true, maintainAspectRatio: false, cutout: '82%', plugins: { legend: { display: false } } };

        new Chart(document.getElementById('healthDonut'), {
            type: 'doughnut', data: {
                labels: ['Ready', 'Down'],
                datasets: [{ data: [<?= $totals['ready_y'] ?>, <?= $totals['ready_n'] ?>], backgroundColor: ['#10b981', '#f43f5e'], borderWidth: 0 }]
            }, options: cfg
        });

        new Chart(document.getElementById('readyBreakdownDonut'), {
            type: 'doughnut', data: {
                labels: ['Used', 'Asgn', 'Stb'],
                datasets: [{ data: [<?= $totals['used'] ?>, <?= $totals['assigned'] ?>, <?= $totals['stb'] ?>], backgroundColor: ['#0ea5e9', '#6366f1', '#fbbf24'], borderWidth: 0 }]
            }, options: cfg
        });

        new Chart(document.getElementById('maintenanceDonut'), {
            type: 'doughnut', data: {
                labels: ['PM', 'BRK'],
                datasets: [{ data: [<?= $totals['pm'] ?>, <?= $totals['brk'] ?>], backgroundColor: ['#f59e0b', '#be123c'], borderWidth: 0 }]
            }, options: cfg
        });

        function handleBodyClick(e) {
            const ignoredTags = ['BUTTON', 'A', 'SELECT', 'INPUT', 'I', 'CANVAS', 'OPTION'];
            if (!ignoredTags.includes(e.target.tagName)) {
                if (typeof toggleSidebar === "function") toggleSidebar();
            }
        }
    </script>
</body>
</html>