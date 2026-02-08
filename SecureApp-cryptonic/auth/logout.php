<?php
/**
 * Logout page
 * Securely destroy session and redirect
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthMiddleware.php';

// Initialize session
AuthMiddleware::initSession();

$auth = new AuthMiddleware();
$auth->logout();

// Redirect to login
header('Location: /SecureApp-cryptonic/auth/login.php?logged_out=1'); // a status flag to show a logout confirma message on login page.
exit;
?>
