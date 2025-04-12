<?php
/**
 * Security helper functions for generating and validating CSRF tokens
 */

/**
 * Generate a CSRF token
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Generate a token if one doesn't exist
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token Token to validate
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token) {
    // Check if session and token exist
    if (!isset($_SESSION['csrf_token']) || !$token) {
        return false;
    }

    // Perform timing-safe comparison
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 * 
 * @param mixed $data Input data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }

    if (is_string($data)) {
        // Trim whitespace
        $data = trim($data);
        
        // Remove backslashes
        $data = stripslashes($data);
        
        // Convert special characters to HTML entities
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    return $data;
}

/**
 * Generate a secure random string
 * 
 * @param int $length Length of the string
 * @return string Randomly generated string
 */
function generateSecureRandomString($length = 16) {
    try {
        $randomBytes = random_bytes(ceil($length / 2));
        return substr(bin2hex($randomBytes), 0, $length);
    } catch (Exception $e) {
        // Fallback method if random_bytes fails
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $randomString;
    }
}

/**
 * Validate and sanitize email
 * 
 * @param string $email Email address to validate
 * @return string|false Sanitized email or false if invalid
 */
function validateEmail($email) {
    // Sanitize email
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    // Validate email
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    
    return false;
}

/**
 * Create a secure password hash
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    // Use the strongest password hashing algorithm available
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 1024 * 64,  // 64MB
        'time_cost'   => 4,
        'threads'     => 4
    ]);
}

/**
 * Verify a password against its hash
 * 
 * @param string $password Plain text password
 * @param string $hash Stored password hash
 * @return bool True if password is correct, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get client IP address
 * 
 * @return string Client IP address
 */
function getClientIP() {
    $ipAddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipAddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipAddress = 'UNKNOWN';
    
    return $ipAddress;
}

/**
 * Log security events
 * 
 * @param string $message Log message
 * @param string $level Log level (default: 'info')
 */
function logSecurityEvent($message, $level = 'info') {
    $logEntry = sprintf(
        "[%s] [%s] [IP: %s] %s\n", 
        date('Y-m-d H:i:s'), 
        strtoupper($level), 
        getClientIP(), 
        $message
    );
    
    // Append to a secure log file
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/security.log';
    
    // Ensure log directory exists and is not web-accessible
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0700, true);
    }
    
    // Add .htaccess to prevent web access to log files
    $htaccessPath = $logDir . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, "Deny from all");
    }
    
    // Append log entry
    error_log($logEntry, 3, $logFile);
}