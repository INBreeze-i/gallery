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
    <script src="https://cdn.tailwindcss.com"></script>
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
        <!-- Filters and Search -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <form method="GET" class="space-y-4">
                <div class="flex flex-wrap gap-4 items-end">
                    <!-- Search -->
                    <div class="flex-1 min-w-64">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search mr-1 text-blue-600"></i>ค้นหา
                        </label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="ค้นหาชื่อหรือรายละเอียด Album..."
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="min-w-48">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-tags mr-1 text-blue-600"></i>หมวดหมู่
                        </label>
                        <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">ทุกหมวดหมู่</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="min-w-40">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-toggle-on mr-1 text-blue-600"></i>สถานะ
                        </label>
                        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">ทุกสถานะ</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>เผยแพร่</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>ไม่เผยแพร่</option>
                        </select>
                    </div>
                    
                    <!-- Search Button -->
                    <div>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>ค้นหา
                        </button>
                        <?php if (!empty($search) || $category_filter || !empty($status_filter)): ?>
                            <a href="album_manage.php" class="ml-2 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                                <i class="fas fa-times mr-2"></i>ล้าง
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sort controls -->
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
            </form>
        </div>

        <!-- Results Summary -->
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div class="text-gray-600">
                    แสดง Albums <?php echo number_format($offset + 1); ?>-<?php echo number_format(min($offset + $per_page, $total_records)); ?> 
                    จากทั้งหมด <?php echo number_format($total_records); ?> รายการ
                    <?php if (!empty($search)): ?>
                        <span class="text-blue-600">(ค้นหา: "<?php echo htmlspecialchars($search); ?>")</span>
                    <?php endif; ?>
                </div>
                <div class="text-sm text-gray-500">
                    หน้า <?php echo $page; ?> จาก <?php echo $total_pages; ?>
                </div>
            </div>
        </div>

        <!-- Albums Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
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
                        // แสดงความสำเร็จและ reload หน้า
                        alert('ลบ Album สำเร็จ!');
                        window.location.reload();
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

        // ปิด modal เมื่อคลิกนอก modal
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // ESC key ปิด modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>