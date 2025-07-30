<nav class="bg-blue-600 shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <!-- Logo -->
                <a href="index.php" class="text-white hover:text-blue-200 transition-colors h1">KPRU PHOTOS</a>
            
            <!-- Navigation Links -->
            <div class="hidden md:flex space-x-6">
                <a href="category.php?id=1" class="text-white hover:text-blue-200 transition-colors">ผลิตบัณฑิต</a>
                <a href="category.php?id=2" class="text-white hover:text-blue-200 transition-colors">การบริการวิชาการ</a>
                <a href="category.php?id=3" class="text-white hover:text-blue-200 transition-colors">ผลิตและพัฒนาครู</a>
                <a href="category.php?id=4" class="text-white hover:text-blue-200 transition-colors">การวิจัย</a>
                <a href="category.php?id=5" class="text-white hover:text-blue-200 transition-colors">ศิลปะและวัฒนธรรม</a>
                <a href="category.php?id=6" class="text-white hover:text-blue-200 transition-colors">การบริหารจัดการ</a>
            </div>
            
            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button id="mobile-menu-btn" class="text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden pb-4">
            <a href="index.php" class="block text-white py-2 hover:text-blue-200">หน้าหลัก</a>
            <a href="pages/about.php" class="block text-white py-2 hover:text-blue-200">เกี่ยวกับเรา</a>
            <a href="pages/services.php" class="block text-white py-2 hover:text-blue-200">บริการ</a>
            <a href="pages/portfolio.php" class="block text-white py-2 hover:text-blue-200">ผลงาน</a>
            <a href="pages/blog.php" class="block text-white py-2 hover:text-blue-200">บล็อก</a>
            <a href="pages/contact.php" class="block text-white py-2 hover:text-blue-200">ติดต่อเรา</a>
        </div>
    </div>
</nav>

<script>
    document.getElementById('mobile-menu-btn').addEventListener('click', function() {
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenu.classList.toggle('hidden');
    });
</script>
