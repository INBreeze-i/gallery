<?php
class Auth {
    private $conn;
    private $max_login_attempts = 5;
    private $lockout_duration = 30; // นาที
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // เริ่ม session อย่างปลอดภัย
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    // ตรวจสอบว่ามี session หรือไม่
    private function hasActiveSession() {
        $this->startSession();
        return isset($_SESSION['admin_logged_in']) && 
               $_SESSION['admin_logged_in'] === true && 
               isset($_SESSION['admin_user_id']) && 
               !empty($_SESSION['admin_user_id']);
    }
    
    // ตรวจสอบการ login
    public function login($username, $password, $remember_me = false) {
        try {
            // ตรวจสอบว่า account ถูกล็อคหรือไม่
            if ($this->isAccountLocked($username)) {
                $this->logFailedAttempt($username, 'Account locked due to too many failed attempts');
                return [
                    'success' => false, 
                    'message' => 'บัญชีถูกล็อค กรุณาลองใหม่อีก ' . $this->lockout_duration . ' นาที'
                ];
            }
            
            // ดึงข้อมูลผู้ใช้
            $query = "SELECT id, username, password, email, full_name, role, status, login_attempts 
                     FROM admin_users 
                     WHERE username = ? AND status = 'active'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->logFailedAttempt($username, 'Username not found');
                return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
            }
            
            // ตรวจสอบรหัสผ่าน
            if (!password_verify($password, $user['password'])) {
                $this->incrementFailedAttempts($user['id']);
                $this->logFailedAttempt($username, 'Invalid password', $user['id']);
                return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
            }
            
            // Login สำเร็จ
            $this->resetFailedAttempts($user['id']);
            $this->updateLastLogin($user['id']);
            $this->logSuccessfulLogin($user['id'], $username);
            
            // สร้าง session
            $this->startSession();
            
            // Clear any existing session data first
            session_regenerate_id(true);
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_full_name'] = $user['full_name'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            $_SESSION['login_ip'] = $this->getClientIP();
            $_SESSION['last_activity'] = time();
            
            // Remember me functionality
            if ($remember_me) {
                $this->setRememberMeCookie($user['id']);
            }
            
            return [
                'success' => true, 
                'message' => 'เข้าสู่ระบบสำเร็จ',
                'user' => $user
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $this->logFailedAttempt($username, 'System error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ'];
        }
    }
    
    // ตรวจสอบสิทธิ์การเข้าถึง
    public function checkAccess($required_role = null) {
        if (!$this->hasActiveSession()) {
            return false;
        }
        
        // ตรวจสอบ session timeout (1 hour)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
            $this->logout();
            return false;
        }
        
        // อัปเดต last activity
        $_SESSION['last_activity'] = time();
        
        // ตรวจสอบ role ถ้าระบุ
        if ($required_role) {
            $user_role = $_SESSION['admin_role'] ?? '';
            $role_hierarchy = ['admin' => 3, 'moderator' => 2, 'editor' => 1];
            
            if (!isset($role_hierarchy[$user_role]) || 
                !isset($role_hierarchy[$required_role]) ||
                $role_hierarchy[$user_role] < $role_hierarchy[$required_role]) {
                return false;
            }
        }
        
