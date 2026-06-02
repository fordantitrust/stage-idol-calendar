<?php
/**
 * Migration: Add FTS5 virtual tables, triggers, and initial index
 *
 * Creates:
 *   programs_fts  — indexes title, description, categories, location, organizer
 *   events_fts    — indexes name, description
 *   artists_fts   — indexes name
 *
 * Tokenizer: trigram (SQLite >= 3.43) with fallback to unicode61
 * Content tables: FTS index references original rows — no data duplication
 * Triggers:       9 triggers keep index in sync on INSERT/UPDATE/DELETE
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../data/calendar.db';

echo "=================================================\n";
echo "Migration: Add FTS5 Full-Text Search Tables\n";
echo "=================================================\n\n";

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode = WAL");

    // ── Step 1: SQLite version & tokenizer ──────────────────────────────────
    $sqliteVer = $db->query("SELECT sqlite_version()")->fetchColumn();
    echo "Step 1: SQLite version: $sqliteVer\n";
    $tokenizer = version_compare($sqliteVer, '3.43.0', '>=') ? 'trigram' : 'unicode61';
    echo "  Using tokenizer: $tokenizer\n\n";

    // ── Step 2: Check FTS5 support ───────────────────────────────────────────
    echo "Step 2: Checking FTS5 support...\n";
    try {
        $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS _fts5_test USING fts5(x)");
        $db->exec("DROP TABLE IF EXISTS _fts5_test");
        echo "  FTS5 is supported\n\n";
    } catch (PDOException $e) {
        echo "  ERROR: FTS5 not supported by this SQLite build.\n";
        echo "  " . $e->getMessage() . "\n";
        exit(1);
    }

    // ── Step 3: Create FTS virtual tables ───────────────────────────────────
    echo "Step 3: Creating FTS virtual tables...\n";

    // Drop and recreate to ensure tokenizer matches (idempotent rebuild)
    foreach (['programs_fts', 'events_fts', 'artists_fts'] as $t) {
        $exists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$t'")->fetchColumn();
        if ($exists) {
            echo "  $t already exists — rebuilding index\n";
            $db->exec("INSERT INTO $t($t) VALUES('rebuild')");
        }
    }

    // programs_fts
    if (!$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='programs_fts'")->fetchColumn()) {
        $db->exec("CREATE VIRTUAL TABLE programs_fts USING fts5(
            title, description, categories, location, organizer,
            content=programs, content_rowid=id,
            tokenize='$tokenizer'
        )");
        echo "  Created programs_fts ($tokenizer)\n";
    }

    // events_fts
    if (!$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events_fts'")->fetchColumn()) {
        $db->exec("CREATE VIRTUAL TABLE events_fts USING fts5(
            name, description,
            content=events, content_rowid=id,
            tokenize='$tokenizer'
        )");
        echo "  Created events_fts ($tokenizer)\n";
    }

    // artists_fts
    if (!$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='artists_fts'")->fetchColumn()) {
        $db->exec("CREATE VIRTUAL TABLE artists_fts USING fts5(
            name,
            content=artists, content_rowid=id,
            tokenize='$tokenizer'
        )");
        echo "  Created artists_fts ($tokenizer)\n";
    }
    echo "\n";

    // ── Step 4: Create triggers ──────────────────────────────────────────────
    echo "Step 4: Creating sync triggers...\n";

    $triggers = [
        // programs
        "programs_ai" => "AFTER INSERT ON programs BEGIN
            INSERT INTO programs_fts(rowid, title, description, categories, location, organizer)
            VALUES (new.id, new.title, COALESCE(new.description,''), COALESCE(new.categories,''), COALESCE(new.location,''), COALESCE(new.organizer,''));
        END",

        "programs_au" => "AFTER UPDATE ON programs BEGIN
            INSERT INTO programs_fts(programs_fts, rowid, title, description, categories, location, organizer)
            VALUES ('delete', old.id, old.title, COALESCE(old.description,''), COALESCE(old.categories,''), COALESCE(old.location,''), COALESCE(old.organizer,''));
            INSERT INTO programs_fts(rowid, title, description, categories, location, organizer)
            VALUES (new.id, new.title, COALESCE(new.description,''), COALESCE(new.categories,''), COALESCE(new.location,''), COALESCE(new.organizer,''));
        END",

        "programs_ad" => "AFTER DELETE ON programs BEGIN
            INSERT INTO programs_fts(programs_fts, rowid, title, description, categories, location, organizer)
            VALUES ('delete', old.id, old.title, COALESCE(old.description,''), COALESCE(old.categories,''), COALESCE(old.location,''), COALESCE(old.organizer,''));
        END",

        // events
        "events_ai" => "AFTER INSERT ON events BEGIN
            INSERT INTO events_fts(rowid, name, description)
            VALUES (new.id, new.name, COALESCE(new.description,''));
        END",

        "events_au" => "AFTER UPDATE ON events BEGIN
            INSERT INTO events_fts(events_fts, rowid, name, description)
            VALUES ('delete', old.id, old.name, COALESCE(old.description,''));
            INSERT INTO events_fts(rowid, name, description)
            VALUES (new.id, new.name, COALESCE(new.description,''));
        END",

        "events_ad" => "AFTER DELETE ON events BEGIN
            INSERT INTO events_fts(events_fts, rowid, name, description)
            VALUES ('delete', old.id, old.name, COALESCE(old.description,''));
        END",

        // artists
        "artists_ai" => "AFTER INSERT ON artists BEGIN
            INSERT INTO artists_fts(rowid, name) VALUES (new.id, new.name);
        END",

        "artists_au" => "AFTER UPDATE ON artists BEGIN
            INSERT INTO artists_fts(artists_fts, rowid, name) VALUES ('delete', old.id, old.name);
            INSERT INTO artists_fts(rowid, name) VALUES (new.id, new.name);
        END",

        "artists_ad" => "AFTER DELETE ON artists BEGIN
            INSERT INTO artists_fts(artists_fts, rowid, name) VALUES ('delete', old.id, old.name);
        END",
    ];

    $existingTriggers = $db->query("SELECT name FROM sqlite_master WHERE type='trigger'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($triggers as $name => $body) {
        if (in_array($name, $existingTriggers)) {
            echo "  $name already exists, skipping\n";
        } else {
            $db->exec("CREATE TRIGGER $name $body");
            echo "  Created trigger: $name\n";
        }
    }
    echo "\n";

    // ── Step 5: Rebuild initial index ────────────────────────────────────────
    echo "Step 5: Building initial FTS index...\n";
    $db->exec("INSERT INTO programs_fts(programs_fts) VALUES('rebuild')");
    $pCount = $db->query("SELECT COUNT(*) FROM programs")->fetchColumn();
    echo "  programs_fts: indexed $pCount rows\n";

    $db->exec("INSERT INTO events_fts(events_fts) VALUES('rebuild')");
    $eCount = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
    echo "  events_fts:   indexed $eCount rows\n";

    $db->exec("INSERT INTO artists_fts(artists_fts) VALUES('rebuild')");
    $aCount = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
    echo "  artists_fts:  indexed $aCount rows\n";
    echo "\n";

    echo "=================================================\n";
    echo "FTS5 migration completed successfully!\n";
    echo "=================================================\n";
    echo "\nTokenizer : $tokenizer\n";
    echo "Tables    : programs_fts, events_fts, artists_fts\n";
    echo "Triggers  : " . count($triggers) . " (ai/au/ad × 3 tables)\n";

} catch (PDOException $e) {
    echo "\nMigration failed!\nError: " . $e->getMessage() . "\n";
    exit(1);
}
