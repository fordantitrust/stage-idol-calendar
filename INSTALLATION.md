# 📖 Installation Guide - Idol Stage Timetable

Complete installation and configuration guide for all deployment scenarios.

---

## 📑 Table of Contents

- [Overview](#-overview)
- [System Requirements](#-system-requirements)
- [Installation Methods](#-installation-methods)
- [Creating ICS Files](#-creating-ics-files)
- [Configuration](#-configuration)
- [Admin Panel Setup](#️-admin-panel-setup)
- [Request System Setup](#-request-system-setup)
- [Security Configuration](#-security-configuration)
- [Customization](#-customization)
- [Troubleshooting](#-troubleshooting)

---

## 🎯 Overview

Idol Stage Timetable is a PHP-based web application for managing and displaying event schedules. It features:
- 🌸 Beautiful Sakura theme
- 🌏 Multi-language support (Thai/English/Japanese)
- 📱 Mobile-responsive design
- ⚡ SQLite database for performance
- ⚙️ Admin panel for event management
- 📝 User request system

---

## 🔧 System Requirements

### Minimum Requirements
- **PHP**: 8.1 or higher (tested on PHP 8.1, 8.2, 8.3)
- **PHP Extensions**: PDO, PDO_SQLite, mbstring
- **Web Server**: Apache, Nginx, or PHP built-in server
- **Disk Space**: ~10 MB + space for database
- **Browser**: Modern browser with JavaScript enabled

### Recommended Requirements
- **PHP**: 8.2 or higher
- **Memory**: 256 MB RAM
- **Permissions**: Read/write access to project directory

### Verify PHP Installation
```bash
# Check PHP version
php -v

# Check for SQLite support
php -m | grep pdo_sqlite
```

---

## 📦 Installation Methods

### Method 1: PHP Built-in Server (Development)

**Best for**: Local testing, development

```bash
# 1. Navigate to project folder
cd /path/to/stage-idol-calendar

# 2. Start server
php -S localhost:8000

# 3. Open browser
# http://localhost:8000
```

**Pros**: Quick setup, no configuration needed
**Cons**: Not suitable for production

---

### Method 2: XAMPP (Windows)

**Best for**: Windows users, beginners

1. **Download XAMPP**: https://www.apachefriends.org

2. **Install XAMPP** to `C:\xampp`

3. **Copy project folder** to:
   ```
   C:\xampp\htdocs\stage-idol-calendar\
   ```

4. **Start Apache** from XAMPP Control Panel

5. **Open browser**:
   ```
   http://localhost/stage-idol-calendar/
   ```

**Pros**: Easy GUI, includes phpMyAdmin
**Cons**: Windows only, heavier than needed

---

### Method 3: MAMP (macOS)

**Best for**: macOS users

1. **Download MAMP**: https://www.mamp.info

2. **Install MAMP** to `/Applications/MAMP`

3. **Copy project folder** to:
   ```
   /Applications/MAMP/htdocs/stage-idol-calendar/
   ```

4. **Start MAMP servers**

5. **Open browser**:
   ```
   http://localhost:8888/stage-idol-calendar/
   ```

**Pros**: Easy macOS setup
**Cons**: Default port 8888 may conflict

---

### Method 4: Linux Apache/Nginx (Production)

**Best for**: Production deployment, VPS

#### Apache

```bash
# 1. Install Apache + PHP
sudo apt update
sudo apt install apache2 php php-sqlite3 libapache2-mod-php

# 2. Copy files to web root
sudo cp -r stage-idol-calendar /var/www/html/

# 3. Set permissions
sudo chown -R www-data:www-data /var/www/html/stage-idol-calendar
sudo chmod -R 755 /var/www/html/stage-idol-calendar

# 4. Restart Apache
sudo systemctl restart apache2
```

**Virtual Host Example** (`/etc/apache2/sites-available/calendar.conf`):
```apache
<VirtualHost *:80>
    ServerName calendar.example.com
    DocumentRoot /var/www/html/stage-idol-calendar

    <Directory /var/www/html/stage-idol-calendar>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/calendar-error.log
    CustomLog ${APACHE_LOG_DIR}/calendar-access.log combined
</VirtualHost>
```

Enable site:
```bash
sudo a2ensite calendar.conf
sudo systemctl reload apache2
```

#### Nginx

```bash
# 1. Install Nginx + PHP-FPM
sudo apt update
sudo apt install nginx php-fpm php-sqlite3

# 2. Copy files
sudo cp -r stage-idol-calendar /var/www/html/

# 3. Set permissions
sudo chown -R www-data:www-data /var/www/html/stage-idol-calendar
sudo chmod -R 755 /var/www/html/stage-idol-calendar
```

**Server Block Example** (`/etc/nginx/sites-available/calendar`):
```nginx
server {
    listen 80;
    server_name calendar.example.com;
    root /var/www/html/stage-idol-calendar;
    index index.php index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/calendar /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

**Pros**: Production-ready, scalable
**Cons**: More complex setup

---

## 📝 Creating ICS Files

### Basic ICS Structure

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Your Organization//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH

BEGIN:VEVENT
UID:unique-id-001@yourdomain.com
DTSTAMP:20260101T000000Z
DTSTART:20260207T100000Z
DTEND:20260207T110000Z
SUMMARY:Event Title
LOCATION:Venue Name
ORGANIZER;CN="Artist Name":mailto:artist@example.com
CATEGORIES:Artist Name
DESCRIPTION:Detailed event description
STATUS:CONFIRMED
SEQUENCE:0
END:VEVENT

END:VCALENDAR
```

### Field Descriptions

| Field | Required | Description | Example |
|-------|----------|-------------|---------|
| `UID` | ✅ Yes | Unique identifier | `event-001@myorg.com` |
| `DTSTART` | ✅ Yes | Start date/time (UTC) | `20260207T100000Z` |
| `DTEND` | ✅ Yes | End date/time (UTC) | `20260207T110000Z` |
| `SUMMARY` | ✅ Yes | Event title | `Concert Performance` |
| `LOCATION` | ⚠️ Recommended | Venue name | `Main Stage` |
| `ORGANIZER` | ⚠️ Recommended | Artist/performer | `CN="Band Name"` |
| `CATEGORIES` | ⚠️ Recommended | Artist name (for filtering) | `Band Name` |
| `DESCRIPTION` | ❌ Optional | Event details | `Special guest appearance` |
| `STATUS` | ❌ Optional | Event status | `CONFIRMED` |

### Date/Time Format

**Format**: `YYYYMMDDTHHMMSSZ`

**Components**:
- `YYYY`: Year (4 digits)
- `MM`: Month (01-12)
- `DD`: Day (01-31)
- `T`: Time separator
- `HH`: Hour (00-23, 24-hour format)
- `MM`: Minute (00-59)
- `SS`: Second (00-59)
- `Z`: UTC timezone indicator

**Examples**:
- `20260207T100000Z` = Feb 7, 2026, 10:00 AM UTC
- `20260315T143000Z` = Mar 15, 2026, 2:30 PM UTC
- `20261231T235959Z` = Dec 31, 2026, 11:59:59 PM UTC

### Multiple Events in One File

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Your Organization//EN

BEGIN:VEVENT
UID:event-001@myorg.com
DTSTART:20260207T100000Z
DTEND:20260207T110000Z
SUMMARY:Morning Show
LOCATION:Stage A
ORGANIZER;CN="Artist 1":mailto:artist1@example.com
END:VEVENT

BEGIN:VEVENT
UID:event-002@myorg.com
DTSTART:20260207T140000Z
DTEND:20260207T150000Z
SUMMARY:Afternoon Show
LOCATION:Stage B
ORGANIZER;CN="Artist 2":mailto:artist2@example.com
END:VEVENT

END:VCALENDAR
```

### File Encoding

**Important**: Always save ICS files as UTF-8 encoding.

**Line Endings**: Use CRLF (`\r\n`) for maximum compatibility.

---

## ⚙️ Configuration

### Cache Busting

Edit [config/app.php](config/app.php):
```php
define('APP_VERSION', '1.0.0'); // Change to force cache refresh
```

**When to change**:
- After updating CSS/JS files
- After changing theme colors
- After modifying translations

### Production Mode

Edit [config/app.php](config/app.php):
```php
define('PRODUCTION_MODE', true);  // Hide error details
```

Set to `false` during development for debugging.

### Data Version Cache

Edit [config/cache.php](config/cache.php):
```php
define('DATA_VERSION_CACHE_TTL', 600); // 10 minutes in seconds
```

Controls how often the "Last Updated" timestamp refreshes.

---

## ⚙️ Admin Panel Setup

### Step 1: Create Database Tables

```bash
cd tools

# Create request table
php migrate-add-requests-table.php

# Create credits table
php migrate-add-credits-table.php
```

This creates the `event_requests` and `credits` tables in the database.

### Step 2: Configure Admin Credentials

**Generate password hash first**:
```bash
php -r "echo password_hash('your_secure_password', PASSWORD_DEFAULT);"
```

Then edit [config/admin.php](config/admin.php):

```php
// Change these!
define('ADMIN_USERNAME', 'your_username');
define('ADMIN_PASSWORD_HASH', '$2y$10$...paste_generated_hash_here...');
```

⚠️ **Important**: Do NOT use `password_hash()` directly in the config file. Always generate the hash first and paste the static hash value.

### Step 3: Access Admin Panel

1. Navigate to `/admin/`
2. Login with configured credentials
3. Manage events and requests

### Admin Features

**Events Tab**:
- Create new events
- Edit existing events
- Delete events (single or bulk up to 100)
- Bulk edit (venue, organizer, categories)
- Search and filter
- Pagination (20/50/100 per page)

**Requests Tab**:
- View pending user requests
- Compare changes side-by-side
- Approve or reject requests

**Credits Tab**:
- Create, edit, delete credits/references
- Bulk delete multiple credits
- Search, sort by display order
- Pagination
- Pagination

**Requests Tab**:
- View pending user requests
- Compare original vs. requested changes
- Approve or reject requests
- Filter by status

---

## 📝 Request System Setup

The request system allows users to submit event additions/modifications for admin approval.

### Features

- User-friendly request form
- Pre-filled data for modifications
- Rate limiting (10 requests/hour/IP)
- Email collection for follow-up
- Admin approval workflow

### Configuration

Rate limit settings in [api/request.php](api/request.php):

```php
define('RATE_LIMIT_MAX', 10);      // Max requests
define('RATE_LIMIT_WINDOW', 3600); // Time window (seconds)
```

### Workflow

1. User submits request via form
2. System validates and stores request
3. Admin reviews in admin panel
4. Admin approves/rejects
5. If approved, event is created/updated automatically

---

## 🔒 Security Configuration

### IP Whitelist (Optional)

Restrict admin access to specific IP addresses.

Edit [config/admin.php](config/admin.php):

```php
define('ADMIN_IP_WHITELIST_ENABLED', true);

define('ADMIN_ALLOWED_IPS', [
    '127.0.0.1',           // Localhost
    '::1',                 // Localhost IPv6
    '192.168.1.100',       // Single IP
    '192.168.1.0/24',      // IP range (CIDR notation)
    '10.0.0.0/8',          // Large network
]);
```

**CIDR Examples**:
- `192.168.1.0/24` = 192.168.1.0 - 192.168.1.255
- `10.0.0.0/8` = 10.0.0.0 - 10.255.255.255

### Session Security

Configure session behavior in [config/admin.php](config/admin.php):

```php
/**
 * Session Timeout (in seconds)
 * Default: 7200 seconds (2 hours)
 */
define('SESSION_TIMEOUT', 7200);
```

**Security Features Enabled**:
- ✅ Session timeout with automatic logout after inactivity
- ✅ Timing attack prevention using `hash_equals()` for constant-time comparison
- ✅ Session fixation prevention via `session_regenerate_id()`
- ✅ Secure cookies with httponly, secure, SameSite=Strict attributes
- ✅ Race condition prevention with safe session start

### Security Headers

Headers are sent automatically via `send_security_headers()` function in [functions/security.php](functions/security.php).

Configured headers:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`

### File Permissions

**Recommended settings**:

```bash
# PHP files
chmod 644 *.php

# Database
chmod 600 calendar.db

# Directories
chmod 755 ics/ admin/ api/ tools/ styles/ js/

# Cache directory (writable)
chmod 755 cache/
```

**Production**:
```bash
# Make database read-only for web server
chown root:www-data calendar.db
chmod 640 calendar.db
```

---

## 🎨 Customization

### Theme Colors

Edit [styles/common.css](styles/common.css):

```css
:root {
    --sakura-light: #FFB7C5;
    --sakura-medium: #F48FB1;
    --sakura-dark: #E91E63;
    --sakura-deep: #C2185B;
    --sakura-gradient: linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%);
    --sakura-bg: #FFF0F3;
}
```

**Alternative Themes**:

**Blue/Purple**:
```css
:root {
    --sakura-light: #B8C5FF;
    --sakura-medium: #7B8CDE;
    --sakura-dark: #667eea;
    --sakura-deep: #5a67d8;
    --sakura-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --sakura-bg: #F0F4FF;
}
```

**Green/Teal**:
```css
:root {
    --sakura-light: #B8E0D2;
    --sakura-medium: #6BC5A0;
    --sakura-dark: #00B894;
    --sakura-deep: #009874;
    --sakura-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --sakura-bg: #F0FFF4;
}
```

### Fonts

Add Google Fonts in page `<head>`:

```html
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
```

Then update CSS:
```css
body {
    font-family: 'Kanit', 'Sarabun', sans-serif;
}
```

### Translations

Edit [js/translations.js](js/translations.js) to modify or add translations.

Structure:
```javascript
const translations = {
    en: { key: "English text" },
    th: { key: "ข้อความไทย" },
    ja: { key: "日本語テキスト" }
};
```

---

## 🔍 Troubleshooting

### Events Not Showing

**Problem**: Calendar displays but no events appear

**Solutions**:
1. Check `.ics` files exist in `ics/` folder
2. Verify file permissions allow PHP to read files
3. Run import script: `php tools/import-ics-to-sqlite.php`
4. Check browser console for JavaScript errors
5. Verify PHP SQLite extension: `php -m | grep pdo_sqlite`

### Database Errors

**Problem**: "unable to open database file"

**Solutions**:
1. Check `calendar.db` exists
2. Verify permissions: `chmod 644 calendar.db`
3. Ensure parent directory is writable
4. Re-run import script

**Problem**: "database is locked"

**Solutions**:
1. Close other connections to database
2. Check for hung PHP processes
3. Restart web server
4. In worst case: delete `calendar.db` and re-import

### Cache Issues

**Problem**: Changes not reflecting in browser

**Solutions**:
1. Change `APP_VERSION` in `config/app.php`
2. Hard refresh browser (Ctrl+F5 or Cmd+Shift+R)
3. Clear browser cache completely
4. If using CDN (Cloudflare), purge cache

### Image Export Fails

**Problem**: "Save as Image" button not working

**Solutions**:
1. Check internet connection (html2canvas loads from CDN)
2. Disable browser popup blocker
3. Check browser console for errors
4. Try different browser

### Admin Login Issues

**Problem**: Can't login to admin panel

**Solutions**:
1. Verify credentials in `config/admin.php`
2. Check if IP whitelist is enabled and your IP is allowed
3. Clear browser cookies
4. Check session directory permissions

### PHP Errors

**Enable error display** (development only):

Add to top of PHP files:
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**View error logs**:
```bash
# Apache
tail -f /var/log/apache2/error.log

# Nginx
tail -f /var/log/nginx/error.log

# PHP-FPM
tail -f /var/log/php7.4-fpm.log
```

---

## 🚀 Performance Optimization

### Enable OPcache

Edit `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### Database Optimization

```bash
# Compact database
sqlite3 calendar.db "VACUUM;"

# Analyze for query optimization
sqlite3 calendar.db "ANALYZE;"
```

### Apache .htaccess

Create `.htaccess` for caching:
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
</IfModule>

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript
</IfModule>
```

---

## 🧪 Testing & Quality Assurance

### Automated Test Suite

The project includes **172 automated unit tests** for quality assurance:

```bash
# Run all tests
php tests/run-tests.php

# Run specific test suite
php tests/run-tests.php SecurityTest
php tests/run-tests.php CacheTest
php tests/run-tests.php AdminAuthTest
php tests/run-tests.php CreditsApiTest
php tests/run-tests.php IntegrationTest
```

### Quick Pre-Commit Tests

```bash
# Windows
quick-test.bat

# Linux/Mac
chmod +x quick-test.sh
./quick-test.sh
```

### Test Coverage

- **SecurityTest** (15 tests) - XSS protection, input sanitization, SQL injection prevention
- **CacheTest** (11 tests) - Cache creation, invalidation, TTL behavior
- **AdminAuthTest** (15 tests) - Authentication, session management, password security
- **CreditsApiTest** (13 tests) - Database CRUD, bulk operations, validation
- **IntegrationTest** (118 tests) - Configuration validation, file structure, workflows, API endpoints

✅ **All 172 tests pass on PHP 8.1, 8.2, and 8.3**

### CI/CD Integration

GitHub Actions automatically runs tests on every push/PR:
- Tests run on **PHP 8.1, 8.2, and 8.3**
- Separate security and integration test jobs
- Automatic test result reporting
- All tests pass on all PHP versions

See [.github/workflows/tests.yml](.github/workflows/tests.yml)

### Manual Testing Checklist

For comprehensive manual testing scenarios, see [TESTING.md](TESTING.md) which includes:
- 129 manual test cases
- Security testing procedures
- Performance benchmarks
- Edge case scenarios
- Browser compatibility checks

### Pre-Production Checklist

Before deploying to production:

- [ ] Run full test suite: `php tests/run-tests.php`
- [ ] Verify all 172 tests pass
- [ ] Test on target PHP version (8.1, 8.2, or 8.3)
- [ ] Change admin credentials in `config/admin.php`
- [ ] Set `PRODUCTION_MODE` to `true` in `config/app.php`
- [ ] Update `APP_VERSION` for cache busting
- [ ] Enable IP whitelist if needed
- [ ] Backup database and ICS files

---

## 📚 Additional Resources

- **Quick Start**: [QUICKSTART.md](QUICKSTART.md)
- **Main Documentation**: [README.md](README.md)
- **Database Guide**: [SQLITE_MIGRATION.md](SQLITE_MIGRATION.md)
- **Version History**: [CHANGELOG.md](CHANGELOG.md)
- **Contributing**: [CONTRIBUTING.md](CONTRIBUTING.md)

---

**Need help?** Open an issue on [GitHub](https://github.com/yourusername/stage-idol-calendar/issues) or contact [@FordAntiTrust](https://x.com/FordAntiTrust).

Happy installing! 🎉
