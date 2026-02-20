<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Dashboard Debugging Page</h1>";

// 1. Check Database Connection
echo "<h3>1. Database Connection</h3>";
$db_path = __DIR__ . '/../../api/hr_db.php';
if (file_exists($db_path)) {
    require_once $db_path;
    if (isset($mysqli) && !$mysqli->connect_error) {
        echo "<p style='color:green;'>✅ Connected to Database Successfully.</p>";
    } else {
        die("<p style='color:red;'>❌ Database Connection Failed: " . ($mysqli->connect_error ?? 'mysqli object not found') . "</p>");
    }
} else {
    die("<p style='color:red;'>❌ Connection file not found at: $db_path</p>");
}

// 2. Check Master Sites (Dropdown Source)
echo "<h3>2. Master Sites Table (Dropdown Test)</h3>";
$site_query = $mysqli->query("SELECT * FROM master_sites");
if ($site_query) {
    $site_count = $site_query->num_rows;
    echo "<p>Total Sites in DB: $site_count</p>";
    if ($site_count > 0) {
        echo "<table border='1' cellpadding='5'><tr><th>site_code</th><th>site_name</th><th>is_active</th></tr>";
        while ($s = $site_query->fetch_assoc()) {
            $color = $s['is_active'] == 1 ? "green" : "red";
            echo "<tr><td>{$s['site_code']}</td><td>{$s['site_name']}</td><td style='color:$color'>{$s['is_active']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange;'>⚠️ Table 'master_sites' is empty!</p>";
    }
} else {
    echo "<p style='color:red;'>❌ Query Error (master_sites): " . $mysqli->error . "</p>";
}

// 3. Check Vehicle Utilization Data
echo "<h3>3. Vehicle Utilization Table (Data Test)</h3>";
$date_query = $mysqli->query("SELECT MAX(report_date) as last_date, COUNT(*) as total_rows FROM vehicle_utilization");
$date_info = $date_query->fetch_assoc();

if ($date_info['total_rows'] > 0) {
    echo "<p style='color:green;'>✅ Found " . $date_info['total_rows'] . " records in vehicle_utilization.</p>";
    echo "<p>Latest Date in DB: <b>" . $date_info['last_date'] . "</b></p>";

    // Test a sample query with the latest date
    $last_date = $date_info['last_date'];
    echo "<p>Testing Data Fetch for Date: $last_date ...</p>";
    
    $test_sql = "SELECT * FROM vehicle_utilization WHERE report_date = ? LIMIT 5";
    $stmt = $mysqli->prepare($test_sql);
    $stmt->bind_param("s", $last_date);
    $stmt->execute();
    $res = $stmt->get_result();
    
    echo "<table border='1' cellpadding='5'><tr><th>site_code</th><th>vehicle_type</th><th>total_amount</th><th>assign_reuire</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr>
                <td>{$row['site_code']}</td>
                <td>{$row['vehicle_type']}</td>
                <td>{$row['total_amount']}</td>
                <td>" . ($row['assign_reuire'] ?? '<span style="color:red">Column Missing!</span>') . "</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>❌ No data found in 'vehicle_utilization' table.</p>";
}

echo "<br><hr>";
echo "<p><i>Please check if 'site_code' in Master Sites matches 'site_code' in Vehicle Utilization exactly.</i></p>";
?>