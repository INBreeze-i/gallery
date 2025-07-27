<?php
header('Content-Type: application/json');

include_once '../../config/database.php';
include_once '../Auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    // ตรวจสอบสิทธิ์การเข้าถึง
    if (!$auth->checkAccess()) {
        echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';
    
    if ($action === 'save') {
        // บันทึกสถานะตัวกรอง
        $userId = $auth->getCurrentUser()['id'];
        $filterState = json_encode($input['filters']);
        
        $query = "INSERT INTO user_filter_preferences (user_id, filter_state, updated_at) 
                  VALUES (?, ?, NOW()) 
                  ON DUPLICATE KEY UPDATE 
                  filter_state = VALUES(filter_state), 
                  updated_at = NOW()";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$userId, $filterState]);
        
        echo json_encode(['success' => true, 'message' => 'บันทึกสถานะตัวกรองสำเร็จ']);
        
    } elseif ($action === 'load') {
        // โหลดสถานะตัวกรอง
        $userId = $auth->getCurrentUser()['id'];
        
        $query = "SELECT filter_state FROM user_filter_preferences WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'filters' => json_decode($result['filter_state'], true)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่มีสถานะตัวกรองที่บันทึกไว้']);
        }
        
    } elseif ($action === 'clear') {
        // ล้างสถานะตัวกรอง
        $userId = $auth->getCurrentUser()['id'];
        
        $query = "DELETE FROM user_filter_preferences WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$userId]);
        
        echo json_encode(['success' => true, 'message' => 'ล้างสถานะตัวกรองสำเร็จ']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง']);
    }
    
} catch (Exception $e) {
    error_log("Filter state error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'เกิดข้อผิดพลาดในการจัดการสถานะตัวกรอง: ' . $e->getMessage()
    ]);
}
?>