# 🔌 API Documentation

All API endpoints for Idol Stage Timetable v16.5.1

---

## 📑 Table of Contents

- [Public API](#-public-api-apiphp)
- [Request API](#-request-api-apirequestphp)
- [Event Request API](#-event-request-api-apievent-requestphp)
- [Admin API](#-admin-api-adminapiphp)
  - [Authentication](#authentication)
  - [Programs](#programs-endpoints)
  - [Requests](#requests-endpoints)
  - [Event Requests](#event-requests-endpoints)
  - [Artist Requests](#artist-requests-endpoints-v1230)
  - [ICS Import](#ics-import-endpoints)
  - [Events (Meta)](#events-meta-endpoints)
  - [Event Cover Images](#event-cover-images-endpoints)
  - [Event Pictures](#event-pictures-endpoints-v700)
  - [Credits](#credits-endpoints)
  - [Backup/Restore](#backuprestore-endpoints)
  - [User Management](#user-management-endpoints-admin-only)
  - [Settings](#settings-endpoints)
  - [Site Cover Image](#site-cover-image-endpoints-v920)
  - [Contact Channels](#contact-channels-endpoints)
  - [Artists](#artists-endpoints-v900)
  - [Artist Variants](#artist-variants-endpoints)
  - [Email Config](#email-config-endpoints)
  - [Google Config (Analytics + AdSense)](#google-config-endpoints)
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
| `/api.php?action=search&q=keyword` | GET | — | FTS5 full-text search across programs, events, and artists (v9.0.0+) |

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
      "description": "",
      "program_type": "Live Stream",
      "stream_url": "https://www.youtube.com/live/..."
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

## 🗓️ Event Request API (`api/event-request.php`) *(v9.3.0)*

Users submit proposals to add new events — Rate limited: **10 requests/hour/IP** (uses `evrate_` prefix, separate from program request rate limiting)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/event-request.php?action=submit` | POST | Submit a new event proposal (add-only) |
| `/api/event-request.php?action=events` | GET | Get list of active events (for reference) |

### Submit Event Request

```http
POST /api/event-request.php?action=submit
Content-Type: application/json

{
  "type": "add",
  "name": "Idol Stage Summer 2026",
  "description": "Annual summer idol event with 50+ artists",
  "start_date": "2026-07-15",
  "end_date": "2026-07-16",
  "requester_name": "Jane",
  "requester_email": "jane@example.com",
  "note": "Please consider adding this event"
}
```

**type**: Only `"add"` is accepted (propose a new event)

Response:
```json
{
  "success": true,
  "data": { "id": 42 },
  "message": "Request submitted"
}
```

### Get Active Events (for reference)

```http
GET /api/event-request.php?action=events
```

Response:
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Idol Stage Feb 2026", "start_date": "2026-02-07", "end_date": "2026-02-09" }
  ]
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
  "program_type": "Live Stream",
  "stream_url": "https://www.youtube.com/live/channel",
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

### Event Requests Endpoints

User proposals to add/modify events (from `api/event-request.php`) and organizer requests to activate assigned events. Stored in `event_requests` table. *(v9.3.0, activation requests in v12.1.0)*

| Action | Method | Description |
|--------|--------|-------------|
| `event_requests_list` | GET | List event requests (pagination + status filter) |
| `event_request_approve` | PUT | Approve event request (type=add → INSERT new event; type=modify → UPDATE fields; type=activate → set active) |
| `event_request_reject` | PUT | Reject event request |
| `event_request_pending_count` | GET | Count pending event requests (used in unified badge) |
| `events_request_activate` | POST | Organizer-only request to activate an assigned inactive event |

#### List Event Requests

```http
GET /admin/api.php?action=event_requests_list&request_group=guest&status=pending&page=1&limit=20
```

**status**: `pending` / `approved` / `rejected` / (omit for all)

**request_group**:
- `guest` → public guest Event Requests (`add` / `modify`)
- `active` → organizer Event Active Requests (`activate`)
- `all` or omitted → all event request types

#### Approve Event Request

```http
PUT /admin/api.php?action=event_request_approve&id=5
Content-Type: application/json
X-CSRF-Token: <token>

{ "admin_note": "Added to schedule — see idol-stage-summer-2026" }
```

When approved:
- `type=add` → INSERT into `events` table with auto-generated slug; `is_active=0` (admin activates manually)
- `type=modify` → UPDATE non-empty fields of the existing event
- `type=activate` → UPDATE the existing event to `is_active=1`

Organizer users cannot set `is_active=1` directly when creating or editing events. They use:

```http
POST /admin/api.php?action=events_request_activate
Content-Type: application/json
X-CSRF-Token: <token>

{ "event_id": 5, "note": "Ready for publication" }
```

Admin and agent users approve or reject the activation request from Admin › Requests › Event Requests.

#### Reject Event Request

```http
PUT /admin/api.php?action=event_request_reject&id=5
Content-Type: application/json
X-CSRF-Token: <token>

{ "admin_note": "Not enough information provided" }
```

> **Unified Pending Badge**: `pending_count` action sums both `program_requests` AND `event_requests` pending counts. If `event_requests` table does not exist yet (pre-migration), it gracefully falls back to counting program requests only.

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
  "theme": "sakura",
  "email": "contact@idol-stage.com",
  "ticket_url": "https://tickets.example.com/idol-stage"
}
```

**venue_mode**: `"multi"` (multiple venues + Gantt view) / `"single"` (single venue) / `"calendar"` (monthly calendar grid, v2.7.0+)

**theme**: one of `sakura`, `ocean`, `forest`, `midnight`, `sunset`, `dark`, `gray`, `crimson`, `teal`, `rose`, `amber`, `indigo` (12 themes total; or omit to use global setting)

**gallery_template** *(v7.0.0)*: `"grid1"` (1 column) / `"grid2"` (2 columns) / `"grid3"` (3 columns, default) / `"masonry"` (CSS column-count layout)

**ticket_url** *(v9.5.0)*: optional URL (http/https) to the ticket purchase page — displays an orange "🎟️ ซื้อบัตร" button in the event-detail header nav

---

### Event Cover Images Endpoints

Per-event cover images — Hero (16:9), Card (4:3), and Header Cover (4:1). Require admin login + CSRF. *(v8.0.0, header cover v9.2.0)*

| Action | Method | Description |
|--------|--------|-------------|
| `event_cover_upload` | POST | Upload Hero (16:9, 1600×900) or Card (4:3, 800×600) cover image via Cropper.js |
| `event_cover_delete` | POST | Delete a Hero or Card cover image |
| `event_header_cover_upload` | POST | Upload per-event Header Cover (4:1, 1920×480) |
| `event_header_cover_delete` | POST | Delete per-event Header Cover |

#### Upload Cover Image (Hero / Card)

```http
POST /admin/api.php?action=event_cover_upload
Content-Type: multipart/form-data
X-CSRF-Token: <token>

cover_image=<cropped-file>
event_id=1
cover_type=hero
```

**cover_type**: `"hero"` (stored in `events.cover_image`) or `"card"` (stored in `events.cover_image_card`)

#### Upload Event Header Cover

```http
POST /admin/api.php?action=event_header_cover_upload
Content-Type: multipart/form-data
X-CSRF-Token: <token>

cover_image=<cropped-file>
event_id=1
```

Stores in `events.header_cover_image`. Cropped client-side to 4:1 ratio before upload.

#### Delete Cover Image

```http
POST /admin/api.php?action=event_cover_delete
Content-Type: application/json
X-CSRF-Token: <token>

{ "event_id": 1, "cover_type": "hero" }
```

```http
POST /admin/api.php?action=event_header_cover_delete
Content-Type: application/json
X-CSRF-Token: <token>

{ "event_id": 1 }
```

---

### Event Pictures Endpoints *(v7.0.0)*

| Action | Method | Description |
|--------|--------|-------------|
| `event_pictures_list` | GET | List pictures for an event |
| `event_picture_upload` | POST | Upload a picture (multipart/form-data) |
| `event_picture_delete` | POST | Delete a picture |
| `event_pictures_reorder` | POST | Reorder pictures |

#### List Event Pictures

```http
GET /admin/api.php?action=event_pictures_list&event_id=1
```

Returns `{ pictures: [{ id, filename, caption, display_order }] }`

#### Upload Event Picture

```http
POST /admin/api.php?action=event_picture_upload&event_id=1
X-CSRF-Token: <token>
Content-Type: multipart/form-data

picture=<file>
```

- Max 5 MB per file; accepted MIME types: `image/jpeg`, `image/png`, `image/gif`, `image/webp`
- PHP GD scales image to fit within 1200×900 px (no upscale, no crop); saves as JPEG 85%
- Stored in `uploads/events/{eventId}/{uniqid}.jpg` (sharded by event ID, v7.2.0+)
- Returns `{ id, filename, caption }`

#### Delete Event Picture

```http
POST /admin/api.php?action=event_picture_delete
Content-Type: application/json
X-CSRF-Token: <token>

{ "id": 5, "event_id": 1 }
```

#### Reorder Event Pictures

```http
POST /admin/api.php?action=event_pictures_reorder
Content-Type: application/json
X-CSRF-Token: <token>

{ "event_id": 1, "order": [3, 1, 5, 2] }
```

`order` is an array of picture IDs in the desired display sequence.

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

### Settings Endpoints

Site-wide settings stored in `cache/site-settings.json`. **admin role only** for save actions.

| Action | Method | Description |
|--------|--------|-------------|
| `title_get` | GET | Get current site title |
| `title_save` | POST | Save site title |
| `theme_get` | GET | Get current global theme |
| `theme_save` | POST | Save global theme |
| `disclaimer_get` | GET | Get disclaimer text (TH/EN/JA) |
| `disclaimer_save` | POST | Save disclaimer text |
| `email_config_get` | GET | Get SMTP email notification config (password redacted in response) |
| `email_config_save` | POST | Save SMTP email notification config |
| `email_test_send` | POST | Send a test email using the submitted/current config |

#### Save Site Title

```http
POST /admin/api.php?action=title_save
Content-Type: application/json
X-CSRF-Token: <token>

{ "site_title": "My Event Calendar" }
```

#### Save Theme

```http
POST /admin/api.php?action=theme_save
Content-Type: application/json
X-CSRF-Token: <token>

{ "theme": "ocean" }
```

**theme**: `sakura` / `ocean` / `forest` / `midnight` / `sunset` / `dark` / `gray`

#### Save Disclaimer

```http
POST /admin/api.php?action=disclaimer_save
Content-Type: application/json
X-CSRF-Token: <token>

{
  "disclaimer_th": "ข้อมูลนี้อาจมีการเปลี่ยนแปลง",
  "disclaimer_en": "Information subject to change",
  "disclaimer_ja": "情報は変更される場合があります"
}
```

---

### Site Cover Image Endpoints *(v9.2.0)*

Site-wide header cover image (1920×480 px, 4:1 ratio) applied to every page's `<header>`. Stored in `uploads/site/`. **admin role only**.

| Action | Method | Description |
|--------|--------|-------------|
| `site_cover_bg_upload` | POST | Upload site-wide header cover (Cropper.js 4:1 crop before upload) |
| `site_cover_bg_delete` | POST | Delete site-wide header cover |

#### Upload Site Cover

```http
POST /admin/api.php?action=site_cover_bg_upload
Content-Type: multipart/form-data
X-CSRF-Token: <token>

cover_image=<cropped-file>
```

Cropped to 4:1 ratio client-side before upload. Stored in `uploads/site/`. Path saved in `cache/site-settings.json`.

#### Delete Site Cover

```http
POST /admin/api.php?action=site_cover_bg_delete
X-CSRF-Token: <token>
```

> **Priority chain**: `get_header_cover_bg(?$eventMeta)` checks (1) `event.header_cover_image` → (2) site-wide cover → (3) empty (theme gradient fallback). Applies `class="has-site-cover"` and `--header-cover-url` CSS variable on `<header>` for all public pages.

---

### Contact Channels Endpoints

Contact channels displayed on `contact.php`. **admin role only**.

| Action | Method | Description |
|--------|--------|-------------|
| `contact_channels_list` | GET | List all contact channels |
| `contact_channels_get` | GET | Get a single channel |
| `contact_channels_create` | POST | Create a new channel |
| `contact_channels_update` | PUT | Update a channel |
| `contact_channels_delete` | DELETE | Delete a channel |

#### Create Contact Channel

```http
POST /admin/api.php?action=contact_channels_create
Content-Type: application/json
X-CSRF-Token: <token>

{
  "icon": "📷",
  "title": "Instagram",
  "description": "@idol_stage",
  "url": "https://www.instagram.com/idol_stage/",
  "display_order": 0,
  "is_active": true
}
```

---

### Artists Endpoints *(v9.0.0)*

Manage artist/group master records. **admin/agent** for list; **admin role** for write operations.

| Action | Method | Description |
|--------|--------|-------------|
| `artists_list` | GET | List all artists (pagination, search, filter by group) |
| `artists_get` | GET | Get a single artist |
| `artists_create` | POST | Create a new artist |
| `artists_update` | PUT | Update an artist |
| `artists_delete` | DELETE | Delete an artist |
| `artists_bulk_set_group` | POST | Assign multiple solo artists to a group |
| `artists_bulk_import` | POST | Bulk import artists from a name list (up to 500) |

#### Create Artist

```http
POST /admin/api.php?action=artists_create
Content-Type: application/json
X-CSRF-Token: <token>

{
  "name": "Artist Name",
  "is_group": false,
  "group_id": null,
  "social_facebook": "https://facebook.com/artistname",
  "social_instagram": "https://instagram.com/artistname",
  "social_twitter": "https://twitter.com/artistname",
  "social_tiktok": "https://tiktok.com/@artistname"
}
```

**social_*** *(v9.5.0)*: optional social link URLs — validated with `sanitize_social_url()` (accepts http/https only; rejects `javascript:` and other schemes). Displayed as icon buttons on `/artist/{id}` and `/artists` portal cards.

#### Update Artist

```http
PUT /admin/api.php?action=artists_update&id=5
Content-Type: application/json
X-CSRF-Token: <token>

{
  "name": "Updated Name",
  "is_group": false,
  "group_id": 1,
  "social_facebook": "https://facebook.com/updatedname",
  "social_instagram": null,
  "social_twitter": null,
  "social_tiktok": null
}
```

> **Pictures**: Artist display/cover pictures are managed via `artist_picture_upload` and `artist_picture_delete` actions (multipart/form-data, similar to event picture upload). See v6.0.0 release notes.

---

### Artist Variants Endpoints

Manage alias/variant names per artist — used by ICS import to auto-link alternate spellings.

| Action | Method | Description |
|--------|--------|-------------|
| `artists_variants_list` | GET | List all variants for an artist (`?artist_id=X`) |
| `artists_variants_create` | POST | Add a new variant to an artist |
| `artists_variants_delete` | DELETE | Remove a variant (`?id=X`) |

#### List Variants

```http
GET /admin/api.php?action=artists_variants_list&artist_id=5
```

#### Add Variant

```http
POST /admin/api.php?action=artists_variants_create
Content-Type: application/json
X-CSRF-Token: <token>

{
  "artist_id": 5,
  "variant": "Alternative Spelling"
}
```

#### Delete Variant

```http
DELETE /admin/api.php?action=artists_variants_delete&id=12
X-CSRF-Token: <token>
```

---

### Email Config Endpoints

SMTP settings for admin email notifications are stored in `config/email-config.json`. **admin role only** (v9.6.0+).

| Action | Method | Description |
|--------|--------|-------------|
| `email_config_get` | GET | Get current email notification configuration |
| `email_config_save` | POST | Save email notification configuration |
| `email_test_send` | POST | Send a test email with the submitted configuration |

#### Get Email Config

```http
GET /admin/api.php?action=email_config_get
```

Response:

```json
{
  "enabled": false,
  "smtp_host": "smtp.example.com",
  "smtp_port": 587,
  "smtp_encryption": "tls",
  "smtp_username": "mailer@example.com",
  "smtp_password": "",
  "from_email": "noreply@example.com",
  "from_name": "Idol Stage Timetable",
  "recipients": "admin@example.com"
}
```

#### Save Email Config

```http
POST /admin/api.php?action=email_config_save
Content-Type: application/json
X-CSRF-Token: <token>

{
  "enabled": true,
  "smtp_host": "smtp.example.com",
  "smtp_port": 587,
  "smtp_encryption": "tls",
  "smtp_username": "mailer@example.com",
  "smtp_password": "smtp-password",
  "from_email": "noreply@example.com",
  "from_name": "Idol Stage Timetable",
  "recipients": "admin@example.com, staff@example.com"
}
```

#### Send Test Email

```http
POST /admin/api.php?action=email_test_send
Content-Type: application/json
X-CSRF-Token: <token>

{ "...same fields as save..." }
```

Notes:
- Multiple recipients may be separated by comma, semicolon, or newline.
- Request notifications are sent after successful submissions to `api/request.php` and `api/event-request.php` when `enabled=true`.
- Delivery failures are logged to `cache/logs/email.log` and do not reject the already-created request.
- `config/email-config.json` is protected from direct HTTP access by `config/.htaccess`.

---

### Google Config Endpoints

Google Analytics 4 and AdSense settings stored in `config/google-config.json`. **admin role only** (v6.4.0+).

| Action | Method | Description |
|--------|--------|-------------|
| `analytics_config_get` | GET | Get current Google Analytics + AdSense configuration |
| `analytics_config_save` | POST | Save Google Analytics + AdSense configuration |

#### Get Google Config

```http
GET /admin/api.php?action=analytics_config_get
```

Response:
```json
{
  "success": true,
  "data": {
    "ga_id": "G-XXXXXXXXXX",
    "ads_client": "ca-pub-XXXXXXXXXXXXXXXX",
    "ads_slot_leaderboard": "1234567890",
    "ads_slot_rectangle": "0987654321",
    "ads_slot_responsive": "1122334455"
  }
}
```

#### Save Google Config

```http
POST /admin/api.php?action=analytics_config_save
Content-Type: application/json
X-CSRF-Token: <token>

{
  "ga_id": "G-XXXXXXXXXX",
  "ads_client": "ca-pub-XXXXXXXXXXXXXXXX",
  "ads_slot_leaderboard": "1234567890",
  "ads_slot_rectangle": "0987654321",
  "ads_slot_responsive": "1122334455"
}
```

> **Security**: `config/google-config.json` is protected from direct HTTP access by `config/.htaccess` (Deny from all for .json files).
> **Effect**: Changes take effect immediately on next page load — constants `GOOGLE_ANALYTICS_ID`, `GOOGLE_ADS_CLIENT`, `GOOGLE_ADS_SLOT_*` are loaded at runtime from the JSON file.

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

### Two-Factor Authentication Endpoints

TOTP 2FA endpoints are session + CSRF protected and apply only to DB-managed users in `admin_users`.

The `admin_users.twofa_*` columns must be created manually through `setup.php` or `php tools/migrate-add-admin-2fa-columns.php`. Since v10.1.0, `admin/api.php` only checks schema readiness and does not run `ALTER TABLE` during normal API requests. Once ready, `data/.admin_2fa_columns_ready` caches the result permanently until the flag file is removed.

## Organizer Role (v12.0.0+)

The Admin API supports `admin`, `agent`, and `organizer` roles. Organizer users can access only assigned Events plus related Programs, Credits, event covers/header covers, and event pictures. Assignment is stored in `event_organizers`; run `php tools/migrate-add-organizer-role.php` or `setup.php` migrations before using organizer accounts.

Admin-only assignment endpoints:
- `GET admin/api.php?action=event_organizers_list&event_id={id}`
- `POST admin/api.php?action=event_organizers_update` with JSON `{ "event_id": 1, "user_ids": [2, 3] }`

Organizer users receive `403` for Requests, Event Request review actions, ICS Import, Artists CRUD, Users, Backup/Restore, Settings/config/contact, Email/Telegram/Analytics config, cross-user 2FA reset actions, and direct event activation. Organizer users may submit `events_request_activate` for assigned inactive events.

Since v12.2.0, organizer users may call `artists_autocomplete` while adding/editing Programs. This is reference-only: organizer Program saves must use existing artist names/variants and cannot create new artist records through `categories`. `programs_venues` returns the central venue list for organizer autocomplete.

Since v12.3.0, organizer users may open `Artists › Request new artist` and call `artist_requests_create`; admin/agent users review those requests through `Requests › Artist Request`.

### Artist Requests Endpoints *(v12.3.0)*

| Action | Method | Role | Description |
|--------|--------|------|-------------|
| `artist_requests_create` | POST | organizer | Submit a new artist request using logged-in organizer as requester |
| `artist_requests_list` | GET | admin/agent | List Artist Requests with status filter and pagination |
| `artist_request_approve&id={id}` | PUT | admin/agent | Approve pending request and create an `artists` record |
| `artist_request_reject&id={id}` | PUT | admin/agent | Reject pending request with optional admin note |
| `artists_groups` | GET | admin/agent/organizer | Load group options for the Artist form/request form |

Approve rejects duplicate artist names at review time; submit also rejects names already present in `artists` and duplicate pending artist requests.

| Action | Method | Description |
|--------|--------|-------------|
| `twofa_status` | GET | Get current user's 2FA status and remaining backup-code count |
| `twofa_begin_setup` | POST | Generate a pending TOTP secret and otpauth URI |
| `twofa_confirm_setup` | POST | Verify first TOTP code, enable 2FA, and return one-time backup codes |
| `twofa_disable` | POST | Disable own 2FA after current password + TOTP/recovery code |
| `twofa_regenerate_backup_codes` | POST | Replace backup codes after current password + TOTP/recovery code |
| `twofa_reset_user` | POST | Admin-only reset of another user's 2FA |

`users_list` and `users_get` expose `twofa_enabled` and `twofa_confirmed_at`; secrets and backup-code hashes are never returned.

---

## 🎯 Role-Based Access

| Feature | admin | agent |
|---------|-------|-------|
| Programs (CRUD, bulk) | ✅ | ✅ |
| Requests — Program (view, approve, reject) | ✅ | ✅ |
| Requests — Event (view, approve, reject) | ✅ | ✅ |
| ICS Import | ✅ | ✅ |
| Credits (CRUD, bulk) | ✅ | ✅ |
| Events/Conventions (CRUD) | ✅ | ✅ |
| Event Cover Images (upload/delete hero/card/header) | ✅ | ✅ |
| Event Pictures (upload/delete/reorder) | ✅ | ✅ |
| User Management | ✅ | ❌ |
| Backup/Restore | ✅ | ❌ |
| Contact Channels (CRUD) | ✅ | ❌ |
| Settings (title, theme, disclaimer) save | ✅ | ❌ |
| Site Cover Image (upload/delete) | ✅ | ❌ |
| Artist Variants (list, create, delete) | ✅ | ✅ |
| Email Config (SMTP notifications) get/save/test | ✅ | ❌ |
| Google Config (Analytics + AdSense) get/save | ✅ | ❌ |
| Change own password | ✅ | ✅ |
| Own 2FA setup/disable/backup codes | ✅ | ✅ |
| Reset another user's 2FA | ✅ | ❌ |

---

## 🔗 Related Documentation

- [README.md](README.md) — Project overview + Quick Start
- [INSTALLATION.md](INSTALLATION.md) — Detailed installation guide
- [PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md) — Database schema + file structure
- [ICS_FORMAT.md](ICS_FORMAT.md) — ICS file format guide
- [SECURITY.md](SECURITY.md) — Security policy

---

*Idol Stage Timetable v16.5.1*
