# 🧪 Automated Tests

Automated unit test suite for Stage Idol Calendar.

## 📁 Files

```
tests/
├── TestRunner.php           # Lightweight test framework
├── SecurityTest.php         # Security functions (sanitization, XSS, etc.)
├── CacheTest.php            # Cache system (data version, credits cache)
├── AdminAuthTest.php        # Authentication & session management
├── CreditsApiTest.php       # Credits database operations
├── IntegrationTest.php      # Integration tests (config, workflow, API, multi-event)
├── UserManagementTest.php   # User management & role-based access tests
├── ThemeTest.php            # Theme system (get_site_theme, per-event theme, CSS files)
├── SiteSettingsTest.php     # Site title system (get_site_title, cache, admin API)
├── EventEmailTest.php       # Event email field (schema, CRUD, validation, ICS ORGANIZER)
├── run-tests.php            # Main test runner script
└── README.md                # This file
```

## 🚀 Quick Start

### Run All Tests

```bash
php tests/run-tests.php
```

### Run Specific Test Suite

```bash
php tests/run-tests.php SecurityTest
php tests/run-tests.php CacheTest
php tests/run-tests.php AdminAuthTest
php tests/run-tests.php CreditsApiTest
php tests/run-tests.php ThemeTest
php tests/run-tests.php SiteSettingsTest
php tests/run-tests.php EventEmailTest
```

### Run Specific Test Method

```bash
php tests/run-tests.php SecurityTest::testSanitizeString
php tests/run-tests.php CacheTest::testDataVersionCacheCreation
```

## 📊 Test Coverage

### SecurityTest (7 tests)
- ✅ String sanitization (trim, null bytes, length limit)
- ✅ Array sanitization (items limit, empty removal)
- ✅ GET parameter sanitization
- ✅ Array GET parameter sanitization
- ✅ XSS protection
- ✅ Null byte injection prevention
- ✅ Safe error messages

### CacheTest (17 tests)
- ✅ Cache directory existence & permissions
- ✅ Data version cache creation
- ✅ Data version cache hit
- ✅ Data version cache expiration
- ✅ Credits cache creation
- ✅ Credits cache invalidation
- ✅ Credits cache hit
- ✅ Credits cache expiration
- ✅ Cache fallback on error

### AdminAuthTest (38 tests)
- ✅ Safe session start
- ✅ Session idempotency
- ✅ Session cookie parameters
- ✅ Login success/failure
- ✅ Timing attack resistance
- ✅ Session data handling
- ✅ Session timeout
- ✅ Session activity update
- ✅ Logout functionality
- ✅ Password hash verification

### CreditsApiTest (49 tests)
- ✅ Database connection
- ✅ Credits table schema
- ✅ Insert credit
- ✅ Select credits
- ✅ Update credit
- ✅ Delete credit
- ✅ Bulk delete credits
- ✅ SQL injection protection
- ✅ Display order sorting
- ✅ Validation (title, description length)

### IntegrationTest (97 tests)
- ✅ Configuration validation
- ✅ IcsParser functionality
- ✅ Database operations (CRUD, bulk)
- ✅ API endpoints (public + admin)
- ✅ Request system workflow
- ✅ Multi-event support (events_meta CRUD, filtering, URL routing)
- ✅ Convention management (create, update, delete, slug uniqueness)
- ✅ Per-convention venue mode and cache scoping

### UserManagementTest (116 tests)
- ✅ Role column schema (exists, default value, valid values)
- ✅ Role helper functions (get_admin_role, is_admin_role)
- ✅ User CRUD operations (create, update, delete, validation)
- ✅ Permission checks (admin-only actions, agent restrictions)
- ✅ Safety guards (cannot delete self, last admin protection)

### ThemeTest (24 unique tests / 140 cumulative)
- ✅ get_site_theme() function exists and returns correct values
- ✅ Default fallback to 'dark' when no cache file exists (no event meta)
- ✅ Reads all 7 valid themes from cache file
- ✅ Invalid/malformed/missing-key cache falls back to 'dark'
- ✅ Theme CSS files exist on disk (ocean, forest, midnight, sunset, dark, gray)
- ✅ Admin API has theme_get / theme_save cases + functions defined
- ✅ saveThemeSetting() does not call undefined validate_csrf_token()
- ✅ Public pages have server-side theme link, no theme-switcher UI
- ✅ Per-event theme overrides global theme
- ✅ Null/empty/invalid event theme falls back to global theme
- ✅ Null event + no global → 'dark' fallback
- ✅ All 7 valid event themes work correctly
- ✅ Admin API events_create/update handle theme field
- ✅ migrate-add-theme-column.php script exists

