<!-- Search Modal -->
<div id="searchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-96 overflow-y-auto">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6 rounded-t-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold">
                    <i class="fas fa-search mr-2"></i>ค้นหา Albums
                </h3>
                <button id="closeModalBtn" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Modal Body -->
        <form id="searchForm" action="search_results.php" method="GET" class="p-6">
            <!-- ช่องค้นหาชื่อ -->
            <div class="mb-4">
                <label for="searchQuery" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-font mr-1"></i>ชื่อ Album หรือคำอธิบาย
                </label>
                <input type="text" 
                       id="searchQuery" 
                       name="query" 
                       placeholder="ใส่ชื่อ Album หรือคำที่ต้องการค้นหา..."
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <!-- ช่องเลือกหมวดหมู่ -->
            <div class="mb-4">
                <label for="categorySelect" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-tags mr-1"></i>หมวดหมู่
                </label>
                <select id="categorySelect" 
                        name="category_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="0">-- ทุกหมวดหมู่ --</option>
                    <?php
                    // ดึงข้อมูลหมวดหมู่จากฐานข้อมูล
                    include_once 'config/database.php';
                    $database = new Database();
                    $db = $database->getConnection();
                    
                    $category_query = "SELECT id, name, icon, color FROM album_categories ORDER BY name";
                    $category_stmt = $db->prepare($category_query);
                    $category_stmt->execute();
                    
                    while ($category = $category_stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value="' . htmlspecialchars($category['id']) . '">';
                        echo htmlspecialchars($category['name']);
                        echo '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <!-- ช่องเลือกวันที่เริ่ม -->
            <div class="mb-4">
                <label for="startDate" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-calendar-alt mr-1"></i>วันที่เริ่มต้น
                </label>
                <input type="date" 
                       id="startDate" 
                       name="start_date"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <!-- ช่องเลือกวันที่สิ้นสุด -->
            <div class="mb-6">
                <label for="endDate" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-calendar-alt mr-1"></i>วันที่สิ้นสุด
                </label>
                <input type="date" 
                       id="endDate" 
                       name="end_date"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <!-- ปุ่มควบคุม -->
            <div class="flex space-x-3">
                <button type="submit" 
                        class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-2 rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 font-semibold">
                    <i class="fas fa-search mr-2"></i>ค้นหา
                </button>
                <button type="button" 
                        id="clearFormBtn"
                        class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-eraser mr-2"></i>ล้าง
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for Modal Control -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchModal = document.getElementById('searchModal');
    const searchModalBtn = document.getElementById('searchModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const clearFormBtn = document.getElementById('clearFormBtn');
    const searchForm = document.getElementById('searchForm');

    // เปิด Modal
    if (searchModalBtn) {
        searchModalBtn.addEventListener('click', function() {
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    // ปิด Modal
    function closeModal() {
        searchModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }
    
    // ปิด Modal เมื่อคลิกพื้นหลัง
    searchModal.addEventListener('click', function(e) {
        if (e.target === searchModal) {
            closeModal();
        }
    });
    
    // ปิด Modal ด้วย ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !searchModal.classList.contains('hidden')) {
            closeModal();
        }
    });
    
    // ล้างข้อมูลในฟอร์ม
    if (clearFormBtn) {
        clearFormBtn.addEventListener('click', function() {
            searchForm.reset();
        });
    }
    
    // ตรวจสอบว่าวันที่สิ้นสุดไม่เก่ากว่าวันที่เริ่มต้น
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    startDate.addEventListener('change', function() {
        if (endDate.value && endDate.value < startDate.value) {
            endDate.value = startDate.value;
        }
        endDate.min = startDate.value;
    });
    
    endDate.addEventListener('change', function() {
        if (startDate.value && endDate.value < startDate.value) {
            startDate.value = endDate.value;
        }
        startDate.max = endDate.value;
    });
});
</script>