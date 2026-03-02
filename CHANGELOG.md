# Changelog

All notable changes to Idol Stage Timetable will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.4.3] - 2026-03-02

### Added
- 🧪 **ProgramTypeTest** — 35 automated tests ครอบคลุมการเปลี่ยนแปลงทั้งหมดใน v2.4.x
  - **Schema**: `programs.program_type` column มีอยู่และ nullable
  - **Migration**: `tools/migrate-add-program-type-column.php` มีอยู่และ idempotent
  - **CRUD**: insert/read/update/delete ค่า `program_type` รวมถึง NULL
  - **Public API**: `?type=` filter ทำงานผ่าน `$typeFilter` variable
  - **Admin API**: `programs_types` action, `SELECT DISTINCT program_type`, CREATE/UPDATE/bulk-update handle program_type
  - **index.php UI**: `appendFilter()` function, `URLSearchParams`, `$hasTypes` flag, `.event-subtitle`, `data-i18n="table.type"`, clickable badges, `htmlspecialchars(json_encode())` pattern
  - **Translations**: `'table.type'` key ครบทั้ง 3 ภาษา (ประเภท / Type / タイプ) ปรากฏ 3 ครั้ง
  - **Admin UI v2.4.2**: `sortBy('categories')`, ไม่มี `sortBy('organizer')`, `event.categories`, ไม่มี `<th>ผู้จัด</th>`
- 📊 **Total tests: 999** (เพิ่มจาก 964 → 999 tests, ผ่านทั้งหมด 10 suites)

### Fixed
- 🐛 **setup.php `fix_programs_title` — program_type column หายหลัง fix** — action นี้ recreate `programs` table จาก `summary` → `title` แต่ CREATE TABLE ใหม่ไม่มี `program_type` column ทำให้ column หายทันที
  - เพิ่ม `program_type TEXT DEFAULT NULL` ใน CREATE TABLE ที่ recreate เสมอ
  - ตรวจสอบว่า `programs_old` มี `program_type` อยู่แล้วหรือไม่: ถ้ามี → copy ค่าใน INSERT SELECT ด้วย, ถ้าไม่มี → INSERT โดยไม่รวม column (ค่า default เป็น NULL)

---

## [2.4.2] - 2026-03-02

### Changed
- 🗂️ **Admin Programs List: Organizer → Categories** — เปลี่ยน column "Organizer" ในตาราง Programs ของ Admin เป็น "Categories" (ศิลปินที่เกี่ยวข้อง)
  - Programs list table: header `Organizer` → `Categories`, sort key `organizer` → `categories`, data `event.organizer` → `event.categories`
  - ICS import preview table: header `ผู้จัด` → `ศิลปินที่เกี่ยวข้อง`, data `event.organizer` → `event.categories`
  - ฟอร์มเพิ่ม/แก้ไข Program ยังคงมีช่อง Organizer สำหรับแก้ไขข้อมูลเดิมได้

---

## [2.4.1] - 2026-03-02

### Added
- 🖱️ **Clickable Filter Badges** — กด badge ในตารางเพื่อ append filter ได้ทันที ไม่ต้องใช้ช่อง filter ด้านบน
  - **ศิลปินที่เกี่ยวข้อง**: categories ถูกแยกเป็น badge แต่ละศิลปิน — กดเพื่อ append `artist[]` filter
  - **ประเภท**: badge ประเภทใน column "ประเภท" — กดเพื่อ append `type[]` filter
  - `appendFilter(type, value)` JS function: เพิ่ม filter เข้า URL แบบ append (ไม่ลบ filter เดิม) รองรับทั้งมีและไม่มี filter อยู่ก่อน ไม่เพิ่มซ้ำ
- 📋 **Program Type Column** — แยก column "ประเภท" ออกจาก title cell เป็น column ของตัวเอง
  - แสดง column เมื่อ event นั้นมี program ที่กำหนด `program_type` ไว้อย่างน้อย 1 รายการ (`$hasTypes = !empty($types)`)
  - รองรับ 3 ภาษา (`table.type`: ประเภท / Type / タイプ)
  - Badge คลิกได้ → append filter ตามประเภท, row ที่ไม่มีประเภท → แสดง `-`

### Changed
- 🏷️ **Event Name Subtitle** — แสดงชื่องานเป็น subtitle แยกต่างหากใต้ชื่อเว็บไซต์ในหน้าตาราง
  - ย้ายชื่องานออกจาก `<h1>` (เดิม "Site Title - Event Name") เป็น `<div class="event-subtitle">` แยกต่างหาก
  - แสดงผลเสมอเมื่อดูตารางของ event ใดๆ — ไม่ขึ้นกับว่า dropdown selector จะแสดงหรือไม่
  - ประโยชน์: เมื่อมีงานเดียวในระบบ dropdown จะไม่แสดง แต่ชื่องานยังคงปรากฏชัดเจนใต้ชื่อเว็บ

### Documentation
- 📖 **how-to-use.php อัพเดท** — เพิ่มหัวข้อ "5. กรองด่วนจาก badge ในตาราง" ใน section การกรองข้อมูล ครบทั้ง 3 ภาษา (TH/EN/JA)
  - อธิบาย badge ศิลปิน (สีชมพู) และ badge ประเภท (สีน้ำเงิน)
  - อธิบายพฤติกรรม append filter (ไม่ลบ filter เดิม)

### Fixed
- 🐛 **SyntaxError ใน onclick badge** — `json_encode()` คืน string ที่มี `"` ทำให้ปิด HTML attribute กลางคัน แก้ด้วย `htmlspecialchars(json_encode(...), ENT_QUOTES, 'UTF-8')`

## [2.4.0] - 2026-03-02

### Added
- 🏷️ **Program Type System** — ระบบแยกประเภทโปรแกรม (stage, booth, meet & greet, ฯลฯ)
  - `program_type TEXT DEFAULT NULL` column ใน `programs` table (backward compatible — NULL = ไม่มีประเภท)
  - Free-text entry: พิมพ์ประเภทได้อิสระ พร้อม autocomplete จากข้อมูลที่มีอยู่ในระบบ
  - **Admin form**: input + datalist ใน create/edit modal, badge ในรายการ, bulk edit option
  - **Public filter UI**: checkbox group กรองตามประเภท (เหมือนกับ artist/venue filter) — แสดงเฉพาะเมื่อมีข้อมูล
  - **Program badge**: แสดง badge สีฟ้าเหนือชื่อ program ในตารางหลัก
  - **Gantt Chart**: แสดง type label บน program bar (เล็กๆ ด้านบน)
  - **Public API**: `?type=` filter parameter + `action=types` endpoint
  - **ICS Export**: `?type[]=` filter + program_type ถูก append เข้า CATEGORIES field
  - Migration script: `tools/migrate-add-program-type-column.php` (idempotent)
  - GitHub Actions: เพิ่ม migration ใน workflow
- 🏷️ **Program Type ใน ICS Import** — กำหนดประเภทได้ 3 วิธี (เรียงตามลำดับความสำคัญ)
  - `X-PROGRAM-TYPE:` field ใน VEVENT block (per-event, highest priority)
  - ช่อง "🏷️ Program Type (default)" ใน Admin → Import UI (batch default สำหรับ web upload)
  - `--type=value` argument เมื่อ import ผ่าน CLI: `php tools/import-ics-to-sqlite.php --event=slug --type=stage`
  - `IcsParser::parseEvent()` รองรับ `X-PROGRAM-TYPE:` field แล้ว

