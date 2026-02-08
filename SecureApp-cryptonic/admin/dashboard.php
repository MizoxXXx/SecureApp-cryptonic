<?php
/**
 * Admin - Dashboard page
 * View statistics and all violations
 * Security: Read-only access, no delete/update from frontend
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
$auth->requireRole('admin');

$userID = $auth->getUserID();
$user = $auth->getCurrentUser();

// Log admin access
$audit = new AuditLog();
$audit->logAdminAccess($userID, 'dashboard');

// Get statistics and violations
$violationService = new ViolationService();

// Get date filters
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

if ($dateFrom) {
    $dateFrom = Security::sanitizeInput($dateFrom);
}
if ($dateTo) {
    $dateTo = Security::sanitizeInput($dateTo);
}

$stats = $violationService->getStatistics($dateFrom, $dateTo);

// Get violations with filters
$filters = [];
if ($dateFrom) {
    $filters['date_from'] = $dateFrom;
}
if ($dateTo) {
    $filters['date_to'] = $dateTo;
}

$violations = $violationService->getAllViolations(100, 0, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Police Traffic Violation Management</title>
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
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Navbar Styling */
        .navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
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
            color: #ecf0f1;
            font-size: 14px;
        }

        .user-info strong {
            color: #3498db;
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

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
            padding: 0 40px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border-top: 5px solid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, currentColor, transparent);
            opacity: 0.05;
            border-radius: 50%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-card h3 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #95a5a6;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 40px;
            font-weight: 700;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.blue {
            border-top-color: #3498db;
            color: #3498db;
        }

        .stat-card.green {
            border-top-color: #27ae60;
            color: #27ae60;
        }

        .stat-card.orange {
            border-top-color: #f39c12;
            color: #f39c12;
        }

        /* Content Section */
        .content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin: 0 40px 40px 40px;
        }

        .content h2 {
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }

        /* Filter Section */
        .filter-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #ecf0f1 100%);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: auto auto auto;
            gap: 20px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input {
            padding: 10px 12px;
            border: 2px solid #dfe6e9;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        }

        /* Buttons */
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 11px 24px;
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
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-clear {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .btn-clear:hover {
            box-shadow: 0 5px 15px rgba(149, 165, 166, 0.3);
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

        /* Section Title */
        .section-title {
            margin-top: 40px;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            padding-bottom: 12px;
            border-bottom: 2px solid #3498db;
            display: inline-block;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
        }

        .empty-state p {
            font-size: 14px;
        }

        @media (max-width: 1024px) {
            .filter-section {
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navbar">
            <h1>Police Traffic Violation Management - Admin</h1>
            <div class="user-info">
                <span>Chief: <strong><?php echo Security::escapeOutput($user['full_name']); ?></strong></span>
                <a href="/SecureApp-cryptonic/auth/logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <h3>Total Violations</h3>
                <div class="value"><?php echo htmlspecialchars($stats['total_violations']); ?></div>
            </div>

            <div class="stat-card green">
                <h3>Total Revenue</h3>
                <div class="value">PKR <?php echo number_format($stats['total_collected'], 0); ?></div>
            </div>

            <div class="stat-card orange">
                <h3>Active Officers</h3>
                <div class="value"><?php echo htmlspecialchars(count($stats['by_policeman'])); ?></div>
            </div>
        </div>

        <!-- Violations Data -->
        <div class="content">
            <h2>Violations Management</h2>

            <!-- Filters based on time -->
            <div class="filter-section">
                <div class="filter-group">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" 
                        value="<?php echo Security::escapeOutput($dateFrom ?? ''); ?>">
                </div>
                <div class="filter-group">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to"
                        value="<?php echo Security::escapeOutput($dateTo ?? ''); ?>">
                </div>
                <div>
                    <button class="btn" onclick="applyFilters()">Filter</button>
                    <button class="btn btn-clear" onclick="clearFilters()">Clear</button>
                </div>
            </div>

            <!-- Violations by Officer Section -->
            <div class="section-title">Violations by Officer</div>
            <?php if (!empty($stats['by_policeman'])): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Officer Name</th>
                            <th>Username</th>
                            <th>Violations Count</th>
                            <th>Amount Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['by_policeman'] as $officer): ?>
                            <tr>
                                <td><?php echo Security::escapeOutput($officer['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo Security::escapeOutput($officer['username'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($officer['violation_count']); ?></td>
                                <td class="amount">PKR <?php echo number_format($officer['amount_collected'] ?? 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999;">No officer data available.</p>
            <?php endif; ?>

            <!-- All Violations Section for the car -->
            <div class="section-title">All Recorded Violations</div>
            <?php if (!empty($violations)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Vehicle ID</th>
                                <th>Officer</th>
                                <th>Violation Reason</th>
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
                                    <td><?php echo Security::escapeOutput($v['full_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo Security::escapeOutput(substr($v['violation_reason'], 0, 40)) . (strlen($v['violation_reason']) > 40 ? '...' : ''); ?></td>
                                    <td><?php echo Security::escapeOutput($v['checkpoint_position']); ?></td>
                                    <td class="amount">PKR <?php echo number_format($v['fine_amount'], 2); ?></td>
                                    <td><?php echo Security::escapeOutput($v['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #999;">No violations found.</p>
            <?php endif; ?>

            <!-- Violations by Date Section -->
            <div class="section-title">Violations by Date (Last 30 Days)</div>
            <?php if (!empty($stats['by_date'])): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Count</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['by_date'] as $day): ?>
                            <tr>
                                <td><?php echo Security::escapeOutput($day['violation_date']); ?></td>
                                <td><?php echo htmlspecialchars($day['count']); ?></td>
                                <td class="amount">PKR <?php echo number_format($day['total'] ?? 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999;">No date-based data available.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function applyFilters() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            
            let url = '?';
            if (dateFrom) url += 'date_from=' + encodeURIComponent(dateFrom);

            // Add date_to if provided (with & separator if date_from exists)
            if (dateTo) {
                if (dateFrom) url += '&';
                url += 'date_to=' + encodeURIComponent(dateTo);
            }
            window.location.href = url || '?'; // Redirect to filtered URL or reset
        }

        function clearFilters() {
            window.location.href = '?'; // Remove all filters by reloading base page
        }
    </script>
</body>
</html>
