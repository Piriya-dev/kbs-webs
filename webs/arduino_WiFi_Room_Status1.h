#include <WiFi.h>
#include <HTTPClient.h>

const char* ssid = "CCTV@IOT";
const char* password = "KBSit@2468";

// 1. URL สำหรับอ่านค่าสวิตช์ (ขาลง)
const char* serverName = "http://203.154.4.209/pages/firepump/check_status_for_esp32.php";
// 2. URL สำหรับส่งค่าเซนเซอร์กลับ (ขาขึ้น)
const char* uploadURL = "http://203.154.4.209/pages/firepump/update_sensor.php";

#define LED2 2
bool isBlinking = false;
bool ledState = false;
unsigned long lastRequestTime = 0;
const long requestInterval = 1000; 
unsigned long lastBlinkTime = 0;
const long blinkInterval = 300; 
unsigned long lastSensorTime = 0;
const long sensorInterval = 10000; // ส่งค่าเซนเซอร์ทุก 10 วินาที

void setup_wifi() {
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(ssid, password);
  Serial.print("Connecting WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Connected!");
}

void setup() {
  Serial.begin(115200);
  pinMode(LED2, OUTPUT);
  digitalWrite(LED2, LOW);
  setup_wifi();
}

void loop() {
  unsigned long currentMillis = millis();

  // --- Task 1: อ่านสถานะจากเว็บ (ทำทุก 1 วินาที) ---
  if (currentMillis - lastRequestTime >= requestInterval) {
    lastRequestTime = currentMillis;
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.begin(serverName);
      int httpResponseCode = http.GET();
      if (httpResponseCode > 0) {
        String payload = http.getString();
        payload.trim();
        
        // แสดงผลวินาทีปัจจุบันเพื่อให้รู้ว่า Real-time
        Serial.printf("[%lus] Web Status: %s\n", currentMillis/1000, payload.c_str());

        if (payload == "Active") isBlinking = true;
        else {
          isBlinking = false;
          digitalWrite(LED2, LOW);
        }
      }
      http.end();
    }
  }

  // --- Task 2: ส่งค่า Sensor กลับไปโชว์ที่ Dashboard (ทำทุก 10 วินาที) ---
  if (currentMillis - lastSensorTime >= sensorInterval) {
    lastSensorTime = currentMillis;
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.begin(uploadURL);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      
      // ค่าจำลอง (Khim สามารถเปลี่ยนเป็นค่าจากเซนเซอร์จริงได้)
      float t = 24.0 + (random(0, 100) / 100.0);
      float h = 60.0 + (random(0, 100) / 100.0);
      String postData = "temp=" + String(t) + "&humid=" + String(h);
      
      int httpCode = http.POST(postData);
      Serial.printf(">>> Sensor Data Sent! Response: %d\n", httpCode);
      http.end();
    }
  }

  // --- Task 3: จัดการไฟกะพริบ ---
  if (isBlinking) {
    if (currentMillis - lastBlinkTime >= blinkInterval) {
      lastBlinkTime = currentMillis;
      ledState = !ledState;
      digitalWrite(LED2, ledState);
    }
  }
}