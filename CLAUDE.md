# 🌸 Idol Stage Timetable - Idol Stage Event

ระบบปฏิทินกิจกรรม Idol Stage สำหรับงาน Idol Stage Event

**Theme**: Sakura (桜) - ธีมดอกซากุระสไตล์ญี่ปุ่น

## ✨ คุณสมบัติ

### สำหรับผู้ใช้งาน
- 🌸 **Sakura Theme** - ธีมสีชมพูซากุระสไตล์ญี่ปุ่น
- 🌏 **3 ภาษา** - ไทย, English, 日本語 (พร้อม html lang attribute)
- 📱 **Responsive Design** - รองรับทุกขนาดหน้าจอ รวมถึง iOS
- 📊 **Dual View Modes** - สลับมุมมอง List / Gantt Chart Timeline
- 🔍 **กรองข้อมูล** - ตามศิลปิน/วง, เวที และประเภท program (รองรับหลายค่า)
- 📸 **บันทึกเป็นรูปภาพ** - Lazy-load html2canvas (PNG)
- 📅 **Export ICS** - ส่งออกเป็นไฟล์ปฏิทิน (Google Calendar / Apple Calendar)
- 📝 **Request System** - ผู้ใช้แจ้งเพิ่ม/แก้ไข event ได้ + Admin อนุมัติ
- 🎪 **Multi-Event Support** - รองรับหลาย conventions/events ในระบบเดียว เลือกผ่าน URL หรือ dropdown

### สำหรับ Admin
- 🛠️ **Setup Wizard** - (`setup.php`) ติดตั้งระบบ fresh install + maintenance แบบ 6 ขั้นตอน + Production Cleanup + lock/unlock
- ⚙️ **Admin UI** - จัดการ events, requests, credits และ conventions ผ่านหน้าเว็บ (CRUD)
- 🎪 **Events Management** - Tab "Events" สำหรับจัดการหลายงาน (CRUD)
- 🔐 **Database Auth** - Admin credentials ใน SQLite รองรับหลาย users + เปลี่ยนรหัสผ่านผ่าน UI
- 💾 **Backup/Restore** - สำรอง/กู้คืนฐานข้อมูลผ่าน Admin UI พร้อม auto-backup ก่อน restore
- 📦 **Bulk Operations** - เลือกหลาย events/credits แล้วลบหรือแก้ไขพร้อมกัน (สูงสุด 100)
- 📤 **ICS Upload** - อัพโหลดไฟล์ .ics พร้อม preview ก่อน import
- 🎯 **Flexible Venue Entry** - พิมพ์เวทีใหม่ได้ พร้อม autocomplete
- 📊 **Customizable Pagination** - เลือกแสดง 20/50/100 รายการต่อหน้า
- 📋 **Credits Management** - จัดการ credits/references ผ่าน admin panel
- 📝 **Request Management** - ดู/อนุมัติ/ปฏิเสธ คำขอจากผู้ใช้
- 👤 **User Management** - จัดการ admin users (CRUD) พร้อม role-based access control
- 🛡️ **Role-Based Access** - 2 roles: admin (full access) / agent (programs management only)

### เทคนิค
- ⚡ **SQLite Database** - ประสิทธิภาพสูง
- 🎪 **Multi-Event Architecture** - ตาราง `events` (meta) + `event_id` FK ใน programs, program_requests, credits
- 🔄 **Cache System** - Cache สำหรับ data version และ credits แยกตาม convention
- 🏟️ **Venue Mode** - สลับโหมด multi/single venue แยกตาม convention ได้
- 🔒 **Security** - XSS protection, CSRF tokens, rate limiting, IP whitelist, security headers
- 🐳 **Docker Support** - Deploy ด้วย Docker Compose คำสั่งเดียว
- 🔗 **Clean URLs** - ลบ .php extension จาก public URLs พร้อม .htaccess และ Nginx config
- 📅 **Date Jump Bar** - แถบกระโดดไปวันที่ต้องการ (fixed-position, IntersectionObserver)
- 🧪 **999 Automated Tests** - ผ่านทั้งหมดบน PHP 8.1, 8.2, 8.3

## 🚀 การติดตั้ง

### ความต้องการ
- **Docker** (แนะนำ) หรือ
- **PHP 8.1+** (ทดสอบบน PHP 8.1, 8.2, 8.3) พร้อม PDO SQLite และ mbstring extension
- **Web Server** (Apache, Nginx, หรือ PHP Built-in Server)

### 🐳 วิธีที่ 1: Docker (แนะนำ)

```bash
# 1. Clone repository
cd stage-idol-calendar

# 2. วางไฟล์ ICS ในโฟลเดอร์ ics/
cp your-events.ics ics/

# 3. Start ด้วย Docker Compose
docker-compose up -d

# 4. เปิดเว็บไซต์
# http://localhost:8000
```

**เท่านี้ก็เรียบร้อย!** 🎉 ดูคู่มือเพิ่มเติมที่ [DOCKER.md](DOCKER.md)

---

### 💻 วิธีที่ 2: PHP Built-in Server

1. **วางไฟล์** ในโฟลเดอร์เว็บไซต์

2. **วางไฟล์ ICS** ในโฟลเดอร์ `ics/`

3. **Import ข้อมูล** (แนะนำ):
   ```bash
   cd tools
   php import-ics-to-sqlite.php
   ```

4. **เปิดเว็บไซต์**:
   ```bash
   php -S localhost:8000
   ```
   เปิด `http://localhost:8000`

## 📁 โครงสร้างไฟล์

