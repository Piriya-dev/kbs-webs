<?php
// check_status_for_esp32.php
header('Content-Type: text/plain');
if (file_exists("light_status.txt")) {
    echo file_get_contents("light_status.txt");
} else {
    echo "Unactive";
}
?>