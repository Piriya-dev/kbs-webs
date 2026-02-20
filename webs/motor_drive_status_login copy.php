<?php
session_start();
// Replace with your desired credentials
$username_disk = "admin";
$password_disk = "KBS@2026"; 

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST['username'] === $username_disk && $_POST['password'] === $password_disk) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $_POST['username'];
        header("Location: motor_drive_status.php");
        exit;
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>KBS Login - Motor Drive Monitoring</title>
    <style>
        body { background: #0f172a; color: white; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #1e293b; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); width: 300px; text-align: center; }
        input { width: 100%; padding: 10px; margin: 10px 0; border-radius: 5px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #3b82f6; border: none; color: white; font-weight: bold; border-radius: 5px; cursor: pointer; margin-top: 10px; }
        button:hover { background: #2563eb; }
        .error { color: #ef4444; font-size: 0.8rem; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="login-box">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" style="height:40px; margin-bottom: 20px;">
        <h3>IT System Access</h3>
        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        <form method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>