### SiteSettingsTest (14 unique tests / 154 cumulative)
- ✅ get_site_title() function exists in functions/helpers.php
- ✅ Default: no cache file → returns APP_NAME constant
- ✅ Reads custom title from cache/site-settings.json
- ✅ Empty site_title in cache → falls back to APP_NAME
- ✅ Whitespace-only title in cache → falls back to APP_NAME
- ✅ get_site_title() trims surrounding whitespace from cached title
- ✅ Malformed JSON in cache → falls back to APP_NAME
- ✅ admin/api.php has case 'title_get' and case 'title_save'
- ✅ getTitleSetting() and saveTitleSetting() defined in admin/api.php
- ✅ Public pages (index, how-to-use, contact, credits) call get_site_title()
- ✅ Public pages inject window.SITE_TITLE before translations.js
- ✅ js/translations.js has window.SITE_TITLE patching IIFE
- ✅ saveTitleSetting() calls require_api_admin_role()
- ✅ APP_NAME constant defined and non-empty

### EventEmailTest (19 unique tests / 173 cumulative)
- ✅ events table has email column (TEXT DEFAULT NULL)
- ✅ email column is nullable (notnull=0)
- ✅ Insert event with valid email → stored correctly
- ✅ Insert event with NULL email → stored as NULL
- ✅ Email validation accepts valid formats (standard, subdomains, tags)
- ✅ Email validation rejects invalid/empty inputs → returns NULL
- ✅ Validation trims surrounding whitespace before checking
- ✅ Empty string / null input → returns NULL
- ✅ Update event email → new value stored
- ✅ Update event email to NULL → stored as NULL
- ✅ SELECT * returns email field with correct value
- ✅ ICS ORGANIZER uses event name + email when email is valid
- ✅ ICS ORGANIZER falls back to noreply@stageidol.local when email absent
- ✅ ICS ORGANIZER falls back when email is NULL
- ✅ ICS ORGANIZER falls back when email is invalid
- ✅ ORGANIZER line not emitted when no eventMeta
- ✅ ORGANIZER line not emitted when event name is empty
- ✅ migrate-add-event-email-column.php exists in tools/
- ✅ Migration is idempotent (skips ALTER TABLE when column already present)

**Total: 637 automated tests** (all pass on PHP 8.1, 8.2, 8.3, 8.4, 8.5)

> **Note**: Test counts shown per-suite are cumulative (the runner reloads all previously-defined `test*` functions). The "unique" count is the number of test functions in each file only.

## 🎯 Expected Output

```
╔════════════════════════════════════════════════════╗
║     Stage Idol Calendar - Automated Test Suite    ║
╚════════════════════════════════════════════════════╝

━━━ SecurityTest ━━━
Testing: testSanitizeString... ✓ PASS
Testing: testSanitizeStringArray... ✓ PASS
Testing: testGetSanitizedParam... ✓ PASS
...

━━━ CacheTest ━━━
Testing: testCacheDirectoryExists... ✓ PASS
Testing: testDataVersionCacheCreation... ✓ PASS
...

━━━ AdminAuthTest ━━━
Testing: testSafeSessionStart... ✓ PASS
...

━━━ CreditsApiTest ━━━
Testing: testDatabaseConnection... ✓ PASS
...

╔════════════════════════════════════════════════════╗
║                  FINAL SUMMARY                     ║
╚════════════════════════════════════════════════════╝

SecurityTest              ✓ PASS (7 passed, 0 failed)
CacheTest                 ✓ PASS (17 passed, 0 failed)
AdminAuthTest             ✓ PASS (38 passed, 0 failed)
CreditsApiTest            ✓ PASS (49 passed, 0 failed)
IntegrationTest           ✓ PASS (97 passed, 0 failed)
UserManagementTest        ✓ PASS (116 passed, 0 failed)
ThemeTest                 ✓ PASS (140 passed, 0 failed)
SiteSettingsTest          ✓ PASS (154 passed, 0 failed)
EventEmailTest            ✓ PASS (173 passed, 0 failed)

──────────────────────────────────────────────────────
Total: 637 tests
Passed: 637
Pass Rate: 100.0%
──────────────────────────────────────────────────────

✅ ALL TESTS PASSED
```

## 🔧 Requirements

- PHP 8.1 or higher (tested on PHP 8.1, 8.2, 8.3, 8.4, 8.5)
- SQLite database (`calendar.db`) for database tests
- Write permissions on `cache/` directory

## 📝 Writing New Tests

### 1. Create Test File

Create a new file in `tests/` directory:

