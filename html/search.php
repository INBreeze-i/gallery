<?php
include_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';

// Query สำหรับค้นหา
$query = "SELECT a.*, c.name as category_name, 
          COUNT(ai.id) as image_count 
          FROM albums a 
          LEFT JOIN album_categories c ON a.category_id = c.id 
          LEFT JOIN album_images ai ON a.id = ai.album_id 
          WHERE a.status = 'active'";

$params = [];

if (!empty($search)) {
    $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($date)) {
    $query .= " AND DATE(a.date_created) = ?";
    $params[] = $date;
}

$query .= " GROUP BY a.id ORDER BY a.date_created DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการค้นหา - PHP Gallery</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/kanit-font.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- ผลการค้นหา -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-search mr-2 text-blue-600"></i>ผลการค้นหา
            </h2>
            
            <div class="text-gray-600 mb-4">
                <?php if (!empty($search) || !empty($date)): ?>
                    <p>ค้นหา: 
                        <?php if (!empty($search)): ?>
                            <span class="font-semibold"><?php echo htmlspecialchars($search); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($date)): ?>
                            <span class="font-semibold">วันที่: <?php echo htmlspecialchars($date); ?></span>
                        <?php endif; ?>
                    </p>
                    <p>พบ <span class="font-semibold"><?php echo count($albums); ?></span> รายการ</p>
                <?php else: ?>
                    <p>กรุณาระบุคำค้นหาหรือวันที่</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($albums)): ?>
            <!-- แสดงผลลัพธ์การค้นหา -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($albums as $album): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="p-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                <?php echo htmlspecialchars($album['title']); ?>
                            </h3>
                            <p class="text-gray-600 text-sm mb-3">
                                <?php echo htmlspecialchars($album['description']); ?>
                            </p>
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <span>
                                    <i class="fas fa-images mr-1"></i>
                                    <?php echo $album['image_count']; ?> รูป
                                </span>
                                <span>
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?php echo date('d/m/Y', strtotime($album['date_created'])); ?>
                                </span>
                            </div>
                            <div class="mt-4">
                                <a href="album_detail.php?id=<?php echo $album['id']; ?>" 
                                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                    <i class="fas fa-eye mr-2"></i>ดู Album
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($search) || !empty($date)): ?>
            <!-- ไม่พบผลลัพธ์ -->
            <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">ไม่พบผลการค้นหา</h3>
                <p class="text-gray-500 mb-4">ลองเปลี่ยนคำค้นหาหรือวันที่ใหม่</p>
                <a href="index.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-home mr-2"></i>กลับหน้าหลัก
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include 'components/footer.php'; ?>
</body>
</html>