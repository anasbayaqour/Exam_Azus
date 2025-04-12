<?php
/**
 * Centralized authentication and authorization check
 */

// Prevent direct script access
if (!defined('APP_RUNNING')) {
    die('Direct script access is not allowed.');
}

// Require security helpers
require_once 'helpers/security.php';

/**
 * Check user authentication and authorization
 * 
 * @param string|array $allowed_roles Allowed user roles
 * @param bool $redirect Whether to redirect unauthorized users
 * @return bool True if authenticated, false otherwise
 */
function checkAuthentication($allowed_roles = ['all'], $redirect = true) {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        // Log unauthorized access attempt
        logSecurityEvent('Unauthorized access attempt - Not logged in', 'warning');
        
        if ($redirect) {
            // Store the original requested URL for post-login redirect
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            
            // Redirect to login page
            header('Location: login.php');
            exit();
        }
        return false;
    }

    // Check user role
    if (!isset($_SESSION['user_type'])) {
        // Log suspicious session without user type
        logSecurityEvent('Suspicious session - Missing user type', 'alert');
        
        // Destroy potentially compromised session
        session_destroy();
        
        if ($redirect) {
            header('Location: login.php');
            exit();
        }
        return false;
    }

    // Check if user role is allowed
    if (!in_array('all', $allowed_roles) && !in_array($_SESSION['user_type'], $allowed_roles)) {
        // Log unauthorized role access
        logSecurityEvent(
            sprintf(
                'Unauthorized role access - User %d with role %s attempted to access restricted area', 
                $_SESSION['user_id'], 
                $_SESSION['user_type']
            ), 
            'warning'
        );
        
        if ($redirect) {
            // Redirect to unauthorized access page
            header('Location: unauthorized.php');
            exit();
        }
        return false;
    }

    // Optional: Check session expiration
    $max_session_time = 3600; // 1 hour
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > $max_session_time)) {
        
        // Log session timeout
        logSecurityEvent('Session expired', 'info');
        
        // Destroy expired session
        session_unset();
        session_destroy();
        
        if ($redirect) {
            // Redirect to login with session expired message
            header('Location: login.php?error=session_expired');
            exit();
        }
        return false;
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();

    // Optional: Regenerate session ID periodically to prevent session fixation
    if (!isset($_SESSION['last_regenerate']) || 
        (time() - $_SESSION['last_regenerate'] > 300)) { // Every 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regenerate'] = time();
    }

    return true;
}

/**
 * Get current authenticated user information
 * 
 * @return array|false User information or false if not authenticated
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    return [
        'id' => $_SESSION['user_id'],
        'type' => $_SESSION['user_type'],
        'email' => $_SESSION['user_email'] ?? null,
        'name' => $_SESSION['user_name'] ?? 'Unknown User'
    ];
}

/**
 * Logout user
 */
function logoutUser() {
    // Log logout event
    logSecurityEvent('User logged out', 'info');

    // Unset all session variables
    $_SESSION = [];

    // Destroy the session
    session_destroy();

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Example usage in other scripts:
// Restrict to teachers
// checkAuthentication(['teacher']);

// Restrict to students and teachers
// checkAuthentication(['student', 'teacher']);

// Allow all authenticated users
// checkAuthentication(['all']);