# 🛠️ Tools — CLI Utilities & Migrations

Command-line scripts for **Idol Stage Timetable**. Run from the project root with `php tools/<script>.php`.

> **CLI-only.** This directory is blocked from HTTP access by `tools/.htaccess` (`Require all denied`). Never expose it over the web.
>
> **Prefer the Setup Wizard.** For a fresh install or routine maintenance, use [`setup.php`](../SETUP.md) → it runs all migrations in the correct dependency order and reports status. Run the scripts below individually only when you need a specific one.

---

## ⚡ Quick start

```bash
# Fresh install (recommended): open the wizard in a browser
#   http://your-host/setup.php   → Initialize Database → Run All Migrations → Import

# Manual: import ICS data, then run migrations (idempotent — safe to re-run)
php tools/import-ics-to-sqlite.php
php tools/migrate-add-indexes.php
# ...see "Schema migrations" below for the full set
```

Most migrations are **idempotent** (safe to run repeatedly — they check before altering). Run order matters only for dependencies (a table must exist before columns/indexes are added to it); the Setup Wizard's *Run All Migrations* handles this for you.

---

## 📥 Database init & import

| Script | Purpose |
|--------|---------|
| `import-ics-to-sqlite.php` | Import `.ics` files from `ics/` into the `programs` table (creates base tables on first run). Idempotent via `INSERT OR REPLACE`. |
| `update-ics-categories.php` | Add a `CATEGORIES` field to `.ics` files using each event's `ORGANIZER;CN=` value. |
| `import-artist-socials.php` | Bulk-import artist social links from CSV/JSON: `php tools/import-artist-socials.php <file.csv> [--dry-run] [--overwrite]`. Matches by name → variant → id; stores only `http(s)` URLs. *(v16.4.0)* |
| `sample-artist-socials.csv` | UTF-8-BOM template for `import-artist-socials.php` (opens cleanly in Excel). |

---

## 🗄️ Schema migrations — create tables

All idempotent (`CREATE TABLE IF NOT EXISTS` + guarded seeds).

| Script | Creates |
|--------|---------|
| `migrate-add-events-meta-table.php` | `events` (convention metadata) + `event_id` FK on related tables |
| `migrate-add-requests-table.php` | `program_requests` |
| `migrate-add-event-requests-table.php` | `event_requests` |
| `migrate-add-credits-table.php` | `credits` |
| `migrate-add-admin-users-table.php` | `admin_users` (+ seed from `config/admin.php`) |
| `migrate-add-contact-channels-table.php` | `contact_channels` |
| `migrate-add-artists-table.php` | `artists` (self-referential) + `program_artists` junction |
| `migrate-add-artist-variants-table.php` | `artist_variants` (+ import aliases from `data/artists-mapping.json`) |
| `migrate-add-artist-requests-table.php` | `artist_requests` (organizer artist-request workflow) |
| `migrate-add-event-pictures-table.php` | `event_pictures` + `events.gallery_template` |
| `migrate-add-venues-table.php` | `venues` + `venue_variants`; seeds from `programs.location`; flags online platforms *(v16.0.0)* |
| `migrate-add-fts5.php` | FTS5 virtual tables (`programs_fts`, `events_fts`, `artists_fts`) + 9 auto-sync triggers; rebuilds the index |

---

## 🧩 Schema migrations — add columns / alter

All idempotent (check `PRAGMA table_info()` before `ALTER TABLE`).

