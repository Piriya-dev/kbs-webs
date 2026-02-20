<?php
// /opt/lampp/htdocs/pages/hr/menu.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
    
    :root { --sidebar-width: 16rem; }

    #sidebar { 
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        width: var(--sidebar-width); 
    }
    
    .sidebar-hidden { transform: translateX(-100%); }

    /* ควบคุมการขยายของเนื้อหาผ่าน Body Class */
    body { 
        font-family: 'Inter', sans-serif; 
        transition: padding-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        margin: 0;
    }

    /* เมื่อเมนูเปิด (เฉพาะจอใหญ่) ให้ดันเนื้อหา */
    @media (min-width: 1025px) {
        body.sidebar-open { padding-left: var(--sidebar-width); }
    }
</style>

<button id="btn-open" onclick="toggleSidebar(event)" class="fixed left-4 top-4 z-[1001] bg-slate-900 text-white p-3 rounded-xl shadow-lg opacity-0 pointer-events-none transition-all hover:bg-sky-600">
    <i class="fas fa-bars"></i>
</button>

<aside id="sidebar" class="fixed left-0 top-0 h-screen bg-slate-900 text-slate-300 z-[1000] flex flex-col shadow-2xl no-pdf">
    <div class="p-5 border-b border-slate-800 flex justify-between items-center">
        <div class="flex items-center">
            <a href="https://www.kbs.co.th/" target="_blank" class="flex-shrink-0 transition-opacity hover:opacity-80">
                <img src="https://www.kbs.co.th/themes/default/assets/static/images/logo-main.webp" 
                     alt="KBS Logo" 
                     style="height: 32px; width: auto; object-fit: contain;">
            </a>
            
            <div class="h-8 w-[1px] bg-slate-700 mx-3"></div>
            
            <div class="flex flex-col whitespace-nowrap">
                <span class="font-extrabold text-lg text-white tracking-tighter italic leading-none">
                    KBS <span class="text-sky-500">FLEET V1</span>
                </span>
                <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mt-1">
                    Analytics Platform
                </span>
            </div>
        </div>
        
        <button onclick="toggleSidebar(event)" class="text-slate-500 hover:text-white lg:hidden ml-2">
            <i class="fas fa-times"></i>
        </button>
    </div>

    ```

### **Why this works better:**
* **The Divider:** The explicit `div` with `h-8 w-[1px]` creates a much sharper separation than a border, which can sometimes look blurry on different monitors.
* **Object-Fit:** The `object-fit: contain` ensures that if the source image has extra white space, it won't stretch or distort your sidebar layout.
* **Scale:** 32px is the "sweet spot" for sidebar logos—it's small enough to look sleek but large enough for the KBS branding to be clearly readable.

Would you like me to also add a "Back to Home" tooltip when the user hovers over the logo?

<script>
    function toggleSidebar(e) {
        if (e) e.stopPropagation();
        const sidebar = document.getElementById('sidebar');
        const btnOpen = document.getElementById('btn-open');
        const body = document.body;

        sidebar.classList.toggle('sidebar-hidden');
        body.classList.toggle('sidebar-open');
        
        if (sidebar.classList.contains('sidebar-hidden')) {
            btnOpen.style.opacity = '1'; 
            btnOpen.style.pointerEvents = 'auto';
        } else {
            btnOpen.style.opacity = '0'; 
            btnOpen.style.pointerEvents = 'none';
        }
    }

    window.addEventListener('load', () => {
        const sidebar = document.getElementById('sidebar');
        const btnOpen = document.getElementById('btn-open');
        
        if (window.innerWidth < 1024) {
            sidebar.classList.add('sidebar-hidden');
            btnOpen.style.opacity = '1';
            btnOpen.style.pointerEvents = 'auto';
        } else {
            document.body.classList.add('sidebar-open');
        }
    });
</script>
