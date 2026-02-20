<?php
session_start();
session_destroy();
header("Location: motor_drive_status_login.php");
exit;
?>