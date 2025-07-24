<?php
include_once '../config/database.php';
include_once 'Auth.php';
include_once 'ImageHandler.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// ตรวจสอบสิทธิ์การเข้าถึง (Admin only)
$auth->requireLogin('admin');

$imageHandler = new ImageHandler();
$system_info = $imageHandler->getSystemInfo();
$current_user = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Information - Albums Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">System Information</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">
                        <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($current_user['full_name']); ?>
                    </span>
                    <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-home mr-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- System Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 <?php echo $system_info['gd_available'] ? 'bg-green-100' : 'bg-red-100'; ?> rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-image text-2xl <?php echo $system_info['gd_available'] ? 'text-green-600' : 'text-red-600'; ?>"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-lg">GD Extension</h3>
                        <p class="text-sm <?php echo $system_info['gd_available'] ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $system_info['gd_available'] ? 'Available' : 'Not Available'; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 <?php echo $system_info['upload_dir_writable'] ? 'bg-green-100' : 'bg-red-100'; ?> rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-folder text-2xl <?php echo $system_info['upload_dir_writable'] ? 'text-green-600' : 'text-red-600'; ?>"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-lg">Upload Directory</h3>
                        <p class="text-sm <?php echo $system_info['upload_dir_writable'] ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $system_info['upload_dir_writable'] ? 'Writable' : 'Not Writable'; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-server text-2xl text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-lg">Server Time</h3>
                        <p class="text-sm text-gray-600">
                            <?php echo date('2025-07-23 04:15:56'); ?> UTC
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- PHP Configuration -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-cog mr-2 text-blue-600"></i>PHP Configuration
                </h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium">PHP Version</span>
                        <span class="text-gray-600"><?php echo PHP_VERSION; ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium">Upload Max Filesize</span>
                        <span class="text-gray-600"><?php echo $system_info['upload_max_filesize']; ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium">Post Max Size</span>
                        <span class="text-gray-600"><?php echo $system_info['post_max_size']; ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium">Max File Uploads</span>
                        <span class="text-gray-600"><?php echo $system_info['max_file_uploads']; ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium">Memory Limit</span>
                        <span class="text-gray-600"><?php echo ini_get('memory_limit'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Image Processing -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-image mr-2 text-purple-600"></i>Image Processing
                </h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium">GD Extension</span>
                        <span class="<?php echo $system_info['gd_available'] ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $system_info['gd_available'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                    
                    <?php if ($system_info['gd_available']): ?>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium">GD Version</span>
                        <span class="text-gray-600"><?php echo $system_info['gd_version']; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium block mb-2">Supported Formats</span>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($system_info['supported_formats'] as $format): ?>
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm"><?php echo $format; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium">Upload Directory</span>
                        <span class="text-gray-600 text-sm"><?php echo $system_info['upload_dir']; ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium">Directory Writable</span>
                        <span class="<?php echo $system_info['upload_dir_writable'] ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $system_info['upload_dir_writable'] ? 'Yes' : 'No'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommendations -->
        <?php if (!$system_info['gd_available'] || !$system_info['upload_dir_writable']): ?>
        <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-yellow-800 mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>System Recommendations
            </h3>
            
            <ul class="space-y-2 text-yellow-700">
                <?php if (!$system_info['gd_available']): ?>
                <li>
                    <i class="fas fa-arrow-right mr-2"></i>
                    <strong>Install GD Extension:</strong> For image resizing functionality, install PHP GD extension.
                    <code class="bg-yellow-100 px-2 py-1 rounded text-sm ml-2">sudo apt-get install php-gd</code>
                </li>
                <?php endif; ?>
                
                <?php if (!$system_info['upload_dir_writable']): ?>
                <li>
                    <i class="fas fa-arrow-right mr-2"></i>
                    <strong>Fix Directory Permissions:</strong> Make upload directory writable.
                    <code class="bg-yellow-100 px-2 py-1 rounded text-sm ml-2">chmod 755 <?php echo $system_info['upload_dir']; ?></code>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Test Upload -->
        <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-vial mr-2 text-green-600"></i>Test Upload Function
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-gray-600 mb-4">
                        Current system status: 
                        <?php if ($system_info['gd_available'] && $system_info['upload_dir_writable']): ?>
                            <span class="text-green-600 font-semibold">Fully Functional</span>
                        <?php elseif ($system_info['upload_dir_writable']): ?>
                            <span class="text-yellow-600 font-semibold">Basic Upload Only</span>
                        <?php else: ?>
                            <span class="text-red-600 font-semibold">Upload Disabled</span>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($system_info['upload_dir_writable']): ?>
                    <a href="album_create.php" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors inline-block">
                        <i class="fas fa-plus mr-2"></i>Test Create Album
                    </a>
                    <?php else: ?>
                    <button disabled class="bg-gray-400 text-white px-6 py-3 rounded-lg cursor-not-allowed">
                        <i class="fas fa-ban mr-2"></i>Upload Disabled
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-medium text-blue-800 mb-2">System Status</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>
                            <i class="fas fa-<?php echo $system_info['gd_available'] ? 'check text-green-600' : 'times text-red-600'; ?> mr-2"></i>
                            Image Processing: <?php echo $system_info['gd_available'] ? 'Available' : 'Limited'; ?>
                        </li>
                        <li>
                            <i class="fas fa-<?php echo $system_info['upload_dir_writable'] ? 'check text-green-600' : 'times text-red-600'; ?> mr-2"></i>
                            File Upload: <?php echo $system_info['upload_dir_writable'] ? 'Available' : 'Disabled'; ?>
                        </li>
                        <li>
                            <i class="fas fa-info mr-2 text-blue-600"></i>
                            Mode: <?php echo $system_info['gd_available'] ? 'Full Processing' : 'File Copy Only'; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>