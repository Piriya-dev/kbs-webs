<?php
// update_status.php
if (isset($_GET['status'])) {
    $status = $_GET['status'];
    // บันทึกสถานะลงในไฟล์ light_status.txt
    file_put_contents("light_status.txt", $status);
    echo "Update Success: " . $status;
} else {
    echo "No status received";
}
?>