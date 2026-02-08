This project was built during **the Cryptonic Area Cyber security Virtual Internship Program** to improve web skills and understanding .

# 🚔 Police Traffic Violation Management System

A basic secure, web application for managing traffic violations built with PHP logic and design, Stored and visualized using PostgreSQL, and deployed by Apache service . It was the basic experience to be more aware of how web applications are usually built .

The choice of php and postgreSQL is based on my needs to understand it by practice , so in CTF web challenges I can get the logic well .

### Core Functionality

- **Role-Based Access Control (RBAC)**: Separate interfaces for Admin and Police Officers
- **Violation Management**: Create, view, search, and filter traffic violations
- **Real-time Statistics**: Dashboard with violation counts, revenue tracking, and officer performance
- **Advanced Filtering**: Search by car ID, date range, checkpoint, and officer
- **Audit Logging**: Complete audit trail of all system actions
- **Session Management**: Secure session handling with timeout and fixation protection
- **Satisfying UI/UX**  as first and basic frontend

### User Roles

### 👮 Police Officer

- Add new traffic violations
- View personal violation history
- Search and filter own records
- Update violation details

### 👨‍💼 Admin

- View all violations system-wide
- Access comprehensive statistics
- Monitor officer performance
- Filter and export violation data
- View audit logs

## 📑 Table of Contents

- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [Security Features](#-security-implementation-details)
  - [SQL Injection Prevention](#1-sql-injection-prevention)
  - [XSS Prevention](#2-cross-site-scripting-xss-prevention)
  - [CSRF Protection](#3-csrf-cross-site-request-forgery-protection)
  - [Password Security](#4-password-security---configsecurityphp)
  - [Session Security](#5-session-security)
  - [Rate Limiting](#6-rate-limiting-brute-force-protection)
  - [Authentication Middleware](#7-authentication-middleware)
  - [Audit Logging](#8-audit-logging)
  - [Input Validation](#9-input-validation--sanitization)
  - [Routing & Entry Point](#10-routing--entry-point)
- [Demo Videos](#-demo-videos)
- [Support](#-support)

## 📦 Installation

### Prerequisites

- **XAMPP** (includes Apache 2.4+ and PHP 8.2+)
- **PostgreSQL 18+**
- **pgAdmin 4** (optional, for database management)
- **Git** (for cloning the repository)

### Step 1: Install Required Software

### Install XAMPP

1. Download from https://www.apachefriends.org/
2. Install to `C:\xampp`
3. Start Apache from XAMPP Control Panel

### Install PostgreSQL

1. Download from https://www.postgresql.org/download/
2. Install PostgreSQL 18
3. Remember your postgres password
4. Default port: `5432`

### Step 2: Clone Repository

bash

```jsx
cd C:\xampp\htdocs
git clone https://github.com/MizoxXXx/SecureApp-cryptonic.git
cd SecureApp-cryptonic
```

### Step 3: Enable PHP Extensions

Edit `C:\xampp\php\php.ini` in Apache pannel > config :

```jsx
# Uncomment these lines (remove the semicolon ; ):
extension=pdo_pgsql
extension=pgsql
extension=mbstring
extension=openssl
```

**Important**: Make sure `openssl` appears only ONCE in the file.

### Step 4: Enable Apache mod_rewrite

Edit `C:\xampp\apache\conf\httpd.conf`:

```jsx
# Uncomment this line:
LoadModule rewrite_module modules/mod_rewrite.so

# Find <Directory "C:/xampp/htdocs"> and change:
AllowOverride None
# To:
AllowOverride All
```

Restart Apache after making changes.

### Step 5: Configure PostgreSQL

### Create Database

bash

```jsx
# Open Command Prompt
cd "C:\Program Files\PostgreSQL\18\bin"
psql -U postgres
```

```jsx
- In psql prompt: 
CREATE DATABASE trafic_notes;
\c trafic_notes
\i C:/xampp/htdocs/SecureAppcryptonic/sql/schema.sql // import the schema.sql to do all the work
\q                                                   // exit 
```

### Start PostgreSQL Service


```jsx
# Press Win+R, type: services.msc
# Find: postgresql-x64-18
# Right-click → Start
# Right-click → Properties → Startup type: Automatic
```

### Step 6: Configure Environment Variables

`.env` file is already ceated in the project root as follows :

```jsx
# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=trafic_notes
DB_USER=postgres
DB_PASSWORD=your_postgres_password_here

# Application Settings
APP_NAME=traffic_notes
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/SecureApp-cryptonic

# Security Settings
SESSION_TIMEOUT=1800
SESSION_NAME=PVMS_SESSION
CSRF_TOKEN_EXPIRY=1800

# Rate Limiting
RATE_LIMIT_LOGIN_ATTEMPTS=5
RATE_LIMIT_LOGIN_WINDOW=900
```

### Step 7: Seed Database

**Database seeding** is the process of populating a database with **initial or sample data** automatically through a script, rather than manually entering data through forms or SQL commands.

```jsx
cd C:\xampp\htdocs\SecureApp-cryptonic
php sql\seed.php
```

**Expected output:**

```jsx
=== Police Traffic Violation System - Database Seeding ===

[1/3] Creating admin account...
  ✓ Admin created successfully

[2/3] Creating policeman accounts...
  ✓ Sergeant Mohammed Sinwar created
  ✓ Sergeant Yasser Ayach created

[3/3] Creating sample violations...
  ✓ Violation #1 created for ABC-1234
  ...

=== Database Seeding Complete ===
```

### Step 8: Access Application

Open browser and navigate to: `http://localhost/SecureApp-cryptonic/auth/login.php`

---

## ⚙️ Configuration

### Security Configuration

Located in `config/security.php`:

```php
// Session timeout (seconds)
define('SESSION_TIMEOUT', 1800); // 1/2 hour for better session security

// CSRF token expiry
define('CSRF_TOKEN_EXPIRY', 1800);

// Rate limiting
define('RATE_LIMIT_LOGIN_ATTEMPTS', 5);
define('RATE_LIMIT_LOGIN_WINDOW', 900); // 15 minutes

// Security headers - Check the code for more understanding
define('SECURITY_HEADERS', [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000',
    'Content-Security-Policy' => "default-src 'self'",
]);
```

---

## 🎯 Usage

### Admin Workflow

1. **Login** as admin
2. View **Dashboard** with statistics
3. **Filter violations** by date, officer, or car ID
4. **Monitor officer performance**
5. **Review audit logs** for security

### Officer Workflow

1. **Login** as police officer
2. Navigate to **Add Violation**
3. Fill in violation details:
    - Car registration number
    - Violation reason
    - Date and time
    - Checkpoint location
    - Fine amount
4. **Submit** violation
5. View in **My Violations**

---

## 🛡️ Security Implementation Details

### 1. SQL Injection Prevention

**Implementation**: PDO Prepared Statements 

The  main idea is to **separates SQL code from data**, preventing SQL injection by treating user input as data (not executable code).

> **Do not trust user input**
> 

```php
// ✅ SECURE - Parameterized query
public function fetchOne($query, $params = []) {
    $stmt = $this->pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Example usage:
$user = $db->fetchOne(
    "SELECT * FROM users WHERE username = ?",
    [$username]  // Parameter is safely bound
);

// ❌ INSECURE - Direct concatenation (NEVER DO THIS)
$query = "SELECT * FROM users WHERE username = '$username'";
```

---

### 2. Cross-Site Scripting (XSS) Prevention

**Implementation**: Output Escaping + Content Security Policy

```php
// Security class method
public static function escapeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Usage in templates:
echo Security::escapeOutput($user['username']);
// Converts: <script>alert('xss')</script>
// To: &lt;script&gt;alert('xss')&lt;/script&gt;
```

**Content Security Policy Header**:

```php
define('SECURITY_HEADERS', [
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline';"
]);
```

---

### 3. CSRF (Cross-Site Request Forgery) Protection

**Implementation**: Token-based validation

```php
// Generate token (in forms)
public static function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

// Validate token (on form submission)
public static function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Check expiry
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRY) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    
    // Timing-attack safe comparison
    return hash_equals($_SESSION['csrf_token'], $token);
}
```

**Usage in forms**:

```html
<form method="POST">
    <input type="hidden" name="csrf_token" 
           value="<?php echo Security::generateCSRFToken(); ?>">
    <!-- form fields -->
</form>
```

**Validation in handler**:

```php
if (!Security::validateCSRFToken($_POST['csrf_token'])) {
    die('Invalid CSRF token');
}
```

---

### 4. Password Security - `config/security.php`

**Implementation**: Bcrypt hashing with cost factor 12 

**Why Bcrypt?**

- Automatically salts passwords
- Adaptive function (cost increases with hardware)
- Resistant to rainbow table attacks
- Time-constant verification (prevents timing attacks)

---

### 5. Session Security

**Implementation**: Secure session configuration

```php
public static function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session BEFORE starting
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        
        // Set secure cookie parameters
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path' => '/',
            'domain' => '',
            'secure' => (APP_ENV === 'production'),  // HTTPS only
            'httponly' => true,                       // No JavaScript access in console
            'samesite' => 'Strict'                    // CSRF protection
        ]);
        
        session_name(SESSION_NAME);
        session_start();
        
        // Check timeout
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
```

**Session Fixation Prevention**:

```php
// Regenerate session ID on login
public static function regenerateSessionID() {
    session_regenerate_id(true);
}

// Called in login process:
Security::regenerateSessionID();
$_SESSION['user_id'] = $user['id'];
```

---

### 6. Rate Limiting (Brute Force Protection)

**Implementation**: Database-backed rate limiting - check the RateLimiter() class 

**Usage in login**:

```php
$rateLimiter = new RateLimiter();

if ($rateLimiter->isRateLimited($ip, 'login', 5, 900)) {
    die('Too many attempts. Try again in 15 minutes.');
}

$rateLimiter->recordAttempt($ip, 'login');

// On successful login:
$rateLimiter->resetAttempts($ip, 'login');
```

---

### 7. Authentication Middleware

**Implementation**: Role-based access control

**Usage in protected pages**:

```php
// Admin-only page
$auth = new AuthMiddleware();
$auth->requireAuth('admin');

// Any authenticated user
$auth->requireAuth();
```

---

### 8. Audit Logging

**Implementation**: Complete action tracking - check logs image in screenshots

**Logged events**:

- Login success/failure
- Violation creation/modification
- Unauthorized access attempts
- Admin actions
- Password changes

---

### 9. Input Validation & Sanitization

**Implementation**: Multi-layer validation - check `config/security.php` 

---

### 10. Routing & Entry Point

**Implementation**: Centralized routing especially in **.htaccess** 

---

## 📹 Demo Videos

### Admin Dashboard

<video controls src="releases/dashboard.mp4" title="Admin dashboard"></video>

### Add Violation (Police Officer)

<video controls src="releases/add_violation.mp4" title="Adding violations " auto ></video>

*Click to watch: Creating violations, form validation, and confirmation*

> **Note**: check the screenshots directory to view some images about viewing data in pgAdmin 4 pannel .
> 

## 📞 Support

For support, email [cyberexplorer27710@gmail.com](mailto:your-email@example.com) or open an issue in this repository.