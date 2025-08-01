<?php
include_once 'config/database.php';
include_once 'models/Album.php';

$database = new Database();
$db = $database->getConnection();
$album_model = new Album($db);

$album_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($album_id <= 0) {
    header("Location: index.php");
    exit();
}

// เพิ่มจำนวนการดู
$album_model->incrementViewCount($album_id);

// ดึงข้อมูล Album
$album_stmt = $album_model->getAlbumById($album_id);
$album = $album_stmt->fetch(PDO::FETCH_ASSOC);

if (!$album) {
    header("Location: index.php");
    exit();
}

// ดึงรูปภาพทั้งหมดใน Album
$images_stmt = $album_model->getAlbumImages($album_id, 100); // ดึงทั้งหมด
$images = [];
while ($img = $images_stmt->fetch(PDO::FETCH_ASSOC)) {
    $images[] = $img;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($album['title']); ?> - Album Detail</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/kanit-font.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <nav class="mb-6">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li><a href="index.php" class="hover:text-blue-600">หน้าหลัก</a></li>
                <li><i class="fas fa-chevron-right"></i></li>
                <li><a href="category.php?id=<?php echo $album['category_id']; ?>" class="hover:text-blue-600"><?php echo htmlspecialchars($album['category_name']); ?></a></li>
                <li><i class="fas fa-chevron-right"></i></li>
                <li class="text-gray-800"><?php echo htmlspecialchars($album['title']); ?></li>
            </ol>
        </nav>

        <!-- Album Header -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
            <div class="<?php echo htmlspecialchars($album['color']); ?> text-white p-6">
                <div class="flex items-center space-x-4">
                    <i class="<?php echo htmlspecialchars($album['icon']); ?> text-3xl"></i>
                    <div>
                        <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($album['title']); ?></h1>
                        <p class="text-lg opacity-90"><?php echo htmlspecialchars($album['category_name']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <div class="flex flex-wrap items-center justify-between mb-4">
                    <div class="flex items-center space-x-6 text-sm text-gray-600">
                        <span><i class="fas fa-calendar-alt mr-2"></i><?php echo date('d/m/Y', strtotime($album['date_created'])); ?></span>
                        <span><i class="fas fa-images mr-2"></i><?php echo count($images); ?> รูปภาพ</span>
                        <span><i class="fas fa-eye mr-2"></i><?php echo number_format($album['view_count']); ?> ครั้ง</span>
                    </div>
                    <button onclick="shareAlbum()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-share mr-2"></i>แชร์
                    </button>
                </div>
                
                <p class="text-gray-600 leading-relaxed"><?php echo htmlspecialchars($album['description']); ?></p>
            </div>
        </div>

        <!-- Images Gallery -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">รูปภาพใน Album</h2>
            
            <?php if (empty($images)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-images text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">ยังไม่มีรูปภาพใน Album นี้</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($images as $index => $image): ?>
                        <div class="group cursor-pointer" onclick="openLightbox(<?php echo $index; ?>)">
                            <div class="aspect-square overflow-hidden rounded-lg shadow-md">
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($image['image_title']); ?>"
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                     onerror="this.src='https://via.placeholder.com/300x300/E5E7EB/6B7280?text=ไม่พบรูปภาพ'">
                            </div>
                            
                            <?php if ($image['image_title']): ?>
                                <div class="mt-2">
                                    <h3 class="font-medium text-gray-800 text-sm truncate"><?php echo htmlspecialchars($image['image_title']); ?></h3>
                                    <?php if ($image['image_description']): ?>
                                        <p class="text-gray-600 text-xs mt-1 line-clamp-2"><?php echo htmlspecialchars($image['image_description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Back Button -->
        <div class="mt-8 text-center">
            <a href="category.php?id=<?php echo $album['category_id']; ?>" 
               class="inline-flex items-center bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                กลับไปยังประเภท <?php echo htmlspecialchars($album['category_name']); ?>
            </a>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div id="lightbox" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center">
        <div class="relative max-w-4xl max-h-full p-4">
            <button onclick="closeLightbox()" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
            
            <img id="lightbox-image" src="" alt="" class="max-w-full max-h-full object-contain">
            
            <div class="absolute bottom-4 left-4 right-4 text-white">
                <h3 id="lightbox-title" class="text-lg font-semibold mb-2"></h3>
                <p id="lightbox-description" class="text-sm opacity-90"></p>
            </div>
            
            <!-- Navigation arrows -->
            <button onclick="previousImage()" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white text-3xl hover:text-gray-300">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button onclick="nextImage()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white text-3xl hover:text-gray-300">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <script>
        const images = <?php echo json_encode($images); ?>;
        let currentImageIndex = 0;

        function openLightbox(index) {
            currentImageIndex = index;
            showLightboxImage();
            document.getElementById('lightbox').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightbox').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function showLightboxImage() {
            const image = images[currentImageIndex];
            document.getElementById('lightbox-image').src = image.image_path;
            document.getElementById('lightbox-title').textContent = image.image_title || '';
            document.getElementById('lightbox-description').textContent = image.image_description || '';
        }

        function previousImage() {
            currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
            showLightboxImage();
        }

        function nextImage() {
            currentImageIndex = (currentImageIndex + 1) % images.length;
            showLightboxImage();
        }

        function shareAlbum() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars($album['title']); ?>',
                    text: '<?php echo htmlspecialchars($album['description']); ?>',
                    url: window.location.href
                });
            } else {
                // Fallback: copy URL to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('คัดลอกลิงก์แล้ว!');
                });
            }
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (!document.getElementById('lightbox').classList.contains('hidden')) {
                if (e.key === 'Escape') closeLightbox();
                if (e.key === 'ArrowLeft') previousImage();
                if (e.key === 'ArrowRight') nextImage();
            }
        });
    </script>

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