<?php


class Database {
    private $pdo;
    private static $instance = null;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                PDO_DSN,
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
                    PDO::ATTR_PERSISTENT => false, // Don't use persistent connections
                ]
            );

            // Set timezone to UTC for consistency
            $this->pdo->exec("SET TIME ZONE 'UTC'");

        } catch (PDOException $e) {
            // Log error but don't expose details to user
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please contact the chief.");
        }
    }

    // Singleton pattern for database connection
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute a prepared statement
     * Security: Always use this method, never build queries with concatenation
     * 
     * @param string $query SQL query with placeholders (? or :name)
     * @param array $params Parameters to bind
     * @return PDOStatement
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage() . " Query: " . $query);
            throw new Exception("Database query failed");
        }
    }

    // Fetch one row
    public function fetchOne($query, $params = []) {
        return $this->query($query, $params)->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch all rows
    public function fetchAll($query, $params = []) {
        return $this->query($query, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Insert record
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->query($query, array_values($data));

        return $this->pdo->lastInsertId();
    }

    // Update record
    public function update($table, $data, $where, $whereParams = []) {
        $set = implode(', ', array_map(function ($key) {
            return $key . ' = ?';
        }, array_keys($data)));

        $query = "UPDATE {$table} SET {$set} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);

        return $this->query($query, $params)->rowCount();
    }

    // Delete record
    public function delete($table, $where, $params = []) {
        $query = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($query, $params)->rowCount();
    }

    // Get last insert ID
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    // Begin transaction
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    // Commit transaction
    public function commit() {
        return $this->pdo->commit();
    }

    // Rollback transaction
    public function rollback() {
        return $this->pdo->rollback();
    }

    // Get raw PDO connection (use with caution)
    public function getPDO() {
        return $this->pdo;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize Database instance");
    }
}
