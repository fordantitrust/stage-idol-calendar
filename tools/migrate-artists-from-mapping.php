<?php
/**
 * Migrate Artists from Mapping File
 * Idol Stage Timetable
 *
 * อ่าน artists-mapping.json แล้ว populate:
 *   Pass 1 → artists (พร้อม is_group flag, group_id = NULL ก่อน)
 *   Pass 2 → set group_id ให้สมาชิก (self-referential lookup ในตัวเอง)
 *   Pass 3 → program_artists (เชื่อม programs ↔ artists)
 *
 * Prerequisites:
 *   1. php migrate-add-artists-table.php
 *   2. php generate-artists-mapping.php
 *   3. Review/edit data/artists-mapping.json
 *
 * Usage:
 *   cd tools && php migrate-artists-from-mapping.php --dry-run   # preview
 *   cd tools && php migrate-artists-from-mapping.php             # apply
 */

require_once __DIR__ . '/../config.php';

$dryRun = in_array('--dry-run', $argv);

echo "=== Migrate Artists from Mapping" . ($dryRun ? " [DRY RUN]" : "") . " ===\n\n";

// ---- Validate prerequisites ----

if (!defined('DB_PATH') || !file_exists(DB_PATH)) {
    echo "ERROR: Database not found at: " . (defined('DB_PATH') ? DB_PATH : '(DB_PATH not defined)') . "\n";
    exit(1);
}

$mappingPath = __DIR__ . '/../data/artists-mapping.json';
if (!file_exists($mappingPath)) {
    echo "ERROR: artists-mapping.json not found.\n";
    echo "Run: php generate-artists-mapping.php\n";
    exit(1);
}

$mapping = json_decode(file_get_contents($mappingPath), true);
if (!$mapping || !isset($mapping['artists']) || !is_array($mapping['artists'])) {
    echo "ERROR: Invalid or empty artists-mapping.json\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "ERROR: Cannot connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

foreach (['artists', 'program_artists'] as $t) {
    if (!$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $db->quote($t))->fetch()) {
        echo "ERROR: Table '$t' not found. Run: php migrate-add-artists-table.php\n";
        exit(1);
    }
}

// ---- Prepare data ----

$activeEntries = array_values(array_filter($mapping['artists'], fn($e) => !$e['skip']));
$skippedCount  = count($mapping['artists']) - count($activeEntries);

// variant (lowercase) → canonical
$variantToCanonical = [];
foreach ($activeEntries as $entry) {
    if (empty(trim($entry['canonical']))) continue;
    foreach ($entry['variants'] as $variant) {
        $variantToCanonical[mb_strtolower(trim($variant), 'UTF-8')] = $entry['canonical'];
    }
}

$canonicalNames = array_values(array_unique(array_values($variantToCanonical)));
sort($canonicalNames);

// canonical → [is_group, group_name]
$canonicalMeta = [];
foreach ($activeEntries as $entry) {
    $canonicalMeta[$entry['canonical']] = [
        'is_group' => !empty($entry['is_group']) ? 1 : 0,
        'group'    => $entry['group'] ?? null,
    ];
}

// count members per group (for summary)
$groupMemberCount = [];
foreach ($activeEntries as $entry) {
    if (!empty($entry['group'])) {
        $groupMemberCount[$entry['group']] = ($groupMemberCount[$entry['group']] ?? 0) + 1;
    }
}

$groupEntries = array_filter($activeEntries, fn($e) => !empty($e['is_group']));

echo "Mapping summary:\n";
echo "  Total entries    : " . count($mapping['artists']) . "\n";
echo "  Active artists   : " . count($activeEntries) . "\n";
echo "  Skipped          : $skippedCount\n";
echo "  Groups (is_group): " . count($groupEntries) . "\n\n";

// ---- Load programs ----

