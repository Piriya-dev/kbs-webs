#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>

// ===== Config =====
const char* ssid = "CCTV@IOT";
const char* password = "KBSit@2468";
const char* serverName = "http://203.154.4.209/pages/firepump/check_status_for_esp32.php";
const char* uploadURL = "http://203.154.4.209/pages/firepump/update_status.php";

// Pin Mapping (GPIO numbers)
#define STATUS_LED 4    // D2 (Relay - ตั้ง Jumper เป็น H)
#define TEST_BUTTON 0   // D3 (Switch - Active Low)
#define WIFI_LED 2      // D4 (Built-in LED)

// ===== Variables =====
unsigned long buttonPressedTime = 0; 
bool isButtonPressed = false;        
bool manualOverride = false;         
bool webActive = false; 

unsigned long lastRequestTime = 0;
const long requestInterval = 1000; 
unsigned long lastSerialTime = 0;
unsigned long lastSensorTime = 0;
const long sensorInterval = 10000;

void setup() {
  Serial.begin(115200);
  pinMode(WIFI_LED, OUTPUT);
  pinMode(STATUS_LED, OUTPUT);
  pinMode(TEST_BUTTON, INPUT_PULLUP);
  
  digitalWrite(STATUS_LED, LOW); // เริ่มต้น Relay OFF (สำหรับ High Trigger)
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(WIFI_LED, !digitalRead(WIFI_LED));
    delay(250);
    Serial.print(".");
  }
  digitalWrite(WIFI_LED, LOW); 
  Serial.println("\n--- ONLINE: Ready for Web Control ---");
}

void loop() {
  unsigned long currentMillis = millis();
  int swState = digitalRead(TEST_BUTTON);

  // 1. ระบบ Manual Override (กดปุ่ม 5 วินาที)
  if (swState == LOW) {
    if (!isButtonPressed) {
      isButtonPressed = true;
      buttonPressedTime = currentMillis; 
    }
    if (currentMillis - buttonPressedTime >= 5000) manualOverride = true;
  } else { 
    isButtonPressed = false;
    manualOverride = false; 
  }

  // 2. Logic ตัดสินใจ (OR Logic)
  // ถ้ากดปุ่ม 5 วิ หรือ เว็บสั่ง Active -> ให้เปิด Relay
  if (manualOverride || webActive) {
    digitalWrite(STATUS_LED, HIGH); // สั่ง HIGH เพื่อ ON (High Trigger)
  } else {
    digitalWrite(STATUS_LED, LOW);  // สั่ง LOW เพื่อ OFF
  }

  // 3. อ่านสถานะจาก Web (ขาลง)
  if (currentMillis - lastRequestTime >= requestInterval) {
    lastRequestTime = currentMillis;
    if (WiFi.status() == WL_CONNECTED) {
      WiFiClient client;
      HTTPClient http;
      if (http.begin(client, serverName)) {
        int httpCode = http.GET();
        if (httpCode > 0) {
          String payload = http.getString();
          payload.trim(); // ล้างช่องว่างที่อาจติดมาจาก PHP
          
          // ตรวจสอบ Active/Unactive (ไม่สนตัวเล็กใหญ่)
          webActive = payload.equalsIgnoreCase("Active");
        }
        http.end();
      }
    }
  }

  // 4. ส่งค่า Sensor (ขาขึ้น)
  if (currentMillis - lastSensorTime >= sensorInterval) {
    lastSensorTime = currentMillis;
    if (WiFi.status() == WL_CONNECTED) {
      WiFiClient client;
      HTTPClient http;
      if (http.begin(client, uploadURL)) {
        http.addHeader("Content-Type", "application/x-www-form-urlencoded");
        String postData = "temp=25.5&humid=65.0"; // ตัวอย่างค่าเซนเซอร์
        http.POST(postData);
        http.end();
      }
    }
  }

  // 5. Serial Monitor รายงานผล
  if (currentMillis - lastSerialTime >= 1000) {
    lastSerialTime = currentMillis;
    Serial.println("-------------------------");
    Serial.printf("Web Comm: %s | Manual: %s\n", (webActive ? "ACTIVE" : "UNACTIVE"), (manualOverride ? "YES" : "NO"));
    Serial.printf("Relay Status: %s\n", (digitalRead(STATUS_LED) == HIGH ? ">>> ON <<<" : "OFF"));
  }
}