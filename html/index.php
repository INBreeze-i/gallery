<?php
include_once 'config/database.php';
include_once 'models/Album.php';

$database = new Database();
$db = $database->getConnection();
$album = new Album($db);

// ดึง Albums ล่าสุดของแต่ละประเภท
$albums = $album->getLatestAlbumsByCategory();
$album_cards = [];

while ($row = $albums->fetch(PDO::FETCH_ASSOC)) {
    if ($row['album_id']) { // ตรวจสอบว่ามี album หรือไม่
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
        
        $album_cards[] = [
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
    <title>Albums Gallery - โปรเจค PHP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            font-family: 'Kanit', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <?php include 'components/navbar.php'; ?>
    
    <!-- Banner -->
    <?php include 'components/banner.php'; ?>
    
    <!-- Albums Section -->
    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">Albums Gallery</h2>
            <p class="text-gray-600 text-lg">สำรวจ Albums ของกิจกรรมต่างๆ ในหมวดหมู่ที่หลากหลาย</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($album_cards as $card): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                    <!-- Category Header -->
                    <div class="<?php echo htmlspecialchars($card['category_color']); ?> text-white p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <i class="<?php echo htmlspecialchars($card['category_icon']); ?> text-xl"></i>
                                <div>
                                    <h3 class="text-lg font-bold"><?php echo htmlspecialchars($card['category_name']); ?></h3>
                                    <p class="text-sm opacity-90"><?php echo htmlspecialchars($card['album_title']); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs opacity-75">รูปภาพ</div>
                                <div class="text-lg font-bold"><?php echo $card['image_count']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Album Images Grid -->
                    <div class="grid grid-cols-2 gap-2 p-4">
                        <?php 
                                $placeholder_images = [
                                    'https://picsum.photos/200/150?random=1',  // รูปจาก Lorem Picsum
                                    'https://picsum.photos/200/150?random=2',
                                    'https://picsum.photos/200/150?random=3', 
                                    'https://picsum.photos/200/150?random=4'
                                ];
                        
                        for ($i = 0; $i < 4; $i++): 
                            $image_data = isset($card['images'][$i]) ? $card['images'][$i] : null;
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
                                <?php echo date('d/m/Y', strtotime($card['date_created'])); ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-eye mr-1"></i>
                                <?php echo number_format($card['view_count']); ?> ครั้ง
                            </span>
                        </div>
                        
                        <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                            <?php echo htmlspecialchars($card['album_description']); ?>
                        </p>
                        
                        <div class="flex space-x-2">
                            <a href="album_detail.php?id=<?php echo $card['album_id']; ?>" 
                               class="flex-1 bg-blue-600 text-white text-center px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                <i class="fas fa-images mr-2"></i>ดู Album
                            </a>
                            <a href="category.php?id=<?php echo $card['category_id']; ?>" 
                               class="bg-gray-200 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-300 transition-colors text-sm">
                                <i class="fas fa-th-large"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- View All Categories Button -->
        <div class="text-center mt-12">
            <a href="categories.php" class="inline-flex items-center bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-3 rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 font-semibold">
                <i class="fas fa-th-large mr-2"></i>
                ดูประเภท Albums ทั้งหมด
            </a>
        </div>
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