### Fixed
- 🐛 **setup.php `init_database` ขาด `program_type` column** — `CREATE TABLE programs` ใน fresh install ไม่มี `program_type TEXT DEFAULT NULL` ทำให้ status check `$allTablesOk = false` และ bottom button แสดงผิด
  - เพิ่ม `program_type TEXT DEFAULT NULL` ใน CREATE TABLE statement ของ `init_database` handler

### Documentation
- 📖 **Admin Help Pages อัพเดท** (`admin/help.php`, `admin/help-en.php`)
  - เพิ่ม Program Type field ในตาราง Add/Edit Program form
  - เพิ่ม X-PROGRAM-TYPE ในตาราง Supported ICS Fields
  - เพิ่มหัวข้อ "การกำหนด Program Type ตอน Import" พร้อมตาราง 3 วิธี
  - เพิ่ม FAQ: การกำหนด Program Type ตอน import ICS
  - อัพเดท Bulk Edit description ให้รวม Program Type

### Changed
- ⬆️ **APP_VERSION** → `2.4.0` (cache busting)

## [2.3.4] - 2026-03-02

### Fixed
- 🗓️ **Gantt Chart ไม่แสดงใน Single Venue Mode** — toggle switch ถูกซ่อนด้วย `if ($currentVenueMode === 'multi')` ทำให้ `#viewToggle` element ไม่มีอยู่ใน DOM และ `initializeView()` ไม่ทำงาน
  - ลบเงื่อนไข `venue_mode === 'multi'` ออก — Toggle switch แสดงทุก mode
  - Gantt Chart ทำงานได้ใน single venue mode (แสดง 1 column)

### Changed
- ⬆️ **APP_VERSION** → `2.3.4` (cache busting)

## [2.3.3] - 2026-03-02

### Fixed
- 🗓️ **Gantt Chart: program ที่ 4+ ไม่แสดงเมื่อ overlap มากกว่า 3** — CSS class `stack-h-N` ออกแบบไว้สำหรับ 2 หรือ 3 overlap เท่านั้น แต่ JS assign ตาม `stackIndex + 1` ตรงๆ ทำให้ program ที่ 4 ได้ `stack-h-4` (1/3 กลาง) ซึ่งทับซ้อนกับ `stack-h-1` และ `stack-h-2` อย่างมองไม่เห็น
  - แก้โดยเปลี่ยนจาก CSS class เป็น inline style คำนวณจาก `stackIndex / stackTotal` แบบ dynamic
  - หารพื้นที่ column เท่าๆ กันทุก program ไม่จำกัดจำนวน (N=4 → 25% ต่อช่อง, N=5 → 20% ต่อช่อง, ...)
  - ลบ CSS classes `stack-h-1` ถึง `stack-h-5` ออก (ไม่ใช้แล้ว)

### Changed
- ⬆️ **APP_VERSION** → `2.3.3` (cache busting)

## [2.3.2] - 2026-03-02

### Fixed
- 🕐 **Timezone ไม่สม่ำเสมอทั่วระบบ** — ไม่มีการกำหนด timezone ทำให้ PHP ใช้ timezone ของ server (Linux/Docker = UTC) ส่งผลให้ `export.php` แปลงเวลาผิด ±7 ชั่วโมง
  - เพิ่ม `date_default_timezone_set('Asia/Bangkok')` ใน `config/app.php` ก่อน constant ทั้งหมด
- 🕐 **IcsParser ทิ้ง Z suffix** — `DTSTART:20260207T100000Z` (UTC 10:00 = ไทย 17:00) ถูกเก็บเป็น `10:00:00` แทน `17:00:00`
  - แก้ `IcsParser::parseDateTime()` ให้ detect Z suffix และแปลง UTC → Asia/Bangkok ก่อนเก็บลง DB

### Changed
- ⬆️ **APP_VERSION** → `2.3.2` (cache busting)

## [2.3.1] - 2026-03-02

### Fixed
- 🐛 **Bulk Edit Programs ไม่บันทึกลงฐานข้อมูล** — `bulkUpdatePrograms()` ใน `admin/api.php` ผสม named parameters (`:location`, `:updated_at`) กับ positional `?` ใน WHERE IN clause เดียวกัน
  - PDO ไม่รองรับการผสมสองแบบ — `execute()` รันสำเร็จแต่ไม่มีแถวใดถูก update (silent fail)
  - แก้ไขให้ใช้ named parameters ล้วน: ID ทุกตัวใช้ `:id_0`, `:id_1`, ... แทน `?`

### Changed
- ⬆️ **APP_VERSION** → `2.3.1` (cache busting)

## [2.3.0] - 2026-03-02

### Added
- 📧 **Event Email Field** — เพิ่ม `email` column ใน `events` table
  - Admin › Events form มี "Contact Email" input field
  - Stored as TEXT DEFAULT NULL; invalid email → stored as NULL (server-side `FILTER_VALIDATE_EMAIL`)
  - Migration script: `tools/migrate-add-event-email-column.php` (idempotent, รันซ้ำได้)
- 📅 **ICS ORGANIZER Redesign** — เปลี่ยน ORGANIZER ใน ICS export ให้สื่อถึงงาน (convention) แทนศิลปิน
  - `ORGANIZER;CN="ชื่องาน":mailto:email@event.com` — ตาม RFC 5545 semantics
  - Fallback: `noreply@stageidol.local` เมื่อไม่ได้ตั้ง email (ไม่ใช้ email ศิลปิน)
- 🧹 **Production Cleanup (Setup Wizard Step 6)** — ระบบลบไฟล์ dev/docs ผ่าน `setup.php`
  - ตรวจสอบ/ลบไฟล์แบบ grouped checkbox (Docs, Tests, Tools, Docker, Nginx, CI/CD)
  - Whitelist-based security (ป้องกัน path traversal); locked เมื่อ setup locked
  - กลุ่มไฟล์:
    - **Docs**: `README.md`, `QUICKSTART.md`, `INSTALLATION.md`, `DOCKER.md`, `CHANGELOG.md`, `TESTING.md`, `SQLITE_MIGRATION.md`, `SECURITY.md`, `CONTRIBUTING.md`, `SETUP.md`, `API.md`, `PROJECT-STRUCTURE.md`, `LICENSE`
    - **Tests**: `tests/` directory
    - **Tools**: `tools/` directory
    - **Docker**: `Dockerfile`, `docker-compose.yml`, `docker-compose.dev.yml`, `.dockerignore`, `.env.example`
    - **Nginx**: `nginx-clean-url.conf`
    - **CI/CD**: `.github/`, `.gitignore`, `quick-test.bat`, `quick-test.sh`
- 🧪 **EventEmailTest** — 19 automated tests สำหรับ email field (รวม 637 ทั้งระบบ)
  - Schema: email column nullable, TEXT type
  - CRUD: insert valid/null email, update email, update to null, read-back via SELECT *
  - Validation logic: accepts valid emails, rejects invalid/empty (returns null)
  - ICS ORGANIZER logic: uses event email, falls back to noreply, skips when no event meta
  - Migration: script exists, idempotent when column already present

