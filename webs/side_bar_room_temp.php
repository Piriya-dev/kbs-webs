<?php
// sidebar.php
// ตรวจสอบชื่อไฟล์ปัจจุบันเพื่อไฮไลท์เมนูที่กำลังเปิดอยู่
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        KBS <span style="color: #fff;">ENG</span>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
            <span>📊</span> Dashboard
        </a>
        <a href="report.php" class="nav-item <?= ($current_page == 'report.php') ? 'active' : '' ?>">
            <span>📈</span> Report
        </a>
        <a href="setting.php" class="nav-item <?= ($current_page == 'setting.php') ? 'active' : '' ?>">
            <span>⚙️</span> Settings
        </a>
    </nav>
</aside>