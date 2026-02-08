<?php
/**
 * Public entry point / Router
 * Redirects to appropriate pages
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/AuditLog.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

// Initialize session
AuthMiddleware::initSession();

$auth = new AuthMiddleware();

// Check if user is authenticated
if ($auth->isAuthenticated()) {
    $role = $auth->getUserRole();
    if ($role === 'admin') {
        header('Location: /SecureApp-cryptonic/admin/dashboard.php');
    } else {
        header('Location: /SecureApp-cryptonic/policeman/add_violation.php');
    }
} else {
    header('Location: /SecureApp-cryptonic/auth/login.php');
}
exit;
?>