```
stage-idol-calendar/
├── index.php              # หน้าหลัก
├── how-to-use.php         # วิธีใช้งาน
├── contact.php            # ติดต่อเรา
├── credits.php            # หน้า Credits & References
├── export.php             # Export ICS
├── api.php                # Public API endpoint
├── config.php             # Bootstrap file (โหลด config/ และ functions/)
├── IcsParser.php          # ICS Parser class
├── .htaccess              # Apache clean URL rewrite rules
├── nginx-clean-url.conf   # Nginx clean URL config example
│
├── config/                # Configuration constants
│   ├── app.php            # Application settings & version
│   ├── admin.php          # Admin & authentication
│   ├── security.php       # Security & rate limiting
│   ├── database.php       # Database configuration
│   └── cache.php          # Cache settings (data version + credits)
│
├── functions/             # Helper functions
│   ├── helpers.php        # General utilities
│   ├── cache.php          # Cache functions (get_data_version, get_cached_credits, etc.)
│   ├── admin.php          # Auth functions (login, session, CSRF, role-based access)
│   └── security.php       # Security functions (sanitize, headers, IP whitelist)
│
├── data/                  # Database storage
│   └── calendar.db        # SQLite database
│
├── backups/               # Backup storage (auto-created)
│   └── backup_*.db        # Backup files
│
├── cache/                 # Cache storage (auto-created)
│   ├── data_version.json  # Data version cache
│   └── credits.json       # Credits cache
│
├── styles/                # Shared CSS
│   └── common.css         # Sakura theme styles
│
├── js/                    # Shared JavaScript
│   ├── translations.js    # ข้อความ 3 ภาษา
│   └── common.js          # ฟังก์ชันกลาง
│
├── ics/                   # ไฟล์ ICS data
│   └── *.ics
│
├── api/                   # Public APIs
│   └── request.php        # Request to Add/Modify API
│
├── admin/                 # Admin UI (login required)
│   ├── index.php          # Admin dashboard (Events + Requests + Credits + Conventions + Users + Backup)
│   ├── api.php            # CRUD API endpoints (events + requests + credits + conventions + users + ICS upload + backup)
│   └── login.php          # Login page
│
├── tests/                 # Automated test suite
│   ├── TestRunner.php     # Lightweight test framework (20 assertion methods)
│   ├── run-tests.php      # Test runner with colored output
│   ├── SecurityTest.php   # Security tests (7 tests)
│   ├── CacheTest.php      # Cache tests (17 tests)
│   ├── AdminAuthTest.php  # Auth tests (38 tests)
│   ├── CreditsApiTest.php # Credits API tests (49 tests)
│   ├── IntegrationTest.php # Integration tests (97 tests)
│   ├── UserManagementTest.php # User management & role tests (116 tests)
│   ├── ThemeTest.php      # Theme system tests (24 tests)
│   └── SiteSettingsTest.php # Site title settings tests (14 tests)
│
├── tools/                 # Development tools
│   ├── import-ics-to-sqlite.php
│   ├── update-ics-categories.php
│   ├── migrate-add-requests-table.php
│   ├── migrate-add-credits-table.php
│   ├── migrate-add-events-meta-table.php
│   ├── migrate-add-admin-users-table.php
│   ├── migrate-add-role-column.php
│   ├── migrate-rename-tables-columns.php
│   ├── migrate-add-indexes.php
│   ├── migrate-add-event-email-column.php
│   ├── generate-password-hash.php
│   ├── debug-parse.php
│   └── test-parse.php
│
├── Dockerfile             # Docker image configuration
├── docker-compose.yml     # Production Docker Compose
├── docker-compose.dev.yml # Development Docker Compose
├── .dockerignore          # Docker build exclusions
│
├── README.md              # เอกสารหลัก (English)
├── QUICKSTART.md          # คู่มือเริ่มต้นเร็ว
├── INSTALLATION.md        # คู่มือการติดตั้งโดยละเอียด
├── DOCKER.md              # คู่มือ Docker
├── CHANGELOG.md           # ประวัติการเปลี่ยนแปลง
├── TESTING.md             # Manual testing checklist (129 test cases)
├── SQLITE_MIGRATION.md    # คู่มือ migration database
├── SECURITY.md            # นโยบายความปลอดภัย
├── CONTRIBUTING.md        # แนวทางการมีส่วนร่วม
└── .github/workflows/     # CI/CD
    └── tests.yml          # GitHub Actions test pipeline
```

## 🎨 การปรับแต่ง

### เปลี่ยน Version (Cache Busting)
แก้ไขในไฟล์ `config/app.php`:
```php
define('APP_VERSION', '2.4.3'); // เปลี่ยนเลขนี้เพื่อ force cache refresh
```

### Multi-Event Mode (โหมดหลาย Convention)
แก้ไขในไฟล์ `config/app.php`:
```php
define('MULTI_EVENT_MODE', true);      // เปิดใช้งานระบบหลาย conventions
define('DEFAULT_EVENT_SLUG', 'default'); // slug ของ convention เริ่มต้น
```

เข้าถึง convention ผ่าน URL: `/event/slug` เช่น `/event/idol-stage-feb-2026`

### Venue Mode (โหมดเวที)
แก้ไขในไฟล์ `config/app.php`:
```php
define('VENUE_MODE', 'multi');   // หลายเวที - แสดง venue filter, Gantt view, คอลัมน์เวที
define('VENUE_MODE', 'single');  // เวทีเดียว - ซ่อน venue filter, Gantt view, คอลัมน์เวที
```

| Feature | multi | single |
|---------|-------|--------|
| Venue filter (checkbox กรองเวที) | แสดง | ซ่อน |
| Toggle สลับ List/Timeline view | แสดง | ซ่อน |
| คอลัมน์เวทีในตาราง events | แสดง | ซ่อน |
| คอลัมน์เวทีใน admin table | แสดง | ซ่อน |

### ธีมสี (Sakura)
สีหลักอยู่ใน `styles/common.css`:
```css
:root {
    --sakura-light: #FFB7C5;
    --sakura-medium: #F48FB1;
    --sakura-dark: #E91E63;
    --sakura-deep: #C2185B;
    --sakura-gradient: linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%);
}
```

### เพิ่ม/แก้ไขภาษา
แก้ไขใน `js/translations.js`

## 🔧 การใช้งาน

### สลับมุมมอง (List / Timeline)
- ใช้ **Toggle Switch** ด้านล่างปุ่มค้นหาเพื่อสลับมุมมอง
- **รายการ (List)**: มุมมองแบบตาราง แสดงรายละเอียดครบ
- **ไทม์ไลน์ (Gantt)**: มุมมองแบบ timeline เห็น overlap ของหลายเวทีได้ง่าย
- ระบบจะจำมุมมองที่เลือกไว้ (localStorage)

### กรองข้อมูล
1. พิมพ์ค้นหาในช่อง search (auto-select เมื่อคลิก, มีปุ่ม ✕ ล้างค้นหา)
2. เลือกศิลปิน/วงจาก checkbox
3. เลือกเวทีจาก checkbox
4. กดปุ่ม "ค้นหา"

### บันทึกเป็นรูปภาพ
1. กรองข้อมูลตามต้องการ
2. กดปุ่ม "📸 บันทึกเป็นรูปภาพ"
3. รอ html2canvas โหลด (ครั้งแรก)
4. ไฟล์ PNG จะดาวน์โหลดอัตโนมัติ

### Export ไปปฏิทิน
1. กรองข้อมูลตามต้องการ
2. กดปุ่ม "📅 Export to Calendar"
3. เปิดไฟล์ .ics ด้วย Google Calendar / Apple Calendar

## 🔌 API Endpoints

