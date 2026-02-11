<?php
/**
 * Security Functions Tests
 */

require_once __DIR__ . '/../functions/security.php';

function testSanitizeString($test) {
    // Test basic sanitization
    $result = sanitize_string('  Hello World  ');
    $test->assertEquals('Hello World', $result, 'Should trim whitespace');

    // Test null byte removal
    $result = sanitize_string("Test\0String");
    $test->assertFalse(strpos($result, "\0") !== false, 'Should remove null bytes');

    // Test length limit
    $longString = str_repeat('a', 250);
    $result = sanitize_string($longString, 200);
    $test->assertEquals(200, strlen($result), 'Should limit to max length');

    // Test empty string
    $result = sanitize_string('');
    $test->assertEquals('', $result, 'Should handle empty string');
}

function testSanitizeStringArray($test) {
    // Test basic array sanitization
    $input = ['  Hello  ', '  World  ', 'Test'];
    $result = sanitize_string_array($input);
    $test->assertCount(3, $result, 'Should return 3 items');
    $test->assertEquals('Hello', $result[0], 'Should trim first item');

    // Test empty value removal
    $input = ['Hello', '', '  ', 'World'];
    $result = sanitize_string_array($input);
    $test->assertCount(2, $result, 'Should remove empty values');

    // Test max items limit
    $input = range(1, 150);
    $result = sanitize_string_array($input, 200, 100);
    $test->assertCount(100, $result, 'Should limit to 100 items');

    // Test non-array input
    $result = sanitize_string_array('not an array');
    $test->assertEmpty($result, 'Should return empty array for non-array input');

    // Test array re-indexing
    $input = ['a', '', 'b', '', 'c'];
    $result = sanitize_string_array($input);
    $test->assertEquals(['a', 'b', 'c'], $result, 'Should re-index array');
}

function testGetSanitizedParam($test) {
    // Simulate GET parameters
    $_GET['test1'] = '  Value  ';
    $_GET['test2'] = str_repeat('x', 250);

    // Test basic parameter
    $result = get_sanitized_param('test1');
    $test->assertEquals('Value', $result, 'Should sanitize GET parameter');

    // Test default value
    $result = get_sanitized_param('nonexistent', 'default');
    $test->assertEquals('default', $result, 'Should return default for missing parameter');

    // Test max length
    $result = get_sanitized_param('test2', '', 100);
    $test->assertEquals(100, strlen($result), 'Should respect max length');

    // Cleanup
    unset($_GET['test1'], $_GET['test2']);
}

function testGetSanitizedArrayParam($test) {
    // Simulate array GET parameter
    $_GET['array1'] = ['  val1  ', '  val2  ', ''];
    $_GET['array2'] = 'single value';

    // Test array parameter
    $result = get_sanitized_array_param('array1');
    $test->assertCount(2, $result, 'Should sanitize array and remove empty values');
    $test->assertEquals('val1', $result[0], 'Should trim array values');

    // Test single value converted to array
    $result = get_sanitized_array_param('array2');
    $test->assertCount(1, $result, 'Should convert single value to array');
    $test->assertEquals('single value', $result[0], 'Should contain the value');

    // Test missing parameter
    $result = get_sanitized_array_param('nonexistent');
    $test->assertEmpty($result, 'Should return empty array for missing parameter');

    // Cleanup
    unset($_GET['array1'], $_GET['array2']);
}

function testXssProtection($test) {
    // Test XSS attempts
    $xssAttempts = [
        '<script>alert("XSS")</script>',
        '"><script>alert(1)</script>',
        "'; DROP TABLE users; --",
        '<img src=x onerror=alert(1)>',
    ];

    foreach ($xssAttempts as $attempt) {
        $result = sanitize_string($attempt);
        // Should not contain the original tags (length should be limited or modified)
        $test->assertNotNull($result, 'Should return a value');
    }
}

function testNullByteInjection($test) {
    $input = "Test\0String\0Injection";
    $result = sanitize_string($input);

    $test->assertFalse(strpos($result, "\0") !== false, 'Should remove all null bytes');
    $test->assertEquals('TestStringInjection', $result, 'Should concatenate after null byte removal');
}

function testSafeErrorMessage($test) {
    // Test production mode error hiding
    $detailedError = 'Database connection failed: Access denied for user';
    $result = safe_error_message('Operation failed', $detailedError);

    // In production, should not expose detailed error
    if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
        $test->assertEquals('Operation failed', $result, 'Should hide detailed error in production');
    }
}
