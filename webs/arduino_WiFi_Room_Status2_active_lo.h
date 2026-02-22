#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>

// ===== Config =====
const char* ssid = "CCTV@IOT";
const char* password = "KBSit@2468";
const char* serverName = "http://203.154.4.209/pages/firepump/check_status_for_esp32.php";
const char* uploadURL = "http://203.154.4.209/pages/firepump/update_status.php";

// Pin Mapping (GPIO numbers)
#define STATUS_LED 4    // D2 (Relay - เปลี่ยน Jumper บนบอร์ดกลับไปที่ L)
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
  
  // *** สำคัญ: เริ่มต้นสั่ง HIGH เพื่อให้ Relay OFF (สำหรับ Active Low) ***
  digitalWrite(STATUS_LED, HIGH); 
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(WIFI_LED, !digitalRead(WIFI_LED));
    delay(250);
    Serial.print(".");
  }
  digitalWrite(WIFI_LED, LOW); 
  Serial.println("\n--- ONLINE: Active Low Mode Ready ---");
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

  // 2. Logic ตัดสินใจ (Active Low Logic)
  if (manualOverride || webActive) {
    // สั่ง LOW เพื่อเปิด Relay (Active Low)
    digitalWrite(STATUS_LED, LOW); 
  } else {
    // สั่ง HIGH เพื่อปิด Relay
    digitalWrite(STATUS_LED, HIGH); 
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
          payload.trim(); 
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
        String postData = "temp=25.5&humid=65.0"; 
        http.POST(postData);
        http.end();
      }
    }
  }

  // 5. Serial Monitor รายงานผล (ปรับให้ตรงกับ Active Low)
  if (currentMillis - lastSerialTime >= 1000) {
    lastSerialTime = currentMillis;
    int actualPin = digitalRead(STATUS_LED);
    Serial.println("-------------------------");
    Serial.printf("Web Comm: %s | Manual: %s\n", (webActive ? "ACTIVE" : "UNACTIVE"), (manualOverride ? "YES" : "NO"));
    
    // รายงานผล: ถ้า Pin เป็น LOW แสดงว่า ON
    Serial.printf("Relay Status: %s\n", (actualPin == LOW ? ">>> ON (ACTIVE) <<<" : "OFF (INACTIVE)"));
  }
}