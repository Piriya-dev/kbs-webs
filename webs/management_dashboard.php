<?php
// /opt/lampp/htdocs/pages/hr/vehicle_import1.php
ob_start();
session_start();
require_once __DIR__ . '/../../api/hr_db.php'; 
// Sidebar removed to prevent ghost space on 50" TV

$msg = "";
$status = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $site_code = $_POST['site_code'] ?? '1';

    if (($handle = fopen($file, "r")) !== FALSE) {
        // Clear old data for that date/site to prevent duplicates
        $clear_sql = "DELETE FROM vehicle_utilization WHERE report_date = ? AND site_code = ?";
        $stmt_clear = $mysqli->prepare($clear_sql);
        $stmt_clear->bind_param("ss", $report_date, $site_code);
        $stmt_clear->execute();

        $import_count = 0;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // SKIP LOGIC: check if first column is numeric ID
            if (!is_numeric($data[0])) {
                continue;
            }

            $vehicle_type    = $data[1];
            $total_amount    = (int)$data[2];
            $availability_y  = (int)$data[3];
            $availability_n  = (int)$data[4];
            $in_use          = (int)$data[5];
            $assign_require  = (int)($data[6] ?? 0);
            $standby         = (int)$data[7];
            $pm_plan         = (int)$data[8];
            $breakdown       = (int)$data[9];
            $remarks         = $data[10] ?? '';

            $sql = "INSERT INTO vehicle_utilization 
                    (report_date, site_code, vehicle_type, total_amount, availability_y, availability_n, in_use, assign_reuire, standby, pm_plan, breakdown, remarks, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("sssiiiiiiiis", 
                $report_date, $site_code, $vehicle_type, $total_amount, 
                $availability_y, $availability_n, $in_use, $assign_require, 
                $standby, $pm_plan, $breakdown, $remarks
            );
            
            if ($stmt->execute()) {
                $import_count++;
            }
        }
        fclose($handle);
        $msg = "นำเข้าข้อมูลสำเร็จทั้งหมด $import_count รายการ";
        $status = "success";
    } else {
        $msg = "ไม่สามารถเปิดไฟล์ได้";
        $status = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Import Fleet Data - KBS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            margin: 0; 
            padding: 0;
            width: 100vw;
            overflow-x: hidden;
        }

        /* Form Container Polish */
        .import-card {
            background: white;
            border-radius: 2.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }
    </style>
</head>
<body class="bg-slate-50 p-6 lg:p-10">

    <div class="max-w-4xl mx-auto">
        
        <header class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div class="flex items-center gap-4">
                <a href="https://www.kbs.co.th/" target="_blank">
                    <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="KBS Logo" class="h-10 w-auto">
                </a>
                <div class="border-l-2 border-slate-200 pl-4">
                    <h1 class="text-2xl font-black text-slate-900 italic uppercase leading-none">
                        CSV <span class="text-sky-600">Import</span>
                    </h1>
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-[0.2em] mt-1">Vehicle Utilization System</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="management_dashboard.php" class="flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-600 hover:text-sky-600 hover:border-sky-400 transition-all shadow-sm group font-bold">
                    <i class="fas fa-chart-line text-xs group-hover:scale-110 transition-transform"></i>
                    <span class="text-xs uppercase italic tracking-wider">Management Dashboard</span>
                </a>
            </div>
        </header>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-2xl <?= $status === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?> font-bold text-sm flex items-center shadow-sm">
                <i class="fas <?= $status === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3 text-lg"></i>
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="import-card p-8 lg:p-12">
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-3 tracking-widest">Select Report Date</label>
                        <input type="date" name="report_date" value="<?= date('Y-m-d') ?>" required 
                               class="w-full bg-slate-50 border-2 border-slate-50 focus:border-sky-500 focus:bg-white rounded-2xl p-4 font-bold text-slate-700 transition-all outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-3 tracking-widest">Target Site</label>
                        <select name="site_code" class="w-full bg-slate-50 border-2 border-slate-50 focus:border-sky-500 focus:bg-white rounded-2xl p-4 font-bold text-slate-700 transition-all outline-none">
                            <option value="1">KBS Khonburi (ครบุรี)</option>
                            <option value="2">KBS Sikhiu (สีคิ้ว)</option>
                        </select>
                    </div>
                </div>

                <div class="relative">
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" class="hidden" required onchange="updateFileName(this)">
                    <label for="csv_file" class="flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-[2.5rem] p-12 cursor-pointer hover:border-sky-400 hover:bg-sky-50/30 transition-all group">
                        <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-sky-100 transition-colors">
                            <i class="fas fa-file-csv text-3xl text-slate-400 group-hover:text-sky-600"></i>
                        </div>
                        <span id="file-name" class="text-sm font-bold text-slate-500 group-hover:text-slate-700 italic">Drop your Fleet CSV file here or click to browse</span>
                        <p class="text-[9px] text-slate-400 uppercase mt-2 tracking-widest font-bold">Supported format: .CSV only</p>
                    </label>
                </div>

                <button type="submit" class="w-full bg-slate-900 text-white py-5 rounded-[2rem] font-black uppercase tracking-[0.2em] hover:bg-sky-600 hover:-translate-y-1 transition-all shadow-xl shadow-slate-200">
                    Process and Import Data
                </button>
            </form>
        </div>

        <div class="mt-10 flex gap-4">
            <div class="flex-1 p-6 bg-blue-50/50 rounded-3xl border border-blue-100">
                <h4 class="text-[10px] font-black text-blue-600 uppercase mb-2 italic">Format Note:</h4>
                <p class="text-[10px] text-blue-700 leading-relaxed font-medium">
                    ระบบจะเริ่มอ่านข้อมูลจากแถวที่มีหมายเลข ID (บรรทัดที่ 5) โปรดใช้ Template มาตรฐานเท่านั้น
                </p>
            </div>
            <div class="flex-1 p-6 bg-amber-50/50 rounded-3xl border border-amber-100">
                <h4 class="text-[10px] font-black text-amber-600 uppercase mb-2 italic">CSV Warning:</h4>
                <p class="text-[10px] text-amber-700 leading-relaxed font-medium">
                    หากข้อมูลผิดพลาด ระบบจะลบข้อมูลของ Site และวันที่ระบุไว้ก่อนทำการ Insert ใหม่
                </p>
            </div>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const display = document.getElementById('file-name');
                display.innerText = "Selected: " + fileName;
                display.classList.remove('text-slate-500');
                display.classList.add('text-sky-600', 'not-italic');
            }
        }
    </script>
</body>
</html>