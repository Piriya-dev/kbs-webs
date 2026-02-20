<?php
session_start();

/**
 * login.php - Access Control for KBS Motor Drive Monitoring
 */

// 1. Define Credentials (Ideally move these to config.php later)
$username_disk = "admin";
$password_disk = "KBS@2026"; 

$login_failed = false;

// 2. Handle Login Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_user = $_POST['username'] ?? '';
    $input_pass = $_POST['password'] ?? '';

    if ($input_user === $username_disk && $input_pass === $password_disk) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $input_user;
        $_SESSION['login_time'] = time();
        
        // Redirect to main dashboard
        // header("Location: motor_drive_status.php");
        header("Location: /motor_drive_room_dashboard");
        exit;
    } else {
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
            --error: #ef4444;
        }

        body { 
            background: var(--bg); 
            color: var(--text); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }

        .login-box { 
            background: var(--card); 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2); 
            width: 100%;
            max-width: 350px; 
            text-align: center; 
            border: 1px solid #334155; 
        }

        .login-box img {
            height: 45px;
            margin-bottom: 20px;
        }

        h2 { margin-bottom: 5px; font-size: 1.2rem; }
        p { color: #94a3b8; font-size: 0.8rem; margin-bottom: 25px; }

        input { 
            width: 100%; 
            padding: 12px 15px; 
            margin: 10px 0; 
            border-radius: 8px; 
            border: 1px solid #334155; 
            background: #0f172a; 
            color: white; 
            box-sizing: border-box; 
            outline: none;
            transition: border 0.3s;
        }

        input:focus { border-color: var(--accent); }

        .btn-group { 
            display: flex; 
            gap: 12px; 
            margin-top: 20px; 
        }

        button { 
            flex: 1; 
            padding: 12px; 
            border: none; 
            color: white; 
            font-weight: 700; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: opacity 0.2s; 
        }

        .btn-login { background: var(--accent); }
        .btn-login:hover { opacity: 0.9; }

        .btn-cancel { 
            background: transparent; 
            border: 1px solid #475569; 
            color: #94a3b8; 
        }
        .btn-cancel:hover { 
            background: rgba(255,255,255,0.05); 
            color: #fff; 
        }

        /* Responsive */
        @media (max-width: 400px) {
            .login-box { margin: 20px; padding: 30px; }
        }
    </style>
</head>
<body>

    <div class="login-box">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="KBS Logo">
        <h2>IT System Login</h2>
        <p>Motor Drive Control Room Monitoring</p>
        
        <form method="post" id="loginForm">
            <input type="text" name="username" placeholder="Username" required autocomplete="off">
            <input type="password" name="password" placeholder="Password" required>
            
            <div class="btn-group">
                <button type="button" class="btn-cancel" onclick="exitToLogin()">Cancel</button>
                <button type="submit" class="btn-login">Login</button>
            </div>
        </form>
    </div>

    

    <script>
        // 1. Handles the Cancel button to refresh/back to login
        function exitToLogin() {
            if (confirm("Do you want to clear the form and reset the login page?")) {
                window.location.href = "motor_drive_status_login.php";
            }
        }

        // 2. Check if PHP reported a failed login attempt
        <?php if($login_failed): ?>
            // Show the standard browser popup
            alert("Username or Password wrong! Please try again or click Cancel to reset.");
            
            // Clear the password field for the user
            const passField = document.querySelector('input[type="password"]');
            if(passField) {
                passField.value = '';
                passField.focus();
            }
        <?php endif; ?>
    </script>

</body>
</html>