        return true;
    }
    
    // Redirect ถ้าไม่มีสิทธิ์
    public function requireLogin($required_role = null) {
        if (!$this->checkAccess($required_role)) {
            $this->startSession();
            if (!$this->hasActiveSession()) {
                header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
            } else {
                header("Location: access_denied.php");
            }
            exit();
        }
    }
    
    // Logout
    public function logout() {
        $this->startSession();
        
        // ลบ remember me cookie
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/', '', false, true);
            
            // ลบ token จากฐานข้อมูลด้วย
            if (isset($_SESSION['admin_user_id'])) {
                try {
                    $query = "UPDATE admin_users SET remember_token = NULL, remember_expires = NULL WHERE id = ?";
                    $stmt = $this->conn->prepare($query);
                    $stmt->execute([$_SESSION['admin_user_id']]);
                } catch (Exception $e) {
                    error_log("Logout cleanup error: " . $e->getMessage());
                }
            }
        }
        
        // Clear session
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        header("Location: login.php?logged_out=1");
        exit();
    }
    
    // ตรวจสอบว่าบัญชีถูกล็อคหรือไม่
    private function isAccountLocked($username) {
        try {
            $query = "SELECT locked_until FROM admin_users 
                     WHERE username = ? AND locked_until > NOW()";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$username]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Account lock check error: " . $e->getMessage());
            return false;
        }
    }
    
    // เพิ่มจำนวนครั้งที่ login ผิด
    private function incrementFailedAttempts($user_id) {
        try {
            $query = "UPDATE admin_users 
                     SET login_attempts = login_attempts + 1,
                         locked_until = CASE 
                             WHEN login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                             ELSE locked_until 
                         END
                     WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$this->max_login_attempts, $this->lockout_duration, $user_id]);
        } catch (Exception $e) {
            error_log("Increment failed attempts error: " . $e->getMessage());
        }
    }
    
    // รีเซ็ต failed attempts
    private function resetFailedAttempts($user_id) {
        try {
            $query = "UPDATE admin_users 
                     SET login_attempts = 0, locked_until = NULL 
                     WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Reset failed attempts error: " . $e->getMessage());
        }
    }
    
    // อัปเดต last login
    private function updateLastLogin($user_id) {
        try {
            $query = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }
    
    // บันทึก login สำเร็จ
    private function logSuccessfulLogin($user_id, $username) {
        try {
            $query = "INSERT INTO login_logs (user_id, username, ip_address, user_agent, status) 
                     VALUES (?, ?, ?, ?, 'success')";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $user_id, 
                $username, 
                $this->getClientIP(), 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Log successful login error: " . $e->getMessage());
        }
    }
    
    // บันทึก login ล้มเหลว
    private function logFailedAttempt($username, $reason, $user_id = null) {
        try {
            $query = "INSERT INTO login_logs (user_id, username, ip_address, user_agent, status, failure_reason) 
                     VALUES (?, ?, ?, ?, 'failed', ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $user_id, 
                $username, 
                $this->getClientIP(), 
                $_SERVER['HTTP_USER_AGENT'] ?? '', 
                $reason
            ]);
        } catch (Exception $e) {
            error_log("Log failed attempt error: " . $e->getMessage());
        }
    }
    
    // ดึง IP ของ client
    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return 'unknown';
    }
    
    // Remember me functionality
    private function setRememberMeCookie($user_id) {
        try {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 วัน
            
            // บันทึก token ในฐานข้อมูล
            $query = "UPDATE admin_users SET remember_token = ?, remember_expires = FROM_UNIXTIME(?) WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([password_hash($token, PASSWORD_DEFAULT), $expiry, $user_id]);
            
            // ตั้ง cookie
            setcookie('remember_me', $user_id . ':' . $token, $expiry, '/', '', false, true);
        } catch (Exception $e) {
            error_log("Set remember me cookie error: " . $e->getMessage());
        }
    }
    
    // ตรวจสอบ remember me
    public function checkRememberMe() {
        if (!isset($_COOKIE['remember_me'])) {
            return false;
        }
        
        try {
            $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);
            if (count($cookie_parts) !== 2) {
                setcookie('remember_me', '', time() - 3600, '/');
                return false;
            }
            
            list($user_id, $token) = $cookie_parts;
            
            $query = "SELECT id, username, email, full_name, role, remember_token 
                     FROM admin_users 
                     WHERE id = ? AND status = 'active' AND remember_expires > NOW()";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['remember_token'] && password_verify($token, $user['remember_token'])) {
                // สร้าง session อัตโนมัติ
                $this->startSession();
                session_regenerate_id(true);
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_full_name'] = $user['full_name'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                $_SESSION['last_activity'] = time();
                $_SESSION['auto_login'] = true;
                
                return true;
            }
            
            // ลบ cookie ที่ไม่ถูกต้อง
            setcookie('remember_me', '', time() - 3600, '/');
            return false;
            
        } catch (Exception $e) {
            error_log("Check remember me error: " . $e->getMessage());
            setcookie('remember_me', '', time() - 3600, '/');
            return false;
        }
    }
    
    // ดึงข้อมูลผู้ใช้ปัจจุบัน
    public function getCurrentUser() {
        if (!$this->hasActiveSession()) {
            return null;
        }
        
        try {
            $query = "SELECT id, username, email, full_name, role, last_login, created_at 
                     FROM admin_users WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$_SESSION['admin_user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                // User not found in database, logout
                $this->logout();
                return null;
            }
            
            return $user;
            
        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
    
    // เปลี่ยนรหัสผ่าน
    public function changePassword($user_id, $current_password, $new_password) {
        try {
            // ตรวจสอบรหัสผ่านปัจจุบัน
            $query = "SELECT password FROM admin_users WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                return ['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'];
            }
            
            // ตรวจสอบความแข็งแกร่งของรหัสผ่านใหม่
            if (strlen($new_password) < 6) {
                return ['success' => false, 'message' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'];
            }
            
            // อัปเดตรหัสผ่านใหม่
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE admin_users SET password = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            
            if ($update_stmt->execute([$new_hash, $user_id])) {
                return ['success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'];
            }
            
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน'];
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ'];
        }
    }
    
    // ตรวจสอบสถานะ session
    public function getSessionInfo() {
        if (!$this->hasActiveSession()) {
            return null;
        }
        
        return [
            'user_id' => $_SESSION['admin_user_id'],
            'username' => $_SESSION['admin_username'],
            'full_name' => $_SESSION['admin_full_name'],
            'email' => $_SESSION['admin_email'],
            'role' => $_SESSION['admin_role'],
            'login_time' => $_SESSION['login_time'],
            'login_ip' => $_SESSION['login_ip'] ?? 'unknown',
            'auto_login' => $_SESSION['auto_login'] ?? false,
            'last_activity' => $_SESSION['last_activity'] ?? time()
        ];
    }
}
?>