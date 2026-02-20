<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foresight Training Deployment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Sarabun:wght@300;400;600;700&display=swap');
        
        body {
            font-family: 'Inter', 'Sarabun', sans-serif;
            background-color: #f3f4f6;
            overflow: hidden;
        }

        .slide {
            display: none;
            height: 100vh;
            width: 100vw;
            padding: 2rem;
            animation: fadeIn 0.5s ease-in-out;
        }

        .slide.active {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pillar-card {
            transition: all 0.3s ease;
        }

        .pillar-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            height: 6px;
            background: #2563eb;
            transition: width 0.3s ease;
        }

        .lang-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 50;
        }

        @media print {
            @page {
                size: landscape;
                margin: 0;
            }
            body {
                overflow: visible !important;
                background: white;
            }
            .slide {
                display: flex !important;
                opacity: 1 !important;
                transform: none !important;
                height: 100vh !important;
                width: 100vw !important;
                page-break-after: always;
                break-after: page;
                position: relative !important;
            }
            .fixed, .progress-bar, button {
                display: none !important;
            }
            /* Ensure background colors print */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50">

    <div class="progress-bar" id="progressBar" style="width: 0%"></div>

    <!-- Export Button -->
    <div class="fixed top-6 right-6 z-50">
        <button onclick="window.print()" class="px-5 py-2.5 bg-white/90 backdrop-blur-sm text-gray-700 rounded-xl shadow-lg hover:bg-gray-50 text-sm font-semibold transition border border-gray-200 flex items-center gap-2">
            <i class="fas fa-file-pdf text-red-500 text-lg"></i> Save as PDF
        </button>
    </div>

    <!-- Navigation -->
    <div class="fixed bottom-8 right-8 flex gap-4 z-50">
        <button onclick="prevSlide()" class="p-4 bg-white shadow-lg rounded-full hover:bg-gray-100 transition">
            <i class="fas fa-chevron-left text-blue-600"></i>
        </button>
        <button onclick="nextSlide()" class="p-4 bg-blue-600 shadow-lg rounded-full hover:bg-blue-700 transition">
            <i class="fas fa-chevron-right text-white"></i>
        </button>
    </div>

    <div class="fixed bottom-8 left-8 text-sm text-gray-400 font-medium">
        Slide <span id="currentSlideNum">1</span> / <span id="totalSlides">7</span>
    </div>

    <!-- Slides Container -->
    <div id="presentation">
        
        <!-- Slide 1: Title -->
        <div class="slide active bg-gradient-to-br from-blue-900 to-indigo-900 text-white">
            <div class="text-center max-w-4xl px-6">
                <div class="mb-6 inline-block p-3 bg-blue-500 rounded-2xl shadow-xl animate-bounce">
                    <i class="fas fa-eye text-4xl"></i>
                </div>
                <h1 class="text-6xl font-extrabold mb-4 tracking-tight">FORESIGHT</h1>
                <p class="text-2xl font-light mb-8 opacity-90">Building a Culture of Proactive Precision & Sustainable Stewardship</p>
                <div class="h-1 w-24 bg-blue-400 mx-auto mb-8"></div>
                <p class="text-xl italic">"Seeing the risk before it becomes a reality."</p>
                <p class="mt-4 text-lg opacity-80 font-sarabun">"มองเห็นความเสี่ยงก่อนที่จะเกิดขึ้นจริง"</p>
            </div>
        </div>

        <!-- Slide 2: Core Definition -->
        <div class="slide bg-white">
            <div class="max-w-5xl w-full">
                <h2 class="text-4xl font-bold text-gray-800 mb-12 text-center border-b pb-4">What is Foresight?</h2>
                <div class="grid md:grid-cols-2 gap-8">
                    <div class="bg-blue-50 p-8 rounded-3xl border-l-8 border-blue-600">
                        <h3 class="text-2xl font-bold text-blue-800 mb-4">Mindset</h3>
                        <p class="text-gray-700 text-lg leading-relaxed mb-4">We transition from "Fixing what broke" to "Preventing the break." We act today to secure tomorrow.</p>
                        <p class="text-gray-600 italic font-sarabun">เปลี่ยนจากการ 'ซ่อมเมื่อเสีย' เป็น 'การป้องกันก่อนจะเสีย' เราทำวันนี้เพื่อความมั่นคงในวันหน้า</p>
                    </div>
                    <div class="bg-green-50 p-8 rounded-3xl border-l-8 border-green-600">
                        <h3 class="text-2xl font-bold text-green-800 mb-4">Responsibility</h3>
                        <p class="text-gray-700 text-lg leading-relaxed mb-4">Protecting our resources, our environment, and our community for generations to come.</p>
                        <p class="text-gray-600 italic font-sarabun">ปกป้องทรัพยากร สิ่งแวดล้อม และชุมชนของเราเพื่อคนรุ่นหลัง</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 3: Pillar 1 -->
        <div class="slide bg-slate-50">
            <div class="max-w-6xl w-full">
                <div class="flex items-center mb-8 gap-4">
                    <div class="p-4 bg-blue-600 text-white rounded-xl shadow-lg">
                        <i class="fas fa-microscope text-3xl"></i>
                    </div>
                    <div>
                        <h2 class="text-4xl font-bold text-gray-800">Proactive Precision</h2>
                        <p class="text-xl text-blue-600 font-sarabun">ความแม่นยำเชิงรุก</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-md border-t-4 border-blue-500 pillar-card">
                        <i class="fas fa-tools text-blue-500 mb-4 text-2xl"></i>
                        <h4 class="font-bold text-lg mb-2">Predictive Maintenance</h4>
                        <p class="text-gray-600 text-sm">Fixing centrifugal pumps or turbines based on data before they stall.</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-md border-t-4 border-blue-500 pillar-card">
                        <i class="fas fa-check-double text-blue-500 mb-4 text-2xl"></i>
                        <h4 class="font-bold text-lg mb-2">Zero-Defect Quality</h4>
                        <p class="text-gray-600 text-sm">Monitoring juice purity in real-time to ensure perfect sugar crystals.</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-md border-t-4 border-blue-500 pillar-card">
                        <i class="fas fa-shield-alt text-blue-500 mb-4 text-2xl"></i>
                        <h4 class="font-bold text-lg mb-2">Safety Protocols</h4>
                        <p class="text-gray-600 text-sm">Identifying "near-miss" hazards during daily walks to prevent accidents.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 4: Pillar 2 -->
        <div class="slide bg-white">
            <div class="max-w-6xl w-full">
                <div class="flex items-center mb-8 gap-4">
                    <div class="p-4 bg-green-600 text-white rounded-xl shadow-lg">
                        <i class="fas fa-leaf text-3xl"></i>
                    </div>
                    <div>
                        <h2 class="text-4xl font-bold text-gray-800">Sustainable Stewardship</h2>
                        <p class="text-xl text-green-600 font-sarabun">การดูแลอย่างยั่งยืน</p>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-10">
                    <div class="space-y-6">
                        <div class="flex gap-4">
                            <div class="text-green-600 text-xl"><i class="fas fa-recycle"></i></div>
                            <div>
                                <h4 class="font-bold text-lg">Resource Optimization</h4>
                                <p class="text-gray-600">Maximizing bagasse utilization for energy; reducing chemical usage in evaporation.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="text-green-600 text-xl"><i class="fas fa-tint-slash"></i></div>
                            <div>
                                <h4 class="font-bold text-lg">Waste Reduction</h4>
                                <p class="text-gray-600">Implementing water recycling loops in the milling process to protect the water table.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 p-8 rounded-3xl flex flex-col justify-center">
                        <p class="text-xl text-green-900 font-medium italic mb-4">"Our foresight drives us to protect our environment and community for generations to come."</p>
                        <p class="text-green-700 font-sarabun italic">"วิสัยทัศน์ของเราผลักดันให้เราปกป้องสิ่งแวดล้อมและชุมชนของเราเพื่อคนรุ่นหลัง"</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 5: KPIs -->
        <div class="slide bg-slate-900 text-white">
            <div class="max-w-5xl w-full">
                <h2 class="text-4xl font-bold mb-12 text-center">Measuring Our Progress (KPIs)</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                    <div class="p-6 bg-slate-800 rounded-2xl border border-slate-700">
                        <div class="text-blue-400 text-3xl mb-2 font-bold">-15%</div>
                        <div class="text-xs uppercase tracking-widest text-slate-400">Unplanned Downtime</div>
                    </div>
                    <div class="p-6 bg-slate-800 rounded-2xl border border-slate-700">
                        <div class="text-green-400 text-3xl mb-2 font-bold">99%+</div>
                        <div class="text-xs uppercase tracking-widest text-slate-400">First-Pass Yield</div>
                    </div>
                    <div class="p-6 bg-slate-800 rounded-2xl border border-slate-700">
                        <div class="text-yellow-400 text-3xl mb-2 font-bold">-10%</div>
                        <div class="text-xs uppercase tracking-widest text-slate-400">Water Usage</div>
                    </div>
                    <div class="p-6 bg-slate-800 rounded-2xl border border-slate-700">
                        <div class="text-purple-400 text-3xl mb-2 font-bold">Max</div>
                        <div class="text-xs uppercase tracking-widest text-slate-400">Bagasse Energy</div>
                    </div>
                </div>
                <p class="mt-12 text-center text-slate-400 font-sarabun">เราจะวัดผลสำเร็จจากความพร้อมของเครื่องจักร คุณภาพผลผลิต และการใช้ทรัพยากรอย่างคุ้มค่า</p>
            </div>
        </div>

        <!-- Slide 6: Real-World Example -->
        <div class="slide bg-white">
            <div class="max-w-5xl w-full">
                <h2 class="text-4xl font-bold text-gray-800 mb-12 text-center">Foresight in Action: Sugar Mill Case Study</h2>
                <div class="flex flex-col md:flex-row gap-12 items-center">
                    <div class="md:w-1/2 bg-blue-600 p-8 rounded-3xl text-white shadow-xl">
                        <h4 class="text-2xl font-bold mb-4"><i class="fas fa-bolt mr-2"></i> Scenario: The Vibrating Turbine</h4>
                        <p class="mb-6 opacity-90">An operator notices a minor vibration in the main turbine during the peak crushing season.</p>
                        <div class="bg-blue-500 p-4 rounded-xl">
                            <p class="font-bold">Foresight Action:</p>
                            <p class="text-sm italic">Reports it immediately for a thermal sensor check. Bearing replaced during a 30-min scheduled cleaning shift.</p>
                        </div>
                    </div>
                    <div class="md:w-1/2">
                        <div class="space-y-6">
                            <div class="flex items-start gap-4">
                                <div class="bg-red-100 p-2 rounded-full text-red-600 mt-1"><i class="fas fa-times"></i></div>
                                <div>
                                    <h5 class="font-bold">The Old Way (Reactive)</h5>
                                    <p class="text-sm text-gray-500">Wait for it to break. 8 hours of downtime. Loss of juice quality due to fermentation.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="bg-green-100 p-2 rounded-full text-green-600 mt-1"><i class="fas fa-check"></i></div>
                                <div>
                                    <h5 class="font-bold">The Foresight Way (Proactive)</h5>
                                    <p class="text-sm text-gray-500 font-sarabun text-green-700">รายงานทันทีเพื่อตรวจสอบ ลดเวลาเครื่องจักรหยุดทำงาน รักษาคุณภาพน้ำอ้อยได้เต็มประสิทธิภาพ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 7: Closing -->
        <div class="slide bg-blue-900 text-white">
            <div class="text-center max-w-3xl">
                <h2 class="text-5xl font-bold mb-8">Every Role Matters</h2>
                <p class="text-xl mb-12 leading-relaxed">Whether you are on the mill floor, in the lab, or in the office—your Foresight is what keeps us moving forward.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-left mb-12">
                    <div class="bg-blue-800 p-6 rounded-2xl">
                        <h5 class="font-bold mb-2">My Daily Commitment:</h5>
                        <ul class="text-sm space-y-2 opacity-90">
                            <li>• Report anomalies immediately</li>
                            <li>• Reduce waste at my station</li>
                            <li>• Prioritize safety over speed</li>
                        </ul>
                    </div>
                    <div class="bg-blue-800 p-6 rounded-2xl font-sarabun">
                        <h5 class="font-bold mb-2">คำมั่นสัญญาของฉัน:</h5>
                        <ul class="text-sm space-y-2 opacity-90">
                            <li>• รายงานความผิดปกติทันที</li>
                            <li>• ลดของเสียในจุดงานที่รับผิดชอบ</li>
                            <li>• ให้ความสำคัญกับความปลอดภัยก่อนเสมอ</li>
                        </ul>
                    </div>
                </div>

                <div class="text-2xl font-bold text-blue-300">Let’s manufacture for the future.</div>
            </div>
        </div>

    </div>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const progressBar = document.getElementById('progressBar');
        const currentSlideDisplay = document.getElementById('currentSlideNum');
        const totalSlidesDisplay = document.getElementById('totalSlides');

        totalSlidesDisplay.innerText = slides.length;

        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.remove('active');
                if (i === index) slide.classList.add('active');
            });
            
            const progress = ((index + 1) / slides.length) * 100;
            progressBar.style.width = `${progress}%`;
            currentSlideDisplay.innerText = index + 1;
        }

        function nextSlide() {
            if (currentSlide < slides.length - 1) {
                currentSlide++;
                showSlide(currentSlide);
            }
        }

        function prevSlide() {
            if (currentSlide > 0) {
                currentSlide--;
                showSlide(currentSlide);
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === ' ') nextSlide();
            if (e.key === 'ArrowLeft') prevSlide();
        });

        // Initialize progress bar on start
        showSlide(0);
    </script>
</body>
</html>