### Changed
- ⬆️ **APP_VERSION** → `2.3.0` (cache busting)
- 🔧 **`tools/migrate-add-event-email-column.php`** table ที่ migrate คือ `events` (ไม่ใช่ `programs`)

## [2.2.1] - 2026-02-28

### Fixed
- 🐛 **setup.php สร้าง programs table ผิด schema** — `CREATE TABLE programs` ใช้ `summary TEXT` แทน `title TEXT NOT NULL`
  ทำให้ Admin › Programs › สร้าง program ใหม่ล้มเหลว (`"Failed to create event"`) เพราะ PDOException ถูกซ่อนด้วย `PRODUCTION_MODE`
  - แก้ไข `CREATE TABLE programs` ให้ตรงกับ schema จริง (`title`, `uid NOT NULL`, `start NOT NULL`, `end NOT NULL`, FK `event_id`)
  - เพิ่ม migration action `fix_programs_title` ใน `setup.php` สำหรับ DB ที่ install ด้วย setup.php เก่า
  - เพิ่ม Setup Wizard UI button **"Fix programs.title"** (แสดงเมื่อ programs table มี `summary` แทน `title`)
  - `$allTablesOk` ตรวจสอบ `$hasTitleColumn` ด้วย
- 🐛 **หน้ารวม Events แสดงว่างเปล่าหลัง init database** — `$showEventListing` นับ `$activeEvents` ทั้งหมด
  รวม default event ด้วย ทำให้ trigger หน้ารวม events แต่ default event ถูก skip ในลูป card → หน้าว่าง
  - แก้ไขให้ใช้ `$nonDefaultEvents` (กรอง default slug ออกก่อน) แทน `$activeEvents` ใน condition
  - เมื่อมีเฉพาะ default event → fallback แสดง calendar view โดยตรง

### Added
- 🌱 **Sample programs seed เมื่อ Initialize Database** — `setup.php` สร้าง 3 sample programs อัตโนมัติ
  (Opening Ceremony, Artist Performance, Closing Stage) ใช้วันปัจจุบันเป็น start/end
  เพื่อให้เห็น layout จริงทันทีหลัง fresh install
- 📖 **Admin Help Pages อัพเดท: Default Event behavior** (`admin/help.php` + `admin/help-en.php`)
  - เพิ่มตาราง "Default Event และหน้ารวม Events" อธิบาย 3 กรณี (default only / มี event จริง / เข้า URL โดยตรง)
  - เพิ่ม callout อธิบายว่า default event ถูกซ่อนจากหน้ารวม Events โดยตั้งใจ

## [2.2.0] - 2026-02-27

### Added
- 📝 **Site Title Editable from Admin UI** — Admin สามารถเปลี่ยน site title ผ่าน Settings tab
  - Constant `APP_NAME` ใน `config/app.php` เป็น default/fallback
  - Helper `get_site_title()` ใน `functions/helpers.php` — อ่าน `cache/site-settings.json` → fallback `APP_NAME`
  - Admin API actions `title_get` / `title_save` + functions `getTitleSetting()` / `saveTitleSetting()`
  - Settings tab UI: input field + Save button (ก่อน Site Theme picker)
  - ทุก public page: `<title>` และ `<h1>` ใช้ `get_site_title()` แบบ dynamic
  - PHP inject `window.SITE_TITLE` ก่อน `translations.js` ทุกหน้า public
  - ICS export: `PRODID`, `X-WR-CALNAME`, `X-WR-CALDESC` ใช้ `get_site_title()`
  - Storage: `cache/site-settings.json` — `{"site_title": "...", "updated_at": ...}` (general-purpose settings file)
- 🌐 **JS Translation Patching IIFE** ใน `js/translations.js`
  - Self-patching IIFE อ่าน `window.SITE_TITLE` แล้วแทนที่ `'Idol Stage Timetable'` ในทุก translation key
  - ทำงานอัตโนมัติเมื่อเปลี่ยน site title — รองรับ 3 ภาษา
- 📖 **Admin Help Pages** อัพเดทรองรับ Site Title
  - เพิ่ม "📝 Site Title" subsection ก่อน "🎨 Site Theme" ใน Settings section (TH + EN)
  - อัพเดท Roles table: "Settings (Theme)" → "Settings (Title + Theme)"
  - เพิ่ม FAQ: Site Title ไม่อัพเดทหลังบันทึก
- 🧪 **SiteSettingsTest** — 14 tests ใหม่ (รวม 618 ทั้งระบบ)
  - ทดสอบ `get_site_title()`: no cache, reads cache, empty/whitespace fallback, trim, malformed JSON
  - ทดสอบ Admin API: `title_get`/`title_save` cases, functions defined, `require_api_admin_role()` guard
  - ทดสอบ public pages: `get_site_title()` call, `window.SITE_TITLE` injection
  - ทดสอบ `js/translations.js`: มี IIFE patching block
  - ทดสอบ `APP_NAME` constant ถูก define และ non-empty

### Changed
- 🌐 **`header.subtitle` EN** เปลี่ยนจาก `'Idol Stage Timetable'` → `'Event Schedule'`
  - ทำให้ subtitle เป็น descriptive เหมือน TH (`'ตารางกิจกรรม Idol Stage'`) และ JA (`'アイドルステージタイムテーブル'`)
  - Brand name อยู่ใน `header.title` เท่านั้น

## [2.1.1] - 2026-02-27

### Added
- 🎨 **Per-Event Theme** — กำหนด theme สีแยกตาม event ได้
  - คอลัมน์ `theme TEXT DEFAULT NULL` ใน `events` table
  - `get_site_theme($eventMeta = null)` รับ event meta เพื่อ resolve theme ตาม priority:
    1. Event-specific theme (`events.theme`) — ถ้าตั้งค่าและ valid
    2. Global theme (Settings tab, `cache/site-theme.json`)
    3. Default fallback: `dark`
  - Admin Event form มี theme picker (🌸 Sakura / 🌊 Ocean / 🌿 Forest / 🌙 Midnight / ☀️ Sunset / 🖤 Dark / 🩶 Gray)
  - ทุก public page ส่ง `$eventMeta` เข้า `get_site_theme()`: `index.php`, `credits.php`, `how-to-use.php`, `contact.php`
  - Migration script: `tools/migrate-add-theme-column.php` (idempotent)
  - Setup wizard รองรับ: fresh install สร้าง `theme` column อัตโนมัติ, existing install มีปุ่ม "+ theme column"
- 🧪 **ThemeTest เพิ่ม 8 tests** (รวม 24 tests / 464 ทั้งระบบ)
  - ทดสอบ priority: event → global → dark fallback
  - ทดสอบ null/empty/invalid event theme fallback
  - ทดสอบ Admin API รองรับ theme field

### Changed
- 🎨 **Default theme fallback** เปลี่ยนจาก `sakura` → `dark`
  - `sakura` เป็นเพียง base CSS ใน `common.css` (ไม่มีไฟล์ override ของตัวเอง)
  - ถ้าไม่ได้ตั้ง Global theme และ Event ไม่มี theme → ใช้ `dark` theme

