<?php
/**
 * Database seeding script for development/testing
 * Creates sample data for testing the application
 * 
 * Usage: php sql/seed.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuditLog.php';
require_once __DIR__ . '/../includes/RateLimiter.php';      
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/ViolationService.php';

echo "=== Police Traffic Violation System - Database Seeding ===\n\n";

$db = Database::getInstance();
$userService = new UserService();
$violationService = new ViolationService();

try {
    // 1. Create admin user
    echo "[1/3] Creating admin account...\n";
    $adminResult = $userService->register(
        'admin',
        'admin@police.local',
        'Chief Officer Ahmed',
        'Admin@123',
        'Admin@123',
        'admin'
    );

    if ($adminResult['success']) {
        echo "  ✓ Admin created successfully\n";
        $adminID = $adminResult['user_id'];
    } else {
        // Admin might already exist
        $admin = $db->fetchOne("SELECT id FROM users WHERE username = 'admin'");
        if ($admin) {
            echo "  ✓ Admin already exists\n";
            $adminID = $admin['id'];
        } else {
            throw new Exception("Failed to create admin: " . implode(", ", $adminResult['errors']));
        }
    }

    // 2. Create sample policemen
    echo "\n[2/3] Creating policeman accounts...\n";

    $policemen = [
        [
            'username' => 'officer1',
            'email' => 'simoS@police.local',
            'full_name' => 'Sergeant Mohammed Sinwar',
        ],
        [
            'username' => 'officer2',
            'email' => 'yasserA@police.local',
            'full_name' => 'Sergeant Yasser Ayach',
        ],
    ];

    $officerIDs = [];
    foreach ($policemen as $officer) {
        $result = $userService->register(
            $officer['username'],
            $officer['email'],
            $officer['full_name'],
            'Officer@123',
            'Officer@123',
            'policeman'
        );

        if ($result['success']) {
            echo "  ✓ {$officer['full_name']} created\n";
            $officerIDs[] = $result['user_id'];
        } else {
            // Check if exists
            $existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$officer['username']]);
            if ($existing) {
                echo "  ✓ {$officer['full_name']} already exists\n";
                $officerIDs[] = $existing['id'];
            } else {
                echo "  ✗ Failed to create {$officer['full_name']}\n";
            }
        }
    }

    // 3. Create sample violations
    echo "\n[3/3] Creating sample violations...\n";

    $violations = [
        [
            'officer_id' => $officerIDs[0] ?? 1,
            'car_id' => 'ABC-1234',
            'reason' => 'Speeding: Vehicle was traveling 80 km/h in a 60 km/h zone',
            'datetime' => date('Y-m-d H:i', strtotime('-5 days')),
            'checkpoint' => 'Main Street, Downtown',
            'amount' => 2000,
        ],
        [
            'officer_id' => $officerIDs[1] ?? 2,
            'car_id' => 'DEF-5678',
            'reason' => 'Traffic light violation: Ran red light at intersection',
            'datetime' => date('Y-m-d H:i', strtotime('-3 days')),
            'checkpoint' => 'Circular Road, Clifton',
            'amount' => 1500,
        ],
        [
            'officer_id' => $officerIDs[0] ?? 1,
            'car_id' => 'GHI-9012',
            'reason' => 'No parking zone violation: Vehicle parked in no-parking area',
            'datetime' => date('Y-m-d H:i', strtotime('-2 days')),
            'checkpoint' => 'University Road',
            'amount' => 1000,
        ],
        [
            'officer_id' => $officerIDs[1] ?? 2,
            'car_id' => 'JKL-3456',
            'reason' => 'Improper lane change: Unsafe lane switching without signal',
            'datetime' => date('Y-m-d H:i', strtotime('-1 days')),
            'checkpoint' => 'Super Highway',
            'amount' => 1800,
        ],
        [
            'officer_id' => $officerIDs[0] ?? 1,
            'car_id' => 'MNO-7890',
            'reason' => 'Invalid license plate: Damaged registration number',
            'datetime' => date('Y-m-d H:i'),
            'checkpoint' => 'Mall Road',
            'amount' => 500,
        ],
    ];

    $count = 1;
    foreach ($violations as $violation) {
        $result = $violationService->createViolation(
            $violation['officer_id'],
            $violation['car_id'],
            $violation['reason'],
            $violation['datetime'],
            $violation['checkpoint'],
            $violation['amount']
        );

        if ($result['success']) {
            echo "  ✓ Violation #" . $count . " created for {$violation['car_id']}\n";
        } else {
            echo "  ✗ Failed to create violation #" . $count . "\n";
        }
        $count++;
    }

    echo "\n=== Database Seeding Complete ===\n";
    echo "\nTest Credentials:\n";
    echo "  Admin:    admin / Admin@123\n";
    echo "  Officer1: officer1 / Officer@123\n";
    echo "  Officer2: officer2 / Officer@123\n";
    echo "\nAccess the application at:\n";
    echo "  http://localhost/SecureApp-cryptonic/auth/login.php\n";

} catch (Exception $e) {
    echo "\n✗ Error during seeding: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>