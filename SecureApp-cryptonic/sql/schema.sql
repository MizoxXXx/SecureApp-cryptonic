-- PostgreSQL Schema for Police Traffic Violation Management System
-- Secure database design with proper constraints and indexing

-- Drop existing tables if they exist (for development only)
-- DROP TABLE IF EXISTS violations CASCADE;
-- DROP TABLE IF EXISTS audit_logs CASCADE;
-- DROP TABLE IF EXISTS rate_limit CASCADE;
-- DROP TABLE IF EXISTS users CASCADE;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'policeman')),
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Create index on username for faster lookups
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Violations table
CREATE TABLE IF NOT EXISTS violations (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    car_id VARCHAR(20) NOT NULL,
    violation_reason TEXT NOT NULL,
    violation_datetime TIMESTAMP NOT NULL,
    checkpoint_position VARCHAR(255) NOT NULL,
    fine_amount DECIMAL(10, 2) NOT NULL CHECK (fine_amount > 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for faster queries
CREATE INDEX IF NOT EXISTS idx_violations_user_id ON violations(user_id);
CREATE INDEX IF NOT EXISTS idx_violations_datetime ON violations(violation_datetime);
CREATE INDEX IF NOT EXISTS idx_violations_car_id ON violations(car_id);

-- Audit logs table for security tracking
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INTEGER,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Index for audit logs
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at);

-- Rate limit table for brute force protection
CREATE TABLE IF NOT EXISTS rate_limit (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempt_count INTEGER DEFAULT 1,
    first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ip_address, action)
);

-- Index for rate limiting lookups
CREATE INDEX IF NOT EXISTS idx_rate_limit_ip ON rate_limit(ip_address);

-- Session table for secure session management (optional, if using database sessions)
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER,
    data TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires_at ON sessions(expires_at);
