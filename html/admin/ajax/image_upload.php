<?php
include_once '../../config/database.php';
include_once '../../models/Album.php';
include_once '../Auth.php';
include_once '../ImageHandler.php';
include_once '../CSRFProtection.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);

    // ตรวจสอบสิทธิ์การเข้าถึง
    $auth->requireLogin();
    $current_user = $auth->getCurrentUser();

    // ตรวจสอบ CSRF token
    if (!CSRFProtection::validateToken('upload_images', $_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }

    // ตรวจสอบ album_id
    $album_id = isset($_POST['album_id']) ? (int)$_POST['album_id'] : 0;
    if ($album_id <= 0) {
        throw new Exception('Invalid album ID');
    }

    // ดึงข้อมูล album และตรวจสอบสิทธิ์
    $album_query = "SELECT * FROM albums WHERE id = ?";
    $album_stmt = $db->prepare($album_query);
    $album_stmt->execute([$album_id]);
    $album = $album_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$album) {
        throw new Exception('Album not found');
    }

    // ตรวจสอบสิทธิ์ในการแก้ไข
    if ($current_user['role'] !== 'admin' && $album['created_by'] != $current_user['id']) {
        throw new Exception('Access denied');
    }

    // ตรวจสอบไฟล์ที่อัปโหลด
    if (!isset($_FILES['new_images']) || !is_array($_FILES['new_images']['name'])) {
        throw new Exception('No files uploaded');
    }

    $imageHandler = new ImageHandler();
    $uploaded_count = 0;
    $errors = [];
    $uploaded_files = [];

    // ประมวลผลไฟล์แต่ละไฟล์
    $file_count = count($_FILES['new_images']['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['new_images']['error'][$i] === UPLOAD_ERR_OK) {
            $file = [
                'name' => $_FILES['new_images']['name'][$i],
                'type' => $_FILES['new_images']['type'][$i],
                'tmp_name' => $_FILES['new_images']['tmp_name'][$i],
                'error' => $_FILES['new_images']['error'][$i],
                'size' => $_FILES['new_images']['size'][$i]
            ];

            $upload_result = $imageHandler->uploadImage($file, $album_id);

            if ($upload_result['success']) {
                // บันทึกข้อมูลรูปภาพลงฐานข้อมูล
                $image_insert = "INSERT INTO album_images (album_id, file_name, original_name, file_path, uploaded_at, uploaded_by) 
                               VALUES (?, ?, ?, ?, NOW(), ?)";
                $image_stmt = $db->prepare($image_insert);
                
                if ($image_stmt->execute([
                    $album_id,
                    $upload_result['filename'],
                    $file['name'],
                    $upload_result['relative_path'],
                    $current_user['id']
                ])) {
                    $uploaded_count++;
                    $uploaded_files[] = [
                        'filename' => $upload_result['filename'],
                        'original_name' => $file['name'],
                        'path' => $upload_result['relative_path']
                    ];
                } else {
                    $errors[] = "Failed to save {$file['name']} to database";
                }
            } else {
                $errors = array_merge($errors, $upload_result['errors']);
            }
        } else {
            $errors[] = "Error uploading {$_FILES['new_images']['name'][$i]}: " . 
                       $imageHandler->getUploadErrorMessage($_FILES['new_images']['error'][$i]);
        }
    }

    // อัปเดตจำนวนรูปภาพใน album
    if ($uploaded_count > 0) {
        $update_query = "UPDATE albums SET updated_at = NOW() WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$album_id]);
    }

    // ส่งผลลัพธ์
    $response = [
        'success' => $uploaded_count > 0,
        'uploaded_count' => $uploaded_count,
        'total_files' => $file_count,
        'uploaded_files' => $uploaded_files,
        'errors' => $errors,
        'message' => $uploaded_count > 0 ? 
            "อัปโหลดรูปภาพสำเร็จ {$uploaded_count} รูป" . 
            (!empty($errors) ? " (มีข้อผิดพลาดบางส่วน)" : "") :
            "ไม่สามารถอัปโหลดรูปภาพได้"
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'uploaded_count' => 0,
        'errors' => [$e->getMessage()]
    ], JSON_UNESCAPED_UNICODE);
}
?>