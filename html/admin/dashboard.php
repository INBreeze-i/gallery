<?php
include_once '../config/database.php';
include_once '../models/Album.php';
include_once 'Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// ตรวจสอบสิทธิ์การเข้าถึง
$auth->requireLogin();

$album = new Album($db);
$current_user = $auth->getCurrentUser();

// สถิติ
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM albums WHERE status = 'active') as total_albums,
    (SELECT COUNT(*) FROM album_images) as total_images,
    (SELECT COUNT(*) FROM album_categories) as total_categories,
    (SELECT SUM(view_count) FROM albums) as total_views,
    (SELECT COUNT(*) FROM admin_users WHERE status = 'active') as total_users";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Albums ล่าสุด (แก้ไข query ให้ไม่ใช้ created_by ถ้ายังไม่มีข้อมูล)
$recent_albums_query = "SELECT a.*, c.name as category_name, c.color
                       FROM albums a 
                       JOIN album_categories c ON a.category_id = c.id 
                       WHERE a.status = 'active' 
                       ORDER BY a.created_at DESC 
                       LIMIT 5";
$recent_stmt = $db->prepare($recent_albums_query);
$recent_stmt->execute();
$recent_albums = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// กิจกรรมล่าสุด (Login logs)
$recent_activities_query = "SELECT l.*, u.full_name 
                           FROM login_logs l
                           LEFT JOIN admin_users u ON l.user_id = u.id
                           ORDER BY l.login_time DESC 
                           LIMIT 10";
