<?php
include_once '../../config/database.php';
include_once '../../models/Album.php';
include_once '../Auth.php';
include_once '../ImageHandler.php';
include_once '../CSRFProtection.php';

header('Content-Type: application/json');

// Helper function for enhanced error logging
function logUploadError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $user_info = isset($context['user_id']) ? " (User ID: {$context['user_id']})" : "";
    $album_info = isset($context['album_id']) ? " (Album ID: {$context['album_id']})" : "";
    $file_info = isset($context['file_name']) ? " (File: {$context['file_name']})" : "";
    
    $log_message = "[{$timestamp}] Image Upload Error{$user_info}{$album_info}{$file_info}: {$message}";
    
    if (!empty($context['additional_data'])) {
        $log_message .= " | Additional Data: " . json_encode($context['additional_data']);
    }
    
    error_log($log_message);
}

// Helper function for enhanced error responses
function sendErrorResponse($message, $error_code = 'UNKNOWN_ERROR', $http_code = 400, $context = []) {
    http_response_code($http_code);
    
    $response = [
        'success' => false,
        'message' => $message,
        'error_code' => $error_code,
        'uploaded_count' => 0,
        'errors' => [$message],
        'timestamp' => date('c'),
        'debug_info' => [
            'php_version' => PHP_VERSION,
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads')
        ]
    ];
    
    // Add context information for debugging
    if (!empty($context)) {
        $response['debug_info']['context'] = $context;
    }
    
    // Log the error with context
    logUploadError($message, $context);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);

    // ตรวจสอบสิทธิ์การเข้าถึง
    $auth->requireLogin();
    $current_user = $auth->getCurrentUser();

    // ตรวจสอบ CSRF token
