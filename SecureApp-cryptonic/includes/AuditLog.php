<?php

class AuditLog {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function logAction($userID, $action, $entityType = null, $entityID = null, $details = null) {
        $data = [
            'user_id' => $userID,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityID,
            'ip_address' => Security::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'details' => $details ? json_encode($details) : null,
        ];

        try {
            $this->db->insert('audit_logs', $data);
        } catch (Exception $e) {
            error_log("Audit Log Error: " . $e->getMessage());
        }
    }

    public function logLoginAttempt($username, $success, $details = null) {
        $this->logAction(
            null,
            $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED',
            'user',
            null,
            array_merge(['username' => $username], $details ?? [])
        );
    }

    public function logViolationSubmission($userID, $violationID, $carID) {
        $this->logAction(
            $userID,
            'VIOLATION_SUBMITTED',
            'violation',
            $violationID,
            ['car_id' => $carID]
        );
    }

    public function logAdminAccess($userID, $page, $details = null) {
        $this->logAction(
            $userID,
            'ADMIN_ACCESS',
            'page',
            null,
            array_merge(['page' => $page], $details ?? [])
        );
    }

    public function getLoginAttempts($ipAddress, $window = 900) {
        $timeLimit = date('Y-m-d H:i:s', time() - $window);
        
        $query = "
            SELECT COUNT(*) as attempts
            FROM audit_logs
            WHERE action = 'LOGIN_FAILED'
            AND ip_address = ?
            AND created_at > ?
        ";

        $result = $this->db->fetchOne($query, [$ipAddress, $timeLimit]); // prepared statement usage
        return $result['attempts'] ?? 0;
    }

    public function getAuditLogs($limit = 100, $offset = 0, $action = null) {
        $query = "SELECT * FROM audit_logs";
        $params = [];

        if ($action) {
            $query .= " WHERE action = ?";
            $params[] = $action;
        }

        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($query, $params); // user input is treated as data 
    }

    public function getUserActivityLog($userID, $limit = 50) {
        $query = "
            SELECT * FROM audit_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ";

        return $this->db->fetchAll($query, [$userID, $limit]);
    }
}
