<?php
/**
 * Version Update Script
 *
 * Automatically updates APP_VERSION in config/app.php and all relevant .md files.
 *
 * Usage:
 *   php tools/update-version.php 2.5.0
 *
 * Auto-updated files:
 *   config/app.php, README.md, SETUP.md, API.md, PROJECT-STRUCTURE.md,
 *   INSTALLATION.md, TESTING.md, SECURITY.md, StaticSitePublisher.md,
 *   StaticSitePublisher_EN.md, ICS_FORMAT.md
 *
 * Manual steps still required:
 *   CHANGELOG.md  — add new release entry
 *   CLAUDE.md     — add changelog entry in the "📝 Changelog" section
 *
 * Smart replacement for .md files:
 *   Lines that are historical version labels are preserved unchanged, e.g.:
 *     (v3.0.0+)         — "introduced in" labels in parentheses
 *     **v3.0.0+**:      — bold "introduced in" labels
 *     | v3.0.0 |        — table "Since" column values
 *     | **v3.0.0** ...  — Feature Timeline rows
 *     ### v3.0.0 —      — historical version section headings
 *     Upgrading from ... v3.0.0  — upgrade guide references
 */

// ── Validate argument ────────────────────────────────────────────────────────

$newVersion = $argv[1] ?? null;

if (!$newVersion || !preg_match('/^\d+\.\d+\.\d+$/', $newVersion)) {
    echo "Usage: php tools/update-version.php X.Y.Z\n";
    echo "Example: php tools/update-version.php 2.5.0\n";
    exit(1);
}

$rootDir = realpath(__DIR__ . '/..');

// ── Read current version from config/app.php ────────────────────────────────

$appConfigPath = "$rootDir/config/app.php";

if (!file_exists($appConfigPath)) {
    echo "Error: config/app.php not found.\n";
    exit(1);
}

$appConfigContent = file_get_contents($appConfigPath);

if (!preg_match("/define\('APP_VERSION',\s*'(\d+\.\d+\.\d+)'\)/", $appConfigContent, $m)) {
    echo "Error: Could not find APP_VERSION in config/app.php.\n";
    exit(1);
}

$currentVersion = $m[1];

if ($currentVersion === $newVersion) {
    echo "Already at version $newVersion — no changes needed.\n";
    exit(0);
}

echo "=== Version Update: v$currentVersion → v$newVersion ===\n\n";

// ── Helpers ──────────────────────────────────────────────────────────────────

$updated = [];
$skipped = [];
$failed  = [];

/**
 * Simple replacement — used for config/app.php with a fully-qualified search
 * string (the entire define() call), so no skip logic is needed.
 */
function replaceInFile(string $path, string $old, string $new, array &$updated, array &$skipped, array &$failed, string $label): void
{
    if (!file_exists($path)) {
        $skipped[] = "$label (file not found)";
        return;
    }

    $original = file_get_contents($path);
    $count    = substr_count($original, $old);

    if ($count === 0) {
        $skipped[] = "$label (version string not found)";
        return;
    }

    $content = str_replace($old, $new, $original);

    if (file_put_contents($path, $content) !== false) {
        $updated[] = sprintf('%-40s %d replacement%s', $label, $count, $count !== 1 ? 's' : '');
    } else {
        $failed[] = "$label (write error)";
    }
}

/**
 * Smart line-by-line replacement for .md files.
 *
 * Lines that contain the old version string but match a "historical label"
 * pattern are left unchanged. Everything else gets the version replaced.
 *
 * Patterns that are SKIPPED (historical / feature-label uses):
 *   (v3.0.0+)           — introduced-in label in parentheses with +
 *   **v3.0.0+**         — bold introduced-in label with +
 *   | v3.0.0 |          — table "Since" column
 *   | **v3.0.0**        — Feature Timeline row (starts with pipe + bold ver)
 *   ### v3.0.0 …        — historical version section heading (# to ######)
 *   Upgrading from … v3.0.0  — upgrade guide section references
 *   new v3.0.0 features — upgrade guide descriptive text
 *   all v3.0.0 features — upgrade guide descriptive text
 *   = Something v3.0.0  — inline code-block comments (e.g. "artist.php = … v3.0.0")
 */
