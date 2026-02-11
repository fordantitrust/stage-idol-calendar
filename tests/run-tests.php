<?php
/**
 * Test Runner Script
 * Run all automated tests
 *
 * Usage:
 *   php tests/run-tests.php
 *   php tests/run-tests.php SecurityTest
 *   php tests/run-tests.php SecurityTest::testSanitizeString
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to project root
chdir(dirname(__DIR__));

require_once __DIR__ . '/TestRunner.php';

// Color constants for easier access
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_CYAN', "\033[36m");
define('COLOR_RESET', "\033[0m");

// Parse command line arguments
$specificTest = isset($argv[1]) ? $argv[1] : null;
$specificMethod = null;

if ($specificTest && strpos($specificTest, '::') !== false) {
    list($specificTest, $specificMethod) = explode('::', $specificTest);
}

// Test files to run
$testFiles = [
    'SecurityTest' => __DIR__ . '/SecurityTest.php',
    'CacheTest' => __DIR__ . '/CacheTest.php',
    'AdminAuthTest' => __DIR__ . '/AdminAuthTest.php',
    'CreditsApiTest' => __DIR__ . '/CreditsApiTest.php',
    'IntegrationTest' => __DIR__ . '/IntegrationTest.php',
];

// Filter test files if specific test requested
if ($specificTest) {
    if (!isset($testFiles[$specificTest])) {
        echo COLOR_RED . "Error: Test file '{$specificTest}' not found\n" . COLOR_RESET;
        echo "\nAvailable tests:\n";
        foreach (array_keys($testFiles) as $name) {
            echo "  - {$name}\n";
        }
        exit(1);
    }
    $testFiles = [$specificTest => $testFiles[$specificTest]];
}

// Display header
echo "\n";
echo COLOR_CYAN . "╔════════════════════════════════════════════════════╗\n";
echo "║     Stage Idol Calendar - Automated Test Suite    ║\n";
echo "╚════════════════════════════════════════════════════╝" . COLOR_RESET . "\n";

if ($specificTest) {
    echo COLOR_YELLOW . "\nRunning specific test: {$specificTest}" . COLOR_RESET;
    if ($specificMethod) {
        echo COLOR_YELLOW . "::{$specificMethod}" . COLOR_RESET;
    }
    echo "\n";
}

echo "\n";

// Run all test suites
$totalPassed = 0;
$totalFailed = 0;
$suiteResults = [];

foreach ($testFiles as $testName => $testFile) {
    echo COLOR_BLUE . "\n━━━ {$testName} ━━━" . COLOR_RESET . "\n";

    // Load test file
    require_once $testFile;

    // Create test runner
    $runner = new TestRunner();

    // Find all test functions in the file
    $functions = get_defined_functions()['user'];

    foreach ($functions as $func) {
        // Only include functions that start with 'test' and are in this file
        if (strpos($func, 'test') === 0) {
            // If specific method requested, only run that one
            if ($specificMethod && $func !== $specificMethod) {
                continue;
            }

            $runner->addTest($func, $func);
        }
    }

    // Run tests
    $exitCode = $runner->run();

    // Track results
    $reflection = new ReflectionObject($runner);
    $passedProp = $reflection->getProperty('passed');
    $passedProp->setAccessible(true);
    $failedProp = $reflection->getProperty('failed');
    $failedProp->setAccessible(true);

    $passed = $passedProp->getValue($runner);
    $failed = $failedProp->getValue($runner);

    $totalPassed += $passed;
    $totalFailed += $failed;

    $suiteResults[$testName] = [
        'passed' => $passed,
        'failed' => $failed,
        'exit_code' => $exitCode
    ];
}

// Display summary
echo "\n";
echo COLOR_CYAN . "╔════════════════════════════════════════════════════╗\n";
echo "║                  FINAL SUMMARY                     ║\n";
echo "╚════════════════════════════════════════════════════╝" . COLOR_RESET . "\n\n";

foreach ($suiteResults as $name => $result) {
    $status = $result['exit_code'] === 0 ? COLOR_GREEN . '✓ PASS' : COLOR_RED . '✗ FAIL';
    echo sprintf(
        "%-25s %s" . COLOR_RESET . " (%d passed, %d failed)\n",
        $name,
        $status,
        $result['passed'],
        $result['failed']
    );
}

echo "\n" . str_repeat("─", 54) . "\n";

$total = $totalPassed + $totalFailed;
$passRate = $total > 0 ? round(($totalPassed / $total) * 100, 1) : 0;

echo sprintf("Total: %d tests\n", $total);
echo COLOR_GREEN . sprintf("Passed: %d\n", $totalPassed) . COLOR_RESET;

if ($totalFailed > 0) {
    echo COLOR_RED . sprintf("Failed: %d\n", $totalFailed) . COLOR_RESET;
}

echo sprintf("Pass Rate: %.1f%%\n", $passRate);

echo str_repeat("─", 54) . "\n";

// Exit with appropriate code
if ($totalFailed > 0) {
    echo "\n" . COLOR_RED . "❌ TESTS FAILED" . COLOR_RESET . "\n\n";
    exit(1);
} else {
    echo "\n" . COLOR_GREEN . "✅ ALL TESTS PASSED" . COLOR_RESET . "\n\n";
    exit(0);
}
