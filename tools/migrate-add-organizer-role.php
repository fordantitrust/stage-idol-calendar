<?php
/**
 * Migration: Add organizer role ownership schema.
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../data/calendar.db';

echo "==========================================\n";
echo "Migration: Add Organizer Role Schema\n";
echo "==========================================\n\n";

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');

    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admin_users', $tables, true) || !in_array('events', $tables, true)) {
        echo "Error: admin_users and events tables are required. Run setup.php first.\n";
        exit(1);
    }

    $adminCols = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('role', $adminCols, true)) {
        $db->exec("ALTER TABLE admin_users ADD COLUMN role TEXT DEFAULT 'admin'");
        echo "Added admin_users.role\n";
    }
    $db->exec("UPDATE admin_users SET role = 'agent' WHERE role IS NULL OR role NOT IN ('admin', 'agent', 'organizer')");

    $eventCols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('created_by_user_id', $eventCols, true)) {
        $db->exec("ALTER TABLE events ADD COLUMN created_by_user_id INTEGER DEFAULT NULL");
        echo "Added events.created_by_user_id\n";
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS event_organizers (
            event_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            assigned_by INTEGER DEFAULT NULL,
            assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id, user_id),
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES admin_users(id) ON DELETE SET NULL
        )
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_event_organizers_event_id ON event_organizers(event_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_event_organizers_user_id ON event_organizers(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_events_created_by_user_id ON events(created_by_user_id)");

    $db->exec("DELETE FROM event_organizers WHERE event_id NOT IN (SELECT id FROM events) OR user_id NOT IN (SELECT id FROM admin_users)");

    echo "Organizer schema is ready.\n";
    echo "Roles: admin, agent, organizer\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
