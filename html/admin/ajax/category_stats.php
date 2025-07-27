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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'message' => 'HTTP method ไม่ถูกต้อง']);
        exit();
    }
    
    // ดึงสถิติหมวดหมู่
    $stats_stmt = $album_model->getCategoryStatistics();
    $statistics = [];
    
    while ($row = $stats_stmt->fetch(PDO::FETCH_ASSOC)) {
        $statistics[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'icon' => $row['icon'],
            'color' => $row['color'],
            'album_count' => (int)$row['album_count'],
            'total_views' => (int)$row['total_views'],
            'total_images' => (int)$row['total_images'],
            'earliest_album' => $row['earliest_album'],
            'latest_album' => $row['latest_album'],
            'avg_views_per_album' => $row['album_count'] > 0 ? round($row['total_views'] / $row['album_count'], 1) : 0
        ];
    }
    
    // คำนวณสถิติรวม
    $total_stats = [
        'total_categories' => count($statistics),
        'total_albums' => array_sum(array_column($statistics, 'album_count')),
        'total_views' => array_sum(array_column($statistics, 'total_views')),
        'total_images' => array_sum(array_column($statistics, 'total_images'))
    ];
    
    // สร้าง HTML สำหรับแสดงสถิติ
    $stats_html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';
    
    // การ์ดสถิติรวม
    $stats_html .= '
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-blue-600 text-white p-3 rounded-lg">
                    <i class="fas fa-folder text-xl"></i>
                </div>
                <div class="ml-4">
                    <div class="text-sm text-blue-600 font-medium">หมวดหมู่ทั้งหมด</div>
                    <div class="text-2xl font-bold text-blue-800">' . number_format($total_stats['total_categories']) . '</div>
                </div>
            </div>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-green-600 text-white p-3 rounded-lg">
                    <i class="fas fa-images text-xl"></i>
                </div>
                <div class="ml-4">
                    <div class="text-sm text-green-600 font-medium">Albums ทั้งหมด</div>
                    <div class="text-2xl font-bold text-green-800">' . number_format($total_stats['total_albums']) . '</div>
                </div>
            </div>
        </div>
        
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-purple-600 text-white p-3 rounded-lg">
                    <i class="fas fa-photo-video text-xl"></i>
                </div>
                <div class="ml-4">
                    <div class="text-sm text-purple-600 font-medium">รูปภาพทั้งหมด</div>
                    <div class="text-2xl font-bold text-purple-800">' . number_format($total_stats['total_images']) . '</div>
                </div>
            </div>
        </div>
        
        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-orange-600 text-white p-3 rounded-lg">
                    <i class="fas fa-eye text-xl"></i>
                </div>
                <div class="ml-4">
                    <div class="text-sm text-orange-600 font-medium">การดูทั้งหมด</div>
                    <div class="text-2xl font-bold text-orange-800">' . number_format($total_stats['total_views']) . '</div>
                </div>
            </div>
        </div>
    </div>';
    
    // ตารางรายละเอียดหมวดหมู่
    $stats_html .= '
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">สถิติแต่ละหมวดหมู่</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">หมวดหมู่</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Albums</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">รูปภาพ</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">การดู</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">เฉลี่ย/Album</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ความนิยม</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">';
    
    foreach ($statistics as $stat) {
        $popularity = '';
        if ($stat['total_views'] > 500) {
            $popularity = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">สูง</span>';
        } elseif ($stat['total_views'] > 100) {
            $popularity = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">ปานกลาง</span>';
        } else {
            $popularity = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">ต่ำ</span>';
        }
        
        $stats_html .= '
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: ' . htmlspecialchars($stat['color']) . '20; color: ' . htmlspecialchars($stat['color']) . ';">
                                <i class="' . htmlspecialchars($stat['icon']) . '"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">' . htmlspecialchars($stat['name']) . '</div>
                            <div class="text-sm text-gray-500">' . htmlspecialchars($stat['description']) . '</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="text-sm font-medium text-gray-900">' . number_format($stat['album_count']) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="text-sm font-medium text-gray-900">' . number_format($stat['total_images']) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="text-sm font-medium text-gray-900">' . number_format($stat['total_views']) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="text-sm font-medium text-gray-900">' . number_format($stat['avg_views_per_album'], 1) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    ' . $popularity . '
                </td>
            </tr>';
    }
    
    $stats_html .= '</tbody></table></div></div>';
    
    echo json_encode([
        'success' => true,
        'data' => [
            'statistics' => $statistics,
            'total_stats' => $total_stats,
            'stats_html' => $stats_html
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Category statistics error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'เกิดข้อผิดพลาดในการดึงสถิติ: ' . $e->getMessage()
    ]);
}
?>