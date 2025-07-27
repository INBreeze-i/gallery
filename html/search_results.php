<?php
include_once 'config/database.php';
include_once 'models/Album.php';

$database = new Database();
$db = $database->getConnection();
$album = new Album($db);

// รับค่าจากการค้นหา
$search_query = isset($_GET['query']) ? trim($_GET['query']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// ตรวจสอบว่ามีการค้นหาหรือไม่
$has_search = !empty($search_query) || !empty($start_date) || !empty($end_date);

$album_results = [];
$total_results = 0;

if ($has_search) {
    // ดำเนินการค้นหา
    $search_stmt = $album->searchAlbums($search_query, $start_date, $end_date);
    
    while ($row = $search_stmt->fetch(PDO::FETCH_ASSOC)) {
        // ดึงรูปภาพ 4 รูปแรกของแต่ละ album
        $images_stmt = $album->getAlbumImages($row['id'], 4);
        $images = [];
        while ($img = $images_stmt->fetch(PDO::FETCH_ASSOC)) {
            $images[] = [
                'path' => $img['image_path'],
                'title' => $img['image_title'],
                'description' => $img['image_description'],
                'is_cover' => $img['is_cover']
            ];
        }
        
        $album_results[] = [
            'id' => $row['id'],
            'category_name' => $row['category_name'],
            'category_color' => $row['color'],
            'category_icon' => $row['icon'],
            'title' => $row['title'],
            'description' => $row['description'],
            'cover_image' => $row['cover_image'],
            'date_created' => $row['date_created'],
            'view_count' => $row['view_count'],
            'image_count' => $row['image_count'],
            'images' => $images
        ];
    }
    
    $total_results = count($album_results);
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
    
    <!-- Search Results Header -->
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <h1 class="text-3xl md:text-4xl font-bold mb-4">
                    <i class="fas fa-search mr-2"></i>ผลการค้นหา Albums
                </h1>
                <?php if ($has_search): ?>
                    <div class="text-lg opacity-90">
                        <?php if (!empty($search_query)): ?>
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full mr-2">
                                <i class="fas fa-font mr-1"></i>"<?php echo htmlspecialchars($search_query); ?>"
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($start_date) || !empty($end_date)): ?>
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <?php 
                                if (!empty($start_date) && !empty($end_date)) {
                                    echo date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date));
                                } elseif (!empty($start_date)) {
                                    echo 'ตั้งแต่ ' . date('d/m/Y', strtotime($start_date));
                                } elseif (!empty($end_date)) {
                                    echo 'ถึง ' . date('d/m/Y', strtotime($end_date));
                                }
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-3 text-sm opacity-75">
                        พบ <?php echo $total_results; ?> Albums
                    </p>
                <?php else: ?>
                    <p class="text-lg opacity-90">กรุณาระบุเงื่อนไขการค้นหา</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
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
                    <button onclick="window.history.back()" class="inline-flex items-center bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                        <i class="fas fa-arrow-left mr-2"></i>ย้อนกลับ
                    </button>
                </div>
            </div>
        
        <?php else: ?>
            <!-- Search Results -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($album_results as $album_data): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <!-- Category Header -->
                        <div class="<?php echo htmlspecialchars($album_data['category_color']); ?> text-white p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <i class="<?php echo htmlspecialchars($album_data['category_icon']); ?> text-xl"></i>
                                    <div>
                                        <h3 class="text-lg font-bold"><?php echo htmlspecialchars($album_data['category_name']); ?></h3>
                                        <p class="text-sm opacity-90"><?php echo htmlspecialchars($album_data['title']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs opacity-75">รูปภาพ</div>
                                    <div class="text-lg font-bold"><?php echo $album_data['image_count']; ?></div>
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
                                $image_data = isset($album_data['images'][$i]) ? $album_data['images'][$i] : null;
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
                                    <?php echo date('d/m/Y', strtotime($album_data['date_created'])); ?>
                                </span>
                                <span class="text-sm text-gray-500">
                                    <i class="fas fa-eye mr-1"></i>
                                    <?php echo number_format($album_data['view_count']); ?> ครั้ง
                                </span>
                            </div>
                            
                            <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                                <?php echo htmlspecialchars($album_data['description']); ?>
                            </p>
                            
                            <div class="flex space-x-2">
                                <a href="album_detail.php?id=<?php echo $album_data['id']; ?>" 
                                   class="flex-1 bg-blue-600 text-white text-center px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                    <i class="fas fa-images mr-2"></i>ดู Album
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Back to Main Button -->
            <div class="text-center mt-12">
                <a href="index.php" class="inline-flex items-center bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-3 rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 font-semibold">
                    <i class="fas fa-home mr-2"></i>กลับหน้าหลัก
                </a>
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
</body>
</html>