$stmt = $db->query("
    SELECT id, title, categories
    FROM programs
    WHERE categories IS NOT NULL AND categories != ''
    ORDER BY id
");
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$programArtistMap = []; // program_id → [canonical, ...]
$unmappedNames    = [];

foreach ($programs as $program) {
    foreach (explode(',', $program['categories']) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $key = mb_strtolower($part, 'UTF-8');
        if (isset($variantToCanonical[$key])) {
            $programArtistMap[$program['id']][] = $variantToCanonical[$key];
        } else {
            $unmappedNames[$part] = ($unmappedNames[$part] ?? 0) + 1;
        }
    }
}

foreach ($programArtistMap as $pid => $names) {
    $programArtistMap[$pid] = array_values(array_unique($names));
}

$totalLinks = array_sum(array_map('count', $programArtistMap));

echo "Programs to link  : " . count($programArtistMap) . " / " . count($programs) . "\n";
echo "Total links       : $totalLinks\n\n";

// ---- Dry run ----

if ($dryRun) {
    echo "--- Pass 1: artists to create (" . count($canonicalNames) . ") ---\n";
    foreach ($canonicalNames as $name) {
        $meta  = $canonicalMeta[$name];
        $label = $meta['is_group'] ? " [GROUP]" : "";
        $grp   = $meta['group'] ? " → member of: {$meta['group']}" : "";
        $exists = $db->prepare("SELECT id FROM artists WHERE name = ?");
        $exists->execute([$name]);
        echo "  " . ($exists->fetchColumn() ? "[EXISTS]" : "[CREATE]") . "$label $name$grp\n";
    }

    echo "\n--- Pass 2: group_id to set ---\n";
    foreach ($activeEntries as $entry) {
        if (empty($entry['group'])) continue;
        echo "  {$entry['canonical']} → group: {$entry['group']}\n";
    }

    echo "\n--- Pass 3: sample program links (first 20) ---\n";
    $shown = 0;
    foreach ($programArtistMap as $pid => $names) {
        if ($shown >= 20) { echo "  ... (and more)\n"; break; }
        $prog = array_filter($programs, fn($p) => $p['id'] === $pid);
        $prog = reset($prog);
        echo "  program #$pid \"{$prog['title']}\"\n";
        foreach ($names as $n) echo "    → $n\n";
        $shown++;
    }

    if (!empty($unmappedNames)) {
        echo "\n--- Unmapped/skipped (" . count($unmappedNames) . ") ---\n";
        arsort($unmappedNames);
        foreach ($unmappedNames as $name => $count) echo "  [$count programs] $name\n";
    }

    echo "\nDry run complete. Run without --dry-run to apply.\n";
    exit(0);
}

// ---- Apply ----

try {
    $db->beginTransaction();

    // Pass 1: Insert all artists (group_id = NULL for now)
    $insertArtist  = $db->prepare("INSERT OR IGNORE INTO artists (name, is_group) VALUES (?, ?)");
    $artistCreated = 0;
    $artistSkipped = 0;

    foreach ($canonicalNames as $name) {
        $isGroup = $canonicalMeta[$name]['is_group'] ?? 0;
        $insertArtist->execute([$name, $isGroup]);
        if ($db->lastInsertId()) {
            $artistCreated++;
        } else {
            $artistSkipped++;
        }
    }

    // Load name → id map
    $artistIdMap = [];
    $stmt = $db->query("SELECT id, name FROM artists");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $artistIdMap[$row['name']] = (int)$row['id'];
    }

    // Pass 2: Set group_id (self-referential lookup)
    $updateGroup  = $db->prepare("UPDATE artists SET group_id = ? WHERE id = ?");
    $groupLinked  = 0;
    $groupMissing = [];

    foreach ($activeEntries as $entry) {
        if (empty($entry['group'])) continue;

        $memberId = $artistIdMap[$entry['canonical']] ?? null;
        if (!$memberId) continue;

        // Exact match first, then case-insensitive
        $groupId = $artistIdMap[$entry['group']] ?? null;
        if (!$groupId) {
            $s = $db->prepare("SELECT id FROM artists WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $s->execute([$entry['group']]);
            $groupId = $s->fetchColumn() ?: null;
        }

        if ($groupId) {
            $updateGroup->execute([$groupId, $memberId]);
            $groupLinked++;
        } else {
            $groupMissing[] = "  \"{$entry['canonical']}\" → group \"{$entry['group']}\" not found";
        }
    }

    // Pass 3: Insert program_artists
    $insertLink   = $db->prepare("INSERT OR IGNORE INTO program_artists (program_id, artist_id) VALUES (?, ?)");
    $linksCreated = 0;
    $linksSkipped = 0;

    foreach ($programArtistMap as $programId => $names) {
        foreach ($names as $name) {
            $artistId = $artistIdMap[$name] ?? null;
            if (!$artistId) continue;
            $insertLink->execute([$programId, $artistId]);
            if ($db->lastInsertId()) {
                $linksCreated++;
            } else {
                $linksSkipped++;
            }
        }
    }

    $db->commit();

} catch (PDOException $e) {
    $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "--- Results ---\n";
echo "  artists created  : $artistCreated\n";
echo "  artists skipped  : $artistSkipped (already existed)\n";
echo "  group_id set     : $groupLinked\n";
echo "  program links    : $linksCreated created, $linksSkipped skipped\n";

if (!empty($groupMissing)) {
    echo "\nWarning — group not resolved (" . count($groupMissing) . "):\n";
    foreach ($groupMissing as $msg) echo $msg . "\n";
}

if (!empty($unmappedNames)) {
    echo "\nUnmapped/skipped (" . count($unmappedNames) . ") — not linked:\n";
    arsort($unmappedNames);
    foreach ($unmappedNames as $name => $count) echo "  [$count programs] $name\n";
}

echo "\nMigration completed successfully.\n";
echo "Note: programs.categories field is unchanged (kept as backup).\n";
exit(0);
