<?php
ob_start();
session_start();
require_once __DIR__ . '/../../api/hr_db.php'; 
include 'menu.php'; 
// 6-Feb-2026: Refactor Management Dashboard - Executive Insights
// 1. AUTO FETCH LAST DATE
$date_query = $mysqli->query("SELECT MAX(report_date) as last_date FROM vehicle_utilization");
$date_row = $date_query->fetch_assoc();
$f_date = $date_row['last_date'] ?? date('Y-m-d');

// 2. SITE LISTING (2 Sites)
$site_res = $mysqli->query("SELECT site_code, site_name FROM master_sites WHERE is_active = 1 ORDER BY site_name ASC");
$all_sites = ($site_res) ? $site_res->fetch_all(MYSQLI_ASSOC) : [];
$f_site = $_GET['f_site'] ?? ($all_sites[0]['site_code'] ?? '');

$current_site_name = "Unknown Site";
foreach ($all_sites as $s) {
    if ($s['site_code'] == $f_site) { $current_site_name = $s['site_name']; break; }
}

// 3. FETCH DATA (Mapping คอลัมน์ assign_reuire ให้ตรงตามไฟล์จริง)
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
    if (strpos($type, 'truck') !== false) return ['icon' => 'fa-truck', 'color' => 'text-sky-500', 'bg' => 'bg-sky-50'];
    if (strpos($type, 'pickup') !== false) return ['icon' => 'fa-truck-pickup', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50'];
    return ['icon' => 'fa-car-side', 'color' => 'text-slate-400', 'bg' => 'bg-slate-50'];
}
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
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; transition: all 0.3s ease; cursor: pointer; }
        .flat-card { border: 1px solid #e2e8f0; border-radius: 2.5rem; background: #fff; transition: all 0.3s ease; }
        .header-container { padding-left: 4.5rem; }
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
        
        <div class="border-l-2 border-slate-200 pl-4">
            <h1 class="text-xl font-extrabold text-slate-800 uppercase italic leading-none">
                KBS <span class="text-sky-600">Fleet Dashboard</span>
            </h1>
            <div class="mt-2 flex flex-col gap-1">
                <p class="text-slate-400 font-bold uppercase text-[10px] tracking-[0.2em] leading-none">
                    ข้อมูลล่าสุด: <span class="text-sky-600"><?= date('d M Y | H:i:s') ?></span>
                </p>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm">
        <select onchange="location.href='?f_site='+this.value" class="bg-transparent font-black text-slate-700 outline-none text-xs px-6 cursor-pointer">
            <?php foreach ($all_sites as $s): ?>
                <option value="<?= h($s['site_code']) ?>" <?= ($f_site == $s['site_code']) ? 'selected' : '' ?>><?= h($s['site_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button onclick="window.print()" class="bg-slate-900 text-white w-10 h-10 rounded-xl flex items-center justify-center hover:bg-sky-600 transition-colors shadow-sm">
            <i class="fas fa-print"></i>
        </button>
    </div>
</header>


        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-4 mb-10 text-center">
    
    <div class="lg:col-span-3">
        <div class="flat-card p-6 h-full flex flex-col items-center justify-center relative min-h-[350px]">
            <p class="text-[16px] font-black text-slate-400 uppercase absolute top-8 italic">% Health</p>
            <div class="relative w-32 h-32">
                <canvas id="healthDonut"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-black text-slate-900 leading-none"><?= number_format($totals['total']) ?> คัน</span>
                    <span class="text-lg font-black text-sky-600"><?= $p_ready_y ?>%</span>
                </div>
            </div>
            <div class="mt-6 w-full text-[14px] font-black italic space-y-2">
            <div class="flex justify-between text-green-800">
    <span>พร้อมใช้งาน:</span>
    <span><?= $p_ready_y ?>%</span>
</div>
                <div class="flex justify-between text-rose-600">
                    <span>ไม่พร้อมใช้งาน:</span>
                    <span><?= $p_ready_n ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="flat-card p-6 h-full flex flex-col items-center justify-center relative min-h-[350px]">
            <p class="text-[16px] font-black text-emerald-600 uppercase absolute top-8 italic">พร้อมใช้งาน</p>
            <div class="relative w-28 h-28">
                <canvas id="readyBreakdownDonut"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-black text-emerald-600 leading-none"><?= number_format($totals['ready_y']) ?></span>
                </div>
            </div>
            <div class="mt-6 w-full text-[14px] font-black italic space-y-2 uppercase">
                <div class="flex justify-between text-sky-600"><span>กำลังใช้งาน:</span><span><?= number_format($totals['used']) ?> (<?= $p_used ?>%)</span></div>
                <div class="flex justify-between text-indigo-600"><span>รอมอบหมาย:</span><span><?= number_format($totals['assigned']) ?> (<?= $p_asgn ?>%)</span></div>
                <div class="flex justify-between text-amber-600"><span>รอใช้งาน:</span><span><?= number_format($totals['stb']) ?> (<?= $p_stb ?>%)</span></div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="flat-card p-6 h-full flex flex-col items-center justify-center relative min-h-[350px]">
            <p class="text-[16px] font-black text-rose-600 uppercase absolute top-8 italic">ไม่พร้อมใช้งาน</p>
            <div class="relative w-28 h-28">
                <canvas id="maintenanceDonut"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-black text-rose-600 leading-none"><?= number_format($totals['ready_n']) ?></span>
                </div>
            </div>
            <div class="mt-6 w-full text-[14px] font-black italic space-y-2 uppercase">
                <div class="flex justify-between text-amber-600"><span>ซ่อมบำรุง:</span><span><?= number_format($totals['pm']) ?></span></div>
                <div class="flex justify-between text-rose-700"><span>เสีย:</span><span><?= number_format($totals['brk']) ?></span></div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-5 grid grid-rows-2 gap-3 text-center font-black italic">
        <div class="grid grid-cols-3 gap-3">
        <div class="flat-card border-t-4 border-t-green-800 p-4 flex flex-col justify-center items-center">
    <span class="text-[14px] text-green-800 uppercase leading-none font-black">พร้อมใช้งาน(คัน)</span>
    <div class="text-2xl text-green-900 mt-1 font-black italic">
        <?= number_format($totals['ready_y']) ?>
    </div>
</div>
            <div class="flat-card border-t-4 border-t-rose-400 p-4 flex flex-col justify-center items-center">
                <span class="text-[14px] text-rose-400 uppercase leading-none">ไม่พร้อมใช้งาน(คัน)</span>
                <div class="text-2xl text-rose-500 mt-1"><?= number_format($totals['ready_n']) ?></div>
            </div>
            <div class="flat-card border-t-4 border-t-indigo-500 p-4 flex flex-col justify-center items-center">
                <span class="text-[14px] text-indigo-500 uppercase leading-none">รอมอบหมาย(คัน)</span>
                <div class="text-2xl text-indigo-600 mt-1"><?= number_format($totals['assigned']) ?></div>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div class="flat-card border-t-4 border-t-sky-500 p-4 flex flex-col justify-center items-center">
                <span class="text-[14px] text-sky-500 uppercase leading-none">ถูกใช้งาน(คัน)</span>
                <div class="text-2xl text-sky-600 mt-1"><?= number_format($totals['used']) ?></div>
            </div>
            <div class="flat-card border-t-4 border-t-amber-500 p-4 flex flex-col justify-center items-center">
                <span class="text-[14px] text-amber-500 uppercase leading-none">รอใช้งาน(คัน)</span>
                <div class="text-2xl text-amber-600 mt-1"><?= number_format($totals['stb']) ?></div>
            </div>
            <div class="flat-card border-t-4 border-t-rose-700 p-4 flex flex-col justify-center items-center">
                <span class="text-[14px] text-rose-700 uppercase leading-none">เสีย(คัน)</span>
                <div class="text-2xl text-rose-800 mt-1"><?= number_format($totals['brk']) ?></div>
            </div>
        </div>
    </div>
</div>

        <div class="flex items-center gap-4 mb-6 mt-12 italic border-b pb-2 no-pdf">
            <h2 class="text-xs font-blue text-slate-400 uppercase tracking-[0.4em] leading-none">📍 Fleet List: <?= h($current_site_name) ?></h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-10">

        <?php foreach ($rows as $r): $style = getVehicleStyle($r['vehicle_type']); ?>
    <div class="flat-card p-5 flex flex-col items-center text-center group transition-colors hover:border-sky-300">
        <div class="w-14 h-14 rounded-2xl <?= $style['bg'] ?> flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
            <i class="fas <?= $style['icon'] ?> <?= $style['color'] ?> text-2xl"></i>
        </div>
        <h4 class="font-black text-slate-800 uppercase italic text-[11px] leading-tight mb-4 tracking-tighter"><?= h($r['vehicle_type']) ?></h4>
        
        <div class="w-full space-y-4">
            <div class="space-y-1">
                <p class="text-[12px] font-black text-green-800 uppercase italic leading-none">พร้อมใช้งาน</p>
                <div class="grid grid-cols-3 gap-1 font-black italic">
                    <div class="bg-sky-100 p-2 rounded-lg text-sky-800 text-[13px]" title="Used">
                        <span class="block text-[8px] uppercase">กำลังใช้งาน</span>
                        <?= (int)$r['in_use'] ?>
                    </div>
                    <div class="bg-indigo-100 p-2 rounded-lg text-indigo-800 text-[13px]" title="Assign">
                        <span class="block text-[8px] uppercase">รอมอบหมาย</span>
                        <?= (int)($r['assign_reuire'] ?? 0) ?>
                    </div>
                    <div class="bg-amber-100 p-2 rounded-lg text-amber-800 text-[13px]" title="Standby">
                        <span class="block text-[8px] uppercase">รอใช้งาน</span>
                        <?= (int)$r['standby'] ?>
                    </div>
                </div>
            </div>

            <div class="space-y-1 border-t pt-2 border-slate-100">
                <p class="text-[12px] font-black text-rose-700 uppercase italic leading-none">ไม่พร้อมใช้งาน</p>
                <div class="grid grid-cols-2 gap-1 font-black italic">
                    <div class="bg-orange-100 p-2 rounded-lg text-orange-800 text-[13px]" title="PM Plan">
                        <span class="block text-[8px] uppercase">ซ่อมบำรุง</span>
                        <?= (int)$r['pm_plan'] ?>
                    </div>
                    <div class="bg-rose-100 p-2 rounded-lg text-rose-800 text-[13px]" title="Breakdown">
                        <span class="block text-[8px] uppercase">เสีย</span>
                        <?= (int)$r['breakdown'] ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
    </main>

    <script>
        const cfg = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };

        // Donut 1: Fleet Health %
        new Chart(document.getElementById('healthDonut'), {
            type: 'doughnut', data: {
                labels: ['Ready (Y)', 'Ready (N)'],
                datasets: [{ data: [<?= $totals['ready_y'] ?>, <?= $totals['ready_n'] ?>], backgroundColor: ['#026242', '#fb7185'], borderWidth: 0, cutout: '80%' }]
            }, options: cfg
        });

        // Donut 2: Status Overview (Total Counts)
        new Chart(document.getElementById('statusDonut'), {
            type: 'doughnut', data: {
                labels: ['Ready (Y)', 'Ready (N)'],
                datasets: [{ data: [<?= $totals['ready_y'] ?>, <?= $totals['ready_n'] ?>], backgroundColor: ['#10b981', '#fb7185'], borderWidth: 0, cutout: '80%' }]
            }, options: cfg
        });

        // Donut 3: Readiness Breakdown
        new Chart(document.getElementById('readyBreakdownDonut'), {
            type: 'doughnut', data: {
                labels: ['Used', 'Assigned', 'Standby', 'Not Ready'],
                datasets: [{ data: [<?= $totals['used'] ?>, <?= $totals['assigned'] ?>, <?= $totals['stb'] ?>, <?= $totals['ready_n'] ?>], backgroundColor: ['#0ea5e9', '#6366f1', '#f59e0b', '#f1f5f9'], borderWidth: 0, cutout: '80%' }]
            }, options: cfg
        });

        // Donut 4: Maintenance Breakdown
        new Chart(document.getElementById('maintenanceDonut'), {
            type: 'doughnut', data: {
                labels: ['PM Plan', 'Breakdown'],
                datasets: [{ data: [<?= $totals['pm'] ?>, <?= $totals['brk'] ?>], backgroundColor: ['#f59e0b', '#be123c'], borderWidth: 0, cutout: '80%' }]
            }, options: cfg
        });

        // TAB TO TOGGLE SIDEMENU
        function handleBodyClick(e) {
            const ignoredTags = ['BUTTON', 'A', 'SELECT', 'INPUT', 'I', 'CANVAS', 'OPTION'];
            if (!ignoredTags.includes(e.target.tagName)) {
                if (typeof toggleSidebar === "function") toggleSidebar();
            }
        }
    </script>
</body>
</html>
