<?php
header('Content-Type: application/json');

include_once '../../config/database.php';
include_once '../Auth.php';
include_once '../ImageHandler.php';
include_once '../CSRFProtection.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    // ตรวจสอบสิทธิ์การเข้าถึง
    if (!$auth->checkAccess()) {
        echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
        exit();
    }
    
    // ตรวจสอบ HTTP method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'HTTP method ไม่ถูกต้อง']);
        exit();
    }
    
    // รับข้อมูลจาก request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['album_id']) || !is_numeric($input['album_id'])) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูล album_id ไม่ถูกต้อง']);
        exit();
    }
    
    $album_id = (int)$input['album_id'];
    $current_user = $auth->getCurrentUser();
    
    // ตรวจสอบว่า album มีอยู่จริง
    $check_query = "SELECT a.*, u.full_name as created_by_name 
                    FROM albums a 
                    LEFT JOIN admin_users u ON a.created_by = u.id 
                    WHERE a.id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$album_id]);
    $album = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$album) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบ Album ที่ต้องการลบ']);
        exit();
    }
    
    // ตรวจสอบสิทธิ์ในการลบ (เฉพาะ admin หรือเจ้าของ album)
    if ($current_user['role'] !== 'admin' && $album['created_by'] != $current_user['id']) {
        echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ลบ Album นี้']);
        exit();
    }
    
    // เริ่ม transaction
    $db->beginTransaction();
    
    try {
        // ดึงรายการรูปภาพในอัลบั้ม
        $images_query = "SELECT image_path FROM album_images WHERE album_id = ?";
        $images_stmt = $db->prepare($images_query);
        $images_stmt->execute([$album_id]);
        $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ลบรูปภาพในฐานข้อมูลก่อน
        $delete_images_query = "DELETE FROM album_images WHERE album_id = ?";
        $delete_images_stmt = $db->prepare($delete_images_query);
        $delete_images_stmt->execute([$album_id]);
        
        // ลบ album
        $delete_album_query = "DELETE FROM albums WHERE id = ?";
        $delete_album_stmt = $db->prepare($delete_album_query);
        $delete_album_stmt->execute([$album_id]);
        
        // Commit transaction
        $db->commit();
        
        // ลบไฟล์รูปภาพจริงหลังจาก commit สำเร็จ
        $imageHandler = new ImageHandler();
        $deleted_files = 0;
        $failed_files = [];
        
        foreach ($images as $image) {
            $file_path = '../../' . $image['image_path'];
            if (file_exists($file_path)) {
                if ($imageHandler->deleteImage($file_path)) {
                    $deleted_files++;
                } else {
                    $failed_files[] = $image['image_path'];
                }
            }
        }
        
        // ลบโฟลเดอร์อัลบั้มถ้าว่าง
        $album_dir = "../../uploads/albums/album_{$album_id}/";
        if (is_dir($album_dir)) {
            $files = array_diff(scandir($album_dir), ['.', '..']);
            if (empty($files)) {
                rmdir($album_dir);
            }
        }
        
        $message = "ลบ Album สำเร็จ";
        if (!empty($failed_files)) {
            $message .= " (ไม่สามารถลบไฟล์บางไฟล์ได้: " . count($failed_files) . " ไฟล์)";
        }
        
        // บันทึก log การลบ
        $log_query = "INSERT INTO album_deletion_logs (album_id, album_title, deleted_by, deleted_at, image_count) 
                      VALUES (?, ?, ?, NOW(), ?)";
        $log_stmt = $db->prepare($log_query);
        
        // ใช้ try-catch เพื่อไม่ให้ log ทำให้การลบล้มเหลว (ในกรณีที่ตารางไม่มี)
        try {
            $log_stmt->execute([$album_id, $album['title'], $current_user['id'], count($images)]);
        } catch (Exception $log_error) {
            // ไม่ต้องทำอะไร หาก log ล้มเหลว
            error_log("Album deletion log failed: " . $log_error->getMessage());
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'details' => [
                'album_id' => $album_id,
                'album_title' => $album['title'],
                'images_count' => count($images),
                'deleted_files' => $deleted_files,
                'failed_files' => count($failed_files)
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Album deletion error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'เกิดข้อผิดพลาดในการลบ Album: ' . $e->getMessage()
    ]);
}
?>