## [2.1.0] - 2026-02-27

### Added
- 🎨 **Theme System** — Admin กำหนด theme สีสำหรับหน้าเว็บ public ทั้งหมด
  - Theme CSS files: `ocean.css` 🌊 Blue, `forest.css` 🌿 Green, `midnight.css` 🌙 Purple, `sunset.css` ☀️ Orange, `dark.css` 🖤 Charcoal, `gray.css` 🩶 Silver
  - Tab "⚙️ Settings" ใน Admin panel (admin role only) พร้อม theme picker UI
  - Admin API: `theme_get`, `theme_save` actions ใน `admin/api.php`
  - Helper: `get_site_theme()` ใน `functions/helpers.php` (อ่าน `cache/site-theme.json` + validate + fallback sakura)
  - Public pages โหลด theme CSS server-side ใน `<head>`
- 📖 **Admin Help Pages — อัพเดทครอบคลุมทุก feature** (`admin/help.php` ไทย + `admin/help-en.php` English)
  - เพิ่ม section ⚙️ Settings: อธิบาย Site Theme, 7 themes ที่ใช้ได้, ขั้นตอนการเปลี่ยน theme
  - อัพเดท overview: 8 แท็บ (เพิ่ม Settings), tab chips พร้อม emoji icons ครบ
  - อัพเดท Roles table: เพิ่มแถว Settings (Theme) — admin ✅, agent ❌
  - เพิ่ม FAQ: เปลี่ยน Theme แล้วหน้าเว็บไม่เปลี่ยนสี
  - TOC (mobile + desktop): เพิ่มลิงก์ Settings, ปรับ "Import ICS" → "Import"

### Changed
- 🎨 **CSS Extracted to External Files** — ย้าย inline `<style>` blocks ออกจาก PHP files เป็น external CSS files
  - `index.php` → `styles/index.css` (ลดขนาดไฟล์จาก ~90KB → ~43KB)
  - `credits.php` → `styles/credits.css`
  - `how-to-use.php` → `styles/how-to-use.css`
- 🧭 **Admin Nav Icons** — เพิ่ม emoji icons ครบทุก tab ใน Admin panel (desktop + mobile)
  - 🎵 Programs, 🎪 Events, 📝 Requests, ✨ Credits, 📤 Import, 👤 Users, 💾 Backup, ⚙️ Settings
  - เปลี่ยนชื่อ "Import ICS" → "Import" ใน nav (เนื้อหา section ยังคงอธิบาย ICS format)

## [2.0.1] - 2026-02-27

### Changed
- ⚙️ **Google Analytics ID configurable** — ย้าย Measurement ID จาก hardcode ในแต่ละ PHP file มาตั้งค่าใน `config/app.php`
  - เพิ่ม constant `GOOGLE_ANALYTICS_ID` — ตั้งค่าเป็น `''` เพื่อปิด Analytics
  - อัพเดท `index.php`, `how-to-use.php`, `contact.php`, `credits.php` ให้ใช้ constant แทน hardcode

## [2.0.0] - 2026-02-27

### ⚠️ Breaking Changes
- 🗄️ **Database Schema Rename** — เปลี่ยนชื่อ tables/columns ทั้งหมด **(ต้องรัน migration script)**
  - Table `events` → `programs` (individual shows)
  - Table `events_meta` → `events` (meta events/conventions)
  - Table `event_requests` → `program_requests`
  - Column `programs.event_meta_id` → `programs.event_id` (FK to events)
  - Column `program_requests.event_id` → `program_requests.program_id` (FK to programs)
  - Column `program_requests.event_meta_id` → `program_requests.event_id` (FK to events)
  - Column `credits.event_meta_id` → `credits.event_id` (FK to events)
  - Migration script: `tools/migrate-rename-tables-columns.php` (idempotent)
- 🔌 **API Action Names Renamed**
  - Public API: `action=events` → `action=programs`
  - Admin API Programs: `list`→`programs_list`, `get`→`programs_get`, `create`→`programs_create`, `update`→`programs_update`, `delete`→`programs_delete`, `venues`→`programs_venues`, `bulk_delete`→`programs_bulk_delete`, `bulk_update`→`programs_bulk_update`
  - Admin API Events: `event_meta_list`→`events_list`, `event_meta_get`→`events_get`, `event_meta_create`→`events_create`, `event_meta_update`→`events_update`, `event_meta_delete`→`events_delete`
  - Request API: `action=events` → `action=programs`
- 🏷️ **Terminology Rename** — ปรับคำเรียกทั่วทั้งระบบ
  - "Events" (individual shows) → **"Programs"**
  - "Conventions" → **"Events"**

### Added
- 🛠️ **Setup Wizard** (`setup.php`) — ติดตั้งระบบแบบ interactive สำหรับ fresh install และ maintenance
  - 5 ขั้นตอน: System Requirements → Directories → Database → Import Data → Admin & Security
  - Auto-login หลัง Initialize Database, Inline password change, Default credentials box
  - Lock/Unlock mechanism (`data/.setup_locked`), Auth gate (fresh install ไม่ต้อง login)
- 📖 **Admin Help Pages** — คู่มือการใช้งาน Admin Panel
  - `admin/help.php` (ไทย) + `admin/help-en.php` (English) พร้อม language switcher
  - ครอบคลุม: Overview, Login, Header, Programs, Events, Requests, Credits, Import ICS, Users, Backup, Roles & Permissions, Tips & FAQ
  - ปุ่ม "📖 Help" ใน Admin header
- ⚡ **Database Indexes** (`tools/migrate-add-indexes.php`) — 7 indexes เพิ่มความเร็ว 2-5x
  - `idx_programs_event_id`, `idx_programs_start`, `idx_programs_location`, `idx_programs_categories` บน `programs` table
  - `idx_program_requests_status`, `idx_program_requests_event_id` บน `program_requests` table
  - `idx_credits_event_id` บน `credits` table
  - Migration script idempotent (`CREATE INDEX IF NOT EXISTS`)
- 🚦 **Login Rate Limiting** — จำกัด login ไม่เกิน 5 ครั้ง/15 นาที/IP
  - Functions: `check_login_rate_limit()`, `record_failed_login()`, `clear_login_attempts()`
  - เก็บข้อมูลใน `cache/login_attempts.json`, แสดงเวลารอที่เหลือ
- 🔑 **`get_db()` Singleton** (`functions/helpers.php`) — PDO singleton สำหรับ web context (1 connection/request)
- `tools/migrate-rename-tables-columns.php` — Migration script (idempotent) for existing databases

### Changed
- 📱 **Admin UI Mobile Responsive** — รองรับ mobile อย่างสมบูรณ์ (iOS + Android)
  - iOS Auto-Zoom Fix: date input `font-size: 0.9rem → 1rem` (ป้องกัน iOS zoom เมื่อ focus)
  - Touch Targets: modal-close button `32×32px → 44×44px`, checkboxes `18px → 20px`, btn-sm `min-height: 40px`
  - Hamburger Tab Menu: dropdown navigation บน mobile (≤600px) พร้อม badge + active state
  - Table Scroll Fix: wrapper div pattern (`<div class="table-scroll-wrapper">`) ป้องกัน iOS scroll capture
  - 3 Breakpoints: 768px (tablet), 600px (small phone), 480px (very small phone)
  - Help page TOC mobile: Sidebar ซ่อนบน mobile ใช้ collapsible dropdown แทน
