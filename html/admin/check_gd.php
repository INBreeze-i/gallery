<?php
include_once '../config/database.php';
include_once 'Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireLogin('admin');

// ตรวจสอบ GD
$gd_info = [];
if (extension_loaded('gd')) {
    $gd_info = gd_info();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GD Extension Check</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/kanit-font.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-search mr-2 text-blue-600"></i>GD Extension Status
            </h1>
            
            <?php if (extension_loaded('gd')): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 p-4 rounded-lg mb-6">
                    <h2 class="text-lg font-semibold mb-2">
                        <i class="fas fa-check-circle mr-2"></i>GD Extension is Installed!
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <?php foreach ($gd_info as $key => $value): ?>
                            <div class="bg-white p-3 rounded border">
                                <strong><?php echo htmlspecialchars($key); ?>:</strong>
                                <span class="text-gray-600">
                                    <?php echo is_bool($value) ? ($value ? 'Yes' : 'No') : htmlspecialchars($value); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <a href="album_create.php" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700">
                        <i class="fas fa-plus mr-2"></i>Create Album (Full Features)
                    </a>
                    <a href="system_info.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-info mr-2"></i>System Info
                    </a>
                </div>
                
            <?php else: ?>
                <div class="bg-red-100 border border-red-400 text-red-700 p-4 rounded-lg mb-6">
                    <h2 class="text-lg font-semibold mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>GD Extension is NOT Installed
                    </h2>
                    
                    <div class="bg-white p-4 rounded border mt-4">
                        <h3 class="font-semibold mb-2">Docker Installation Commands:</h3>
                        <div class="bg-gray-900 text-green-400 p-3 rounded font-mono text-sm overflow-x-auto">
<pre># Method 1: Install in running container
docker exec -it &lt;container_name&gt; bash
apt-get update
apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev
docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
docker-php-ext-install gd
service apache2 restart

# Method 2: Use pre-built image
docker run -d --name php-albums -p 80:80 -v $(pwd):/var/www/html thecodingmachine/php:8.1-v4-apache</pre>
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <a href="album_create.php" class="bg-yellow-600 text-white px-6 py-3 rounded-lg hover:bg-yellow-700">
                        <i class="fas fa-upload mr-2"></i>Create Album (Limited)
                    </a>
                    <button onclick="location.reload()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-refresh mr-2"></i>Recheck
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Current System Info -->
            <div class="mt-8 border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Current System Info</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gray-50 p-3 rounded">
                        <strong>PHP Version:</strong><br>
                        <span class="text-gray-600"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="bg-gray-50 p-3 rounded">
                        <strong>Server Time:</strong><br>
                        <span class="text-gray-600"><?php echo date('2025-07-23 04:30:04'); ?> UTC</span>
                    </div>
                    <div class="bg-gray-50 p-3 rounded">
                        <strong>User:</strong><br>
                        <span class="text-gray-600">INBreeze-i</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>