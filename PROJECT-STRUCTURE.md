# 📁 Project Structure

โครงสร้างไฟล์และโฟลเดอร์ของ Idol Stage Timetable v2.0.2

---

## ภาพรวม

```
stage-idol-calendar/
│
├── 📄 Root PHP Pages
├── ⚙️  config/          Configuration constants
├── 🔧 functions/        Helper functions
├── 🎨 styles/           CSS
├── 📜 js/               JavaScript
├── 🗄️  data/             SQLite database
├── 💾 backups/          Database backups
├── 🗂️  ics/              ICS source files
├── 📦 cache/            Cache files
├── 🔌 api/              Public API endpoints
├── 🔐 admin/            Admin panel
├── 🛠️  tools/            CLI migration tools
├── 🧪 tests/            Automated test suite
└── 📚 docs/*.md         Documentation
```

---

## Root PHP Pages

| ไฟล์ | หน้าที่ |
|------|--------|
| `index.php` | หน้าหลัก — แสดงตาราง programs (List + Gantt view) |
| `how-to-use.php` | วิธีใช้งาน (3 ภาษา: TH/EN/JA) |
| `contact.php` | หน้าติดต่อ (3 ภาษา) |
| `credits.php` | Credits & References (โหลดจาก DB + cache) |
| `export.php` | Export ICS handler — ดาวน์โหลด .ics จาก filtered programs |
| `api.php` | Public API endpoint (programs, organizers, locations, events_list) |
| `setup.php` | Setup Wizard — fresh install & maintenance (5 ขั้นตอน) |
| `config.php` | Bootstrap — โหลด config/ + functions/ ทั้งหมด |
| `IcsParser.php` | ICS Parser class — parse .ics files → SQLite |
| `.htaccess` | Apache clean URL rewrite rules (ลบ .php extension) |
| `nginx-clean-url.conf` | Nginx clean URL config example |

---

## ⚙️ config/

ค่าคงที่สำหรับ configuration ทั้งระบบ โหลดผ่าน `config.php`

| ไฟล์ | Define constants | หน้าที่ |
|------|-----------------|--------|
| `app.php` | `APP_VERSION`, `PRODUCTION_MODE`, `VENUE_MODE`, `MULTI_EVENT_MODE`, `DEFAULT_EVENT_SLUG` | App settings + cache busting |
| `admin.php` | `ADMIN_USERNAME`, `ADMIN_PASSWORD_HASH`, `SESSION_TIMEOUT`, `ADMIN_IP_WHITELIST_ENABLED`, `ADMIN_ALLOWED_IPS` | Admin auth fallback + IP whitelist |
| `security.php` | Security rate limiting constants | Rate limiting config |
| `database.php` | `DB_PATH` (`data/calendar.db`) | Database file path |
| `cache.php` | `DATA_VERSION_CACHE_TTL` (600s), `CREDITS_CACHE_TTL` (3600s) | Cache TTL settings |

---

## 🔧 functions/

Helper functions โหลดผ่าน `config.php`

| ไฟล์ | Functions หลัก | หน้าที่ |
|------|--------------|--------|
| `helpers.php` | `get_db()`, `get_event_by_slug()`, `get_event_id()`, `get_all_active_events()`, `get_event_venue_mode()`, `event_url()` | General utilities + DB singleton + multi-event helpers |
| `cache.php` | `get_data_version()`, `get_cached_credits()`, `invalidate_data_version_cache()`, `invalidate_credits_cache()`, `invalidate_all_caches()` | Cache read/write/invalidate |
| `admin.php` | `admin_login()`, `safe_session_start()`, `check_admin_session()`, `admin_logout()`, `get_admin_role()`, `is_admin_role()`, `require_admin_role()`, `check_login_rate_limit()`, `record_failed_login()`, `clear_login_attempts()` | Auth + session + RBAC + rate limiting |
| `security.php` | `sanitize_string()`, `sanitize_string_array()`, `get_sanitized_param()`, `send_security_headers()`, `check_ip_whitelist()`, `generate_csrf_token()`, `validate_csrf_token()` | XSS, CSRF, headers, IP whitelist |

---

## 🗄️ data/

| ไฟล์ | หน้าที่ |
|------|--------|
| `calendar.db` | SQLite database หลัก |
| `.setup_locked` | Lock file สำหรับ setup.php (มีอยู่ = locked) |

> **Security**: โฟลเดอร์ `data/` ถูกป้องกันโดย `.htaccess` ไม่ให้เข้าถึงจาก web browser

---

## 💾 backups/

สร้างอัตโนมัติโดย Admin Panel (Backup tab)

| ไฟล์ | หน้าที่ |
|------|--------|
| `backup_YYYYMMDD_HHMMSS.db` | Database backup files |

---

## 📦 cache/

สร้างอัตโนมัติโดยระบบ

| ไฟล์ | หน้าที่ | TTL |
|------|--------|-----|
| `data_version.json` | Last data update timestamp (footer display) | 10 นาที |
| `credits.json` | Credits data cache | 1 ชั่วโมง |
| `login_attempts.json` | Login rate limiting data | 15 นาที |

---

## 🔌 api/

Public API endpoints — ไม่ต้อง login

| ไฟล์ | หน้าที่ |
|------|--------|
| `request.php` | User request submission (submit + programs listing) |

ดู [API.md](API.md) สำหรับ endpoint documentation ครบถ้วน

---

## 🔐 admin/

Admin panel — ต้อง login

| ไฟล์ | หน้าที่ |
|------|--------|
| `login.php` | Login page (rate limited: 5 attempts/15 min/IP) |
| `index.php` | Admin dashboard — Tabs: Programs, Requests, Credits, Events, Users, Backup |
| `api.php` | CRUD API endpoints ทั้งหมด (ต้อง session + CSRF token) |