### Public API (`api.php`)
```
GET /api.php?action=programs              # Programs ทั้งหมด
GET /api.php?action=programs&event=slug   # กรองตาม event
GET /api.php?action=programs&organizer=X  # กรองตามศิลปิน
GET /api.php?action=programs&location=X   # กรองตามเวที
GET /api.php?action=organizers            # รายชื่อศิลปินทั้งหมด
GET /api.php?action=locations             # รายชื่อเวทีทั้งหมด
GET /api.php?action=events_list           # รายการ events ที่ active ทั้งหมด
```

### Request API (`api/request.php`)
```
POST /api/request.php?action=submit     # ส่งคำขอเพิ่ม/แก้ไข event
GET  /api/request.php?action=programs   # ดึงรายการ programs (สำหรับ modal)
```

### Admin API (`admin/api.php`) - ต้อง login + CSRF Token
```
# Programs (รองรับ ?event_meta_id=X สำหรับกรองตาม event)
GET    ?action=programs_list              # รายการ programs (pagination, search, filter, sort)
GET    ?action=programs_get&id=X          # ดึง program เดียว
POST   ?action=programs_create            # สร้าง program ใหม่ (รับ event_meta_id)
PUT    ?action=programs_update&id=X       # แก้ไข program (รับ event_meta_id)
DELETE ?action=programs_delete&id=X       # ลบ program
DELETE ?action=programs_bulk_delete       # ลบหลาย programs (สูงสุด 100)
PUT    ?action=programs_bulk_update       # แก้ไขหลาย programs (venue/organizer/categories)
GET    ?action=programs_venues            # รายชื่อเวทีทั้งหมด (สำหรับ autocomplete)

# Events (events_meta CRUD)
GET    ?action=events_list           # รายการ events ทั้งหมด
GET    ?action=events_get&id=X       # ดึง event เดียว
POST   ?action=events_create         # สร้าง event ใหม่
PUT    ?action=events_update&id=X    # แก้ไข event
DELETE ?action=events_delete&id=X    # ลบ event

# Requests (รองรับ ?event_meta_id=X สำหรับกรองตาม convention)
GET    ?action=requests          # รายการคำขอ (filter by status)
GET    ?action=pending_count     # จำนวนคำขอ pending (สำหรับ badge)
PUT    ?action=request_approve&id=X  # อนุมัติคำขอ
PUT    ?action=request_reject&id=X   # ปฏิเสธคำขอ

# ICS Upload (รองรับ event_meta_id)
POST   ?action=upload_ics       # อัพโหลด + parse ไฟล์ .ics
POST   ?action=import_ics_confirm    # ยืนยัน import events

# Credits (รองรับ ?event_meta_id=X สำหรับกรองตาม convention)
GET    ?action=credits_list      # รายการ credits (pagination, search)
GET    ?action=credits_get&id=X  # ดึง credit เดียว
POST   ?action=credits_create    # สร้าง credit ใหม่ (รับ event_meta_id)
PUT    ?action=credits_update&id=X   # แก้ไข credit
DELETE ?action=credits_delete&id=X   # ลบ credit
DELETE ?action=credits_bulk_delete   # ลบหลาย credits

# Change Password
POST   ?action=change_password      # เปลี่ยนรหัสผ่าน admin (ต้อง login ด้วย DB user)

# User Management (admin role only)
GET    ?action=users_list          # รายการ users ทั้งหมด
GET    ?action=users_get&id=X      # ดึง user เดียว
POST   ?action=users_create        # สร้าง user ใหม่ (username, password, display_name, role)
PUT    ?action=users_update&id=X   # แก้ไข user (password optional)
DELETE ?action=users_delete&id=X   # ลบ user (ห้ามลบตัวเอง/admin คนสุดท้าย)

# Backup/Restore (admin role only)
POST   ?action=backup_create        # สร้าง backup ใหม่
GET    ?action=backup_list          # รายการ backup ทั้งหมด
GET    ?action=backup_download&filename=X  # ดาวน์โหลดไฟล์ backup
DELETE ?action=backup_delete        # ลบไฟล์ backup
POST   ?action=backup_restore      # Restore จากไฟล์บน server
POST   ?action=backup_upload_restore     # Upload .db แล้ว restore
```

## 🔒 Security Features

- **XSS Protection**: sanitize_string(), sanitize_string_array(), get_sanitized_param()
- **CSRF Protection**: Token-based validation สำหรับ POST/PUT/DELETE
- **Session Security**: Timeout 2 ชั่วโมง, timing attack prevention, session fixation prevention
- **Secure Cookies**: httponly, secure, SameSite attributes
- **Rate Limiting**: 10 requests/ชั่วโมง/IP สำหรับ event requests
- **IP Whitelist**: จำกัด admin access ตาม IP (รองรับ CIDR notation)
- **Security Headers**: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy
- **SQL Injection Prevention**: PDO prepared statements ทุก query
- **Role-Based Access Control**: admin/agent roles, defense in depth (PHP + API level)

## 🛠 Tools (สำหรับ Developer)

อยู่ในโฟลเดอร์ `tools/`:

| ไฟล์ | หน้าที่ |
|------|--------|
| `import-ics-to-sqlite.php` | Import ICS → SQLite |
| `update-ics-categories.php` | เพิ่ม CATEGORIES field |
| `migrate-add-requests-table.php` | สร้างตาราง event_requests |
| `migrate-add-credits-table.php` | สร้างตาราง credits |
| `migrate-add-events-meta-table.php` | สร้างตาราง events_meta + เพิ่ม event_meta_id |
| `migrate-rename-tables-columns.php` | Rename tables/columns เป็น v1.2.9 schema (idempotent) |
| `migrate-add-admin-users-table.php` | สร้างตาราง admin_users + seed จาก config |
| `migrate-add-role-column.php` | เพิ่ม role column ใน admin_users |
| `migrate-rename-tables-columns.php` | Rename tables/columns เป็น v1.2.9 schema (idempotent) |
| `migrate-add-indexes.php` | DB performance indexes (idempotent, รันซ้ำได้) |
| `migrate-add-event-email-column.php` | เพิ่ม email column ใน events table (idempotent) |
| `generate-password-hash.php` | สร้าง password hash สำหรับ admin |
| `debug-parse.php` | Debug การ parse ICS |
| `test-parse.php` | ทดสอบ parse ไฟล์ |

**วิธีใช้**:
```bash
cd tools
php import-ics-to-sqlite.php
php migrate-add-credits-table.php
php generate-password-hash.php yourpassword
```

## 🐳 Docker