function replaceVersionInFileSmart(string $path, string $old, string $new, array &$updated, array &$skipped, array &$failed, string $label): void
{
    if (!file_exists($path)) {
        $skipped[] = "$label (file not found)";
        return;
    }

    $original = file_get_contents($path);

    if (strpos($original, $old) === false) {
        $skipped[] = "$label (version string not found)";
        return;
    }

    $v = preg_quote($old, '/');

    $skipPatterns = [
        '/\(v' . $v . '\+\)/',                  // (v3.0.0+)  — introduced-in label
        '/\*\*v' . $v . '\+\*\*/',               // **v3.0.0+** — bold introduced-in
        '/\|\s*v' . $v . '\s*\|/',               // | v3.0.0 |  — table Since column
        '/^\s*\|\s*\*\*v' . $v . '\*\*/',        // | **v3.0.0** — Feature Timeline row
        '/^#{1,6}\s+v' . $v . '[\s\-\x{2014}(]/u', // ### v3.0.0 — historical heading
        '/Upgrading from.*v' . $v . '/',         // Upgrading from … v3.0.0
        '/\bnew v' . $v . ' features/',          // new v3.0.0 features
        '/\ball v' . $v . ' features/',          // all v3.0.0 features
        '/=\s+\S[^\n]*v' . $v . '/',            // = Something v3.0.0 (inline comment)
    ];

    $lines        = explode("\n", $original);
    $changedCount = 0;
    $newLines     = [];

    foreach ($lines as $line) {
        // Fast path: line doesn't contain the old version at all
        if (strpos($line, $old) === false) {
            $newLines[] = $line;
            continue;
        }

        // Check skip patterns
        $skip = false;
        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                $skip = true;
                break;
            }
        }

        if ($skip) {
            $newLines[] = $line;
        } else {
            $newLines[] = str_replace($old, $new, $line);
            $changedCount++;
        }
    }

    if ($changedCount === 0) {
        $skipped[] = "$label (all occurrences matched skip patterns — nothing replaced)";
        return;
    }

    $content = implode("\n", $newLines);

    if (file_put_contents($path, $content) !== false) {
        $updated[] = sprintf('%-40s %d replacement%s', $label, $changedCount, $changedCount !== 1 ? 's' : '');
    } else {
        $failed[] = "$label (write error)";
    }
}

// ── 1. config/app.php (exact define() pattern — no skip needed) ──────────────

$oldDefine = "define('APP_VERSION', '$currentVersion')";
$newDefine = "define('APP_VERSION', '$newVersion')";
replaceInFile($appConfigPath, $oldDefine, $newDefine, $updated, $skipped, $failed, 'config/app.php');

// ── 2. Markdown files (smart line-by-line replacement) ───────────────────────

$mdFiles = [
    'README.md',
    'SETUP.md',
    'API.md',
    'PROJECT-STRUCTURE.md',
    'INSTALLATION.md',
    'TESTING.md',
    'SECURITY.md',
    'StaticSitePublisher.md',
    'StaticSitePublisher_EN.md',
    'ICS_FORMAT.md',
];

foreach ($mdFiles as $mdFile) {
    replaceVersionInFileSmart(
        "$rootDir/$mdFile",
        $currentVersion,
        $newVersion,
        $updated,
        $skipped,
        $failed,
        $mdFile
    );
}

// ── Report ───────────────────────────────────────────────────────────────────

if ($updated) {
    echo "✅ Updated:\n";
    foreach ($updated as $line) {
        echo "   $line\n";
    }
}

if ($skipped) {
    echo "\n⏭️  Skipped:\n";
    foreach ($skipped as $line) {
        echo "   $line\n";
    }
}

if ($failed) {
    echo "\n❌ Failed:\n";
    foreach ($failed as $line) {
        echo "   $line\n";
    }
}

echo "\n📝 Manual steps still required:\n";
echo "   1. CHANGELOG.md  — add new release entry for v$newVersion\n";
echo "   2. CLAUDE.md     — add changelog entry in the '📝 Changelog' section\n";

echo "\n" . ($failed ? "⚠️  Done with errors." : "✅ Version updated to v$newVersion successfully!") . "\n";
