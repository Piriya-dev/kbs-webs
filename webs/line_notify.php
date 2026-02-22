<?php
// pages/firepump/line_notify.php
header('Content-Type: application/json');

function sendLinePush($message, $accessToken, $userId) {
    $url = 'https://api.line.me/v2/bot/message/push';
    
    // โครงสร้าง JSON Payload
    $data = [
        'to' => $userId,
        'messages' => [
            [
                'type' => 'text',
                'text' => $message
            ]
        ]
    ];

    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    // ตั้งค่า Headers ให้เหมือนกับที่คุณใช้ใน Node-RED
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);

    // ⚠️ สำคัญมาก: ข้ามการตรวจสอบ SSL เพื่อป้องกัน Network Error ในบาง Server
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'error' => $err,
        'result' => json_decode($result, true)
    ];
}

if (isset($_POST['test_alarm'])) {
    $accessToken = 'C2wBOtd3y8bXw7m8TCPU6kE3y8cMFi1w4J98wC1SZiqirrYWMqCSrPcQKjwus39B/f/9Ev1bpE1FAWoDN4/Nq2zcACx0r0K88juxk+Rq4fbZgTQCRUgM5of+rl2tOsFR0URBFmSeVHeOAfhTe0xhQQdB04t89/1O/w1cDnyilFU='; // Token ที่ใช้ได้ใน Node-RED
    $userId = 'Ub4e26942b3c80454751b2d60939fb2ec'; 
    
    $userMsg = $_POST['message'] ?? "Test Message from KBS System";
    $response = sendLinePush($userMsg, $accessToken, $userId);
    
    echo json_encode($response);
    exit;
}
?>