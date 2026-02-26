<?php
ob_start();
session_start();
require_once __DIR__ . '/../../api/hr_db.php'; 
// include 'menu.php'; 
date_default_timezone_set('Asia/Bangkok');

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

    /* --- 1. GLOBAL RESET & FULL-WIDTH FORCE --- */
    /* Since the sidebar is removed, we force 100% width globally */
    body { 
        font-family: 'Plus Jakarta Sans', sans-serif; 
        background-color: #0f172a; 
        color: #f1f5f9; 
        margin: 0; 
        padding: 0;
        overflow-x: hidden !important; 
        width: 100vw;
        max-width: 100vw;
    }

    /* Force the main container and wrappers to fill the screen */
    #wrapper, #content-wrapper, main {
        padding-left: 0 !important;
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        display: block !important;
    }

    /* Standardized Header Padding (No more 5rem gap) */
    .header-container {
        padding-left: 1.5rem !important;
        padding-right: 1.5rem !important;
        width: 100% !important;
        margin-left: 0 !important;
    }

    /* --- 2. DASHBOARD UI COMPONENTS --- */
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

    /* --- 3. AUTO-SWAP NOTIFICATION --- */
    #swap-notification {
        position: fixed;
        top: -100px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(12px);
        border: 2px solid rgba(14, 165, 233, 0.5);
        border-radius: 1rem;
        padding: 1rem 2rem;
        transition: top 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 15px;
    }
    #swap-notification.show { top: 30px; }

    /* Hide elements during print */
    @media print { 
        .no-pdf { display: none !important; } 
        .header-container { padding-left: 0 !important; } 
    }
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
                KBS <span class="text-sky-500">Fleet Dashboard V1.1</span>
            </h1>
            <div class="mt-2 flex flex-col gap-1">
                <p class="text-slate-500 font-bold uppercase text-[10px] tracking-[0.2em] leading-none">
                    ข้อมูลล่าสุด: <span class="text-sky-400"><?= date('d M Y | H:i:s') ?></span>
                </p>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 bg-slate-800 p-2 rounded-2xl border border-slate-700 shadow-sm">
        
        <div class="flex items-center gap-2 px-2 border-r border-white/10">
            <!-- <a href="index.php" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-slate-700 hover:text-white transition-all" title="Home">
                <i class="fas fa-home text-sm"></i>
            </a>
             -->
            <a href="/vehicle_import_csv" class="flex items-center gap-2 px-3 py-1.5 bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 rounded-xl transition-all border border-sky-500/20 group">
                <i class="fas fa-file-import text-xs group-hover:rotate-12 transition-transform"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider">Import CSV</span>
            </a>
        </div>

        <div class="flex items-center gap-2 px-3 border-r border-white/5">
            <div class="flex flex-col items-start mr-2">
                 <span id="screensaver-status-text" class="text-[9px] font-bold text-slate-500 uppercase italic whitespace-nowrap leading-none mb-1">Rotation Off</span>
                 <select id="wait-time-selector" onchange="resetScreensaver()" class="bg-transparent text-[10px] font-bold text-sky-400 outline-none cursor-pointer">
                     <option value="5">Wait 5 Min</option>
                     <option value="4">Wait 4 Min</option>
                     <option value="3">Wait 3 Min</option>
                     <option value="2">Wait 2Min</option>
                     <option value="1" selected>Wait 1 Min</option>
                 </select>
            </div>
            
            <label class="relative inline-flex items-center cursor-pointer scale-75">
                <input type="checkbox" id="screensaver-toggle" class="sr-only peer" checked onchange="toggleScreensaver()">
                <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
            </label>
        </div>

        <select onchange="location.href='?f_site='+this.value" class="bg-transparent font-black text-white outline-none text-xs px-2 cursor-pointer min-w-[100px]">
            <?php foreach ($all_sites as $s): ?>
                <option value="<?= h($s['site_code']) ?>" <?= ($f_site == $s['site_code']) ? 'selected' : '' ?>><?= h($s['site_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button onclick="window.print()" class="bg-slate-700 text-white w-10 h-10 rounded-xl flex items-center justify-center hover:bg-sky-600 transition-colors shadow-sm">
            <i class="fas fa-print"></i>
        </button>
    </div>
</header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 2xl:gap-10 mb-12 max-w-[2400px] mx-auto">
    
    <div class="lg:col-span-7 grid grid-cols-1 md:grid-cols-3 gap-4 2xl:gap-6">
        
        <div class="flat-card p-4 2xl:p-10 flex flex-col items-center justify-between min-h-[380px] 2xl:min-h-[550px] relative">
            <p class="text-[clamp(14px,1.2vw,22px)] font-bold text-yellow-300 uppercase absolute top-6 2xl:top-10 italic tracking-widest">% Fleet Health</p>
            
            <div class="relative w-[clamp(140px,15vw,280px)] h-[clamp(140px,15vw,280px)] mt-10">
                <canvas id="healthDonut"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                    <span class="text-xs 2xl:text-xl font-bold text-white leading-none"><?= number_format($totals['total']) ?> คัน</span>
                    <span class="text-2xl 4xl:text-6xl font-black text-lime-400 mt-1"><?= $p_ready_y ?>%</span>
                </div>
            </div>

            <div class="mt-6 w-full text-[clamp(14px,1vw,24px)] font-bold space-y-2 px-2">
                <div class="flex justify-between text-lime-400 border-b border-white/5 pb-1"><span>พร้อม:</span><span><?= $p_ready_y ?>%</span></div>
                <div class="flex justify-between text-red-400"><span>ไม่พร้อม:</span><span><?= $p_ready_n ?>%</span></div>
            </div>
        </div>

        <div class="flat-card p-4 2xl:p-10 flex flex-col items-center justify-between min-h-[380px] 2xl:min-h-[550px] relative">
            <p class="text-[clamp(15px,1.2vw,22px)] font-bold text-emerald-300 uppercase absolute top-6 2xl:top-10 italic">พร้อมใช้งาน(คัน)</p>
            
            <div class="relative w-[clamp(130px,14vw,250px)] h-[clamp(130px,14vw,250px)] mt-10">
                <canvas id="readyBreakdownDonut"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-4xl 2xl:text-7xl font-black text-emerald-400"><?= number_format($totals['ready_y']) ?></span>
                </div>
            </div>

            <div class="mt-6 w-full text-[clamp(13px,0.9vw,20px)] font-bold uppercase space-y-1 px-1">
                <div class="flex justify-between text-sky-400"><span>กำลังใช้งาน:</span><span><?= number_format($totals['used']) ?> (<?= $p_used ?>%)</span></div>
                <div class="flex justify-between text-indigo-400"><span>รอมอบหมาย:</span><span><?= number_format($totals['assigned']) ?> (<?= $p_asgn ?>%)</span></div>
                <div class="flex justify-between text-amber-500"><span>รอใช้งาน:</span><span><?= number_format($totals['stb']) ?> (<?= $p_stb ?>%)</span></div>
            </div>
        </div> 

        <div class="flat-card p-4 2xl:p-10 flex flex-col items-center justify-between min-h-[380px] 2xl:min-h-[550px] relative">
            <p class="text-[clamp(15px,1.2vw,22px)] font-bold text-rose-300 uppercase absolute top-6 2xl:top-10 italic">ไม่พร้อมใช้งาน(คัน)</p>
            
            <div class="relative w-[clamp(130px,14vw,250px)] h-[clamp(130px,14vw,250px)] mt-10">
                <canvas id="maintenanceDonut"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                    <span class="text-3xl 4xl:text-7xl font-black text-rose-500 leading-none"><?= number_format($totals['ready_n']) ?></span>
                </div>
            </div>

            <div class="mt-6 w-full text-[clamp(13px,0.9vw,20px)] font-bold uppercase space-y-1 px-1">
                <div class="flex justify-between text-amber-500"><span>ซ่อมบำรุง:</span><span><?= number_format($totals['pm']) ?> (<?= $p_pm ?>%)</span></div>
                <div class="flex justify-between text-rose-500"><span>เสีย:</span><span><?= number_format($totals['brk']) ?> (<?= $p_brk ?>%)</span></div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-5 flex flex-col gap-4 2xl:gap-8">
        <div class="flat-card p-6 2xl:p-12 flex-1 flex flex-col justify-between bg-emerald-500/5 border-t-4 border-emerald-500 shadow-2xl">
            <div class="flex items-center gap-2 mb-4 border-b border-white/10 pb-2">
                <i class="fas fa-check-circle text-emerald-400 text-xl 2xl:text-3xl"></i>
                <span class="text-[clamp(16px,1.3vw,30px)] text-emerald-400 font-black uppercase italic tracking-widest">พร้อมใช้งาน: <?= number_format($totals['ready_y']) ?> คัน</span>
            </div>
            <div class="space-y-4 2xl:space-y-10">
                <?php 
                $ready_items = [
                    ['label' => 'กำลังใช้งาน', 'val' => $totals['used'], 'color' => 'text-sky-400'],
                    ['label' => 'รอมอบหมาย', 'val' => $totals['assigned'], 'color' => 'text-indigo-400'],
                    ['label' => 'รอใช้งาน', 'val' => $totals['stb'], 'color' => 'text-amber-400']
                ];
                foreach ($ready_items as $item): ?>
                <div class="flex justify-between items-end">
                    <span class="text-[16px] 2xl:text-[28px] <?= $item['color'] ?> font-bold"><?= $item['label'] ?></span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl 2xl:text-7xl font-black text-white italic leading-none font-mono"><?= number_format($item['val']) ?></span>
                        <span class="text-[10px] 2xl:text-lg text-slate-500 font-bold uppercase">คัน</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flat-card p-6 2xl:p-12 flex-1 flex flex-col justify-between bg-rose-900/10 border-t-4 border-rose-600 shadow-2xl">
            <div class="flex items-center gap-2 mb-4 border-b border-white/10 pb-2">
                <i class="fas fa-tools text-rose-500 text-xl 2xl:text-3xl"></i>
                <span class="text-[clamp(16px,1.3vw,30px)] text-rose-500 font-black uppercase italic tracking-widest">ไม่พร้อมใช้งาน: <?= number_format($totals['ready_n']) ?> คัน</span>
            </div>
            <div class="space-y-4 2xl:space-y-10">
                <div class="flex justify-between items-end">
                    <span class="text-[16px] 2xl:text-[28px] text-amber-500 font-bold">ซ่อมแซม (PM)</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl 2xl:text-7xl font-black text-white italic leading-none font-mono"><?= number_format($totals['pm']) ?></span>
                        <span class="text-[10px] 2xl:text-lg text-slate-500 font-bold uppercase">คัน</span>
                    </div>
                </div>
                <div class="flex justify-between items-end">
                    <span class="text-[16px] 2xl:text-[28px] text-rose-500 font-bold">เสีย (Breakdown)</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl 2xl:text-7xl font-black text-white italic leading-none font-mono"><?= number_format($totals['brk']) ?></span>
                        <span class="text-[10px] 2xl:text-lg text-slate-500 font-bold uppercase">คัน</span>
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
                <h4 class="font-bold text-slate-200 uppercase italic text-[14px] leading-tight mb-5 tracking-tight"><?= h($r['vehicle_type']) ?></h4>
                
                <div class="w-full space-y-4">
                    <div class="space-y-1">
                        <p class="text-[16px] font-bold text-emerald-400 uppercase italic leading-none">พร้อมใช้งาน</p>
                        <div class="grid grid-cols-3 gap-1.5 font-bold italic">
                            <div class="bg-slate-900/80 p-2 rounded-lg text-sky-400 text-[18px]"><span class="block text-[8px] text-slate-500 uppercase">ใช้งาน</span><?= (int)$r['in_use'] ?></div>
                            <div class="bg-slate-900/80 p-2 rounded-lg text-indigo-400 text-[18px]"><span class="block text-[8px] text-slate-500 uppercase">มอบหมาย</span><?= (int)($r['assign_reuire'] ?? 0) ?></div>
                            <div class="bg-slate-900/80 p-2 rounded-lg text-amber-400 text-[18px]"><span class="block text-[8px] text-slate-500 uppercase">รอใช้</span><?= (int)$r['standby'] ?></div>
                        </div>
                    </div>
                    <div class="space-y-1 border-t border-slate-800 pt-3">
                        <p class="text-[16px] font-bold text-rose-400 uppercase italic leading-none">ไม่พร้อมใช้งาน</p>
                        <div class="grid grid-cols-2 gap-1.5 font-bold italic">
                            <div class="bg-slate-900/80 p-2 rounded-lg text-orange-400 text-[18px]"><span class="block text-[8px] text-slate-500 uppercase">ซ่อม</span><?= (int)$r['pm_plan'] ?></div>
                            <div class="bg-slate-900/80 p-2 rounded-lg text-rose-500 text-[18px]"><span class="block text-[8px] text-slate-500 uppercase">เสีย</span><?= (int)$r['breakdown'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    </main>
    <script>
    // --- NEW: FORCE HIDE SIDE MENU ON LOAD ---
    document.addEventListener("DOMContentLoaded", function() {
    let count = 0;
    // This ensures that even if KBSG loads slowly, we keep cleaning it
    const layoutGuard = setInterval(() => {
        bestAutoHide();
        count++;
        if (count > 20) clearInterval(layoutGuard); // Stop after 2 seconds
    }, 100);
});
    // --- Chart.js & Sidebar Logic (Preserved) ---
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

    // --- Auto-Swap Screensaver Logic ---
    const currentSite = "<?= h($f_site) ?>";
    const allSites = <?= json_encode($all_sites) ?>;
    
    let targetSiteObj = allSites.find(s => s.site_code !== currentSite) || allSites[0];
    const targetSiteCode = targetSiteObj.site_code;
    const targetSiteName = targetSiteObj.site_name;

    let inactivityTimer;
    let countdownInterval;
    
    const toggle = document.getElementById('screensaver-toggle');
    const waitSelector = document.getElementById('wait-time-selector');
    const statusText = document.getElementById('screensaver-status-text');
    const notification = document.getElementById('swap-notification');
    const timerDisplay = document.getElementById('swap-timer');
    const nameDisplay = document.getElementById('target-site-name');

    function toggleScreensaver() {
    if (toggle.checked) {
        // HIDE MENU IMMEDIATELY
        bestAutoHide();
        
        statusText.innerText = "Monitoring...";
        statusText.classList.replace('text-slate-500', 'text-sky-400');
        resetScreensaver();
    } else {
        statusText.innerText = "Rotation Off";
        statusText.classList.replace('text-sky-400', 'text-slate-500');
        stopAllTimers();
    }
}
    function stopAllTimers() {
        if(notification) notification.classList.remove('show');
        clearInterval(countdownInterval);
        clearTimeout(inactivityTimer);
    }

    function resetScreensaver() {
        if (!toggle || !toggle.checked) return;
        stopAllTimers();

        const waitMinutes = waitSelector ? parseInt(waitSelector.value) : 5;
        // Logic: Wait Minutes * 60 seconds * 1000ms
        const selectedWait = waitMinutes * 5 * 1000; 
        
        inactivityTimer = setTimeout(startCountdown, selectedWait);
        statusText.innerText = "Monitoring...";
    }

    function startCountdown() {
    if (!toggle.checked) return;
    
    // --- TRIGGER THE HAND BOT ---
    triggerHandBotClick();

    // Update the notification UI for the TV
    statusText.innerText = "S-Saver Active";
    let timeLeft = 10; 
    
    if (nameDisplay) nameDisplay.innerText = targetSiteName;
    if (timerDisplay) timerDisplay.innerText = timeLeft;
    if (notification) notification.classList.add('show');

    countdownInterval = setInterval(() => {
        timeLeft--;
        if (timerDisplay) timerDisplay.innerText = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(countdownInterval);
            window.location.href = `?f_site=${targetSiteCode}`;
        }
    }, 1000);
}
    // Capture events to reset the timer
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetScreensaver, { passive: true });
    });
