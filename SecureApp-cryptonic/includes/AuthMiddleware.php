<?php
/**
 * Authentication Middleware
 * Handles user authentication and authorization
 */

require_once __DIR__ . '/AuditLog.php';

class AuthMiddleware {
    private $db;
    private $auditLog;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->auditLog = new AuditLog();
    }

    /**
     * Initialize session with security settings
     * MUST be called before session_start()
     */
    public static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set session configuration BEFORE starting session
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            
            // Set cookie parameters BEFORE starting session
            session_set_cookie_params([
                'lifetime' => SESSION_TIMEOUT,
                'path' => '/',
                'domain' => '',
                'secure' => (APP_ENV === 'production'),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            // Set session name
            session_name(SESSION_NAME);
            
            // Now start the session
            session_start();

            // Check session timeout
            if (isset($_SESSION['last_activity'])) {
                if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                    session_unset();
                    session_destroy();
                    session_start();
                }
            }
            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }

    /**
     * Get current user ID
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current username
     */
    public function getUsername() {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Get current user role
     */
    public function getUserRole() {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'full_name' => $_SESSION['full_name'] ?? ''
        ];
    }

    /**
     * Require authentication - redirect if not logged in
     */
    public function requireAuth($requiredRole = null) {
        if (!$this->isAuthenticated()) {
            header('Location: /SecureApp-cryptonic/auth/login.php');
            exit;
        }

        if ($requiredRole && $this->getUserRole() !== $requiredRole) {
            // Unauthorized access attempt
            $this->auditLog->log(
                $this->getUserId(),
                'unauthorized_access',
                'access_attempt',
                null,
                ['required_role' => $requiredRole, 'user_role' => $this->getUserRole()]
            );
            
            header('HTTP/1.0 403 Forbidden');
            echo '403 Forbidden - You do not have permission to access this page.';
            exit;
        }
    }

    /**
     * Alias for requireAuth
     */
    public function requireRole($role) {
        $this->requireAuth($role);
    }

    /**
     * Login user
     */
    public function login($userId, $username) {
        // Get user from database
        $user = $this->db->fetchOne(
            "SELECT id, username, role, full_name, is_active FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        // Check if account is active
        if (!$user['is_active']) {
            $this->auditLog->log($user['id'], 'login_failed', 'user', $user['id'], ['reason' => 'account_disabled']);
            
            return [
                'success' => false,
                'error' => 'Your account has been disabled. Please contact the administrator.'
            ];
        }

        // Login successful - regenerate session ID
        Security::regenerateSessionID();

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();

        // Update last login
        $this->db->query(
            "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?",
            [$user['id']]
        );

        // Log successful login
        $this->auditLog->logLoginAttempt($user['username'], true);

        return [
            'success' => true,
            'role' => $user['role']
        ];
    }

    /**
     * Logout user
     */
    public function logout() {
        if ($this->isAuthenticated()) {
            $userId = $this->getUserId();
            
            // Log logout
            $this->auditLog->logAction($userId, 'LOGOUT', 'user', $userId);
        }

        // Clear session
        $_SESSION = [];

        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy session
        session_destroy();
    }
}
