<?php
/**
 * Bulk import artist social links from a CSV (or JSON) file.
 *
 * Usage:
 *   php tools/import-artist-socials.php <file.csv> [--dry-run] [--overwrite]
 *
 * Matching: each row is matched to an artist by NAME first (exact name, then
 * artist_variants alias), and falls back to artist ID if the name is missing
 * or not found. Ambiguous name matches (>1 artist) are skipped and reported.
 *
 * CSV format: a header row is required. Recognised columns (case-insensitive,
 * flexible aliases) — only the ones present are used:
 *   name / artist            → match key (name)
 *   id / artist_id           → match key (fallback)
 *   facebook  / fb  / social_facebook
 *   instagram / ig  / social_instagram
 *   twitter   / x   / social_twitter
 *   tiktok    / tt  / social_tiktok
 *
 * Behaviour:
 *   - Only http(s):// URLs are stored (mirrors sanitize_social_url()).
 *   - An empty cell leaves that field UNCHANGED (no wipe).
 *   - By default a non-empty value only fills a field that is currently NULL/empty.
 *     Pass --overwrite to replace existing values too.
 *   - --dry-run prints what would change without writing.
 *
 * JSON format (when file ends in .json): an array of objects using the same keys.
 */

require_once __DIR__ . '/../config.php';

// ----- Parse arguments ------------------------------------------------------
$file      = null;
$dryRun    = false;
$overwrite = false;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--dry-run')        { $dryRun = true; }
    elseif ($arg === '--overwrite')  { $overwrite = true; }
    elseif (strpos($arg, '--') === 0) { fwrite(STDERR, "Unknown option: $arg\n"); exit(1); }
    elseif ($file === null)          { $file = $arg; }
}

if ($file === null) {
    echo "Usage: php tools/import-artist-socials.php <file.csv> [--dry-run] [--overwrite]\n";
    exit(1);
}
if (!is_file($file)) {
    fwrite(STDERR, "❌ File not found: $file\n");
    exit(1);
}

echo "=== Artist Social Links Import ===\n";
echo "File: $file\n";
echo $dryRun    ? "Mode: DRY RUN (no writes)\n" : "Mode: WRITE\n";
echo $overwrite ? "Existing values: OVERWRITE\n" : "Existing values: keep (fill empty only)\n";
echo "\n";

// ----- Column alias map -----------------------------------------------------
$aliases = [
    'name'             => ['name', 'artist', 'artist_name', 'ชื่อ'],
    'id'               => ['id', 'artist_id'],
    'social_facebook'  => ['facebook', 'fb', 'social_facebook'],
    'social_instagram' => ['instagram', 'ig', 'social_instagram'],
    'social_twitter'   => ['twitter', 'x', 'twitter/x', 'social_twitter'],
    'social_tiktok'    => ['tiktok', 'tt', 'social_tiktok'],
];
$socialCols = ['social_facebook', 'social_instagram', 'social_twitter', 'social_tiktok'];

/** Normalise a header cell to a canonical key, or null if unrecognised. */
function resolve_header(string $h, array $aliases): ?string {
    $h = strtolower(trim($h));
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip UTF-8 BOM
    foreach ($aliases as $canon => $list) {
        if (in_array($h, $list, true)) return $canon;
    }
    return null;
}

/** Mirror admin/api.php sanitize_social_url(): only http(s) URLs kept. */
function clean_social_url(string $raw): ?string {
    $url = trim($raw);
    return ($url !== '' && preg_match('/^https?:\/\//i', $url)) ? $url : null;
}

// ----- Read rows ------------------------------------------------------------
$rows = [];   // each = ['name'=>?, 'id'=>?, 'social_*'=>? raw string]
$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if ($ext === 'json') {
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) {
        fwrite(STDERR, "❌ JSON must be an array of objects\n");
        exit(1);
    }
    foreach ($data as $obj) {
        if (!is_array($obj)) continue;
        $row = [];
        foreach ($obj as $k => $v) {
            $canon = resolve_header((string)$k, $aliases);
            if ($canon !== null) $row[$canon] = is_string($v) ? $v : (string)$v;
        }
        if ($row) $rows[] = $row;
    }
} else {
    $fh = fopen($file, 'r');
    if (!$fh) { fwrite(STDERR, "❌ Cannot open file\n"); exit(1); }

    $header = fgetcsv($fh);
    if (!$header) { fwrite(STDERR, "❌ Empty file or missing header row\n"); exit(1); }

    // Map column index → canonical key
    $colMap = [];
    foreach ($header as $idx => $cell) {
        $canon = resolve_header((string)$cell, $aliases);
        if ($canon !== null) $colMap[$idx] = $canon;
    }
    if (!$colMap) {
        fwrite(STDERR, "❌ No recognised columns in header. Need at least name/id + a social column.\n");
        exit(1);
    }
    echo "Detected columns: " . implode(', ', array_values($colMap)) . "\n\n";

    while (($cells = fgetcsv($fh)) !== false) {
        if (count(array_filter($cells, fn($c) => trim((string)$c) !== '')) === 0) continue; // blank line
        $row = [];
        foreach ($colMap as $idx => $canon) {
            $row[$canon] = isset($cells[$idx]) ? (string)$cells[$idx] : '';
        }
        $rows[] = $row;
    }
    fclose($fh);
}

