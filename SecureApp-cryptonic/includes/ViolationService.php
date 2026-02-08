<?php
/**
 * Violation service for handling traffic violations
 */

class ViolationService {
    private $db;
    private $audit;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->audit = new AuditLog();
    }

    /**
     * Create new violation
     */
    public function createViolation($userID, $carID, $reason, $violationDatetime, $checkpoint, $fineAmount) {
        $errors = [];

        // Validate inputs
        if (!Security::validateLength($carID, 3, 20)) {
            $errors[] = "Car ID must be between 3 and 20 characters";
        }

        if (!Security::validateLength($reason, 10, 500)) {
            $errors[] = "Violation reason must be between 10 and 500 characters";
        }

        if (!Security::validateNumeric($fineAmount, 0, 10000)) {
            $errors[] = "Fine amount must be between 0 and 10000dhs";
        }

        if (!Security::validateLength($checkpoint, 5, 255)) {
            $errors[] = "Checkpoint must be between 5 and 255 characters";
        }

        // Validate datetime
        if (!$this->validateDatetime($violationDatetime)) {
            $errors[] = "Invalid date/time format";
        }

        // Ensure violation datetime is not in future
        if (strtotime($violationDatetime) > time()) {
            $errors[] = "Violation cannot be in the future";
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $data = [
                'user_id' => $userID,
                'car_id' => Security::sanitizeInput($carID),
                'violation_reason' => Security::sanitizeInput($reason),
                'violation_datetime' => $violationDatetime,
                'checkpoint_position' => Security::sanitizeInput($checkpoint),
                'fine_amount' => (float)$fineAmount,
            ];

            $violationID = $this->db->insert('violations', $data);

            $this->audit->logViolationSubmission($userID, $violationID, $carID);

            return [
                'success' => true,
                'violation_id' => $violationID,
                'message' => 'Violation recorded successfully'
            ];

        } catch (Exception $e) {
            error_log("Violation Creation Error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Failed to record violation']];
        }
    }

    /**
     * Validate datetime format (Y-m-d H:i)
     */
    private function validateDatetime($datetime) {
        $format = 'Y-m-d H:i';
        $d = DateTime::createFromFormat($format, $datetime);
        return $d && $d->format($format) === $datetime;
    }

    /**
     * Get violations by policeman
     */
    public function getViolationsByPoliceman($userID, $limit = 50, $offset = 0) {
        $query = "
            SELECT id, car_id, violation_reason, violation_datetime, checkpoint_position, fine_amount, created_at
            FROM violations
            WHERE user_id = ?
            ORDER BY violation_datetime DESC
            LIMIT ? OFFSET ?
        ";

        return $this->db->fetchAll($query, [$userID, $limit, $offset]);
    }

    /**
     * Get all violations (for admin)
     */
    public function getAllViolations($limit = 100, $offset = 0, $filters = []) {
        $query = "
            SELECT v.id, v.user_id, v.car_id, v.violation_reason, v.violation_datetime, 
                   v.checkpoint_position, v.fine_amount, v.created_at, u.username, u.full_name
            FROM violations v
            JOIN users u ON v.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        // Apply filters
        if (!empty($filters['user_id'])) {
            $query .= " AND v.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['car_id'])) {
            $query .= " AND v.car_id ILIKE ?";
            $params[] = '%' . $filters['car_id'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND v.violation_datetime >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND v.violation_datetime <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $query .= " ORDER BY v.violation_datetime DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($query, $params);
    }

    /**
     * Get violation statistics
     */
    public function getStatistics($dateFrom = null, $dateTo = null) {
        $params = [];

        // Total violations
        $query = "SELECT COUNT(*) as total_violations FROM violations WHERE 1=1";
        if ($dateFrom) {
            $query .= " AND violation_datetime >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $query .= " AND violation_datetime <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $total = $this->db->fetchOne($query, $params);

        // Total money collected
        $query = "SELECT SUM(fine_amount) as total_collected FROM violations WHERE 1=1";
        $params = [];
        if ($dateFrom) {
            $query .= " AND violation_datetime >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $query .= " AND violation_datetime <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $collected = $this->db->fetchOne($query, $params);

        // Violations by policeman
        $query = "
            SELECT u.id, u.username, u.full_name, COUNT(v.id) as violation_count, SUM(v.fine_amount) as amount_collected
            FROM users u
            LEFT JOIN violations v ON u.id = v.user_id
            WHERE u.role = 'policeman'
            GROUP BY u.id, u.username, u.full_name
            ORDER BY violation_count DESC
        ";

        $byPoliceman = $this->db->fetchAll($query);

        // Violations by date (last 30 days)
        $query = "
            SELECT DATE(violation_datetime) as violation_date, COUNT(*) as count, SUM(fine_amount) as total
            FROM violations
            WHERE violation_datetime >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(violation_datetime)
            ORDER BY violation_date DESC
        ";

        $byDate = $this->db->fetchAll($query);

        return [
            'total_violations' => $total['total_violations'] ?? 0,
            'total_collected' => $collected['total_collected'] ?? 0,
            'by_policeman' => $byPoliceman,
            'by_date' => $byDate,
        ];
    }

    /**
     * Get violation detail
     */
    public function getViolation($violationID) {
        $query = "
            SELECT v.*, u.username, u.full_name, u.email
            FROM violations v
            JOIN users u ON v.user_id = u.id
            WHERE v.id = ?
        ";

        return $this->db->fetchOne($query, [$violationID]);
    }

    /**
     * Count violations for user
     */
    public function countViolations($userID = null) {
        if ($userID) {
            $query = "SELECT COUNT(*) as count FROM violations WHERE user_id = ?";
            $result = $this->db->fetchOne($query, [$userID]);
        } else {
            $query = "SELECT COUNT(*) as count FROM violations";
            $result = $this->db->fetchOne($query);
        }

        return $result['count'] ?? 0;
    }
}
