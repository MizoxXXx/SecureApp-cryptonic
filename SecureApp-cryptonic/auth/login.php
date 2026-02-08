<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuditLog.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/UserService.php';

// Initialize session
AuthMiddleware::initSession();

// Check if already logged in
$isLoggedIn = false;
$currentUser = null;
$currentRole = null;
if (isset($_SESSION['user_id'])) {
    $auth = new AuthMiddleware();
    if ($auth->isAuthenticated()) {
        $isLoggedIn = true;
        $currentUser = $auth->getUsername();
        $currentRole = $auth->getUserRole();
        // Only redirect if not requesting to view this page
        if (!isset($_GET['alreadylogged'])) {
            // Set flag to show message instead of redirecting
            $_GET['alreadylogged'] = '1';
        }
    }
}

$errors = [];
$success = false;
$logoutMessage = '';

// Check if user just logged out
if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1') {
    $logoutMessage = '✓ You have been successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        // Get credentials
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Get client IP for rate limiting
        $clientIP = Security::getClientIP();
        $rateLimiter = new RateLimiter();

        // Check rate limiting
        if ($rateLimiter->isRateLimited($clientIP, 'login', RATE_LIMIT_LOGIN_ATTEMPTS, RATE_LIMIT_LOGIN_WINDOW)) {
            $errors[] = 'Too many login attempts. Please try again in ' . ceil(RATE_LIMIT_LOGIN_WINDOW / 60) . ' minutes.';
        } else {
            // Record attempt
            $rateLimiter->recordAttempt($clientIP, 'login');

            // Authenticate
            $userService = new UserService();
            $result = $userService->authenticate($username, $password);

            if ($result['success']) {
                // Login successful - reset rate limit
                $rateLimiter->resetAttempts($clientIP, 'login');

                // Create authentication session
                $auth = new AuthMiddleware();
                $loginResult = $auth->login($result['user_id'], $result['username']);

                if ($loginResult['success']) {
                    // Redirect based on role
                    if ($result['role'] === 'admin') {
                        header('Location: /SecureApp-cryptonic/admin/dashboard.php');
                    } else {
                        header('Location: /SecureApp-cryptonic/policeman/add_violation.php');
                    }
                    exit;
                } else {
                    $errors[] = $loginResult['error'];
                }
            } else {
                $errors = $result['errors'];
            }
        }
    }
}

// Generate CSRF token
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Police Traffic Violation Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .main-wrapper {
            display: flex;
            gap: 40px;
            align-items: center;
            max-width: 900px;
            width: 100%;
        }

        .branding {
            flex: 1;
            color: white;
            display: none;
        }

        @media (min-width: 768px) {
            .branding {
                display: block;
            }
        }

        .branding h2 {
            font-size: 32px;
            margin-bottom: 20px;
            font-weight: 300;
            letter-spacing: 1px;
        }

        .branding p {
            font-size: 16px;
            line-height: 1.8;
            opacity: 0.9;
            margin-bottom: 15px;
        }

        .branding .feature {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .branding .feature::before {
            content: "✓";
            width: 24px;
            height: 24px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: bold;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 18px 20px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header .icon {
            font-size: 32px;
            margin-bottom: 6px;
        }

        .header h1 {
            font-size: 20px;
            margin-bottom: 3px;
            color: #2c3e50;
            font-weight: 600;
        }

        .header .subtitle {
            color: #7f8c8d;
            font-size: 14px;
            margin: 0;
        }

        .form-group {
            margin-bottom: 12px;
        }

        label {
            display: block;
            margin-bottom: 4px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #ecf0f1;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        }

        .btn {
            width: 100%;
            padding: 9px;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alerts {
            margin-bottom: 10px;
        }

        .alert {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 12px;
            border-left: 4px solid;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fadbd8;
            color: #78281f;
            border-left-color: #e74c3c;
        }

        .alert-success {
            background: #d5f4e6;
            color: #1e5631;
            border-left-color: #27ae60;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 12px 0;
            color: #bdc3c7;
            font-size: 12px;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #ecf0f1;
        }

        .divider span {
            padding: 0 12px;
        }

        .links {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
        }

        .links p {
            color: #7f8c8d;
            margin-bottom: 8px;
        }

        .links a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .links a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        .demo-box {
            background: linear-gradient(135deg, #ecf0f1 0%, #f8f9fa 100%);
            padding: 10px 12px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #dfe6e9;
        }

        .demo-box strong {
            display: block;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .demo-credentials {
            font-size: 11px;
            color: #555;
            line-height: 1.6;
        }

        .demo-credentials .role {
            font-weight: 600;
            color: #2c3e50;
        }

        @media (max-width: 600px) {
            .login-container {
                padding: 35px 25px;
            }

            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="branding">
            <h2>Traffic Enforcement System</h2>
            <p>Streamlined management of traffic violations with real-time analytics and secure officer tracking.</p>
            <div class="feature">Secure role-based access control</div>
            <div class="feature">Real-time violation tracking</div>
            <div class="feature">Comprehensive audit logging</div>
            <div class="feature">Advanced security features</div>
        </div>

        <div class="login-container">
            <div class="header">
                <div class="icon">🚔</div>
                <h1>Police System</h1>
                <p class="subtitle">Secure Access Portal</p>
            </div>

            <?php if (!empty($logoutMessage)): ?>
                <div class="alerts">
                    <div class="alert alert-success">
                        <?php echo Security::escapeOutput($logoutMessage); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isLoggedIn && isset($_GET['alreadylogged'])): ?>
                <div class="alerts">
                    <div class="alert alert-success">
                        ✓ Already logged in as <strong><?php echo Security::escapeOutput($currentUser); ?></strong>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 12px;">
                    <p style="color: #7f8c8d; margin-bottom: 10px; font-size: 12px;">Choose an action:</p>
                    <div style="display: flex; gap: 8px; flex-direction: column;">
                        <a href="<?php echo ($currentRole === 'admin') ? '/SecureApp-cryptonic/admin/dashboard.php' : '/SecureApp-cryptonic/policeman/add_violation.php'; ?>" 
                           style="padding: 8px 16px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; display: block; font-size: 12px;">
                            → Dashboard
                        </a>
                        <a href="/SecureApp-cryptonic/auth/logout.php" 
                           style="padding: 8px 16px; background: #e74c3c; color: white; text-decoration: none; border-radius: 6px; display: block; font-size: 12px;">
                            → Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alerts">
                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-error">
                                ⚠️ <?php echo Security::escapeOutput($error); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo Security::escapeOutput($_SERVER['REQUEST_URI']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo Security::escapeOutput($csrfToken); ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus
                        placeholder="Enter your username"
                        value="<?php echo Security::escapeOutput($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                        placeholder="Enter your password">
                </div>

                <button type="submit" class="btn">Sign In</button>
                </form>

                <div class="divider"><span>OR</span></div>

                <div class="links">
                    <p>Don't have an account?</p>
                    <a href="/SecureApp-cryptonic/auth/register.php">→ Create New Account</a>
                </div>

                <div class="demo-box">
                    <strong>🔓 Demo Credentials</strong>
                    <div class="demo-credentials">
                        <div><span class="role">Admin:</span> admin / Admin@123</div>
                        <div><span class="role">Officer:</span> officer1 / Officer@123</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
