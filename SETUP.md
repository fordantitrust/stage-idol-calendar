# Setup & Installation Guide

คู่มือการติดตั้งระบบ Idol Stage Timetable ผ่านหน้า Setup Wizard (`setup.php`)

---

## ภาพรวม

`setup.php` คือหน้า Setup Wizard สำหรับ:
- **Fresh Install** — ติดตั้งระบบครั้งแรก ทำ 6 ขั้นตอนจบในหน้าเดียว (รวม Production Cleanup)
- **Maintenance** — ตรวจสอบสถานะระบบ, import ข้อมูลเพิ่ม, เปลี่ยน password

---

## การเข้าถึง

เปิด `https://your-domain.com/setup.php` (หรือ `http://localhost:8000/setup.php`)

### Auth Gate

| สถานะระบบ | การเข้าถึง |
|-----------|-----------|
| Fresh install (ยังไม่มี `admin_users` table) | เข้าได้โดยไม่ต้อง login |
| Existing install (มี admin users แล้ว) | ต้อง login admin ก่อน |
| Setup ถูก Lock | แสดงข้อความ locked, เข้าไม่ได้ |

---

## 6 ขั้นตอน Setup

### ขั้นตอนที่ 1 — System Requirements

ตรวจสอบว่าระบบพร้อมใช้งาน:

| รายการ | ต้องการ |
|--------|--------|
| PHP Version | 8.1 ขึ้นไป |
| Extension: PDO | ✅ ต้องติดตั้ง |
| Extension: PDO_SQLite | ✅ ต้องติดตั้ง |
| Extension: mbstring | ✅ ต้องติดตั้ง |
| เขียนไฟล์ได้ | `data/`, `cache/`, `backups/` ต้อง writable |

หากพบ ❌ ให้แก้ไข PHP configuration ก่อนดำเนินการต่อ

---

### ขั้นตอนที่ 2 — Directories & Permissions

ตรวจสอบและสร้างโฟลเดอร์ที่จำเป็น:

| โฟลเดอร์ | หน้าที่ |
|---------|--------|
| `data/` | เก็บ SQLite database (`calendar.db`) |
| `cache/` | เก็บ cache files (data version, credits, login attempts) |
| `backups/` | เก็บไฟล์ backup database |
| `ics/` | เก็บไฟล์ `.ics` สำหรับ import |

กดปุ่ม **"สร้าง Directories"** เพื่อสร้างโฟลเดอร์ที่ยังไม่มีอัตโนมัติ

> **หมายเหตุ**: หากมีปัญหา permission บน Linux/Mac ให้รัน:
> ```bash
> chmod 755 data/ cache/ backups/ ics/
> ```

---

### ขั้นตอนที่ 3 — Database Setup

สร้างตาราง SQLite ทั้งหมดและ seed ข้อมูลเริ่มต้น

กดปุ่ม **"Initialize Database"** เพื่อ:
1. สร้างไฟล์ `data/calendar.db`
2. สร้างตารางทั้งหมด:
   - `programs` — ตารางแสดง (individual shows)
   - `events` — งาน/events (meta, เดิมเรียก conventions)
   - `program_requests` — คำขอเพิ่ม/แก้ไขจากผู้ใช้
   - `credits` — เครดิต/อ้างอิง
   - `admin_users` — admin users
3. Seed admin user เริ่มต้น (จาก `config/admin.php`)
4. Seed default event (slug: `default`) และ **3 sample programs** ตัวอย่าง เพื่อให้เห็น layout จริงทันที
   - Opening Ceremony, Artist Performance, Closing Stage (วันปัจจุบัน)
   - แก้ไขหรือลบได้ภายหลังจาก Admin › Programs
5. **Auto-login** — session จะ login อัตโนมัติหลัง initialize สำเร็จ

**Credentials เริ่มต้น:**

ระบบจะแสดงกล่อง credentials หลัง initialize สำเร็จ เช่น:

```
Username: admin
Password: admin123
```

> ⚠️ **สำคัญ**: จด credentials ไว้ก่อนออกจากหน้านี้ แล้วเปลี่ยนรหัสผ่านทันทีใน Step 5 / ลบไฟล์ dev ใน Step 6

---

### ขั้นตอนที่ 4 — Import ข้อมูล Programs

Import ข้อมูลจากไฟล์ `.ics` เข้าฐานข้อมูล

**วิธีใช้:**

1. วางไฟล์ `.ics` ในโฟลเดอร์ `ics/`
2. กดปุ่ม **"Import ICS Files"**
3. ระบบจะ parse และ import events ทั้งหมด
4. แสดงจำนวน programs ที่ import สำเร็จ

**ถ้าไม่มีไฟล์ .ics:**

สามารถข้ามขั้นตอนนี้ไปก่อน แล้วเพิ่มข้อมูลผ่าน Admin Panel ภายหลัง (`/admin`)

