# 🌸 Idol Stage Timetable

A beautiful, responsive event calendar system designed for idol performances and stage events, featuring a stunning Sakura (桜) theme with multi-language support and powerful filtering capabilities.

**Perfect for**: Concert schedules, festival lineups, idol events, convention programming, and any multi-stage event management.

---

## ✨ Features

### 🎭 For Event Attendees
| Feature | Description |
|---------|-------------|
| 🌸 **Sakura Theme** | Beautiful cherry blossom-themed UI with Japanese aesthetics |
| 🌏 **Multi-language** | Full support for Thai, English, and Japanese (日本語) |
| 📱 **Mobile Optimized** | Responsive design works perfectly on all devices including iOS |
| 📊 **Dual View Modes** | Switch between List and Gantt Chart timeline views |
| 🔍 **Advanced Filtering** | Filter by artists, venues, or search keywords |
| 📸 **Save as Image** | Export filtered schedule as PNG image |
| 📅 **Export to Calendar** | Download as ICS file for Google Calendar, Apple Calendar, etc. |
| 📝 **Request Changes** | Submit requests to add or modify programs |
| 🎪 **Multi-Event** | Support for multiple conventions/events with URL-based selection |

### 👨‍💼 For Event Organizers (Admin)
| Feature | Description |
|---------|-------------|
| ⚙️ **Full CRUD** | Create, read, update, and delete programs via web interface |
| 📦 **Bulk Operations** | Select and edit/delete multiple events at once (up to 100) |
| ✏️ **Bulk Edit** | Update venue, organizer, or categories for multiple events |
| 🎯 **Flexible Venue** | Add new venues on-the-fly with autocomplete suggestions |
| 📊 **Custom Pagination** | Choose 20, 50, or 100 events per page |
| 📋 **Request Management** | Review and approve user-submitted program requests |
| 🔍 **Comparison View** | Side-by-side comparison of original vs. requested changes |
| 💳 **Credits Management** | Manage credits/references with full CRUD and bulk operations |
| 📤 **ICS Upload** | Upload and preview ICS files before importing |
| 💾 **Backup/Restore** | Backup and restore database with auto-safety backup |
| 🎪 **Events Management** | Full CRUD for managing multiple events |
| 🔐 **DB Auth & Multi-user** | Admin credentials in SQLite, supports multiple admin users |
| 🔑 **Change Password** | Change admin password via UI with current password verification |
| 👤 **User Management** | Full CRUD for admin users with role assignment |
| 🛡️ **Role-Based Access** | Admin (full access) / Agent (events only) role system |
| 🔒 **Secure Access** | Session-based authentication with optional IP whitelist |
| 🔐 **CSRF Protection** | Token-based CSRF validation for all admin operations |

### ⚡ Technical Highlights
| Feature | Description |
|---------|-------------|
| 🗄️ **SQLite Database** | 10-20x faster than parsing ICS files |
| 🔒 **Security First** | XSS protection, CSRF tokens, rate limiting, IP whitelist, security headers |
| 🔄 **Smart Caching** | Data version cache (10 min) + Credits cache (1 hour) with auto-invalidation |
| 📁 **ICS Compatible** | Import events from standard .ics calendar files |
| 🐳 **Docker Support** | One-command deployment with Docker Compose |
| 🎪 **Multi-Event** | Support multiple events with per-event venue mode and caching |
| 🧪 **324 Unit Tests** | Automated test suite, CI/CD with GitHub Actions (PHP 8.1-8.3) |
| 🛠️ **No Dependencies** | Pure PHP, vanilla JavaScript, no frameworks required |

---

## 📑 Table of Contents

