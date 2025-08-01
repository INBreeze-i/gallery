<?php
include_once 'config/database.php';
include_once 'models/Album.php';

$database = new Database();
$db = $database->getConnection();
$album_model = new Album($db);

$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($category_id <= 0) {
    header("Location: index.php");
    exit();
}

// ดึง Albums ทั้งหมดในประเภทนี้
$albums_stmt = $album_model->getAlbumsByCategory($category_id);
$albums = [];

while ($row = $albums_stmt->fetch(PDO::FETCH_ASSOC)) {
    $images_stmt = $album_model->getAlbumImages($row['id'], 1); // เอาแค่รูปแรก
    $cover_image = $images_stmt->fetch(PDO::FETCH_ASSOC);
    
    $albums[] = [
        'album_data' => $row,
        'cover_image' => $cover_image
    ];
}

$category_name = !empty($albums) ? $albums[0]['album_data']['category_name'] : 'ไม่พบประเภท';
$category_color = !empty($albums) ? $albums[0]['album_data']['color'] : 'bg-gray-500';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category_name); ?> - Albums</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/kanit-font.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Category Header -->
        <div class="<?php echo htmlspecialchars($category_color); ?> text-white rounded-xl p-8 mb-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold mb-2"><?php echo htmlspecialchars($category_name); ?></h1>
                <p class="text-lg opacity-90">Albums ทั้งหมดในประเภทนี้</p>
                <div class="mt-4">
                    <span class="bg-white bg-opacity-20 px-4 py-2 rounded-full">
                        <i class="fas fa-images mr-2"></i><?php echo count($albums); ?> Albums
                    </span>
                </div>
            </div>
        </div>

        <!-- Albums Grid -->
        <?php if (empty($albums)): ?>
            <div class="text-center py-12">
                <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-600 mb-2">ยังไม่มี Albums</h2>
                <p class="text-gray-500">ประเภทนี้ยังไม่มี Albums ให้แสดง</p>
                <a href="index.php" class="inline-block mt-4 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    กลับหน้าหลัก
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($albums as $album_item): 
                    $album = $album_item['album_data'];
                    $cover = $album_item['cover_image'];
                ?>
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <!-- Album Cover -->
                        <div class="aspect-square overflow-hidden">
                            <img src="<?php echo $cover ? htmlspecialchars($cover['image_path']) : 'https://via.placeholder.com/300x300/E5E7EB/6B7280?text=ไม่มีรูปภาพ'; ?>" 
                                 alt="<?php echo htmlspecialchars($album['title']); ?>"
                                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                                 onerror="this.src='https://via.placeholder.com/300x300/E5E7EB/6B7280?text=ไม่มีรูปภาพ'">
                        </div>
                        
                        <!-- Album Info -->
                        <div class="p-4">
                            <h3 class="font-bold text-lg mb-2 line-clamp-2"><?php echo htmlspecialchars($album['title']); ?></h3>
                            <p class="text-gray-600 text-sm mb-3 line-clamp-2"><?php echo htmlspecialchars($album['description']); ?></p>
                            
                            <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                                <span><i class="fas fa-calendar-alt mr-1"></i><?php echo date('d/m/Y', strtotime($album['date_created'])); ?></span>
                                <span><i class="fas fa-images mr-1"></i><?php echo $album['image_count']; ?> รูป</span>
                                <span><i class="fas fa-eye mr-1"></i><?php echo number_format($album['view_count']); ?></span>
                            </div>
                            
                            <a href="album_detail.php?id=<?php echo $album['id']; ?>" 
                               class="block w-full bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-images mr-2"></i>ดู Album
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
        <div class="mt-12 text-center">
            <a href="index.php" class="inline-flex items-center bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>กลับหน้าหลัก
            </a>
        </div>
    </div>

    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>

    <!-- Footer -->
    <?php include 'components/footer.php'; ?>
</body>
</html>