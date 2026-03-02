# 🔌 API Documentation

API endpoints ทั้งหมดของ Idol Stage Timetable v2.4.3

---

## 📑 Table of Contents

- [Public API](#-public-api-apiphp)
- [Request API](#-request-api-apirequestphp)
- [Admin API](#-admin-api-adminapiphp)
  - [Authentication](#authentication)
  - [Programs](#programs-endpoints)
  - [Requests](#requests-endpoints)
  - [ICS Import](#ics-import-endpoints)
  - [Events (Meta)](#events-meta-endpoints)
  - [Credits](#credits-endpoints)
  - [Backup/Restore](#backuprestore-endpoints)
  - [User Management](#user-management-endpoints-admin-only)
  - [Account](#account-endpoint)

---

## 🌐 Public API (`api.php`)

ไม่ต้อง login — รองรับ HTTP cache (ETag + 304 Not Modified)

### Endpoints

| Endpoint | Method | Cache | Description |
|----------|--------|-------|-------------|
| `/api.php?action=programs` | GET | 5 min | Programs ทั้งหมด |
| `/api.php?action=programs&event=slug` | GET | 5 min | Filter ตาม event slug |
| `/api.php?action=programs&organizer=X` | GET | 5 min | Filter ตามศิลปิน |
| `/api.php?action=programs&location=X` | GET | 5 min | Filter ตามเวที |
| `/api.php?action=organizers` | GET | 5 min | รายชื่อศิลปินทั้งหมด |
| `/api.php?action=locations` | GET | 5 min | รายชื่อเวทีทั้งหมด |
| `/api.php?action=events_list` | GET | 10 min | รายการ events ที่ active ทั้งหมด |

### Response Format

```json
{
  "success": true,
  "data": [...],
  "generated_at": "2026-03-01 10:00:00"
}
```

### ตัวอย่าง: Programs

```http
GET /api.php?action=programs&event=idol-stage-feb-2026
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "uid": "event-001@example.com",
      "title": "Morning Show",
      "start": "2026-02-07 10:00:00",
      "end": "2026-02-07 11:00:00",
      "location": "Main Stage",
      "organizer": "Artist Name",
      "categories": "Artist Name",
      "description": ""
    }
  ]
}
```

### HTTP Cache Headers

```http
ETag: "abc123"
Cache-Control: public, max-age=300
```

ส่ง `If-None-Match: "abc123"` → รับ `304 Not Modified` ถ้าข้อมูลไม่เปลี่ยน

---

## 📝 Request API (`api/request.php`)

ผู้ใช้ส่งคำขอเพิ่ม/แก้ไข program — Rate limited: **10 requests/ชั่วโมง/IP**

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/request.php?action=submit` | POST | ส่งคำขอ |
| `/api/request.php?action=programs` | GET | ดึงรายการ programs (สำหรับ modal) |

### Submit Request

```http
POST /api/request.php?action=submit
Content-Type: application/json

{
  "type": "add",
  "title": "New Show",
  "start": "2026-03-01 10:00:00",
  "end": "2026-03-01 11:00:00",
  "location": "Stage A",
  "organizer": "Artist",
  "categories": "Artist",
  "description": "",
  "requester_name": "John",
  "requester_email": "john@example.com",
  "requester_note": "Please add this show",
  "event_id": 1
}
```

**type**: `"add"` (เพิ่มใหม่) หรือ `"modify"` (แก้ไข — ต้องมี `program_id`)

Response:
```json
{
  "success": true,
  "message": "Request submitted successfully"
}
```

---

## 🔐 Admin API (`admin/api.php`)

### Authentication

ทุก endpoint ต้องผ่าน:
1. **Session** — `$_SESSION['admin_logged_in'] === true` (login ที่ `/admin/login`)
2. **IP Whitelist** — ถ้าเปิดใช้ใน `config/admin.php`

### CSRF Protection

POST/PUT/DELETE requests ต้องส่ง header:
```http
X-CSRF-Token: <token>
```

Token ได้จาก `generate_csrf_token()` — ฝังใน HTML ของ Admin Panel

---

### Programs Endpoints

Programs = รายการแสดง (individual shows) ใน `programs` table

| Action | Method | Description |
|--------|--------|-------------|
| `programs_list` | GET | รายการ programs (pagination, search, filter, sort) |
| `programs_get` | GET | ดึง program เดียว |
| `programs_create` | POST | สร้าง program ใหม่ |
| `programs_update` | PUT | แก้ไข program |
| `programs_delete` | DELETE | ลบ program |
| `programs_bulk_delete` | DELETE | ลบหลาย programs (สูงสุด 100) |
| `programs_bulk_update` | PUT | แก้ไขหลาย programs (venue/organizer/categories) |
| `programs_venues` | GET | รายชื่อเวทีทั้งหมด (สำหรับ autocomplete) |

#### List Programs

```http
GET /admin/api.php?action=programs_list&page=1&limit=20&search=keyword&location=venue&event_meta_id=1&sort=start&order=asc
```

Parameters:
| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | หน้า (default: 1) |
| `limit` | int | 20/50/100 (default: 20) |
| `search` | string | ค้นหา title/organizer |
| `location` | string | กรองเวที |
| `event_meta_id` | int | กรองตาม event ID |
| `sort` | string | start/title/location/organizer |
| `order` | string | asc/desc |

Response:
```json
{
  "success": true,
  "data": {
    "programs": [...],
    "total": 100,
    "page": 1,
    "limit": 20,
    "totalPages": 5
  }
}
```

#### Create Program

```http
POST /admin/api.php?action=programs_create
Content-Type: application/json
X-CSRF-Token: <token>

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

#### Update Program

```http
PUT /admin/api.php?action=programs_update&id=5
Content-Type: application/json
X-CSRF-Token: <token>

{ "title": "Updated Title", "location": "Stage B", ... }
```

#### Delete Program

```http
DELETE /admin/api.php?action=programs_delete&id=5
X-CSRF-Token: <token>
```

#### Bulk Delete

```http
DELETE /admin/api.php?action=programs_bulk_delete
Content-Type: application/json
X-CSRF-Token: <token>

{ "ids": [1, 2, 3, 4, 5] }
```

#### Bulk Update (venue/organizer/categories)

```http
PUT /admin/api.php?action=programs_bulk_update
Content-Type: application/json
X-CSRF-Token: <token>

{
  "ids": [1, 2, 3],
  "location": "Stage B",
  "organizer": "New Artist",
  "categories": "New Artist"
}
```

---

### Requests Endpoints

User requests สำหรับเพิ่ม/แก้ไข programs

| Action | Method | Description |
|--------|--------|-------------|
| `requests` | GET | รายการ requests (filter by status, event_meta_id) |
| `pending_count` | GET | จำนวน pending requests (สำหรับ badge) |
| `request_approve` | PUT | อนุมัติ request → auto-create/update program |
| `request_reject` | PUT | ปฏิเสธ request |

#### List Requests

```http
GET /admin/api.php?action=requests&status=pending&event_meta_id=1
```

**status**: `pending` / `approved` / `rejected` / (ไม่ระบุ = ทั้งหมด)

#### Approve / Reject

```http
PUT /admin/api.php?action=request_approve&id=10
X-CSRF-Token: <token>
```

```http
PUT /admin/api.php?action=request_reject&id=10
X-CSRF-Token: <token>
```

เมื่ออนุมัติ (`approve`) ระบบจะ:
- `type=add` → INSERT ลง `programs` table
- `type=modify` → UPDATE program ที่มี `program_id` ตรงกัน

---

### ICS Import Endpoints

| Action | Method | Description |
|--------|--------|-------------|
| `upload_ics` | POST | อัพโหลด + parse .ics (สูงสุด 5MB) |
| `import_ics_confirm` | POST | ยืนยัน import (เลือก insert/update/skip แต่ละ event) |

#### Upload ICS

```http
POST /admin/api.php?action=upload_ics
Content-Type: multipart/form-data
X-CSRF-Token: <token>

ics_file: <file.ics>
event_meta_id: 1
```

Response: รายการ events ที่ parse ได้ + สถานะ (new/duplicate)

#### Confirm Import

```http
POST /admin/api.php?action=import_ics_confirm
Content-Type: application/json
X-CSRF-Token: <token>

{
  "event_meta_id": 1,
  "events": [
    { "uid": "event-001@...", "action": "insert", "title": "...", ... },
    { "uid": "event-002@...", "action": "update", ... },
    { "uid": "event-003@...", "action": "skip" }
  ],
  "save_ics": true
}
```

---

### Events (Meta) Endpoints

Events = งาน/convention metadata ใน `events` table (formerly `events_meta`)

| Action | Method | Description |
|--------|--------|-------------|
| `events_list` | GET | รายการ events ทั้งหมด |
| `events_get` | GET | ดึง event เดียว |
| `events_create` | POST | สร้าง event ใหม่ |
| `events_update` | PUT | แก้ไข event |
| `events_delete` | DELETE | ลบ event |

#### Create Event

```http
POST /admin/api.php?action=events_create
Content-Type: application/json
X-CSRF-Token: <token>

{
  "name": "Idol Stage Feb 2026",
  "slug": "idol-stage-feb-2026",
  "description": "Annual idol event",
  "start_date": "2026-02-07",
  "end_date": "2026-02-08",
  "venue_mode": "multi",
  "is_active": true,
  "email": "contact@idol-stage.com"
}
```

**venue_mode**: `"multi"` (หลายเวที) หรือ `"single"` (เวทีเดียว)

#### Events ใน Public URL

เข้าถึง event ผ่าน URL: `/event/{slug}` เช่น `/event/idol-stage-feb-2026`

---

### Credits Endpoints

| Action | Method | Description |
|--------|--------|-------------|
| `credits_list` | GET | รายการ credits (pagination, search, sort) |
| `credits_get` | GET | ดึง credit เดียว |
| `credits_create` | POST | สร้าง credit ใหม่ |
| `credits_update` | PUT | แก้ไข credit |
| `credits_delete` | DELETE | ลบ credit |
| `credits_bulk_delete` | DELETE | ลบหลาย credits |

#### List Credits

```http
GET /admin/api.php?action=credits_list&page=1&limit=20&search=keyword&sort=display_order&order=asc&event_meta_id=1
```

#### Create Credit

```http
POST /admin/api.php?action=credits_create
Content-Type: application/json
X-CSRF-Token: <token>

{
  "title": "Data Source",
  "link": "https://example.com",
  "description": "Official schedule",
  "display_order": 0,
  "event_id": 1
}
```

> **Cache**: Credits cache (`cache/credits.json`) จะถูก invalidate อัตโนมัติหลัง create/update/delete

---

### Backup/Restore Endpoints

**admin role only** — agent ไม่มีสิทธิ์

| Action | Method | Description |
|--------|--------|-------------|
| `backup_create` | POST | สร้าง backup ใหม่ (เก็บใน `backups/`) |
| `backup_list` | GET | รายการ backup ทั้งหมด |
| `backup_download` | GET | ดาวน์โหลดไฟล์ backup |
| `backup_delete` | DELETE | ลบไฟล์ backup |
| `backup_restore` | POST | Restore จากไฟล์บน server |
| `backup_upload_restore` | POST | Upload .db แล้ว restore ทันที |

#### Create Backup

```http
POST /admin/api.php?action=backup_create
X-CSRF-Token: <token>
```

Response:
```json
{
  "success": true,
  "filename": "backup_20260301_100000.db",
  "message": "Backup created successfully"
}
```

#### Restore from Server

```http
POST /admin/api.php?action=backup_restore
Content-Type: application/json
X-CSRF-Token: <token>

{ "filename": "backup_20260301_100000.db" }
```

> **Auto-backup**: ระบบจะสร้าง backup อัตโนมัติก่อนทุกการ restore

#### Upload & Restore

```http
POST /admin/api.php?action=backup_upload_restore
Content-Type: multipart/form-data
X-CSRF-Token: <token>

db_file: <calendar.db>
```

---

### User Management Endpoints (admin only)

**admin role only** — ใช้สำหรับจัดการ admin users

| Action | Method | Description |
|--------|--------|-------------|
| `users_list` | GET | รายการ users ทั้งหมด |
| `users_get` | GET | ดึง user เดียว |
| `users_create` | POST | สร้าง user ใหม่ |
| `users_update` | PUT | แก้ไข user (password optional) |
| `users_delete` | DELETE | ลบ user |

#### Create User

```http
POST /admin/api.php?action=users_create
Content-Type: application/json
X-CSRF-Token: <token>

{
  "username": "newagent",
  "password": "securepassword",
  "display_name": "New Agent",
  "role": "agent"
}
```

**role**: `"admin"` (full access) หรือ `"agent"` (programs management only)

#### Update User

```http
PUT /admin/api.php?action=users_update&id=3
Content-Type: application/json
X-CSRF-Token: <token>

{
  "display_name": "Updated Name",
  "role": "admin",
  "is_active": true,
  "password": "newpassword"
}
```

`password` — optional: ถ้าไม่ส่ง ไม่เปลี่ยน password

**Safety guards**:
- ห้ามลบตัวเอง
- ห้ามเปลี่ยน role ตัวเอง
- ต้องเหลือ admin อย่างน้อย 1 คน

---

### Account Endpoint

| Action | Method | Description |
|--------|--------|-------------|
| `change_password` | POST | เปลี่ยน password ตัวเอง |

```http
POST /admin/api.php?action=change_password
Content-Type: application/json
X-CSRF-Token: <token>

{
  "current_password": "oldpassword",
  "new_password": "newpassword",
  "confirm_password": "newpassword"
}
```

> ต้อง login ผ่าน `admin_users` table (ไม่รองรับ config fallback)

---

## 🎯 Role-Based Access

| Feature | admin | agent |
|---------|-------|-------|
| Programs (CRUD, bulk) | ✅ | ✅ |
| Requests (view, approve, reject) | ✅ | ✅ |
| ICS Import | ✅ | ✅ |
| Credits (CRUD, bulk) | ✅ | ✅ |
| Events/Conventions (CRUD) | ✅ | ✅ |
| User Management | ✅ | ❌ |
| Backup/Restore | ✅ | ❌ |
| Change own password | ✅ | ✅ |

---

## 🔗 Related Documentation

- [README.md](README.md) — ภาพรวม + Quick Start
- [INSTALLATION.md](INSTALLATION.md) — การติดตั้งโดยละเอียด
- [SQLITE_MIGRATION.md](SQLITE_MIGRATION.md) — Database schema
- [SECURITY.md](SECURITY.md) — Security policy

---

*Idol Stage Timetable v2.4.3*