### คำสั่งที่ใช้บ่อย
```bash
docker-compose up -d              # เริ่ม container
docker-compose down               # หยุด container
docker-compose logs -f             # ดู logs
docker-compose restart             # restart
docker exec idol-stage-calendar bash                      # เข้า shell
docker exec idol-stage-calendar php tests/run-tests.php   # รัน tests
docker exec idol-stage-calendar php tools/import-ics-to-sqlite.php  # import data
```

### ไฟล์ Docker
- `Dockerfile` - PHP 8.1-apache พร้อม PDO SQLite
- `docker-compose.yml` - Production (port 8000, volumes สำหรับ ics/cache/db)
- `docker-compose.dev.yml` - Development (live reload, error display)
- `.dockerignore` - ลดขนาด image

ดูรายละเอียดที่ [DOCKER.md](DOCKER.md)

## 🧪 Testing (สำหรับ Developer)

### Automated Test Suite

ระบบมี **999 automated unit tests** ครอบคลุมทุก feature:

```bash
# รัน test ทั้งหมด
php tests/run-tests.php

# รัน test แต่ละ suite
php tests/run-tests.php SecurityTest          # 7 tests
php tests/run-tests.php CacheTest             # 17 tests
php tests/run-tests.php AdminAuthTest         # 38 tests
php tests/run-tests.php CreditsApiTest        # 49 tests
php tests/run-tests.php IntegrationTest       # 97 tests
php tests/run-tests.php UserManagementTest    # 116 tests
php tests/run-tests.php ThemeTest             # 140 tests
php tests/run-tests.php SiteSettingsTest      # 154 tests
php tests/run-tests.php EventEmailTest        # 19 tests
php tests/run-tests.php ProgramTypeTest       # 35 tests

# รัน test เฉพาะ function
php tests/run-tests.php SecurityTest::testSanitizeString
```

### Quick Tests (ก่อน Commit)

```bash
# Windows
quick-test.bat

# Linux/Mac
./quick-test.sh
```

### Test Coverage

- **SecurityTest**: XSS protection, input sanitization, SQL injection prevention
- **CacheTest**: Cache TTL, invalidation, hit/miss behavior
- **AdminAuthTest**: Session security, timing attack resistance, DB auth, change password
- **CreditsApiTest**: Database CRUD operations, bulk operations
- **IntegrationTest**: Configuration validation, workflow testing, API endpoints, multi-event support
- **UserManagementTest**: Role column schema, role helpers, user CRUD, permission checks
- **ThemeTest**: Theme system, get_site_theme(), CSS files, admin API, public pages
- **SiteSettingsTest**: Site title, get_site_title(), APP_NAME, admin API, public pages
- **EventEmailTest**: events.email schema, CRUD, validation logic, ICS ORGANIZER logic, migration idempotency
- **ProgramTypeTest**: programs.program_type schema, CRUD, migration idempotency, public API type filter, admin API programs_types, index.php UI (appendFilter, hasTypes, event-subtitle, clickable badges), translations (table.type TH/EN/JA), admin UI v2.4.2 categories column

✅ **ผ่านทั้งหมด 999 tests บน PHP 8.1, 8.2, และ 8.3**

### Manual Testing

ดู [TESTING.md](TESTING.md) สำหรับ:
- 129 manual test cases
- Security testing procedures
- Performance benchmarks
- Edge case scenarios

### CI/CD

GitHub Actions รัน tests อัตโนมัติ:
- ทดสอบบน **PHP 8.1, 8.2, และ 8.3**
- Security และ Integration tests แยกกัน
- ผ่านทุก test บนทุก PHP version
- ดูที่ `.github/workflows/tests.yml`

## 📄 รูปแบบไฟล์ ICS

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Idol Stage Timetable//EN

BEGIN:VEVENT
UID:event-001@jpexpo.local
DTSTART:20260207T100000Z
DTEND:20260207T110000Z
SUMMARY:ชื่อการแสดง
LOCATION:ชื่อเวที
ORGANIZER;CN="ชื่อศิลปิน":mailto:info@example.com
CATEGORIES:ชื่อศิลปิน
DESCRIPTION:รายละเอียด
STATUS:CONFIRMED
END:VEVENT

