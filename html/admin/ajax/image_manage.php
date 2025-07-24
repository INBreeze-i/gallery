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
    
    if (!isset($input['action'])) {
        echo json_encode(['success' => false, 'message' => 'ไม่ระบุ action']);
        exit();
    }
    
    $current_user = $auth->getCurrentUser();
    $action = $input['action'];
    
    switch ($action) {
        case 'reorder':
            if (!isset($input['album_id']) || !isset($input['image_ids']) || !is_array($input['image_ids'])) {
                echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
                exit();
            }
            
            $album_id = (int)$input['album_id'];
            $image_ids = array_map('intval', $input['image_ids']);
            
            // ตรวจสอบสิทธิ์ในการแก้ไข album
            $album_check = checkAlbumPermission($db, $album_id, $current_user);
            if (!$album_check['success']) {
                echo json_encode($album_check);
                exit();
            }
            
            // อัปเดตลำดับรูปภาพ
            $db->beginTransaction();
            try {
                for ($i = 0; $i < count($image_ids); $i++) {
                    $query = "UPDATE album_images SET image_order = ? WHERE id = ? AND album_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$i + 1, $image_ids[$i], $album_id]);
                }
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'จัดเรียงลำดับสำเร็จ']);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'set_cover':
            if (!isset($input['album_id']) || !isset($input['image_id'])) {
                echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
                exit();
            }
            
            $album_id = (int)$input['album_id'];
            $image_id = (int)$input['image_id'];
            
            // ตรวจสอบสิทธิ์
            $album_check = checkAlbumPermission($db, $album_id, $current_user);
            if (!$album_check['success']) {
                echo json_encode($album_check);
                exit();
            }
            
            // ตรวจสอบว่ารูปภาพอยู่ใน album นี้
            $image_check_query = "SELECT id FROM album_images WHERE id = ? AND album_id = ?";
            $image_check_stmt = $db->prepare($image_check_query);
            $image_check_stmt->execute([$image_id, $album_id]);
            if (!$image_check_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'ไม่พบรูปภาพในอัลบั้มนี้']);
                exit();
            }
            
            $db->beginTransaction();
            try {
                // ยกเลิกการเป็นปกของรูปอื่น
                $unset_query = "UPDATE album_images SET is_cover = 0 WHERE album_id = ?";
                $unset_stmt = $db->prepare($unset_query);
                $unset_stmt->execute([$album_id]);
                
                // ตั้งรูปใหม่เป็นปก
                $set_query = "UPDATE album_images SET is_cover = 1 WHERE id = ?";
                $set_stmt = $db->prepare($set_query);
                $set_stmt->execute([$image_id]);
                
                // อัปเดต cover_image ใน table albums
                $cover_path_query = "SELECT image_path FROM album_images WHERE id = ?";
                $cover_path_stmt = $db->prepare($cover_path_query);
                $cover_path_stmt->execute([$image_id]);
                $cover_path = $cover_path_stmt->fetch(PDO::FETCH_ASSOC)['image_path'];
                
                $update_album_query = "UPDATE albums SET cover_image = ? WHERE id = ?";
                $update_album_stmt = $db->prepare($update_album_query);
                $update_album_stmt->execute([$cover_path, $album_id]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'ตั้งปก Album สำเร็จ']);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'get_image':
            if (!isset($input['image_id'])) {
                echo json_encode(['success' => false, 'message' => 'ไม่ระบุ image_id']);
                exit();
            }
            
            $image_id = (int)$input['image_id'];
            
            $query = "SELECT ai.*, a.created_by 
                     FROM album_images ai 
                     JOIN albums a ON ai.album_id = a.id 
                     WHERE ai.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$image_id]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$image) {
                echo json_encode(['success' => false, 'message' => 'ไม่พบรูปภาพ']);
                exit();
            }
            
            // ตรวจสอบสิทธิ์
            if ($current_user['role'] !== 'admin' && $image['created_by'] != $current_user['id']) {
                echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์แก้ไขรูปภาพนี้']);
                exit();
            }
            
            echo json_encode(['success' => true, 'image' => $image]);
            break;
            
        case 'update_image':
            if (!isset($input['image_id']) || !isset($input['title']) || !isset($input['description'])) {
                echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
                exit();
            }
            
            $image_id = (int)$input['image_id'];
            $title = trim($input['title']);
            $description = trim($input['description']);
            
            // ตรวจสอบสิทธิ์
            $image_check_query = "SELECT ai.*, a.created_by 
                                FROM album_images ai 
                                JOIN albums a ON ai.album_id = a.id 
                                WHERE ai.id = ?";
            $image_check_stmt = $db->prepare($image_check_query);
            $image_check_stmt->execute([$image_id]);
            $image = $image_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$image) {
                echo json_encode(['success' => false, 'message' => 'ไม่พบรูปภาพ']);
                exit();
            }
            
            if ($current_user['role'] !== 'admin' && $image['created_by'] != $current_user['id']) {
                echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์แก้ไขรูปภาพนี้']);
                exit();
            }
            
            // อัปเดตข้อมูลรูปภาพ
            $update_query = "UPDATE album_images SET image_title = ?, image_description = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$title, $description, $image_id]);
            
            echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลรูปภาพสำเร็จ']);
            break;
            
        case 'delete_image':
            if (!isset($input['image_id'])) {
                echo json_encode(['success' => false, 'message' => 'ไม่ระบุ image_id']);
                exit();
            }
            
            $image_id = (int)$input['image_id'];
            
            // ดึงข้อมูลรูปภาพ
            $image_query = "SELECT ai.*, a.created_by 
                           FROM album_images ai 
                           JOIN albums a ON ai.album_id = a.id 
                           WHERE ai.id = ?";
            $image_stmt = $db->prepare($image_query);
            $image_stmt->execute([$image_id]);
            $image = $image_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$image) {
                echo json_encode(['success' => false, 'message' => 'ไม่พบรูปภาพ']);
                exit();
            }
            
            // ตรวจสอบสิทธิ์
            if ($current_user['role'] !== 'admin' && $image['created_by'] != $current_user['id']) {
                echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ลบรูปภาพนี้']);
                exit();
            }
            
            $db->beginTransaction();
            try {
                // ลบจากฐานข้อมูล
                $delete_query = "DELETE FROM album_images WHERE id = ?";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->execute([$image_id]);
                
                // ถ้าเป็นปก ให้ตั้งรูปแรกเป็นปกใหม่
                if ($image['is_cover']) {
                    $new_cover_query = "SELECT id, image_path FROM album_images 
                                      WHERE album_id = ? 
                                      ORDER BY image_order, id 
                                      LIMIT 1";
                    $new_cover_stmt = $db->prepare($new_cover_query);
                    $new_cover_stmt->execute([$image['album_id']]);
                    $new_cover = $new_cover_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($new_cover) {
                        $set_new_cover_query = "UPDATE album_images SET is_cover = 1 WHERE id = ?";
                        $set_new_cover_stmt = $db->prepare($set_new_cover_query);
                        $set_new_cover_stmt->execute([$new_cover['id']]);
                        
                        $update_album_cover_query = "UPDATE albums SET cover_image = ? WHERE id = ?";
                        $update_album_cover_stmt = $db->prepare($update_album_cover_query);
                        $update_album_cover_stmt->execute([$new_cover['image_path'], $image['album_id']]);
                    } else {
                        // ไม่มีรูปเหลือ ให้ลบ cover_image
                        $update_album_cover_query = "UPDATE albums SET cover_image = NULL WHERE id = ?";
                        $update_album_cover_stmt = $db->prepare($update_album_cover_query);
                        $update_album_cover_stmt->execute([$image['album_id']]);
                    }
                }
                
                $db->commit();
                
                // ลบไฟล์จริง
                $imageHandler = new ImageHandler();
                $file_path = '../../' . $image['image_path'];
                $imageHandler->deleteImage($file_path);
                
                echo json_encode(['success' => true, 'message' => 'ลบรูปภาพสำเร็จ']);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Image manage error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()
    ]);
}

// ฟังก์ชันตรวจสอบสิทธิ์ album
function checkAlbumPermission($db, $album_id, $current_user) {
    $query = "SELECT created_by FROM albums WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$album_id]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$album) {
        return ['success' => false, 'message' => 'ไม่พบ Album'];
    }
    
    if ($current_user['role'] !== 'admin' && $album['created_by'] != $current_user['id']) {
        return ['success' => false, 'message' => 'คุณไม่มีสิทธิ์แก้ไข Album นี้'];
    }
    
    return ['success' => true];
}
?>