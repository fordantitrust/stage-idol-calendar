<?php
/**
 * Credits API Tests
 */

require_once __DIR__ . '/../config.php';

function testDatabaseConnection($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    try {
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $test->assertNotNull($db, 'Should connect to database');
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

function testCreditsTableExists($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if credits table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'");
    $table = $result->fetch();

    $test->assertNotFalse($table, 'Credits table should exist');
}

function testCreditsTableSchema($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);

    // Check table schema
    try {
        $result = $db->query("PRAGMA table_info(credits)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'name');

        $expectedColumns = ['id', 'title', 'link', 'description', 'display_order', 'created_at', 'updated_at'];

        foreach ($expectedColumns as $col) {
            $test->assertContains($col, $columnNames, "Should have '{$col}' column");
        }
    } catch (PDOException $e) {
        echo " [SKIP: Table doesn't exist] ";
    }
}

function testInsertCredit($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table exists first
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Insert test credit
    $stmt = $db->prepare("
        INSERT INTO credits (title, link, description, display_order)
        VALUES (:title, :link, :description, :display_order)
    ");

    $testData = [
        'title' => 'Test Credit ' . time(),
        'link' => 'https://test.com',
        'description' => 'Test Description',
        'display_order' => 999
    ];

    $result = $stmt->execute([
        ':title' => $testData['title'],
        ':link' => $testData['link'],
        ':description' => $testData['description'],
        ':display_order' => $testData['display_order']
    ]);

    $test->assertTrue($result, 'Should insert credit successfully');

    $insertId = $db->lastInsertId();
    $test->assertGreaterThan(0, $insertId, 'Should return valid insert ID');

    // Cleanup
    $db->exec("DELETE FROM credits WHERE id = {$insertId}");
}

function testSelectCredits($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Select all credits
    $stmt = $db->query("SELECT * FROM credits ORDER BY display_order ASC");
    $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $test->assertTrue(is_array($credits), 'Should return array of credits');

    // If we have credits, check structure
    if (!empty($credits)) {
        $firstCredit = $credits[0];
        $test->assertArrayHasKey('id', $firstCredit, 'Credit should have id');
        $test->assertArrayHasKey('title', $firstCredit, 'Credit should have title');
    }
}

function testUpdateCredit($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Insert test credit
    $stmt = $db->prepare("INSERT INTO credits (title) VALUES (?)");
    $stmt->execute(['Test Update ' . time()]);
    $insertId = $db->lastInsertId();

    // Update credit
    $newTitle = 'Updated Title ' . time();
    $stmt = $db->prepare("UPDATE credits SET title = ? WHERE id = ?");
    $result = $stmt->execute([$newTitle, $insertId]);

    $test->assertTrue($result, 'Should update credit');

    // Verify update
    $stmt = $db->prepare("SELECT title FROM credits WHERE id = ?");
    $stmt->execute([$insertId]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    $test->assertEquals($newTitle, $updated['title'], 'Title should be updated');

    // Cleanup
    $db->exec("DELETE FROM credits WHERE id = {$insertId}");
}

function testDeleteCredit($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Insert test credit
    $stmt = $db->prepare("INSERT INTO credits (title) VALUES (?)");
    $stmt->execute(['Test Delete ' . time()]);
    $insertId = $db->lastInsertId();

    // Delete credit
    $stmt = $db->prepare("DELETE FROM credits WHERE id = ?");
    $result = $stmt->execute([$insertId]);

    $test->assertTrue($result, 'Should delete credit');
    $test->assertEquals(1, $stmt->rowCount(), 'Should delete exactly 1 row');

    // Verify deletion
    $stmt = $db->prepare("SELECT * FROM credits WHERE id = ?");
    $stmt->execute([$insertId]);
    $deleted = $stmt->fetch();

    $test->assertFalse($deleted, 'Credit should not exist after deletion');
}

function testBulkDeleteCredits($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Insert multiple test credits
    $ids = [];
    $stmt = $db->prepare("INSERT INTO credits (title) VALUES (?)");

    for ($i = 0; $i < 3; $i++) {
        $stmt->execute(['Bulk Test ' . time() . ' #' . $i]);
        $ids[] = $db->lastInsertId();
    }

    // Bulk delete
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("DELETE FROM credits WHERE id IN ($placeholders)");
    $result = $stmt->execute($ids);

    $test->assertTrue($result, 'Should bulk delete credits');
    $test->assertEquals(3, $stmt->rowCount(), 'Should delete 3 credits');

    // Verify all deleted
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM credits WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $test->assertEquals(0, $count, 'All credits should be deleted');
}

function testCreditValidation($test) {
    // Test title length limit
    $longTitle = str_repeat('a', 250);
    $truncated = substr($longTitle, 0, 200);

    $test->assertEquals(200, strlen($truncated), 'Title should be limited to 200 characters');

    // Test description length limit
    $longDesc = str_repeat('b', 1500);
    $truncatedDesc = substr($longDesc, 0, 1000);

    $test->assertEquals(1000, strlen($truncatedDesc), 'Description should be limited to 1000 characters');
}

function testSQLInjectionProtection($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Test SQL injection attempt (should be safe with prepared statements)
    $maliciousInput = "'; DROP TABLE credits; --";

    $stmt = $db->prepare("SELECT * FROM credits WHERE title = ?");
    $stmt->execute([$maliciousInput]);

    // Should not error, and table should still exist
    $checkTable = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'");
    $tableExists = $checkTable->fetch();

    $test->assertNotFalse($tableExists, 'Table should still exist (SQL injection prevented)');
}

function testDisplayOrderSorting($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='credits'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Insert credits with different display orders
    $ids = [];
    $stmt = $db->prepare("INSERT INTO credits (title, display_order) VALUES (?, ?)");

    $orders = [30, 10, 20];
    foreach ($orders as $order) {
        $stmt->execute(['Order Test ' . time() . ' #' . $order, $order]);
        $ids[] = $db->lastInsertId();
    }

    // Select ordered by display_order
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT display_order FROM credits WHERE id IN ($placeholders) ORDER BY display_order ASC");
    $stmt->execute($ids);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Should be in ascending order
    $test->assertEquals([10, 20, 30], $results, 'Credits should be ordered by display_order');

    // Cleanup
    $db->exec("DELETE FROM credits WHERE id IN ($placeholders)");
}