- 🌐 **HTTP Cache Headers** (`api.php`) — ETag + Cache-Control + 304 Not Modified
  - Programs/organizers/locations: max-age=300 (5 นาที), events_list: max-age=600 (10 นาที)
- ⚡ **Pre-computed Timestamps** (`index.php`) — `start_ts`/`end_ts` คำนวณครั้งเดียวต่อ record
  - ลด `strtotime()` calls ซ้ำในลูปจาก 6 จุด → คำนวณครั้งเดียวต่อ record
- 🌐 **Translation Updates** (`js/translations.js`) — อัพเดท 3 ภาษา (TH/EN/JA)
  - Key renames: `message.noEvents`→`message.noPrograms`, `table.event`→`table.program`, `gantt.noEvents`→`gantt.noPrograms`, `modal.eventName`→`modal.programName`
- 🎨 **CSS Class Renames** — `.event-*`→`.program-*`, `.gantt-event-*`→`.gantt-program-*`
- 🔧 **PHP Backend Function Renames**
  - `admin/api.php`: `listEvents()`→`listPrograms()`, `getEvent()`→`getProgram()`, `createEvent()`→`createProgram()`, `updateEvent()`→`updateProgram()`, `deleteEvent()`→`deleteProgram()`, `bulkDeleteEvents()`→`bulkDeletePrograms()`, `bulkUpdateEvents()`→`bulkUpdatePrograms()`
  - `admin/api.php`: `listEventMeta()`→`listEvents()`, `getEventMeta()`→`getEvent()`, `createEventMeta()`→`createEvent()`, `updateEventMeta()`→`updateEvent()`, `deleteEventMeta()`→`deleteEvent()`
  - `functions/helpers.php`: `get_event_meta_by_slug()`→`get_event_by_slug()`, `get_event_meta_id()`→`get_event_id()`
- ⚙️ **Admin Panel Tab Renames**: "Events"→"Programs", "🏟️ Conventions"→"🏟️ Events"
- `config/app.php`: APP_VERSION → '2.0.0'

### Documentation
- 🔌 **[API.md](API.md)** — API endpoint documentation ครบถ้วน (Public / Request / Admin APIs) พร้อม request/response examples
- 📁 **[PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md)** — โครงสร้างไฟล์ + function list + config constants + file relationships
- 📖 **[SETUP.md](SETUP.md)** — คู่มือการใช้งาน Setup Wizard ฉบับสมบูรณ์
- อัพเดท README, QUICKSTART, INSTALLATION, SQLITE_MIGRATION, TESTING ให้สอดคล้องกับ schema ใหม่

### Migration Guide (from v1.2.5)
```bash
# 1. รัน schema migration (Breaking change — ต้องทำก่อน)
php tools/migrate-rename-tables-columns.php

# 2. เพิ่ม database indexes (performance)
php tools/migrate-add-indexes.php
```

### Testing
- 🧪 **324 automated tests** ผ่านทั้งหมด (PHP 8.1, 8.2, 8.3)

## [1.2.5] - 2026-02-18

### Added

- 👤 **User Management System** - จัดการ admin users ผ่าน Admin panel
  - Tab "👤 Users" ใน Admin panel (แสดงเฉพาะ admin role)
  - ตาราง users: ID, Username, Display Name, Role, Active, Last Login, Actions
  - สร้าง user ใหม่: username, password (min 8 chars), display_name, role, is_active
  - แก้ไข user: password optional, username ไม่สามารถเปลี่ยนได้
  - ลบ user: ห้ามลบตัวเอง, ต้องเหลืออย่างน้อย 1 admin
  - API endpoints: `users_list`, `users_get`, `users_create`, `users_update`, `users_delete`

- 🛡️ **Role-Based Access Control** - ระบบสิทธิ์ตาม role
  - 2 roles: `admin` (full access) และ `agent` (events management only)
  - `admin` role: เข้าถึงทุก tab + จัดการ users + backup/restore
  - `agent` role: เข้าถึงเฉพาะ Events, Requests, Import ICS, Credits, Conventions
  - Defense in depth: PHP ซ่อน HTML elements + API-level role checks
  - ป้องกัน lockout: ห้ามลบตัวเอง, ห้ามเปลี่ยน role ตัวเอง, ห้าม deactivate ตัวเอง
  - ต้องเหลืออย่างน้อย 1 active admin เสมอ
  - Config fallback users เป็น admin role เสมอ (backward compatible)
  - Role badge แสดงข้าง username ใน header
  - Helper functions: `get_admin_role()`, `is_admin_role()`, `require_admin_role()`, `require_api_admin_role()`
  - Migration script: `tools/migrate-add-role-column.php`

### Changed
- `functions/admin.php`: เพิ่ม `$_SESSION['admin_role']` ใน `admin_login()` + 4 role helper functions
- `admin/api.php`: เพิ่ม admin-only action gate สำหรับ backup/users actions + 5 user CRUD endpoints
- `admin/index.php`: เพิ่ม Users tab/modal + ซ่อน Users/Backup tabs จาก agent role
- `config/app.php`: APP_VERSION → '1.2.5'

### Testing
- 🧪 **226 automated tests** (เพิ่มจาก 207) - เพิ่ม 19 tests ใน `UserManagementTest.php`
  - Schema tests: role column, default values
  - Role helper tests: `get_admin_role()`, `is_admin_role()`
  - User CRUD tests: create, update, delete, validation
  - Permission tests: admin-only actions, agent restrictions

## [1.2.4] - 2026-02-17

### Added

- 🔐 **Database-based Admin Authentication** - ย้าย credentials จาก config เข้า SQLite
  - ตาราง `admin_users` รองรับหลาย admin users (username, password_hash, display_name, is_active)
  - Login ลองหาจาก DB ก่อน → fallback ไปใช้ config constants (backward compatible)
  - บันทึก `last_login_at` ทุกครั้งที่ login สำเร็จ
  - Dummy `password_verify` เมื่อไม่พบ username เพื่อป้องกัน timing attacks
  - Migration script: `tools/migrate-add-admin-users-table.php`

- 🔑 **Change Password UI** - เปลี่ยนรหัสผ่านผ่าน Admin panel
  - ปุ่ม "🔑 Change Password" ใน Admin header (แสดงเฉพาะ DB user)
  - Modal form: current password + new password + confirm password
  - Validation: ต้องใส่รหัสเดิม, รหัสใหม่ขั้นต่ำ 8 ตัวอักษร
  - API endpoint: `POST ?action=change_password`

### Fixed
- 🐛 **Backup Delete Fix** - แก้ไขปัญหาลบไฟล์ backup แล้วขึ้น "Invalid filename"
  - เปลี่ยน HTTP method จาก DELETE เป็น POST (Apache/Windows ไม่ส่ง body ใน DELETE request)
  - แก้ JS variable scope bug: `closeDeleteBackupModal()` เคลียร์ตัวแปร filename ก่อนที่ `fetch` จะใช้งาน
  - บันทึก filename เป็น local variable ก่อน close modal

