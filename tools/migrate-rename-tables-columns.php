<?php
/**
 * Migration: Rename Database Tables and Columns (v1.2.9)
 *
 * Renames tables and columns to match new terminology:
 *   events         → programs       (individual shows/performances)
 *   events_meta    → events         (meta events, formerly conventions)
 *   event_requests → program_requests
 *   Column event_meta_id → event_id  (in programs, program_requests, credits)
 *   Column event_id → program_id     (in program_requests, FK to programs)
 *
 * SQLite 3.25+ required for RENAME COLUMN support.
 * Safe to run multiple times (idempotent guard).
 */

require_once __DIR__ . '/../config.php';

$dbPath = DB_PATH;

echo "=== Migration: Rename Tables & Columns (v1.2.9) ===\n\n";

if (!file_exists($dbPath)) {
    echo "ERROR: Database file not found: $dbPath\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "ERROR: Cannot connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

// Idempotent guard: check if already migrated
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('programs', $tables)) {
    echo "Already migrated — 'programs' table exists. Nothing to do.\n";
    exit(0);
}

if (!in_array('events', $tables)) {
    echo "ERROR: 'events' table not found. Database may not be initialized yet.\n";
    exit(1);
}

echo "Starting migration...\n\n";

$db->beginTransaction();

try {
    // Step 1: events → programs  (free up 'events' name before events_meta rename)
    echo "Step 1: Renaming table 'events' → 'programs'... ";
    $db->exec("ALTER TABLE events RENAME TO programs");
    echo "OK\n";

    // Step 2: events_meta → events
    if (in_array('events_meta', $tables)) {
        echo "Step 2: Renaming table 'events_meta' → 'events'... ";
        $db->exec("ALTER TABLE events_meta RENAME TO events");
        echo "OK\n";
    } else {
        echo "Step 2: 'events_meta' table not found, skipping.\n";
    }

    // Step 3: event_requests → program_requests
    if (in_array('event_requests', $tables)) {
        echo "Step 3: Renaming table 'event_requests' → 'program_requests'... ";
        $db->exec("ALTER TABLE event_requests RENAME TO program_requests");
        echo "OK\n";
    } else {
        echo "Step 3: 'event_requests' table not found, skipping.\n";
    }

    // Step 4: programs.event_meta_id → programs.event_id
    $programsCols = $db->query("PRAGMA table_info(programs)")->fetchAll(PDO::FETCH_ASSOC);
    $programsColNames = array_column($programsCols, 'name');
    if (in_array('event_meta_id', $programsColNames)) {
        echo "Step 4: Renaming column 'programs.event_meta_id' → 'programs.event_id'... ";
        $db->exec("ALTER TABLE programs RENAME COLUMN event_meta_id TO event_id");
        echo "OK\n";
    } else {
        echo "Step 4: Column 'event_meta_id' not found in 'programs', skipping.\n";
    }

    // Step 5: program_requests.event_id → program_requests.program_id
    // (Must be done BEFORE step 6 to avoid column name conflict!)
    $prTables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('program_requests', $prTables)) {
        $prCols = $db->query("PRAGMA table_info(program_requests)")->fetchAll(PDO::FETCH_ASSOC);
        $prColNames = array_column($prCols, 'name');
        if (in_array('event_id', $prColNames)) {
            echo "Step 5: Renaming column 'program_requests.event_id' → 'program_requests.program_id'... ";
            $db->exec("ALTER TABLE program_requests RENAME COLUMN event_id TO program_id");
            echo "OK\n";
        } else {
            echo "Step 5: Column 'event_id' not found in 'program_requests', skipping.\n";
        }

        // Step 6: program_requests.event_meta_id → program_requests.event_id
        $prCols2 = $db->query("PRAGMA table_info(program_requests)")->fetchAll(PDO::FETCH_ASSOC);
        $prColNames2 = array_column($prCols2, 'name');
        if (in_array('event_meta_id', $prColNames2)) {
            echo "Step 6: Renaming column 'program_requests.event_meta_id' → 'program_requests.event_id'... ";
            $db->exec("ALTER TABLE program_requests RENAME COLUMN event_meta_id TO event_id");
            echo "OK\n";
        } else {
            echo "Step 6: Column 'event_meta_id' not found in 'program_requests', skipping.\n";
        }
    } else {
        echo "Steps 5-6: 'program_requests' table not found, skipping.\n";
    }

    // Step 7: credits.event_meta_id → credits.event_id
    if (in_array('credits', $tables)) {
        $creditsCols = $db->query("PRAGMA table_info(credits)")->fetchAll(PDO::FETCH_ASSOC);
        $creditsColNames = array_column($creditsCols, 'name');
        if (in_array('event_meta_id', $creditsColNames)) {
            echo "Step 7: Renaming column 'credits.event_meta_id' → 'credits.event_id'... ";
            $db->exec("ALTER TABLE credits RENAME COLUMN event_meta_id TO event_id");
            echo "OK\n";
        } else {
            echo "Step 7: Column 'event_meta_id' not found in 'credits', skipping.\n";
        }
    } else {
        echo "Step 7: 'credits' table not found, skipping.\n";
    }

    $db->commit();

    echo "\n✅ Migration completed successfully!\n\n";
    echo "Tables renamed:\n";
    echo "  events         → programs\n";
    echo "  events_meta    → events\n";
    echo "  event_requests → program_requests\n";
    echo "\nColumns renamed:\n";
    echo "  programs.event_meta_id          → programs.event_id\n";
    echo "  program_requests.event_id       → program_requests.program_id\n";
    echo "  program_requests.event_meta_id  → program_requests.event_id\n";
    echo "  credits.event_meta_id           → credits.event_id\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "ERROR: Migration failed — " . $e->getMessage() . "\n";
    echo "Transaction rolled back. Database is unchanged.\n";
    exit(1);
}
