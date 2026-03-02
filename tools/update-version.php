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
 *   INSTALLATION.md, TESTING.md, SECURITY.md, StaticSitePublisher.md
 *
 * Manual steps still required:
 *   CHANGELOG.md  — add new release entry
 *   CLAUDE.md     — add changelog entry in the "📝 Changelog" section
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

// ── 1. config/app.php ────────────────────────────────────────────────────────

$oldDefine = "define('APP_VERSION', '$currentVersion')";
$newDefine = "define('APP_VERSION', '$newVersion')";
replaceInFile($appConfigPath, $oldDefine, $newDefine, $updated, $skipped, $failed, 'config/app.php');

// ── 2. Markdown files (excluding CHANGELOG.md and CLAUDE.md) ─────────────────
//
//  Most version references in .md files use the bare version number as part of
//  "v2.4.3" or "**Current Version**: 2.4.3", so replacing the bare number is safe
//  in these files (they contain no historical version numbers unlike CHANGELOG.md).

$mdFiles = [
    'README.md',
    'SETUP.md',
    'API.md',
    'PROJECT-STRUCTURE.md',
    'INSTALLATION.md',
    'TESTING.md',
    'SECURITY.md',
    'StaticSitePublisher.md',
];

foreach ($mdFiles as $mdFile) {
    replaceInFile(
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
