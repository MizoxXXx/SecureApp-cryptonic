<?php
/**
 * User authentication service
 * Handles registration, login, password verification
 */

class UserService {
    private $db;
    private $audit;
    private $rateLimiter;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->audit = new AuditLog();
        $this->rateLimiter = new RateLimiter();
    }

    /**
     * Register new user
     */
    public function register($username, $email, $fullName, $password, $confirmPassword, $role = 'policeman') {
        $errors = [];

        // Validation
        if (!Security::validateLength($username, 3, 50)) {
            $errors[] = "Username must be between 3 and 50 characters";
        }

        if (!Security::validateEmail($email)) {
            $errors[] = "Invalid email format";
        }

        if (!Security::validateLength($fullName, 2, 100)) {
            $errors[] = "Full name must be between 2 and 100 characters";
        }

        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }

        $passwordErrors = Security::validatePasswordStrength($password);
        $errors = array_merge($errors, $passwordErrors);

        if (!in_array($role, ['admin', 'policeman'])) {
            $errors[] = "Invalid role specified";
        }

        // Check if username exists
        $existing = $this->db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) {
            $errors[] = "Username already exists";
        }

        // Check if email exists
        $existing = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $errors[] = "Email already exists";
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $hashedPassword = Security::hashPassword($password);
            $sanitizedUsername = Security::sanitizeInput($username);
            $sanitizedEmail = Security::sanitizeInput($email);
            $sanitizedName = Security::sanitizeInput($fullName);

            $data = [
                'username' => $sanitizedUsername,
                'email' => $sanitizedEmail,
                'full_name' => $sanitizedName,
                'password_hash' => $hashedPassword,
                'role' => $role,
            ];

            $userID = $this->db->insert('users', $data);

            $this->audit->logAction($userID, 'USER_REGISTERED', 'user', $userID);

            return [
                'success' => true,
                'user_id' => $userID,
                'message' => 'Registration successful'
            ];

        } catch (Exception $e) {
            error_log("Registration Error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
        }
    }

    /**
     * Authenticate user (login)
     */
    public function authenticate($username, $password) {
        $clientIP = Security::getClientIP();
        $errors = [];

        // Check rate limiting
        if ($this->rateLimiter->isRateLimited(
            $clientIP,
            'login',
            RATE_LIMIT_LOGIN_ATTEMPTS,
            RATE_LIMIT_LOGIN_WINDOW
        )) {
            $remaining = $this->rateLimiter->getRemainingAttempts($clientIP, 'login', RATE_LIMIT_LOGIN_ATTEMPTS);
            return [
                'success' => false,
                'errors' => ['Too many login attempts. Please try again later.'],
                'remaining_attempts' => $remaining
            ];
        }

        // Validate input
        if (empty($username) || empty($password)) {
            $errors[] = "Username and password are required";
        }

        if (!empty($errors)) {
            $this->rateLimiter->recordAttempt($clientIP, 'login');
            $this->audit->logLoginAttempt($username, false, ['reason' => 'invalid_input']);
            return ['success' => false, 'errors' => $errors];
        }

        // Get user
        $query = "SELECT id, username, password_hash, role, is_active FROM users WHERE username = ?";
        $user = $this->db->fetchOne($query, [$username]);

        if (!$user) {
            $this->rateLimiter->recordAttempt($clientIP, 'login');
            $this->audit->logLoginAttempt($username, false, ['reason' => 'user_not_found']);
            return ['success' => false, 'errors' => ['Invalid username or password']];
        }

        if (!$user['is_active']) {
            $this->rateLimiter->recordAttempt($clientIP, 'login');
            $this->audit->logLoginAttempt($username, false, ['reason' => 'account_inactive']);
            return ['success' => false, 'errors' => ['Your account has been deactivated']];
        }

        // Verify password
        if (!Security::verifyPassword($password, $user['password_hash'])) {
            $this->rateLimiter->recordAttempt($clientIP, 'login');
            $this->audit->logLoginAttempt($username, false, ['reason' => 'invalid_password']);
            return ['success' => false, 'errors' => ['Invalid username or password']];
        }

        // Successful login
        $this->rateLimiter->resetAttempts($clientIP, 'login');

        return [
            'success' => true,
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'message' => 'Login successful'
        ];
    }

    /**
     * Change password
     */
    public function changePassword($userID, $currentPassword, $newPassword, $confirmPassword) {
        $errors = [];

        // Get user
        $user = $this->db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$userID]);
        if (!$user) {
            return ['success' => false, 'errors' => ['User not found']];
        }

        // Verify current password
        if (!Security::verifyPassword($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'errors' => ['Current password is incorrect']];
        }

        // Validate new password
        if ($newPassword !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }

        $passwordErrors = Security::validatePasswordStrength($newPassword);
        $errors = array_merge($errors, $passwordErrors);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Update password
        $hashedPassword = Security::hashPassword($newPassword);
        $this->db->update('users', ['password_hash' => $hashedPassword], 'id = ?', [$userID]);

        $this->audit->logAction($userID, 'PASSWORD_CHANGED', 'user', $userID);

        return ['success' => true, 'message' => 'Password changed successfully'];
    }

    /**
     * Get user by ID
     */
    public function getUserByID($userID) {
        $query = "SELECT id, username, email, full_name, role, is_active, created_at, last_login FROM users WHERE id = ?";
        return $this->db->fetchOne($query, [$userID]);
    }

    /**
     * Get all policemen
     */
    public function getPolicemen() {
        $query = "SELECT id, username, full_name, is_active, created_at FROM users WHERE role = 'policeman' ORDER BY full_name";
        return $this->db->fetchAll($query);
    }

    /**
     * Deactivate user account
     */
    public function deactivateUser($userID) {
        $this->db->update('users', ['is_active' => false], 'id = ?', [$userID]);
        $this->audit->logAction($userID, 'ACCOUNT_DEACTIVATED', 'user', $userID);
        return true;
    }
}
