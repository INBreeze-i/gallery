<?php
include_once '../../config/database.php';
include_once '../../models/Album.php';
include_once '../Auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $album_model = new Album($db);
    
    // ตรวจสอบสิทธิ์การเข้าถึง
    if (!$auth->checkAccess()) {
        header('HTTP/1.1 403 Forbidden');
        exit('ไม่มีสิทธิ์เข้าถึง');
    }
    
    // รับพารามิเตอร์จาก URL
    $filters = [
        'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
        'categories' => isset($_GET['categories']) ? explode(',', $_GET['categories']) : [],
        'status' => isset($_GET['status']) ? $_GET['status'] : '',
        'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'created_at',
        'order' => isset($_GET['order']) ? $_GET['order'] : 'DESC',
        'page' => 1,
        'per_page' => 1000 // ส่งออกข้อมูลทั้งหมด
    ];
    
    // ดึงข้อมูลอัลบั้มที่กรองแล้ว
    $result = $album_model->getAlbumsWithMultiCategoryFilter($filters);
    
    // กำหนด header สำหรับการดาวน์โหลดไฟล์ CSV
    $filename = 'albums_export_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    // สร้างไฟล์ CSV
    $output = fopen('php://output', 'w');
    
    // เพิ่ม BOM สำหรับ UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header ของ CSV
    fputcsv($output, [
        'ID',
        'ชื่อ Album',
        'รายละเอียด',
        'หมวดหมู่',
        'สถานะ',
        'จำนวนรูปภาพ',
        'จำนวนการดู',
        'ผู้สร้าง',
        'วันที่สร้าง',
        'วันที่แก้ไขล่าสุด'
    ]);
    
    // ข้อมูลแต่ละแถว
    foreach ($result['albums'] as $album) {
        fputcsv($output, [
            $album['id'],
            $album['title'],
            $album['description'],
            $album['category_name'],
            $album['status'] === 'active' ? 'เผยแพร่' : 'ไม่เผยแพร่',
            $album['image_count'],
            $album['view_count'],
            $album['created_by_name'] ?? 'ไม่ทราบ',
            date('d/m/Y H:i:s', strtotime($album['created_at'])),
            date('d/m/Y H:i:s', strtotime($album['updated_at']))
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('เกิดข้อผิดพลาดในการส่งออกข้อมูล');
}
?>