### Changed
- `functions/admin.php`: เพิ่ม 4 ฟังก์ชัน (`admin_users_table_exists`, `get_admin_user_by_username`, `update_admin_last_login`, `change_admin_password`) + แก้ `admin_login()` ให้อ่านจาก DB ก่อน
- `config/admin.php`: `ADMIN_USERNAME` / `ADMIN_PASSWORD_HASH` เป็น fallback (deprecation comment)
- `tools/generate-password-hash.php`: แนะนำ 3 วิธีเปลี่ยนรหัส (Admin UI, config, SQL)
- `admin/api.php`: เปลี่ยน backup delete จาก DELETE เป็น POST method
- เพิ่ม 6 tests ใหม่ (รวม 207 tests จาก 189)

## [1.2.3] - 2026-02-17

### Added

- 💾 **Backup/Restore System** - จัดการ backup ฐานข้อมูลผ่าน Admin UI
  - **Backup Tab**: Tab ใหม่ "💾 Backup" ใน Admin panel
  - **Create Backup**: สร้าง backup ไฟล์ .db พร้อมบันทึกไว้บน server ใน `backups/`
  - **Download Backup**: ดาวน์โหลดไฟล์ backup มาเก็บที่เครื่อง
  - **Restore from Server**: เลือก restore จากไฟล์ backup ที่เก็บไว้บน server
  - **Upload & Restore**: อัพโหลดไฟล์ .db จากเครื่องเพื่อ restore
  - **Delete Backup**: ลบไฟล์ backup ที่ไม่ต้องการ
  - **Auto-Backup Safety**: สร้าง auto-backup อัตโนมัติก่อนทุกการ restore
  - **SQLite Validation**: ตรวจสอบ SQLite header ก่อน restore
  - **Path Traversal Protection**: ป้องกัน path traversal attacks ใน filename

- 📂 **Database Directory Restructure** - จัดโครงสร้าง directory ใหม่
  - **`data/`**: ย้าย `calendar.db` ไปอยู่ใน `data/calendar.db`
  - **`backups/`**: เก็บไฟล์ backup แยกใน `backups/` directory
  - **DB_PATH Constant**: ใช้ `DB_PATH` constant แทน hardcoded path ทั้งระบบ
  - **Docker Updated**: อัพเดท docker-compose.yml mount volume เป็น `data/`

### Changed
- `config/database.php`: DB_PATH ชี้ไป `data/calendar.db`
- `admin/api.php`: ใช้ `DB_PATH` constant, backup dir เปลี่ยนเป็น `backups/`
- `functions/cache.php`: เพิ่ม `invalidate_all_caches()` สำหรับ restore
- อัพเดท migration tools, tests, Docker files ให้ใช้ path ใหม่

## [1.2.1] - 2026-02-12

### Added

- 🔗 **Clean URL Rewrite** - Remove `.php` extension from all public URLs
  - **`.htaccess`**: Apache rewrite rules for clean URLs and event path routing
  - **`nginx-clean-url.conf`**: Nginx configuration example for clean URLs
  - **Event Path Routing**: `/event/slug` → `index.php?event=slug`, `/event/slug/credits` → `credits.php?event=slug`
  - **Backward Compatible**: Old `.php` URLs still work
  - **Admin URLs unchanged**: `/admin/` paths remain as-is
  - **Updated `event_url()`**: Generates clean URLs (`/credits` instead of `/credits.php`)

- 📅 **Date Jump Bar** - Quick navigation between days in multi-day events
  - Fixed-position bar appears when scrolling past the calendar area
  - Shows day/month and weekday name for each date
  - Smooth scroll with offset for fixed bar height
  - IntersectionObserver highlights current visible date
  - Responsive design for mobile
  - Translatable label in all 3 languages

- 📦 **ICS Import Event Selector** - Choose target convention when importing ICS files
  - Dedicated dropdown in ICS upload area to select target convention
  - Convention name badge shown in preview stats

- 📋 **Admin Credits Per-Event** - Assign credits to specific conventions
  - Convention selector dropdown in credit create/edit form
  - Supports global credits (null = shown in all conventions)

- 🌏 **Complete i18n for Request Modal** - All form elements fully translated
  - 20 new translation keys for request modal (labels, buttons, messages) in TH/EN/JA
  - `data-i18n` attributes on all form labels and buttons
  - JavaScript alert/confirm messages use translation system
  - Added missing `credits.list.title` and `credits.noData` keys

### Changed
- Updated `event_url()` to generate clean event paths (`/event/slug/page`)
- Updated `exportToIcs()` to use clean URL paths
- Updated inline JS API calls to use clean URLs (`api/request` instead of `api/request.php`)

### Testing
- 🧪 **189 automated tests** (up from 187) - Added clean URL routing tests

## [1.2.0] - 2026-02-11

### Added

- 🎪 **Multi-Event (Conventions) Support** - Manage multiple events/conventions in one system
  - **New Table**: `events_meta` for storing convention metadata (name, slug, dates, venue_mode, is_active)
  - **Convention Management**: Full CRUD for conventions via new "Conventions" tab in admin panel
  - **Event Scoping**: Each event, request, and credit can belong to a specific convention
  - **URL-based Selection**: Access conventions via `?event=slug` URL parameter
  - **Convention Selector**: Dropdown in header to switch between conventions (public + admin)
  - **Per-Convention Venue Mode**: Each convention can have its own `multi` or `single` venue mode
  - **Backward Compatible**: Existing data works without migration (null event_meta_id = global)
  - **Feature Flag**: `MULTI_EVENT_MODE` constant to enable/disable multi-event features
  - **Migration Script**: `tools/migrate-add-events-meta-table.php` creates tables and migrates existing data
  - **New Config Constants**: `DEFAULT_EVENT_SLUG`, `MULTI_EVENT_MODE` in `config/app.php`
  - **New Helper Functions**: `get_current_event_slug()`, `get_event_meta_by_slug()`, `get_event_meta_id()`, `get_all_active_events()`, `get_event_venue_mode()`, `event_url()`
  - **Admin API Endpoints**: `event_meta_list`, `event_meta_get`, `event_meta_create`, `event_meta_update`, `event_meta_delete`
  - **Public API**: New `events_list` action returns all active conventions; all actions support `?event=slug` filtering
  - **ICS Import**: `--event=slug` argument for CLI import tool
  - **Cache Scoping**: Data version and credits cache scoped per convention
  - **15 New Tests**: Multi-event helper functions, IcsParser filtering, cache scoping (total: 187 tests)

## [1.1.0] - 2026-02-11

### Added

- 🐳 **Docker Support** - One-command deployment with Docker Compose
  - **Dockerfile**: PHP 8.1-apache with PDO SQLite, auto-creates directories and imports data
  - **docker-compose.yml**: Production setup with port 8000, volume mounts (ics, cache, database)
  - **docker-compose.dev.yml**: Development mode with live reload and error display
  - **.dockerignore**: Optimized build exclusions for smaller image size
  - **Health Check**: Built-in container health monitoring
  - **Auto-Setup**: Automatically creates tables and imports ICS files on first run
  - **DOCKER.md**: Comprehensive Docker deployment guide (Quick Start, Production, Development, Advanced)

