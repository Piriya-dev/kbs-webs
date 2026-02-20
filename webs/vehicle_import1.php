<?php
// /opt/lampp/htdocs/pages/hr/vehicle_import1.php
ob_start();
session_start();
require_once __DIR__ . '/../../api/hr_db.php'; 
include 'menu.php'; 

$msg = "";
$status = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $site_code = $_POST['site_code'] ?? '1'; // Default site

    if (($handle = fopen($file, "r")) !== FALSE) {
        // ลบข้อมูลเก่าของวันที่และ Site นั้นก่อน เพื่อป้องกันข้อมูลซ้ำ (Optional)
        $clear_sql = "DELETE FROM vehicle_utilization WHERE report_date = ? AND site_code = ?";
        $stmt_clear = $mysqli->prepare($clear_sql);
        $stmt_clear->bind_param("ss", $report_date, $site_code);
        $stmt_clear->execute();

        $import_count = 0;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // SKIP LOGIC: ตรวจสอบว่าคอลัมน์แรกเป็นตัวเลข ID หรือไม่
            // ถ้าไม่ใช่ตัวเลข (เช่น เป็นคำว่า #, site_name, หรือว่าง) ให้ข้ามไป
            if (!is_numeric($data[0])) {
                continue;
            }

            // MAPPING COLUMNS (อ้างอิงตามไฟล์ Fleet Service_KBS_1.csv)
            $vehicle_type    = $data[1];
            $total_amount    = (int)$data[2];
            $availability_y  = (int)$data[3];
            $availability_n  = (int)$data[4];
            $in_use         = (int)$data[5];
            $assign_require = (int)($data[6] ?? 0); // รองรับค่าว่าง
            $standby        = (int)$data[7];
            $pm_plan        = (int)$data[8];
            $breakdown      = (int)$data[9];
            $remarks        = $data[10] ?? '';

            // INSERT DATA
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
</head>
<body class="bg-slate-50 p-6 lg:p-10">
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-900 italic uppercase">CSV <span class="text-sky-600">Import</span></h1>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Upload CSV from Excel Report</p>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-2xl <?= $status === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?> font-bold text-sm">
                <i class="fas <?= $status === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Report Date</label>
                        <input type="date" name="report_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-50 border-none rounded-xl p-3 font-bold text-slate-700">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Target Site</label>
                        <select name="site_code" class="w-full bg-slate-50 border-none rounded-xl p-3 font-bold text-slate-700">
                            <option value="1">ครบุรี</option>
                            <option value="2">สีคิ้ว</option>
                        </select>
                    </div>
                </div>

                <div class="border-2 border-dashed border-slate-200 rounded-[2rem] p-10 text-center hover:border-sky-400 transition-colors group">
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" class="hidden" required onchange="updateFileName(this)">
                    <label for="csv_file" class="cursor-pointer">
                        <i class="fas fa-file-csv text-5xl text-slate-200 group-hover:text-sky-500 transition-colors mb-4 block"></i>
                        <span id="file-name" class="text-sm font-bold text-slate-400 group-hover:text-slate-600 italic">Click to select Fleet Service_KBS_1.csv</span>
                    </label>
                </div>

                <button type="submit" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black uppercase tracking-widest hover:bg-sky-600 transition-all shadow-lg shadow-slate-200">
                    Process and Import Data
                </button>
            </form>
        </div>

        <div class="mt-8 p-6 bg-amber-50 rounded-2xl border border-amber-100">
            <h4 class="text-[10px] font-black text-amber-600 uppercase mb-2 leading-none italic"><i class="fas fa-info-circle mr-1"></i> Import Note:</h4>
            <p class="text-[10px] text-amber-700 leading-relaxed font-medium">
                ระบบจะอ่านข้อมูลจากบรรทัดที่ 5 เป็นต้นไป (ที่มีหมายเลข ID) โปรดตรวจสอบให้แน่ใจว่าได้บันทึกไฟล์จาก Excel เป็นนามสกุล .csv (Comma Separated Values) แล้วเท่านั้น
            </p>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = input.files[0].name;
            document.getElementById('file-name').innerText = "Selected: " + fileName;
            document.getElementById('file-name').classList.remove('text-slate-400');
            document.getElementById('file-name').classList.add('text-sky-600');
        }
    </script>
</body>
</html>
