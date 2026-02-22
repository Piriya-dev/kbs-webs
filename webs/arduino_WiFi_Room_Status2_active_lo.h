#include <WiFi.h>
#include <HTTPClient.h>

// ===== Config =====
const char* ssid = "CCTV@IOT";
const char* password = "KBSit@2468";
const char* serverName = "http://203.154.4.209/pages/firepump/check_status_for_esp32.php";
const char* uploadURL = "http://203.154.4.209/pages/firepump/update_status.php";

// Pin Mapping สำหรับ ESP32 (แนะนำใช้ขาเหล่านี้เพื่อความเสถียร)
#define STATUS_LED 4    // GPIO 4 (ต่อกับ IN ของ Relay - Active Low)
#define TEST_BUTTON 33  // GPIO 33 (ต่อกับปุ่มกด - Active Low)
#define WIFI_LED 2      // GPIO 2 (On-board LED ของ ESP32)

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

void setup_wifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi...");
  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(WIFI_LED, !digitalRead(WIFI_LED));
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Connected!");
  digitalWrite(WIFI_LED, HIGH); // ติดค้างเมื่อเชื่อมต่อสำเร็จ
}

void setup() {
  Serial.begin(115200);
  pinMode(WIFI_LED, OUTPUT);
  pinMode(STATUS_LED, OUTPUT);
  pinMode(TEST_BUTTON, INPUT_PULLUP);
  
  // *** สำคัญ: เริ่มต้นสั่ง HIGH เพื่อให้ Relay OFF (สำหรับ Active Low) ***
  digitalWrite(STATUS_LED, HIGH); 
  
  setup_wifi();
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
    digitalWrite(STATUS_LED, LOW); // สั่ง LOW เพื่อเปิด Relay
  } else {
    digitalWrite(STATUS_LED, HIGH); // สั่ง HIGH เพื่อปิด Relay
  }

  // 3. อ่านสถานะจาก Web (ขาลง)
  if (currentMillis - lastRequestTime >= requestInterval) {
    lastRequestTime = currentMillis;
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.begin(serverName); // ESP32 ไม่ต้องใส่ WiFiClient ใน begin ทั่วไป
      int httpCode = http.GET();
      if (httpCode > 0) {
        String payload = http.getString();
        payload.trim(); 
        webActive = payload.equalsIgnoreCase("Active");
      }
      http.end();
    }
  }

  // 4. ส่งค่า Sensor (ขาขึ้น)
  if (currentMillis - lastSensorTime >= sensorInterval) {
    lastSensorTime = currentMillis;
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.begin(uploadURL);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      
      // ส่งค่าจำลอง หรืออ่านค่าจริงจาก Sensor ที่คุณมี
      String postData = "temp=25.5&humid=65.0"; 
      int httpResponseCode = http.POST(postData);
      http.end();
    }
  }

  // 5. Serial Monitor รายงานผล
  if (currentMillis - lastSerialTime >= 1000) {
    lastSerialTime = currentMillis;
    int actualPin = digitalRead(STATUS_LED);
    Serial.println("-------------------------");
    Serial.printf("System: ESP32 | Web: %s | Manual: %s\n", 
                  (webActive ? "ACTIVE" : "UNACTIVE"), 
                  (manualOverride ? "YES" : "NO"));
    
    Serial.printf("Relay Pin %d: %s\n", STATUS_LED, (actualPin == LOW ? ">>> ON (ACTIVE) <<<" : "OFF (INACTIVE)"));
  }
}