**Import ผ่าน command line** (ทางเลือก):

```bash
php tools/import-ics-to-sqlite.php
# หรือระบุ event slug:
php tools/import-ics-to-sqlite.php --event=my-event-slug
```

---

### ขั้นตอนที่ 5 — Admin & Security Setup

#### 5.1 ตรวจสอบและเปลี่ยน Admin Password

หากใช้รหัสผ่านเริ่มต้น ระบบจะแสดงแบบฟอร์มเปลี่ยนรหัสผ่าน inline:

1. กรอก **New Password** (ขั้นต่ำ 8 ตัวอักษร)
2. กรอก **Confirm Password** (ต้องตรงกัน)
3. กด **"บันทึก Password ใหม่"**
4. กล่องเตือนจะหายไปเมื่อเปลี่ยนสำเร็จ

> หลังเปลี่ยน password แล้ว ให้ทดสอบ login ที่ `/admin/login` ก่อน lock setup

#### 5.2 เพิ่ม Database Indexes (แนะนำ)

กดปุ่ม **"Add Indexes"** เพื่อเพิ่ม performance indexes ใน SQLite:
- เพิ่มความเร็ว query 2–5x สำหรับ queries ที่ใช้บ่อย
- Idempotent — รันซ้ำได้ปลอดภัย

#### 5.3 Lock Setup Page

เมื่อ setup เสร็จสมบูรณ์ ให้ล็อกหน้านี้ทันที:

กดปุ่ม **"🔒 Lock Setup"** → สร้างไฟล์ `data/.setup_locked`

หลัง lock แล้ว ผู้อื่นจะเข้าหน้า setup ไม่ได้

**Unlock** (กรณีต้องการ maintenance):
1. ลบไฟล์ `data/.setup_locked` (ผ่าน SSH/FTP) หรือ
2. Login admin แล้วเข้า setup อีกครั้ง → กดปุ่ม **"🔓 Unlock"**

---

## Flow สำหรับ Fresh Install

```
เปิด setup.php
    ↓
[Step 1] ตรวจสอบ PHP ✅
    ↓
[Step 2] สร้าง Directories ✅
    ↓
[Step 3] Initialize Database → Auto-login → บันทึก credentials
    ↓
[Step 4] Import .ics files (ถ้ามี)
    ↓
[Step 5] เปลี่ยน password → Add indexes → Lock setup
    ↓
[Step 6] Production Cleanup — ลบ dev/docs files (checkbox grouped)
    ↓
เข้าใช้งาน /admin ✅
```

---

## Troubleshooting

### หน้า setup ไม่ขึ้น / redirect ไป login
- มี admin users ใน DB แล้ว → ต้อง login admin ก่อน
- ไปที่ `/admin/login` แล้ว login จากนั้นกลับมา `/setup.php`

### "Setup is Locked"
- ไฟล์ `data/.setup_locked` มีอยู่
- Login admin แล้วเปิด `setup.php` → กดปุ่ม Unlock หรือลบไฟล์ lock ผ่าน SSH/FTP

### Initialize Database ไม่สำเร็จ
- ตรวจสอบว่าโฟลเดอร์ `data/` มีอยู่และ writable
- ตรวจสอบ PHP มี extension `pdo_sqlite`

### Import ICS ไม่มีข้อมูล
- ตรวจสอบว่าไฟล์ `.ics` อยู่ใน `ics/` directory
- ตรวจสอบ format ไฟล์ ดู [README.md](README.md) สำหรับ ICS format

### กล่องเตือน password ไม่หายหลังเปลี่ยน
- ตรวจสอบว่ากรอก password ถูกต้อง (ขั้นต่ำ 8 ตัวอักษร และต้องตรงกัน)
- หน้าจะ reload อัตโนมัติหลังเปลี่ยนสำเร็จ กล่องเตือนจะหายไป

---

## Security Notes

- **ล็อก setup ทุกครั้ง** หลัง setup เสร็จ — setup.php มีอำนาจ initialize/override ฐานข้อมูล
- **เปลี่ยน default password** ก่อนใช้งานจริงใน production
- หากต้องการ maintenance ให้ unlock ผ่าน admin session เท่านั้น ไม่ควรลบไฟล์ lock โดยตรงใน production

---

## ไฟล์ที่เกี่ยวข้อง

| ไฟล์ | หน้าที่ |
|------|--------|
| `setup.php` | Setup Wizard หลัก |
| `data/.setup_locked` | Lock file (มีอยู่ = locked) |
| `data/calendar.db` | SQLite database |
| `config/admin.php` | Admin credentials (fallback) |
| `config/app.php` | App version และ settings |
| `tools/import-ics-to-sqlite.php` | CLI import tool |
| `tools/migrate-add-indexes.php` | DB performance indexes |
| `tools/migrate-add-event-email-column.php` | เพิ่ม email column ใน events table |

---

*Idol Stage Timetable v2.4.3*
