<?php
include_once 'config/database.php';
include_once 'models/Album.php';

$database = new Database();
$db = $database->getConnection();
$album = new Album($db);

// รับค่าจากการค้นหา
$search_query = isset($_GET['query']) ? trim($_GET['query']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// ตรวจสอบว่ามีการค้นหาหรือไม่
$has_search = !empty($search_query) || $category_id > 0 || !empty($start_date) || !empty($end_date);

$album_results = [];
$total_results = 0;

if ($has_search) {
    // ดำเนินการค้นหา
    $search_stmt = $album->searchAlbumsWithCategory($search_query, $category_id, $start_date, $end_date);
    
    while ($row = $search_stmt->fetch(PDO::FETCH_ASSOC)) {
        // ดึงรูปภาพในอัลบั้ม
        $image_stmt = $album->getAlbumImages($row['id']);
        $images = [];
        while ($image = $image_stmt->fetch(PDO::FETCH_ASSOC)) {
            $images[] = $image;
        }
        
        $album_results[] = [
            'category_name' => $row['category_name'],
            'category_color' => $row['color'],
            'category_icon' => $row['icon'],
            'album_id' => $row['id'],
            'album_title' => $row['title'],
            'album_description' => $row['description'],
            'cover_image' => $row['cover_image'],
            'date_created' => $row['date_created'],
            'view_count' => $row['view_count'],
            'image_count' => $row['image_count'],
            'images' => $images
        ];
    }
    
    $total_results = count($album_results);
}

// ดึงข้อมูลหมวดหมู่สำหรับแสดงชื่อ
$selected_category_name = '';
if ($category_id > 0) {
    $cat_query = "SELECT name FROM album_categories WHERE id = ?";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->bindParam(1, $category_id);
    $cat_stmt->execute();
    if ($cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC)) {
        $selected_category_name = $cat_row['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการค้นหา - Albums Gallery</title>
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
    
    <!-- Search Summary -->
    <?php if ($has_search): ?>
        <div class="bg-white shadow-sm border-b">
            <div class="container mx-auto px-4 py-6">
                <div class="flex flex-wrap items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 mb-2">ผลการค้นหา</h1>
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                            <?php if (!empty($search_query)): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-search mr-1"></i>
                                    คำค้นหา: "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($category_id > 0 && !empty($selected_category_name)): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-tags mr-1"></i>
                                    หมวดหมู่: "<strong><?php echo htmlspecialchars($selected_category_name); ?></strong>"
                                </span>
                            <?php elseif ($category_id == 0 && ($has_search && empty($search_query) && empty($start_date) && empty($end_date))): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-tags mr-1"></i>
                                    หมวดหมู่: "<strong>ทุกหมวดหมู่</strong>"
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($start_date) || !empty($end_date)): ?>
                                <span class="flex items-center">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    ช่วงวันที่: 
                                    <?php if (!empty($start_date)): ?>
                                        <strong><?php echo htmlspecialchars($start_date); ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($start_date) && !empty($end_date)): ?>
                                        ถึง
                                    <?php endif; ?>
                                    <?php if (!empty($end_date)): ?>
                                        <strong><?php echo htmlspecialchars($end_date); ?></strong>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (empty($search_query) && $category_id == 0 && empty($start_date) && empty($end_date)): ?>
                                <span class="flex items-center text-blue-600">
                                    <i class="fas fa-globe mr-1"></i>
                                    <strong>แสดงทั้งหมด</strong>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $total_results; ?></div>
                        <div class="text-sm text-gray-600">Albums ที่พบ</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search Results Content -->
    <div class="container mx-auto px-4 py-8">
        <?php if (!$has_search): ?>
            <!-- No Search Criteria -->
            <div class="text-center py-12">
                <i class="fas fa-search text-6xl text-gray-400 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-600 mb-4">ไม่มีการระบุเงื่อนไขการค้นหา</h2>
                <p class="text-gray-500 mb-6">กรุณากลับไปที่หน้าหลักและใช้ฟอร์มค้นหา</p>
                <a href="index.php" class="inline-flex items-center bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 font-semibold">
                    <i class="fas fa-home mr-2"></i>กลับหน้าหลัก
                </a>
            </div>
        
        <?php elseif ($total_results == 0): ?>
            <!-- No Results Found -->
            <div class="text-center py-12">
                <i class="fas fa-search-minus text-6xl text-gray-400 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-600 mb-4">ไม่พบ Albums ที่ตรงกับการค้นหา</h2>
                <p class="text-gray-500 mb-6">ลองเปลี่ยนเงื่อนไขการค้นหาหรือคำค้นหาใหม่</p>
                <div class="flex justify-center space-x-4">
                    <a href="index.php" class="inline-flex items-center bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 font-semibold">
                        <i class="fas fa-home mr-2"></i>กลับหน้าหลัก
                    </a>
                    <button id="newSearchBtn" class="inline-flex items-center bg-white text-gray-700 px-6 py-3 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors font-semibold">
                        <i class="fas fa-search mr-2"></i>ค้นหาใหม่
                    </button>
                </div>
            </div>
        
        <?php else: ?>
            <!-- Results Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($album_results as $result): ?>
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
                        
                        <!-- Album Cover -->
                        <div class="relative h-64 overflow-hidden bg-gray-200">
                            <?php if (!empty($result['cover_image'])): ?>
                                <img src="<?php echo htmlspecialchars($result['cover_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($result['album_title']); ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full text-gray-400">
                                    <i class="fas fa-image text-4xl"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Overlay with view button -->
                            <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity duration-300">
                                <a href="album_detail.php?id=<?php echo $result['album_id']; ?>" 
                                   class="bg-white text-gray-800 px-6 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-eye mr-2"></i>ดู Album
                                </a>
                            </div>
                        </div>
                        
                        <!-- Album Info -->
                        <div class="p-6">
                            <h4 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($result['album_title']); ?></h4>
                            <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($result['album_description']); ?></p>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <div class="flex items-center space-x-4">
                                    <span class="flex items-center">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        <?php echo date('d/m/Y', strtotime($result['date_created'])); ?>
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-eye mr-1"></i>
                                        <?php echo number_format($result['view_count']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Search Modal -->
    <?php include 'components/search_modal.php'; ?>

    <script>
        // New Search Button
        const newSearchBtn = document.getElementById('newSearchBtn');
        if (newSearchBtn) {
            newSearchBtn.addEventListener('click', function() {
                document.getElementById('searchModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
        }
    </script>
</body>
</html>