- 📋 **Credits Management System** - Complete CRUD system for managing credits and references
  - **Database Table**: SQLite table with fields: id, title, link, description, display_order, created_at, updated_at
  - **Admin UI**: New "Credits" tab in admin panel with full management interface
    - Create, Read, Update, Delete operations
    - Search functionality with 300ms debounce
    - Sortable columns (ID, Title, Display Order)
    - Pagination with 20/50/100 per page options
    - Bulk selection with master checkbox
    - Bulk delete up to 100 credits at once
  - **Admin API**: 6 RESTful endpoints
    - `credits_list` - List with pagination, search, sorting
    - `credits_get` - Get single credit
    - `credits_create` - Create new credit
    - `credits_update` - Update existing credit
    - `credits_delete` - Delete single credit
    - `credits_bulk_delete` - Bulk delete with transaction support
  - **Public Display**: credits.php now loads from database instead of hardcoded HTML
  - **Validation**: Title required (max 200 chars), description optional (max 1000 chars)
  - **Migration Script**: `tools/migrate-add-credits-table.php` for database setup

- 🔄 **Cache System for Credits** - Performance optimization for credits page
  - **Cache Function**: `get_cached_credits()` in `functions/cache.php`
  - **TTL**: 1 hour (3600 seconds) configurable via `CREDITS_CACHE_TTL`
  - **Cache File**: `cache/credits.json` with timestamp and data
  - **Auto-Invalidation**: Cache automatically cleared on create/update/delete operations
  - **Fallback**: Returns empty array on cache miss or database error
  - **Performance**: Reduces database queries for frequently accessed credits data
  - **Configuration**: Settings in `config/cache.php`

- 📦 **Bulk Operations** - Admin can now manage multiple events simultaneously
  - Checkbox selection with master checkbox (select all/deselect all)
  - Bulk Delete - Delete up to 100 events at once with confirmation
  - Bulk Edit - Update venue, organizer, and categories for multiple events
  - Selection count display in bulk actions toolbar
  - Transaction handling with partial failure support
  - Visual feedback with selected row highlighting
  - Indeterminate checkbox state for partial selections

- 🎯 **Flexible Venue Entry** - Add new venues without limitations
  - Changed from `<select>` dropdown to `<input>` with `<datalist>`
  - Autocomplete suggestions from existing venues
  - Ability to type new venue names on-the-fly
  - Applies to both single event form and bulk edit modal

- 📤 **ICS Upload & Import** - Upload ICS files directly through Admin UI
  - File upload with validation (max 5MB, .ics files only)
  - MIME type checking (text/calendar, text/plain, application/octet-stream)
  - Preview parsed events before importing
  - Duplicate detection (checks against existing UIDs in database)
  - Per-event action: insert, update, or skip
  - Option to save uploaded file to `ics/` folder
  - Import statistics (inserted, updated, skipped, errors)

- 📊 **Per-Page Selector** - Customize events displayed per page
  - Options: 20, 50, or 100 events per page
  - Auto-reset to page 1 when changing page size
  - Works seamlessly with filters, search, and sorting
  - Dropdown integrated in admin toolbar

- 🎨 **Admin UI Improvements**
  - Professional Blue/Gray color scheme (distinct from user-facing Sakura theme)
  - Enhanced gradient header with icon
  - Card-style tab navigation
  - Improved contrast and readability
  - Fixed username and table header visibility issues

### Changed
- **Cache Configuration** (`config/cache.php`)
  - Added `CREDITS_CACHE_FILE` and `CREDITS_CACHE_TTL` constants
  - Organized cache settings for both data version and credits

- **Cache Functions** (`functions/cache.php`)
  - Added `get_cached_credits()` - Fetch credits with caching
  - Added `invalidate_credits_cache()` - Clear cache after modifications
  - Maintained existing `get_data_version()` function

- **Credits Display** (`credits.php`)
  - Replaced hardcoded HTML with database-driven dynamic content
  - Uses `get_cached_credits()` for optimized loading
  - Proper HTML escaping with `htmlspecialchars()`
  - Support for optional fields (link, description)
  - Empty state handling when no credits exist

- **Admin API** (`admin/api.php`)
  - Added 6 new switch cases for credits operations
  - Cache invalidation after all state-changing operations
  - Consistent error handling and JSON responses

- **Bulk Edit API** (`admin/api.php`)
  - Added support for categories field alongside venue and organizer
  - Validation ensures at least one field is provided
  - Dynamic UPDATE query construction based on provided fields
  - Maximum 100 events per bulk operation for performance

- **Admin Event List** (`admin/index.php`)
  - Added checkbox column to events table
  - Enhanced toolbar with bulk actions bar (shows when events selected)
  - Improved state management with `perPage` variable
  - Better pagination with customizable limits

### Security
- 🔒 **Enhanced Input Sanitization** - Comprehensive protection against XSS and injection attacks
  - **New Functions** in `functions/security.php`:
    - `sanitize_string()` - Remove null bytes, trim, length limits
    - `sanitize_string_array()` - Sanitize array inputs with max items limit
    - `get_sanitized_param()` - Safe GET parameter extraction (string)
    - `get_sanitized_array_param()` - Safe GET parameter extraction (array)
  - **Applied to**: `index.php`, `export.php`, `admin/api.php`
  - **Parameters sanitized**: artist, venue, search, date filters
  - **Protection**: Max length validation, null byte removal, array size limits

- 🛡️ **Session Security Improvements** - Complete rewrite of session management (`functions/admin.php`)
  - **Timing Attack Prevention**: Use `hash_equals()` for username comparison (constant-time)
  - **Session Fixation Prevention**: `session_regenerate_id()` before login and logout
  - **Session Timeout**: Automatic logout after 2 hours of inactivity (configurable)
  - **Secure Cookies**: httponly, secure (HTTPS), SameSite=Strict attributes
  - **Session Validation**: Check timeout on every request
  - **New constant**: `SESSION_TIMEOUT` in `config/admin.php` (default: 7200 seconds)

- 🔐 **JSON Security** - Safe JSON encoding in HTML attributes
  - **Changed**: `htmlspecialchars(json_encode())` → `json_encode()` with security flags
  - **Flags used**: `JSON_HEX_QUOT`, `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`
  - **Benefit**: No JSON structure corruption, safe in HTML attributes
  - **Applied to**: `index.php` request modal data attributes

- **Credits System Security**
  - All credits API endpoints protected by authentication (`require_api_login()`)
  - CSRF token validation for create/update/delete operations
  - SQL injection prevention via PDO prepared statements
  - XSS prevention via `htmlspecialchars()` on output
  - Input validation (required fields, length limits)
  - Rate limiting inherited from admin panel
  - Transaction rollback on bulk operation failures

- **General Security**
  - All bulk operations protected by CSRF tokens
  - Input validation for bulk IDs (max 100, integer sanitization)
  - Transaction rollback on errors
  - Prepared statements for all database operations
  - Safe session handling with race condition prevention

