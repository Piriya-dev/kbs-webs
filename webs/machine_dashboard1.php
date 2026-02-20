<?php
// filename: dashboard.php

// 1. USE SYSTEM TEMP DIRECTORY (Always Writable)
$temp_dir = sys_get_temp_dir();
$stored_csv_file = $temp_dir . '/machine_data.csv';
$upload_message = '';

// --- HANDLE FILE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file_tmp = $_FILES['csv_file']['tmp_name'];
    $file_name = $_FILES['csv_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if ($file_ext === 'csv') {
        // Try to move file
        if (move_uploaded_file($file_tmp, $stored_csv_file)) {
            $upload_message = '<div class="alert success">✅ Success! File saved to: ' . $stored_csv_file . '</div>';
        } else {
            // Fallback: Copy if move fails
            if (copy($file_tmp, $stored_csv_file)) {
                $upload_message = '<div class="alert success">✅ Success! File saved (copy method).</div>';
            } else {
                $upload_message = '<div class="alert error">❌ Critical Error: Server cannot write to ' . $temp_dir . '</div>';
            }
        }
    } else {
        $upload_message = '<div class="alert error">❌ Invalid file. Please upload a .csv file.</div>';
    }
}

// --- READ DATA ---
$machines = [];
$stats = ['total' => 0, 'ready' => 0, 'not_ready' => 0, 'in_use' => 0, 'standby' => 0, 'breakdown' => 0];
$file_exists = file_exists($stored_csv_file);

function getShortName($name) {
    if (preg_match('/(No\.|NO\.)\s*(\d+)/i', $name, $matches)) {
        return "MC-" . str_pad($matches[2], 2, "0", STR_PAD_LEFT);
    }
    return mb_substr($name, 0, 15, "UTF-8") . '...';
}

if ($file_exists) {
    if (($handle = fopen($stored_csv_file, "r")) !== FALSE) {
        for ($i = 0; $i < 4; $i++) fgets($handle); // Skip headers
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty($data[1]) || $data[1] == 'Total') continue;
            
            $name = $data[1];
            // 5: In Use, 6: Assign Req, 7: Standby, 8: PM Plan, 9: Breakdown
            $in_use = $data[5] ?? 0; $assign = $data[6] ?? 0; $standby = $data[7] ?? 0;
            $pm = $data[8] ?? 0; $breakdown = $data[9] ?? 0;

            $status = "Unknown"; $group = "Unknown"; $css = "";

            if ($in_use == 1) { $status = "In Use"; $group = "Ready"; $css = "in-use"; $stats['in_use']++; }
            elseif ($standby == 1) { $status = "Standby"; $group = "Ready"; $css = "standby"; $stats['standby']++; }
            elseif ($assign == 1) { $status = "Assign Req"; $group = "Ready"; $css = "assign"; }
            elseif ($pm == 1) { $status = "PM Plan"; $group = "Not Ready"; $css = "pm"; }
            elseif ($breakdown == 1) { $status = "Breakdown"; $group = "Not Ready"; $css = "breakdown"; $stats['breakdown']++; }

            if ($group != "Unknown") {
                $machines[] = ['name' => $name, 'short_name' => getShortName($name), 'status' => $status, 'group' => $group, 'class' => $css];
                $stats['total']++;
                if ($group == 'Ready') $stats['ready']++;
                if ($group == 'Not Ready') $stats['not_ready']++;
            }
        }
        fclose($handle);
    }
}
$utilization = $stats['total'] > 0 ? round(($stats['in_use'] / $stats['total']) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Machine Status Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .upload-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 0 auto 30px auto; max-width: 600px; text-align: center; }
        .upload-btn { background-color: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .header { text-align: center; margin-bottom: 30px; }
        .kpi-row { display: flex; justify-content: center; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .kpi-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 150px; text-align: center; }
        .kpi-value { font-size: 2.5em; font-weight: bold; margin: 10px 0; color: #333; }
        .monitor-widget { display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; }
        .monitor-col { flex: 1; min-width: 300px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .col-header { padding: 15px; text-align: center; color: white; font-weight: bold; font-size: 1.2em; text-transform: uppercase; }
        .header-ready { background-color: #27ae60; }
        .header-not-ready { background-color: #c0392b; }
        .machine-list { padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px; }
        .mc-card { background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .mc-card:hover { transform: translateY(-3px); }
        .in-use { border-left: 5px solid #2ecc71; color: #27ae60; }
        .standby { border-left: 5px solid #f1c40f; color: #f39c12; }
        .assign { border-left: 5px solid #3498db; color: #2980b9; }
        .breakdown { border-left: 5px solid #e74c3c; color: #c0392b; }
        .pm { border-left: 5px solid #9b59b6; color: #8e44ad; }
        .mc-name { font-weight: bold; color: #333; }
        .mc-status { font-size: 0.85em; font-weight: bold; margin-top: 5px; text-align: right; }
    </style>
</head>
<body>

    <div class="header">
        <h1>🏭 Shop Floor Monitor</h1>
    </div>

    <div class="upload-container">
        <h3>Update Data Source</h3>
        <?php echo $upload_message; ?>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <br><br>
            <button type="submit" class="upload-btn">Upload CSV File</button>
        </form>
    </div>

    <?php if ($file_exists && $stats['total'] > 0): ?>
        
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $stats['total']; ?></div>
                <div>Total Machines</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: #27ae60;"><?php echo $stats['ready']; ?></div>
                <div>Ready</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: #c0392b;"><?php echo $stats['not_ready']; ?></div>
                <div>Not Ready</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $utilization; ?>%</div>
                <div>Utilization</div>
            </div>
        </div>

        <div class="monitor-widget">
            <div class="monitor-col">
                <div class="col-header header-ready">✅ Ready Queue</div>
                <div class="machine-list">
                    <?php foreach ($machines as $mc): ?>
                        <?php if ($mc['group'] == 'Ready'): ?>
                            <div class="mc-card <?php echo $mc['class']; ?>">
                                <div class="mc-name"><?php echo $mc['short_name']; ?></div>
                                <div class="mc-status <?php echo $mc['class']; ?>"><?php echo $mc['status']; ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="monitor-col">
                <div class="col-header header-not-ready">❌ Maintenance / Down</div>
                <div class="machine-list">
                    <?php $has_down = false;
                    foreach ($machines as $mc): 
                        if ($mc['group'] == 'Not Ready'): $has_down = true; ?>
                            <div class="mc-card <?php echo $mc['class']; ?>">
                                <div class="mc-name"><?php echo $mc['short_name']; ?></div>
                                <div class="mc-status <?php echo $mc['class']; ?>"><?php echo $mc['status']; ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if(!$has_down): ?>
                        <div style="text-align:center; padding:20px; color:#aaa;">All machines are operational!</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div style="text-align: center; color: #666; margin-top: 50px;">
            <h2>Waiting for Data...</h2>
            <p>Please upload your <b>CSV file</b> to generate the dashboard.</p>
        </div>
    <?php endif; ?>

</body>
</html>