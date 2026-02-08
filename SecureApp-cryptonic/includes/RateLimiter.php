<?php
/**
 * Rate limiting to prevent brute force attacks
 * Limits login attempts and other sensitive actions
 */

class RateLimiter {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Check if action is rate limited
     * 
     * @param string $ipAddress IP address to check
     * @param string $action Action type (login, register, etc.)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $window Time window in seconds
     * @return bool True if rate limited, false if allowed
     */
    public function isRateLimited($ipAddress, $action, $maxAttempts = 5, $window = 900) {
        $timeLimit = date('Y-m-d H:i:s', time() - $window);

        $query = "
            SELECT attempt_count, last_attempt_at
            FROM rate_limit
            WHERE ip_address = ? AND action = ? AND last_attempt_at > ?
        ";

        $result = $this->db->fetchOne($query, [$ipAddress, $action, $timeLimit]);

        if (!$result) {
            return false;
        }

        return $result['attempt_count'] >= $maxAttempts;
    }

    /**
     * Record attempt for rate limiting
     */
    public function recordAttempt($ipAddress, $action) {
        $query = "
            INSERT INTO rate_limit (ip_address, action, attempt_count, last_attempt_at)
            VALUES (?, ?, 1, NOW())
            ON CONFLICT (ip_address, action)
            DO UPDATE SET
                attempt_count = rate_limit.attempt_count + 1,
                last_attempt_at = NOW()
        ";

        try {
            $this->db->query($query, [$ipAddress, $action]);
        } catch (Exception $e) {
            error_log("Rate Limit Error: " . $e->getMessage());
        }
    }

    /**
     * Reset attempts for IP
     */
    public function resetAttempts($ipAddress, $action = null) {
        if ($action) {
            $this->db->delete('rate_limit', 'ip_address = ? AND action = ?', [$ipAddress, $action]);
        } else {
            $this->db->delete('rate_limit', 'ip_address = ?', [$ipAddress]);
        }
    }

    /**
     * Clean up old rate limit entries
     */
    public function cleanup($window = 3600) {
        $timeLimit = date('Y-m-d H:i:s', time() - $window);
        $this->db->delete('rate_limit', 'last_attempt_at < ?', [$timeLimit]);
    }

    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts($ipAddress, $action, $maxAttempts = 5, $window = 900) {
        $timeLimit = date('Y-m-d H:i:s', time() - $window);

        $query = "
            SELECT attempt_count
            FROM rate_limit
            WHERE ip_address = ? AND action = ? AND last_attempt_at > ?
        ";

        $result = $this->db->fetchOne($query, [$ipAddress, $action, $timeLimit]);

        if (!$result) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - $result['attempt_count']);
    }
}
