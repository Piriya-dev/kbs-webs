<?php
ob_start(); // ป้องกัน Error: Cannot modify header information
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../api/hr_db.php'; 

// จำลองสิทธิ์ Admin
$_SESSION['user_role'] = 'admin'; 
$is_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

// ==========================================
// 1. DELETE & CLEAR LOGIC (ต้องอยู่บนสุด)
// ==========================================

// ลบรายการเดียว
if (isset($_GET['delete_site']) && isset($_GET['delete_date'])) {
    if(!$is_admin) die("Unauthorized");
    $dsite = $_GET['delete_site'];
    $ddate = $_GET['delete_date'];
    $stmt = $mysqli->prepare("DELETE FROM vehicle_utilization WHERE site_code = ? AND report_date = ?");
    $stmt->bind_param("ss", $dsite, $ddate);
    $stmt->execute();
    header("Location: vehicle_list1.php?f_date=$ddate&msg=Deleted");
    exit();
}

// ล้างข้อมูลทั้งวัน (Clear Data)
if (isset($_GET['clear_date'])) {
    if(!$is_admin) die("Unauthorized");
    $cdate = $_GET['clear_date'];
    $stmt = $mysqli->prepare("DELETE FROM vehicle_utilization WHERE report_date = ?");
    $stmt->bind_param("s", $cdate);
    $stmt->execute();
    header("Location: vehicle_list1.php?f_date=$cdate&msg=Cleared");
    exit();
}

// ==========================================
// 2. INCLUDE MENU (หลังจากจัดการ Logic เสร็จ)
// ==========================================
include 'menu.php'; 

// 3. FILTERS & FETCH DATA
$f_date = $_GET['f_date'] ?? date('Y-m-d');
$f_site_code = $_GET['f_site_code'] ?? 'ALL';

$site_list_res = $mysqli->query("SELECT site_code, site_name FROM master_sites WHERE is_active = 1 ORDER BY site_code ASC");
$available_sites = $site_list_res->fetch_all(MYSQLI_ASSOC);

$sql = "SELECT v.*, m.site_name as master_site_name 
        FROM vehicle_utilization v 
        LEFT JOIN master_sites m ON v.site_code = m.site_code 
        WHERE v.report_date = ?";
$params = [$f_date]; $types = "s";
if ($f_site_code !== 'ALL') {
    $sql .= " AND v.site_code = ?";
    $params[] = $f_site_code;
    $types .= "s";
}
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$all_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. GROUP DATA
$sites_data = [];
foreach ($all_rows as $row) { 
    $siteKey = $row['site_code'];
    $sites_data[$siteKey]['name'] = $row['master_site_name'] ?: "Site: " . $row['site_code'];
    $sites_data[$siteKey]['rows'][] = $row; 
}

