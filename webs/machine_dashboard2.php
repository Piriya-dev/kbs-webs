<?php
// filename: dashboard.php

// 1. SETUP: Use system temp directory
$temp_dir = sys_get_temp_dir();
$stored_csv_file = $temp_dir . '/machine_data.csv';
$upload_message = '';

// --- HANDLE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file_tmp = $_FILES['csv_file']['tmp_name'];
    if (move_uploaded_file($file_tmp, $stored_csv_file) || copy($file_tmp, $stored_csv_file)) {
        $upload_message = '<div class="alert success">✅ Dashboard Updated Successfully!</div>';
    } else {
        $upload_message = '<div class="alert error">❌ Error saving file.</div>';
    }
}

// --- INITIALIZE ---
$machines = [];
$stats = [
    'total' => 0, 
    'ready' => 0, 
    'not_ready' => 0, 
    'in_use' => 0,
    'standby' => 0,
    'avail_y' => 0, 
    'avail_n' => 0,
    'count_pm' => 0,
    'count_breakdown' => 0
];

$file_exists = file_exists($stored_csv_file);

function getShortName($name) {
    if (preg_match('/^(MT-MC-[A-Z]+-\d+)/', $name, $matches)) {
        return $matches[1];
    }
    return mb_substr($name, 0, 15, "UTF-8");
}

if ($file_exists && ($handle = fopen($stored_csv_file, "r")) !== FALSE) {
    for ($i = 0; $i < 4; $i++) fgets($handle); 
    
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (empty($data[1]) || $data[1] == 'Total') continue;
        
        $name = $data[1];
        $is_y = (isset($data[3]) && trim($data[3]) == '1') ? 1 : 0;
        $is_n = (isset($data[4]) && trim($data[4]) == '1') ? 1 : 0;
        $stats['avail_y'] += $is_y;
        $stats['avail_n'] += $is_n;

        $in_use = (isset($data[5]) && trim($data[5]) == '1') ? 1 : 0; 
        $standby = (isset($data[7]) && trim($data[7]) == '1') ? 1 : 0;
        $assign = (isset($data[6]) && trim($data[6]) == '1') ? 1 : 0; 
        $pm = (isset($data[8]) && trim($data[8]) == '1') ? 1 : 0; 
        $breakdown = (isset($data[9]) && trim($data[9]) == '1') ? 1 : 0;

        $status = "Unknown"; $group = "Unknown"; $color = "#ccc"; $util_percent = 0;
        $card_dot = "#ccc"; 

        if ($in_use == 1) { 
            $status = "In Use"; $group = "Available"; $color="#2ecc71"; $card_dot="#2ecc71";
            $stats['in_use']++; $util_percent = 100; 
        } elseif ($standby == 1) { 
            $status = "Standby"; $group = "Available"; $color="#f1c40f"; $card_dot="#f1c40f";
            $stats['standby']++; $util_percent = 0;   
        } elseif ($assign == 1) { 
            $status = "Assign Req"; $group = "Available"; $color="#3498db"; $card_dot="#f1c40f";
            $util_percent = 0;
        } elseif ($pm == 1) { 
            $status = "PM Plan"; $group = "Not Available"; $color="#9b59b6"; $card_dot="#e74c3c";
            $stats['count_pm']++; $util_percent = 0;
        } elseif ($breakdown == 1) { 
            $status = "Breakdown"; $group = "Not Available"; $color="#e74c3c"; $card_dot="#e74c3c";
            $stats['count_breakdown']++; $util_percent = 0;
        }

        if ($group != "Unknown") {
            $machines[] = [
                'short_name' => getShortName($name), 
                'status' => $status, 
                'group' => $group, 
                'color' => $color,
                'dot' => $card_dot,
                'utilization' => $util_percent
            ];
            $stats['total']++;
            if ($group == 'Available') $stats['ready']++;
            if ($group == 'Not Available') $stats['not_ready']++;
        }
    }
    fclose($handle);
}

// Grouping logic for the 3 tabs
$tab_used = [];
$tab_standby = [];
$tab_breakdown = [];

foreach ($machines as $mc) {
    if ($mc['status'] == 'In Use') {
        $tab_used[] = $mc;
    } elseif ($mc['status'] == 'Standby' || $mc['status'] == 'Assign Req') {
        $tab_standby[] = $mc;
    } elseif ($mc['status'] == 'Breakdown' || $mc['status'] == 'PM Plan') {
        $tab_breakdown[] = $mc;
    }
}

// Calculations for Donuts
$total_avail = $stats['avail_y'] + $stats['avail_n'];
$avail_percent = ($total_avail > 0) ? round(($stats['avail_y'] / $total_avail) * 100) : 0;
$donut_avail = "conic-gradient(#2ecc71 0% $avail_percent%, #e74c3c $avail_percent% 100%)";