- [Quick Start](#-quick-start)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Usage](#-usage)
- [Admin Panel](#️-admin-panel)
- [API Documentation](#-api-documentation) → [API.md](API.md)
- [Configuration](#-configuration)
- [Project Structure](#-project-structure) → [PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md)
- [Testing](#-testing)
- [Contributing](#-contributing)
- [License](#-license)
- [Support](#-support)

---

## 🚀 Quick Start

See [QUICKSTART.md](QUICKSTART.md) for a 3-step quick start guide.

**TL;DR:**
```bash
# 1. Navigate to project folder
cd stage-idol-calendar

# 2. Import ICS files to database (optional but recommended)
cd tools
php import-ics-to-sqlite.php
cd ..

# 3. Start PHP server
php -S localhost:8000

# 4. Open browser
# http://localhost:8000
```

---

## 🔧 Requirements

- **PHP 8.1+** (tested on PHP 8.1, 8.2, 8.3) with PDO SQLite extension
- **Web Server** (Apache, Nginx, or PHP built-in server)
- Modern web browser with JavaScript enabled

---

## 📦 Installation

| วิธี | เหมาะสำหรับ | คู่มือ |
|------|-----------|-------|
| 🐳 **Docker** | Production, ง่ายที่สุด | [DOCKER.md](DOCKER.md) |
| 🧙 **Setup Wizard** | Fresh install ทุกประเภท | [SETUP.md](SETUP.md) |
| 💻 **PHP Built-in** | Development/Local | [QUICKSTART.md](QUICKSTART.md) |
| 🌐 **Apache/Nginx** | Production server | [INSTALLATION.md](INSTALLATION.md) |

**Docker (fastest):**
```bash
docker-compose up -d
# http://localhost:8000
```

**PHP Built-in:**
```bash
php -S localhost:8000
# แล้วเปิด http://localhost:8000/setup.php
```

ดูรายละเอียดทั้งหมดที่ [INSTALLATION.md](INSTALLATION.md)

---

## 📖 Usage

### Viewing Events

The calendar displays all events with two view modes:

1. **List View** - Traditional table layout with full event details
2. **Gantt Chart View** - Visual timeline showing event overlaps across venues

Toggle between views using the switch below the search controls.

### Filtering Events

- **Text Search**: Click the search box (auto-selects), type artist/event name
- **Artist Filter**: Check one or more artists
- **Venue Filter**: Check one or more venues
- **Clear Filters**: Click the ✕ button in search box or remove individual tags

### Exporting Data

- **📸 Save as Image**: Downloads the filtered schedule as PNG
- **📅 Export to Calendar**: Downloads filtered events as .ics file

### Requesting Changes

Users can request to add new events or modify existing ones:

1. Click **"📝 Request to Add Event"** button to add new event
2. Click **"✏️"** button next to any event to request modifications
3. Fill in the form and submit
4. Admins will review and approve/reject requests

**Rate Limit**: 10 requests per hour per IP address

---

## ⚙️ Admin Panel

### Accessing Admin

1. Navigate to `/admin/`
2. Login with configured credentials
3. Default credentials are set in [config/admin.php](config/admin.php)

### Admin Capabilities

**Events Tab:**
- Create, edit, and delete events (with convention assignment)
- Bulk operations (select and delete/edit up to 100 events)
- Filter by venue or convention
- Pagination for large event lists (20/50/100 per page)

**Requests Tab:**
- View pending user requests
- Compare original vs. requested changes (side-by-side)
- Approve or reject requests
- Filter by status and convention

**Credits Tab:**
- Create, edit, and delete credits/references
- Bulk delete multiple credits
- Search, sort, and pagination
- Manage title, link, description, and display order

**Conventions Tab:**
- Create, edit, and delete conventions/events
- Configure name, slug, dates, venue mode, active status
- Per-convention venue mode (multi/single)

**Users Tab** (admin role only):
- Create, edit, and delete admin users
- Assign roles: `admin` (full access) or `agent` (events management only)
- Toggle active/inactive status
- Safety: cannot delete self, cannot change own role, must keep 1+ admin

**Backup Tab** (admin role only):
- Create database backup (stored on server in `backups/`)
- Download backup files to local machine
- Restore from server backup or upload .db file
- Auto-backup created before every restore operation
- Delete old backup files

**Authentication & Roles:**
- Admin credentials stored in SQLite (`admin_users` table) - supports multiple users
- Role-based access: `admin` sees all tabs; `agent` sees Events, Requests, Import, Credits, Conventions only
- Change Password button in admin header (current password required)
- Fallback to `config/admin.php` if `admin_users` table doesn't exist

### Initial Setup

#### Option A: Setup Wizard (Recommended) 🧙

Open `http://localhost:8000/setup.php` and follow the 5-step wizard:

1. **System Requirements** — checks PHP version, extensions, permissions
2. **Directories** — creates `data/`, `cache/`, `backups/`, `ics/`
3. **Database** — creates all tables and seeds admin user (auto-login)
4. **Import Data** — imports `.ics` files from `ics/` folder
5. **Admin & Security** — change default password, add indexes, lock setup

See [SETUP.md](SETUP.md) for detailed guide.

#### Option B: Manual CLI

```bash
cd tools

# Create all tables
php import-ics-to-sqlite.php
php migrate-add-requests-table.php
php migrate-add-credits-table.php
php migrate-add-events-meta-table.php
php migrate-add-admin-users-table.php
php migrate-add-role-column.php
php migrate-rename-tables-columns.php
php migrate-add-indexes.php
```

**(Optional) Enable IP whitelist** in `config/admin.php`:
```php
define('ADMIN_IP_WHITELIST_ENABLED', true);
define('ADMIN_ALLOWED_IPS', [
    '127.0.0.1',
    '192.168.1.0/24',  // Your office network
]);
```

For more details, see [INSTALLATION.md](INSTALLATION.md) and [SETUP.md](SETUP.md).

---

## 🔌 API Documentation

ระบบมี 3 API groups:

| API | URL | Auth | Description |
|-----|-----|------|-------------|
| **Public** | `/api.php` | ❌ | Programs, organizers, locations, events list |
| **Request** | `/api/request.php` | ❌ | User request submission (rate limited) |
| **Admin** | `/admin/api.php` | ✅ Session + CSRF | Full CRUD ทั้งระบบ |

**Public API ตัวอย่าง:**
```http
GET /api.php?action=programs&event=idol-stage-feb-2026
```

**Admin API ต้องการ:**
- Session cookie (login ที่ `/admin/login`)
- Header `X-CSRF-Token` สำหรับ POST/PUT/DELETE

ดู **[API.md](API.md)** สำหรับ endpoint documentation ครบถ้วนพร้อม request/response examples

---

## 🎨 Configuration

### Changing Version (Cache Busting)

Edit [config/app.php](config/app.php):
```php
define('APP_VERSION', '2.0.0'); // Change this to force cache refresh
```

### Multi-Event Mode

Enable multiple conventions support in [config/app.php](config/app.php):

```php
define('MULTI_EVENT_MODE', true);       // Enable multi-event support
define('DEFAULT_EVENT_SLUG', 'default'); // Default convention slug
```

Access events via URL: `/event/slug` (e.g., `/event/idol-stage-feb-2026`)

### Venue Mode

Toggle between multi-venue and single-venue layouts in [config/app.php](config/app.php):

```php
define('VENUE_MODE', 'multi');   // Multiple venues: shows venue filter, Gantt view, venue columns
define('VENUE_MODE', 'single');  // Single venue: hides venue filter, Gantt view, venue columns
```

| Feature | `multi` | `single` |
|---------|---------|----------|
| Venue filter (checkboxes) | Visible | Hidden |
| List/Timeline toggle switch | Visible | Hidden |
| Venue column in event table | Visible | Hidden |
| Venue column in admin table | Visible | Hidden |

### Cache Configuration

Edit [config/cache.php](config/cache.php):
```php
// Data version cache (footer display)
define('DATA_VERSION_CACHE_TTL', 600); // 10 minutes

// Credits cache (credits.php page)
define('CREDITS_CACHE_TTL', 3600); // 1 hour
```

**Cache files** (auto-created in `cache/` directory):
- `cache/data_version.json` - Last update timestamp
- `cache/credits.json` - Credits data with timestamp

**Manual cache clear**:
```bash
# Clear all cache
rm cache/*.json

# Clear credits cache only
rm cache/credits.json
```

### Customizing Theme Colors

Edit `styles/common.css`:
```css
:root {
    --sakura-light: #FFB7C5;
    --sakura-medium: #F48FB1;
    --sakura-dark: #E91E63;
    --sakura-deep: #C2185B;
    --sakura-gradient: linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%);
}
```

### Adding/Editing Translations

Edit `js/translations.js` to add or modify translations for Thai, English, and Japanese.

### ICS File Format

The system supports standard iCalendar (.ics) format:

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Your Organization//EN

BEGIN:VEVENT
UID:unique-id-001@yourdomain.com
DTSTART:20260207T100000Z
DTEND:20260207T110000Z
SUMMARY:Event Title
LOCATION:Venue Name
ORGANIZER;CN="Artist Name":mailto:info@example.com
CATEGORIES:Artist Name
DESCRIPTION:Event description
STATUS:CONFIRMED
END:VEVENT

END:VCALENDAR
```

Place `.ics` files in the `ics/` folder and run the import script.

---

## 📁 Project Structure

```
stage-idol-calendar/
├── index.php / api.php / setup.php / ...   # Root PHP pages
├── config/          Configuration constants (app, admin, security, database, cache)
├── functions/       Helper functions (helpers, cache, admin, security)
├── styles/ / js/   CSS + JavaScript (Sakura theme, translations)
├── data/            SQLite database (calendar.db, .setup_locked)
├── backups/         Database backups (auto-created)
├── cache/           Cache files (data_version, credits, login_attempts)
├── ics/             ICS source files
├── api/             Public API (request.php)
├── admin/           Admin panel (login.php, index.php, api.php)
├── tools/           CLI migration scripts
├── tests/           324 automated tests
└── *.md             Documentation
```

ดู **[PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md)** สำหรับรายละเอียดไฟล์ทั้งหมด ความสัมพันธ์ระหว่างไฟล์ และคำอธิบาย functions

---

## 🔒 Security

Security is a top priority for this project.

### Security Features

#### 🛡️ Input Protection
- **XSS Prevention**: Comprehensive input sanitization with dedicated functions
  - `sanitize_string()` - Removes null bytes, trims, limits length
  - `sanitize_string_array()` - Handles array inputs with item limits
  - `get_sanitized_param()` - Safe GET parameter retrieval
  - `get_sanitized_array_param()` - Safe array parameter retrieval
- **Output Encoding**: All user-generated content properly escaped before display
- **JSON Security**: Safe JSON encoding with `JSON_HEX_*` flags for HTML attributes

#### 🔐 Session Security
- **Session Timeout**: Automatic logout after 2 hours of inactivity (configurable in `config/admin.php`)
- **Timing Attack Prevention**: Constant-time comparison (`hash_equals()`) for username/password checks
- **Session Fixation Prevention**: Session ID regeneration on login and logout
- **Secure Cookies**: httponly, secure, SameSite=Strict attributes
- **Race Condition Prevention**: Safe session start with status checks

#### 🔌 API Security
- **CSRF Protection**: Token-based validation for all state-changing operations (POST, PUT, DELETE)
- **SQL Injection Prevention**: PDO prepared statements for all database queries
- **Authentication Required**: All admin endpoints protected by login check
- **IP Whitelist**: Optional IP restriction for admin panel (configurable in `config/admin.php`)

#### 🌐 General Security
- **Security Headers**: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy
- **Rate Limiting**: Prevents abuse of user request submission (10 requests/hour/IP)
- **Input Validation**: Length limits, null byte removal, array size limits
- **Error Handling**: Safe error messages in production mode (hides details)

### Reporting Security Issues

If you discover a security vulnerability, please email the author directly instead of opening a public issue. See [CONTRIBUTING.md](CONTRIBUTING.md) for contact information.

---

## 🛠️ Development

### Developer Tools

Located in `tools/` folder:

| Tool | Purpose |
|------|---------|
| `import-ics-to-sqlite.php` | Import ICS files to SQLite database |
| `update-ics-categories.php` | Add CATEGORIES field to ICS files |
| `migrate-add-requests-table.php` | Create event_requests table |
| `migrate-add-credits-table.php` | Create credits table |
| `migrate-add-events-meta-table.php` | Create events_meta table (multi-event support) |
| `migrate-rename-tables-columns.php` | Rename tables/columns to v1.2.9 schema (idempotent) |
| `migrate-add-indexes.php` | Add DB performance indexes (idempotent, run once) |
| `migrate-add-admin-users-table.php` | Create admin_users table + seed from config |
| `migrate-add-role-column.php` | Add role column to admin_users (RBAC) |
| `generate-password-hash.php` | Generate bcrypt password hash for admin |
| `debug-parse.php` | Debug ICS file parsing |
| `test-parse.php` | Test ICS parser |

### Running Tests

```bash
# Run all 324 automated tests
php tests/run-tests.php

# Run specific suite
php tests/run-tests.php SecurityTest

# Quick pre-commit tests
quick-test.bat          # Windows
./quick-test.sh         # Linux/Mac
```

See [Testing](#-testing) section for full details.

### Docker Development

```bash
# Development mode (live reload)
docker-compose -f docker-compose.dev.yml up

# Run tests in container
docker exec idol-stage-calendar php tests/run-tests.php
```

See [DOCKER.md](DOCKER.md) for complete Docker guide.

### Database Management

See [SQLITE_MIGRATION.md](SQLITE_MIGRATION.md) for database schema, migration guide, and performance benchmarks.

---

## 🐛 Troubleshooting

### Events Not Showing

- Check that `.ics` files exist in `ics/` folder
- Run `php tools/import-ics-to-sqlite.php` to import
- Verify file permissions allow PHP to read files

### Cache Not Updating

- Change `APP_VERSION` in `config.php`
- Clear browser cache (Ctrl+F5 or Cmd+Shift+R)
- If using Cloudflare, purge cache

### Image Export Not Working

- Check internet connection (html2canvas loads from CDN)
- Open browser console to check for errors
- Ensure popup blocker is not blocking download

### Database Errors

- Verify PHP has SQLite extension enabled: `php -m | grep pdo_sqlite`
- Check database file permissions: `chmod 644 data/calendar.db`
- Try deleting `data/calendar.db` and re-running import

---

### Quick Guidelines

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

### Built With

- **Backend**: PHP 8.1+ (tested on 8.1, 8.2, 8.3), SQLite
- **Frontend**: Vanilla JavaScript, CSS3
- **Libraries**: [html2canvas](https://html2canvas.hertzen.com/) (lazy-loaded for image export)
- **Design**: Sakura (桜) theme with Material Design influences

### Use Case Example

This project was originally created for **Idol Stage Event** to manage idol stage schedules across multiple venues.

---

## 🧪 Testing

### Automated Test Suite

The project includes **324 automated unit tests** covering all critical functionality:

**Test Suites:**
- 🔒 **SecurityTest** (7 tests) - Input sanitization, XSS protection, SQL injection prevention
- 💾 **CacheTest** (17 tests) - Cache creation, invalidation, TTL, fallback behavior
- 🔐 **AdminAuthTest** (38 tests) - Authentication, session management, timing attack resistance, DB auth, change password
- 📋 **CreditsApiTest** (49 tests) - Database CRUD operations, bulk operations
- 🔗 **IntegrationTest** (97 tests) - File structure, configuration, full workflows, API endpoints
- 👤 **UserManagementTest** (116 tests) - Role column schema, role helpers, user CRUD, permission checks

**Run All Tests:**
```bash
php tests/run-tests.php
```

**Run Specific Suite:**
```bash
php tests/run-tests.php SecurityTest
php tests/run-tests.php CacheTest
php tests/run-tests.php IntegrationTest
```

**Quick Pre-Commit Tests:**
```bash
# Windows
quick-test.bat

# Linux/Mac
./quick-test.sh
```

**Test Documentation:**
- [tests/README.md](tests/README.md) - Testing guide
- [TESTING.md](TESTING.md) - Manual testing checklist

**CI/CD Integration:**

GitHub Actions automatically run tests on every push/PR across **PHP 8.1, 8.2, and 8.3**.

```yaml
# .github/workflows/tests.yml included
strategy:
  matrix:
    php-version: ['8.1', '8.2', '8.3']
```

✅ **All 324 tests pass on PHP 8.1, 8.2, and 8.3**

**Expected Output:**
```
✅ ALL TESTS PASSED

Total: 324 tests
Passed: 324
Pass Rate: 100.0%
```

For detailed testing documentation, see [tests/README.md](tests/README.md) and [TESTING.md](TESTING.md).

---

## 📜 Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and release notes.

**Current Version**: 2.0.0

---

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

---

## 📞 Support

- **Documentation**: [README](README.md) | [Quick Start](QUICKSTART.md) | [Setup](SETUP.md) | [Install](INSTALLATION.md) | [API](API.md) | [Structure](PROJECT-STRUCTURE.md)
- **Issues**: [GitHub Issues](https://github.com/yourusername/stage-idol-calendar/issues)
- **Twitter**: [@FordAntiTrust](https://x.com/FordAntiTrust)

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

**TL;DR**: You can use, modify, and distribute this software freely, even for commercial purposes.

---

<div align="center">

🌸 **Idol Stage Timetable** 🌸

Made with ❤️ for event organizers and idol fans everywhere

[⭐ Star this repo](https://github.com/yourusername/stage-idol-calendar) | [🐛 Report Bug](https://github.com/yourusername/stage-idol-calendar/issues) | [✨ Request Feature](https://github.com/yourusername/stage-idol-calendar/issues)

</div>
