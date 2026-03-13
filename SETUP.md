# Setup & Installation Guide

A step-by-step guide for installing Idol Stage Timetable using the Setup Wizard (`setup.php`).

---

## Overview

`setup.php` is the interactive Setup Wizard for:
- **Fresh Install** — Install the system for the first time, completing all 6 steps in a single page (including Production Cleanup)
- **Maintenance** — Check system status, import additional data, or change the admin password

---

## Accessing the Setup Wizard

Open `https://your-domain.com/setup.php` (or `http://localhost:8000/setup.php`)

### Auth Gate

| System State | Access |
|-------------|--------|
| Fresh install (no `admin_users` table yet) | Accessible without login |
| Existing install (admin users exist) | Must login as admin first |
| Setup is Locked | Shows locked message, access denied |

---

## 6-Step Setup Process

### Step 1 — System Requirements

Verifies that the system is ready to run:

| Check | Required |
|-------|----------|
| PHP Version | 8.1 or higher |
| Extension: PDO | ✅ Must be installed |
| Extension: PDO_SQLite | ✅ Must be installed |
| Extension: mbstring | ✅ Must be installed |
| Write permissions | `data/`, `cache/`, `backups/` must be writable |

If any item shows ❌, fix the PHP configuration before proceeding.

---

### Step 2 — Directories & Permissions

Checks and creates the required folders:

| Directory | Purpose |
|-----------|---------|
| `data/` | Stores the SQLite database (`calendar.db`) |
| `cache/` | Stores cache files (data version, credits, login attempts) |
| `backups/` | Stores database backup files |
| `ics/` | Stores `.ics` files for import |

Click **"Create Directories"** to automatically create any missing folders.

> **Note**: If you encounter permission issues on Linux/Mac, run:
> ```bash
> chmod 755 data/ cache/ backups/ ics/
> ```

---

### Step 3 — Database Setup

Creates all SQLite tables and seeds initial data.

Click **"Initialize Database"** to:
1. Create the `data/calendar.db` file
2. Create all required tables:
   - `programs` — Individual show/performance records
   - `events` — Event/convention metadata
   - `program_requests` — User-submitted add/edit requests
   - `credits` — Credits and references
   - `admin_users` — Admin user accounts
3. Seed the default admin user (from `config/admin.php`)
4. Seed a default event (slug: `default`) and **3 sample programs** so you can see the real layout immediately:
   - Opening Ceremony, Artist Performance, Closing Stage (dated today)
   - These can be edited or deleted later from Admin › Programs
5. **Auto-login** — The session will be logged in automatically after a successful initialization

**Default Credentials:**

After a successful initialization, the system will display a credentials box:

```
Username: admin
Password: admin123
```

> ⚠️ **Important**: Note down the credentials before leaving this page, then change the password immediately in Step 5 and clean up dev files in Step 6.

---

### Step 4 — Import Programs Data

Imports data from `.ics` files into the database.

**How to use:**

1. Place `.ics` files in the `ics/` folder
2. Click **"Import ICS Files"**
3. The system will parse and import all events
4. The number of successfully imported programs will be displayed

**If you have no `.ics` files:**

You can skip this step and add data later through the Admin Panel (`/admin`).

**Import via command line** (alternative):

```bash
php tools/import-ics-to-sqlite.php
# Or specify an event slug:
php tools/import-ics-to-sqlite.php --event=my-event-slug
```

---

### Step 5 — Admin & Security Setup

#### 5.1 Change Admin Password

If the default password is still in use, the system will display an inline password change form:

1. Enter **New Password** (minimum 8 characters)
2. Enter **Confirm Password** (must match)
3. Click **"Save New Password"**
4. The warning box will disappear after a successful change

> After changing the password, test your login at `/admin/login` before locking setup.

#### 5.2 Add Database Indexes (Recommended)

Click **"Add Indexes"** to add performance indexes to SQLite:
- Speeds up frequent queries by 2–5x
- Idempotent — safe to run multiple times

#### 5.3 Lock Setup Page

Once setup is complete, lock the page immediately:

Click **"🔒 Lock Setup"** → Creates the `data/.setup_locked` file.

After locking, no one can access the setup page.

**Unlocking** (for future maintenance):
1. Delete the `data/.setup_locked` file (via SSH/FTP), or
2. Log in as admin, open setup again → click **"🔓 Unlock"**

---

### Step 6 — Production Cleanup

Removes development and documentation files that are not needed in production.

Files are grouped by category with checkboxes:

| Group | Files Included |
|-------|---------------|
| **Docs** | `*.md` documentation files |
| **Tests** | `tests/` directory and test scripts |
| **Tools** | `tools/` directory and migration scripts |
| **Docker** | `Dockerfile`, `docker-compose*.yml`, `.dockerignore` |
| **Nginx** | `nginx-*.conf` configuration files |
| **CI/CD** | `.github/` workflows directory |

Select the groups you want to remove and click **"Delete Selected Files"**.

> ⚠️ This action is irreversible. Only remove files you are certain are not needed.

---

## Fresh Install Flow

```
Open setup.php
    ↓
[Step 1] Check PHP requirements ✅
    ↓
[Step 2] Create Directories ✅
    ↓
[Step 3] Initialize Database → Auto-login → Note credentials
    ↓
[Step 4] Import .ics files (if available)
    ↓
[Step 5] Change password → Add indexes → Lock setup
    ↓
[Step 6] Production Cleanup — Remove dev/docs files
    ↓
Access /admin ✅
```

---

## Troubleshooting

### Setup page not loading / redirects to login
- Admin users already exist in the DB → must login as admin first
- Go to `/admin/login`, login, then return to `/setup.php`

### "Setup is Locked"
- The file `data/.setup_locked` exists
- Login as admin and open `setup.php` → click Unlock, or delete the lock file via SSH/FTP

### Initialize Database fails
- Verify that the `data/` folder exists and is writable
- Check that PHP has the `pdo_sqlite` extension enabled

### Import ICS returns no data
- Verify that `.ics` files are present in the `ics/` directory
- Check the file format — see [README.md](README.md) for the expected ICS format

### Password warning box does not disappear after changing
- Verify the password meets requirements (minimum 8 characters, both fields must match)
- The page will reload automatically after a successful change and the warning will disappear

---

## Security Notes

- **Always lock setup** after completing installation — `setup.php` has the power to initialize or override the database
- **Change the default password** before going live in production
- For maintenance, unlock through an admin session rather than deleting the lock file directly on a production server

---

## Related Files

| File | Purpose |
|------|---------|
| `setup.php` | Main Setup Wizard |
| `data/.setup_locked` | Lock file (if present = locked) |
| `data/calendar.db` | SQLite database |
| `config/admin.php` | Admin credentials (fallback) |
| `config/app.php` | App version and settings |
| `tools/import-ics-to-sqlite.php` | CLI import tool |
| `tools/migrate-add-indexes.php` | DB performance indexes |
| `tools/migrate-add-event-email-column.php` | Adds email column to events table |
| `tools/migrate-add-program-type-column.php` | Adds program_type column to programs table |

---

*Idol Stage Timetable v2.10.0*
