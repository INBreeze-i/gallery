<?php
include_once '../config/database.php';
include_once '../models/Album.php';
include_once 'Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// ตรวจสอบสิทธิ์การเข้าถึง
$auth->requireLogin();

$current_user = $auth->getCurrentUser();

// การค้นหาและกรอง
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// สร้าง WHERE clause
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category_filter > 0) {
    $where_conditions[] = "a.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// นับจำนวนรวม
$count_query = "SELECT COUNT(*) as total 
                FROM albums a 
                JOIN album_categories c ON a.category_id = c.id 
                WHERE {$where_clause}";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// ดึงข้อมูลอัลบั้ม
$valid_sorts = ['title', 'created_at', 'view_count', 'category_name', 'status'];
$sort_field = in_array($sort, $valid_sorts) ? $sort : 'created_at';
if ($sort_field === 'category_name') {
    $sort_field = 'c.name';
} elseif ($sort_field === 'title') {
    $sort_field = 'a.title';
} elseif ($sort_field === 'created_at') {
    $sort_field = 'a.created_at';
} elseif ($sort_field === 'view_count') {
    $sort_field = 'a.view_count';
} elseif ($sort_field === 'status') {
    $sort_field = 'a.status';
}

$albums_query = "SELECT a.*, c.name as category_name, c.color as category_color,
                        (SELECT COUNT(*) FROM album_images WHERE album_id = a.id) as image_count,
                        (SELECT image_path FROM album_images WHERE album_id = a.id AND is_cover = 1 LIMIT 1) as cover_image_path,
                        u.full_name as created_by_name
                 FROM albums a 
                 JOIN album_categories c ON a.category_id = c.id 
                 LEFT JOIN admin_users u ON a.created_by = u.id
                 WHERE {$where_clause}
                 ORDER BY {$sort_field} {$order}
                 LIMIT {$per_page} OFFSET {$offset}";

$albums_stmt = $db->prepare($albums_query);
$albums_stmt->execute($params);
$albums = $albums_stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงรายการหมวดหมู่สำหรับ filter
$categories_query = "SELECT * FROM album_categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการ Albums - Albums Management</title>
     <link rel="stylesheet" href="../dist/output.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table-hover tbody tr:hover {
            background-color: #f8fafc;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .sort-arrow {
            opacity: 0.5;
            margin-left: 0.25rem;
        }
        .sort-active {
            opacity: 1;
            color: #3b82f6;
        }
        
        /* Enhanced Filter Styles */
        .category-item:hover {
            background-color: #f9fafb;
            transform: translateX(2px);
            transition: all 0.2s ease;
        }
        
        .category-checkbox:checked + .ml-2 {
            background-color: #eff6ff;
            border-radius: 0.5rem;
            padding: 0.25rem;
        }
        
        /* Loading animations */
        .loading-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }
        
        /* Smooth transitions */
        #albumsTableContainer {
            transition: opacity 0.3s ease;
        }
        
        /* Custom scrollbar for category list */
        #categoryList::-webkit-scrollbar {
            width: 6px;
        }
        
        #categoryList::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        #categoryList::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        #categoryList::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 1024px) {
            .lg\\:col-span-1 {
                order: 2;
            }
            
            .lg\\:col-span-3 {
                order: 1;
            }
        }
        
        /* Breadcrumb animations */
        #filterBreadcrumbs {
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Enhanced hover effects */
        .sort-btn:hover {
            background-color: #f8fafc;
            border-radius: 0.375rem;
            padding: 0.25rem;
            margin: -0.25rem;
        }
        
        .pagination-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        /* Stats panel animations */
        #statsPanel {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
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
                        <h1 class="text-xl font-bold text-gray-800">จัดการ Albums</h1>
                        <p class="text-sm text-gray-600">รายการ Albums ทั้งหมด</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm text-gray-600">ผู้ใช้:</div>
                        <div class="font-medium text-gray-800">
                            <?php echo htmlspecialchars($current_user['full_name']); ?>
                        </div>
                    </div>
                    <a href="album_create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>สร้าง Album ใหม่
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- Enhanced Filters Section -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
            <!-- Filter Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <!-- Filter Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold">
                                <i class="fas fa-filter mr-2"></i>ตัวกรอง
                            </h3>
                            <button id="toggleStats" class="text-white hover:text-gray-200 transition-colors">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Search -->
                    <div class="p-4 border-b">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search mr-1 text-blue-600"></i>ค้นหา
                        </label>
                        <input type="text" id="searchInput" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="ค้นหาชื่อหรือรายละเอียด Album..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    
                    <!-- Multi-Category Filter -->
                    <div class="p-4 border-b">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-sm font-medium text-gray-700">
                                <i class="fas fa-tags mr-1 text-blue-600"></i>หมวดหมู่
                            </label>
                            <div class="space-x-2">
                                <button id="selectAllCategories" class="text-xs text-blue-600 hover:text-blue-800">เลือกทั้งหมด</button>
                                <button id="clearAllCategories" class="text-xs text-gray-600 hover:text-gray-800">ล้าง</button>
                            </div>
                        </div>
                        
                        <!-- Category Search -->
                        <input type="text" id="categorySearch" placeholder="ค้นหาหมวดหมู่..."
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm mb-3 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        
                        <!-- Category Checkboxes -->
                        <div id="categoryList" class="space-y-2 max-h-48 overflow-y-auto">
                            <?php foreach ($categories as $category): ?>
                                <label class="flex items-center p-2 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer category-item">
                                    <input type="checkbox" value="<?php echo $category['id']; ?>" 
                                           class="category-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           <?php echo $category_filter == $category['id'] ? 'checked' : ''; ?>>
                                    <div class="ml-2 flex items-center flex-1">
                                        <div class="w-4 h-4 rounded mr-2" style="background-color: <?php echo htmlspecialchars($category['color']); ?>"></div>
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-900 category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                                            <div class="text-xs text-gray-500 category-description"><?php echo htmlspecialchars($category['description'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="p-4 border-b">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-toggle-on mr-1 text-blue-600"></i>สถานะ
                        </label>
                        <select id="statusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            <option value="">ทุกสถานะ</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>เผยแพร่</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>ไม่เผยแพร่</option>
                        </select>
                    </div>
                    
                    <!-- Sort Options -->
                    <div class="p-4 border-b">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sort mr-1 text-blue-600"></i>เรียงลำดับ
                        </label>
                        <div class="space-y-2">
                            <select id="sortField" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>วันที่สร้าง</option>
                                <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>ชื่อ Album</option>
                                <option value="category_name" <?php echo $sort === 'category_name' ? 'selected' : ''; ?>>หมวดหมู่</option>
                                <option value="view_count" <?php echo $sort === 'view_count' ? 'selected' : ''; ?>>จำนวนดู</option>
                                <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>สถานะ</option>
                            </select>
                            <div class="flex rounded-lg border border-gray-300 overflow-hidden">
                                <button id="sortAsc" class="flex-1 px-3 py-2 text-sm bg-white hover:bg-gray-50 transition-colors <?php echo $order === 'ASC' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                                    <i class="fas fa-sort-amount-up mr-1"></i>น้อย-มาก
                                </button>
                                <button id="sortDesc" class="flex-1 px-3 py-2 text-sm bg-white hover:bg-gray-50 transition-colors border-l <?php echo $order === 'DESC' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                                    <i class="fas fa-sort-amount-down mr-1"></i>มาก-น้อย
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Actions -->
                    <div class="p-4">
                        <div class="space-y-2">
                            <button id="applyFilters" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                                <i class="fas fa-search mr-2"></i>ใช้ตัวกรอง
                            </button>
                            <button id="clearFilters" class="w-full bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors text-sm">
                                <i class="fas fa-times mr-2"></i>ล้างตัวกรอง
                            </button>
                            <div class="flex space-x-2">
                                <button id="saveFilterState" class="flex-1 bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 transition-colors text-xs">
                                    <i class="fas fa-save mr-1"></i>บันทึก
                                </button>
                                <button id="loadFilterState" class="flex-1 bg-purple-600 text-white px-3 py-2 rounded-lg hover:bg-purple-700 transition-colors text-xs">
                                    <i class="fas fa-download mr-1"></i>โหลด
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Panel (Hidden by default) -->
                <div id="statsPanel" class="mt-4 hidden">
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-4">
                            <h3 class="text-lg font-semibold">
                                <i class="fas fa-chart-bar mr-2"></i>สถิติหมวดหมู่
                            </h3>
                        </div>
                        <div id="statsContent" class="p-4">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
                                <p class="text-gray-500 mt-2">กำลังโหลดสถิติ...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="lg:col-span-3">
                <!-- Filter Breadcrumbs -->
                <div id="filterBreadcrumbs" class="bg-white rounded-lg shadow-sm p-4 mb-4 hidden">
                    <div class="flex items-center text-sm">
                        <span class="text-gray-500 mr-2">ตัวกรองที่ใช้:</span>
                        <div id="breadcrumbContent" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <!-- Results Summary -->
                <div class="mb-4">
                    <div class="flex justify-between items-center">
                        <div id="resultsSummary" class="text-gray-600">
                            แสดง Albums <?php echo number_format($offset + 1); ?>-<?php echo number_format(min($offset + $per_page, $total_records)); ?> 
                            จากทั้งหมด <?php echo number_format($total_records); ?> รายการ
                            <?php if (!empty($search)): ?>
                                <span class="text-blue-600">(ค้นหา: "<?php echo htmlspecialchars($search); ?>")</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center space-x-4">
                            <button id="exportResults" class="hidden bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors text-sm">
                                <i class="fas fa-download mr-2"></i>ส่งออก CSV
                            </button>
                            <div id="loadingIndicator" class="hidden">
                                <i class="fas fa-spinner fa-spin text-blue-600"></i>
                                <span class="text-sm text-gray-600 ml-2">กำลังโหลด...</span>
                            </div>
                            <div class="text-sm text-gray-500">
                                หน้า <span id="currentPage"><?php echo $page; ?></span> จาก <span id="totalPages"><?php echo $total_pages; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Albums Table Container -->
                <div id="albumsTableContainer" class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Albums Table Container -->
                <div id="albumsTableContainer" class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <?php if (empty($albums)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-folder-open text-6xl text-gray-400 mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">ไม่มี Albums</h3>
                            <p class="text-gray-500 mb-4">
                                <?php if (!empty($search) || $category_filter || !empty($status_filter)): ?>
                                    ไม่พบ Albums ที่ตรงกับเงื่อนไขที่ค้นหา
                                <?php else: ?>
                                    ยังไม่มี Albums ในระบบ
                                <?php endif; ?>
                            </p>
                            <a href="album_create.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>สร้าง Album แรก
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full table-hover">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left">
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'title', 'order' => ($sort === 'title' && $order === 'ASC') ? 'desc' : 'asc'])); ?>" 
                                               class="flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                                Album
                                                <i class="fas fa-sort<?php echo $sort === 'title' ? ($order === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow'; ?>"></i>
                                            </a>
                                        </th>
                                        <th class="px-6 py-4 text-left">
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'category_name', 'order' => ($sort === 'category_name' && $order === 'ASC') ? 'desc' : 'asc'])); ?>" 
                                               class="flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                                หมวดหมู่
                                                <i class="fas fa-sort<?php echo $sort === 'category_name' ? ($order === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow'; ?>"></i>
                                            </a>
                                        </th>
                                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">รูปภาพ</th>
                                        <th class="px-6 py-4 text-center">
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'view_count', 'order' => ($sort === 'view_count' && $order === 'ASC') ? 'desc' : 'asc'])); ?>" 
                                               class="flex items-center justify-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                                ดู
                                                <i class="fas fa-sort<?php echo $sort === 'view_count' ? ($order === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow'; ?>"></i>
                                            </a>
                                        </th>
                                        <th class="px-6 py-4 text-center">
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'status', 'order' => ($sort === 'status' && $order === 'ASC') ? 'desc' : 'asc'])); ?>" 
                                               class="flex items-center justify-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                                สถานะ
                                                <i class="fas fa-sort<?php echo $sort === 'status' ? ($order === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow'; ?>"></i>
                                            </a>
                                        </th>
                                        <th class="px-6 py-4 text-center">
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => ($sort === 'created_at' && $order === 'ASC') ? 'desc' : 'asc'])); ?>" 
                                               class="flex items-center justify-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                                วันที่สร้าง
                                                <i class="fas fa-sort<?php echo $sort === 'created_at' ? ($order === 'ASC' ? '-up sort-active' : '-down sort-active') : ' sort-arrow'; ?>"></i>
                                            </a>
                                        </th>
                                        <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($albums as $album): ?>
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <?php if ($album['cover_image_path']): ?>
                                                        <img src="../<?php echo htmlspecialchars($album['cover_image_path']); ?>" 
                                                             alt="Cover" class="w-12 h-12 rounded-lg object-cover mr-4">
                                                    <?php else: ?>
                                                        <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                                                            <i class="fas fa-image text-gray-400"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($album['title']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500 max-w-xs truncate">
                                                            <?php echo htmlspecialchars($album['description']); ?>
                                                        </div>
                                                        <?php if ($album['created_by_name']): ?>
                                                            <div class="text-xs text-gray-400">
                                                                โดย <?php echo htmlspecialchars($album['created_by_name']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="status-badge" style="background-color: <?php echo htmlspecialchars($album['category_color'] . '20'); ?>; color: <?php echo htmlspecialchars($album['category_color']); ?>;">
                                                    <?php echo htmlspecialchars($album['category_name']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="text-sm font-medium text-gray-900">
                                                    <?php echo number_format($album['image_count']); ?>
                                                </span>
                                                <div class="text-xs text-gray-500">รูป</div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="text-sm font-medium text-gray-900">
                                                    <?php echo number_format($album['view_count']); ?>
                                                </span>
                                                <div class="text-xs text-gray-500">ครั้ง</div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="status-badge <?php echo $album['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                    <i class="fas fa-circle text-xs mr-1"></i>
                                                    <?php echo $album['status'] === 'active' ? 'เผยแพร่' : 'ไม่เผยแพร่'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center text-sm text-gray-500">
                                                <?php echo date('d/m/Y', strtotime($album['created_at'])); ?>
                                                <div class="text-xs">
                                                    <?php echo date('H:i', strtotime($album['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <a href="../album_detail.php?id=<?php echo $album['id']; ?>" 
                                                       target="_blank"
                                                       class="text-blue-600 hover:text-blue-800 p-1 rounded transition-colors"
                                                       title="ดู Album">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="album_edit.php?id=<?php echo $album['id']; ?>" 
                                                       class="text-green-600 hover:text-green-800 p-1 rounded transition-colors"
                                                       title="แก้ไข">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="deleteAlbum(<?php echo $album['id']; ?>, '<?php echo htmlspecialchars($album['title'], ENT_QUOTES); ?>')" 
                                                            class="text-red-600 hover:text-red-800 p-1 rounded transition-colors"
                                                            title="ลบ">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="bg-gray-50 px-6 py-4 border-t">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-700">
                                        แสดง <span class="font-medium"><?php echo number_format($offset + 1); ?></span> 
                                        ถึง <span class="font-medium"><?php echo number_format(min($offset + $per_page, $total_records)); ?></span> 
                                        จากทั้งหมด <span class="font-medium"><?php echo number_format($total_records); ?></span> รายการ
                                    </div>
                                    <div class="flex space-x-1">
                                        <!-- Previous Page -->
                                        <?php if ($page > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                               class="px-3 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Page Numbers -->
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        if ($start_page > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                                               class="px-3 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">1</a>
                                            <?php if ($start_page > 2): ?>
                                                <span class="px-3 py-2 text-gray-500">...</span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <span class="px-3 py-2 rounded-lg bg-blue-600 text-white font-medium"><?php echo $i; ?></span>
                                            <?php else: ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                                   class="px-3 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                                <span class="px-3 py-2 text-gray-500">...</span>
                                            <?php endif; ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                                               class="px-3 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"><?php echo $total_pages; ?></a>
                                        <?php endif; ?>

                                        <!-- Next Page -->
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                               class="px-3 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-6">
            <div class="flex items-center mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl mr-3"></i>
                <h3 class="text-lg font-semibold text-gray-900">ยืนยันการลบ Album</h3>
            </div>
            <p class="text-gray-600 mb-6">
                คุณต้องการลบ Album "<span id="deleteAlbumName" class="font-medium"></span>" ใช่หรือไม่?
                <br><br>
                <strong class="text-red-600">การดำเนินการนี้ไม่สามารถยกเลิกได้</strong>
            </p>
            <div class="flex space-x-4">
                <button onclick="confirmDelete()" class="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fas fa-trash mr-2"></i>ลบ Album
                </button>
                <button onclick="closeDeleteModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400 transition-colors">
                    ยกเลิก
                </button>
            </div>
        </div>
    </div>

    <script>
        let albumToDelete = null;
        let currentFilters = {
            search: '<?php echo htmlspecialchars($search); ?>',
            categories: <?php echo $category_filter ? '['.$category_filter.']' : '[]'; ?>,
            status: '<?php echo htmlspecialchars($status_filter); ?>',
            sort: '<?php echo htmlspecialchars($sort); ?>',
            order: '<?php echo htmlspecialchars($order); ?>',
            page: <?php echo $page; ?>
        };

        // Enhanced Album Management Functions
        function deleteAlbum(albumId, albumName) {
            albumToDelete = albumId;
            document.getElementById('deleteAlbumName').textContent = albumName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            albumToDelete = null;
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function confirmDelete() {
            if (albumToDelete) {
                // แสดง loading
                const modal = document.getElementById('deleteModal');
                modal.innerHTML = `
                    <div class="bg-white rounded-xl max-w-md w-full p-6 text-center">
                        <i class="fas fa-spinner fa-spin text-blue-600 text-3xl mb-4"></i>
                        <p class="text-gray-600">กำลังลบ Album...</p>
                    </div>
                `;

                // ส่งคำขอลบ
                fetch('ajax/album_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        album_id: albumToDelete
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('ลบ Album สำเร็จ!');
                        applyFilters(); // โหลดข้อมูลใหม่
                        closeDeleteModal();
                        window.location.reload(true);
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถลบ Album ได้'));
                        closeDeleteModal();
                    }
                })
                .catch(error => {
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                    closeDeleteModal();
                });
            }
        }

        // Enhanced Filter Functions
        function showLoading() {
            document.getElementById('loadingIndicator').classList.remove('hidden');
            document.getElementById('albumsTableContainer').style.opacity = '0.6';
        }

        function hideLoading() {
            document.getElementById('loadingIndicator').classList.add('hidden');
            document.getElementById('albumsTableContainer').style.opacity = '1';
        }

        function applyFilters(resetPage = true) {
            if (resetPage) {
                currentFilters.page = 1;
            }

            // รวบรวมข้อมูลจากฟอร์ม
            currentFilters.search = document.getElementById('searchInput').value.trim();
            currentFilters.categories = Array.from(document.querySelectorAll('.category-checkbox:checked')).map(cb => parseInt(cb.value));
            currentFilters.status = document.getElementById('statusFilter').value;
            currentFilters.sort = document.getElementById('sortField').value;
            currentFilters.order = document.querySelector('#sortAsc.bg-blue-50') ? 'ASC' : 'DESC';

            showLoading();
            updateBreadcrumbs();

            fetch('ajax/category_filter.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(currentFilters)
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    document.getElementById('albumsTableContainer').innerHTML = data.data.albums_html;
                    
                    // อัปเดต pagination และสถิติ
                    updateResultsSummary(data.data);
                    attachTableEventListeners();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading();
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                console.error('Filter error:', error);
            });
        }

        function updateResultsSummary(data) {
            const start = (data.current_page - 1) * data.filters.per_page + 1;
            const end = Math.min(data.current_page * data.filters.per_page, data.total_records);
            
            document.getElementById('resultsSummary').innerHTML = `
                แสดง Albums ${start.toLocaleString()}-${end.toLocaleString()} 
                จากทั้งหมด ${data.total_records.toLocaleString()} รายการ
                ${data.filters.search ? `<span class="text-blue-600">(ค้นหา: "${data.filters.search}")</span>` : ''}
            `;
            
            document.getElementById('currentPage').textContent = data.current_page;
            document.getElementById('totalPages').textContent = data.total_pages;
            
            // แสดงปุ่มส่งออกถ้ามีผลลัพธ์
            const exportBtn = document.getElementById('exportResults');
            if (data.total_records > 0) {
                exportBtn.classList.remove('hidden');
            } else {
                exportBtn.classList.add('hidden');
            }
        }

        function updateBreadcrumbs() {
            const breadcrumbContainer = document.getElementById('filterBreadcrumbs');
            const breadcrumbContent = document.getElementById('breadcrumbContent');
            let breadcrumbs = [];

            // ค้นหา
            if (currentFilters.search) {
                breadcrumbs.push(`<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">ค้นหา: "${currentFilters.search}"</span>`);
            }

            // หมวดหมู่
            if (currentFilters.categories.length > 0) {
                const categoryNames = Array.from(document.querySelectorAll('.category-checkbox:checked'))
                    .map(cb => cb.closest('.category-item').querySelector('.category-name').textContent);
                breadcrumbs.push(`<span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">หมวดหมู่: ${categoryNames.join(', ')}</span>`);
            }

            // สถานะ
            if (currentFilters.status) {
                const statusText = currentFilters.status === 'active' ? 'เผยแพร่' : 'ไม่เผยแพร่';
                breadcrumbs.push(`<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">สถานะ: ${statusText}</span>`);
            }

            if (breadcrumbs.length > 0) {
                breadcrumbContent.innerHTML = breadcrumbs.join('');
                breadcrumbContainer.classList.remove('hidden');
            } else {
                breadcrumbContainer.classList.add('hidden');
            }
        }

        function attachTableEventListeners() {
            // Sort buttons
            document.querySelectorAll('.sort-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentFilters.sort = btn.dataset.sort;
                    currentFilters.order = currentFilters.order === 'ASC' ? 'DESC' : 'ASC';
                    applyFilters(false);
                });
            });

            // Pagination buttons
            document.querySelectorAll('.pagination-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentFilters.page = parseInt(btn.dataset.page);
                    applyFilters(false);
                });
            });
        }

        function clearFilters() {
            // รีเซ็ตฟอร์ม
            document.getElementById('searchInput').value = '';
            document.querySelectorAll('.category-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('statusFilter').value = '';
            document.getElementById('sortField').value = 'created_at';
            document.getElementById('sortDesc').classList.add('bg-blue-50', 'text-blue-600');
            document.getElementById('sortAsc').classList.remove('bg-blue-50', 'text-blue-600');
            
            // รีเซ็ตตัวแปร
            currentFilters = {
                search: '',
                categories: [],
                status: '',
                sort: 'created_at',
                order: 'DESC',
                page: 1
            };

            applyFilters();
        }

        function loadCategoryStats() {
            const statsContent = document.getElementById('statsContent');
            statsContent.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
                    <p class="text-gray-500 mt-2">กำลังโหลดสถิติ...</p>
                </div>
            `;

            fetch('ajax/category_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statsContent.innerHTML = data.data.stats_html;
                } else {
                    statsContent.innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-red-400 text-2xl"></i>
                            <p class="text-red-500 mt-2">เกิดข้อผิดพลาดในการโหลดสถิติ</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Stats error:', error);
                statsContent.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle text-red-400 text-2xl"></i>
                        <p class="text-red-500 mt-2">เกิดข้อผิดพลาดในการเชื่อมต่อ</p>
                    </div>
                `;
            });
        }

        // Filter State Management
        function saveFilterState() {
            fetch('ajax/filter_state.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'save',
                    filters: currentFilters
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('บันทึกสถานะตัวกรองสำเร็จ', 'success');
                } else {
                    showNotification('เกิดข้อผิดพลาด: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Save filter state error:', error);
                showNotification('เกิดข้อผิดพลาดในการบันทึก', 'error');
            });
        }

        function loadFilterState() {
            fetch('ajax/filter_state.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'load'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.filters) {
                    // อัปเดตฟอร์ม
                    document.getElementById('searchInput').value = data.filters.search || '';
                    document.getElementById('statusFilter').value = data.filters.status || '';
                    document.getElementById('sortField').value = data.filters.sort || 'created_at';
                    
                    // อัปเดต sort order
                    if (data.filters.order === 'ASC') {
                        document.getElementById('sortAsc').click();
                    } else {
                        document.getElementById('sortDesc').click();
                    }
                    
                    // อัปเดต category checkboxes
                    document.querySelectorAll('.category-checkbox').forEach(cb => {
                        cb.checked = data.filters.categories && data.filters.categories.includes(parseInt(cb.value));
                    });
                    
                    // อัปเดต currentFilters และใช้ตัวกรอง
                    currentFilters = data.filters;
                    applyFilters();
                    
                    showNotification('โหลดสถานะตัวกรองสำเร็จ', 'success');
                } else {
                    showNotification('ไม่มีสถานะตัวกรองที่บันทึกไว้', 'info');
                }
            })
            .catch(error => {
                console.error('Load filter state error:', error);
                showNotification('เกิดข้อผิดพลาดในการโหลด', 'error');
            });
        }

        function exportResults() {
            const params = new URLSearchParams({
                search: currentFilters.search,
                categories: currentFilters.categories.join(','),
                status: currentFilters.status,
                sort: currentFilters.sort,
                order: currentFilters.order
            });
            
            showNotification('กำลังเตรียมไฟล์ส่งออก...', 'info');
            window.open('ajax/export_albums.php?' + params.toString(), '_blank');
        }

        function showNotification(message, type = 'info') {
            // สร้าง notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all duration-300 transform translate-x-full`;
            
            const colors = {
                'success': 'bg-green-500 text-white',
                'error': 'bg-red-500 text-white',
                'info': 'bg-blue-500 text-white',
                'warning': 'bg-yellow-500 text-black'
            };
            
            notification.className += ' ' + (colors[type] || colors.info);
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // แสดง notification
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);
            
            // ซ่อน notification หลัง 5 วินาที
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Category search
            document.getElementById('categorySearch').addEventListener('input', function() {
                const query = this.value.toLowerCase();
                document.querySelectorAll('.category-item').forEach(item => {
                    const name = item.querySelector('.category-name').textContent.toLowerCase();
                    const description = item.querySelector('.category-description').textContent.toLowerCase();
                    item.style.display = (name.includes(query) || description.includes(query)) ? '' : 'none';
                });
            });

            // Select/Clear all categories
            document.getElementById('selectAllCategories').addEventListener('click', function() {
                document.querySelectorAll('.category-checkbox:not([style*="display: none"])').forEach(cb => cb.checked = true);
            });

            document.getElementById('clearAllCategories').addEventListener('click', function() {
                document.querySelectorAll('.category-checkbox').forEach(cb => cb.checked = false);
            });

            // Filter controls
            document.getElementById('applyFilters').addEventListener('click', () => applyFilters());
            document.getElementById('clearFilters').addEventListener('click', clearFilters);
            
            // Filter state management
            document.getElementById('saveFilterState').addEventListener('click', saveFilterState);
            document.getElementById('loadFilterState').addEventListener('click', loadFilterState);
            
            // Export functionality
            document.getElementById('exportResults').addEventListener('click', exportResults);

            // Sort order buttons
            document.getElementById('sortAsc').addEventListener('click', function() {
                document.getElementById('sortDesc').classList.remove('bg-blue-50', 'text-blue-600');
                this.classList.add('bg-blue-50', 'text-blue-600');
                currentFilters.order = 'ASC';
            });

            document.getElementById('sortDesc').addEventListener('click', function() {
                document.getElementById('sortAsc').classList.remove('bg-blue-50', 'text-blue-600');
                this.classList.add('bg-blue-50', 'text-blue-600');
                currentFilters.order = 'DESC';
            });

            // Stats toggle
            document.getElementById('toggleStats').addEventListener('click', function() {
                const statsPanel = document.getElementById('statsPanel');
                if (statsPanel.classList.contains('hidden')) {
                    statsPanel.classList.remove('hidden');
                    loadCategoryStats();
                } else {
                    statsPanel.classList.add('hidden');
                }
            });

            // Auto-apply filters on input changes (debounced)
            let searchTimeout;
            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => applyFilters(), 500);
            });

            // Real-time category selection
            document.querySelectorAll('.category-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    setTimeout(() => applyFilters(), 100);
                });
            });

            // Status filter change
            document.getElementById('statusFilter').addEventListener('change', () => applyFilters());
            document.getElementById('sortField').addEventListener('change', () => applyFilters());

            // Modal controls
            document.getElementById('deleteModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDeleteModal();
                }
            });

            // Initialize breadcrumbs
            updateBreadcrumbs();
        });
    </script>
</body>
</html>