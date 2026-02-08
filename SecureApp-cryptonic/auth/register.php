<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/AuditLog.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

// Initialize session
AuthMiddleware::initSession();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    $auth = new AuthMiddleware();
    if ($auth->isAuthenticated()) {
        header('Location: /SecureApp-cryptonic/auth/login.php');
        exit;
    }
}

$errors = [];
$success = false;
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $fullName = $_POST['full_name'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $userService = new UserService();
        $result = $userService->register($username, $email, $fullName, $password, $confirmPassword, 'policeman');

        if ($result['success']) {
            $success = true;
            $successMessage = "Registration successful! Redirecting to login...";
            header('refresh:2;url=/SecureApp-cryptonic/auth/login.php');
        } else {
            $errors = $result['errors'];
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
    <title>Register - Police Traffic Violation Management</title>
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

        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 480px;
            padding: 50px;
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
            margin-bottom: 40px;
        }

        .header .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }

        .subtitle {
            text-align: center;
            color: #7f8c8d;
            margin: 0;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #ecf0f1;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        input:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alerts {
            margin-bottom: 24px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 13px;
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

        .password-requirements {
            background: linear-gradient(135deg, #f8f9fa 0%, #ecf0f1 100%);
            padding: 14px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 10px;
            line-height: 1.8;
            border: 1px solid #dfe6e9;
        }

        .requirement {
            margin: 5px 0;
            color: #7f8c8d;
            display: flex;
            align-items: center;
        }

        .requirement::before {
            content: "○";
            margin-right: 8px;
            font-weight: bold;
        }

        .requirement.met {
            color: #27ae60;
        }

        .requirement.met::before {
            content: "✓";
        }

        .links {
            text-align: center;
            margin-top: 24px;
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

        @media (max-width: 600px) {
            .register-container {
                padding: 35px 25px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="header">
            <div class="icon">🚔</div>
            <h1>Create Account</h1>
            <p class="subtitle">Join the Police System</p>
        </div>

        <?php if ($success): ?>
            <div class="alerts">
                <div class="alert alert-success">
                    ✓ <?php echo Security::escapeOutput($successMessage); ?>
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

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo Security::escapeOutput($csrfToken); ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required
                        placeholder="Choose your username"
                        value="<?php echo Security::escapeOutput($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required
                        placeholder="your.email@police.local"
                        value="<?php echo Security::escapeOutput($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required
                        placeholder="Your Full Name"
                        value="<?php echo Security::escapeOutput($_POST['full_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                        placeholder="Create a strong password">
                    <div class="password-requirements">
                        <div class="requirement">At least 8 characters</div>
                        <div class="requirement">One uppercase letter (A-Z)</div>
                        <div class="requirement">One number (0-9)</div>
                        <div class="requirement">One special character (!@#$%^&*)</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                        placeholder="Re-enter your password">
                </div>

                <button type="submit" class="btn">Create Account</button>
            </form>

            <div class="links">
                <p>Already have an account?</p>
                <a href="/SecureApp-cryptonic/auth/login.php">→ Sign In Here</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const requirements = document.querySelectorAll('.requirement');

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const value = this.value;
                const checks = [
                    value.length >= 8,
                    /[A-Z]/.test(value),
                    /[0-9]/.test(value),
                    /[!@#$%^&*]/.test(value)
                ];

                requirements.forEach((req, index) => {
                    if (checks[index]) {
                        req.classList.add('met');
                    } else {
                        req.classList.remove('met');
                    }
                });
            });
        }
    </script>
</body>
</html>