// 5. TOTALS CALCULATOR
function getSiteTotals($rows) {
    $t = ['total' => 0, 'inuse' => 0, 'brk' => 0, 'avail_y' => 0, 'standby' => 0, 'pm' => 0];
    foreach ($rows as $r) {
        $t['total']   += (int)$r['total_amount'];
        $t['inuse']   += (int)$r['in_use'];
        $t['brk']     += (int)$r['breakdown'];
        $t['avail_y'] += (int)$r['availability_y'];
        $t['standby'] += (int)$r['standby'];
        $t['pm']      += (int)$r['pm_plan'];
    }
    $div = max($t['total'], 1);
    $t['p_avail']   = round(($t['avail_y'] / $div) * 100, 1);
    $t['p_inuse']   = round(($t['inuse'] / $div) * 100, 1);
    $t['p_standby'] = round(($t['standby'] / $div) * 100, 1);
    return $t;
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>KBS Fleet Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
        body { font-family: 'Inter', sans-serif; transition: padding-left 0.3s ease; } 
        .pdf-fit-page { width: 1300px !important; background: white !important; }
        @media print { body { padding-left: 0 !important; } }
    </style>
</head>
<body class="bg-blue-50/40 min-h-screen">

    <header class="bg-white border-b border-slate-200 p-4 sticky top-0 z-[100] no-pdf shadow-sm">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-extrabold text-slate-800 uppercase italic">KBS <span class="text-sky-600">Fleet Dashboard</span></h1>
            <div class="flex gap-2">
                <?php if($is_admin && count($all_rows) > 0): ?>
                <button onclick="confirmClear('<?= $f_date ?>')" class="bg-rose-100 text-rose-600 hover:bg-rose-600 hover:text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-all border border-rose-200">
                    <i class="fas fa-trash-alt mr-2"></i> CLEAR DATA
                </button>
                <?php endif; ?>

                <button onclick="runExport(true)" class="bg-rose-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md">PDF</button>
                <a href="vehicle_entry.php" class="bg-emerald-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md">MANUAL</a>
                <a href="vehicle_import1.php" class="bg-sky-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md">UPLOAD CSV</a>
            </div>
        </div>
    </header>

    <main id="report-content" class="container mx-auto p-6">
        <div class="bg-white p-6 rounded-[2rem] shadow-sm mb-10 no-pdf flex items-end gap-6 border border-slate-200">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase block mb-2">Date</label>
                <input type="date" value="<?= $f_date ?>" onchange="location.href='?f_date='+this.value+'&f_site_code=<?= $f_site_code ?>'" class="bg-slate-50 p-3 rounded-2xl font-bold outline-none ring-2 ring-slate-100 focus:ring-sky-500">
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase block mb-2">Location</label>
                <select onchange="location.href='?f_date=<?= $f_date ?>&f_site_code='+this.value" class="bg-slate-50 p-3 rounded-2xl font-bold outline-none ring-2 ring-slate-100 focus:ring-sky-500">
                    <option value="ALL">All Sites</option>
                    <?php foreach ($available_sites as $s): ?>
                        <option value="<?= $s['site_code'] ?>" <?= ($f_site_code == $s['site_code']) ? 'selected' : '' ?>><?= h($s['site_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if(empty($sites_data)): ?>
            <div class="text-center py-20 bg-white rounded-[2rem] border border-dashed border-slate-300">
                <i class="fas fa-folder-open text-slate-200 text-6xl mb-4"></i>
                <p class="text-slate-400 font-bold">ไม่พบข้อมูลในวันที่เลือก</p>
            </div>
        <?php endif; ?>

        <?php foreach ($sites_data as $siteCode => $site): $st = getSiteTotals($site['rows']); ?>
        <div class="mb-20">
            <h2 class="text-3xl font-black text-slate-800 mb-8 border-l-8 border-sky-500 pl-4 uppercase italic leading-none"><?= h($site['name']) ?></h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-[2rem] shadow-sm flex items-center justify-between border">
                    <div class="w-20 h-20"><canvas id="avail-<?= $siteCode ?>"></canvas></div>
                    <div class="text-right"><span class="text-[10px] font-bold text-slate-400 uppercase">Availability</span><div class="text-xl font-black text-emerald-600"><?= $st['p_avail'] ?>%</div></div>
                </div>
                <div class="bg-white p-6 rounded-[2rem] shadow-sm flex items-center justify-between border">
                    <div class="w-20 h-20"><canvas id="util-<?= $siteCode ?>"></canvas></div>
                    <div class="text-right"><span class="text-[10px] font-black text-slate-400 uppercase">Utilization</span><div class="text-xl font-black text-sky-600"><?= $st['p_inuse'] ?>%</div></div>
                </div>
                <div class="bg-white p-6 rounded-[2rem] shadow-sm flex items-center justify-between border">
                    <div class="w-20 h-20"><canvas id="standby-<?= $siteCode ?>"></canvas></div>
                    <div class="text-right"><span class="text-[10px] font-black text-slate-400 uppercase">Standby Rate</span><div class="text-xl font-black text-amber-500"><?= $st['p_standby'] ?>%</div></div>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] shadow-sm overflow-hidden border">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b">
                        <tr class="text-slate-400 font-black text-[10px] uppercase tracking-widest">
                            <th class="p-6">Vehicle Type</th>
                            <th class="p-2 text-center">Total</th>
                            <th class="p-2 text-center text-emerald-500">Ready</th>
                            <th class="p-2 text-center text-sky-500">Used</th>
                            <th class="p-2 text-center text-amber-500">STB</th>
                            <th class="p-2 text-center text-rose-500">BRK</th>
                            <?php if($is_admin): ?><th class="p-4 text-center no-pdf">Action</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y italic font-medium">
                        <?php foreach ($site['rows'] as $r): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-6 font-bold uppercase text-slate-800"><?= h($r['vehicle_type']) ?></td>
                            <td class="p-2 text-center font-black"><?= (int)$r['total_amount'] ?></td>
                            <td class="p-2 text-center font-bold text-emerald-600"><?= (int)$r['availability_y'] ?></td>
                            <td class="p-2 text-center font-bold text-sky-600"><?= (int)$r['in_use'] ?></td>
                            <td class="p-2 text-center font-bold text-amber-500"><?= (int)$r['standby'] ?></td>
                            <td class="p-2 text-center font-bold text-rose-600"><?= (int)$r['breakdown'] ?></td>
                            <?php if($is_admin): ?>
                            <td class="p-4 text-center no-pdf">
                                <a href="vehicle_entry.php?edit_id=<?= $r['id'] ?>" class="text-sky-500 hover:scale-110 transition-transform inline-block"><i class="fas fa-edit text-lg"></i></a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            {
                const chartOpt = { responsive: true, plugins: { legend: { display: false } }, cutout: '70%' };
                new Chart(document.getElementById('avail-<?= $siteCode ?>'), { type: 'doughnut', data: { datasets: [{ data: [<?= $st['p_avail'] ?>, <?= 100 - $st['p_avail'] ?>], backgroundColor: ['#10b981', '#f1f5f9'], borderWidth: 0 }] }, options: chartOpt });
                new Chart(document.getElementById('util-<?= $siteCode ?>'), { type: 'doughnut', data: { datasets: [{ data: [<?= $st['p_inuse'] ?>, <?= 100 - $st['p_inuse'] ?>], backgroundColor: ['#0284c7', '#f1f5f9'], borderWidth: 0 }] }, options: chartOpt });
                new Chart(document.getElementById('standby-<?= $siteCode ?>'), { type: 'doughnut', data: { datasets: [{ data: [<?= $st['p_standby'] ?>, <?= 100 - $st['p_standby'] ?>], backgroundColor: ['#f59e0b', '#f1f5f9'], borderWidth: 0 }] }, options: chartOpt });
            }
        </script>
        <?php endforeach; ?>
    </main>

    <script>
    function confirmClear(date) {
        if (confirm("แจ้งเตือน: ต้องการลบข้อมูลทั้งหมดของวันที่ " + date + " ใช่หรือไม่?\nการดำเนินการนี้ไม่สามารถยกเลิกได้!")) {
            window.location.href = 'vehicle_list1.php?clear_date=' + date;
        }
    }

    function runExport(shouldFit) {
        const element = document.getElementById('report-content');
        if(shouldFit) element.classList.add('pdf-fit-page');
        const opt = { 
            margin: 5, 
            filename: 'KBS_Fleet_Report_' + '<?= $f_date ?>.pdf', 
            image: { type: 'jpeg', quality: 1 }, 
            html2canvas: { scale: 2 }, 
            jsPDF: { format: 'a4', orientation: 'landscape' } 
        };
        document.querySelectorAll('.no-pdf').forEach(el => el.style.display = 'none');
        html2pdf().set(opt).from(element).save().then(() => {
            document.querySelectorAll('.no-pdf').forEach(el => el.style.display = 'flex');
            element.classList.remove('pdf-fit-page');
        });
    }
    </script>
</body>
</html>
<?php
ob_end_flush(); // ปล่อย Output ออกมาทั้งหมด
?>
