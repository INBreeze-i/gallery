<?php
include_once '../config/database.php';
include_once 'Auth.php';
include_once 'ImageHandler.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// ตรวจสอบสิทธิ์การเข้าถึง
$auth->requireLogin();

$imageHandler = new ImageHandler();
$current_user = $auth->getCurrentUser();

// ดึงรายการหมวดหมู่
$categories_query = "SELECT * FROM album_categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

$success = false;
$errors = [];
$album_id = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $date_created = $_POST['date_created'];
    
    // Validation
    if (empty($title)) $errors[] = "กรุณากรอกชื่อ Album";
    if (empty($description)) $errors[] = "กรุณากรอกรายละเอียด";
    if ($category_id <= 0) $errors[] = "กรุณาเลือกหมวดหมู่";
    if (empty($date_created)) $errors[] = "กรุณาเลือกวันที่";
    
    if (empty($errors)) {
        try {
            // สร้าง Album พร้อม created_by
            $insert_query = "INSERT INTO albums (category_id, title, description, date_created, created_by, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->execute([$category_id, $title, $description, $date_created, $current_user['id']]);
            
            $album_id = $db->lastInsertId();
            
            // อัปโหลดรูปภาพ
            $uploaded_count = 0;
            $upload_errors = [];
            $total_files = 0;
            
            // นับจำนวนไฟล์ทั้งหมด
            if (!empty($_FILES['bulk_images']['name'][0])) {
                $total_files += count($_FILES['bulk_images']['name']);
            }
            
            if (!empty($_FILES['images']['name'][0])) {
                $total_files += count(array_filter($_FILES['images']['name']));
            }
            
            // อัปโหลดไฟล์จาก bulk upload
            if (!empty($_FILES['bulk_images']['name'][0])) {
                for ($i = 0; $i < count($_FILES['bulk_images']['name']); $i++) {
                    if ($_FILES['bulk_images']['error'][$i] == UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['bulk_images']['name'][$i],
                            'type' => $_FILES['bulk_images']['type'][$i],
                            'tmp_name' => $_FILES['bulk_images']['tmp_name'][$i],
                            'size' => $_FILES['bulk_images']['size'][$i],
                            'error' => $_FILES['bulk_images']['error'][$i]
                        ];
                        
                        $upload_result = $imageHandler->uploadImage($file, $album_id);
                        
                        if ($upload_result['success']) {
                            $image_query = "INSERT INTO album_images (album_id, image_path, image_title, image_description, image_order) 
                                          VALUES (?, ?, ?, ?, ?)";
                            $image_stmt = $db->prepare($image_query);
                            $image_stmt->execute([
                                $album_id, 
                                $upload_result['relative_path'], 
                                '', // ไม่มีชื่อสำหรับ bulk upload
                                '', // ไม่มีคำอธิบายสำหรับ bulk upload
                                $uploaded_count + 1
                            ]);
                            $uploaded_count++;
                        } else {
                            $upload_errors = array_merge($upload_errors, $upload_result['errors']);
                        }
                    }
                }
            }
            
            // อัปโหลดไฟล์จาก individual fields
            if (!empty($_FILES['images']['name'][0])) {
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] == UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['images']['name'][$i],
                            'type' => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'size' => $_FILES['images']['size'][$i],
                            'error' => $_FILES['images']['error'][$i]
                        ];
                        
                        $image_title = !empty($_POST['image_titles'][$i]) ? trim($_POST['image_titles'][$i]) : '';
                        $image_description = !empty($_POST['image_descriptions'][$i]) ? trim($_POST['image_descriptions'][$i]) : '';
                        
                        $upload_result = $imageHandler->uploadImage($file, $album_id);
                        
                        if ($upload_result['success']) {
                            $image_query = "INSERT INTO album_images (album_id, image_path, image_title, image_description, image_order) 
                                          VALUES (?, ?, ?, ?, ?)";
                            $image_stmt = $db->prepare($image_query);
                            $image_stmt->execute([
                                $album_id, 
                                $upload_result['relative_path'], 
                                $image_title, 
                                $image_description, 
                                $uploaded_count + 1
                            ]);
                            $uploaded_count++;
                        } else {
                            $upload_errors = array_merge($upload_errors, $upload_result['errors']);
                        }
                    }
                }
            }
            
            if ($uploaded_count > 0) {
                $success = true;
                $success_message = "สร้าง Album สำเร็จ! อัปโหลดรูปภาพได้ {$uploaded_count}/{$total_files} รูป";
                if (!empty($upload_errors)) {
                    $success_message .= " (มีข้อผิดพลาดบางรูป)";
                }
            } else if ($total_files > 0) {
                $errors[] = "ไม่สามารถอัปโหลดรูปภาพได้";
                if (!empty($upload_errors)) {
                    $errors = array_merge($errors, array_slice($upload_errors, 0, 5));
                }
            } else {
                $success = true;
                $success_message = "สร้าง Album สำเร็จ! (ยังไม่มีรูปภาพ - สามารถเพิ่มทีหลังได้)";
            }
            
        } catch (PDOException $e) {
            error_log("Album creation error: " . $e->getMessage());
            $errors[] = "เกิดข้อผิดพลาดในการสร้าง Album: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้าง Album ใหม่ - Albums Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/kanit-font.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .upload-area {
            border: 2px dashed #d1d5db;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #3b82f6;
            background-color: #f8fafc;
        }
        .upload-area.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
            transform: scale(1.02);
        }
        .file-preview {
            max-height: 150px;
            object-fit: cover;
        }
        .upload-progress {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            width: 0%;
            transition: width 0.3s ease;
        }
        .file-item {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800 transition-colors">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">สร้าง Album ใหม่</h1>
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-clock mr-1"></i>
                            สร้างเมื่อ: <?php echo date('Y-m-d H:i:s', strtotime('2025-07-23 04:11:50')); ?> UTC
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm text-gray-600">สร้างโดย:</div>
                        <div class="font-medium text-gray-800">
                            <i class="fas fa-user mr-1"></i>
                            <?php echo htmlspecialchars($current_user['full_name']); ?> (INBreeze-i)
                        </div>
                    </div>
                    <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-home mr-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- Success/Error Messages (เดิม) -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg mb-6">
                <div class="flex items-center mb-3">
                    <i class="fas fa-check-circle text-2xl mr-3"></i>
                    <span class="font-semibold text-lg"><?php echo $success_message; ?></span>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="album_edit.php?id=<?php echo $album_id; ?>" 
                       class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-edit mr-2"></i>แก้ไข Album
                    </a>
                    <a href="album_create.php" 
                       class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>สร้าง Album ใหม่
                    </a>
                    <a href="../album_detail.php?id=<?php echo $album_id; ?>" 
                       target="_blank" 
                       class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-eye mr-2"></i>ดู Album
                    </a>
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

        <!-- Main Form -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <form method="POST" enctype="multipart/form-data" id="albumForm" class="space-y-8">
                <!-- ข้อมูลพื้นฐาน Album (เดิม) -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">ข้อมูล Album</h2>
                    <p class="text-gray-600">กรอกข้อมูลพื้นฐานของ Album ที่ต้องการสร้าง</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-heading mr-2 text-blue-600"></i>ชื่อ Album *
                            </label>
                            <input type="text" name="title" required 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                   placeholder="เช่น: การแข่งขันฟุตบอลประจำปี 2025">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tags mr-2 text-blue-600"></i>หมวดหมู่ *
                            </label>
                            <select name="category_id" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                <option value="">เลือกหมวดหมู่</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>วันที่จัดกิจกรรม *
                            </label>
                            <input type="date" name="date_created" required 
                                   value="<?php echo isset($_POST['date_created']) ? $_POST['date_created'] : '2025-07-23'; ?>"
                                   max="2025-07-23"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-2 text-blue-600"></i>รายละเอียด *
                        </label>
                        <textarea name="description" required rows="8"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                                  placeholder="อธิบายรายละเอียดของกิจกรรม เช่น วันที่จัด สถานที่ ผู้เข้าร่วม และไฮไลท์ของกิจกรรม"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- Multi-file Upload Section -->
                <div class="border-t pt-8">
                    <div class="mb-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">
                            <i class="fas fa-images mr-2 text-purple-600"></i>อัปโหลดรูปภาพ
                        </h3>
                        <p class="text-gray-600">เลือกรูปภาพที่เกี่ยวข้องกับกิจกรรม - รองรับการอัปโหลดหลายไฟล์พร้อมกัน</p>
                    </div>

                    <!-- Bulk Upload Section -->
                    <div class="mb-8">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-upload mr-2 text-green-600"></i>อัปโหลดหลายไฟล์พร้อมกัน
                        </h4>
                        
                        <!-- Drag & Drop Area -->
                        <div id="dropZone" class="upload-area rounded-xl p-8 text-center cursor-pointer">
                            <div class="mb-4">
                                <i class="fas fa-cloud-upload-alt text-6xl text-gray-400 mb-4"></i>
                                <h5 class="text-xl font-semibold text-gray-700 mb-2">ลากและวางไฟล์ที่นี่</h5>
                                <p class="text-gray-500 mb-4">หรือคลิกเพื่อเลือกไฟล์หลายๆ ไฟล์พร้อมกัน</p>
                            </div>
                            
                            <input type="file" 
                                   id="bulkUpload" 
                                   name="bulk_images[]" 
                                   multiple 
                                   accept="image/*" 
                                   class="hidden">
                            
                            <button type="button" 
                                    onclick="document.getElementById('bulkUpload').click()" 
                                    class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-folder-open mr-2"></i>เลือกไฟล์หลายๆ ไฟล์
                            </button>
                            
                            <div class="mt-4 text-sm text-gray-500">
                                <p>รองรับ: JPEG, PNG, GIF, WebP | แปลงเป็น WebP อัตโนมัติเพื่อประหยัดพื้นที่</p>
                                <p>สามารถเลือกได้หลายไฟล์พร้อมกัน (กด Ctrl/Cmd + คลิก)</p>
                            </div>
                        </div>

                        <!-- Bulk Upload Preview -->
                        <div id="bulkPreview" class="mt-6 hidden">
                            <h5 class="font-semibold text-gray-800 mb-3">
                                <i class="fas fa-eye mr-2"></i>ไฟล์ที่เลือก: <span id="fileCount">0</span> ไฟล์
                            </h5>
                            <div id="bulkFileList" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4"></div>
                            <div class="mt-4">
                                <button type="button" onclick="clearBulkFiles()" 
                                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                                    <i class="fas fa-trash mr-2"></i>ลบไฟล์ทั้งหมด
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Separator -->
                    <div class="flex items-center my-8">
                        <div class="flex-1 border-t border-gray-300"></div>
                        <div class="px-4 text-gray-500 font-medium">หรือ</div>
                        <div class="flex-1 border-t border-gray-300"></div>
                    </div>

                    <!-- Individual Upload Section -->
                    <div>
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-800">
                                    <i class="fas fa-image mr-2 text-blue-600"></i>อัปโหลดแต่ละไฟล์พร้อมรายละเอียด
                                </h4>
                                <p class="text-gray-600 mt-1">เหมาะสำหรับการเพิ่มชื่อและคำอธิบายให้กับรูปภาพแต่ละรูป</p>
                            </div>
                            <button type="button" onclick="addImageField()" 
                                    class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>เพิ่มรูปภาพ
                            </button>
                        </div>

                        <div id="images-container" class="space-y-6">
                            <!-- Individual image fields จะถูกเพิ่มที่นี่ -->
                        </div>
                    </div>

                    <!-- Upload Guidelines -->
                    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <h4 class="font-semibold text-blue-800 mb-3">
                            <i class="fas fa-info-circle mr-2"></i>ข้อมูลสำคัญสำหรับการอัปโหลด:
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-700">
                            <ul class="space-y-2">
                                <li><i class="fas fa-check mr-2 text-green-600"></i>รองรับไฟล์: JPEG, PNG, GIF, WebP</li>
                                <li><i class="fas fa-check mr-2 text-green-600"></i>แปลงเป็น WebP อัตโนมัติ (ประหยัดพื้นที่)</li>
                                <li><i class="fas fa-check mr-2 text-green-600"></i>สามารถอัปโหลดได้หลายไฟล์พร้อมกัน</li>
                            </ul>
                            <ul class="space-y-2">
                                <li><i class="fas fa-check mr-2 text-green-600"></i>รูปภาพจะถูกปรับขนาดอัตโนมัติ</li>
                                <li><i class="fas fa-check mr-2 text-green-600"></i>ระบบจะสร้าง thumbnail อัตโนมัติ</li>
                                <li><i class="fas fa-check mr-2 text-green-600"></i>รองรับ Drag & Drop</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex flex-wrap gap-4 pt-8 border-t">
                    <button type="submit" id="submitBtn" 
                            class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-save mr-2"></i>
                        <span class="btn-text">สร้าง Album</span>
                        <span class="loading hidden">
                            <i class="fas fa-spinner fa-spin mr-2"></i>กำลังสร้าง...
                        </span>
                    </button>
                    
                    <button type="button" onclick="resetForm()" 
                            class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-undo mr-2"></i>รีเซ็ต
                    </button>
                    
                    <a href="dashboard.php" 
                       class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-times mr-2"></i>ยกเลิก
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        let imageFieldCount = 0;
        let bulkFiles = [];

        // Drag & Drop functionality
        const dropZone = document.getElementById('dropZone');
        const bulkUpload = document.getElementById('bulkUpload');

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            const files = Array.from(e.dataTransfer.files);
            const imageFiles = files.filter(file => file.type.startsWith('image/'));
            
            if (imageFiles.length > 0) {
                handleBulkFiles(imageFiles);
            } else {
                alert('กรุณาเลือกไฟล์รูปภาพเท่านั้น');
            }
        });

        bulkUpload.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleBulkFiles(Array.from(e.target.files));
            }
        });

        function handleBulkFiles(files) {
            // ตรวจสอบและกรองไฟล์
            const validFiles = [];
            const errors = [];

            files.forEach(file => {
                if (!file.type.startsWith('image/')) {
                    errors.push(`${file.name}: ไม่ใช่ไฟล์รูปภาพ`);
                } else {
                    validFiles.push(file);
                }
            });

            if (errors.length > 0) {
                alert('พบข้อผิดพลาด:\n' + errors.join('\n'));
            }

            if (validFiles.length > 0) {
                bulkFiles = [...bulkFiles, ...validFiles];
                updateBulkPreview();
            }
        }

        function updateBulkPreview() {
            const preview = document.getElementById('bulkPreview');
            const fileList = document.getElementById('bulkFileList');
            const fileCount = document.getElementById('fileCount');

            if (bulkFiles.length === 0) {
                preview.classList.add('hidden');
                return;
            }

            preview.classList.remove('hidden');
            fileCount.textContent = bulkFiles.length;
            fileList.innerHTML = '';

            bulkFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item bg-white border border-gray-200 rounded-lg p-2 relative';
                    fileItem.innerHTML = `
                        <img src="${e.target.result}" class="file-preview w-full rounded">
                        <div class="mt-2 text-xs text-gray-600 truncate" title="${file.name}">
                            ${file.name}
                        </div>
                        <div class="text-xs text-gray-500">
                            ${(file.size / 1024 / 1024).toFixed(2)} MB
                        </div>
                        <button type="button" onclick="removeBulkFile(${index})" 
                                class="absolute top-1 right-1 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-700">
                            ×
                        </button>
                    `;
                    fileList.appendChild(fileItem);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeBulkFile(index) {
            bulkFiles.splice(index, 1);
            updateBulkPreview();
            updateBulkInput();
        }

        function clearBulkFiles() {
            if (confirm('คุณต้องการลบไฟล์ทั้งหมดใช่หรือไม่?')) {
                bulkFiles = [];
                updateBulkPreview();
                updateBulkInput();
            }
        }

        function updateBulkInput() {
            // อัปเดต input file กับไฟล์ที่เหลือ
            const dt = new DataTransfer();
            bulkFiles.forEach(file => dt.items.add(file));
            bulkUpload.files = dt.files;
        }

        // Individual image field functions
        function addImageField() {
            imageFieldCount++;
            const container = document.getElementById('images-container');
            
            const imageFieldHTML = `
                <div id="image-field-${imageFieldCount}" class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-medium text-gray-800 text-lg">
                            <i class="fas fa-image mr-2 text-blue-600"></i>รูปภาพที่ ${imageFieldCount}
                        </h4>
                        <button type="button" onclick="removeImageField(${imageFieldCount})" 
                                class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-100 transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">เลือกไฟล์รูปภาพ</label>
                            <input type="file" name="images[]" accept="image/*"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   onchange="previewImage(this, ${imageFieldCount})">
                        </div>
                        
                        <div class="lg:col-span-2 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อรูปภาพ</label>
                                <input type="text" name="image_titles[]" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="เช่น: การเตะลูกฟุตบอล, การเชียร์จากผู้ชม">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">คำอธิบายรูปภาพ</label>
                                <textarea name="image_descriptions[]" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                          placeholder="อธิบายสิ่งที่เกิดขึ้นในรูปภาพ"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div id="preview-${imageFieldCount}" class="mt-4 hidden">
                        <div class="border border-gray-300 rounded-lg p-2 bg-white">
                            <img class="file-preview w-full rounded-lg">
                            <div class="text-center text-sm text-gray-600 mt-2">ตัวอย่างรูปภาพ</div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', imageFieldHTML);
        }

        function removeImageField(fieldId) {
            const field = document.getElementById(`image-field-${fieldId}`);
            if (field) {
                field.remove();
            }
        }

        function previewImage(input, fieldId) {
            const preview = document.getElementById(`preview-${fieldId}`);
            const img = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                preview.classList.add('hidden');
            }
        }

        function resetForm() {
            if (confirm('คุณต้องการรีเซ็ตฟอร์มใช่หรือไม่? ข้อมูลทั้งหมดจะหายไป')) {
                document.getElementById('albumForm').reset();
                document.getElementById('images-container').innerHTML = '';
                bulkFiles = [];
                updateBulkPreview();
                imageFieldCount = 0;
            }
        }

        // Form submission
        document.getElementById('albumForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const loading = submitBtn.querySelector('.loading');
            
            // ตรวจสอบข้อมูลพื้นฐาน
            const title = document.querySelector('input[name="title"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            const category = document.querySelector('select[name="category_id"]').value;
            
            if (!title || !description || !category) {
                alert('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
                return false;
            }
            
            // แสดง loading state
            submitBtn.disabled = true;
            btnText.classList.add('hidden');
            loading.classList.remove('hidden');
            
            return true;
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus ที่ชื่อ Album
            document.querySelector('input[name="title"]').focus();
        });
    </script>
</body>
</html>
