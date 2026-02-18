<?php
/**
 * Migration: Add admin_users table
 *
 * Moves admin credentials from config/admin.php to SQLite database.
 * Seeds with current ADMIN_USERNAME / ADMIN_PASSWORD_HASH from config.
 * Supports multiple admin users.
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../data/calendar.db';

echo "==========================================\n";
echo "Migration: Admin Users Table\n";
echo "==========================================\n\n";

try {
    echo "Connecting to database: $dbPath\n";
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n\n";

    // Step 1: Create admin_users table
    echo "Step 1: Creating admin_users table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            display_name TEXT,
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login_at DATETIME
        )
    ");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_admin_users_username ON admin_users(username)");
    echo "  admin_users table created\n\n";

    // Step 2: Seed with current config credentials (if table is empty)
    echo "Step 2: Seeding default admin user...\n";
    $count = $db->query("SELECT COUNT(*) as c FROM admin_users")->fetch(PDO::FETCH_ASSOC)['c'];

    if ($count == 0) {
        $username = defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin';
        $passwordHash = defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : password_hash('admin', PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO admin_users (username, password_hash, display_name, is_active, created_at, updated_at)
            VALUES (:username, :password_hash, :display_name, 1, :now, :now2)
        ");
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => $passwordHash,
            ':display_name' => $username,
            ':now' => $now,
            ':now2' => $now,
        ]);
        echo "  Default admin user created (username: $username)\n";
    } else {
        echo "  admin_users table already has data ($count users), skipped seeding\n";
    }

    // Display summary
    echo "\n==========================================\n";
    echo "Migration Summary\n";
    echo "==========================================\n\n";

    echo "admin_users table:\n";
    $stmt = $db->query("SELECT id, username, display_name, is_active, created_at FROM admin_users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  ID: {$row['id']}, Username: {$row['username']}, ";
        echo "Display: {$row['display_name']}, Active: {$row['is_active']}\n";
    }

    echo "\n==========================================\n";
    echo "Migration completed successfully!\n";
    echo "==========================================\n";
    echo "\nYou can now manage admin users through the Admin UI.\n";
    echo "The config constants ADMIN_USERNAME and ADMIN_PASSWORD_HASH\n";
    echo "are kept as fallback and can be removed after verifying.\n";

} catch (PDOException $e) {
    echo "\n==========================================\n";
    echo "Migration failed!\n";
    echo "==========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
