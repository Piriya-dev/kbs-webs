<?php
// test_curl.php
$ch = curl_init("https://www.google.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
if($res === false) {
    echo "cURL Error: " . curl_error($ch);
} else {
    echo "cURL is working fine!";
}
?>