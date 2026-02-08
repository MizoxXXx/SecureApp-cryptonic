<?php
/**
 * Security configuration and utilities
 * OWASP best practices implementation
 */

class Security {
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        // Check token expiry
        if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRY) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token); //Timing attack safe string comparison
    }

    public static function escapeOutput($data) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeInput($input) {
        $input = trim($input);
        $input = stripslashes($input);
        return $input;
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePasswordStrength($password) {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        return $errors;
    }

    /**
     * Security: Use PASSWORD_BCRYPT algorithm (default in PHP 7.2+)
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12, // Higher cost = more secure but slower because of salt 
        ]);
    }

    // Verify password against hash
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Security: on login to prevent session fixation attacks
     */
    public static function regenerateSessionID() {
        session_regenerate_id(true);
    }

    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    //Validate numeric input
    public static function validateNumeric($input, $min = null, $max = null) {
        if (!is_numeric($input)) {
            return false;
        }

        $num = (float)$input;

        if ($min !== null && $num < $min) {
            return false;
        }

        if ($max !== null && $num > $max) {
            return false;
        }
        return true;
    }

    //Validate string length
    public static function validateLength($input, $min = 0, $max = 255) {
        $length = strlen($input);
        return $length >= $min && $length <= $max;
    }

    /**
     * Gets real client IP behind proxies:
     * Cloudflare header - When behind Cloudflare CDN
     * X-Forwarded-For - Standard proxy header (takes first IP)
     * X-Forwarded - Older proxy header
     * Forwarded-For - Alternative header
     * Forwarded - Another alternative
     * REMOTE_ADDR - Fallback to server-recorded IP
     */
    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }

    // Set security headers
    public static function setSecurityHeaders() {
        foreach (SECURITY_HEADERS as $header => $value) {
            header($header . ': ' . $value);
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
    }

    /**
     * Create secure random password (for admin generation)
     */
    public static function generateSecurePassword($length = 12) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()';

        $all = $uppercase . $lowercase . $numbers . $special;
        $password = '';

        // Ensure at least one of each type
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill rest with random characters
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Shuffle password
        return str_shuffle($password);
    }
}

// Set security headers immediately
Security::setSecurityHeaders();
