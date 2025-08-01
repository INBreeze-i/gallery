<?php
include_once '../config/database.php';
include_once '../models/Album.php';
include_once 'Auth.php';
include_once 'ImageHandler.php';
include_once 'CSRFProtection.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// ตรวจสอบสิทธิ์การเข้าถึง
$auth->requireLogin();

$current_user = $auth->getCurrentUser();
$imageHandler = new ImageHandler();

// ดึง album_id
$album_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($album_id <= 0) {
    header("Location: album_manage.php");
    exit();
}

// ดึงข้อมูล album
$album_query = "SELECT a.*, c.name as category_name, c.color as category_color, u.full_name as created_by_name
                FROM albums a 
                JOIN album_categories c ON a.category_id = c.id 
                LEFT JOIN admin_users u ON a.created_by = u.id
                WHERE a.id = ?";
$album_stmt = $db->prepare($album_query);
$album_stmt->execute([$album_id]);
$album = $album_stmt->fetch(PDO::FETCH_ASSOC);

if (!$album) {
    header("Location: album_manage.php");
    exit();
}

// ตรวจสอบสิทธิ์ในการแก้ไข
if ($current_user['role'] !== 'admin' && $album['created_by'] != $current_user['id']) {
    header("Location: album_manage.php?error=no_permission");
    exit();
}

// ดึงรายการหมวดหมู่
$categories_query = "SELECT * FROM album_categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงรูปภาพใน album
$images_query = "SELECT * FROM album_images WHERE album_id = ? ORDER BY image_order, id";
$images_stmt = $db->prepare($images_query);
$images_stmt->execute([$album_id]);
$images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

$success = false;
$errors = [];