if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '', 'upload_images')) {
        sendErrorResponse(
            'การยืนยันความปลอดภัยล้มเหลว กรุณารีเฟรชหน้าและลองใหม่', 
            'CSRF_TOKEN_INVALID',
            403,
            ['user_id' => $current_user['id']]
        );
    }

    // ตรวจสอบ album_id
    $album_id = isset($_POST['album_id']) ? (int)$_POST['album_id'] : 0;
    if ($album_id <= 0) {
        sendErrorResponse(
            'หมายเลข Album ไม่ถูกต้อง', 
            'INVALID_ALBUM_ID',
            400,
            ['user_id' => $current_user['id'], 'provided_album_id' => $_POST['album_id'] ?? 'not_provided']
        );
    }

    // ดึงข้อมูล album และตรวจสอบสิทธิ์
    $album_query = "SELECT * FROM albums WHERE id = ?";
    $album_stmt = $db->prepare($album_query);
    $album_stmt->execute([$album_id]);
    $album = $album_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$album) {
        sendErrorResponse(
            'ไม่พบ Album ที่ระบุ', 
            'ALBUM_NOT_FOUND',
            404,
            ['user_id' => $current_user['id'], 'album_id' => $album_id]
        );
    }

    // ตรวจสอบสิทธิ์ในการแก้ไข
    if ($current_user['role'] !== 'admin' && $album['created_by'] != $current_user['id']) {
        sendErrorResponse(
            'ไม่มีสิทธิ์ในการแก้ไข Album นี้', 
            'ACCESS_DENIED',
            403,
            [
                'user_id' => $current_user['id'], 
                'user_role' => $current_user['role'],
                'album_id' => $album_id,
                'album_owner' => $album['created_by']
            ]
        );
    }

    // ตรวจสอบไฟล์ที่อัปโหลด
    if (!isset($_FILES['new_images']) || !is_array($_FILES['new_images']['name'])) {
        sendErrorResponse(
            'ไม่พบไฟล์ที่อัปโหลด กรุณาเลือกไฟล์ภาพก่อนอัปโหลด', 
            'NO_FILES_UPLOADED',
            400,
            ['user_id' => $current_user['id'], 'album_id' => $album_id]
        );
    }

    $imageHandler = new ImageHandler();
    
    // ตรวจสอบการตั้งค่า PHP สำหรับการอัปโหลด
    $upload_validation = $imageHandler->validatePHPUploadSettings();
    if (!empty($upload_validation['issues'])) {
        sendErrorResponse(
            'ระบบไม่รองรับการอัปโหลดไฟล์: ' . implode('; ', $upload_validation['issues']), 
            'PHP_UPLOAD_CONFIG_ERROR',
            500,
            [
                'user_id' => $current_user['id'], 
                'album_id' => $album_id,
                'additional_data' => $upload_validation
            ]
        );
    }
    
    $uploaded_count = 0;
    $errors = [];
    $warnings = [];
    $uploaded_files = [];

    // แสดงคำเตือนหากมี
    if (!empty($upload_validation['warnings'])) {
        $warnings = array_merge($warnings, $upload_validation['warnings']);
        logUploadError('Upload warnings detected', [
            'user_id' => $current_user['id'], 
            'album_id' => $album_id,
            'additional_data' => ['warnings' => $upload_validation['warnings']]
        ]);
    }

    // ประมวลผลไฟล์แต่ละไฟล์
    $file_count = count($_FILES['new_images']['name']);
    
    logUploadError("Starting upload process for {$file_count} files", [
        'user_id' => $current_user['id'], 
        'album_id' => $album_id,
        'additional_data' => [
            'file_count' => $file_count,
            'album_title' => $album['title']
        ]
    ]);
    
    for ($i = 0; $i < $file_count; $i++) {
        $current_file_name = $_FILES['new_images']['name'][$i];
        
        if ($_FILES['new_images']['error'][$i] === UPLOAD_ERR_OK) {
            $file = [
                'name' => $current_file_name,
                'type' => $_FILES['new_images']['type'][$i],
                'tmp_name' => $_FILES['new_images']['tmp_name'][$i],
                'error' => $_FILES['new_images']['error'][$i],
                'size' => $_FILES['new_images']['size'][$i]
            ];

            logUploadError("Processing file: {$current_file_name}", [
                'user_id' => $current_user['id'],
                'album_id' => $album_id,
                'file_name' => $current_file_name,
                'additional_data' => [
                    'file_size' => $file['size'],
                    'file_type' => $file['type']
                ]
            ]);

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
                        'path' => $upload_result['relative_path'],
                        'file_size' => $upload_result['file_size'] ?? $file['size']
                    ];
                    
                    logUploadError("Successfully uploaded file: {$current_file_name}", [
                        'user_id' => $current_user['id'],
                        'album_id' => $album_id,
                        'file_name' => $current_file_name,
                        'additional_data' => [
                            'saved_filename' => $upload_result['filename'],
                            'file_size' => $upload_result['file_size'] ?? $file['size']
                        ]
                    ]);
                } else {
                    $error_msg = "ไม่สามารถบันทึกข้อมูล {$file['name']} ลงฐานข้อมูลได้";
                    $errors[] = $error_msg;
                    
                    logUploadError("Database save failed for file: {$current_file_name}", [
                        'user_id' => $current_user['id'],
                        'album_id' => $album_id,
                        'file_name' => $current_file_name,
                        'additional_data' => [
                            'upload_result' => $upload_result,
                            'database_error' => $image_stmt->errorInfo()
                        ]
                    ]);
                }
            } else {
                $file_errors = $upload_result['errors'] ?? ['ไม่ทราบสาเหตุ'];
                $errors = array_merge($errors, $file_errors);
                
                logUploadError("Upload failed for file: {$current_file_name}", [
                    'user_id' => $current_user['id'],
                    'album_id' => $album_id,
                    'file_name' => $current_file_name,
                    'additional_data' => [
                        'upload_errors' => $file_errors,
                        'file_info' => $file
                    ]
                ]);
            }
        } else {
            $error_msg = "ข้อผิดพลาดในการอัปโหลด {$current_file_name}: " . 
                       $imageHandler->getUploadErrorMessage($_FILES['new_images']['error'][$i]);
            $errors[] = $error_msg;
            
            logUploadError("Upload error for file: {$current_file_name}", [
                'user_id' => $current_user['id'],
                'album_id' => $album_id,
                'file_name' => $current_file_name,
                'additional_data' => [
                    'error_code' => $_FILES['new_images']['error'][$i],
                    'error_message' => $imageHandler->getUploadErrorMessage($_FILES['new_images']['error'][$i])
                ]
            ]);
        }
    }

    // อัปเดตจำนวนรูปภาพใน album
    if ($uploaded_count > 0) {
        $update_query = "UPDATE albums SET updated_at = NOW() WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$album_id]);
        
        logUploadError("Upload process completed successfully", [
            'user_id' => $current_user['id'],
            'album_id' => $album_id,
            'additional_data' => [
                'uploaded_count' => $uploaded_count,
                'total_files' => $file_count,
                'errors_count' => count($errors)
            ]
        ]);
    } else {
        logUploadError("Upload process completed with no successful uploads", [
            'user_id' => $current_user['id'],
            'album_id' => $album_id,
            'additional_data' => [
                'total_files' => $file_count,
                'errors_count' => count($errors),
                'errors' => $errors
            ]
        ]);
    }

    // ส่งผลลัพธ์
    $response = [
        'success' => $uploaded_count > 0,
        'uploaded_count' => $uploaded_count,
        'total_files' => $file_count,
        'uploaded_files' => $uploaded_files,
        'errors' => $errors,
        'warnings' => $warnings,
        'message' => $uploaded_count > 0 ? 
            "อัปโหลดรูปภาพสำเร็จ {$uploaded_count} รูป" . 
            (!empty($errors) ? " (มีข้อผิดพลาดบางส่วน: " . count($errors) . " ไฟล์)" : "") :
            "ไม่สามารถอัปโหลดรูปภาพได้",
        'debug_info' => [
            'album_id' => $album_id,
            'album_title' => $album['title'],
            'user_id' => $current_user['id'],
            'timestamp' => date('c'),
            'upload_settings' => $upload_validation['settings'] ?? []
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Enhanced exception handling
    $error_context = [
        'user_id' => isset($current_user) ? $current_user['id'] : 'unknown',
        'album_id' => isset($album_id) ? $album_id : 'unknown',
        'additional_data' => [
            'exception_type' => get_class($e),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ];
    
    sendErrorResponse(
        'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(),
        'SYSTEM_ERROR',
        500,
        $error_context
    );
}
?>