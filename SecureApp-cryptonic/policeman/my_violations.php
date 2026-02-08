<?php
/**
 * Policeman - View My Violations page
 * Display violations recorded by the current officer
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

$violationService = new ViolationService();
$violations = $violationService->getViolationsByPoliceman($userID, 50, 0);
$totalViolations = $violationService->countViolations($userID);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Violations - Police Traffic Violation Management</title>
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
            max-width: 1200px;
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

        /* Content Section */
        .content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin: 0 40px 40px 40px;
        }

        .content h2 {
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }

        /* Statistics Box */
        .stats {
            background: linear-gradient(135deg, #d5f4e6 0%, #e8f8f5 100%);
            padding: 15px 16px;
            border-left: 4px solid #27ae60;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #1e5631;
            border: 1px solid #a9dfbf;
        }

        .stats strong {
            color: #0d3b1d;
            font-weight: 600;
        }

        /* Action Buttons */
        .actions {
            margin: 25px 0;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(39, 174, 96, 0.3);
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: white;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 14px 15px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 13px;
            color: #555;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.02);
        }

        .amount {
            color: #27ae60;
            font-weight: 700;
            font-size: 14px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }

        .empty-state p {
            margin-bottom: 25px;
            font-size: 16px;
        }

        /* Links */
        .links {
            margin-top: 30px;
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
            .navbar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .content {
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

        <div class="content">
            <h2>My Recorded Violations</h2>

            <div class="stats">
                <strong>Total Violations Recorded:</strong> <?php echo htmlspecialchars($totalViolations); ?>
            </div>

            <div class="actions">
                <a href="/SecureApp-cryptonic/policeman/add_violation.php" class="btn">+ Record New Violation</a>
            </div>

            <?php if (empty($violations)): ?>
                <div class="empty-state">
                    <p>No violations recorded yet.</p>
                    <a href="/SecureApp-cryptonic/policeman/add_violation.php" class="btn">Record Your First Violation</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Vehicle ID</th>
                            <th>Reason</th>
                            <th>Location</th>
                            <th>Fine Amount</th>
                            <th>Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($violations as $v): ?>
                            <tr>
                                <td><?php echo Security::escapeOutput($v['violation_datetime']); ?></td>
                                <td><?php echo Security::escapeOutput($v['car_id']); ?></td>
                                <td><?php echo Security::escapeOutput(substr($v['violation_reason'], 0, 50)) . (strlen($v['violation_reason']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo Security::escapeOutput($v['checkpoint_position']); ?></td>
                                <td class="amount">PKR <?php echo number_format($v['fine_amount'], 2); ?></td>
                                <td><?php echo Security::escapeOutput($v['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="links">
                <a href="/SecureApp-cryptonic/policeman/add_violation.php">Record Violation</a>
            </div>
        </div>
    </div>
</body>
</html>
