<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

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
    
    // เตรียมพารามิเตอร์สำหรับการกรอง
    $filters = [
        'search' => isset($input['search']) ? trim($input['search']) : '',
        'categories' => isset($input['categories']) && is_array($input['categories']) ? array_map('intval', $input['categories']) : [],
        'status' => isset($input['status']) ? $input['status'] : '',
        'sort' => isset($input['sort']) ? $input['sort'] : 'created_at',
        'order' => isset($input['order']) ? $input['order'] : 'DESC',
        'page' => isset($input['page']) ? (int)$input['page'] : 1,
        'per_page' => isset($input['per_page']) ? (int)$input['per_page'] : 10
    ];
    
    // ดึงข้อมูลอัลบั้มที่กรองแล้ว
    $result = $album_model->getAlbumsWithMultiCategoryFilter($filters);
    
    // สร้าง HTML สำหรับตารางอัลบั้ม
    $albums_html = '';
    
    if (empty($result['albums'])) {
        $albums_html = '
        <div class="text-center py-12">
            <i class="fas fa-folder-open text-6xl text-gray-400 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">ไม่มี Albums</h3>
            <p class="text-gray-500 mb-4">ไม่พบ Albums ที่ตรงกับเงื่อนไขที่ค้นหา</p>
        </div>';
    } else {
        $albums_html .= '<div class="overflow-x-auto">
                            <table class="w-full table-hover">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left">
                                            <button class="sort-btn flex items-center text-sm font-medium text-gray-700 hover:text-blue-600" data-sort="title">
                                                Album
                                                <i class="fas fa-sort' . ($filters['sort'] === 'title' ? ($filters['order'] === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow') . '"></i>
                                            </button>
                                        </th>
                                        <th class="px-6 py-4 text-left">
                                            <button class="sort-btn flex items-center text-sm font-medium text-gray-700 hover:text-blue-600" data-sort="category_name">
                                                หมวดหมู่
                                                <i class="fas fa-sort' . ($filters['sort'] === 'category_name' ? ($filters['order'] === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow') . '"></i>
                                            </button>
                                        </th>
                                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">รูปภาพ</th>
                                        <th class="px-6 py-4 text-center">
                                            <button class="sort-btn flex items-center justify-center text-sm font-medium text-gray-700 hover:text-blue-600" data-sort="view_count">
                                                ดู
                                                <i class="fas fa-sort' . ($filters['sort'] === 'view_count' ? ($filters['order'] === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow') . '"></i>
                                            </button>
                                        </th>
                                        <th class="px-6 py-4 text-center">
                                            <button class="sort-btn flex items-center justify-center text-sm font-medium text-gray-700 hover:text-blue-600" data-sort="status">
                                                สถานะ
                                                <i class="fas fa-sort' . ($filters['sort'] === 'status' ? ($filters['order'] === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow') . '"></i>
                                            </button>
                                        </th>
                                        <th class="px-6 py-4 text-center">
                                            <button class="sort-btn flex items-center justify-center text-sm font-medium text-gray-700 hover:text-blue-600" data-sort="created_at">
                                                วันที่สร้าง
                                                <i class="fas fa-sort' . ($filters['sort'] === 'created_at' ? ($filters['order'] === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow') . '"></i>
                                            </button>
                                        </th>
                                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">';
        
        foreach ($result['albums'] as $album) {
            $albums_html .= '
                <tr>
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            ' . ($album['cover_image_path'] ? 
                                '<img src="../' . htmlspecialchars($album['cover_image_path']) . '" alt="Cover" class="w-12 h-12 rounded-lg object-cover mr-4">' : 
                                '<div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-4"><i class="fas fa-image text-gray-400"></i></div>'
                            ) . '
                            <div>
                                <div class="font-medium text-gray-900">' . htmlspecialchars($album['title']) . '</div>
                                <div class="text-sm text-gray-500 max-w-xs truncate">' . htmlspecialchars($album['description']) . '</div>
                                ' . ($album['created_by_name'] ? '<div class="text-xs text-gray-400">โดย ' . htmlspecialchars($album['created_by_name']) . '</div>' : '') . '
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="status-badge" style="background-color: ' . htmlspecialchars($album['category_color'] . '20') . '; color: ' . htmlspecialchars($album['category_color']) . ';">
                            <i class="' . htmlspecialchars($album['category_icon']) . ' mr-1"></i>' . htmlspecialchars($album['category_name']) . '
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm font-medium text-gray-900">' . number_format($album['image_count']) . '</span>
                        <div class="text-xs text-gray-500">รูป</div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm font-medium text-gray-900">' . number_format($album['view_count']) . '</span>
                        <div class="text-xs text-gray-500">ครั้ง</div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="status-badge ' . ($album['status'] === 'active' ? 'status-active' : 'status-inactive') . '">
                            <i class="fas fa-circle text-xs mr-1"></i>' . ($album['status'] === 'active' ? 'เผยแพร่' : 'ไม่เผยแพร่') . '
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center text-sm text-gray-500">
                        ' . date('d/m/Y', strtotime($album['created_at'])) . '
                        <div class="text-xs">' . date('H:i', strtotime($album['created_at'])) . '</div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center space-x-2">
                            <a href="../album_detail.php?id=' . $album['id'] . '" target="_blank" class="text-blue-600 hover:text-blue-800 p-1 rounded transition-colors" title="ดู Album">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="album_edit.php?id=' . $album['id'] . '" class="text-green-600 hover:text-green-800 p-1 rounded transition-colors" title="แก้ไข">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button onclick="deleteAlbum(' . $album['id'] . ', \'' . htmlspecialchars($album['title'], ENT_QUOTES) . '\')" class="text-red-600 hover:text-red-800 p-1 rounded transition-colors" title="ลบ">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>';
        }
        
        $albums_html .= '</tbody></table></div>';
    }
    
    // สร้าง pagination HTML
    $pagination_html = '';
    if ($result['total_pages'] > 1) {
        $pagination_html = '<div class="bg-gray-50 px-6 py-4 border-t">
                              <div class="flex items-center justify-between">
                                  <div class="text-sm text-gray-700">
                                      แสดง <span class="font-medium">' . number_format(($result['current_page'] - 1) * $result['per_page'] + 1) . '</span> 
                                      ถึง <span class="font-medium">' . number_format(min($result['current_page'] * $result['per_page'], $result['total_records'])) . '</span> 
                                      จากทั้งหมด <span class="font-medium">' . number_format($result['total_records']) . '</span> รายการ
                                  </div>
                                  <div class="flex space-x-1">';
        
        // Previous page
        if ($result['current_page'] > 1) {
            $pagination_html .= '<button class="pagination-btn px-3 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50" data-page="' . ($result['current_page'] - 1) . '">
                                   <i class="fas fa-chevron-left"></i>
                                 </button>';
        }
        
        // Page numbers
        $start_page = max(1, $result['current_page'] - 2);
        $end_page = min($result['total_pages'], $result['current_page'] + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $result['current_page']) {
                $pagination_html .= '<span class="px-3 py-2 rounded-lg bg-blue-600 text-white font-medium">' . $i . '</span>';
            } else {
                $pagination_html .= '<button class="pagination-btn px-3 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50" data-page="' . $i . '">' . $i . '</button>';
            }
        }
        
        // Next page
        if ($result['current_page'] < $result['total_pages']) {
            $pagination_html .= '<button class="pagination-btn px-3 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50" data-page="' . ($result['current_page'] + 1) . '">
                                   <i class="fas fa-chevron-right"></i>
                                 </button>';
        }
        
        $pagination_html .= '</div></div></div>';
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'albums_html' => $albums_html,
            'pagination_html' => $pagination_html,
            'total_records' => $result['total_records'],
            'total_pages' => $result['total_pages'],
            'current_page' => $result['current_page'],
            'filters' => $filters
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Category filter error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'เกิดข้อผิดพลาดในการกรองข้อมูล: ' . $e->getMessage()
    ]);
}
?>