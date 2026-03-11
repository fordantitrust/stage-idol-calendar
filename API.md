# 🔌 API Documentation

All API endpoints for Idol Stage Timetable v2.7.2

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

No login required — supports HTTP cache (ETag + 304 Not Modified)

### Endpoints

| Endpoint | Method | Cache | Description |
|----------|--------|-------|-------------|
| `/api.php?action=programs` | GET | 5 min | All programs |
| `/api.php?action=programs&event=slug` | GET | 5 min | Filter by event slug |
| `/api.php?action=programs&organizer=X` | GET | 5 min | Filter by artist |
| `/api.php?action=programs&location=X` | GET | 5 min | Filter by venue |
| `/api.php?action=programs&type=X` | GET | 5 min | Filter by program type |
| `/api.php?action=organizers` | GET | 5 min | All artist names |
| `/api.php?action=locations` | GET | 5 min | All venue names |
| `/api.php?action=events_list` | GET | 10 min | All active events |

### Response Format

```json
{
  "success": true,
  "data": [...],
  "generated_at": "2026-03-01 10:00:00"
}
```

### Example: Programs

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

Send `If-None-Match: "abc123"` → receives `304 Not Modified` if data has not changed.

---

## 📝 Request API (`api/request.php`)

Users submit requests to add/modify programs — Rate limited: **10 requests/hour/IP**

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/request.php?action=submit` | POST | Submit a request |
| `/api/request.php?action=programs` | GET | Get program list (for modal) |

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

**type**: `"add"` (new entry) or `"modify"` (edit — requires `program_id`)

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

All endpoints require:
1. **Session** — `$_SESSION['admin_logged_in'] === true` (login at `/admin/login`)
2. **IP Whitelist** — if enabled in `config/admin.php`

### CSRF Protection

POST/PUT/DELETE requests must include the header:
```http
X-CSRF-Token: <token>
```

Token is obtained from `generate_csrf_token()` — embedded in the Admin Panel HTML.

---

### Programs Endpoints

Programs = individual shows in the `programs` table

| Action | Method | Description |
|--------|--------|-------------|
| `programs_list` | GET | List programs (pagination, search, filter, sort) |
| `programs_get` | GET | Get a single program |
| `programs_create` | POST | Create a new program |
| `programs_update` | PUT | Update a program |
| `programs_delete` | DELETE | Delete a program |
| `programs_bulk_delete` | DELETE | Delete multiple programs (up to 100) |
| `programs_bulk_update` | PUT | Update multiple programs (venue/organizer/categories) |
| `programs_venues` | GET | All venue names (for autocomplete) |
| `programs_types` | GET | All program types (for autocomplete) |

#### List Programs

```http
GET /admin/api.php?action=programs_list&page=1&limit=20&search=keyword&location=venue&event_meta_id=1&sort=start&order=asc
```

Parameters:
| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number (default: 1) |
| `limit` | int | 20/50/100 (default: 20) |
| `search` | string | Search by title/organizer |
| `location` | string | Filter by venue |
| `event_meta_id` | int | Filter by event ID |
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

User requests to add/modify programs

| Action | Method | Description |
|--------|--------|-------------|
| `requests` | GET | List requests (filter by status, event_meta_id) |
| `pending_count` | GET | Count of pending requests (for badge) |
| `request_approve` | PUT | Approve request → auto-create/update program |
| `request_reject` | PUT | Reject request |

#### List Requests

```http
GET /admin/api.php?action=requests&status=pending&event_meta_id=1
```

**status**: `pending` / `approved` / `rejected` / (omit for all)

#### Approve / Reject

```http
PUT /admin/api.php?action=request_approve&id=10
X-CSRF-Token: <token>
```

```http
PUT /admin/api.php?action=request_reject&id=10
X-CSRF-Token: <token>
```

When approved (`approve`), the system will:
- `type=add` → INSERT into `programs` table
- `type=modify` → UPDATE program matching `program_id`

---

### ICS Import Endpoints

| Action | Method | Description |
|--------|--------|-------------|
| `upload_ics` | POST | Upload + parse .ics file (max 5MB) |
| `import_ics_confirm` | POST | Confirm import (choose insert/update/skip per event) |

#### Upload ICS

```http
POST /admin/api.php?action=upload_ics
Content-Type: multipart/form-data
X-CSRF-Token: <token>

ics_file: <file.ics>
event_meta_id: 1
```

Response: list of parsed events + status (new/duplicate)

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

Events = convention/event metadata in the `events` table (formerly `events_meta`)

| Action | Method | Description |
|--------|--------|-------------|
| `events_list` | GET | List all events |
| `events_get` | GET | Get a single event |
| `events_create` | POST | Create a new event |
| `events_update` | PUT | Update an event |
| `events_delete` | DELETE | Delete an event |

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

**venue_mode**: `"multi"` (multiple venues) or `"single"` (single venue)

#### Events in Public URL

Access an event via URL: `/event/{slug}` e.g. `/event/idol-stage-feb-2026`

---

### Credits Endpoints

| Action | Method | Description |
|--------|--------|-------------|
| `credits_list` | GET | List credits (pagination, search, sort) |
| `credits_get` | GET | Get a single credit |
| `credits_create` | POST | Create a new credit |
| `credits_update` | PUT | Update a credit |
| `credits_delete` | DELETE | Delete a credit |
| `credits_bulk_delete` | DELETE | Delete multiple credits |

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

> **Cache**: Credits cache (`cache/credits.json`) is automatically invalidated after create/update/delete.

---

### Backup/Restore Endpoints

**admin role only** — not available to agent role

| Action | Method | Description |
|--------|--------|-------------|
| `backup_create` | POST | Create a new backup (stored in `backups/`) |
| `backup_list` | GET | List all backups |
| `backup_download` | GET | Download a backup file |
| `backup_delete` | DELETE | Delete a backup file |
| `backup_restore` | POST | Restore from a file on the server |
| `backup_upload_restore` | POST | Upload .db file and restore immediately |

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

> **Auto-backup**: The system automatically creates a backup before every restore.

#### Upload & Restore

```http
POST /admin/api.php?action=backup_upload_restore
Content-Type: multipart/form-data
X-CSRF-Token: <token>

db_file: <calendar.db>
```

---

### User Management Endpoints (admin only)

**admin role only** — for managing admin users

| Action | Method | Description |
|--------|--------|-------------|
| `users_list` | GET | List all users |
| `users_get` | GET | Get a single user |
| `users_create` | POST | Create a new user |
| `users_update` | PUT | Update a user (password optional) |
| `users_delete` | DELETE | Delete a user |

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

**role**: `"admin"` (full access) or `"agent"` (programs management only)

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

`password` — optional: if not provided, the password remains unchanged.

**Safety guards**:
- Cannot delete yourself
- Cannot change your own role
- At least one admin must remain

---

### Account Endpoint

| Action | Method | Description |
|--------|--------|-------------|
| `change_password` | POST | Change your own password |

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

> Must be logged in via the `admin_users` table (config fallback not supported).

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

- [README.md](README.md) — Project overview + Quick Start
- [INSTALLATION.md](INSTALLATION.md) — Detailed installation guide
- [SQLITE_MIGRATION.md](SQLITE_MIGRATION.md) — Database schema
- [SECURITY.md](SECURITY.md) — Security policy

---

*Idol Stage Timetable v2.7.2*