END:VCALENDAR
```

## 🐛 แก้ไขปัญหา

### ไม่แสดงกิจกรรม
- ตรวจสอบไฟล์ .ics ในโฟลเดอร์ `ics/`
- รัน `php tools/import-ics-to-sqlite.php`
- ตรวจสอบ permission

### Cache ไม่ update
- เปลี่ยน `APP_VERSION` ใน `config/app.php`
- Cloudflare: Purge cache

### บันทึกรูปภาพไม่ได้
- ตรวจสอบ internet connection (ต้องโหลด html2canvas)
- ลองเปิด browser console ดู error

### Docker issues
- ตรวจสอบ `docker-compose logs -f`
- ตรวจสอบ permissions: `docker exec idol-stage-calendar chmod -R 777 cache/`
- Rebuild: `docker-compose down && docker-compose up --build -d`

## 📞 ติดต่อ

- Twitter (X): [@FordAntiTrust](https://x.com/FordAntiTrust)

## 📝 Changelog

### v2.4.5 (2026-03-03)

- 🕐 **Collapse same-time** — start == end time → แสดงแค่เวลาเริ่ม (List view, Gantt tooltip, Admin Programs list)
- 📅 **Collapse same-date** — event start_date == end_date → แสดงแค่วันเริ่ม (event listing card); Admin ICS preview มี `formatDateTimeRange()` helper รวม same-date + same-time

### v2.4.4 (2026-03-03)

- 🔧 **Version update script** — `tools/update-version.php X.Y.Z` อัปเดต 9 files อัตโนมัติ (`config/app.php` + 8 .md files); ยกเว้น `CHANGELOG.md` และ `CLAUDE.md` (ต้องเขียน content เอง)
- 📅 **ICS Export VALARM** — ทุก VEVENT มี `VALARM` 15 นาทีก่อนเวลา (`TRIGGER:-PT15M`, `ACTION:DISPLAY`); รองรับ Google Calendar, Apple Calendar
- 🌐 **Full English translation** — แปล `SETUP.md`, `CHANGELOG.md`, `API.md`, `PROJECT-STRUCTURE.md` เป็นภาษาอังกฤษ
- 📝 **README.md Features updated** — เพิ่ม features ที่หายไป (Program Types, Quick Filter Badges, Date Jump Bar, Per-Event Theme, Site Title Setting, Setup Wizard); แก้ Venue Mode table (List/Timeline toggle ของ `single` mode = Visible ตาม v2.3.4); อัปเดต test count เป็น 999 / 10 suites
- 🔀 **Doc consolidation** — รวม `QUICKSTART.md` → `README.md` (Quick Start + Core Features + Pro Tips); รวม `SQLITE_MIGRATION.md` → `PROJECT-STRUCTURE.md` (DB Schema + Performance + Management); ลบไฟล์ต้นทาง
- 🐛 **IntegrationTest fix** — ลบ `QUICKSTART.md` และ `SQLITE_MIGRATION.md` ออกจาก `testDocumentationExists` หลังลบไฟล์จริงไปแล้ว

### v2.4.3 (2026-03-02)

- 🧪 **ProgramTypeTest** — 35 automated tests ครอบคลุม v2.4.x (schema, migration, CRUD, public API type filter, admin API programs_types, index.php UI features, translations, admin UI v2.4.2 categories column); total 999 tests (10 suites)
- 🐛 **setup.php `fix_programs_title` bug fix** — CREATE TABLE recreate ไม่มี `program_type` column; แก้โดยเพิ่ม column ใน schema ใหม่เสมอ + ตรวจสอบ `programs_old` แล้ว copy ค่าถ้ามี

### v2.4.2 (2026-03-02)

- 🗂️ **Admin Programs List: Organizer → Categories** — เปลี่ยน column "Organizer" ในตาราง Programs ของ Admin เป็น "Categories" (ศิลปินที่เกี่ยวข้อง); sort key `organizer` → `categories`; ICS import preview header `ผู้จัด` → `ศิลปินที่เกี่ยวข้อง`

### v2.4.1 (2026-03-02)

- 🏷️ **Event Name Subtitle** — ชื่องานแสดงเป็น `<div class="event-subtitle">` แยกใต้ `<h1>` เสมอ (ไม่ขึ้นกับ dropdown)
- 🖱️ **Clickable Filter Badges** — badge ศิลปิน (categories cell) และ badge ประเภท (type cell) กดได้เพื่อ append filter; `appendFilter(type, value)` JS function; `htmlspecialchars(json_encode(...), ENT_QUOTES)` fix SyntaxError
- 📋 **Program Type Column** — แยก column "ประเภท" เป็นของตัวเอง; แสดงเมื่อ `$hasTypes = !empty($types)` (มีอย่างน้อย 1 program มี type); `table.type` key ใน translations.js (TH/EN/JA)
- 📖 **how-to-use.php อัพเดท** — เพิ่มหัวข้อ "5. กรองด่วนจาก badge" ครบ 3 ภาษา

### v2.4.0 (2026-03-02)

- 🏷️ **Program Type System** — `program_type TEXT DEFAULT NULL` column ใน `programs` table; free-text entry พร้อม autocomplete; filter checkbox UI (เหมือน artist filter); badge บน program rows; type label บน Gantt bars; `?type=` filter ใน public API + export; migration script `tools/migrate-add-program-type-column.php`

### v2.3.4 (2026-03-02)

- 🗓️ **Gantt Chart ใน Single Venue Mode** — ลบ `if ($currentVenueMode === 'multi')` wrapper ออก; toggle switch แสดงทุก venue mode

### v2.3.3 (2026-03-02)

- 🗓️ **Gantt Chart overlap fix** — program ที่ 4+ ไม่แสดงเมื่อ overlap >3; แก้จาก CSS `stack-h-N` เป็น inline style dynamic `stackIndex/stackTotal` (หารเท่ากัน ไม่จำกัดจำนวน)

### v2.3.2 (2026-03-02)

- 🕐 **Timezone fix** — เพิ่ม `date_default_timezone_set('Asia/Bangkok')` ใน `config/app.php`; แก้ `IcsParser::parseDateTime()` แปลง UTC (Z suffix) → Asia/Bangkok ก่อนเก็บ DB

### v2.3.1 (2026-03-02)

- 🐛 **Bulk Edit Programs ไม่บันทึก** — `bulkUpdatePrograms()` ผสม named + positional PDO parameters → silent fail; แก้เป็น named params ล้วน (`:id_0`, `:id_1`, ...)

### v2.3.0 (2026-03-02)

- 📧 **Event Email Field** — `email TEXT DEFAULT NULL` ใน `events` table; Admin Event form มี Contact Email input; invalid email → stored as NULL (`FILTER_VALIDATE_EMAIL`)
- 📅 **ICS ORGANIZER redesign** — `ORGANIZER;CN="ชื่องาน":mailto:email` แทนการใส่ชื่อศิลปิน; fallback `noreply@stageidol.local` เมื่อไม่มี email
- 🧹 **Production Cleanup (Step 6 ใน setup.php)** — ลบไฟล์ dev/docs ผ่าน grouped checkbox (Docs, Tests, Tools, Docker, Nginx, CI/CD); whitelist-based security
- 🧪 **EventEmailTest** — 19 tests ใหม่ (รวม 637 ทั้งระบบ)
- 🔧 **Migration** — `tools/migrate-add-event-email-column.php` (idempotent)

### v2.2.1 (2026-02-28)

- 🐛 **setup.php schema fix** — `programs` table ถูกสร้างด้วย `summary` แทน `title` → Admin สร้าง program ไม่ได้ ("Failed to create event")
  - แก้ `CREATE TABLE programs` + migration action `fix_programs_title` + UI button + `$allTablesOk` ตรวจ `$hasTitleColumn`
- 🐛 **หน้ารวม Events ว่างหลัง init** — `$showEventListing` ใช้ `$nonDefaultEvents` แทน `$activeEvents` ทั้งหมด
- 🌱 **Seed 3 sample programs** เมื่อ init_database (Opening Ceremony, Artist Performance, Closing Stage)
- 📖 **Admin Help Pages** เพิ่ม "Default Event" behavior documentation (TH + EN)

### v2.2.0 (2026-02-27)

- 📝 **Site Title** — แก้ไขชื่อเว็บได้จาก Admin › Settings (`get_site_title()`, `cache/site-settings.json`, `APP_NAME`)
- 🌐 **JS IIFE patching** — `translations.js` patch `window.SITE_TITLE` ทุก translation key
- 🧪 **618 automated tests** (SiteSettingsTest เพิ่ม 14 tests)

### v2.1.1 (2026-02-27)

- 🎨 **Per-Event Theme** — กำหนด theme แยกตาม event ได้
  - คอลัมน์ `theme TEXT DEFAULT NULL` ใน `events` table
  - `get_site_theme($eventMeta = null)` — priority: event.theme → global → `dark` fallback
  - Admin Event form: theme picker (7 themes)
  - Migration: `tools/migrate-add-theme-column.php` + setup.php รองรับ fresh install / existing install
- 🧪 **464 automated tests** (ThemeTest เพิ่มจาก 16 → 24)

### v1.2.12 (2026-02-26)

- 📖 **Admin Help Pages** - คู่มือการใช้งาน Admin Panel
  - `admin/help.php` (ไทย) + `admin/help-en.php` (English) พร้อม language switcher
  - Mobile TOC: sidebar ซ่อนบน mobile ใช้ collapsible dropdown แทน
- 📱 **Admin UI Mobile Responsive** (`admin/index.php`)
  - iOS Auto-Zoom Fix: date input font-size ≥ 1rem
  - Touch Targets: modal-close 44×44px, checkboxes 20px, btn-sm min-height 40px
  - Hamburger Tab Menu: dropdown navigation บน mobile (≤600px) พร้อม badge + active state
  - Table Scroll Fix: wrapper div pattern ป้องกัน iOS scroll capture ใน Programs list
  - 3 Breakpoints: 768px, 600px, 480px

### v1.2.11 (2026-02-25)

- 🛠️ **Setup Wizard** (`setup.php`) - หน้า Setup & Installation แบบ interactive
  - 5 ขั้นตอน: System Requirements → Directories → Database → Import → Admin & Security
  - Auto-login หลัง Initialize Database, Inline password change, Default credentials box
  - Lock/Unlock mechanism (`data/.setup_locked`), Auth gate (fresh install ไม่ต้อง login)
- 📖 **[SETUP.md](SETUP.md)** - คู่มือ Setup Wizard ฉบับสมบูรณ์
- 🔌 **[API.md](API.md)** - API endpoint documentation ครบถ้วน (Public/Request/Admin) พร้อม examples
- 📁 **[PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md)** - โครงสร้างไฟล์ + คำอธิบาย functions + file relationships
- 📝 **ปรับปรุง .md ทั้งหมด** - อัพเดท README (ย่อ 3 sections → links), QUICKSTART, INSTALLATION, SQLITE_MIGRATION, TESTING

### v1.2.10 (2026-02-19)

- ⚡ **Database Indexes** (`tools/migrate-add-indexes.php`) - เพิ่ม 7 indexes เพิ่มความเร็ว 2-5x
  - `idx_programs_event_id`, `idx_programs_start`, `idx_programs_location`, `idx_programs_categories`
  - `idx_program_requests_status`, `idx_program_requests_event_id`, `idx_credits_event_id`
- 🔑 **`get_db()` Singleton** (`functions/helpers.php`) - PDO singleton สำหรับ web context
- 🚦 **Login Rate Limiting** - จำกัด login 5 ครั้ง/15 นาที/IP (`cache/login_attempts.json`)
- ⚡ **Pre-computed Timestamps** (`index.php`) - `start_ts`/`end_ts` คำนวณครั้งเดียวต่อ record
- 🌐 **HTTP Cache Headers** (`api.php`) - ETag + Cache-Control + 304 Not Modified สำหรับ public API
- 🧪 **324 automated tests** ผ่านทั้งหมด

### v1.2.9 (2026-02-18)

- 🗄️ **Database Tables & Columns Rename** - เปลี่ยนชื่อ tables/columns ให้สอดคล้องกับ terminology
  - `events` → `programs`, `events_meta` → `events`, `event_requests` → `program_requests`
  - Column `event_meta_id` → `event_id`, `event_id` (in requests) → `program_id`
  - Migration script: `tools/migrate-rename-tables-columns.php` (idempotent)
- 🧪 **324 automated tests** ผ่านทั้งหมด

### v1.2.8 (2026-02-18)

- 🔧 **PHP Backend Function Renames** - เปลี่ยนชื่อ PHP functions ให้สอดคล้องกับ terminology ใหม่
  - `admin/api.php`: `listEvents()`→`listPrograms()`, `getEvent()`→`getProgram()`, `createEvent()`→`createProgram()`, `updateEvent()`→`updateProgram()`, `deleteEvent()`→`deleteProgram()`, `bulkDeleteEvents()`→`bulkDeletePrograms()`, `bulkUpdateEvents()`→`bulkUpdatePrograms()`
  - `admin/api.php`: `listEventMeta()`→`listEvents()`, `getEventMeta()`→`getEvent()`, `createEventMeta()`→`createEvent()`, `updateEventMeta()`→`updateEvent()`, `deleteEventMeta()`→`deleteEvent()`
  - `functions/helpers.php`: `get_event_meta_by_slug()`→`get_event_by_slug()`, `get_event_meta_id()`→`get_event_id()`
- 🧪 **226 automated tests** ผ่านทั้งหมด

### v1.2.7 (2026-02-18)

- 🔌 **API Action Name Renames** - เปลี่ยนชื่อ API actions ให้สอดคล้องกับ terminology ใหม่
  - Public API: `action=events`→`action=programs`, `action=events_list` คงเดิม (ถูกต้องแล้ว)
  - Admin API Programs: `list`→`programs_list`, `get`→`programs_get`, `create`→`programs_create`, `update`→`programs_update`, `delete`→`programs_delete`, `venues`→`programs_venues`, `bulk_delete`→`programs_bulk_delete`, `bulk_update`→`programs_bulk_update`
  - Admin API Events: `event_meta_list`→`events_list`, `event_meta_get`→`events_get`, `event_meta_create`→`events_create`, `event_meta_update`→`events_update`, `event_meta_delete`→`events_delete`
  - Request API: `action=events`→`action=programs`
- 🧪 **226 automated tests** ผ่านทั้งหมด

### v1.2.6 (2026-02-18)

- 🏷️ **Terminology Rename** - ปรับคำเรียกทั่วทั้ง UI
  - "Events" (individual shows) → **"Programs"**
  - "Conventions" → **"Events"**
- 🌐 **Translation Updates** - อัพเดท 3 ภาษา (TH/EN/JA) ทั้ง text values และ translation key names
  - Key renames: `message.noEvents`→`message.noPrograms`, `table.event`→`table.program`, `gantt.noEvents`→`gantt.noPrograms`, `modal.eventName`→`modal.programName`
- 🎨 **CSS Class Renames**: `.event-*`→`.program-*`, `.gantt-event-*`→`.gantt-program-*`
- ⚙️ **Admin Panel**: Tab "Events"→"Programs", Tab "Conventions"→"Events", section IDs and JS functions updated
- 🧪 **226 automated tests** ผ่านทั้งหมด

### v1.2.5 (2026-02-18)

- 👤 **User Management** - จัดการ admin users ผ่าน Admin panel (CRUD)
  - Tab "👤 Users" สำหรับ admin role
  - สร้าง/แก้ไข/ลบ users พร้อม role assignment
  - API: `users_list`, `users_get`, `users_create`, `users_update`, `users_delete`
- 🛡️ **Role-Based Access Control** - แบ่งสิทธิ์ admin/agent
  - `admin`: เข้าถึงทุกอย่าง + จัดการ users + backup/restore
  - `agent`: จัดการ programs เท่านั้น (Programs, Requests, Import ICS, Credits, Events)
  - Defense in depth: PHP ซ่อน HTML + API บล็อก role
  - ป้องกัน lockout: ห้ามลบตัวเอง, ห้ามเปลี่ยน role ตัวเอง, ต้องเหลืออย่างน้อย 1 admin
  - Migration script: `tools/migrate-add-role-column.php`
  - Helper functions: `get_admin_role()`, `is_admin_role()`, `require_admin_role()`, `require_api_admin_role()`
- 🧪 **226 automated tests** (เพิ่มจาก 207)

### v1.2.4 (2026-02-17)

- 🔐 **Database-based Admin Authentication** - ย้าย credentials จาก config เข้า SQLite
  - ตาราง `admin_users` รองรับหลาย admin users
  - Login: DB ก่อน → fallback config (backward compatible)
  - Migration script: `tools/migrate-add-admin-users-table.php`
- 🔑 **Change Password UI** - เปลี่ยนรหัสผ่านผ่าน Admin panel
  - ปุ่ม "🔑 Change Password" ใน header + modal form
  - API: `POST ?action=change_password`
- 🐛 **Backup Delete Fix** - แก้ไขลบ backup ขึ้น "Invalid filename" (DELETE→POST + JS variable scope fix)
- 🧪 **207 automated tests** (เพิ่มจาก 189)

### v1.2.3 (2026-02-17)

- 💾 **Backup/Restore System** - สำรอง/กู้คืนฐานข้อมูลผ่าน Admin UI
  - Tab "💾 Backup" ใน Admin panel
  - สร้าง/download/restore/delete backup ไฟล์ .db
  - Upload .db จากเครื่องเพื่อ restore
  - Auto-backup ก่อนทุกการ restore (safety net)
  - SQLite header validation + path traversal protection
- 📂 **Database Directory Restructure** - จัดโครงสร้างใหม่
  - `calendar.db` → `data/calendar.db`
  - Backup files → `backups/` directory
  - ใช้ `DB_PATH` constant แทน hardcoded path ทั้งระบบ
  - เพิ่ม `invalidate_all_caches()` ใน cache functions

### v1.2.1 (2026-02-12)

- 🔗 **Clean URL Rewrite** - ลบ .php จาก public URLs พร้อม .htaccess และ nginx config
- 📅 **Date Jump Bar** - แถบ fixed-position สำหรับกระโดดไปวันที่ต้องการ
- 📦 **ICS Import Event Selector** - เลือก convention เป้าหมายเมื่อ import ICS
- 📋 **Admin Credits Per-Event** - กำหนด credits ให้ convention เฉพาะได้
- 🌏 **Complete i18n** - แปลภาษาครบทุก element รวม request modal (20 keys ใหม่)
- 🧪 **189 automated tests** (เพิ่มจาก 187)

### v1.2.0 (2026-02-11)

- 🎪 **Multi-Event (Conventions) Support** - รองรับหลายงาน/conventions ในระบบเดียว
  - ตาราง `events_meta` สำหรับเก็บข้อมูล convention (name, slug, dates, venue_mode, is_active)
  - Tab "Conventions" ใน Admin สำหรับจัดการ CRUD
  - เลือก Convention ผ่าน URL `?event=slug` หรือ dropdown
  - Convention dropdown ใน Event form สำหรับระบุ convention
  - Per-convention venue mode (multi/single)
  - Cache แยกตาม convention
  - Backward compatible - ข้อมูลเดิมทำงานได้โดยไม่ต้อง migrate
  - Migration script: `tools/migrate-add-events-meta-table.php`
  - Config ใหม่: `MULTI_EVENT_MODE`, `DEFAULT_EVENT_SLUG`
  - Helper functions: `get_current_event_slug()`, `get_event_by_slug()`, `get_event_id()`, `get_all_active_events()`, `get_event_venue_mode()`, `event_url()`
  - Admin API: `event_meta_list`, `event_meta_get`, `event_meta_create`, `event_meta_update`, `event_meta_delete`
  - Public API: action `events_list` + parameter `?event=slug` สำหรับทุก action
  - ICS Import: argument `--event=slug`
  - เพิ่ม 15 tests ใหม่ (รวม 189 tests)

### v1.1.0 (2026-02-11)

- 🐳 **Docker Support** - Deploy ด้วย Docker Compose คำสั่งเดียว
  - Dockerfile (PHP 8.1-apache พร้อม PDO SQLite)
  - docker-compose.yml (Production) + docker-compose.dev.yml (Development)
  - Auto-import data และสร้างตารางอัตโนมัติ
  - Health check, volume persistence, network isolation
  - คู่มือครบถ้วนใน [DOCKER.md](DOCKER.md)

- 📋 **Credits Management System** - จัดการ credits/references ผ่าน admin panel
  - ฐานข้อมูล SQLite สำหรับเก็บ credits (title, link, description, display_order)
  - Admin UI - Tab "Credits" พร้อม CRUD operations
  - Bulk operations - เลือกและลบหลาย credits พร้อมกัน
  - Search, sort, และ pagination
  - หน้า credits.php โหลดข้อมูลจาก database แทน hardcode

- 🔄 **Cache System for Credits** - เพิ่มประสิทธิภาพการโหลดหน้า
  - Cache credits data ด้วย TTL 1 ชั่วโมง
  - Auto-invalidate cache เมื่อมีการแก้ไข
  - ลด database queries และเพิ่มความเร็ว
  - Cache file: `cache/credits.json`

- 📦 **Bulk Delete & Bulk Edit** - Admin สามารถจัดการหลาย events พร้อมกัน
  - Checkbox เลือก events แบบ multi-select พร้อม master checkbox
  - Bulk Delete - ลบหลาย events พร้อมกันได้สูงสุด 100 รายการ
  - Bulk Edit - แก้ไข Venue, Organizer, และ Categories พร้อมกัน
  - แสดง selection count และ bulk actions toolbar
  - Transaction handling และ partial failure support
  - Confirmation modals พร้อม count display

- 📤 **ICS Upload & Import** - อัพโหลดไฟล์ .ics ผ่าน Admin UI
  - อัพโหลดไฟล์ .ics (สูงสุด 5MB)
  - ตรวจสอบ file type และ MIME type
  - Preview events ก่อน import
  - ตรวจจับ duplicates (เช็คกับ UIDs ที่มีอยู่)
  - เลือก insert/update/skip แต่ละ event
  - บันทึกไฟล์ต้นฉบับไปโฟลเดอร์ ics/ ได้

- 🎯 **Flexible Venue Entry** - เพิ่มเวทีใหม่ได้โดยไม่ต้องจำกัด
  - เปลี่ยนจาก `<select>` เป็น `<input>` + `<datalist>`
  - แสดง dropdown แนะนำเวทีที่มีอยู่
  - สามารถพิมพ์ชื่อเวทีใหม่ได้เลย
  - รองรับทั้ง single event form และ bulk edit modal

- 📊 **Per-Page Selector** - เลือกจำนวนรายการที่แสดงต่อหน้า
  - ตัวเลือก: 20, 50, 100 รายการต่อหน้า
  - Auto-reset กลับไปหน้า 1 เมื่อเปลี่ยนจำนวน
  - ทำงานร่วมกับ filters, search, และ sort

- 🎨 **Admin UI Improvements** - ปรับปรุง admin interface
  - เปลี่ยนธีมเป็น Professional Blue/Gray
  - Enhanced header พร้อม gradient background
  - Tab navigation แบบ cards
  - ปรับสีและ contrast เพื่อความอ่านง่าย

- 🔒 **Security Enhancements** - เพิ่มความปลอดภัยให้กับระบบ
  - **XSS Protection**: Input sanitization functions (sanitize_string, sanitize_string_array, get_sanitized_param)
  - **CSRF Protection**: Token-based validation สำหรับ POST/PUT/DELETE requests
  - **Session Security**: Session timeout (2 ชั่วโมง), timing attack prevention (hash_equals), session fixation prevention
  - **IP Whitelist**: จำกัด admin access ตาม IP/CIDR
  - **Secure Cookies**: httponly, secure, SameSite attributes
  - **Security Headers**: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy
  - **JSON Security**: ใช้ JSON_HEX_* flags แทน htmlspecialchars() สำหรับ JSON ใน HTML attributes
  - **Race Condition Fix**: safe_session_start() พร้อม session status check
  - **Configuration**: SESSION_TIMEOUT, IP Whitelist ตั้งค่าได้ใน config/admin.php

- 🧪 **Automated Test Suite** - 207 unit tests ครอบคลุมทุก feature
  - Custom TestRunner framework (20 assertion methods)
  - 5 test suites: Security, Cache, AdminAuth, CreditsApi, Integration
  - CI/CD ด้วย GitHub Actions (PHP 8.1, 8.2, 8.3)
  - Quick test scripts สำหรับ pre-commit

### v20260204-231000
- 📝 **Request to Add/Modify Event** - ผู้ใช้สามารถส่งคำขอเพิ่ม/แก้ไข event ได้
  - ปุ่ม "📝 แจ้งเพิ่ม Event" สำหรับแจ้งเพิ่ม event ใหม่
  - ปุ่ม "✏️" ที่แต่ละ event ในหน้ารายการ สำหรับแจ้งแก้ไข
  - Modal form พร้อม pre-fill ข้อมูลจาก event ที่เลือก
  - Rate limiting (10 requests/ชั่วโมง/IP)
  - เก็บข้อมูล: ชื่อผู้แจ้ง, email, หมายเหตุ

- 👨‍💼 **Admin Request Management** - Admin สามารถจัดการคำขอได้
  - Tab "Requests" ใน Admin UI พร้อม badge แสดงจำนวน pending
  - ปุ่ม "👁️ ดู" เพื่อดูรายละเอียดคำขอทั้งหมด
  - Modal แสดงข้อมูล event ที่ขอ + ข้อมูลผู้แจ้ง + ข้อมูลระบบ
  - ปุ่มอนุมัติ/ปฏิเสธ พร้อม auto-create/update event เมื่ออนุมัติ
  - Filter by status (pending/approved/rejected)

- 🔧 **Bug Fixes & Improvements**
  - แก้ไข IcsParser ให้ return `id` field
  - แก้ไข modal overflow - รองรับหน้าจอเล็ก scroll ได้
  - แก้ไข PHP compatibility (ใช้ anonymous function แทน arrow function)

- 📁 **ไฟล์ใหม่**
  - `api/request.php` - Public API สำหรับส่งคำขอ
  - `tools/migrate-add-requests-table.php` - Migration script

### v20260204-020000
- 📊 **Horizontal Gantt Chart** - ปรับ Timeline view เป็นแนวนอนแบบ Gantt Chart จริง
  - แกน Y แสดงเวที (venues)
  - แกน X แสดงเวลา
  - Event bars แสดง duration ตามจริง
  - เห็น overlap ของ events ในเวทีเดียวกันได้ชัดเจน
  - Stack events ที่ซ้อนทับกัน

### v20260204-010000
- ⚙️ **Admin UI** - เพิ่มหน้า Admin สำหรับจัดการ events
  - CRUD operations (Create, Read, Update, Delete)
  - ค้นหาและกรองตามเวที
  - Pagination
  - ใช้งานบน local network (ไม่มี authentication)
  - อยู่ใน `/admin/` directory

### v20260203-230000
- 📊 **Vertical Gantt Chart** - ปรับ Gantt Chart เป็นแนวตั้ง
  - ดูง่ายบนมือถือ (scroll แนวตั้งแทนแนวนอน)
  - แสดง events เรียงตามเวลา พร้อมข้อมูลเวทีครบถ้วน
  - ใช้ข้อมูลเวทีจริงจาก database (ไม่ hardcode)

### v20260203-220000
- 📊 **Gantt Chart View** - เพิ่มมุมมองไทม์ไลน์แบบ Gantt Chart
  - แสดงหลายเวทีพร้อมกัน ดู time overlap ได้ง่าย
  - Toggle switch สลับระหว่าง List/Timeline view
  - Tooltip แสดงรายละเอียดเมื่อ hover/click
  - จำ view mode ใน localStorage

### v20260203-210000
- 🔍 ปรับปรุงช่องค้นหา: Auto-select เมื่อคลิก + ปุ่ม ✕ ล้างค้นหา

### v20260203-200000
- 🌸 เปลี่ยนธีมเป็น Sakura (桜)
- 📦 แยก CSS/JS เป็นไฟล์กลาง (`styles/`, `js/`)
- ⚡ Lazy-load html2canvas
- 🌐 html lang attribute ตามภาษา
- 🔄 Cache busting ด้วย version
- 📁 ย้าย tools ไปโฟลเดอร์ `tools/`
- 🗑 ลบปุ่ม "กลับ" ที่ซ้ำซ้อน

## 📝 ใบอนุญาต

Open Source - นำไปใช้และปรับแต่งได้อย่างอิสระ

---

🌸 **Idol Stage Timetable** - Idol Stage Event
