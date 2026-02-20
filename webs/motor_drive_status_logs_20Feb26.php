<?php
/**
 * pages/firepump/motor_drive_room_logs.php - Integrated DB Version
 */
session_start();

// --- 1. DATABASE CONFIGURATION (รวมไว้ในไฟล์เดียว) ---
$db_host = '127.0.0.1';
$db_name = 'kbs_eng_db'; // *** เปลี่ยนเป็นชื่อ DB ของคุณ ***
$db_user = 'kbs-ccsonline';               // *** เปลี่ยนเป็น User ของคุณ ***
$db_pass = '@Kbs2024!#';      
try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- 2. SECURITY CHECK ---
    // ตรวจสอบว่า Login หรือยัง และต้องเป็น ADMIN เท่านั้นถึงจะดูหน้านี้ได้
    if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
        header("Location: /motor_drive_room_dashboard");
        exit;
    }

    // --- 3. FETCH LOG DATA ---
    // ดึงข้อมูล 50 รายการล่าสุด พร้อมคำนวณระยะเวลาใช้งาน (Duration)
    $sql = "SELECT *, TIMEDIFF(logout_at, login_at) AS duration 
            FROM login_logs 
            ORDER BY login_at DESC 
            LIMIT 50";
    $stmt = $conn->query($sql);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // ถ้าเกิด Error 500 ให้แสดงสาเหตุ (ช่วย IT Debug ง่ายขึ้น)
    die("Database Error: " . $e->getMessage());
}

// ดึงตัวแปรมาใช้ใน Sidebar
$user_role = $_SESSION['role'] ?? 'user';
$username  = $_SESSION['username'] ?? 'Guest';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <title>KBS-Access Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/pages/firepump/css/style.css">
    <style>
        .log-container { padding: 20px; }
        .log-table { 
            width: 100%; border-collapse: collapse; background: #1e293b; 
            border-radius: 10px; overflow: hidden; border: 1px solid #334155;
        }
        .log-table th { background: #0f172a; color: #94a3b8; text-align: left; padding: 12px; font-size: 0.8rem; }
        .log-table td { padding: 12px; border-bottom: 1px solid #334155; color: #f1f5f9; font-size: 0.9rem; }
        .log-table tr:hover { background: rgba(59, 130, 246, 0.05); }
        .status-online { color: #22c55e; font-weight: bold; font-size: 0.75rem; }
    </style>
</head>
<body class="app-container">

<aside class="sidebar">
    <div class="sidebar-nav">
        <a href="/motor_drive_room_dashboard" class="nav-item">
            <span class="icon">📊</span><span class="text">Dashboard</span>
        </a>
        <a href="/motor_drive_room_settings" class="nav-item">
            <span class="icon">⚙️</span><span class="text">Settings</span>
        </a>
        <a href="/motor_drive_room_logs" class="nav-item active">
            <span class="icon">📝</span><span class="text">Access Logs</span>
        </a>
    </div>

    <div style="padding: 20px; border-top: 1px solid #334155; margin-top: auto;">
        <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Current User</div>
        <div style="font-weight: 700; color: #fff; margin-top: 5px;"><?php echo strtoupper($username); ?></div>
        <span style="display: inline-block; padding: 2px 8px; background: #3b82f6; color: #fff; border-radius: 4px; font-size: 0.65rem; font-weight: bold; margin-top: 5px;">
            <?php echo strtoupper($user_role); ?>
        </span>
    </div>

    <div style="padding-bottom: 20px;">
        <a href="/motor_drive_room_logout" class="nav-item" style="color: #ef4444;">
            <span class="icon">⏻</span><span class="text">Logout</span>
        </a>
    </div>
</aside>

<main class="main-content">
    <header class="topbar">
        <div style="font-weight:700; color: #fff;">System Access Logs</div>
        <div style="color: #94a3b8; font-size: 0.75rem;">KBS IT Audit Trail</div>
    </header>

    <div class="log-container">
        <table class="log-table">
            <thead>
                <tr>
                    <th>USER</th>
                    <th>LOGIN AT</th>
                    <th>LOGOUT AT</th>
                    <th>DURATION</th>
                    <th>IP ADDRESS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $row): ?>
                <tr>
                    <td style="color: #3b82f6; font-weight: bold;"><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo date('d/m/Y H:i:s', strtotime($row['login_at'])); ?></td>
                    <td>
                        <?php 
                        echo $row['logout_at'] 
                            ? date('d/m/Y H:i:s', strtotime($row['logout_at'])) 
                            : '<span class="status-online">● ONLINE</span>'; 
                        ?>
                    </td>
                    <td><?php echo $row['duration'] ?? '--:--:--'; ?></td>
                    <td style="font-family: monospace; opacity: 0.7;"><?php echo $row['ip_address']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>