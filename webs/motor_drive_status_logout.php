<?php
/**
 * pages/firepump/motor_drive_status_logout.php
 */
session_start();

// --- 1. DATABASE CONFIGURATION (รวมไว้ในไฟล์เดียว) ---
$db_host = '127.0.0.1';
$db_name = 'kbs_eng_db'; // *** เปลี่ยนเป็นชื่อ DB ของคุณ ***
$db_user = 'kbs-ccsonline';               // *** เปลี่ยนเป็น User ของคุณ ***
$db_pass = '@Kbs2024!#';              

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    if (isset($_SESSION['current_log_id'])) {
        $log_id = $_SESSION['current_log_id'];
        $stmt = $conn->prepare("UPDATE login_logs SET logout_at = NOW() WHERE id = ?");
        $stmt->execute([$log_id]);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// --- 2. DESTROY SESSION ---
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

// --- 3. REDIRECT TO LOGIN PAGE ---
// ส่งกลับไปที่ "ชื่อเล่น" ตาม .htaccess
header("Location: /motor_drive_room_login");
exit;