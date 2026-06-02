<?php
/**
 * Migration: Add TOTP 2FA columns to admin_users.
 */

require_once __DIR__ . '/../config.php';

echo "Migration: Add Admin 2FA Columns\n";
echo str_repeat('=', 42) . "\n\n";

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $table = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'")->fetch();
    if (!$table) {
        echo "Error: admin_users table does not exist.\n";
        echo "Run setup.php or tools/migrate-add-admin-users-table.php first.\n";
        exit(1);
    }

    $columns = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $defs = [
        'twofa_enabled' => 'INTEGER DEFAULT 0',
        'twofa_secret' => 'TEXT DEFAULT NULL',
        'twofa_backup_codes' => 'TEXT DEFAULT NULL',
        'twofa_confirmed_at' => 'DATETIME DEFAULT NULL',
        'twofa_last_used_step' => 'INTEGER DEFAULT NULL',
    ];

    foreach ($defs as $name => $definition) {
        if (in_array($name, $columns, true)) {
            echo "  - {$name} already exists\n";
            continue;
        }
        $db->exec("ALTER TABLE admin_users ADD COLUMN {$name} {$definition}");
        echo "  + Added {$name}\n";
    }

    echo "\nDone. Admin 2FA columns are ready.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