$total_ready = $stats['in_use'] + $stats['standby'];
$in_use_percent = ($total_ready > 0) ? round(($stats['in_use'] / $total_ready) * 100) : 0;
$standby_percent = ($total_ready > 0) ? round(($stats['standby'] / $total_ready) * 100) : 0;
$donut_util = ($total_ready == 0) ? "conic-gradient(#eee 0% 100%)" : "conic-gradient(#2ecc71 0% $in_use_percent%, #f1c40f $in_use_percent% 100%)";

$total_down = $stats['count_breakdown'] + $stats['count_pm'];
$bd_percent = ($total_down > 0) ? round(($stats['count_breakdown'] / $total_down) * 100) : 0;
$donut_maint = ($total_down == 0) ? "conic-gradient(#eee 0% 100%)" : "conic-gradient(#e74c3c 0% $bd_percent%, #9b59b6 $bd_percent% 100%)";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KBS-Machine Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f4f6f9; --green: #2ecc71; --red: #e74c3c; --blue: #3498db; --yellow: #f1c40f; --purple: #9b59b6; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #333; }
        .navbar { background: white; padding: 15px 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { border: 2px solid var(--blue); color: var(--blue); background: white; padding: 6px 15px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        
        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; flex-direction: column; min-height: 250px; }
        .kpi-lbl { font-size: 0.85rem; text-transform: uppercase; color: #888; font-weight: 700; margin-bottom: 15px; text-align: left; }
        .kpi-content { flex-grow: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; }
        
        .donut-container { display: flex; align-items: center; gap: 20px; justify-content: center; width: 100%; }
        .donut-chart { width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .donut-inner { width: 85px; height: 85px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; }
        .total-subtext { font-weight: 800; font-size: 1rem; color: #333; margin-top: 10px; }
        
        .legend-column { display: flex; flex-direction: column; gap: 10px; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; white-space: nowrap; }
        .dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }

        .tab-container { background: white; border-radius: 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .tabs-header { display: flex; border-bottom: 2px solid #eee; }
        .tab-btn { 
            flex: 1; padding: 20px; text-align: center; cursor: pointer; font-weight: 800; color: #aaa; background: #fafafa; 
            display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.2s;
        }
        .tab-icon { width: 20px; height: 20px; flex-shrink: 0; }
        
        /* Dynamic Tab Active Colors */
        .tab-btn.active { background: white; color: #333; }
        .tab-btn.t-used.active { border-bottom: 4px solid var(--green); }
        .tab-btn.t-stby.active { border-bottom: 4px solid var(--yellow); }
        .tab-btn.t-down.active { border-bottom: 4px solid var(--red); }
        
        .tab-content { display: none; padding: 25px; }
        .tab-content.active { display: block; }

        .mc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .mc-card { background: white; border: 1px solid #eee; border-radius: 12px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); display: flex; flex-direction: column; height: 110px; position: relative; }
        .card-status-dot { width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; display: inline-block; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
        .mc-name { font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; }
        .mc-status { font-size: 0.7rem; font-weight: 700; padding: 4px 8px; border-radius: 6px; }
        .progress-bg { background: #eee; height: 6px; border-radius: 4px; margin-top: auto; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 4px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: 600; }
        .success { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>

<div class="navbar">
    <h1 style="margin:0; display: flex; align-items: center; gap: 15px;">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="KBS Logo" style="height:40px;">
        <span class="nav-title">Machine Shop Monitor(Beta)</span>
    </h1>
    <form action="" method="post" enctype="multipart/form-data" id="upForm">
        <label class="btn">📂 Upload CSV <input type="file" name="csv_file" accept=".csv" style="display:none;" onchange="document.getElementById('upForm').submit()"></label>
    </form>
</div>

    <?php echo $upload_message; ?>

    <?php if ($file_exists && $stats['total'] > 0): ?>

        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-lbl">% Health</div>
                <div class="kpi-content">
                    <div class="donut-container">
                        <div style="text-align:center">
                            <div class="donut-chart" style="background: <?php echo $donut_avail; ?>">
                                <div class="donut-inner"><?php echo $avail_percent; ?>%</div>
                            </div>
                            <div class="total-subtext">Total Machines: <?php echo $total_avail; ?></div>
                        </div>
                        <div class="legend-column">
                            <div class="legend-item" style="color:var(--green)"><div class="dot" style="background:var(--green)"></div> พร้อม: <?php echo $stats['avail_y']; ?></div>
                            <div class="legend-item" style="color:var(--red)"><div class="dot" style="background:var(--red)"></div> ไม่พร้อม: <?php echo $stats['avail_n']; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-lbl" style="color:var(--green)" >พร้อมใช้งาน(คัน)</div>
                <div class="kpi-content">
                    <div class="donut-container">
                        <div class="donut-chart" style="background: <?php echo $donut_util; ?>">
                            <div class="donut-inner"><?php echo $total_ready; ?></div>
                        </div>
                        <div class="legend-column">
                            <div class="legend-item" style="color:var(--green)"><div class="dot" style="background:var(--green)"></div> ใช้งาน: <?php echo $stats['in_use']; ?> (<?php echo $in_use_percent; ?>%)</div>
                            <div class="legend-item" style="color:var(--yellow)"><div class="dot" style="background:var(--yellow)"></div> สแตนด์บาย: <?php echo $stats['standby']; ?> (<?php echo $standby_percent; ?>%)</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-lbl" style="color:var(--red)">ไม่พร้อมใช้งาน(คัน)</div>
                <div class="kpi-content">
                    <div class="donut-container">
                        <div style="text-align:center">
                            <div class="donut-chart" style="background: <?php echo $donut_maint; ?>">
                                <div class="donut-inner" style="font-size:1.5rem"><?php echo $total_down; ?></div>
                            </div>
                        </div>
                        <div class="legend-column">
                            <div class="legend-item" style="color:var(--red)"><div class="dot" style="background:var(--red)"></div> เสีย: <?php echo $stats['count_breakdown']; ?></div>
                            <div class="legend-item" style="color:var(--purple)"><div class="dot" style="background:var(--purple)"></div> ซ่อม: <?php echo $stats['count_pm']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-container">
            <div class="tabs-header">
                <div class="tab-btn t-used active" onclick="openTab('tabUsed', this)">
                    <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--green)">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    กำลังใช้งาน (<?php echo count($tab_used); ?>)
                </div>
                
                <div class="tab-btn t-stby" onclick="openTab('tabStandby', this)">
                    <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--yellow)">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    สแตนด์บาย (<?php echo count($tab_standby); ?>)
                </div>

                <div class="tab-btn t-down" onclick="openTab('tabDown', this)">
                    <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--red)">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                    แจ้งซ่อม/เสีย (<?php echo count($tab_breakdown); ?>)
                </div>
            </div>
            
            <div id="tabUsed" class="tab-content active">
                <div class="mc-grid">
                    <?php foreach ($tab_used as $mc): ?>
                        <div class="mc-card" style="border-left: 5px solid <?php echo $mc['color']; ?>">
                            <div class="mc-name">
                                <span class="card-status-dot" style="background: <?php echo $mc['dot']; ?>"></span>
                                <?php echo $mc['short_name']; ?>
                            </div>
                            <div style="margin-top: 5px;">
                                <span class="mc-status" style="background:<?php echo $mc['color']; ?>15; color:<?php echo $mc['color']; ?>"><?php echo $mc['status']; ?></span>
                            </div>
                            <div class="progress-bg"><div class="progress-fill" style="width:<?php echo $mc['utilization']; ?>%; background:<?php echo $mc['color']; ?>"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tabStandby" class="tab-content">
                <div class="mc-grid">
                    <?php foreach ($tab_standby as $mc): ?>
                        <div class="mc-card" style="border-left: 5px solid <?php echo $mc['color']; ?>">
                            <div class="mc-name">
                                <span class="card-status-dot" style="background: <?php echo $mc['dot']; ?>"></span>
                                <?php echo $mc['short_name']; ?>
                            </div>
                            <div style="margin-top: 5px;">
                                <span class="mc-status" style="background:<?php echo $mc['color']; ?>15; color:<?php echo $mc['color']; ?>"><?php echo $mc['status']; ?></span>
                            </div>
                            <div class="progress-bg"><div class="progress-fill" style="width:0%; background:<?php echo $mc['color']; ?>"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tabDown" class="tab-content">
                <div class="mc-grid">
                    <?php foreach ($tab_breakdown as $mc): ?>
                        <div class="mc-card" style="border-left: 5px solid <?php echo $mc['color']; ?>">
                            <div class="mc-name">
                                <span class="card-status-dot" style="background: <?php echo $mc['dot']; ?>"></span>
                                <?php echo $mc['short_name']; ?>
                            </div>
                            <div style="margin-top: 5px;">
                                <span class="mc-status" style="background:<?php echo $mc['color']; ?>15; color:<?php echo $mc['color']; ?>"><?php echo $mc['status']; ?></span>
                            </div>
                            <div class="progress-bg"><div class="progress-fill" style="width:0%; background:<?php echo $mc['color']; ?>"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div style="text-align:center; margin-top:80px; color:#aaa;"><h2>No Data. Please upload CSV.</h2></div>
    <?php endif; ?>

    <script>
        function openTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(d => d.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>
</html>