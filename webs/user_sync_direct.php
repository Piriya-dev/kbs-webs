<?php
// 1. DATABASE CONNECTION (Keep this at the top)
$host = "127.0.0.1";
$user = "kbs-ccsonline"; // Your DB user
$pass = "@Kbs2024!#";     // Your DB password
$dbname = "kbs_db"; // Change to your real DB name

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$message = "";

// 2. PROCESS UPLOAD (When button is clicked)
if (isset($_POST['submit_sync']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    
    if (is_uploaded_file($file) && ($handle = fopen($file, "r")) !== FALSE) {
        fgetcsv($handle); // Skip header row

        $sql = "INSERT INTO master_users1_upload
                (code, fname, role, title, status, region_code, phone, phone_for_test1, phone_for_test2, phone_original) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                fname = VALUES(fname), role = VALUES(role), title = VALUES(title), 
                status = VALUES(status), region_code = VALUES(region_code), 
                phone = VALUES(phone), phone_for_test1 = VALUES(phone_for_test1), 
                phone_for_test2 = VALUES(phone_for_test2), phone_original = VALUES(phone_original)";

        $stmt = $conn->prepare($sql);
        $conn->begin_transaction();
        // 1. Force the database connection to use UTF8
        $conn->set_charset("utf8");
        
        try {
            $count = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) >= 10) {
                    $stmt->bind_param("ssssssssss", 
                        $data[0], $data[1], $data[2], $data[3], $data[4], 
                        $data[5], $data[6], $data[7], $data[8], $data[9]
                    );
                    $stmt->execute();
                    $count++;
                }
            }
            $conn->commit();
            $message = "<div style='color:green;'>Success: $count records synced!</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div style='color:red;'>Error: " . $e->getMessage() . "</div>";
        }
        fclose($handle);
    } else {
        $message = "<div style='color:red;'>No file selected or permission denied.</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Direct Sync</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 50px; text-align: center; }
        .container { background: white; padding: 30px; border-radius: 8px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input { margin: 20px 0; }
        button { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <h2>Master User Direct CSV Sync</h2>
    <?php echo $message; ?>
    
    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required>
        <br>
        <button type="submit" name="submit_sync">Upload & Sync Now</button>
    </form>
</div>

</body>
</html>