| Script | Adds |
|--------|------|
| `migrate-rename-tables-columns.php` | Renames tables/columns to the v2.0.0 schema (`events`→`programs`, `events_meta`→`events`, …) |
| `migrate-add-role-column.php` | `admin_users.role` |
| `migrate-add-admin-2fa-columns.php` | `admin_users.twofa_*` (TOTP 2FA) |
| `migrate-add-organizer-role.php` | `events.created_by_user_id` + `event_organizers` (organizer role, v12.0.0) |
| `migrate-add-indexes.php` | Performance indexes on `programs`, `program_requests`, `credits` |
| `migrate-add-event-email-column.php` | `events.email` (ICS `ORGANIZER`) |
| `migrate-add-program-type-column.php` | `programs.program_type` |
| `migrate-add-stream-url-column.php` | `programs.stream_url` (live stream URL) |
| `migrate-add-theme-column.php` | `events.theme` (per-event theme) |
| `migrate-add-timezone-column.php` | `events.timezone` (default `Asia/Bangkok`) |
| `migrate-add-event-cover-image-column.php` | `events.cover_image` (Hero 16:9) |
| `migrate-add-event-cover-image-card-column.php` | `events.cover_image_card` (Card 4:3) |
| `migrate-add-header-cover-image-column.php` | `events.header_cover_image` (4:1 header) |
| `migrate-add-ticket-url-column.php` | `events.ticket_url` |
| `migrate-add-artist-pictures-column.php` | `artists.display_picture` + `cover_picture`; creates `uploads/artists/` |
| `migrate-add-artist-social-columns.php` | `artists.social_facebook/instagram/twitter/tiktok` |

---

## 🎤 Artist mapping (legacy / one-time)

| Script | Purpose |
|--------|---------|
| `generate-artists-mapping.php` | Scan all `programs.categories`, group + flag issues, and write `data/artists-mapping.json`. |
| `migrate-artists-from-mapping.php` | Populate `artists` + `artist_variants` from `data/artists-mapping.json`. |

> The Artist Reuse System (v3.0.0+) now manages this through the Admin UI; these scripts cover the original one-time migration.

---

## 🔧 Utilities

| Script | Purpose |
|--------|---------|
| `update-version.php` | Bump `APP_VERSION` across `config/app.php` + doc files: `php tools/update-version.php X.Y.Z`. **Run `php sync-sw-version.php` afterward** to sync the service worker's `CACHE_VERSION`. (Edit `CHANGELOG.md` + `CLAUDE.md` by hand.) |
| `generate-password-hash.php` | Generate a bcrypt admin password hash: `php tools/generate-password-hash.php <password>`. |
| `generate-favorites-secret.php` | Generate the Favorites HMAC secret into the gitignored `config/favorites-config.json` (overwrite-guarded). *(v16.5.2)* |
| `generate-pwa-icons.php` | Generate sakura-gradient PWA icons (72/192/512) into `icon/` via PHP GD. |
| `setup-telegram-webhook.php` | Register the Telegram bot webhook URL with the Bot API (after setting token + secret in Admin › Settings › Telegram). |

---

## 🐞 Debug / dev

| Script | Purpose |
|--------|---------|
| `debug-parse.php` | Print which events parse successfully from a sample `.ics`. |
| `test-parse.php` | Quick `IcsParser` smoke test against a sample `.ics`. |
| `dedup-locations.sql` | SQL helper used while building the venue dedup mapping (v16.0.0). |

> These read hard-coded sample paths under `ics/`; they are development aids, not part of normal operation.

---

## 📝 Notes

- **Idempotency:** the `migrate-*` scripts are safe to re-run; they detect existing tables/columns and skip.
- **2FA schema:** since v10.1.0, the Admin API no longer auto-adds `admin_users.twofa_*`. Run `migrate-add-admin-2fa-columns.php` (or the wizard) manually; readiness is cached in `data/.admin_2fa_columns_ready`.
- **Secrets:** `generate-favorites-secret.php` writes to `config/favorites-config.json`, which is gitignored (`config/*-config.json`) and HTTP-denied — never commit it.
- **Docker:** run any script with `docker exec idol-stage-calendar php tools/<script>.php`.

See [SETUP.md](../SETUP.md), [PROJECT-STRUCTURE.md](../PROJECT-STRUCTURE.md) (DB schema), and [API.md](../API.md) for more.