ดู [API.md](API.md) สำหรับ admin endpoint documentation

---

## 🛠️ tools/

CLI scripts สำหรับ developer — รันผ่าน `php tools/script.php`

| ไฟล์ | หน้าที่ | Idempotent |
|------|--------|-----------|
| `import-ics-to-sqlite.php` | Import .ics files → `programs` table | ✅ (INSERT OR REPLACE) |
| `update-ics-categories.php` | เพิ่ม CATEGORIES field ใน .ics | - |
| `migrate-add-requests-table.php` | สร้างตาราง `program_requests` | ✅ |
| `migrate-add-credits-table.php` | สร้างตาราง `credits` | ✅ |
| `migrate-add-events-meta-table.php` | สร้างตาราง `events` (meta) | ✅ |
| `migrate-add-admin-users-table.php` | สร้างตาราง `admin_users` + seed จาก config | ✅ |
| `migrate-add-role-column.php` | เพิ่ม `role` column ใน `admin_users` | ✅ |
| `migrate-rename-tables-columns.php` | Rename tables/columns เป็น v2.0.0 schema | ✅ |
| `migrate-add-indexes.php` | เพิ่ม 7 performance indexes | ✅ |
| `generate-password-hash.php` | สร้าง bcrypt password hash |  |
| `debug-parse.php` | Debug ICS file parsing | |
| `test-parse.php` | ทดสอบ ICS parser | |

> **หมายเหตุ**: สำหรับ fresh install ให้ใช้ [Setup Wizard](SETUP.md) แทนการรัน tools ทีละตัว

---

## 🧪 tests/

Automated test suite — 324 tests, PHP 8.1/8.2/8.3

| ไฟล์ | Tests | Coverage |
|------|-------|---------|
| `TestRunner.php` | — | Lightweight test framework (20 assertion methods) |
| `run-tests.php` | — | Main runner + colored output + suite selector |
| `SecurityTest.php` | 7 | XSS, null bytes, input sanitization, safe errors |
| `CacheTest.php` | 17 | Cache TTL, hit/miss, invalidation, fallback on error |
| `AdminAuthTest.php` | 38 | Session, login, timing attack resistance, DB auth |
| `CreditsApiTest.php` | 49 | Credits CRUD, bulk delete, SQL injection prevention |
| `IntegrationTest.php` | 97 | Config, file structure, workflows, API, multi-event |
| `UserManagementTest.php` | 116 | Role schema, RBAC helpers, user CRUD, permission guards |

```bash
# Run all 324 tests
php tests/run-tests.php

# Run specific suite
php tests/run-tests.php SecurityTest
php tests/run-tests.php UserManagementTest::testRoleColumn
```

---

## 🐳 Docker

| ไฟล์ | หน้าที่ |
|------|--------|
| `Dockerfile` | PHP 8.1-apache + PDO SQLite |
| `docker-compose.yml` | Production (port 8000, volumes) |
| `docker-compose.dev.yml` | Development (live reload, error display) |
| `.dockerignore` | ลดขนาด Docker image |

---

## 📚 Documentation

| ไฟล์ | หน้าที่ |
|------|--------|
| `README.md` | ภาพรวมโปรเจค + Quick Start |
| `SETUP.md` | Setup Wizard guide (fresh install) |
| `QUICKSTART.md` | เริ่มต้นเร็ว 3 ขั้นตอน |
| `INSTALLATION.md` | คู่มือติดตั้งโดยละเอียด (Apache/Nginx/XAMPP/Docker) |
| `API.md` | API Endpoint documentation ครบถ้วน |
| `PROJECT-STRUCTURE.md` | โครงสร้างไฟล์ (ไฟล์นี้) |
| `SQLITE_MIGRATION.md` | Database schema + migration guide |
| `DOCKER.md` | Docker deployment guide |
| `TESTING.md` | Manual testing checklist (129 cases) |
| `CHANGELOG.md` | Version history |
| `CONTRIBUTING.md` | Contribution guidelines |
| `SECURITY.md` | Security policy |

---

## 🔗 ความสัมพันธ์ระหว่างไฟล์

```
config.php (bootstrap)
    ├── config/app.php          → APP_VERSION, VENUE_MODE, MULTI_EVENT_MODE
    ├── config/admin.php        → Auth fallback, IP whitelist, SESSION_TIMEOUT
    ├── config/security.php     → Rate limiting
    ├── config/database.php     → DB_PATH
    ├── config/cache.php        → Cache TTL constants
    ├── functions/helpers.php   → get_db(), event helpers
    ├── functions/cache.php     → Cache read/write
    ├── functions/admin.php     → Auth + RBAC + rate limiting
    └── functions/security.php  → Sanitize, CSRF, headers

index.php
    ├── config.php              → bootstrap
    ├── IcsParser.php           → ถ้าไม่มี DB (fallback mode)
    └── data/calendar.db        → ผ่าน get_db() หรือ IcsParser

admin/api.php
    ├── config.php              → bootstrap
    ├── data/calendar.db        → $db global (PDO)
    └── cache/                  → invalidate after writes

admin/index.php
    └── admin/api.php           → fetch via JS (AJAX)

api.php (public)
    ├── config.php              → bootstrap
    └── data/calendar.db        → queries with ETag caching

setup.php
    ├── config.php              → bootstrap (for constants)
    ├── data/calendar.db        → init + write
    └── data/.setup_locked      → lock/unlock
```

---

*Idol Stage Timetable v2.0.2*