$activities_stmt = $db->prepare($recent_activities_query);
$activities_stmt->execute();
$recent_activities = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Albums Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-images text-2xl text-blue-600"></i>
                    <h1 class="text-xl font-bold text-gray-800">Albums Management</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- User Info -->
                    <div class="flex items-center space-x-3">
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-800">
                                <?php echo htmlspecialchars($current_user['full_name']); ?>
                            </div>
                            <div class="text-xs text-gray-600">
                                <?php echo ucfirst($current_user['role']); ?> | 
                                Last: <?php echo $current_user['last_login'] ? date('d/m H:i', strtotime($current_user['last_login'])) : 'Never'; ?>
                            </div>
                        </div>
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-bold">
                                <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Dropdown Menu -->
                    <div class="relative group">
                        <button class="flex items-center text-gray-600 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100">
                            <i class="fas fa-cog"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-2"></i>Settings
                            </a>
                            <?php if ($current_user['role'] == 'admin'): ?>
                            <a href="users_manage.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-users mr-2"></i>Manage Users
                            </a>
                            <?php endif; ?>
                            <hr class="my-1">
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Welcome Message -->
    <div class="container mx-auto px-4 py-6">
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold mb-2">
                        Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>! 👋
                    </h2>
                    <p class="opacity-90">
                        Ready to manage your albums? Current time: 2025-07-23 04:00:10 UTC
                    </p>
                    <?php if (isset($_SESSION['auto_login'])): ?>
                    <p class="text-sm opacity-75 mt-1">
                        <i class="fas fa-magic mr-1"></i>You were automatically signed in
                    </p>
                    <?php endif; ?>
                </div>
                <div class="text-right">
                    <div class="text-lg font-semibold">Role: <?php echo ucfirst($current_user['role']); ?></div>
                    <div class="text-sm opacity-75">Member since: <?php echo date('M Y', strtotime($current_user['created_at'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <a href="album_create.php" class="bg-blue-600 text-white p-6 rounded-xl hover:bg-blue-700 transition-colors transform hover:scale-105">
                <div class="flex items-center">
                    <i class="fas fa-plus-circle text-3xl mr-4"></i>
                    <div>
                        <h3 class="text-lg font-semibold">สร้าง Album ใหม่</h3>
                        <p class="text-sm opacity-90">เพิ่ม Album และรูปภาพ</p>
                    </div>
                </div>
            </a>
            
            <a href="album_manage.php" class="bg-green-600 text-white p-6 rounded-xl hover:bg-green-700 transition-colors transform hover:scale-105">
                <div class="flex items-center">
                    <i class="fas fa-list text-3xl mr-4"></i>
                    <div>
                        <h3 class="text-lg font-semibold">จัดการ Albums</h3>
                        <p class="text-sm opacity-90">แก้ไข ลบ Albums</p>
                    </div>
                </div>
            </a>
            
            <a href="categories_manage.php" class="bg-purple-600 text-white p-6 rounded-xl hover:bg-purple-700 transition-colors transform hover:scale-105">
                <div class="flex items-center">
                    <i class="fas fa-tags text-3xl mr-4"></i>
                    <div>
                        <h3 class="text-lg font-semibold">จัดการหมวดหมู่</h3>
                        <p class="text-sm opacity-90">แก้ไขประเภท Albums</p>
                    </div>
                </div>
            </a>
            
            <a href="../index.php" target="_blank" class="bg-gray-600 text-white p-6 rounded-xl hover:bg-gray-700 transition-colors transform hover:scale-105">
                <div class="flex items-center">
                    <i class="fas fa-external-link-alt text-3xl mr-4"></i>
                    <div>
                        <h3 class="text-lg font-semibold">ดูเว็บไซต์</h3>
                        <p class="text-sm opacity-90">เปิดหน้าเว็บหลัก</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Albums ทั้งหมด</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo number_format($stats['total_albums']); ?></p>
                    </div>
                    <i class="fas fa-photo-video text-3xl text-blue-600"></i>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">รูปภาพทั้งหมด</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo number_format($stats['total_images']); ?></p>
                    </div>
                    <i class="fas fa-images text-3xl text-green-600"></i>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">หมวดหมู่</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo number_format($stats['total_categories']); ?></p>
                    </div>
                    <i class="fas fa-tags text-3xl text-purple-600"></i>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">จำนวนการดู</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo number_format($stats['total_views']); ?></p>
                    </div>
                    <i class="fas fa-eye text-3xl text-red-600"></i>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-indigo-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">ผู้ใช้งาน</p>
                        <p class="text-3xl font-bold text-indigo-600"><?php echo number_format($stats['total_users']); ?></p>
                    </div>
                    <i class="fas fa-users text-3xl text-indigo-600"></i>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Albums -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Albums ล่าสุด</h2>
                    <i class="fas fa-photo-video text-2xl text-blue-600"></i>
                </div>
                
                <div class="space-y-4">
                    <?php if (empty($recent_albums)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-folder-open text-4xl mb-4"></i>
                            <p>ยังไม่มี Albums</p>
                            <a href="album_create.php" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">
                                สร้าง Album แรก
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_albums as $album_item): ?>
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex-1">
                                <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($album_item['title']); ?></h3>
                                <div class="flex items-center space-x-3 text-sm text-gray-600 mt-1">
                                    <span class="<?php echo htmlspecialchars($album_item['color']); ?> text-white px-2 py-1 rounded text-xs">
                                        <?php echo htmlspecialchars($album_item['category_name']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        <?php echo date('d/m/Y', strtotime($album_item['created_at'])); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-eye mr-1"></i>
                                        <?php echo number_format($album_item['view_count']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <a href="album_edit.php?id=<?php echo $album_item['id']; ?>" 
                                   class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="../album_detail.php?id=<?php echo $album_item['id']; ?>" 
                                   target="_blank"
                                   class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700 transition-colors">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="album_manage.php" class="text-blue-600 hover:text-blue-800 font-medium">
                        ดู Albums ทั้งหมด <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">กิจกรรมล่าสุด</h2>
                    <i class="fas fa-chart-line text-2xl text-green-600"></i>
                </div>
                
                <div class="space-y-3">
                    <?php if (empty($recent_activities)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-clock text-4xl mb-4"></i>
                            <p>ยังไม่มีกิจกรรม</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-center space-x-3 p-3 border-l-4 <?php echo $activity['status'] == 'success' ? 'border-green-500 bg-green-50' : 'border-red-500 bg-red-50'; ?> rounded-r-lg">
                            <div class="flex-shrink-0">
                                <i class="fas <?php echo $activity['status'] == 'success' ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600'; ?>"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800">
                                    <?php echo $activity['full_name'] ?: $activity['username']; ?>
                                </div>
                                <div class="text-xs text-gray-600">
                                    <?php echo $activity['status'] == 'success' ? 'เข้าสู่ระบบสำเร็จ' : 'เข้าสู่ระบบล้มเหลว'; ?>
                                    <?php if ($activity['failure_reason']): ?>
                                        - <?php echo htmlspecialchars($activity['failure_reason']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?php echo date('d/m/Y H:i:s', strtotime($activity['login_time'])); ?>
                                    <span class="mx-1">|</span>
                                    <i class="fas fa-globe mr-1"></i>
                                    <?php echo htmlspecialchars($activity['ip_address']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($current_user['role'] == 'admin'): ?>
                <div class="mt-4 text-center">
                    <a href="activity_logs.php" class="text-blue-600 hover:text-blue-800 font-medium">
                        ดูกิจกรรมทั้งหมด <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<!-- เพิ่มในส่วน Quick Actions -->
<?php if ($current_user['role'] == 'admin'): ?>
<a href="system_info.php" class="bg-orange-600 text-white p-6 rounded-xl hover:bg-orange-700 transition-colors transform hover:scale-105">
    <div class="flex items-center">
        <i class="fas fa-server text-3xl mr-4"></i>
        <div>
            <h3 class="text-lg font-semibold">System Info</h3>
            <p class="text-sm opacity-90">ตรวจสอบสถานะระบบ</p>
        </div>
    </div>
</a>
<?php endif; ?>