/**
 * HAND BOT: Mimics a physical mouse click at a specific coordinate
 * This is used to trigger the "Close Sidebar" logic in menu.php
 */
function triggerHandBotClick() {
    console.log("🤖 Hand Bot: Performing virtual click to hide menu...");

    // 1. Define the target (The center of your 50" TV screen)
    const x = window.innerWidth / 2;
    const y = window.innerHeight / 2;

    // 2. Create a "Real" Mouse Event
    const clickEvent = new MouseEvent('click', {
        view: window,
        bubbles: true,
        cancelable: true,
        clientX: x,
        clientY: y
    });

    // 3. Dispatch the click to the body
    document.body.dispatchEvent(clickEvent);
}
/**
 * BEST AUTO HIDE: The most stable way to kill ghost space
 * and ensure a full-screen dashboard on your 50" TV.
 */
function bestAutoHide() {
    console.log("🤖 BestAutoHide: Cleaning layout...");
    
    // 1. Force remove all classes that cause the "squeeze"
    const ghostClasses = ['sidebar-open', 'active', 'toggled', 'sidebar-toggled', 'show'];
    document.body.classList.remove(...ghostClasses);
    
    // 2. Target common wrapper IDs to force 0px margins
    const wrapper = document.getElementById('wrapper') || document.getElementById('content-wrapper');
    if (wrapper) {
        wrapper.style.marginLeft = "0px";
        wrapper.style.paddingLeft = "0px";
        wrapper.style.width = "100%";
    }

    // 3. THE HAND BOT: Perform a virtual click in the center of the screen
    // This triggers the internal "close" logic of menu.php
    const x = window.innerWidth / 2;
    const y = window.innerHeight / 2;
    const clickEvent = new MouseEvent('click', {
        view: window, 
        bubbles: true, 
        cancelable: true, 
        clientX: x, 
        clientY: y
    });
    document.body.dispatchEvent(clickEvent);
    
    // 4. Force Charts to recalculate for 100% width
    window.dispatchEvent(new Event('resize'));
}
    // Initialize
    toggleScreensaver();
</script>
  
</body>
</html>