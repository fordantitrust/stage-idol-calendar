# 🚀 Quick Start Guide - Idol Stage Timetable

Get up and running in 3 minutes!

---

## ⚡ 3-Step Setup

### 🐳 Method 1: Docker (Recommended)

```bash
# 1. Navigate to project folder
cd stage-idol-calendar

# 2. Start with Docker Compose
docker-compose up -d

# 3. Open browser
# http://localhost:8000
```

**That's it!** 🎉 See [DOCKER.md](DOCKER.md) for more options.

---

### 💻 Method 2: PHP Built-in Server

```bash
# 1. Navigate to project folder
cd stage-idol-calendar

# 2. Start PHP built-in server
php -S localhost:8000

# 3. Open browser
# http://localhost:8000
```

### 📝 Adding Your Events

- Place `.ics` files in the `ics/` folder
- Refresh the page

---

## 📊 For Better Performance (Recommended)

Import ICS files to SQLite database for 10-20x faster performance:

```bash
cd tools
php import-ics-to-sqlite.php
cd ..
```

---

## 📝 Creating Your First Event

Create a file named `my-events.ics` in the `ics/` folder:

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//My Organization//EN

BEGIN:VEVENT
UID:event-001@myorg.com
DTSTART:20260301T100000Z
DTEND:20260301T110000Z
SUMMARY:My First Event
LOCATION:Main Stage
ORGANIZER;CN="Artist Name":mailto:artist@example.com
CATEGORIES:Artist Name
DESCRIPTION:This is my first event!
STATUS:CONFIRMED
END:VEVENT

END:VCALENDAR
```

**Date Format**: `YYYYMMDDTHHMMSSZ` (ISO 8601 in UTC)
- Example: `20260301T100000Z` = March 1, 2026 at 10:00 AM UTC

---

## 🎯 Core Features

| Feature | How to Use |
|---------|-----------|
| 🔍 **Search** | Type artist/event name in search box |
| 🏷️ **Filter by Artist** | Check artist checkboxes |
| 🏢 **Filter by Venue** | Check venue checkboxes |
| 📊 **Switch Views** | Toggle between List / Gantt Chart |
| 📸 **Save Image** | Click "Save as Image" button |
| 📅 **Export Calendar** | Click "Export to Calendar" button |
| 📝 **Request Changes** | Click "Request to Add Event" or ✏️ edit button |

---

## ⚙️ Admin Setup (Optional)

### Enable Admin Panel

#### Option A: Setup Wizard (Recommended) 🧙

Open `http://localhost:8000/setup.php` and follow the 5-step wizard — creates all tables, seeds admin user, lets you change password, then locks the setup page.

See [SETUP.md](SETUP.md) for detailed guide.

#### Option B: Manual CLI

```bash
cd tools
php import-ics-to-sqlite.php
php migrate-add-requests-table.php
php migrate-add-credits-table.php
php migrate-add-events-meta-table.php
php migrate-add-admin-users-table.php
php migrate-add-role-column.php
php migrate-rename-tables-columns.php
php migrate-add-indexes.php
```

### Access Admin

```
http://localhost:8000/admin/
```

**Default Credentials**: Created via setup.php wizard or set in `config/admin.php`

### What Can Admin Do?

- ✅ Create, edit, delete events (with bulk operations)
- ✅ Upload and import ICS files (with preview and duplicate detection)
- ✅ Review and approve user requests
- ✅ Compare original vs. requested changes
- ✅ Manage credits and references
- ✅ Manage multiple events (multi-event support)
- ✅ Bulk select and delete/edit up to 100 items at once
- ✅ Customizable pagination (20/50/100 per page)
- ✅ CSRF protection and IP whitelist for security

---

## 📁 Project Structure

```
stage-idol-calendar/
├── ics/                   # 👈 Place your .ics files here
├── index.php              # Main calendar page
├── credits.php            # Credits & References page
├── admin/                 # Admin panel (Events + Requests + Credits)
├── tools/                 # Import scripts & migrations
├── cache/                 # Cache files (auto-created)
├── config/                # Configuration files
│   ├── app.php            # App settings & version
│   ├── admin.php          # Admin credentials
│   ├── cache.php          # Cache settings (TTL)
│   └── ...                # Other configs
└── functions/             # Helper functions
    └── cache.php          # Cache functions
```

---

## 💡 Pro Tips

1. **Multiple ICS Files**: You can have as many `.ics` files as you want. The system combines them all.

2. **File Names Don't Matter**: `event1.ics`, `concert.ics`, or `xyz.ics` - all work the same.

3. **Cache Busting**: If changes don't show up, edit `APP_VERSION` in `config/app.php`.

4. **Performance**: Use SQLite for better performance with large datasets.

5. **Backup**: Keep your `.ics` files - they're your data source!

6. **Testing**: Run automated tests before deploying:
   ```bash
   php tests/run-tests.php
   # Or quick tests: ./quick-test.sh (Linux/Mac) or quick-test.bat (Windows)
   ```

---

## 🧪 Testing (Optional)

### Quick Tests

```bash
# Windows
quick-test.bat

# Linux/Mac
./quick-test.sh
```

### Full Test Suite

```bash
php tests/run-tests.php
```

**324 automated tests** covering:
- Security (XSS, SQL injection, input sanitization)
- Cache system (TTL, invalidation)
- Authentication (session, timing attacks)
- Database operations (CRUD, bulk operations)
- Integration (configuration, workflows, API endpoints)
- User management & role-based access control

✅ **All 324 tests pass on PHP 8.1, 8.2, and 8.3**

See [tests/README.md](tests/README.md) for details.

---

## 🔧 Quick Troubleshooting

| Problem | Solution |
|---------|----------|
| Events not showing | Check files are in `ics/` folder with `.ics` extension |
| Changes not updating | Change `APP_VERSION` in `config/app.php` |
| Database errors | Run `php tools/import-ics-to-sqlite.php` |
| Admin can't login | Run setup.php wizard or check `config/admin.php` |

---

## 📚 Need More Help?

- **Full Guide**: [README.md](README.md)
- **Setup Wizard**: [SETUP.md](SETUP.md)
- **Installation**: [INSTALLATION.md](INSTALLATION.md)
- **Database**: [SQLITE_MIGRATION.md](SQLITE_MIGRATION.md)
- **Changes**: [CHANGELOG.md](CHANGELOG.md)

---

## 🎨 Next Steps

1. **Customize Theme**: Edit colors in `styles/common.css`
2. **Add Translations**: Modify `js/translations.js`
3. **Configure Security**: Set up IP whitelist in `config/admin.php`
4. **Deploy**: See [INSTALLATION.md](INSTALLATION.md) for production deployment

---

## 🌸 Example Use Cases

- 🎭 **Idol Events**: Manage performance schedules across multiple stages
- 🎪 **Festivals**: Track lineup across different venues and times
- 🎓 **Conventions**: Schedule panels and activities
- 🎵 **Concerts**: Multi-artist event management
- 🎬 **Film Festivals**: Screening schedules

---

**Happy Scheduling!** 🎉

[⭐ Star on GitHub](https://github.com/yourusername/stage-idol-calendar) | [🐛 Report Issues](https://github.com/yourusername/stage-idol-calendar/issues) | [📖 Full Docs](README.md)
