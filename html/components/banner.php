<div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white">
    <div class="container mx-auto px-4 py-16">
        <div class="text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-4">
                ยินดีต้อนรับสู่โปรเจค PHP
            </h1>
            <p class="text-xl md:text-2xl mb-8 opacity-90">
                ค้นหา Albums ที่คุณสนใจ
            </p>
            
            <!-- Search Form -->
            <div class="max-w-3xl mx-auto">
                <form action="search.php" method="GET" class="bg-white/10 backdrop-blur-sm rounded-xl p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- ช่องค้นหาจากชื่อ -->
                        <div class="space-y-2">
                            <label for="search" class="block text-sm font-medium text-white/90">ค้นหาจากชื่อ Album</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   placeholder="กรอกชื่อหรือคำอธิบาย Album..." 
                                   class="w-full px-4 py-3 rounded-lg text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/50 transition-all duration-200">
                        </div>
                        
                        <!-- ช่องวันที่เริ่มต้น -->
                        <div class="space-y-2">
                            <label for="date_from" class="block text-sm font-medium text-white/90">
                                <i class="fas fa-calendar-alt mr-1"></i>วันที่เริ่มต้น
                            </label>
                            <input type="date" 
                                   id="date_from" 
                                   name="date_from" 
                                   class="w-full px-4 py-3 rounded-lg text-gray-800 focus:outline-none focus:ring-2 focus:ring-white/50 transition-all duration-200">
                        </div>
                        
                        <!-- ช่องวันที่สิ้นสุด -->
                        <div class="space-y-2">
                            <label for="date_to" class="block text-sm font-medium text-white/90">
                                <i class="fas fa-calendar-alt mr-1"></i>วันที่สิ้นสุด
                            </label>
                            <input type="date" 
                                   id="date_to" 
                                   name="date_to" 
                                   class="w-full px-4 py-3 rounded-lg text-gray-800 focus:outline-none focus:ring-2 focus:ring-white/50 transition-all duration-200">
                        </div>
                    </div>
                    
                    <!-- ปุ่มค้นหา -->
                    <div class="pt-2">
                        <button type="submit" 
                                class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors duration-200 inline-flex items-center space-x-2">
                            <i class="fas fa-search"></i>
                            <span>ค้นหา Albums</span>
                        </button>
                    </div>
                </form>
                
                <!-- หมายเหตุ -->
                <p class="text-sm text-white/75 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    สามารถค้นหาได้จากชื่อ Album หรือช่วงวันที่ หรือทั้งคู่ / ใส่เฉพาะวันที่เริ่มต้นหรือสิ้นสุดก็ได้
                </p>
            </div>
        </div>
    </div>
</div>