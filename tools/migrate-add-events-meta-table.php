<?php
/**
 * Migration: Add events_meta table for multi-event support
 *
 * Creates events_meta table and adds event_meta_id column to:
 * - events
 * - event_requests
 * - credits
 *
 * Also creates a default event entry and assigns existing data to it.
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../data/calendar.db';

echo "==========================================\n";
echo "Migration: Multi-Event Support\n";
echo "==========================================\n\n";

try {
    echo "Connecting to database: $dbPath\n";
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n\n";

    // Step 1: Create events_meta table
    echo "Step 1: Creating events_meta table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS events_meta (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            description TEXT,
            start_date DATE,
            end_date DATE,
            venue_mode TEXT DEFAULT 'multi',
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_events_meta_slug ON events_meta(slug)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_events_meta_active ON events_meta(is_active)");
    echo "  events_meta table created\n\n";

    // Step 2: Add event_meta_id column to events table
    echo "Step 2: Adding event_meta_id to events table...\n";
    $columns = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

    if (!in_array('event_meta_id', $columnNames)) {
        $db->exec("ALTER TABLE events ADD COLUMN event_meta_id INTEGER REFERENCES events_meta(id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_events_meta_id ON events(event_meta_id)");
        echo "  event_meta_id column added to events\n";
    } else {
        echo "  event_meta_id column already exists in events (skipped)\n";
    }

    // Step 3: Add event_meta_id column to event_requests table
    echo "\nStep 3: Adding event_meta_id to event_requests table...\n";
    // Check if event_requests table exists
    $tableExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='event_requests'")->fetch();
    if ($tableExists) {
        $columns = $db->query("PRAGMA table_info(event_requests)")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');

        if (!in_array('event_meta_id', $columnNames)) {
            $db->exec("ALTER TABLE event_requests ADD COLUMN event_meta_id INTEGER REFERENCES events_meta(id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_requests_meta_id ON event_requests(event_meta_id)");
            echo "  event_meta_id column added to event_requests\n";
        } else {
            echo "  event_meta_id column already exists in event_requests (skipped)\n";
        }
    } else {
        echo "  event_requests table not found (skipped)\n";
    }

    // Step 4: Add event_meta_id column to credits table
    echo "\nStep 4: Adding event_meta_id to credits table...\n";
    $tableExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'")->fetch();
    if ($tableExists) {
        $columns = $db->query("PRAGMA table_info(credits)")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');

        if (!in_array('event_meta_id', $columnNames)) {
            $db->exec("ALTER TABLE credits ADD COLUMN event_meta_id INTEGER");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_credits_meta_id ON credits(event_meta_id)");
            echo "  event_meta_id column added to credits (NULL = global credit)\n";
        } else {
            echo "  event_meta_id column already exists in credits (skipped)\n";
        }
    } else {
        echo "  credits table not found (skipped)\n";
    }

    // Step 5: Create default event entry
    echo "\nStep 5: Creating default event entry...\n";
    $defaultSlug = defined('DEFAULT_EVENT_SLUG') ? DEFAULT_EVENT_SLUG : 'default';

    $existing = $db->prepare("SELECT id FROM events_meta WHERE slug = :slug");
    $existing->execute([':slug' => $defaultSlug]);
    $defaultEvent = $existing->fetch(PDO::FETCH_ASSOC);

    if (!$defaultEvent) {
        // Get date range from existing events
        $dateRange = $db->query("SELECT MIN(DATE(start)) as min_date, MAX(DATE(end)) as max_date FROM events")->fetch(PDO::FETCH_ASSOC);

        $venueMode = defined('VENUE_MODE') ? VENUE_MODE : 'multi';
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO events_meta (slug, name, description, start_date, end_date, venue_mode, is_active, created_at, updated_at)
            VALUES (:slug, :name, :description, :start_date, :end_date, :venue_mode, 1, :now, :now2)
        ");
        $stmt->execute([
            ':slug' => $defaultSlug,
            ':name' => 'Idol Stage Event',
            ':description' => 'Default event (migrated from existing data)',
            ':start_date' => $dateRange['min_date'],
            ':end_date' => $dateRange['max_date'],
            ':venue_mode' => $venueMode,
            ':now' => $now,
            ':now2' => $now
        ]);
        $defaultEventId = $db->lastInsertId();
        echo "  Default event created (id: $defaultEventId, slug: $defaultSlug)\n";
    } else {
        $defaultEventId = $defaultEvent['id'];
        echo "  Default event already exists (id: $defaultEventId, skipped)\n";
    }

    // Step 6: Assign existing events to default event
    echo "\nStep 6: Assigning existing data to default event...\n";

    $stmt = $db->prepare("UPDATE events SET event_meta_id = :id WHERE event_meta_id IS NULL");
    $stmt->execute([':id' => $defaultEventId]);
    $eventsUpdated = $stmt->rowCount();
    echo "  Events updated: $eventsUpdated\n";

    // Assign event_requests
    $tableExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='event_requests'")->fetch();
    if ($tableExists) {
        $stmt = $db->prepare("UPDATE event_requests SET event_meta_id = :id WHERE event_meta_id IS NULL");
        $stmt->execute([':id' => $defaultEventId]);
        $requestsUpdated = $stmt->rowCount();
        echo "  Event requests updated: $requestsUpdated\n";
    }

    // Credits: leave NULL (global)
    echo "  Credits: left as NULL (global credits)\n";

    // Display summary
    echo "\n==========================================\n";
    echo "Migration Summary\n";
    echo "==========================================\n\n";

    // Show events_meta table
    echo "events_meta table:\n";
    $stmt = $db->query("SELECT * FROM events_meta");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  ID: {$row['id']}, Slug: {$row['slug']}, Name: {$row['name']}, ";
        echo "Venue: {$row['venue_mode']}, Active: {$row['is_active']}\n";
    }
    echo "\n";

    // Show counts
    $eventsCount = $db->query("SELECT COUNT(*) as c FROM events WHERE event_meta_id IS NOT NULL")->fetch()['c'];
    $eventsNullCount = $db->query("SELECT COUNT(*) as c FROM events WHERE event_meta_id IS NULL")->fetch()['c'];
    echo "Events with event_meta_id: $eventsCount\n";
    echo "Events without event_meta_id: $eventsNullCount\n";

    echo "\n==========================================\n";
    echo "Migration completed successfully!\n";
    echo "==========================================\n";

} catch (PDOException $e) {
    echo "\n==========================================\n";
    echo "Migration failed!\n";
    echo "==========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