### Testing
- 🧪 **Automated Test Suite** - 187 comprehensive unit tests
  - **Test Framework**: Custom lightweight TestRunner with 20 assertion methods
  - **SecurityTest** (15 tests): Input sanitization, XSS protection, null byte injection, SQL injection prevention
  - **CacheTest** (11 tests): Cache creation, TTL, invalidation, hit/miss, error fallback
  - **AdminAuthTest** (15 tests): Session management, login/logout, timing attack resistance, password verification
  - **CreditsApiTest** (13 tests): Database CRUD, bulk operations, SQL injection protection, display order sorting
  - **IntegrationTest** (118 tests): File structure validation, configuration checks, full workflow simulation, API endpoints
  - **Test Runner**: `tests/run-tests.php` with colored output, test filtering, detailed statistics
  - **Quick Tests**: `quick-test.sh` (Linux/Mac) and `quick-test.bat` (Windows) for pre-commit testing
  - **CI/CD**: GitHub Actions workflow (`.github/workflows/tests.yml`) for automated testing on push/PR
    - Matrix testing across **PHP 8.1, 8.2, and 8.3** (all tests pass)
    - Separate jobs for security and integration tests
    - Automatic test result artifact upload on failure
  - **Documentation**:
    - `tests/README.md` - Automated testing guide (usage, assertions, writing tests, troubleshooting)
    - `TESTING.md` - Manual testing checklist with 129 test cases

### Documentation
- Updated CLAUDE.md with credits management and testing features
- Updated README.md with cache system and testing information
- Updated QUICKSTART.md with testing section and quick test commands
- Updated INSTALLATION.md with testing & QA procedures, pre-production checklist
- Added credits migration script to tools documentation
- Updated file structure diagrams to include cache/ and tests/ directories
- Added TESTING.md with 129 manual test cases covering all features
- Added tests/README.md with comprehensive automated testing guide

## [1.0.0] - 2026-02-09

### Added
- 🌸 **Sakura Theme** - Beautiful cherry blossom theme with Japanese aesthetics
- 🌏 **Multi-language Support** - Thai, English, and Japanese (日本語) with proper html lang attributes
- 📱 **Responsive Design** - Full support for all screen sizes including iOS devices
- 📊 **Dual View Modes**
  - List View: Traditional table layout with full details
  - Gantt Chart View: Horizontal timeline showing event overlaps across venues
- 🔍 **Advanced Filtering**
  - Search by artist/performer name (with auto-select and clear button)
  - Filter by multiple artists
  - Filter by multiple venues
  - Selected tags display with one-click removal
- 📸 **Image Export** - Save calendar as PNG image (lazy-loaded html2canvas)
- 📅 **ICS Export** - Export filtered events to calendar apps (Google Calendar, Apple Calendar, etc.)
- 📝 **User Request System**
  - Users can request to add new events
  - Users can request to modify existing events
  - Rate limiting (10 requests per hour per IP)
  - Request form with pre-filled data for modifications
- ⚙️ **Admin Panel**
  - Full CRUD operations for events
  - Request management (approve/reject user requests)
  - Side-by-side comparison view for modification requests
  - Highlight changed fields (yellow) and new fields (green)
  - Search and filter by venue
  - Pagination support
  - Session-based authentication
  - Optional IP whitelist
- ⚡ **SQLite Database Support**
  - 10-20x faster than parsing ICS files
  - Efficient querying and filtering
  - Auto-generated unique IDs
  - Timestamps for created_at and updated_at
- 🔄 **Cache Busting** - Version-based cache control for CSS/JS files
- 🔒 **Security Features**
  - XSS Protection (server-side and client-side)
  - CSRF token validation
  - Security headers (CSP, X-Content-Type-Options, X-Frame-Options, etc.)
  - Rate limiting for API requests
  - Input validation and sanitization
  - Prepared statements (SQL injection protection)
- 🗂️ **ICS File Support** - Parse and display events from multiple ICS files
- 🌊 **iOS Scroll Indicators** - Gradient shadows on timeline for better UX on iOS
- 📊 **Auto Data Version** - Displays last update time from database

### Fixed
- **Critical Password Hash Bug** - Fixed admin login system that was broken due to password hash regenerating on every page load
  - Changed from dynamic `password_hash()` call to static hash constant
  - Added clear instructions in SECURITY.md for generating password hash
  - Prevents login failures caused by changing hash values
- **Missing `is_logged_in()` function** - Restored function that was accidentally omitted during config reorganization
- iOS Timeline Header Bug - Fixed venue headers not showing for 5+ venues on iOS Safari
  - Added explicit min-width to prevent compositing issues
  - Moved horizontal scroll to parent container
  - Fixed header/body desync on iOS
- Events sorting - Events now properly sorted by time after admin approval
- Modal overflow - Modals now scrollable on small screens
- PHP 7.0 compatibility - Replaced arrow functions with anonymous functions
- Navigation buttons i18n - All nav buttons now properly change language
- IcsParser - Now returns event `id` field for proper tracking

### Changed
- **Reorganized configuration system** for better maintainability:
  - Split monolithic `config.php` into modular structure
  - Created `config/` folder with categorized configuration files:
    - `config/app.php` - Application settings (version, production mode)
    - `config/admin.php` - Authentication & admin settings
    - `config/security.php` - Security & rate limiting
    - `config/database.php` - Database configuration
    - `config/cache.php` - Cache settings
  - Created `functions/` folder with categorized helper functions:
    - `functions/helpers.php` - General utilities
    - `functions/cache.php` - Cache-related functions
    - `functions/admin.php` - Authentication functions
    - `functions/security.php` - Security functions
  - Root `config.php` now acts as bootstrap file loading all configs
- **Reorganized file structure** with dedicated folders:
  - `styles/` for shared CSS
  - `js/` for shared JavaScript
  - `tools/` for development utilities
  - `admin/` for admin interface
  - `api/` for public APIs
- Removed redundant "Back" buttons
- Improved filter UX with selected tags display
- Enhanced admin comparison view for better change visibility

### Documentation
- README.md - Comprehensive feature documentation (updated for new config structure)
- INSTALLATION.md - Detailed installation guide with multiple deployment options
- QUICKSTART.md - 3-step quick start guide
- SQLITE_MIGRATION.md - Database migration and performance guide
- CHANGELOG.md - Version history (this file)
- LICENSE - MIT License
- CONTRIBUTING.md - Contribution guidelines
- SECURITY.md - Security policy and deployment best practices
- .env.example - Environment variables template
- .gitignore - Git ignore patterns (protects sensitive files)

### Developer Tools
- `import-ics-to-sqlite.php` - Import ICS files to SQLite database
- `update-ics-categories.php` - Add CATEGORIES field to ICS files
- `migrate-add-requests-table.php` - Create event_requests table
- `debug-parse.php` - Debug ICS parsing
- `test-parse.php` - Test ICS file parsing

## [Unreleased]
- Nothing yet

---

**Legend:**
- 🌸 UI/UX improvements
- 🌏 Internationalization
- 📱 Mobile/Responsive
- 📊 Data visualization
- 🔍 Search/Filter
- 📸 Export features
- 📅 Calendar features
- 📝 User features
- ⚙️ Admin features
- ⚡ Performance
- 🔄 Caching
- 🔒 Security
- 🗂️ Data management
- 🐛 Bug fixes
