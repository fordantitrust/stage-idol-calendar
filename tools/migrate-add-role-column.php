<?php
/**
 * Migration: Add role column to admin_users table
 *
 * Adds role-based access control:
 * - 'admin' role: Full access (manage users, backup, everything)
 * - 'agent' role: Events management only (no backup, no user management)
 *
 * All existing users default to 'admin' role.
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../data/calendar.db';

echo "==========================================\n";
echo "Migration: Add Role Column to admin_users\n";
echo "==========================================\n\n";

try {
    echo "Connecting to database: $dbPath\n";
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n\n";

    // Check if admin_users table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo "Error: admin_users table does not exist.\n";
        echo "Please run migrate-add-admin-users-table.php first.\n";
        exit(1);
    }

    // Step 1: Check if role column already exists
    echo "Step 1: Checking for role column...\n";
    $columns = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_ASSOC);
    $hasRole = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'role') {
            $hasRole = true;
            break;
        }
    }

    if ($hasRole) {
        echo "  role column already exists, skipping ALTER TABLE\n\n";
    } else {
        echo "  Adding role column...\n";
        $db->exec("ALTER TABLE admin_users ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
        echo "  role column added successfully (default: 'admin')\n\n";
    }

    // Display summary
    echo "==========================================\n";
    echo "Migration Summary\n";
    echo "==========================================\n\n";

    echo "admin_users table:\n";
    $stmt = $db->query("SELECT id, username, display_name, role, is_active, created_at FROM admin_users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $role = $row['role'] ?? 'admin';
        echo "  ID: {$row['id']}, Username: {$row['username']}, ";
        echo "Display: {$row['display_name']}, Role: {$role}, Active: {$row['is_active']}\n";
    }

    echo "\n==========================================\n";
    echo "Migration completed successfully!\n";
    echo "==========================================\n";
    echo "\nRoles:\n";
    echo "  - admin: Full access (manage users, backup, everything)\n";
    echo "  - agent: Events management only\n";

} catch (PDOException $e) {
    echo "\n==========================================\n";
    echo "Migration failed!\n";
    echo "==========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