if (!$rows) { echo "No data rows found.\n"; exit(0); }

// ----- Connect DB -----------------------------------------------------------
try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "❌ Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

/** Resolve a row to a single artist id, or [null, reason]. */
function resolve_artist_id(PDO $db, array $row): array {
    $name = isset($row['name']) ? trim($row['name']) : '';
    $id   = isset($row['id']) && trim($row['id']) !== '' ? (int)$row['id'] : null;

    // 1) Try by name (exact, then variant alias)
    if ($name !== '') {
        $stmt = $db->prepare("SELECT id FROM artists WHERE name = :n");
        $stmt->execute([':n' => $name]);
        $hits = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $stmt->closeCursor();
        if (count($hits) === 1) return [(int)$hits[0], 'name'];
        if (count($hits) > 1)  return [null, 'ambiguous name (' . count($hits) . ' artists)'];

        // variant alias
        $stmt = $db->prepare("SELECT DISTINCT artist_id FROM artist_variants WHERE variant = :n");
        $stmt->execute([':n' => $name]);
        $vhits = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $stmt->closeCursor();
        if (count($vhits) === 1) return [(int)$vhits[0], 'variant'];
        if (count($vhits) > 1)  return [null, 'ambiguous variant (' . count($vhits) . ' artists)'];
    }

    // 2) Fallback by id
    if ($id !== null) {
        $stmt = $db->prepare("SELECT id FROM artists WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $found = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ($found !== false) return [(int)$found, 'id'];
        return [null, "id $id not found"];
    }

    return [null, $name !== '' ? "name not found: $name" : 'no name or id'];
}

// ----- Process --------------------------------------------------------------
$updated = $skipped = $nochange = $errors = 0;
$rowNum  = 1; // header is row 1 for CSV

foreach ($rows as $row) {
    $rowNum++;
    [$artistId, $reason] = resolve_artist_id($db, $row);
    $label = trim($row['name'] ?? '') !== '' ? $row['name'] : ('id=' . ($row['id'] ?? '?'));

    if ($artistId === null) {
        echo "  ⏭️  row $rowNum [$label] — skipped: $reason\n";
        $skipped++;
        continue;
    }

    // Current values
    $stmt = $db->prepare("SELECT name, " . implode(', ', $socialCols) . " FROM artists WHERE id = :id");
    $stmt->execute([':id' => $artistId]);
    $cur = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $set = [];
    $params = [':id' => $artistId];
    $changes = [];
    foreach ($socialCols as $col) {
        if (!array_key_exists($col, $row)) continue;       // column not in file
        $val = clean_social_url($row[$col]);
        if ($val === null) continue;                        // empty / invalid → leave unchanged
        $existing = $cur[$col] ?? null;
        if (!$overwrite && $existing !== null && $existing !== '') continue; // keep existing
        if ($existing === $val) continue;                   // already same
        $set[] = "$col = :$col";
        $params[":$col"] = $val;
        $changes[] = "$col=$val" . ($existing ? " (was: $existing)" : '');
    }

    if (!$set) { $nochange++; continue; }

    echo "  ✏️  row $rowNum [{$cur['name']}] (#$artistId via $reason): " . implode('; ', $changes) . "\n";

    if (!$dryRun) {
        try {
            $set[] = "updated_at = :ua";
            $params[':ua'] = date('Y-m-d H:i:s');
            $u = $db->prepare("UPDATE artists SET " . implode(', ', $set) . " WHERE id = :id");
            $u->execute($params);
            $u->closeCursor();
        } catch (PDOException $e) {
            echo "  ❌  row $rowNum — DB error: " . $e->getMessage() . "\n";
            $errors++;
            continue;
        }
    }
    $updated++;
}

// ----- Invalidate caches ----------------------------------------------------
if ($updated > 0 && !$dryRun) {
    if (function_exists('invalidate_artist_query_cache')) invalidate_artist_query_cache();
    if (function_exists('invalidate_data_version_cache')) invalidate_data_version_cache();
}

echo "\n=== Summary ===\n";
echo "Rows read:   " . count($rows) . "\n";
echo "Updated:     $updated" . ($dryRun ? " (dry run — not written)" : "") . "\n";
echo "No change:   $nochange\n";
echo "Skipped:     $skipped\n";
echo "Errors:      $errors\n";

exit($errors > 0 ? 1 : 0);
