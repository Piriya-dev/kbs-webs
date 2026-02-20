<?php
// /opt/lampp/htdocs/pages/hr/vehicle_entry.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../api/hr_db.php';
include 'menu.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: vehicle_list1.php?msg=Unauthorized");
    exit();
}

$edit_id = $_GET['edit_id'] ?? null;
// ค่าเริ่มต้นอ้างอิงตามโครงสร้างตาราง vehicle_status
$data = [
    'date' => date('Y-m-d'),
    'fleet_license_id' => '',
    'assest' => '',
    'fleet_type_id' => '',
    'description' => '',
    'status_code' => 1
];

if ($edit_id) {
    $stmt = $mysqli->prepare("SELECT * FROM vehicle_status WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) $data = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date      = $_POST['date'];
    $lic_id    = (int)$_POST['fleet_license_id'];
    $assest    = $_POST['assest']; 
    $type_id   = (int)$_POST['fleet_type_id'];
    $desc      = $_POST['description'];
    $s_code    = (int)$_POST['status_code'];

    if ($edit_id) {
        // UPDATE (7 parameters: sisissi + i)
        $sql = "UPDATE vehicle_status SET date=?, fleet_license_id=?, assest=?, fleet_type_id=?, description=?, status_code=? WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sisissi", $date, $lic_id, $assest, $type_id, $desc, $s_code, $edit_id);
    } else {
        // INSERT (6 parameters: sisisi)
        $sql = "INSERT INTO vehicle_status (date, fleet_license_id, assest, fleet_type_id, description, status_code) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sisisi", $date, $lic_id, $assest, $type_id, $desc, $s_code);
    }
    
    if ($stmt && $stmt->execute()) { 
        header("Location: vehicle_list1.php?f_date=$date&msg=Success"); 
        exit(); 
    } else {
        die("❌ SQL Error: " . ($stmt ? $stmt->error : $mysqli->error));
    }
}

// --- ส่วนดึงข้อมูล Dropdown ---

// 1. ดึงทะเบียนรถจาก fleet_license (แก้ไขตามที่คุณแจ้ง)
$lic_res = $mysqli->query("SELECT id, license_name FROM fleet_license ORDER BY license_name ASC");
$licenses = ($lic_res) ? $lic_res->fetch_all(MYSQLI_ASSOC) : [];

// 2. ดึงประเภทรถจาก vehicle_type (Screenshot 2: type_name)
$type_res = $mysqli->query("SELECT id, type_name FROM vehicle_type WHERE status=1 ORDER BY type_name ASC");
$v_types = ($type_res) ? $type_res->fetch_all(MYSQLI_ASSOC) : [];

// 3. ดึงสถานะการใช้งานจากตาราง utilization (Screenshot 5: code, name)
$util_res = $mysqli->query("SELECT code, name FROM Utilization ORDER BY code ASC");
$util_list = ($util_res) ? $util_res->fetch_all(MYSQLI_ASSOC) : [];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Update Status - KBS Fleet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style> 
        body { padding-left: 16rem; font-family: 'Inter', sans-serif; } 
    </style>
</head>
<body class="bg-slate-50 p-8">
    <div class="max-w-3xl mx-auto bg-white rounded-[2.5rem] shadow-xl border overflow-hidden">
        <div class="bg-slate-900 p-8 text-white flex justify-between items-center">
            <div>
                <h2 class="text-xl font-black italic uppercase tracking-tighter">Update Vehicle Status</h2>
                <p class="text-[9px] text-slate-400 uppercase font-bold tracking-widest italic">Target Table: vehicle_status</p>
            </div>
            <a href="vehicle_list1.php" class="text-slate-400 hover:text-white transition-all"><i class="fas fa-times text-2xl"></i></a>
        </div>
        
        <form method="POST" class="p-10 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 italic text-sky-600">Report Date</label>
                    <input type="date" name="date" value="<?= h($data['date']) ?>" class="w-full bg-slate-50 border-0 p-4 rounded-2xl font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 italic text-sky-600">License Name (fleet_license)</label>
                    <select name="fleet_license_id" required class="w-full bg-slate-50 border-0 p-4 rounded-2xl font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                        <option value="">-- Select License --</option>
                        <?php foreach ($licenses as $lic): ?>
                            <option value="<?= $lic['id'] ?>" <?= ($data['fleet_license_id'] == $lic['id']) ? 'selected' : '' ?>><?= h($lic['license_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 italic text-sky-600">Vehicle Type (vehicle_type)</label>
                    <select name="fleet_type_id" required class="w-full bg-slate-50 border-0 p-4 rounded-2xl font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                        <option value="">-- Select Type --</option>
                        <?php foreach ($v_types as $vt): ?>
                            <option value="<?= $vt['id'] ?>" <?= ($data['fleet_type_id'] == $vt['id']) ? 'selected' : '' ?>><?= h($vt['type_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 italic text-sky-600">Utilization Status (utilization)</label>
                    <select name="status_code" required class="w-full bg-slate-50 border-0 p-4 rounded-2xl font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                        <option value="">-- Select Status --</option>
                        <?php foreach ($util_list as $ut): ?>
                            <option value="<?= $ut['code'] ?>" <?= ($data['status_code'] == $ut['code']) ? 'selected' : '' ?>><?= h($ut['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="bg-slate-50 p-6 rounded-3xl border-b-4 border-sky-500">
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 italic">Assest Amount</label>
                <input type="text" name="assest" value="<?= h($data['assest']) ?>" placeholder="e.g. 15" class="w-full bg-transparent text-2xl font-black outline-none">
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 italic">Description / Remarks</label>
                <textarea name="description" rows="3" class="w-full bg-slate-50 border-0 p-4 rounded-2xl font-bold focus:ring-2 focus:ring-sky-500 outline-none" placeholder="Details..."><?= h($data['description']) ?></textarea>
            </div>

            <button type="submit" class="w-full bg-sky-600 text-white font-black py-5 rounded-3xl shadow-xl uppercase italic tracking-widest hover:bg-sky-700 transition-all flex items-center justify-center">
                <i class="fas fa-save mr-2"></i> Save & Update Status
            </button>
            
            <a href="vehicle_list1.php" class="block text-center text-slate-400 font-bold uppercase text-[10px] hover:text-slate-600 transition-colors">Cancel & Return</a>
        </form>
    </div>
</body>
</html>
