# 🗄️ SQLite Database Guide

Complete guide to using SQLite with Idol Stage Timetable for optimal performance.

---

## 📑 Table of Contents

- [Overview](#-overview)
- [Why SQLite](#-why-sqlite)
- [Database Schema](#-database-schema)
- [Getting Started](#-getting-started)
- [Updating Data](#-updating-data)
- [Database Management](#️-database-management)
- [Performance Benchmarks](#-performance-benchmarks)
- [Admin API](#-admin-api)
- [Advanced Usage](#-advanced-usage)
- [Troubleshooting](#-troubleshooting)

---

## 🎯 Overview

Idol Stage Timetable supports two data sources:
1. **ICS Files** - Simple, portable, but slower for large datasets
2. **SQLite Database** - Fast, efficient, recommended for production

This guide covers SQLite integration, migration, and optimization.

---

## ✨ Why SQLite?

### Performance Comparison

| Operation | ICS Files | SQLite | Improvement |
|-----------|-----------|--------|-------------|
| Load 100 events | ~500ms | ~50ms | **10x faster** |
| Filter by artist | ~300ms | ~20ms | **15x faster** |
| Get all artists | ~200ms | ~10ms | **20x faster** |

### Key Benefits

- ⚡ **10-20x faster** - No need to parse files every request
- 🔍 **Efficient queries** - SQL-based filtering and searching
- 📊 **Better scalability** - Handles thousands of events easily
- 🔄 **Easy updates** - Update individual events without reparsing
- 🗂️ **Structured data** - Proper data types, timestamps, auto-increment IDs

---

## 📊 Database Schema

> **Note**: Tables and columns were renamed in v2.0.0. Run `tools/migrate-rename-tables-columns.php` to update existing databases.

### Table: `programs`

Stores individual show/performance data (formerly named `events`).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Unique program ID |
| `uid` | TEXT | UNIQUE NOT NULL | ICS UID (globally unique) |
| `title` | TEXT | NOT NULL | Program title/summary |
| `start` | DATETIME | NOT NULL | Start date and time |
| `end` | DATETIME | NOT NULL | End date and time |
| `location` | TEXT | | Venue/stage name |
| `organizer` | TEXT | | Performer/artist |
| `description` | TEXT | | Program description |
| `categories` | TEXT | | Artist names (comma-separated) |
| `event_id` | INTEGER | FK → `events.id` | Event this program belongs to |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Indexes** (v1.2.10):
- `idx_programs_event_id` — FK lookups
- `idx_programs_start` — chronological queries
- `idx_programs_location` — venue filtering
- `idx_programs_categories` — artist filtering

---

### Table: `program_requests`

Stores user-submitted requests for adding or modifying programs (formerly `event_requests`).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Request ID |
| `type` | TEXT | NOT NULL | 'add' or 'modify' |
| `program_id` | INTEGER | FK → `programs.id` | Program to modify (for type='modify') |
| `event_id` | INTEGER | FK → `events.id` | Event this request belongs to |
| `title` | TEXT | NOT NULL | Requested program title |
| `start` | DATETIME | NOT NULL | Requested start time |
| `end` | DATETIME | NOT NULL | Requested end time |
| `location` | TEXT | | Requested venue |
| `organizer` | TEXT | | Requested performer |
| `description` | TEXT | | Requested description |
| `categories` | TEXT | | Requested artist names |
| `requester_name` | TEXT | | Name of person making request |
| `requester_email` | TEXT | | Email of requester |
| `requester_note` | TEXT | | Additional notes from requester |
| `status` | TEXT | DEFAULT 'pending' | 'pending', 'approved', or 'rejected' |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Request submission time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last status update time |

**Indexes** (v1.2.10):
- `idx_program_requests_status` — status filtering
- `idx_program_requests_event_id` — event filtering

---

### Table: `events`

Stores event/convention metadata for multi-event support (formerly `events_meta`).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Event ID |
| `slug` | TEXT | UNIQUE NOT NULL | URL-friendly identifier (e.g., `idol-stage-feb-2026`) |
| `name` | TEXT | NOT NULL | Event display name |
| `description` | TEXT | | Optional description |
| `start_date` | DATE | | Event start date |
| `end_date` | DATE | | Event end date |
| `venue_mode` | TEXT | DEFAULT 'multi' | Venue mode: 'multi' or 'single' |
| `is_active` | BOOLEAN | DEFAULT 1 | Whether event is active |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Referenced by**:
- `programs.event_id` → `events.id`
- `program_requests.event_id` → `events.id`
- `credits.event_id` → `events.id`

---

### Table: `credits`

Stores credits and references displayed on the credits.php page.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Credit ID |
| `title` | TEXT | NOT NULL | Credit title/name |
| `link` | TEXT | | URL/website link (optional) |
| `description` | TEXT | | Additional description (optional) |
| `display_order` | INTEGER | DEFAULT 0 | Sort order (lower = shown first) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Indexes**:
- `idx_credits_order` - Index on `display_order` for efficient sorting

**Cache**: Credits data is cached for 1 hour (configurable in `config/cache.php`)

---

### Table: `admin_users`

Stores admin user credentials and roles.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | User ID |
| `username` | TEXT | UNIQUE NOT NULL | Login username |
| `password_hash` | TEXT | NOT NULL | Bcrypt password hash |
| `display_name` | TEXT | | Display name in UI |
| `role` | TEXT | NOT NULL DEFAULT 'admin' | User role: 'admin' or 'agent' |
| `is_active` | BOOLEAN | DEFAULT 1 | Whether user is active |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |
| `last_login_at` | DATETIME | | Last successful login |

**Indexes**:
- `idx_admin_users_username` - Unique index on `username`

**Roles**:
- `admin`: Full access (manage users, backup, and all features)
- `agent`: Events management only (Events, Requests, Import ICS, Credits, Conventions)

---

## 🚀 Getting Started

### Option A: Setup Wizard (Recommended) 🧙

Open `http://your-domain.com/setup.php` — the wizard handles everything: creates directories, initializes all tables, seeds admin user, and imports `.ics` data.

See [SETUP.md](SETUP.md) for detailed guide.

---

### Option B: Manual CLI

#### Step 1: Import ICS Files (creates `programs` table)

```bash
cd tools
php import-ics-to-sqlite.php
```

#### Step 2: Create Additional Tables

```bash
cd tools

# Request system (program_requests table)
php migrate-add-requests-table.php

# Credits management
php migrate-add-credits-table.php

# Multi-event support (events table)
php migrate-add-events-meta-table.php

# Database-based auth (admin_users table)
php migrate-add-admin-users-table.php

# Role-based access control
php migrate-add-role-column.php

# Rename tables/columns to v2.0.0 schema (idempotent)
php migrate-rename-tables-columns.php

# Performance indexes (idempotent)
php migrate-add-indexes.php
```

**Expected Output**:
```
=== ICS to SQLite Import Script ===

✅ Connected to database: data/calendar.db

📋 Creating table structure...
✅ Table structure created/verified

📁 Found 3 file(s)

Processing: events.ics
  ✅ Inserted: Concert at Main Stage
  ✅ Inserted: Meet & Greet Session
  ...

=== Import Summary ===
✅ Inserted: 25 event(s)
🔄 Updated: 0 event(s)
⏭️  Skipped: 0 event(s)
❌ Errors: 0 event(s)

📊 Total events in database: 25

✅ Import completed!
```

### Step 2: Verify Database

Check that database was created:

```bash
ls -lh data/calendar.db
```

Should show a file with size > 0 bytes.

### Step 3: Test Application

Start server and verify events display:

```bash
php -S localhost:8000
```

Open `http://localhost:8000` - events should load instantly!

---

## 🔄 Updating Data

### When You Have New ICS Files

1. **Add new `.ics` files** to `ics/` folder

2. **Re-run import script**:
   ```bash
   cd tools
   php import-ics-to-sqlite.php
   ```

3. **Import behavior**:
   - **New events** (unique UID) → Inserted
   - **Existing events** (duplicate UID) → Updated
   - **Unchanged events** → Skipped

**Smart Updates**: The import script uses `INSERT OR REPLACE` to:
- Preserve existing event IDs
- Update modified events
- Avoid duplicates

### Manual Database Edits

Use the **Admin Panel** at `/admin/` for:
- Creating events
- Editing events
- Deleting events
- Managing user requests

---

## 🛠️ Database Management

### Using SQLite Command Line

**Open database**:
```bash
sqlite3 data/calendar.db
```

**Useful commands**:

```sql
-- View all tables
.tables
-- Should show: programs, events, program_requests, credits, admin_users

-- Show table schema
.schema programs

-- Count programs
SELECT COUNT(*) FROM programs;

-- View all programs ordered by start
SELECT * FROM programs ORDER BY start;

-- Filter by artist
SELECT * FROM programs WHERE categories LIKE '%Artist Name%';

-- Filter by venue
SELECT * FROM programs WHERE location = 'Main Stage';

-- View programs on specific date
SELECT * FROM programs
WHERE DATE(start) = '2026-02-07'
ORDER BY start;

-- Delete all programs (careful!)
DELETE FROM programs;

-- Exit SQLite
.quit
```

### Backup and Restore

Use the **Admin Panel → Backup tab** for GUI-based backup/restore, or via CLI:

**Backup**:
```bash
# Simple file copy
cp data/calendar.db backups/calendar.db.backup

# SQL dump (more portable)
sqlite3 data/calendar.db .dump > backups/backup.sql
```

**Restore**:
```bash
# From backup file
cp backups/calendar.db.backup data/calendar.db

# From SQL dump
sqlite3 data/calendar.db < backups/backup.sql
```

### Vacuum and Optimize

**Compact database** (reclaim unused space):
```bash
sqlite3 data/calendar.db "VACUUM;"
```

**Analyze queries** (optimize query planner):
```bash
sqlite3 data/calendar.db "ANALYZE;"
```

Run these periodically (monthly) if database grows large.

---

## 📈 Performance Benchmarks

### Real-World Test: 500 Events

| Metric | ICS Files | SQLite | Improvement |
|--------|-----------|--------|-------------|
| Initial page load | 2.1s | 0.18s | **11.7x faster** |
| Filter by artist | 1.8s | 0.09s | **20x faster** |
| Get all organizers | 1.5s | 0.05s | **30x faster** |
| Memory usage | 45 MB | 12 MB | **3.75x less** |

### Why SQLite is Faster

1. **No parsing** - Data already structured
2. **Indexed queries** - Fast lookups on `uid` and `start`
3. **Selective loading** - Fetch only needed columns
4. **Compiled SQL** - Optimized query execution
5. **Application-level caching** - Data version (10 min) + Credits (1 hour)
6. **SQLite internal caching** - Built-in query result caching

### Application Cache System

The system includes two cache layers:

**Data Version Cache** (`cache/data_version.json`):
- Caches last update timestamp from events table
- TTL: 10 minutes (600 seconds)
- Displayed in footer
- Reduces database queries for version check

**Credits Cache** (`cache/credits.json`):
- Caches all credits data from database
- TTL: 1 hour (3600 seconds)
- Auto-invalidates on create/update/delete
- Significantly faster page load for credits.php

**Cache Configuration** in `config/cache.php`:
```php
define('DATA_VERSION_CACHE_TTL', 600);   // 10 minutes
define('CREDITS_CACHE_TTL', 3600);       // 1 hour
```

**Manual Cache Clear**:
```bash
# Clear all cache
rm cache/*.json

# Clear specific cache
rm cache/data_version.json
rm cache/credits.json
```

### When to Use ICS Files

ICS mode is still useful for:
- Very small datasets (< 20 events)
- Portable, file-based distribution
- Direct editing in calendar apps
- Testing and development

**Switch modes** in `index.php`:
```php
// SQLite mode (default, recommended)
$parser = new IcsParser('ics');

// ICS file mode
$parser = new IcsParser('ics', false);
```

---

## 🔌 Admin API

Admin panel uses these endpoints in [admin/api.php](admin/api.php). All require a valid session cookie + `X-CSRF-Token` header for state-changing operations.

### Programs Management

**List programs** (with pagination):
```http
GET /admin/api.php?action=programs_list&page=1&limit=20&search=keyword&location=venue
```

**Get single program**:
```http
GET /admin/api.php?action=programs_get&id=5
```

**Create program**:
```http
POST /admin/api.php?action=programs_create
Content-Type: application/json

{
  "title": "New Program",
  "start": "2026-03-01 10:00:00",
  "end": "2026-03-01 11:00:00",
  "location": "Main Stage",
  "organizer": "Artist Name",
  "description": "Program details",
  "categories": "Artist Name",
  "event_id": 1
}
```

**Update program**:
```http
PUT /admin/api.php?action=programs_update&id=5
Content-Type: application/json

{ "title": "Updated Title", ... }
```

**Delete program**:
```http
DELETE /admin/api.php?action=programs_delete&id=5
```

**Bulk delete**:
```http
DELETE /admin/api.php?action=programs_bulk_delete
Content-Type: application/json

{ "ids": [1, 2, 3] }
```

**Bulk edit** (venue/organizer/categories):
```http
PUT /admin/api.php?action=programs_bulk_update
Content-Type: application/json

{ "ids": [1, 2, 3], "location": "Stage B" }
```

### Requests Management

**List requests**:
```http
GET /admin/api.php?action=requests&status=pending
```

**Approve / Reject request**:
```http
PUT /admin/api.php?action=request_approve&id=10
PUT /admin/api.php?action=request_reject&id=10
```

### Events Management (meta/convention)

```http
GET    /admin/api.php?action=events_list
GET    /admin/api.php?action=events_get&id=1
POST   /admin/api.php?action=events_create
PUT    /admin/api.php?action=events_update&id=1
DELETE /admin/api.php?action=events_delete&id=1
```

### Credits Management

```http
GET    /admin/api.php?action=credits_list&page=1&limit=20&search=keyword
GET    /admin/api.php?action=credits_get&id=5
POST   /admin/api.php?action=credits_create
PUT    /admin/api.php?action=credits_update&id=5
DELETE /admin/api.php?action=credits_delete&id=5
DELETE /admin/api.php?action=credits_bulk_delete   # body: { "ids": [1,2,3] }
```

**Cache Invalidation**: Credits cache is automatically cleared after create/update/delete.

---

## 🚀 Advanced Usage

### Custom Queries

Create custom PHP scripts to query the database:

```php
<?php
require_once 'config.php';

$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Example: Get programs happening today
$stmt = $db->prepare("
    SELECT * FROM programs
    WHERE DATE(start) = DATE('now')
    ORDER BY start
");
$stmt->execute();
$todayPrograms = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($todayPrograms as $p) {
    echo "{$p['title']} at {$p['location']}\n";
}
```

### Automated Import (Cron)

Set up automatic imports via cron job:

```bash
# Edit crontab
crontab -e

# Add line: Import every hour
0 * * * * cd /path/to/stage-idol-calendar/tools && php import-ics-to-sqlite.php >> /var/log/calendar-import.log 2>&1
```

### Export to ICS

Export database events back to ICS format:

```php
<?php
$db = new PDO('sqlite:calendar.db');
$events = $db->query("SELECT * FROM programs ORDER BY start")->fetchAll();

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="export.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Idol Stage Timetable//EN\r\n";

foreach ($events as $event) {
    echo "BEGIN:VEVENT\r\n";
    echo "UID:{$event['uid']}\r\n";
    echo "DTSTART:" . date('Ymd\THis\Z', strtotime($event['start'])) . "\r\n";
    echo "DTEND:" . date('Ymd\THis\Z', strtotime($event['end'])) . "\r\n";
    echo "SUMMARY:{$event['title']}\r\n";
    if ($event['location']) echo "LOCATION:{$event['location']}\r\n";
    if ($event['description']) echo "DESCRIPTION:{$event['description']}\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
```

---

## 🔍 Troubleshooting

### Database Not Found

**Error**: `unable to open database file`

**Cause**: Database hasn't been created yet, or `data/` directory doesn't exist

**Fix**:
```bash
mkdir -p data
cd tools
php import-ics-to-sqlite.php
# Or use setup.php wizard: http://localhost:8000/setup.php
```

---

### Database Locked

**Error**: `database is locked`

**Cause**: Another process is using the database

**Fix**:
```bash
# Check for locked processes
lsof calendar.db

# Kill hung processes if needed
killall php

# Worst case: delete lock files
rm calendar.db-shm calendar.db-wal
```

---

### Data Not Updating

**Problem**: Made changes but calendar doesn't reflect them

**Fix**:
1. Re-run import: `php tools/import-ics-to-sqlite.php`
2. Change `APP_VERSION` in `config/app.php` to bust cache
3. Hard refresh browser (Ctrl+F5)

---

### Database Corruption

**Problem**: `database disk image is malformed`

**Fix**:
```bash
# Try to repair
sqlite3 data/calendar.db "PRAGMA integrity_check;"

# If that fails, restore from backup (Admin → Backup tab)
cp backups/backup_*.db data/calendar.db

# Or re-import from ICS files
rm data/calendar.db
php tools/import-ics-to-sqlite.php
```

**Prevention**: Regular backups!

---

### Performance Degradation

**Problem**: Database getting slow over time

**Fix**:
```bash
# Vacuum to reclaim space
sqlite3 data/calendar.db "VACUUM;"

# Update statistics
sqlite3 data/calendar.db "ANALYZE;"

# Check database size
ls -lh data/calendar.db
```

---

## 🔐 Security Considerations

### File Permissions

```bash
# Secure database directory and file
chmod 750 data/
chmod 600 data/calendar.db
chown www-data:www-data data/ data/calendar.db

# data/ is not web-accessible by default (.htaccess blocks it)
# Nginx: add "location ~ ^/data { deny all; }" to server block
```

### SQL Injection Prevention

**Always use prepared statements**:

```php
// ✅ SAFE
$stmt = $db->prepare("SELECT * FROM programs WHERE id = ?");
$stmt->execute([$id]);

// ❌ UNSAFE - Never do this!
$result = $db->query("SELECT * FROM programs WHERE id = $id");
```

All queries in this project use prepared statements.

### XSS Protection

Data from database is escaped before output:

```php
// Server-side escaping in api.php
function escapeOutputData($data) {
    if (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}
```

---

## 🧪 Testing Database Setup

After setting up SQLite database, verify everything works correctly:

### Quick Database Test

```bash
# Test database connection and tables
php tests/run-tests.php CreditsApiTest
php tests/run-tests.php IntegrationTest
```

### Verify Tables Exist

```bash
sqlite3 data/calendar.db

# Inside SQLite shell:
.tables
# Should show: programs, events, program_requests, credits, admin_users

.schema programs
# Should show table structure

SELECT COUNT(*) FROM programs;
# Should show number of imported programs

.quit
```

### Run Full Test Suite

```bash
# Verify all features work with database
php tests/run-tests.php

# Expected: 324 tests pass (all tests pass on PHP 8.1, 8.2, 8.3)
# If any tests fail, check:
# - Database file exists at data/calendar.db
# - All tables are created (programs, events, program_requests, credits, admin_users)
# - Permissions on data/ and cache/ directories are correct
```

### Test Cache with Database

```bash
# Clear cache
rm cache/*.json

# Load page to create cache
curl http://localhost:8000/

# Verify cache created
ls -la cache/
# Should show: data_version.json, credits.json
```

### Manual Verification Checklist

After migration:
- [ ] Run `php tests/run-tests.php` — all 324 tests pass
- [ ] Visit index.php — programs display correctly
- [ ] Check `cache/` folder — cache files created
- [ ] Login to admin — can view/edit programs
- [ ] Test bulk operations — select/delete multiple programs
- [ ] Visit credits.php — credits display from database

See [TESTING.md](TESTING.md) for comprehensive manual test cases.

---

## 📚 Additional Resources

- **SQLite Documentation**: https://www.sqlite.org/docs.html
- **PHP PDO Tutorial**: https://www.php.net/manual/en/book.pdo.php
- **Setup Wizard**: [SETUP.md](SETUP.md)
- **Main Docs**: [README.md](README.md)
- **Installation Guide**: [INSTALLATION.md](INSTALLATION.md)
- **Quick Start**: [QUICKSTART.md](QUICKSTART.md)

---

## 🎉 Summary

SQLite provides:
- ✅ **10-20x performance improvement** over ICS files
- ✅ **Efficient querying** with SQL
- ✅ **Easy management** via admin panel
- ✅ **Scalability** for large event databases
- ✅ **Backward compatibility** - still supports ICS files

**Recommended setup**: Import ICS files to SQLite, keep ICS files as backup/source of truth.

---

**Questions?** Open an issue on [GitHub](https://github.com/yourusername/stage-idol-calendar/issues) or contact [@FordAntiTrust](https://x.com/FordAntiTrust).
