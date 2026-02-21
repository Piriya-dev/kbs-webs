#include <WiFi.h>
#include <HTTPClient.h>

// ===== 1. WiFi Config =====
const char* ssid = "CCTV@IOT";
const char* password = "KBSit@2468";

// ===== 2. Server Config =====
const char* serverName = "http://203.154.4.209/pages/firepump/check_status_for_esp32.php";
const char* uploadURL = "http://203.154.4.209/pages/firepump/update_sensor.php";

// ===== 3. Pin & Variables =====
#define WIFI_LED 2      // LED บนบอร์ดโชว์สถานะ WiFi
#define STATUS_LED 4    // LED ภายนอกโชว์สถานะ Active/Unactive
bool isBlinking = false;
bool ledState = false;

unsigned long lastRequestTime = 0;
const long requestInterval = 1000; 
unsigned long lastBlinkTime = 0;
const long blinkInterval = 300; 
unsigned long lastSensorTime = 0;
const long sensorInterval = 10000; 

void setup_wifi() {
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(ssid, password);

  Serial.print("Connecting WiFi");
  // ในขณะที่รอเชื่อมต่อ ให้ไฟ WiFi กะพริบเร็วๆ เพื่อบอกว่ากำลังพยายามต่อ
  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(WIFI_LED, !digitalRead(WIFI_LED));
    delay(250);
    Serial.print(".");
  }

  Serial.println("\nWiFi Connected");
  digitalWrite(WIFI_LED, HIGH); // เชื่อมต่อสำเร็จ -> ไฟ WiFi ติดค้าง
}

void setup() {
  Serial.begin(115200);
  pinMode(WIFI_LED, OUTPUT);
  pinMode(STATUS_LED, OUTPUT);
  
  digitalWrite(WIFI_LED, LOW);
  digitalWrite(STATUS_LED, LOW);

  setup_wifi();
}

void loop() {
  unsigned long currentMillis = millis();

  // --- เช็คสถานะ WiFi ตลอดเวลา ---
  if (WiFi.status() == WL_CONNECTED) {
    digitalWrite(WIFI_LED, HIGH); // ติดค้างถ้า Online
  } else {
    digitalWrite(WIFI_LED, LOW);  // ดับถ้า Offline
    // setup_wifi(); // ปลดคอมเมนต์ถ้าอยากให้พยายามต่อใหม่ทันทีที่หลุด
  }

  // --- Task 1: ดึงสถานะ Active/Unactive (Polling) ---
  if (currentMillis - lastRequestTime >= requestInterval) {
    lastRequestTime = currentMillis;

    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.begin(serverName);
      int httpResponseCode = http.GET();

      if (httpResponseCode > 0) {
        String payload = http.getString();
        payload.trim();
        
        Serial.print("Web Status: ");
        Serial.println(payload);

        if (payload == "Active") {
          isBlinking = true;
        } else {
          isBlinking = false;
          digitalWrite(STATUS_LED, LOW); // ดับไฟสถานะทันทีถ้า Unactive
        }
      }
      http.end();
    }
  }

  // --- Task 2: ส่งค่า Sensor กลับไปโชว์ที่ Dashboard (ทุก 10 วินาที) ---
  if (currentMillis - lastSensorTime >= sensorInterval) {
    lastSensorTime = currentMillis;
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.begin(uploadURL);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      
      float t = 24.0 + (random(0, 100) / 100.0);
      float h = 60.0 + (random(0, 100) / 100.0);
      String postData = "temp=" + String(t) + "&humid=" + String(h);
      
      int httpCode = http.POST(postData);
      Serial.printf(">>> Sensor Data Sent! HTTP: %d\n", httpCode);
      http.end();
    }
  }

  // --- Task 3: จัดการการกะพริบของ STATUS_LED (Pin 4) ---
  if (isBlinking) {
    if (currentMillis - lastBlinkTime >= blinkInterval) {
      lastBlinkTime = currentMillis;
      ledState = !ledState;
      digitalWrite(STATUS_LED, ledState);
    }
  }
}