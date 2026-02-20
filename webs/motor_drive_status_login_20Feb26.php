<?php
/**
 * motor_drive_status_login.php - Integrated Database & Login Logic
 */
session_start();

// --- 1. DATABASE CONFIGURATION (รวมไว้ในไฟล์เดียว) ---
$db_host = '127.0.0.1';
$db_name = 'kbs_eng_db'; // *** เปลี่ยนเป็นชื่อ DB ของคุณ ***
$db_user = 'kbs-ccsonline';               // *** เปลี่ยนเป็น User ของคุณ ***
$db_pass = '@Kbs2024!#';                   // *** เปลี่ยนเป็น Password ของคุณ ***

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

$login_failed = false;

// --- 2. HANDLE LOGIN SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_user = $_POST['username'] ?? '';
    $input_pass = $_POST['password'] ?? '';

    try {
        // ค้นหาผู้ใช้จากตาราง users
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$input_user]);
        $user = $stmt->fetch();

        if ($user && $input_pass === $user['password']) {
            
            // ป้องกัน Session Fixation
            session_regenerate_id(true);

            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();

            // --- 3. บันทึกประวัติการ Login ลงในตาราง login_logs ---
            $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, username, login_at, ip_address) VALUES (?, ?, NOW(), ?)");
            $log_stmt->execute([
                $user['id'], 
                $user['username'], 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $_SESSION['current_log_id'] = $conn->lastInsertId();

            header("Location: /motor_drive_room_dashboard");
            exit;
        } else {
            $login_failed = true;
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $login_failed = true; 
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>KBS IT System - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --accent: #3b82f6;
            --text: #f1f5f9;
        }
        body { background: var(--bg); color: var(--text); font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: var(--card); padding: 40px; border-radius: 15px; width: 100%; max-width: 350px; text-align: center; border: 1px solid #334155; }
        .login-box img { height: 45px; margin-bottom: 20px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; outline: none; }
        input:focus { border-color: var(--accent); }
        .btn-group { display: flex; gap: 12px; margin-top: 20px; }
        button { flex: 1; padding: 12px; border: none; color: white; font-weight: bold; border-radius: 8px; cursor: pointer; }
        .btn-login { background: var(--accent); }
        .btn-cancel { background: transparent; border: 1px solid #475569; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="login-box">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="KBS Logo">
        <h2>IT System Login</h2>
        <p style="color: #94a3b8; font-size: 0.8rem;">Motor Drive Control Room Monitoring</p>
        
        <form method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <div class="btn-group">
                <button type="button" class="btn-cancel" onclick="window.location.reload()">Cancel</button>
                <button type="submit" class="btn-login">Login</button>
            </div>
        </form>
    </div>

    <script>
        <?php if($login_failed): ?>
            alert("Username or Password wrong!");
        <?php endif; ?>
    </script>
</body>
</html>