// จัดการการส่งข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ตรวจสอบ CSRF token
    if (!isset($_POST['csrf_token']) || !CSRFProtection::validateToken($_POST['csrf_token'], $_POST['action'] ?? 'default')) {
        $errors[] = "การยืนยันความปลอดภัยล้มเหลว กรุณารีเฟรชหน้าและลองใหม่";
    } else {
        if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_album':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $category_id = (int)$_POST['category_id'];
                $status = $_POST['status'];
                $date_created = $_POST['date_created'];
                
                // Validation
                if (empty($title)) $errors[] = "กรุณากรอกชื่อ Album";
                if (empty($description)) $errors[] = "กรุณากรอกรายละเอียด";
                if ($category_id <= 0) $errors[] = "กรุณาเลือกหมวดหมู่";
                if (!in_array($status, ['active', 'inactive'])) $errors[] = "สถานะไม่ถูกต้อง";
                if (empty($date_created)) $errors[] = "กรุณาเลือกวันที่";
                
                if (empty($errors)) {
                    try {
                        $update_query = "UPDATE albums SET 
                                        title = ?, description = ?, category_id = ?, 
                                        status = ?, date_created = ?, updated_at = NOW() 
                                        WHERE id = ?";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->execute([$title, $description, $category_id, $status, $date_created, $album_id]);
                        
                        $success = true;
                        $success_message = "อัปเดต Album สำเร็จ!";
                        
                        // รีเฟรชข้อมูล album
                        $album_stmt->execute([$album_id]);
                        $album = $album_stmt->fetch(PDO::FETCH_ASSOC);
                        
                    } catch (PDOException $e) {
                        $errors[] = "เกิดข้อผิดพลาดในการอัปเดต: " . $e->getMessage();
                    }
                }
                break;
                
            case 'upload_images':
                $uploaded_count = 0;
                $upload_errors = [];
                
                if (!empty($_FILES['new_images']['name'][0])) {
                    for ($i = 0; $i < count($_FILES['new_images']['name']); $i++) {
                        if ($_FILES['new_images']['error'][$i] == UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['new_images']['name'][$i],
                                'type' => $_FILES['new_images']['type'][$i],
                                'tmp_name' => $_FILES['new_images']['tmp_name'][$i],
                                'size' => $_FILES['new_images']['size'][$i],
                                'error' => $_FILES['new_images']['error'][$i]
                            ];
                            
                            $upload_result = $imageHandler->uploadImage($file, $album_id);
                            
                            if ($upload_result['success']) {
                                // หา order สูงสุด
                                $max_order_query = "SELECT COALESCE(MAX(image_order), 0) + 1 as next_order FROM album_images WHERE album_id = ?";
                                $max_order_stmt = $db->prepare($max_order_query);
                                $max_order_stmt->execute([$album_id]);
                                $next_order = $max_order_stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
                                
                                $image_query = "INSERT INTO album_images (album_id, image_path, image_title, image_description, image_order) 
                                              VALUES (?, ?, ?, ?, ?)";
                                $image_stmt = $db->prepare($image_query);
                                $image_stmt->execute([
                                    $album_id, 
                                    $upload_result['relative_path'], 
                                    '', 
                                    '', 
                                    $next_order
                                ]);
                                $uploaded_count++;
                            } else {
                                $upload_errors = array_merge($upload_errors, $upload_result['errors']);
                            }
                        }
                    }
                    
                    if ($uploaded_count > 0) {
                        $success = true;
                        $success_message = "อัปโหลดรูปภาพสำเร็จ {$uploaded_count} รูป";
                        if (!empty($upload_errors)) {
                            $success_message .= " (มีข้อผิดพลาดบางรูป)";
                        }
                        
                        // รีเฟรชรายการรูปภาพ
                        $images_stmt->execute([$album_id]);
                        $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $errors[] = "ไม่สามารถอัปโหลดรูปภาพได้";
                        if (!empty($upload_errors)) {
                            $errors = array_merge($errors, array_slice($upload_errors, 0, 3));
                        }
                    }
                }
                break;
        }
    }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไข Album: <?php echo htmlspecialchars($album['title']); ?> - Albums Management</title>
        <link rel="stylesheet" href="../dist/output.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .image-item {
            transition: all 0.3s ease;
        }
        .image-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .sortable-ghost {
            opacity: 0.5;
        }
        .sortable-chosen {
            transform: scale(1.05);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        .cover-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: linear-gradient(45deg, #f59e0b, #d97706);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .image-overlay {
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 50%);
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="album_manage.php" class="text-blue-600 hover:text-blue-800 transition-colors">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">แก้ไข Album</h1>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($album['title']); ?></p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm text-gray-600">แก้ไขโดย:</div>
                        <div class="font-medium text-gray-800">
                            <?php echo htmlspecialchars($current_user['full_name']); ?>
                        </div>
                    </div>
                    <a href="../album_detail.php?id=<?php echo $album_id; ?>" 
                       target="_blank"
                       class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-eye mr-2"></i>ดู Album
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-2xl mr-3"></i>
                    <span class="font-semibold text-lg"><?php echo $success_message; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg mb-6">
                <div class="flex items-center mb-3">
                    <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                    <span class="font-semibold text-lg">เกิดข้อผิดพลาด:</span>
                </div>
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Album Information -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-edit mr-2 text-blue-600"></i>ข้อมูล Album
                    </h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                            <input type="hidden" name="action" value="upload_images">
                             <?php echo CSRFProtection::getTokenField('upload_images'); ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-heading mr-2 text-blue-600"></i>ชื่อ Album *
                                </label>
                                <input type="text" name="title" required 
                                       value="<?php echo htmlspecialchars($album['title']); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-tags mr-2 text-blue-600"></i>หมวดหมู่ *
                                </label>
                                <select name="category_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $album['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-toggle-on mr-2 text-blue-600"></i>สถานะ *
                                </label>
                                <select name="status" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="active" <?php echo $album['status'] === 'active' ? 'selected' : ''; ?>>เผยแพร่</option>
                                    <option value="inactive" <?php echo $album['status'] === 'inactive' ? 'selected' : ''; ?>>ไม่เผยแพร่</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>วันที่จัดกิจกรรม *
                                </label>
                                <input type="date" name="date_created" required 
                                       value="<?php echo $album['date_created']; ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-align-left mr-2 text-blue-600"></i>รายละเอียด *
                            </label>
                            <textarea name="description" required rows="6"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"><?php echo htmlspecialchars($album['description']); ?></textarea>
                        </div>
                        
                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i>บันทึกการเปลี่ยนแปลง
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Add New Images -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-images mr-2 text-purple-600"></i>เพิ่มรูปภาพใหม่
                    </h2>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="action" value="upload_images">
                        <?php echo CSRFProtection::getTokenField('upload_images'); ?>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-upload mr-2 text-purple-600"></i>เลือกรูปภาพ
                            </label>
                            <input type="file" name="new_images[]" multiple accept="image/*"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                   onchange="previewNewImages(this)">
                            <p class="text-sm text-gray-500 mt-2">สามารถเลือกหลายไฟล์พร้อมกัน (กด Ctrl + คลิก)</p>
                        </div>
                        
                        <div id="newImagesPreview" class="hidden">
                            <h3 class="font-semibold text-gray-800 mb-3">ตัวอย่างรูปภาพที่เลือก:</h3>
                            <div id="previewGrid" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                        </div>
                        
                        <!-- Upload Progress Bar -->
                        <div id="uploadProgress" class="hidden">
                            <div class="bg-gray-50 rounded-lg p-4 border-2 border-dashed border-gray-300">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-800">
                                        <i class="fas fa-upload mr-2 text-purple-600"></i>กำลังอัปโหลด...
                                    </h3>
                                    <div id="uploadPercentage" class="text-sm font-bold text-purple-600">0%</div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
                                    <div id="progressBar" class="bg-gradient-to-r from-purple-500 to-purple-600 h-3 rounded-full transition-all duration-300 ease-out" style="width: 0%"></div>
                                </div>
                                
                                <!-- Upload Status -->
                                <div class="flex justify-between items-center text-sm text-gray-600 mb-2">
                                    <div id="uploadStatus">เตรียมอัปโหลด...</div>
                                    <div id="uploadCount">0 / 0 ไฟล์</div>
                                </div>
                                
                                <!-- Upload Speed and ETA -->
                                <div class="flex justify-between items-center text-xs text-gray-500 mb-2">
                                    <div id="uploadSpeed" class="hidden">ความเร็ว: <span class="font-medium">- KB/s</span></div>
                                    <div id="uploadETA" class="hidden">เหลือเวลา: <span class="font-medium">-</span></div>
                                </div>
                                
                                <!-- Current File Info -->
                                <div id="currentFileInfo" class="mt-2 text-sm text-gray-500 hidden">
                                    <div class="flex items-center">
                                        <i class="fas fa-file-image mr-2"></i>
                                        <span id="currentFileName">-</span>
                                        <span id="currentFileSize" class="ml-2 text-xs">(-)</span>
                                    </div>
                                </div>
                                
                                <!-- Cancel Button -->
                                <div class="mt-3 text-center">
                                    <button type="button" id="cancelUpload" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors text-sm">
                                        <i class="fas fa-times mr-2"></i>ยกเลิกการอัปโหลด
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <button type="submit" id="uploadButton" class="bg-purple-600 text-white px-8 py-3 rounded-lg hover:bg-purple-700 transition-colors font-medium">
                                <i class="fas fa-upload mr-2"></i>อัปโหลดรูปภาพ
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Album Stats and Images -->
            <div>
                <!-- Stats -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-bar mr-2 text-green-600"></i>สถิติ Album
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                            <span class="text-gray-600">จำนวนรูปภาพ</span>
                            <span class="font-bold text-blue-600"><?php echo count($images); ?> รูป</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                            <span class="text-gray-600">จำนวนการดู</span>
                            <span class="font-bold text-green-600"><?php echo number_format($album['view_count']); ?> ครั้ง</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                            <span class="text-gray-600">สถานะ</span>
                            <span class="font-bold <?php echo $album['status'] === 'active' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $album['status'] === 'active' ? 'เผยแพร่' : 'ไม่เผยแพร่'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">สร้างเมื่อ</span>
                            <span class="font-bold text-gray-600">
                                <?php echo date('d/m/Y', strtotime($album['created_at'])); ?>
                            </span>
                        </div>
                        <?php if ($album['created_by_name']): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">สร้างโดย</span>
                            <span class="font-bold text-gray-600"><?php echo htmlspecialchars($album['created_by_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Images Management -->
        <?php if (!empty($images)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-images mr-2 text-indigo-600"></i>จัดการรูปภาพ (<?php echo count($images); ?> รูป)
                </h2>
                <div class="text-sm text-gray-600">
                    <i class="fas fa-hand-paper mr-1"></i>ลากเพื่อจัดเรียงลำดับ
                </div>
            </div>
            
            <div id="imagesGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                <?php foreach ($images as $image): ?>
                    <div class="image-item relative bg-white border border-gray-200 rounded-lg overflow-hidden cursor-move"
                         data-image-id="<?php echo $image['id']; ?>">
                        
                        <?php if ($image['is_cover']): ?>
                            <div class="cover-badge">
                                <i class="fas fa-crown mr-1"></i>ปก
                            </div>
                        <?php endif; ?>
                        
                        <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($image['image_title']); ?>"
                             class="w-full h-32 object-cover">
                        
                        <div class="absolute inset-0 image-overlay opacity-0 hover:opacity-100 transition-opacity duration-300 flex items-end">
                            <div class="w-full p-2">
                                <div class="flex justify-center space-x-2">
                                    <?php if (!$image['is_cover']): ?>
                                        <button onclick="setCover(<?php echo $image['id']; ?>)" 
                                                class="bg-yellow-600 text-white p-2 rounded-full hover:bg-yellow-700 transition-colors"
                                                title="ตั้งเป็นปก">
                                            <i class="fas fa-crown text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="editImage(<?php echo $image['id']; ?>)" 
                                            class="bg-blue-600 text-white p-2 rounded-full hover:bg-blue-700 transition-colors"
                                            title="แก้ไข">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    <button onclick="deleteImage(<?php echo $image['id']; ?>)" 
                                            class="bg-red-600 text-white p-2 rounded-full hover:bg-red-700 transition-colors"
                                            title="ลบ">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-2">
                            <div class="text-xs text-gray-600 truncate">
                                <?php echo htmlspecialchars($image['image_title'] ?: 'ไม่มีชื่อ'); ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                ลำดับ: <?php echo $image['image_order']; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Edit Image Modal -->
    <div id="editImageModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-6">
            <div class="flex items-center mb-4">
                <i class="fas fa-edit text-blue-600 text-2xl mr-3"></i>
                <h3 class="text-lg font-semibold text-gray-900">แก้ไขรูปภาพ</h3>
            </div>
            <form id="editImageForm">
                <input type="hidden" id="editImageId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อรูปภาพ</label>
                        <input type="text" id="editImageTitle" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">คำอธิบาย</label>
                        <textarea id="editImageDescription" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                    </div>
                </div>
                <div class="flex space-x-4 mt-6">
                    <button type="button" onclick="saveImageEdit()" 
                            class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>บันทึก
                    </button>
                    <button type="button" onclick="closeEditModal()" 
                            class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400 transition-colors">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Sortable images
        const imagesGrid = document.getElementById('imagesGrid');
        if (imagesGrid) {
            Sortable.create(imagesGrid, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function(evt) {
                    const imageIds = Array.from(imagesGrid.children).map(item => item.dataset.imageId);
                    updateImageOrder(imageIds);
                }
            });
        }

        function updateImageOrder(imageIds) {
            fetch('ajax/image_manage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'reorder',
                    album_id: <?php echo $album_id; ?>,
                    image_ids: imageIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('เกิดข้อผิดพลาดในการจัดเรียง: ' + (data.message || 'Unknown error'));
                    location.reload(); // Reload to reset order
                }
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                location.reload();
            });
        }

        function setCover(imageId) {
            if (confirm('ต้องการตั้งรูปนี้เป็นปก Album ใช่หรือไม่?')) {
                fetch('ajax/image_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'set_cover',
                        album_id: <?php echo $album_id; ?>,
                        image_id: imageId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (data.message || 'Unknown error'));
                    }
                });
            }
        }

        function editImage(imageId) {
            // ดึงข้อมูลรูปภาพปัจจุบัน
            fetch('ajax/image_manage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_image',
                    image_id: imageId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('editImageId').value = imageId;
                    document.getElementById('editImageTitle').value = data.image.image_title || '';
                    document.getElementById('editImageDescription').value = data.image.image_description || '';
                    document.getElementById('editImageModal').classList.remove('hidden');
                } else {
                    alert('เกิดข้อผิดพลาดในการดึงข้อมูล');
                }
            });
        }

        function saveImageEdit() {
            const imageId = document.getElementById('editImageId').value;
            const title = document.getElementById('editImageTitle').value;
            const description = document.getElementById('editImageDescription').value;

            fetch('ajax/image_manage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_image',
                    image_id: imageId,
                    title: title,
                    description: description
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeEditModal();
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (data.message || 'Unknown error'));
                }
            });
        }

        function closeEditModal() {
            document.getElementById('editImageModal').classList.add('hidden');
        }

        function deleteImage(imageId) {
            if (confirm('คุณต้องการลบรูปภาพนี้ใช่หรือไม่? การดำเนินการนี้ไม่สามารถยกเลิกได้')) {
                fetch('ajax/image_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_image',
                        image_id: imageId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (data.message || 'Unknown error'));
                    }
                });
            }
        }

        let uploadXHR = null; // Global variable to store XMLHttpRequest for cancellation
        let uploadStartTime = 0;
        let lastLoaded = 0;
        let lastTime = 0;

        function previewNewImages(input) {
            const preview = document.getElementById('newImagesPreview');
            const grid = document.getElementById('previewGrid');
            
            if (input.files && input.files.length > 0) {
                preview.classList.remove('hidden');
                grid.innerHTML = '';
                
                Array.from(input.files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'border border-gray-200 rounded-lg overflow-hidden';
                            div.innerHTML = `
                                <img src="${e.target.result}" class="w-full h-24 object-cover">
                                <div class="p-2 text-xs text-gray-600 truncate">${file.name}</div>
                            `;
                            grid.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            } else {
                preview.classList.add('hidden');
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function formatTime(seconds) {
            if (seconds < 60) return Math.round(seconds) + ' วินาที';
            if (seconds < 3600) return Math.round(seconds / 60) + ' นาที';
            return Math.round(seconds / 3600) + ' ชั่วโมง';
        }

        function updateProgressBar(percentage, currentFile, currentIndex, totalFiles, fileSize, loaded, total) {
            const progressBar = document.getElementById('progressBar');
            const percentage_el = document.getElementById('uploadPercentage');
            const status_el = document.getElementById('uploadStatus');
            const count_el = document.getElementById('uploadCount');
            const fileInfo_el = document.getElementById('currentFileInfo');
            const fileName_el = document.getElementById('currentFileName');
            const fileSize_el = document.getElementById('currentFileSize');
            const speed_el = document.getElementById('uploadSpeed');
            const eta_el = document.getElementById('uploadETA');

            // Update progress bar
            progressBar.style.width = percentage + '%';
            percentage_el.textContent = Math.round(percentage) + '%';
            
            // Update file count
            count_el.textContent = `${currentIndex} / ${totalFiles} ไฟล์`;
            
            // Calculate speed and ETA
            const currentTime = Date.now();
            if (loaded && total && uploadStartTime > 0) {
                const elapsedTime = (currentTime - uploadStartTime) / 1000; // seconds
                const bytesPerSecond = loaded / elapsedTime;
                const remainingBytes = total - loaded;
                const eta = remainingBytes / bytesPerSecond;
                
                // Update speed display
                if (bytesPerSecond > 0) {
                    speed_el.classList.remove('hidden');
                    const speedText = bytesPerSecond > 1024 * 1024 ? 
                        (bytesPerSecond / (1024 * 1024)).toFixed(1) + ' MB/s' :
                        (bytesPerSecond / 1024).toFixed(1) + ' KB/s';
                    speed_el.innerHTML = `ความเร็ว: <span class="font-medium">${speedText}</span>`;
                }
                
                // Update ETA display
                if (eta > 0 && eta < 3600 && percentage < 100) {
                    eta_el.classList.remove('hidden');
                    eta_el.innerHTML = `เหลือเวลา: <span class="font-medium">${formatTime(eta)}</span>`;
                } else if (percentage >= 100) {
                    eta_el.innerHTML = `เหลือเวลา: <span class="font-medium text-green-600">เสร็จแล้ว!</span>`;
                }
            }
            
            // Update current file info
            if (currentFile) {
                fileInfo_el.classList.remove('hidden');
                fileName_el.textContent = currentFile;
                fileSize_el.textContent = `(${formatFileSize(fileSize)})`;
                
                if (percentage < 100) {
                    status_el.textContent = `กำลังอัปโหลด: ${currentFile}`;
                } else {
                    status_el.textContent = `เสร็จสิ้นการอัปโหลด`;
                }
            } else {
                fileInfo_el.classList.add('hidden');
                status_el.textContent = percentage < 100 ? 'กำลังประมวลผล...' : 'เสร็จสิ้นการอัปโหลด';
            }
        }

        function showUploadProgress() {
            document.getElementById('uploadProgress').classList.remove('hidden');
            document.getElementById('uploadButton').disabled = true;
            document.getElementById('uploadButton').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>กำลังอัปโหลด...';
            uploadStartTime = Date.now();
        }

        function hideUploadProgress() {
            document.getElementById('uploadProgress').classList.add('hidden');
            document.getElementById('uploadButton').disabled = false;
            document.getElementById('uploadButton').innerHTML = '<i class="fas fa-upload mr-2"></i>อัปโหลดรูปภาพ';
            
            // Hide speed and ETA
            document.getElementById('uploadSpeed').classList.add('hidden');
            document.getElementById('uploadETA').classList.add('hidden');
            
            // Reset timing variables
            uploadStartTime = 0;
            lastLoaded = 0;
            lastTime = 0;
        }

        function handleUploadComplete(success, message) {
            hideUploadProgress();
            
            if (success) {
                // Show success message with better formatting
                const lines = message.split('\n');
                let alertMsg = lines[0]; // Main message
                
                if (lines.length > 1) {
                    // Show warnings separately if any
                    const warnings = lines.slice(1).join('\n');
                    setTimeout(() => {
                        if (confirm(alertMsg + '\n\n' + warnings + '\n\nต้องการดูรายละเอียดเพิ่มเติมใช่หรือไม่?')) {
                            console.log('Upload completed with warnings:', warnings);
                        }
                    }, 100);
                } else {
                    alert(alertMsg);
                }
                
                location.reload();
            } else {
                // Enhanced error display
                const errorDialog = document.createElement('div');
                errorDialog.style.cssText = `
                    position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
                    background: white; padding: 20px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    max-width: 500px; max-height: 70vh; overflow-y: auto; z-index: 10000;
                    font-family: 'Kanit', sans-serif; border: 3px solid #ef4444;
                `;
                
                errorDialog.innerHTML = `
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 24px; margin-right: 10px;"></i>
                        <h3 style="margin: 0; color: #ef4444;">เกิดข้อผิดพลาดในการอัปโหลด</h3>
                    </div>
                    <div style="white-space: pre-wrap; line-height: 1.5; margin-bottom: 20px; color: #374151;">
                        ${message}
                    </div>
                    <div style="text-align: right;">
                        <button onclick="this.parentElement.parentElement.remove(); document.getElementById('uploadOverlay').remove();" 
                                style="background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-family: 'Kanit', sans-serif;">
                            ปิด
                        </button>
                    </div>
                `;
                
                // Add overlay
                const overlay = document.createElement('div');
                overlay.id = 'uploadOverlay';
                overlay.style.cssText = `
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.5); z-index: 9999;
                `;
                overlay.onclick = () => {
                    errorDialog.remove();
                    overlay.remove();
                };
                
                document.body.appendChild(overlay);
                document.body.appendChild(errorDialog);
            }
        }

        // Handle upload form submission with AJAX and progress
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.querySelector('form[method="POST"][enctype="multipart/form-data"]');
            
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission
                
                const fileInput = document.querySelector('input[name="new_images[]"]');
                
                if (!fileInput.files.length) {
                    alert('กรุณาเลือกไฟล์รูปภาพก่อนอัปโหลด');
                    return;
                }
                
                const totalFiles = fileInput.files.length;
                const formData = new FormData();
                
                // Add album_id and CSRF token
                formData.append('album_id', <?php echo $album_id; ?>);
               formData.append('csrf_token', document.querySelector('form[enctype="multipart/form-data"] input[name="csrf_token"]').value);
                
                // Add all files
                Array.from(fileInput.files).forEach(file => {
                    formData.append('new_images[]', file);
                });
                
                showUploadProgress();
                updateProgressBar(0, null, 0, totalFiles, 0, 0, 0);
                
                // Create XMLHttpRequest for progress tracking
                uploadXHR = new XMLHttpRequest();
                
                // Track upload progress
                uploadXHR.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentage = (e.loaded / e.total) * 100;
                        const currentFileIndex = Math.min(Math.ceil((e.loaded / e.total) * totalFiles), totalFiles);
                        const currentFile = fileInput.files[currentFileIndex - 1]?.name || '';
                        const currentFileSize = fileInput.files[currentFileIndex - 1]?.size || 0;
                        
                        updateProgressBar(percentage, currentFile, currentFileIndex, totalFiles, currentFileSize, e.loaded, e.total);
                    }
                });
                
                // Handle upload completion
                uploadXHR.addEventListener('load', function() {
                    if (uploadXHR.status === 200) {
                        try {
                            const response = JSON.parse(uploadXHR.responseText);
                            
                            if (response.success) {
                                updateProgressBar(100, null, totalFiles, totalFiles, 0, 0, 0);
                                
                                // Show detailed success message
                                let successMsg = response.message;
                                if (response.warnings && response.warnings.length > 0) {
                                    successMsg += '\n\nคำเตือน:\n' + response.warnings.join('\n');
                                }
                                
                                setTimeout(() => {
                                    handleUploadComplete(true, successMsg);
                                }, 500);
                            } else {
                                // Enhanced error handling with detailed information
                                let errorMsg = response.message || 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ';
                                
                                if (response.errors && response.errors.length > 1) {
                                    errorMsg += '\n\nรายละเอียดข้อผิดพลาด:\n' + response.errors.slice(0, 5).join('\n');
                                    if (response.errors.length > 5) {
                                        errorMsg += '\n... และอีก ' + (response.errors.length - 5) + ' ข้อผิดพลาด';
                                    }
                                }
                                
                                // Add debug information if available
                                if (response.debug_info && console && console.error) {
                                    console.error('Upload Error Debug Info:', response.debug_info);
                                    
                                    if (response.error_code) {
                                        console.error('Error Code:', response.error_code);
                                    }
                                }
                                
                                handleUploadComplete(false, errorMsg);
                            }
                        } catch (error) {
                            console.error('JSON Parse Error:', error);
                            console.error('Server Response:', uploadXHR.responseText);
                            handleUploadComplete(false, 'ไม่สามารถประมวลผลการตอบกลับจากเซิร์ฟเวอร์ได้\nกรุณาตรวจสอบ Console สำหรับข้อมูลเพิ่มเติม');
                        }
                    } else {
                        let errorMsg = `เซิร์ฟเวอร์ตอบกลับด้วยสถานะ: ${uploadXHR.status}`;
                        
                        if (uploadXHR.status === 400) {
                            errorMsg += '\n\nข้อผิดพลาดที่อาจเกิดขึ้น:\n';
                            errorMsg += '• ไฟล์มีขนาดใหญ่เกินกำหนด\n';
                            errorMsg += '• ประเภทไฟล์ไม่ถูกต้อง\n';
                            errorMsg += '• ข้อมูลฟอร์มไม่ครบถ้วน\n';
                            errorMsg += '• ปัญหาการยืนยันตัวตน';
                        } else if (uploadXHR.status === 403) {
                            errorMsg += '\nไม่มีสิทธิ์ในการดำเนินการ กรุณาเข้าสู่ระบบใหม่';
                        } else if (uploadXHR.status === 404) {
                            errorMsg += '\nไม่พบไฟล์หรือหน้าที่ร้องขอ';
                        } else if (uploadXHR.status === 500) {
                            errorMsg += '\nเกิดข้อผิดพลาดภายในเซิร์ฟเวอร์';
                        }
                        
                        // Try to parse error response even for non-200 status
                        try {
                            const errorResponse = JSON.parse(uploadXHR.responseText);
                            if (errorResponse.message) {
                                errorMsg = errorResponse.message;
                                
                                if (errorResponse.debug_info && console && console.error) {
                                    console.error('Server Error Debug Info:', errorResponse.debug_info);
                                }
                            }
                        } catch (parseError) {
                            console.error('Could not parse error response:', uploadXHR.responseText);
                        }
                        
                        handleUploadComplete(false, errorMsg);
                    }
                });
                
                // Handle upload errors
                uploadXHR.addEventListener('error', function() {
                    console.error('Network error during upload');
                    let errorMsg = 'เกิดข้อผิดพลาดเครือข่าย\n\nสาเหตุที่อาจเกิดขึ้น:\n';
                    errorMsg += '• การเชื่อมต่ออินเทอร์เน็ตไม่เสถียร\n';
                    errorMsg += '• เซิร์ฟเวอร์ไม่ตอบสนอง\n';
                    errorMsg += '• ไฟล์มีขนาดใหญ่เกินไป\n';
                    errorMsg += '• การตั้งค่าเครือข่ายมีปัญหา\n\n';
                    errorMsg += 'แนะนำ: ตรวจสอบการเชื่อมต่อและลองใหม่อีกครั้ง';
                    
                    handleUploadComplete(false, errorMsg);
                });
                
                // Handle upload abort
                uploadXHR.addEventListener('abort', function() {
                    console.log('Upload cancelled by user');
                    hideUploadProgress();
                    alert('การอัปโหลดถูกยกเลิกแล้ว');
                });
                
                // Send the request to AJAX endpoint
                uploadXHR.open('POST', 'ajax/image_upload.php');
                uploadXHR.send(formData);
            });
            
            // Handle cancel upload button
            document.getElementById('cancelUpload').addEventListener('click', function() {
                if (uploadXHR) {
                    uploadXHR.abort();
                    uploadXHR = null;
                }
            });
        });

        // Close modal when clicking outside
        document.getElementById('editImageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // ESC key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>