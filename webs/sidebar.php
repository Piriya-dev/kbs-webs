<nav class="nav">
    <button class="nav-toggle" id="navToggle">☰</button>
    
    <div class="nav-logo">
        <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" alt="KBS Logo">
    </div>

    <div class="nav-divider"></div>

    <div class="nav-menu-items" style="width:100%;">
    <div class="nav-item" id="navSummary" onclick="showPage('pageSummary')" data-tooltip="สรุปสถานะ">
          <div class="nav-item-icon">📊</div>
          <span>สรุปสถานะ</span>
      </div>
      <div class="nav-item active" id="navMap" onclick="showPage('pageMap')" data-tooltip="แผนที่ GIS">
          <div class="nav-item-icon">📍</div>
          <span>แผนที่ GIS</span>
      </div>
   
      <div class="nav-item" id="navTemp" onclick="showPage('pageTemp')" data-tooltip="เซนเซอร์ IIoT">
          <div class="nav-item-icon">🌡️</div>
          <span>เซนเซอร์ IIoT</span>
      </div>
      
      <div class="nav-divider"></div>
      
      <div class="nav-item" id="navSavePoly" onclick="savePolygonBoundary()" data-tooltip="บันทึกขอบเขต">
          <div class="nav-item-icon">💾</div>
          <span>บันทึกขอบเขต</span>
      </div>

      <div class="nav-item" id="navRaw" onclick="showPage('pageRaw')" data-tooltip="Debug JSON">
          <div class="nav-item-icon">🧾</div>
          <span>Debug JSON</span>
      </div>
      <!-- <div class="nav-item" id="navInsert" onclick="showPage('pageInsert')" data-tooltip="บันทึกข้อมูล">
    <div class="nav-item-icon">📥</div>
    <span>บันทึกข้อมูล MQTT</span>
</div> -->
      <div class="nav-item" id="navSettings" onclick="showPage('pageSettings')" data-tooltip="ตั้งค่าระบบ">
          <div class="nav-item-icon">⚙️</div>
          <span>ตั้งค่าระบบ</span>
      </div>
    </div>

    <div style="flex:1;"></div>

    <div class="nav-divider"></div>
    <div class="nav-item profile-item">
        <div class="nav-item-icon" style="background: #3b82f6; border-radius: 50%; color: white; width: 30px; height: 30px; min-width: 30px; font-size: 12px; display:flex; align-items:center; justify-content:center;">PW</div>
        <span style="font-size: 12px; display: flex; flex-direction: column; line-height: 1.2; margin-left: 12px;">
            <b>P. Wong</b>
            <small style="opacity: 0.7;">Administrator</small>
        </span>
    </div>
</nav>