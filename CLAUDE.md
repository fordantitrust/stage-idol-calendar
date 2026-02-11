# 🌸 Idol Stage Timetable - Idol Stage Event

ระบบปฏิทินกิจกรรม Idol Stage สำหรับงาน Idol Stage Event

**Theme**: Sakura (桜) - ธีมดอกซากุระสไตล์ญี่ปุ่น

## ✨ คุณสมบัติ

- 🌸 **Sakura Theme** - ธีมสีชมพูซากุระสไตล์ญี่ปุ่น
- 🌏 **3 ภาษา** - ไทย, English, 日本語 (พร้อม html lang attribute)
- 📱 **Responsive Design** - รองรับทุกขนาดหน้าจอ รวมถึง iOS
- 📊 **Vertical Timeline View** - ดูตารางเวลาแบบแนวตั้ง เห็น events ตามเวลาได้ง่ายบนมือถือ
- 🔍 **กรองข้อมูล** - ตามศิลปิน/วง และเวที (รองรับหลายค่า)
- 📸 **บันทึกเป็นรูปภาพ** - Lazy-load html2canvas
- 📅 **Export ICS** - ส่งออกเป็นไฟล์ปฏิทิน
- ⚡ **SQLite Database** - ประสิทธิภาพสูง
- 🔄 **Cache System** - Cache สำหรับ data version และ credits (TTL: 10 นาที / 1 ชั่วโมง)
- ⚙️ **Admin UI** - จัดการ events และ credits ผ่านหน้าเว็บ (CRUD + Bulk Operations)
- 📦 **Bulk Operations** - เลือกหลาย events/credits แล้วลบหรือแก้ไขพร้อมกัน
- 📝 **Request System** - ผู้ใช้แจ้งเพิ่ม/แก้ไข event ได้ + Admin อนุมัติ
- 🎯 **Flexible Venue Entry** - พิมพ์เวทีใหม่ได้ พร้อม autocomplete
- 📊 **Customizable Pagination** - เลือกแสดง 20/50/100 รายการต่อหน้า
- 📋 **Credits Management** - จัดการ credits/references ผ่าน admin panel

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
├── api.php                # API endpoint
├── config.php             # Bootstrap file (โหลด config/ และ functions/)
├── IcsParser.php          # ICS Parser class
├── calendar.db            # SQLite database
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
│   ├── admin.php          # Auth functions
│   └── security.php       # Security functions
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
│   ├── index.php          # Admin dashboard (Events + Requests + Credits)
│   ├── api.php            # CRUD API endpoints (events + requests + credits)
│   └── login.php          # Login page
│
├── tools/                 # Development tools
│   ├── import-ics-to-sqlite.php
│   ├── update-ics-categories.php
│   ├── migrate-add-requests-table.php
│   ├── migrate-add-credits-table.php
│   ├── debug-parse.php
│   └── test-parse.php
│
├── README.md
├── QUICKSTART.md
├── INSTALLATION.md
├── CHANGELOG.md
└── SQLITE_MIGRATION.md
```

## 🎨 การปรับแต่ง

### เปลี่ยน Version (Cache Busting)
แก้ไขในไฟล์ `config/app.php`:
```php
define('APP_VERSION', '1.0.0'); // เปลี่ยนเลขนี้เพื่อ force cache refresh
```

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

```
GET /api.php?action=events              # Events ทั้งหมด
GET /api.php?action=events&organizer=X  # กรองตามศิลปิน
GET /api.php?action=events&location=X   # กรองตามเวที
GET /api.php?action=organizers          # รายชื่อศิลปินทั้งหมด
GET /api.php?action=locations           # รายชื่อเวทีทั้งหมด
```

## 🛠 Tools (สำหรับ Developer)

อยู่ในโฟลเดอร์ `tools/`:

| ไฟล์ | หน้าที่ |
|------|--------|
| `import-ics-to-sqlite.php` | Import ICS → SQLite |
| `update-ics-categories.php` | เพิ่ม CATEGORIES field |
| `migrate-add-requests-table.php` | สร้างตาราง event_requests |
| `migrate-add-credits-table.php` | สร้างตาราง credits |
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

## 🧪 Testing (สำหรับ Developer)

### Automated Test Suite

ระบบมี **172 automated unit tests** ครอบคลุมทุก feature:

```bash
# รัน test ทั้งหมด
php tests/run-tests.php

# รัน test แต่ละ suite
php tests/run-tests.php SecurityTest      # 15 tests
php tests/run-tests.php CacheTest         # 11 tests
php tests/run-tests.php AdminAuthTest     # 15 tests
php tests/run-tests.php CreditsApiTest    # 13 tests
php tests/run-tests.php IntegrationTest   # 118 tests

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
- **AdminAuthTest**: Session security, timing attack resistance
- **CreditsApiTest**: Database CRUD operations, bulk operations
- **IntegrationTest**: Configuration validation, workflow testing, API endpoints

✅ **ผ่านทั้งหมด 172 tests บน PHP 8.1, 8.2, และ 8.3**

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

## 📞 ติดต่อ

- Twitter (X): [@FordAntiTrust](https://x.com/FordAntiTrust)

## 📝 Changelog

### v1.1.0 (2026-02-10)
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
  - **Session Security**: Session timeout (2 ชั่วโมง), timing attack prevention (hash_equals), session fixation prevention
  - **Secure Cookies**: httponly, secure, SameSite=Strict attributes
  - **JSON Security**: ใช้ JSON_HEX_* flags แทน htmlspecialchars() สำหรับ JSON ใน HTML attributes
  - **Race Condition Fix**: safe_session_start() พร้อม session status check
  - **Configuration**: SESSION_TIMEOUT ตั้งค่าได้ใน config/admin.php

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
