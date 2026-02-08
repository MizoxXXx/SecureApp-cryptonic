<?php
/**
 * Policeman - Add Violation page
 * Secure form for recording traffic violations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/ViolationService.php';
require_once __DIR__ . '/../includes/AuditLog.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

// Initialize and enforce authentication
AuthMiddleware::initSession();
$auth = new AuthMiddleware();
$auth->requireRole('policeman');

$userID = $auth->getUserID();
$user = $auth->getCurrentUser();

$errors = [];
$success = false;
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $carID = $_POST['car_id'] ?? '';
        $reason = $_POST['violation_reason'] ?? '';
        $violationDate = $_POST['violation_date'] ?? '';
        $violationTime = $_POST['violation_time'] ?? '';
        $checkpoint = $_POST['checkpoint_position'] ?? '';
        $fineAmount = $_POST['fine_amount'] ?? '';

        // Combine date and time
        $violationDatetime = $violationDate . ' ' . $violationTime;

        $violationService = new ViolationService();
        $result = $violationService->createViolation(
            $userID,
            $carID,
            $reason,
            $violationDatetime,
            $checkpoint,
            $fineAmount
        );

        if ($result['success']) {
            $success = true;
            $successMessage = "Violation recorded successfully! Violation ID: " . $result['violation_id'];
        } else {
            $errors = $result['errors'];
        }
    }
}

// Generate CSRF token
$csrfToken = Security::generateCSRFToken();

// Get current datetime for max value
$currentDateTime = date('Y-m-d\TH:i');
$currentDate = date('Y-m-d');
$currentTime = date('H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Violation - Police Traffic Violation Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ecf0f1 0%, #f8f9fa 100%);
            padding: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Navbar Styling */
        .navbar {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            margin-bottom: 40px;
        }

        .navbar h1 {
            font-size: 24px;
            color: white;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info span {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        .user-info strong {
            color: #ecf0f1;
            font-weight: 600;
        }

        .btn-logout {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Form Container */
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin: 0 40px 40px 40px;
        }

        .form-container h2 {
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="time"],
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #27ae60;
            background: white;
            box-shadow: 0 0 0 4px rgba(39, 174, 96, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Alerts */
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

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, #d5f4e6 0%, #e8f8f5 100%);
            padding: 15px 16px;
            border-left: 4px solid #27ae60;
            border-radius: 6px;
            font-size: 13px;
            color: #1e5631;
            margin-bottom: 25px;
            border: 1px solid #a9dfbf;
        }

        /* Submit Button */
        .btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
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
            box-shadow: 0 10px 25px rgba(39, 174, 96, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* Links */
        .links {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
            text-align: center;
        }

        .links a {
            display: inline-block;
            color: #27ae60;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 10px 20px;
            border: 2px solid #27ae60;
            border-radius: 6px;
            transition: all 0.3s ease;
            margin: 5px;
        }

        .links a:hover {
            background: #27ae60;
            color: white;
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .form-container {
                padding: 25px;
                margin: 0 20px 40px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navbar">
            <h1>Police Traffic Violation System</h1>
            <div class="user-info">
                <span>Officer: <strong><?php echo Security::escapeOutput($user['full_name']); ?></strong></span>
                <a href="/SecureApp-cryptonic/auth/logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <div class="form-container">
            <h2>Record Traffic Violation</h2>

            <div class="info-box">
                ℹ All fields are required. The system timestamp is automatically recorded server-side for security.
            </div>

            <?php if ($success): ?>
                <div class="alerts">
                    <div class="alert alert-success">
                        <?php echo Security::escapeOutput($successMessage); ?>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alerts">
                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-error">
                                <?php echo Security::escapeOutput($error); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escapeOutput($csrfToken); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="car_id">Vehicle Registration Number</label>
                            <input type="text" id="car_id" name="car_id" required maxlength="20"
                                value="<?php echo Security::escapeOutput($_POST['car_id'] ?? ''); ?>"
                                placeholder="e.g., ABC-1234">
                        </div>

                        <div class="form-group">
                            <label for="fine_amount">Fine Amount (PKR)</label>
                            <input type="number" id="fine_amount" name="fine_amount" required min="0" max="10000" step="100"
                                value="<?php echo Security::escapeOutput($_POST['fine_amount'] ?? ''); ?>"
                                placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="violation_reason">Violation Reason</label>
                        <textarea id="violation_reason" name="violation_reason" required
                            maxlength="500"><?php echo Security::escapeOutput($_POST['violation_reason'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="violation_date">Violation Date</label>
                            <input type="date" id="violation_date" name="violation_date" required max="<?php echo $currentDate; ?>"
                                value="<?php echo Security::escapeOutput($_POST['violation_date'] ?? $currentDate); ?>">
                        </div>

                        <div class="form-group">
                            <label for="violation_time">Violation Time</label>
                            <input type="time" id="violation_time" name="violation_time" required
                                value="<?php echo Security::escapeOutput($_POST['violation_time'] ?? $currentTime); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="checkpoint_position">Checkpoint Location</label>
                        <input type="text" id="checkpoint_position" name="checkpoint_position" required maxlength="255"
                            value="<?php echo Security::escapeOutput($_POST['checkpoint_position'] ?? ''); ?>"
                            placeholder="e.g., Main Street, Downtown">
                    </div>

                    <button type="submit" class="btn">Record Violation</button>
                </form>

                <div class="links">
                    <a href="/SecureApp-cryptonic/policeman/my_violations.php">View My Violations</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
