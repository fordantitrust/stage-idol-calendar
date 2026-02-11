<?php
/**
 * Simple Test Runner
 * Lightweight testing framework for Stage Idol Calendar
 */

class TestRunner {
    private $tests = [];
    private $passed = 0;
    private $failed = 0;
    private $currentTest = '';

    // Colors for terminal output
    const COLOR_GREEN = "\033[32m";
    const COLOR_RED = "\033[31m";
    const COLOR_YELLOW = "\033[33m";
    const COLOR_BLUE = "\033[34m";
    const COLOR_RESET = "\033[0m";

    public function addTest($name, $callback) {
        $this->tests[$name] = $callback;
    }

    public function run() {
        echo "\n";
        echo self::COLOR_BLUE . "╔════════════════════════════════════════╗\n";
        echo "║   Stage Idol Calendar - Test Suite    ║\n";
        echo "╚════════════════════════════════════════╝" . self::COLOR_RESET . "\n\n";

        $startTime = microtime(true);

        foreach ($this->tests as $name => $callback) {
            $this->currentTest = $name;
            echo "Testing: {$name}... ";

            try {
                $callback($this);
                $this->passed++;
                echo self::COLOR_GREEN . "✓ PASS" . self::COLOR_RESET . "\n";
            } catch (Exception $e) {
                $this->failed++;
                echo self::COLOR_RED . "✗ FAIL" . self::COLOR_RESET . "\n";
                echo self::COLOR_RED . "  Error: " . $e->getMessage() . self::COLOR_RESET . "\n";
            }
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 3);

        echo "\n";
        echo str_repeat("─", 50) . "\n";

        $total = $this->passed + $this->failed;
        $passRate = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;

        echo "Total Tests: {$total}\n";
        echo self::COLOR_GREEN . "Passed: {$this->passed}" . self::COLOR_RESET . "\n";

        if ($this->failed > 0) {
            echo self::COLOR_RED . "Failed: {$this->failed}" . self::COLOR_RESET . "\n";
        }

        echo "Pass Rate: {$passRate}%\n";
        echo "Duration: {$duration}s\n";
        echo str_repeat("─", 50) . "\n\n";

        return $this->failed === 0 ? 0 : 1;
    }

    // Assertion methods

    public function assertEquals($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            $msg = $message ?: "Expected " . var_export($expected, true) . " but got " . var_export($actual, true);
            throw new Exception($msg);
        }
    }

    public function assertNotEquals($expected, $actual, $message = '') {
        if ($expected === $actual) {
            $msg = $message ?: "Expected " . var_export($expected, true) . " to not equal " . var_export($actual, true);
            throw new Exception($msg);
        }
    }

    public function assertTrue($condition, $message = 'Expected true but got false') {
        if ($condition !== true) {
            throw new Exception($message);
        }
    }

    public function assertFalse($condition, $message = 'Expected false but got true') {
        if ($condition !== false) {
            throw new Exception($message);
        }
    }

    public function assertNotFalse($value, $message = 'Expected not false') {
        if ($value === false) {
            throw new Exception($message);
        }
    }

    public function assertNotTrue($value, $message = 'Expected not true') {
        if ($value === true) {
            throw new Exception($message);
        }
    }

    public function assertNull($value, $message = 'Expected null') {
        if ($value !== null) {
            throw new Exception($message);
        }
    }

    public function assertNotNull($value, $message = 'Expected not null') {
        if ($value === null) {
            throw new Exception($message);
        }
    }

    public function assertEmpty($value, $message = 'Expected empty') {
        if (!empty($value)) {
            throw new Exception($message);
        }
    }

    public function assertNotEmpty($value, $message = 'Expected not empty') {
        if (empty($value)) {
            throw new Exception($message);
        }
    }

    public function assertCount($expectedCount, $array, $message = '') {
        $actualCount = count($array);
        if ($actualCount !== $expectedCount) {
            $msg = $message ?: "Expected count {$expectedCount} but got {$actualCount}";
            throw new Exception($msg);
        }
    }

    public function assertArrayHasKey($key, $array, $message = '') {
        if (!array_key_exists($key, $array)) {
            $msg = $message ?: "Array does not have key '{$key}'";
            throw new Exception($msg);
        }
    }

    public function assertContains($needle, $haystack, $message = '') {
        if (is_string($haystack)) {
            if (strpos($haystack, $needle) === false) {
                $msg = $message ?: "String does not contain '{$needle}'";
                throw new Exception($msg);
            }
        } elseif (is_array($haystack)) {
            if (!in_array($needle, $haystack, true)) {
                $msg = $message ?: "Array does not contain " . var_export($needle, true);
                throw new Exception($msg);
            }
        }
    }

    public function assertGreaterThan($expected, $actual, $message = '') {
        if ($actual <= $expected) {
            $msg = $message ?: "Expected {$actual} to be greater than {$expected}";
            throw new Exception($msg);
        }
    }

    public function assertLessThan($expected, $actual, $message = '') {
        if ($actual >= $expected) {
            $msg = $message ?: "Expected {$actual} to be less than {$expected}";
            throw new Exception($msg);
        }
    }

    public function assertGreaterThanOrEqual($expected, $actual, $message = '') {
        if ($actual < $expected) {
            $msg = $message ?: "Expected {$actual} to be greater than or equal to {$expected}";
            throw new Exception($msg);
        }
    }

    public function assertLessThanOrEqual($expected, $actual, $message = '') {
        if ($actual > $expected) {
            $msg = $message ?: "Expected {$actual} to be less than or equal to {$expected}";
            throw new Exception($msg);
        }
    }

    public function assertInstanceOf($expectedClass, $object, $message = '') {
        if (!($object instanceof $expectedClass)) {
            $actualClass = get_class($object);
            $msg = $message ?: "Expected instance of {$expectedClass} but got {$actualClass}";
            throw new Exception($msg);
        }
    }

    public function assertFileExists($filepath, $message = '') {
        if (!file_exists($filepath)) {
            $msg = $message ?: "File does not exist: {$filepath}";
            throw new Exception($msg);
        }
    }

    public function assertFileNotExists($filepath, $message = '') {
        if (file_exists($filepath)) {
            $msg = $message ?: "File exists but should not: {$filepath}";
            throw new Exception($msg);
        }
    }
}
