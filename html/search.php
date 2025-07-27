<?php
include_once 'config/database.php';
include_once 'models/Album.php';

$database = new Database();
$db = $database->getConnection();
$album = new Album($db);

// รับค่าจากฟอร์มค้นหา
$search_name = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// รองรับพารามิเตอร์เก่า (date) สำหรับ backward compatibility
if (empty($date_from) && empty($date_to) && isset($_GET['date']) && !empty(trim($_GET['date']))) {
    $date_from = trim($_GET['date']);
    $date_to = trim($_GET['date']);
}

// ตรวจสอบว่ามีการค้นหาหรือไม่
$has_search = !empty($search_name) || !empty($date_from) || !empty($date_to);

$search_results = [];
$total_results = 0;

if ($has_search) {
    // ทำการค้นหา
    $search_stmt = $album->searchAlbums($search_name, $date_from, $date_to);
    $total_results = $album->countSearchResults($search_name, $date_from, $date_to);
    
    while ($row = $search_stmt->fetch(PDO::FETCH_ASSOC)) {
        // ดึงรูปภาพตัวอย่าง 4 รูปแรก
        $images_stmt = $album->getAlbumImages($row['album_id'], 4);
        $images = [];
        while ($img = $images_stmt->fetch(PDO::FETCH_ASSOC)) {
            $images[] = [
                'path' => $img['image_path'],
                'title' => $img['image_title'],
                'description' => $img['image_description'],
                'is_cover' => $img['is_cover']
            ];
        }
        
        $search_results[] = [
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'category_description' => $row['category_description'],
            'category_icon' => $row['icon'],
            'category_color' => $row['color'],
            'album_id' => $row['album_id'],
            'album_title' => $row['album_title'],
            'album_description' => $row['album_description'],
            'cover_image' => $row['cover_image'],
            'date_created' => $row['date_created'],
            'view_count' => $row['view_count'],
            'image_count' => $row['image_count'],
            'images' => $images
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการค้นหา Albums - โปรเจค PHP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/kanit-font.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <?php include 'components/navbar.php'; ?>
    
    <!-- Search Header -->
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <h1 class="text-3xl md:text-4xl font-bold mb-4">
                    <i class="fas fa-search mr-3"></i>ผลการค้นหา Albums
                </h1>
                
                <!-- แสดงเงื่อนไขการค้นหา -->
                <?php if ($has_search): ?>
                    <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 max-w-2xl mx-auto">
                        <p class="text-lg mb-2">เงื่อนไขการค้นหา:</p>
                        <div class="flex flex-wrap justify-center gap-4 text-sm">
                            <?php if (!empty($search_name)): ?>
                                <span class="bg-white/20 px-3 py-1 rounded-full">
                                    <i class="fas fa-tag mr-1"></i>ชื่อ: "<?php echo htmlspecialchars($search_name); ?>"
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($date_from) || !empty($date_to)): ?>
                                <span class="bg-white/20 px-3 py-1 rounded-full">
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?php 
                                    if (!empty($date_from) && !empty($date_to)) {
                                        if ($date_from === $date_to) {
                                            echo "วันที่: " . date('d/m/Y', strtotime($date_from));
                                        } else {
                                            echo "ช่วงวันที่: " . date('d/m/Y', strtotime($date_from)) . " - " . date('d/m/Y', strtotime($date_to));
                                        }
                                    } elseif (!empty($date_from)) {
                                        echo "ตั้งแต่: " . date('d/m/Y', strtotime($date_from));
                                    } elseif (!empty($date_to)) {
                                        echo "จนถึง: " . date('d/m/Y', strtotime($date_to));
                                    }
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Search Form (ซ้ำจากหน้าแรก สำหรับค้นหาใหม่) -->
    <div class="bg-white shadow-sm border-b">
        <div class="container mx-auto px-4 py-6">
            <form action="search.php" method="GET" class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- ช่องค้นหาจากชื่อ -->
                    <div class="space-y-2">
                        <label for="search" class="block text-sm font-medium text-gray-700">ค้นหาจากชื่อ Album</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search_name); ?>"
                               placeholder="กรอกชื่อหรือคำอธิบาย Album..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                    </div>
                    
                    <!-- ช่องวันที่เริ่มต้น -->
                    <div class="space-y-2">
                        <label for="date_from" class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-calendar mr-1"></i>วันที่เริ่มต้น
                        </label>
                        <input type="date" 
                               id="date_from" 
                               name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>"
                               placeholder="วันที่เริ่มต้น"
                               onchange="validateDateRangeSearch()"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                    </div>
                    
                    <!-- ช่องวันที่สิ้นสุด -->
                    <div class="space-y-2">
                        <label for="date_to" class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-calendar mr-1"></i>วันที่สิ้นสุด
                        </label>
                        <input type="date" 
                               id="date_to" 
                               name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>"
                               placeholder="วันที่สิ้นสุด"
                               onchange="validateDateRangeSearch()"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                    </div>
                    
                    <!-- ปุ่มค้นหา -->
                    <div class="flex items-end">
                        <button type="submit" 
                                class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 inline-flex items-center justify-center space-x-2">
                            <i class="fas fa-search"></i>
                            <span>ค้นหา</span>
                        </button>
                    </div>
                </div>
                
                <!-- หมายเหตุ -->
                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        สามารถค้นหาด้วยชื่อ Album หรือช่วงวันที่ หรือทั้งคู่ / ใส่เฉพาะวันที่เริ่มต้นหรือสิ้นสุดก็ได้
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Search Results -->
    <div class="container mx-auto px-4 py-8">
        <?php if ($has_search): ?>
            <!-- แสดงจำนวนผลลัพธ์ -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-gray-800">
                        พบ <?php echo number_format($total_results); ?> Albums
                    </h2>
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors">
                        <i class="fas fa-arrow-left mr-1"></i>กลับหน้าแรก
                    </a>
                </div>
                <hr class="mt-4 mb-6">
            </div>

            <?php if ($total_results > 0): ?>
                <!-- แสดงผลลัพธ์ -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($search_results as $result): ?>
                        <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                            <!-- Category Header -->
                            <div class="<?php echo htmlspecialchars($result['category_color']); ?> text-white p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="<?php echo htmlspecialchars($result['category_icon']); ?> text-xl"></i>
                                        <div>
                                            <h3 class="text-lg font-bold"><?php echo htmlspecialchars($result['category_name']); ?></h3>
                                            <p class="text-sm opacity-90"><?php echo htmlspecialchars($result['album_title']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs opacity-75">รูปภาพ</div>
                                        <div class="text-lg font-bold"><?php echo $result['image_count']; ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Album Images Grid -->
                            <div class="grid grid-cols-2 gap-2 p-4">
                                <?php 
                                $placeholder_images = [
                                    'https://picsum.photos/200/150?random=1',
                                    'https://picsum.photos/200/150?random=2',
                                    'https://picsum.photos/200/150?random=3', 
                                    'https://picsum.photos/200/150?random=4'
                                ];
                        
                                for ($i = 0; $i < 4; $i++): 
                                    $image_data = isset($result['images'][$i]) ? $result['images'][$i] : null;
                                    $image_src = $image_data ? $image_data['path'] : $placeholder_images[$i];
                                    $image_title = $image_data ? $image_data['title'] : "รูปที่ " . ($i + 1);
                                ?>
                                    <div class="aspect-square overflow-hidden rounded-lg relative group">
                                        <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                             alt="<?php echo htmlspecialchars($image_title); ?>"
                                             class="w-full h-full object-cover hover:scale-110 transition-transform duration-300"
                                             onerror="this.src='<?php echo $placeholder_images[$i]; ?>'">
                                        
                                        <!-- Image Overlay -->
                                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center">
                                            <i class="fas fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200"></i>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <!-- Album Info -->
                            <div class="p-4 border-t bg-gray-50">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-500">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        <?php echo date('d/m/Y', strtotime($result['date_created'])); ?>
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        <i class="fas fa-eye mr-1"></i>
                                        <?php echo number_format($result['view_count']); ?> ครั้ง
                                    </span>
                                </div>
                                
                                <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                                    <?php echo htmlspecialchars($result['album_description']); ?>
                                </p>
                                
                                <div class="flex space-x-2">
                                    <a href="album_detail.php?id=<?php echo $result['album_id']; ?>" 
                                       class="flex-1 bg-blue-600 text-white text-center px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                        <i class="fas fa-images mr-2"></i>ดู Album
                                    </a>
                                    <a href="category.php?id=<?php echo $result['category_id']; ?>" 
                                       class="bg-gray-200 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-300 transition-colors text-sm">
                                        <i class="fas fa-th-large"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- ไม่พบผลลัพธ์ -->
                <div class="text-center py-16">
                    <div class="max-w-md mx-auto">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-search text-6xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-600 mb-4">ไม่พบ Albums ที่ตรงกับการค้นหา</h3>
                        <p class="text-gray-500 mb-6">
                            ลองเปลี่ยนคำค้นหาหรือปรับเงื่อนไขการค้นหาใหม่
                        </p>
                        <div class="space-y-3">
                            <button onclick="document.getElementById('search').focus()" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-search mr-2"></i>ค้นหาใหม่
                            </button>
                            <br>
                            <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors">
                                <i class="fas fa-home mr-1"></i>กลับหน้าแรก
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- ยังไม่มีการค้นหา -->
            <div class="text-center py-16">
                <div class="max-w-md mx-auto">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-search text-6xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-600 mb-4">ค้นหา Albums</h3>
                    <p class="text-gray-500 mb-6">
                        กรอกข้อมูลการค้นหาด้านบนเพื่อค้นหา Albums ที่คุณสนใจ
                    </p>
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors">
                        <i class="fas fa-home mr-1"></i>กลับหน้าแรก
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Custom CSS for line-clamp -->
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>

    <!-- Date Range Validation Script -->
    <script>
    function validateDateRangeSearch() {
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        
        if (dateFrom.value && dateTo.value) {
            const fromDate = new Date(dateFrom.value);
            const toDate = new Date(dateTo.value);
            
            if (fromDate > toDate) {
                alert('วันที่เริ่มต้นต้องไม่มากกว่าวันที่สิ้นสุด');
                dateTo.value = '';
                dateTo.focus();
            }
        }
    }
    </script>
</body>
</html>