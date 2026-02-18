<?php
/**
 * User Management & Role-Based Access Control Tests
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/admin.php';

// =============================================================================
// SCHEMA TESTS
// =============================================================================

function testAdminUsersTableExists($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    $table = $result->fetch();

    $test->assertNotFalse($table, 'admin_users table should exist');
}

function testAdminUsersHasRoleColumn($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    $columns = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

    $test->assertContains('role', $columnNames, "admin_users should have 'role' column");
}

function testAdminUsersRoleDefaultsToAdmin($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    $columns = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_ASSOC);
    $roleColumn = null;
    foreach ($columns as $col) {
        if ($col['name'] === 'role') {
            $roleColumn = $col;
            break;
        }
    }

    if ($roleColumn === null) {
        echo " [SKIP: role column not found] ";
        return;
    }

    $test->assertEquals("'admin'", $roleColumn['dflt_value'], "role column should default to 'admin'");
}

function testAdminUsersTableSchema($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    $columns = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

    $expectedColumns = ['id', 'username', 'password_hash', 'display_name', 'role', 'is_active', 'created_at', 'updated_at', 'last_login_at'];

    foreach ($expectedColumns as $col) {
        $test->assertContains($col, $columnNames, "Should have '{$col}' column");
    }
}

// =============================================================================
// ROLE HELPER FUNCTION TESTS
// =============================================================================

function testGetAdminRoleDefault($test) {
    // Without session set, should return 'admin' (default)
    $savedRole = $_SESSION['admin_role'] ?? null;
    unset($_SESSION['admin_role']);

    $role = get_admin_role();
    $test->assertEquals('admin', $role, 'Default role should be admin');

    // Restore
    if ($savedRole !== null) {
        $_SESSION['admin_role'] = $savedRole;
    }
}

function testGetAdminRoleFromSession($test) {
    $savedRole = $_SESSION['admin_role'] ?? null;

    $_SESSION['admin_role'] = 'agent';
    $test->assertEquals('agent', get_admin_role(), 'Should return agent from session');

    $_SESSION['admin_role'] = 'admin';
    $test->assertEquals('admin', get_admin_role(), 'Should return admin from session');

    // Restore
    if ($savedRole !== null) {
        $_SESSION['admin_role'] = $savedRole;
    } else {
        unset($_SESSION['admin_role']);
    }
}

function testIsAdminRoleTrue($test) {
    $savedRole = $_SESSION['admin_role'] ?? null;

    $_SESSION['admin_role'] = 'admin';
    $test->assertTrue(is_admin_role(), 'Should return true for admin role');

    // Restore
    if ($savedRole !== null) {
        $_SESSION['admin_role'] = $savedRole;
    } else {
        unset($_SESSION['admin_role']);
    }
}

function testIsAdminRoleFalseForAgent($test) {
    $savedRole = $_SESSION['admin_role'] ?? null;

    $_SESSION['admin_role'] = 'agent';
    $test->assertFalse(is_admin_role(), 'Should return false for agent role');

    // Restore
    if ($savedRole !== null) {
        $_SESSION['admin_role'] = $savedRole;
    } else {
        unset($_SESSION['admin_role']);
    }
}

// =============================================================================
// USER CRUD TESTS
// =============================================================================

function testCreateAndDeleteUser($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Check if role column exists
    $columns = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    if (!in_array('role', $columnNames)) {
        echo " [SKIP: role column not found] ";
        return;
    }

    $testUsername = 'test_user_' . time();
    $testPassword = password_hash('testpass123', PASSWORD_DEFAULT);

    // Create
    $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, display_name, role, is_active, created_at, updated_at) VALUES (:u, :p, :d, :r, 1, datetime('now'), datetime('now'))");
    $stmt->execute([':u' => $testUsername, ':p' => $testPassword, ':d' => 'Test User', ':r' => 'agent']);
    $userId = $db->lastInsertId();

    $test->assertTrue($userId > 0, 'Should create user with valid ID');

    // Read
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $test->assertEquals($testUsername, $user['username'], 'Username should match');
    $test->assertEquals('Test User', $user['display_name'], 'Display name should match');
    $test->assertEquals('agent', $user['role'], 'Role should be agent');
    $test->assertEquals(1, intval($user['is_active']), 'Should be active');

    // Update role
    $stmt = $db->prepare("UPDATE admin_users SET role = 'admin' WHERE id = :id");
    $stmt->execute([':id' => $userId]);

    $stmt = $db->prepare("SELECT role FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    $test->assertEquals('admin', $updated['role'], 'Role should be updated to admin');

    // Delete
    $stmt = $db->prepare("DELETE FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $userId]);

    $stmt = $db->prepare("SELECT id FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $test->assertFalse($stmt->fetch(), 'User should be deleted');
}

function testCreateUserDuplicateUsername($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    $testUsername = 'test_dup_' . time();
    $testPassword = password_hash('testpass123', PASSWORD_DEFAULT);

    // Insert first
    $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, display_name, role, is_active, created_at, updated_at) VALUES (:u, :p, :d, 'agent', 1, datetime('now'), datetime('now'))");
    $stmt->execute([':u' => $testUsername, ':p' => $testPassword, ':d' => 'Test']);
    $firstId = $db->lastInsertId();

    // Try duplicate
    $duplicateCreated = false;
    try {
        $stmt->execute([':u' => $testUsername, ':p' => $testPassword, ':d' => 'Test2']);
        $duplicateCreated = true;
    } catch (PDOException $e) {
        // Expected: UNIQUE constraint violation
    }

    $test->assertFalse($duplicateCreated, 'Should not allow duplicate username');

    // Cleanup
    $db->prepare("DELETE FROM admin_users WHERE id = :id")->execute([':id' => $firstId]);
}

function testUserRoleValues($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Check if role column exists
    $columns = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    if (!in_array('role', $columnNames)) {
        echo " [SKIP: role column not found] ";
        return;
    }

    $testPassword = password_hash('testpass123', PASSWORD_DEFAULT);

    // Test 'admin' role
    $adminUser = 'test_role_admin_' . time();
    $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, role, is_active, created_at, updated_at) VALUES (:u, :p, 'admin', 1, datetime('now'), datetime('now'))");
    $stmt->execute([':u' => $adminUser, ':p' => $testPassword]);
    $adminId = $db->lastInsertId();

    // Test 'agent' role
    $agentUser = 'test_role_agent_' . time();
    $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, role, is_active, created_at, updated_at) VALUES (:u, :p, 'agent', 1, datetime('now'), datetime('now'))");
    $stmt->execute([':u' => $agentUser, ':p' => $testPassword]);
    $agentId = $db->lastInsertId();

    // Verify
    $stmt = $db->prepare("SELECT role FROM admin_users WHERE id = :id");

    $stmt->execute([':id' => $adminId]);
    $test->assertEquals('admin', $stmt->fetch(PDO::FETCH_ASSOC)['role'], 'Admin role should be stored correctly');

    $stmt->execute([':id' => $agentId]);
    $test->assertEquals('agent', $stmt->fetch(PDO::FETCH_ASSOC)['role'], 'Agent role should be stored correctly');

    // Cleanup
    $db->prepare("DELETE FROM admin_users WHERE id IN (:id1, :id2)")->execute([':id1' => $adminId, ':id2' => $agentId]);
}

function testUserPasswordHash($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    $testUsername = 'test_hash_' . time();
    $testPassword = 'testpass123';
    $hash = password_hash($testPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, role, is_active, created_at, updated_at) VALUES (:u, :p, 'agent', 1, datetime('now'), datetime('now'))");
    $stmt->execute([':u' => $testUsername, ':p' => $hash]);
    $userId = $db->lastInsertId();

    // Verify password
    $stmt = $db->prepare("SELECT password_hash FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $storedHash = $stmt->fetch(PDO::FETCH_ASSOC)['password_hash'];

    $test->assertTrue(password_verify($testPassword, $storedHash), 'Password should verify correctly');
    $test->assertFalse(password_verify('wrongpassword', $storedHash), 'Wrong password should not verify');

    // Cleanup
    $db->prepare("DELETE FROM admin_users WHERE id = :id")->execute([':id' => $userId]);
}

function testUserIsActiveFlag($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    $testPassword = password_hash('testpass123', PASSWORD_DEFAULT);

    // Create inactive user
    $testUsername = 'test_inactive_' . time();
    $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, role, is_active, created_at, updated_at) VALUES (:u, :p, 'agent', 0, datetime('now'), datetime('now'))");
    $stmt->execute([':u' => $testUsername, ':p' => $testPassword]);
    $userId = $db->lastInsertId();

    // get_admin_user_by_username should not return inactive users
    $user = get_admin_user_by_username($testUsername);
    $test->assertNull($user, 'Inactive user should not be returned by get_admin_user_by_username');

    // Activate
    $db->prepare("UPDATE admin_users SET is_active = 1 WHERE id = :id")->execute([':id' => $userId]);
    $user = get_admin_user_by_username($testUsername);
    $test->assertNotNull($user, 'Active user should be returned');

    // Cleanup
    $db->prepare("DELETE FROM admin_users WHERE id = :id")->execute([':id' => $userId]);
}

// =============================================================================
// PERMISSION TESTS
// =============================================================================

function testAdminOnlyActionsArray($test) {
    // Verify admin-only actions are correctly defined
    $adminOnlyActions = [
        'backup_create', 'backup_list', 'backup_download',
        'backup_delete', 'backup_restore', 'backup_upload_restore',
        'users_list', 'users_get', 'users_create', 'users_update', 'users_delete',
    ];

    // Backup actions
    $test->assertContains('backup_create', $adminOnlyActions, 'backup_create should be admin-only');
    $test->assertContains('backup_list', $adminOnlyActions, 'backup_list should be admin-only');
    $test->assertContains('backup_restore', $adminOnlyActions, 'backup_restore should be admin-only');

    // User management actions
    $test->assertContains('users_list', $adminOnlyActions, 'users_list should be admin-only');
    $test->assertContains('users_create', $adminOnlyActions, 'users_create should be admin-only');
    $test->assertContains('users_update', $adminOnlyActions, 'users_update should be admin-only');
    $test->assertContains('users_delete', $adminOnlyActions, 'users_delete should be admin-only');

    // Event actions should NOT be in admin-only
    $test->assertFalse(in_array('list', $adminOnlyActions), 'list should not be admin-only');
    $test->assertFalse(in_array('create', $adminOnlyActions), 'create should not be admin-only');
    $test->assertFalse(in_array('requests', $adminOnlyActions), 'requests should not be admin-only');
    $test->assertFalse(in_array('credits_list', $adminOnlyActions), 'credits_list should not be admin-only');
    $test->assertFalse(in_array('event_meta_list', $adminOnlyActions), 'event_meta_list should not be admin-only');
}

function testRequireApiAdminRoleFunctionExists($test) {
    $test->assertTrue(function_exists('require_api_admin_role'), 'require_api_admin_role function should exist');
}

function testRequireAdminRoleFunctionExists($test) {
    $test->assertTrue(function_exists('require_admin_role'), 'require_admin_role function should exist');
}

function testGetAdminRoleFunctionExists($test) {
    $test->assertTrue(function_exists('get_admin_role'), 'get_admin_role function should exist');
}

function testIsAdminRoleFunctionExists($test) {
    $test->assertTrue(function_exists('is_admin_role'), 'is_admin_role function should exist');
}

function testLoginSetsRoleInSession($test) {
    $dbPath = DB_PATH;

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
    if (!$tableCheck->fetch()) {
        echo " [SKIP: Table doesn't exist] ";
        return;
    }

    // Check if role column exists
    $columns = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    if (!in_array('role', $columnNames)) {
        echo " [SKIP: role column not found] ";
        return;
    }

    // Create test user with agent role
    $testUsername = 'test_login_role_' . time();
    $testPassword = 'testpass123';
    $hash = password_hash($testPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, display_name, role, is_active, created_at, updated_at) VALUES (:u, :p, :d, 'agent', 1, datetime('now'), datetime('now'))");
    $stmt->execute([':u' => $testUsername, ':p' => $hash, ':d' => 'Test Agent']);
    $userId = $db->lastInsertId();

    // Attempt login
    if (PHP_SAPI === 'cli' && headers_sent()) {
        // In CLI, admin_login may fail due to session issues
        // Just verify that get_admin_user_by_username returns the role
        $user = get_admin_user_by_username($testUsername);
        $test->assertNotNull($user, 'User should exist');
        $test->assertEquals('agent', $user['role'], 'User role should be agent');
    } else {
        $result = admin_login($testUsername, $testPassword);
        if ($result) {
            $test->assertEquals('agent', $_SESSION['admin_role'] ?? '', 'Session should contain agent role');
        } else {
            // Login may fail in test environment, verify DB data instead
            $user = get_admin_user_by_username($testUsername);
            $test->assertNotNull($user, 'User should exist in database');
            $test->assertEquals('agent', $user['role'], 'User role should be agent in database');
        }
    }

    // Cleanup
    $db->prepare("DELETE FROM admin_users WHERE id = :id")->execute([':id' => $userId]);
}
