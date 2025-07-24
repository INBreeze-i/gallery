<?php
class CSRFProtection {
    private static $sessionKey = 'csrf_tokens';
    
    public static function generateToken($form_name = 'default') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $timestamp = time();
        
        if (!isset($_SESSION[self::$sessionKey])) {
            $_SESSION[self::$sessionKey] = [];
        }
        
        $_SESSION[self::$sessionKey][$form_name] = [
            'token' => $token,
            'timestamp' => $timestamp
        ];
        
        // Clean old tokens (older than 1 hour)
        self::cleanOldTokens();
        
        return $token;
    }
    
    public static function validateToken($token, $form_name = 'default', $max_age = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$sessionKey][$form_name])) {
            return false;
        }
        
        $stored_data = $_SESSION[self::$sessionKey][$form_name];
        
        // Check token match
        if (!hash_equals($stored_data['token'], $token)) {
            return false;
        }
        
        // Check age
        if (time() - $stored_data['timestamp'] > $max_age) {
            unset($_SESSION[self::$sessionKey][$form_name]);
            return false;
        }
        
        // Token is valid, remove it (one-time use)
        unset($_SESSION[self::$sessionKey][$form_name]);
        return true;
    }
    
    public static function getTokenField($form_name = 'default') {
        $token = self::generateToken($form_name);
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    private static function cleanOldTokens($max_age = 3600) {
        if (!isset($_SESSION[self::$sessionKey])) {
            return;
        }
        
        $current_time = time();
        foreach ($_SESSION[self::$sessionKey] as $form_name => $data) {
            if ($current_time - $data['timestamp'] > $max_age) {
                unset($_SESSION[self::$sessionKey][$form_name]);
            }
        }
    }
    
    public static function requireValidToken($form_name = 'default') {
        $token = $_POST['csrf_token'] ?? '';
        if (!self::validateToken($token, $form_name)) {
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}
?>