```php
<?php
/**
 * MyFeature Tests
 */

require_once __DIR__ . '/../config.php';

function testMyFeature($test) {
    // Arrange
    $input = 'test value';

    // Act
    $result = my_function($input);

    // Assert
    $test->assertEquals('expected', $result);
}

function testAnotherFeature($test) {
    $result = another_function();
    $test->assertTrue($result);
}
```

### 2. Add to run-tests.php

Edit `tests/run-tests.php` and add your test file:

```php
$testFiles = [
    // ... existing tests ...
    'MyFeatureTest' => __DIR__ . '/MyFeatureTest.php',
];
```

### 3. Run Tests

```bash
php tests/run-tests.php MyFeatureTest
```

## 🛠 Available Assertions

```php
// Equality
$test->assertEquals($expected, $actual);

// Boolean
$test->assertTrue($condition);
$test->assertFalse($condition);

// Null
$test->assertNull($value);
$test->assertNotNull($value);

// Empty
$test->assertEmpty($value);
$test->assertNotEmpty($value);

// Array
$test->assertCount($expectedCount, $array);
$test->assertArrayHasKey($key, $array);
$test->assertContains($needle, $haystack);

// Comparison
$test->assertGreaterThan($expected, $actual);
$test->assertLessThan($expected, $actual);

// Object
$test->assertInstanceOf($expectedClass, $object);

// File System
$test->assertFileExists($filepath);
$test->assertFileNotExists($filepath);
```

## 🎨 Test Organization

### Test Naming Convention

- Test files: `FeatureNameTest.php`
- Test functions: `testSpecificBehavior()`
- Use descriptive names that explain what is being tested

### Test Structure (AAA Pattern)

```php
function testFeature($test) {
    // Arrange - Set up test data
    $input = 'test';

    // Act - Execute the function
    $result = my_function($input);

    // Assert - Verify the result
    $test->assertEquals('expected', $result);
}
```

### Handling Test Dependencies

Some tests depend on external resources (database, files, etc.):

```php
function testDatabaseFeature($test) {
    $dbPath = dirname(__DIR__) . '/calendar.db';

    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    // Test code here...
}
```

## 🐛 Debugging Failed Tests

When a test fails, you'll see:

```
Testing: testMyFeature... ✗ FAIL
  Error: Expected "foo" but got "bar"
```

To debug:

1. **Run single test**: `php tests/run-tests.php MyTest::testMyFeature`
2. **Add debug output**: Use `var_dump()` or `print_r()` in test
3. **Check error logs**: Look for PHP errors or warnings
4. **Verify data**: Check database, cache files, session state

## 🔒 Security Tests

Security tests verify:

- **XSS Prevention**: Script tags are escaped/removed
- **SQL Injection**: Prepared statements prevent injection
- **CSRF**: Tokens are validated
- **Input Sanitization**: All user inputs are cleaned
- **Timing Attacks**: Constant-time comparisons used

## ⚡ Performance Tests

Cache tests verify:

- Cache files are created correctly
- TTL (Time To Live) works properly
- Cache invalidation works on updates
- Fallback behavior on errors

## 📊 Continuous Integration

To integrate with CI/CD:

```yaml
# Example GitHub Actions
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.1', '8.2', '8.3', '8.4', '8.5']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
      - name: Run tests
        run: php tests/run-tests.php
```

## 📈 Test Maintenance

### Regular Tasks

1. **Update tests** when adding new features
2. **Remove obsolete tests** when removing features
3. **Review test coverage** periodically
4. **Refactor tests** to reduce duplication

### Best Practices

- ✅ Keep tests independent (no shared state)
- ✅ Use descriptive test names
- ✅ Test both success and failure cases
- ✅ Clean up test data (database, files)
- ✅ Mock external dependencies when possible
- ✅ Run tests before committing code

## 🆘 Troubleshooting

### "Permission denied" errors

```bash
chmod +x tests/run-tests.php
chmod 755 cache/
```

### "Database file not found"

```bash
# Option A: Setup Wizard
# Open http://localhost:8000/setup.php

# Option B: Manual CLI
cd tools
php import-ics-to-sqlite.php
php migrate-add-credits-table.php
php migrate-add-admin-users-table.php
php migrate-rename-tables-columns.php
```

### "Session headers already sent"

Tests that use sessions should be run from CLI, not via web server.

### Colors not showing in terminal

Some terminals don't support ANSI colors. The tests will still run correctly, just without colors.

## 📚 Related Documentation

- [TESTING.md](../TESTING.md) - Manual testing guide
- [README.md](../README.md) - Main documentation
- [SECURITY.md](../SECURITY.md) - Security guidelines

---

**Questions?** Contact [@FordAntiTrust](https://x.com/FordAntiTrust)
