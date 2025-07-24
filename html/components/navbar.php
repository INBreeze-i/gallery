<nav class="bg-blue-600 shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <!-- Logo -->
            <div class="text-white text-xl font-bold">
                โปรเจค PHP
            </div>
            
            <!-- Navigation Links -->
            <div class="hidden md:flex space-x-6">
                <a href="../index.php" class="text-white hover:text-blue-200 transition-colors">หน้าหลัก</a>
                <a href="pages/about.php" class="text-white hover:text-blue-200 transition-colors">เกี่ยวกับเรา</a>
                <a href="pages/services.php" class="text-white hover:text-blue-200 transition-colors">บริการ</a>
                <a href="pages/portfolio.php" class="text-white hover:text-blue-200 transition-colors">ผลงาน</a>
                <a href="pages/blog.php" class="text-white hover:text-blue-200 transition-colors">บล็อก</a>
                <a href="pages/contact.php" class="text-white hover:text-blue-200 transition-colors">ติดต่อเรา</a>
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