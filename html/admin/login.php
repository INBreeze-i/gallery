<?php
include_once '../config/database.php';
include_once 'Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// ตรวจสอบ remember me ก่อน
if ($auth->checkRememberMe()) {
    $redirect = $_GET['redirect'] ?? 'dashboard.php';
    header("Location: " . $redirect);
    exit();
}

// ถ้าเข้าสู่ระบบแล้ว redirect ไป dashboard
if ($auth->checkAccess()) {
    $redirect = $_GET['redirect'] ?? 'dashboard.php';
    header("Location: " . $redirect);
    exit();
}

$message = '';
$message_type = '';

// จัดการ logout message
if (isset($_GET['logged_out'])) {
    $message = 'ออกจากระบบเรียบร้อยแล้ว';
    $message_type = 'success';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $message = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
        $message_type = 'error';
    } else {
        $result = $auth->login($username, $password, $remember_me);
        
        if ($result['success']) {
            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            header("Location: " . $redirect);
            exit();
        } else {
            $message = $result['message'];
            $message_type = 'error';
        }
    }
}

// ดึงข้อมูลสถิติ login attempts (แค่สำหรับแสดง)
try {
    $stats_query = "SELECT 
        COUNT(*) as total_attempts,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_logins,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_attempts
        FROM login_logs 
        WHERE login_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $login_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $login_stats = ['total_attempts' => 0, 'successful_logins' => 0, 'failed_attempts' => 0];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Albums Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            font-family: 'Kanit', sans-serif;
        }
        .login-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .loading {
            display: none;
        }
        .loading.show {
            display: inline-block;
        }
    </style>
</head>
<body class="login-bg flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Login Card -->
        <div class="glass-effect rounded-2xl shadow-2xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-full mb-4">
                    <i class="fas fa-user-shield text-2xl text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Admin Login</h1>
                <p class="text-gray-600">Albums Management System</p>
                <div class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-clock mr-1"></i>
                    <?php echo date('Y-m-d H:i:s'); ?> UTC
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type == 'error' ? 'bg-red-100 border border-red-400 text-red-700' : 'bg-green-100 border border-green-400 text-green-700'; ?>">
                    <div class="flex items-center">
                        <i class="fas <?php echo $message_type == 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> mr-2"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" id="loginForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-blue-600"></i>ชื่อผู้ใช้
                    </label>
                    <input type="text" name="username" id="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                           placeholder="กรอกชื่อผู้ใช้"
                           autocomplete="username">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-blue-600"></i>รหัสผ่าน
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-12"
                               placeholder="กรอกรหัสผ่าน"
                               autocomplete="current-password">
                        <button type="button" onclick="togglePassword()" 
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                            <i id="password-icon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <input type="checkbox" name="remember_me" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700">จดจำการเข้าสู่ระบบ (30 วัน)</span>
                    </label>
                    
                    <a href="forgot_password.php" class="text-sm text-blue-600 hover:text-blue-800">
                        ลืมรหัสผ่าน?
                    </a>
                </div>

                <button type="submit" id="loginBtn"
                        class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="btn-text">
                        <i class="fas fa-sign-in-alt mr-2"></i>เข้าสู่ระบบ
                    </span>
                    <span class="loading">
                        <i class="fas fa-spinner fa-spin mr-2"></i>กำลังเข้าสู่ระบบ...
                    </span>
                </button>
            </form>

            <!-- Demo Accounts -->
            <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                <div class="text-center text-sm text-gray-600 mb-3">
                    <i class="fas fa-info-circle mr-1"></i>บัญชีทดสอบ
                </div>
                <div class="grid grid-cols-1 gap-2 text-xs">
                    <div class="flex justify-between items-center p-2 bg-white rounded border cursor-pointer hover:bg-blue-50" onclick="fillLogin('INBreeze-i')">
                        <span class="font-medium">INBreeze-i</span>
                        <span class="text-blue-600">Admin</span>
                    </div>
                    <div class="flex justify-between items-center p-2 bg-white rounded border cursor-pointer hover:bg-blue-50" onclick="fillLogin('admin')">
                        <span class="font-medium">admin</span>
                        <span class="text-blue-600">Admin</span>
                    </div>
                    <div class="flex justify-between items-center p-2 bg-white rounded border cursor-pointer hover:bg-blue-50" onclick="fillLogin('editor1')">
                        <span class="font-medium">editor1</span>
                        <span class="text-green-600">Editor</span>
                    </div>
                </div>
                <div class="text-center text-xs text-gray-500 mt-2">
                    รหัสผ่านทั้งหมด: <code class="bg-gray-200 px-1 rounded">password</code>
                </div>
            </div>
        </div>

        <!-- System Stats -->
        <div class="mt-6 glass-effect rounded-xl p-4">
            <div class="text-center text-sm text-gray-700">
                <div class="flex justify-between items-center">
                    <span>Login Statistics (24h)</span>
                    <i class="fas fa-chart-line text-blue-600"></i>
                </div>
                <div class="grid grid-cols-3 gap-4 mt-3 text-xs">
                    <div>
                        <div class="font-semibold text-blue-600"><?php echo $login_stats['total_attempts']; ?></div>
                        <div class="text-gray-600">Total</div>
                    </div>
                    <div>
                        <div class="font-semibold text-green-600"><?php echo $login_stats['successful_logins']; ?></div>
                        <div class="text-gray-600">Success</div>
                    </div>
                    <div>
                        <div class="font-semibold text-red-600"><?php echo $login_stats['failed_attempts']; ?></div>
                        <div class="text-gray-600">Failed</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-sm text-white opacity-75">
            <p>&copy; 2025 Albums Management System</p>
            <p>Secured by Advanced Authentication</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }

        function fillLogin(username) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = 'password';
            document.getElementById('username').focus();
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const loading = btn.querySelector('.loading');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            loading.classList.add('show');
        });

        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if (usernameField.value === '') {
                usernameField.focus();
            } else {
                document.getElementById('password').focus();
            }
        });

        // Handle Enter key submission
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });

        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            // You can implement AJAX refresh here if needed
        }, 30000);
    </script>
</body>
</html>