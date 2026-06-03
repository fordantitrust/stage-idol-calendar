# 📁 Project Structure

File and folder structure for Idol Stage Timetable v16.7.1

---

## Overview

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
├── ⏰ cron/             CLI cron scripts (notifications + log rotation)
├── 🛠️  tools/            CLI migration tools
├── 🧪 tests/            Automated test suite
├── 🖼️  uploads/          Admin-uploaded images (artists, events, site)
├── 🎨 icon/             PWA icons (72/192/512)
├── 🔤 fonts/            TrueType fonts for image export
└── 📚 docs/*.md         Documentation
```

> PWA assets live at the root: `manifest.json` / `manifest.php`, `service-worker.js`, `offline.html`, and `sync-sw-version.php` (CLI tool that keeps the service worker's `CACHE_VERSION` in sync with `APP_VERSION`).

---

## Root PHP Pages

| File | Purpose |
|------|---------|
| `index.php` | Main page — displays programs table (List + Gantt + Calendar view) |
| `how-to-use.php` | How-to guide (3 languages: TH/EN/JA) |
| `contact.php` | Contact page — channels loaded from DB (3 languages) |
| `credits.php` | Credits & References (loaded from DB + cache, global/per-event view) |
| `export.php` | Export ICS handler — download .ics from filtered programs |
| `feed.php` | Live ICS subscription feed — ETag, static file cache, RFC 5545/7986 |
| `api.php` | Public API endpoint (programs, organizers, locations, events_list) |
| `artist.php` | Artist Profile page — `/artist/{id}`; all programs grouped by event; group members, variants, social links, follow button |
| `artists.php` | Artist & Group Portal (`/artists`) — gradient group cards + solo grid, real-time search, tab filter |
| `venue.php` | Venue Profile page — `/venue/{id}`; programs grouped by event (v16.0.0+) |
| `venues.php` | All-Venues Portal — `/venues`; grid + search; hides online platforms (v16.0.0+) |
| `my.php` | My Upcoming Programs — `/my/{slug}`; anonymous-favorites schedule (List + Timeline), mini calendar, Telegram/Web Push/QR controls, timezone picker (noindex) |
| `my-favorites.php` | My Favorites — `/my-favorites/{slug}`; followed solo/group artists + unfollow + QR transfer (noindex) |
| `my-feed.php` | Personal ICS feed — `/my/{slug}/feed`; upcoming programs for followed artists across events |
| `connect.php` | Favorites Connect — `/connect`; camera QR scanner (jsQR) to receive a slug on a new device; scoped `Permissions-Policy: camera=(self)` |
| `image.php` | Server-side PNG export (PHP GD) — theme-aware timetable image; cached to `cache/images/` |
| `sitemap.php` | Dynamic XML Sitemap at `/sitemap.xml` (Apache rewrite) — static pages, active events, artist profiles; file-cached to `cache/sitemap.xml` (TTL 1 hr) |
| `robots.php` | Dynamic `robots.txt` at `/robots.txt` (Apache rewrite) — injects `Sitemap:` URL from actual host; Disallows `/my/`, `/my-favorites/` |
| `robots.txt` | Static fallback robots.txt (rewrite routes to `robots.php`) |
| `past-events.php` | Past Events archive page |
| `setup.php` | Setup Wizard — fresh install & maintenance (6 steps) |
| `config.php` | Bootstrap — loads all config/ and functions/ |
| `IcsParser.php` | ICS Parser class — parse .ics files → SQLite |
| `manifest.json` / `manifest.php` | PWA Web App Manifest (name, icons, start_url, display) |
| `service-worker.js` | PWA service worker — push + notificationclick + offline cache strategies; `CACHE_VERSION` synced to `APP_VERSION` |
| `offline.html` | Offline fallback page (self-contained, 3 languages) |
| `sync-sw-version.php` | CLI-only — syncs `CACHE_VERSION` in `service-worker.js` with `APP_VERSION` (run after `update-version.php`) |
| `.htaccess` | Apache clean URL rewrite rules (removes .php extension) |
| `nginx-clean-url.conf` | Nginx complete server config (clean URLs, directory restrictions, security headers) |

---

## ⚙️ config/

Configuration constants for the entire system, loaded via `config.php`

| File | Defines | Purpose |
|------|---------|---------|
| `app.php` | `APP_VERSION`, `APP_NAME`, `PRODUCTION_MODE`, `VENUE_MODE`, `MULTI_EVENT_MODE`, `DEFAULT_EVENT_SLUG`, `DEFAULT_TIMEZONE` | App settings + cache busting + site title default |
| `admin.php` | `ADMIN_USERNAME`, `ADMIN_PASSWORD_HASH`, `SESSION_TIMEOUT`, `ADMIN_IP_WHITELIST_ENABLED`, `ADMIN_ALLOWED_IPS`, `ADMIN_AUDIT_RETENTION_DAYS`, `ADMIN_AUDIT_LOG_DIR` | Admin auth fallback + IP whitelist + audit-log retention (v14.0.0+) |
| `security.php` | Security rate limiting constants | Rate limiting config |
| `database.php` | `DB_PATH` (`data/calendar.db`) | Database file path |
| `cache.php` | `DATA_VERSION_CACHE_TTL` (600s), `CREDITS_CACHE_TTL` (3600s), `FEED_CACHE_DIR`, `FEED_CACHE_TTL` (3600s), `SITEMAP_CACHE_FILE`, `SITEMAP_CACHE_TTL` (3600s), `QUERY_CACHE_DIR`, `QUERY_CACHE_TTL`, `IMAGE_CACHE_DIR`, `IMAGE_CACHE_TTL` | Cache TTL settings + ICS feed cache + sitemap + query cache + image export cache |
| `google.php` | `GOOGLE_ANALYTICS_ID`, `GOOGLE_ADS_CLIENT`, `GOOGLE_ADS_SLOT_*` | Loads from `google-config.json`; constants kept for backward compatibility (replaces `analytics.php` in v6.4.1) |
| `google-config.json` | JSON file with `ga_id`, `ads_client`, `ads_slot_*` | Runtime-editable Google config; gitignored (`config/*-config.json`) + HTTP-denied by `config/.htaccess` |
| `favorites.php` | `FAVORITES_HMAC_SECRET`, `FAVORITES_HMAC_LENGTH`, `FAVORITES_DIR`, `FAVORITES_TTL`, `FAVORITES_RL_DIR`, `FAVORITES_MAX_ARTISTS`, `FAVORITES_RATE_LIMIT`, `FAVORITES_RATE_WINDOW` | Anonymous favorites storage + rate-limit config; **HMAC secret loaded from `favorites-config.json`** (v16.7.1), falls back to placeholder when absent |
| `favorites-config.json` | JSON file with `hmac_secret` | Runtime favorites HMAC secret; gitignored + HTTP-denied; generated by `tools/generate-favorites-secret.php` (v16.5.2+) |
| `telegram.php` | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_NOTIFY_BEFORE_MINUTES`, `TELEGRAM_DAILY_SUMMARY_*`, `TELEGRAM_ENABLED` | Loads from `telegram-config.json`; constants for telegram bot |
| `telegram-config.json` | JSON file with bot token/username/webhook secret + notify/summary settings | Runtime-editable via Admin UI; gitignored + HTTP-denied |
| `email.php` | `EMAIL_ENABLED`, `EMAIL_SMTP_*`, `EMAIL_FROM_*`, `EMAIL_RECIPIENTS` | Loads SMTP notification settings from `email-config.json`; disabled by default |
| `email-config.json` | JSON file with SMTP host/port/encryption, sender, recipients, enabled flag | Runtime-editable Email Notifications config; gitignored + HTTP-denied |
| `webpush.php` | `WEBPUSH_ENABLED`, `WEBPUSH_VAPID_PUBLIC_KEY`, `WEBPUSH_VAPID_PRIVATE_KEY_PEM`, `WEBPUSH_VAPID_SUBJECT`, `WEBPUSH_SITE_URL`, `WEBPUSH_NOTIFY_BEFORE_MINUTES`, `WEBPUSH_MAX_SUBS_PER_TOKEN`, `WEBPUSH_ALLOW_LOCALHOST` | Loads Web Push (VAPID) config from `webpush-config.json` (v15.0.0+) |
| `webpush-config.json` | JSON file with VAPID keys + subject + site URL + notify settings | Runtime Web Push config; gitignored + HTTP-denied; private key never exposed via API |
| `.htaccess` | — | `Require all denied` (Apache 2.4) + 2.2 fallback — denies HTTP access to **every** file under `config/` (v15.5.0, LOW-2) |

---

## 🔧 functions/

Helper functions loaded via `config.php`

| File | Key Functions | Purpose |
|------|--------------|---------|
| `helpers.php` | `get_db()`, `get_site_title()`, `get_site_theme()`, `get_event_by_slug()`, `get_event_id()`, `get_all_active_events()`, `get_event_venue_mode()`, `event_url()`, `get_event_timezone()`, `is_valid_timezone()`, `get_site_cover_bg()`, `get_header_cover_bg()` | General utilities + DB singleton + site title/theme + multi-event helpers + timezone + cover images |
| `cache.php` | `get_data_version()`, `get_cached_credits()`, `invalidate_data_version_cache()`, `invalidate_credits_cache()`, `invalidate_feed_cache()`, `invalidate_sitemap_cache()`, `invalidate_query_cache()`, `invalidate_artist_query_cache()`, `invalidate_all_caches()` | Cache read/write/invalidate (data version, credits, ICS feed, sitemap, query cache) |
| `admin.php` | `admin_login()`, `admin_login_attempt()`, `admin_complete_twofa()`, `safe_session_start()`, `admin_logout()`, `get_admin_role()`, `is_admin_role()`, `require_admin_role()`, `check_login_rate_limit()`, `record_failed_login()`, `clear_login_attempts()` | Auth + session + RBAC + rate limiting + 2FA login flow |
| `totp.php` | `totp_hotp()`, `totp_code()`, `totp_verify()`, `totp_otpauth_uri()`, `twofa_generate_backup_codes()`, `twofa_consume_backup_code()` | RFC 6238 TOTP + Base32 + one-time backup-code helpers for Admin 2FA |
| `security.php` | `sanitize_string()`, `sanitize_string_array()`, `get_sanitized_param()`, `send_security_headers()`, `check_ip_whitelist()`, `csrf_token()`, `verify_csrf_token()` | XSS, CSRF, headers, IP whitelist |
| `ads.php` | `render_ad_unit(type)` | Google AdSense helper — renders leaderboard/rectangle/responsive ad units; no-op when `GOOGLE_ADS_CLIENT` is empty (v6.3.0+) |
| `ics.php` | `icsLine()`, `icsFold()`, `icsEscape()`, `icsEscapeText()`, `icsVtimezone()`, `icsOffsetString()` | Shared ICS helpers for RFC 5545 compliant export and feed generation |
| `telegram.php` | `send_telegram_message()`, `find_favorites_by_chat_id()`, `telegram_get_notify_mode()`, `telegram_per_program_enabled()`, `telegram_is_muted()`, `telegram_format_notification()`, `telegram_format_events_list()` | Telegram Bot API helpers + notification state (3 notify modes: all/summary/off, v16.2.0) |
| `email.php` | `email_send()`, `email_parse_recipients()`, `email_notify_program_request_created()`, `email_notify_event_request_created()` | Native SMTP email notification helpers for Program/Event Requests; logs to `cache/logs/email.log` |
| `webpush.php` | `webpush_is_enabled()`, `webpush_generate_vapid_keys()`, `webpush_vapid_jwt()`, `webpush_encrypt()`, `webpush_send()`, `webpush_allowed_hosts()`, `webpush_validate_endpoint()`, `webpush_urlsafe_b64encode/decode()` | Web Push: VAPID + RFC 8291 aes128gcm crypto + SSRF endpoint allow-list (v15.0.0, hardened v15.5.0) |
| `audit.php` | `audit_log()`, `audit_admin_success()`, `audit_admin_failure()`, `audit_read_recent()`, `audit_redact()`, `audit_api_context()` | File-based admin audit log (JSON Lines, secret redaction) → `cache/logs/admin-audit-YYYY-MM-DD.log` (v14.0.0+) |
| `favorites.php` | `fav_generate_uuid_v7()`, `fav_build_slug()`, `fav_parse_slug()`, `fav_read()`, `fav_write()`, `fav_touch()`, `fav_check_rate_limit()`, `fav_maybe_cleanup()`, `fav_resolve_user_timezone()` | Anonymous favorites: HMAC-signed slug, atomic JSON I/O, sharded storage, per-IP rate limit |
| `seo.php` | `seo_full_url()`, `seo_truncate()`, `seo_render_meta()`, `seo_render_json_ld()` | CLI-safe SEO helpers — meta description, Open Graph, Twitter Card, canonical URL, noindex, JSON-LD structured data (v6.5.0+) |
| `search.php` | `fts5_available()`, `fts5_escape()`, `fts5_count_programs()`, `fts5_search_programs()`, `fts5_count_events()`, `fts5_search_events()`, `fts5_search_artists()`, `fts5_search_all()`, `fts5_rebuild_all()` | FTS5 full-text search across programs, events, artists; `unicode61` tokenizer; graceful LIKE fallback when FTS tables absent (v9.0.0+) |

---

## 🗄️ data/

| File | Purpose |
|------|---------|
| `calendar.db` | Main SQLite database |
| `.setup_locked` | Lock file for setup.php (present = locked) |
| `.admin_2fa_columns_ready` | Runtime flag created after Admin API confirms `admin_users.twofa_*` columns exist; delete to force a schema re-check |

> **Security**: The `data/` directory is protected by `.htaccess` to prevent direct web browser access.

---

## 💾 backups/

Auto-created by the Admin Panel (Backup tab)

| File | Purpose |
|------|---------|
| `backup_YYYYMMDD_HHMMSS.db` | Database backup files |

---

## 📦 cache/

Auto-created by the system

| File | Purpose | TTL |
|------|---------|-----|
| `data_version.json` | Last data update timestamp (ETag for public API + feed) | 10 minutes |
| `credits.json` | Credits data cache | 1 hour |
| `feed_*.ics` | Static ICS feed cache files (key = md5 of sorted filters+eventId) | 1 hour |
| `sitemap.xml` | Static XML sitemap cache (served by `readfile()` on hit) | 1 hour |
| `query_event_{id}.json` | Event page DB query results (programs, artists, venues, types) | 1 hour |
| `query_artist_{id}.json` | Artist profile page DB query results | 1 hour |
| `query_listing.json` | Homepage listing query cache (`$activeEvents` + `$listingCalData` + `live_programs`) | 1 hour |
| `query_portal.json` | Artists & Group Portal page query cache | 1 hour |
| `query_venue_{id}.json` | Venue profile page query cache (v16.0.0+) | 1 hour |
| `query_portal_venues.json` | All-Venues portal query cache (v16.0.0+) | 1 hour |
| `favorites/{shard}/{token}.json` | Anonymous favorites data (sharded by last 3 hex of UUID) + `.ics` personal feed cache | 365 days (auto-touch) |
| `ratelimit/fav_rl_*.json` | Favorites per-IP rate-limit counters (`FAVORITES_RL_DIR`, v16.7.1) | rolling window |
| `logs/email.log` | Email notification delivery log (+ dated archives via rotation cron) | Persistent (rotated daily, 7-day retention) |
| `logs/telegram-cron.log` | Telegram notification cron log (+ dated archives) | Rotated daily, 7-day retention |
| `logs/webpush-cron.log` | Web Push notification cron log (+ dated archives) | Rotated daily, 7-day retention |
| `logs/admin-audit-YYYY-MM-DD.log` | Admin audit log (JSON Lines, secret-redacted) | Retention `ADMIN_AUDIT_RETENTION_DAYS` (default 30) |
| `login_attempts.json` | Login rate limiting data | 15 minutes |
| `site-theme.json` | Global site theme setting | Persistent (changed by admin) |
| `site-settings.json` | Site settings: `site_title`, `disclaimer_th/en/ja`, cover images | Persistent (changed by admin) |
| `images/img_*.png` | Server-side image export PNG cache (theme-aware) | 1 hour |

---

## 🔌 api/

Public API endpoints — no login required

| File | Purpose |
|------|---------|
| `request.php` | Program request submission — submit add/modify request + programs listing (for modal) |
| `event-request.php` | Event request submission — propose new events (add-only); rate-limited 10 req/hr/IP (v9.3.0+) |
| `favorites.php` | Anonymous favorites API — `create` (rate-limited), `get`, `add`/`remove` artist, `set_timezone`, `unlink_telegram`; HMAC slug required (v3.4.0+) |
| `push.php` | Web Push subscription API — `subscribe`/`unsubscribe`/`status`; SSRF endpoint allow-list; HMAC slug required (v15.0.0+) |
| `telegram.php` | Telegram Bot webhook handler — `/start`, `/today`, `/tz`, `/notify`, etc.; `X-Telegram-Bot-Api-Secret-Token` verification (fail-closes when secret empty) (v5.0.0+) |

See [API.md](API.md) for full endpoint documentation.

---

## 🔐 admin/

Admin panel — login required

| File | Purpose |
|------|---------|
| `login.php` | Login page (CSRF-gated, rate limited: 5 attempts/15 min/IP); 2FA step when enabled |
| `index.php` | Admin dashboard — Tabs: Dashboard, Programs, Requests, Credits, Events, Artists, Venues, Import, Settings (sub-tabs: Site/Contact/Users/Backup/Telegram/Web Push/Email/Google/Disclaimer/Audit Log) |
| `api.php` | All CRUD API endpoints (requires session + CSRF token); `$adminOnlyActions` dispatcher gate for admin-role actions |
| `help.php` / `help-en.php` | Admin help pages (Thai / English), role-aware |
| `js/admin-i18n.js` | Bilingual Admin UI dictionary (TH/EN) + `adminT()` lookup |

See [API.md](API.md) for admin endpoint documentation.

---

## ⏰ cron/

CLI-only scripts (HTTP access blocked by `cron/.htaccess` deny-all). Schedule via crontab.

| File | Purpose |
|------|---------|
| `send-telegram-notifications.php` | Per-program + daily-summary Telegram notifications; scans favorites shards; respects mute/notify-mode; idempotent (±7.5 min window) |
| `send-web-push-notifications.php` | Per-program Web Push notifications; removes expired/HTTP-410/non-allow-listed subscriptions |
| `rotate-telegram-logs.php` | Daily rotation + 7-day cleanup of `cache/logs/telegram-cron.log` |
| `rotate-webpush-logs.php` | Daily rotation + 7-day cleanup of `cache/logs/webpush-cron.log` |
| `rotate-email-logs.php` | Daily rotation + 7-day cleanup of `cache/logs/email.log` |
| `rotate-admin-audit-logs.php` | Deletes `admin-audit-*.log` older than `ADMIN_AUDIT_RETENTION_DAYS` |
| `.htaccess` | `Deny from all` — blocks HTTP access to all cron scripts |

> Both notification crons use a read-modify-write under `LOCK_EX` (v15.6.1) to avoid clobbering each other's writes to the shared favorites JSON.

---

## 🛠️ tools/

CLI scripts for developers — run via `php tools/script.php`

| File | Purpose | Idempotent |
|------|---------|-----------|
| `import-ics-to-sqlite.php` | Import .ics files → `programs` table | ✅ (INSERT OR REPLACE) |
| `update-ics-categories.php` | Add CATEGORIES field to .ics files | - |
| `migrate-add-requests-table.php` | Create `program_requests` table | ✅ |
| `migrate-add-credits-table.php` | Create `credits` table | ✅ |
| `migrate-add-events-meta-table.php` | Create `events` (meta) table | ✅ |
| `migrate-add-admin-users-table.php` | Create `admin_users` table + seed from config | ✅ |
| `migrate-add-role-column.php` | Add `role` column to `admin_users` | ✅ |
| `migrate-add-admin-2fa-columns.php` | Add TOTP 2FA columns to `admin_users` | ✅ |
| `migrate-rename-tables-columns.php` | Rename tables/columns to v2.0.0 schema | ✅ |
| `migrate-add-indexes.php` | Add 7 performance indexes | ✅ |
| `migrate-add-event-email-column.php` | Add `email` column to `events` | ✅ |
| `migrate-add-program-type-column.php` | Add `program_type` column to `programs` | ✅ |
| `migrate-add-stream-url-column.php` | Add `stream_url` column to `programs` | ✅ |
| `migrate-add-theme-column.php` | Add `theme` column to `events` | ✅ |
| `migrate-add-contact-channels-table.php` | Create `contact_channels` table | ✅ |
| `migrate-add-artist-variants-table.php` | Create `artist_variants` table + import variants from `data/artists-mapping.json` | ✅ |
| `migrate-add-timezone-column.php` | Add `timezone TEXT DEFAULT 'Asia/Bangkok'` column to `events` | ✅ |
| `migrate-add-artist-pictures-column.php` | Add `display_picture` + `cover_picture TEXT DEFAULT NULL` to `artists`; create `uploads/artists/` dir | ✅ |
| `migrate-add-event-pictures-table.php` | Create `event_pictures` table + `events.gallery_template` column | ✅ |
| `migrate-add-fts5.php` | Create FTS5 virtual tables (`programs_fts`, `events_fts`, `artists_fts`) + 9 auto-sync triggers; rebuild index | ✅ |
| `migrate-add-header-cover-image-column.php` | Add `header_cover_image TEXT DEFAULT NULL` to `events` | ✅ |
| `migrate-add-event-requests-table.php` | Create `event_requests` table | ✅ |
| `migrate-add-artist-requests-table.php` | Create `artist_requests` table for organizer Artist Request workflow | ✅ |
| `migrate-add-artist-social-columns.php` | Add `social_facebook/instagram/twitter/tiktok TEXT DEFAULT NULL` to `artists` | ✅ |
| `migrate-add-ticket-url-column.php` | Add `ticket_url TEXT DEFAULT NULL` to `events` | ✅ |
| `migrate-add-organizer-role.php` | Add organizer role ownership schema (`events.created_by_user_id`, `event_organizers`) (v12.0.0) | ✅ |
| `migrate-add-venues-table.php` | Create `venues` + `venue_variants` tables; seed from `programs.location`; flag online platforms (v16.0.0) | ✅ |
| `update-version.php` | Bump `APP_VERSION` across config/app.php + 8 doc files (run `sync-sw-version.php` after) | - |
| `import-artist-socials.php` | CLI: bulk-import artist social links from CSV/JSON; match by name→variant→id; http(s)-only URLs; `--dry-run`/`--overwrite` (v16.4.0) | - |
| `sample-artist-socials.csv` | UTF-8-BOM template for `import-artist-socials.php` | - |
| `dedup-locations.sql` | SQL helper for venue dedup mapping (v16.0.0) | - |
| `generate-favorites-secret.php` | Generate the Favorites HMAC secret → writes `config/favorites-config.json` (overwrite-guarded) (v16.7.1) | - |
| `generate-pwa-icons.php` | Generate sakura-gradient PWA icons (72/192/512) into `icon/` (PHP GD) | - |
| `setup-telegram-webhook.php` | Register the Telegram webhook URL with the Bot API | - |
| `generate-password-hash.php` | Generate bcrypt password hash | |
| `debug-parse.php` | Debug ICS file parsing | |
| `test-parse.php` | Test ICS parser | |

> **Note**: For a fresh install, use the [Setup Wizard](SETUP.md) instead of running tools individually.

---

## 🧪 tests/

Automated test suite — 27 suites (cumulative runner), PHP 8.1/8.2/8.3/8.4/8.5

| File | Unique Tests | Cumulative | Coverage |
|------|-------------|-----------|---------|
| `TestRunner.php` | — | — | Lightweight test framework (20 assertion methods) |
| `run-tests.php` | — | — | Main runner + colored output + suite selector |
| `SecurityTest.php` | 7 | 7 | XSS, null bytes, input sanitization, safe errors |
| `CacheTest.php` | 10 | 17 | Cache TTL, hit/miss, invalidation, fallback on error |
| `AdminAuthTest.php` | 35 | 52 | Session, login, timing attack, DB auth, **login CSRF gate + order + audit (v15.5.0)** |
| `CreditsApiTest.php` | 11 | 63 | Credits CRUD, bulk delete, SQL injection prevention |
| `IntegrationTest.php` | 58 | 121 | Config, file structure, workflows, API, multi-event, **config/tools .htaccess + .gitignore guards (v15.5.0)** |
| `UserManagementTest.php` | 20 | 141 | Role schema, RBAC helpers, user CRUD, permission guards |
| `ThemeTest.php` | 24 | 165 | Theme system, get_site_theme(), per-event theme, CSS files |
| `SiteSettingsTest.php` | 14 | 179 | Site title: get_site_title(), cache, fallbacks, admin API |
| `EventEmailTest.php` | 19 | 198 | events.email schema, CRUD, validation, ICS ORGANIZER |
| `ProgramTypeTest.php` | 35 | 233 | program_type schema, CRUD, API filter, UI badges, translations |
| `FeedTest.php` | 80 | 313 | icsEscape/icsEscapeText/icsFold, CATEGORIES, ETag, feed cache, RFC 5545 |
| `StreamUrlTest.php` | 31 | 344 | stream_url schema, CRUD, admin badge, public UI, ICS URL property |
| `FavoritesTest.php` | 84 | 428 | Anonymous favorites, UUID v7, HMAC, personal feeds, artist profiles |
| `TimezoneTest.php` | 81 | 509 | Per-event timezone, UTC conversion, TZID format, local time display, migration |
| `TelegramTest.php` | 82 | 591 | Telegram bot commands, helpers, mute/notify modes (all/summary/off), `/tz`, group resolution |
| `EmailNotificationTest.php` | 13 | 604 | Email config/helper loading, site-name subjects, admin URL base path, recipient parsing, SMTP disabled guard, request email hooks, Admin Email UI/API, Requests empty state |
| `TwoFactorAuthTest.php` | 9 | 613 | RFC 6238 TOTP vectors, Base32, otpauth URI, replay guard, backup codes, manual 2FA migration sources, schema flag, API/UI/i18n |
| `OrganizerRoleTest.php` | 3 | 616 | Organizer role scoping, artist-request flow, approve-to-artist refresh |
| `ArtistPictureTest.php` | 61 | 677 | Artist display/cover picture upload, GD resize, admin API, tooltip |
| `SeoTest.php` | 63 | 740 | seo_full_url() CLI safety, seo_truncate() word boundary, seo_render_meta() OG/Twitter/noindex, seo_render_json_ld() Unicode, JSON-LD schemas, source checks on public pages |
| `EventPicturesTest.php` | 57 | 797 | event_pictures table/columns/indexes/CASCADE, events.gallery_template, migration, DB CRUD, admin API (upload/delete/reorder/list), processAndSaveImage mode='fit', uploads dir/htaccess, gallery+lightbox, CSS templates |
| `EventCoverTest.php` | 44 | 841 | events.cover_image/cover_image_card schema, Cropper.js upload flow, CSRF X-CSRF-Token header, fallback chain, admin API, listing cache keys, migration |
| `Fts5Test.php` | 45 | 886 | FTS5 virtual tables, triggers (ai/au/ad × 3 tables), fts5_available() caching, fts5_search_*, fts5_rebuild_all(), LIKE fallback, public API action=search, admin FTS integration |
| `WebPushTest.php` | 73 | 959 | WEBPUSH_* constants, base64url, VAPID keygen (EC P-256), JWT/encrypt/send, **endpoint allow-list (FCM/Mozilla/Apple/WNS) + reject paths + dev-flag gate + defense-in-depth in webpush_send() (v15.5.0)**, service-worker push+notificationclick, admin API, api/push.php (no FILTER_SANITIZE_URL) |
| `PwaOfflineTest.php` | 59 | 1018 | service-worker cache strategies, CACHE_VERSION sync, PRECACHE_ASSETS, network-only routes, SWR + ETag/304, offline.html (3 langs), sync-sw-version.php CLI guard, .htaccess |
| `VenueTest.php` | 34 | 1052 | venues + venue_variants schema, venue_resolve_canonical(), merge, is_online flag, /venue/{id} + /venues portal, admin API, migration |
| `LiveNowTest.php` | 15 | 1067 | Live Now strip query, ISO-with-offset emission, live/soon classification, i18n keys |

> **Cumulative mechanism**: `run-tests.php` uses `get_defined_functions()` — each suite re-runs all functions loaded so far. Total reported = sum of per-suite cumulative counts = **13,231** (27 suites).

```bash
# Run all tests
php tests/run-tests.php

# Run specific suite
php tests/run-tests.php SecurityTest
php tests/run-tests.php FeedTest
php tests/run-tests.php Fts5Test
php tests/run-tests.php StreamUrlTest::testStreamUrlColumn
```

---

## 🐳 Docker

| File | Purpose |
|------|---------|
| `Dockerfile` | PHP 8.1-apache + PDO SQLite |
| `docker-compose.yml` | Production (port 8000, volumes) |
| `docker-compose.dev.yml` | Development (live reload, error display) |
| `.dockerignore` | Reduces Docker image size |

---

## 📚 Documentation

| File | Purpose |
|------|---------|
| `README.md` | Project overview + Quick Start |
| `SETUP.md` | Setup Wizard guide (fresh install + 6-step wizard) |
| `INSTALLATION.md` | Detailed installation guide (Apache/Nginx/XAMPP/Docker) |
| `API.md` | Full API endpoint documentation |
| `ICS_FORMAT.md` | ICS file format guide (fields, examples, import/export) |
| `PROJECT-STRUCTURE.md` | File structure (this file) |
| `DOCKER.md` | Docker deployment guide |
| `TESTING.md` | Manual testing checklist |
| `CHANGELOG.md` | Version history |
| `CONTRIBUTING.md` | Contribution guidelines |
| `SECURITY.md` | Security policy |

---

## 🗄️ Database Schema

> **Note**: Tables and columns were renamed in v2.0.0. Run `tools/migrate-rename-tables-columns.php` to update existing databases.

### Table: `programs`

Individual show/performance records (formerly `events`).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Unique program ID |
| `uid` | TEXT | UNIQUE NOT NULL | ICS UID (globally unique) |
| `title` | TEXT | NOT NULL | Program title/summary |
| `start` | DATETIME | NOT NULL | Start date and time |
| `end` | DATETIME | NOT NULL | End date and time |
| `location` | TEXT | | Venue/stage name |
| `organizer` | TEXT | | Performer/artist |
| `description` | TEXT | | Program description |
| `categories` | TEXT | | Artist names (comma-separated) |
| `program_type` | TEXT | DEFAULT NULL | Program type (free-text, v2.4.0+) |
| `stream_url` | TEXT | DEFAULT NULL | Live stream URL (http/https only, v2.6.0+) |
| `event_id` | INTEGER | FK → `events.id` | Event this program belongs to |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Indexes** (v1.2.10): `idx_programs_event_id`, `idx_programs_start`, `idx_programs_location`, `idx_programs_categories`

---

### Table: `program_requests`

User-submitted requests to add or modify programs (formerly `event_requests`).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Request ID |
| `type` | TEXT | NOT NULL | `'add'` or `'modify'` |
| `program_id` | INTEGER | FK → `programs.id` | Program to modify (for `type='modify'`) |
| `event_id` | INTEGER | FK → `events.id` | Event this request belongs to |
| `title` | TEXT | NOT NULL | Requested program title |
| `start` | DATETIME | NOT NULL | Requested start time |
| `end` | DATETIME | NOT NULL | Requested end time |
| `location` | TEXT | | Requested venue |
| `organizer` | TEXT | | Requested performer |
| `description` | TEXT | | Requested description |
| `categories` | TEXT | | Requested artist names |
| `requester_name` | TEXT | | Name of the requester |
| `requester_email` | TEXT | | Email of the requester |
| `requester_note` | TEXT | | Additional notes |
| `status` | TEXT | DEFAULT `'pending'` | `'pending'`, `'approved'`, or `'rejected'` |
| `admin_note` | TEXT | | Admin's note when approving/rejecting (v6.1.3+) |
| `reviewed_at` | DATETIME | | When the request was reviewed (v6.1.3+) |
| `reviewed_by` | TEXT | | Admin username who reviewed (v6.1.3+) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Submission time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last status update |

**Indexes**: `idx_program_requests_status`, `idx_program_requests_event_id`

---

### Table: `events`

Convention/event metadata for multi-event support (formerly `events_meta`).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Event ID |
| `slug` | TEXT | UNIQUE NOT NULL | URL-friendly identifier (e.g., `idol-stage-feb-2026`) |
| `name` | TEXT | NOT NULL | Event display name |
| `description` | TEXT | | Optional description |
| `start_date` | DATE | | Event start date |
| `end_date` | DATE | | Event end date |
| `venue_mode` | TEXT | DEFAULT `'multi'` | `'multi'` / `'single'` / `'calendar'` (calendar grid view, v2.7.0+) |
| `is_active` | BOOLEAN | DEFAULT 1 | Whether event is publicly visible |
| `theme` | TEXT | DEFAULT NULL | Per-event color theme (v2.1.1+) |
| `email` | TEXT | DEFAULT NULL | Contact email for ICS ORGANIZER field (v2.3.0+) |
| `timezone` | TEXT | DEFAULT `'Asia/Bangkok'` | Per-event timezone for ICS export and UTC conversion (v4.0.0+) |
| `cover_image` | TEXT | DEFAULT NULL | Hero cover image path (1600×900, 16:9) for homepage carousel (v8.0.0+) |
| `cover_image_card` | TEXT | DEFAULT NULL | Card cover image path (800×600, 4:3) for events grid (v8.0.0+) |
| `gallery_template` | TEXT | DEFAULT `'grid3'` | Photo gallery layout: `grid1`/`grid2`/`grid3`/`masonry` (v7.0.0+) |
| `header_cover_image` | TEXT | DEFAULT NULL | Header cover image path (1920×480, 4:1) shown behind page header (v9.2.0+) |
| `ticket_url` | TEXT | DEFAULT NULL | Ticket purchase URL (http/https only); displays orange "🎟️ ซื้อบัตร" button in event-detail nav (v9.5.0+) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Referenced by**: `programs.event_id`, `program_requests.event_id`, `credits.event_id`, `event_requests.event_id`

---

### Table: `credits`

Credits and references displayed on `credits.php`.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Credit ID |
| `title` | TEXT | NOT NULL | Credit title/name |
| `link` | TEXT | | URL (optional) |
| `description` | TEXT | | Additional description (optional) |
| `display_order` | INTEGER | DEFAULT 0 | Sort order (lower = shown first) |
| `event_id` | INTEGER | FK → `events.id` | Event this credit belongs to |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Cache**: Credits data is cached for 1 hour (`cache/credits.json`, configurable in `config/cache.php`)

---

### Table: `admin_users`

Admin user credentials and roles.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | User ID |
| `username` | TEXT | UNIQUE NOT NULL | Login username |
| `password_hash` | TEXT | NOT NULL | Bcrypt password hash |
| `display_name` | TEXT | | Display name in UI |
| `role` | TEXT | NOT NULL DEFAULT `'admin'` | `'admin'` (full access), `'agent'` (programs only), or `'organizer'` (assigned events only, v12.0.0+) |
| `twofa_enabled` | INTEGER | DEFAULT 0 | Optional TOTP 2FA enabled flag |
| `twofa_secret` | TEXT | | Base32 TOTP secret (DB-managed users only) |
| `twofa_backup_codes` | TEXT | | JSON array of hashed one-time recovery codes |
| `twofa_confirmed_at` | DATETIME | | Timestamp when 2FA was enabled |
| `twofa_last_used_step` | INTEGER | | Last accepted TOTP step for replay prevention |
| `is_active` | BOOLEAN | DEFAULT 1 | Whether user is active |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |
| `last_login_at` | DATETIME | | Last successful login |

---

### Table: `contact_channels`

Contact channel entries displayed on `contact.php`. Auto-created by `ensureContactChannelsTable()` (v2.10.0+).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Channel ID |
| `icon` | TEXT | DEFAULT `''` | Icon emoji or SVG string |
| `title` | TEXT | NOT NULL DEFAULT `''` | Channel name/label |
| `description` | TEXT | DEFAULT `''` | Additional description |
| `url` | TEXT | DEFAULT `''` | Link URL |
| `display_order` | INTEGER | DEFAULT 0 | Sort order (lower = shown first) |
| `is_active` | INTEGER | DEFAULT 1 | Whether channel is publicly visible |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |

> Managed via **Admin › Contact** tab (admin role only). Not required for setup — table is created on demand.

---

### Table: `artists`

Artist/group records shared across all events (v3.0.0+).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Artist ID |
| `name` | TEXT | UNIQUE NOT NULL | Canonical display name |
| `is_group` | INTEGER | DEFAULT 0 | 1 = group/unit, 0 = solo artist |
| `group_id` | INTEGER | FK → `artists.id` | Parent group (if this artist is a member) |
| `display_picture` | TEXT | DEFAULT NULL | Circular profile picture path (400×400 px, stored in `uploads/artists/`) (v6.0.0+) |
| `cover_picture` | TEXT | DEFAULT NULL | Banner cover picture path (1200×400 px, stored in `uploads/artists/`) (v6.0.0+) |
| `social_facebook` | TEXT | DEFAULT NULL | Facebook profile URL (v9.5.0+) |
| `social_instagram` | TEXT | DEFAULT NULL | Instagram profile URL (v9.5.0+) |
| `social_twitter` | TEXT | DEFAULT NULL | Twitter/X profile URL (v9.5.0+) |
| `social_tiktok` | TEXT | DEFAULT NULL | TikTok profile URL (v9.5.0+) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

> **Reuse rate**: 74.7% of artists (62/83) appear in 2+ events.
> **Social links** *(v9.5.0)*: Displayed as icon buttons on `/artist/{id}` header and `/artists` portal (group cards + solo cards). Validated with `sanitize_social_url()` — accepts http/https only.

---

### Table: `event_pictures`

Per-event photo gallery (v7.0.0+). Managed via Admin › Events edit modal.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Picture ID |
| `event_id` | INTEGER | FK → `events.id` ON DELETE CASCADE | Owning event |
| `filename` | TEXT | NOT NULL | Relative path (e.g. `uploads/events/1/abc123.jpg`) |
| `caption` | TEXT | DEFAULT `''` | Optional caption text |
| `display_order` | INTEGER | DEFAULT 0 | Sort order for gallery display |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Upload time |

**Indexes**: `idx_event_pictures_event_id`, `idx_event_pictures_order`
**Storage**: Files sharded to `uploads/events/{event_id}/` (v7.2.0+)

---

### Table: `event_requests`

User-submitted proposals to add new events plus organizer activation requests (v12.1.0+). Managed via Admin › Requests › 🗓️ Event Requests sub-tab.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Request ID |
| `request_type` | TEXT | NOT NULL | `'add'`, `'modify'`, or `'activate'` |
| `event_id` | INTEGER | FK → `events.id` (nullable) | Existing event for modify/activate requests |
| `name` | TEXT | | Proposed event name |
| `description` | TEXT | | Proposed event description |
| `start_date` | DATE | | Proposed start date (YYYY-MM-DD) |
| `end_date` | DATE | | Proposed end date (YYYY-MM-DD) |
| `requester_name` | TEXT | NOT NULL | Name of the person submitting |
| `requester_email` | TEXT | | Email of the submitter (optional) |
| `note` | TEXT | | Additional notes from submitter |
| `status` | TEXT | DEFAULT `'pending'` | `'pending'`, `'approved'`, or `'rejected'` |
| `admin_note` | TEXT | | Admin's note when approving/rejecting |
| `reviewed_at` | DATETIME | | When the request was reviewed |
| `reviewed_by` | TEXT | | Admin username who reviewed |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Submission time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

---

### Table: `artist_requests`

Organizer-submitted requests to add new artists (v12.3.0+). Managed via Admin › Requests › Artist Request; organizers submit from Admin › Artists › Request new artist.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Request ID |
| `name` | TEXT | NOT NULL | Proposed artist/group name |
| `is_group` | INTEGER | DEFAULT 0 | `1` for group, `0` for solo/member |
| `group_id` | INTEGER | FK → `artists.id` (nullable) | Parent group for solo/member requests |
| `social_facebook` | TEXT | | Facebook URL |
| `social_instagram` | TEXT | | Instagram URL |
| `social_twitter` | TEXT | | X/Twitter URL |
| `social_tiktok` | TEXT | | TikTok URL |
| `requester_user_id` | INTEGER | FK → `admin_users.id` | Organizer user who submitted |
| `requester_name` | TEXT | NOT NULL | Organizer display name/username |
| `requester_email` | TEXT | | Reserved requester email field |
| `status` | TEXT | DEFAULT `'pending'` | `'pending'`, `'approved'`, or `'rejected'` |
| `admin_note` | TEXT | | Reviewer note |
| `reviewed_at` | DATETIME | | When reviewed |
| `reviewed_by` | TEXT | | Reviewer username |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Submission time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Indexes**: `idx_artist_requests_status`, `idx_artist_requests_created_at`, `idx_artist_requests_requester_user_id`

---

### Table: `program_artists`

Many-to-many junction between programs and artists (v3.0.0+).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Row ID |
| `program_id` | INTEGER | FK → `programs.id` ON DELETE CASCADE | Program |
| `artist_id` | INTEGER | FK → `artists.id` ON DELETE CASCADE | Artist |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Link creation time |

**Unique constraint**: `(program_id, artist_id)`

> ICS import auto-links CATEGORIES field → `artist_id` via direct name match and `artist_variants` lookup.
> 98.2% of programs with categories are linked (336/342).

---

### Table: `artist_variants`

Alias/variant names for artists — used by ICS import to recognise alternate spellings (v3.0.0+).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Variant ID |
| `artist_id` | INTEGER | FK → `artists.id` ON DELETE CASCADE | Owning artist |
| `variant` | TEXT | NOT NULL | Alternate name / alias |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |

**Unique constraint**: `(artist_id, variant)`
**Index**: `idx_artist_variants_artist_id`

> Managed via **Admin › Artists** tab — Variants modal per artist. Seeded from `data/artists-mapping.json` by migration script.

---

### Table: `venues`

Canonical venue/location records — a dedup layer over `programs.location` (v16.0.0+). `programs.location` stays free text (no FK); this table drives autocomplete, auto-canonicalization on save/import, the admin Merge tool, and the public venue profile pages.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Venue ID |
| `name` | TEXT | UNIQUE NOT NULL | Canonical venue name |
| `description` | TEXT | DEFAULT NULL | Optional description |
| `map_url` | TEXT | DEFAULT NULL | Optional map link |
| `is_online` | INTEGER | DEFAULT 0 | `1` = online platform (hidden from `/venues` portal, v16.0.1) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |

> `venue_resolve_canonical()` in `admin/api.php` resolves `location` (exact → variant → auto-create) before binding on create/update/bulk-update/ICS-import. Profile at `/venue/{id}`; portal at `/venues`.

---

### Table: `venue_variants`

Alias/variant names for venues — drives autocomplete + canonicalization (v16.0.0+).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Variant ID |
| `venue_id` | INTEGER | FK → `venues.id` ON DELETE CASCADE | Owning venue |
| `variant` | TEXT | NOT NULL | Alternate name / alias |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |

**Unique constraint**: `(venue_id, variant)`

---

### Table: `event_organizers`

Maps organizer users to the events they may manage (v12.0.0+). Combined with `events.created_by_user_id` (audit-only).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `event_id` | INTEGER | FK → `events.id` | Assigned event |
| `user_id` | INTEGER | FK → `admin_users.id` | Organizer user |
| `assigned_by` | INTEGER | FK → `admin_users.id` | Admin who assigned |
| `assigned_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Assignment time |

> Assignment endpoints (`event_organizers_list`, `event_organizers_update`) require `admin` role. Organizer access is scoped to assigned events only; unassignment immediately revokes access (creator reference does not grant permanent access).

---

### FTS5 Virtual Tables *(v9.0.0+)*

Full-text search virtual tables powered by SQLite FTS5 (`unicode61` tokenizer). Created by `tools/migrate-add-fts5.php`.

| Virtual Table | Indexed Columns | Description |
|---------------|-----------------|-------------|
| `programs_fts` | `title`, `categories`, `organizer`, `description` | Full-text index for programs |
| `events_fts` | `name`, `description` | Full-text index for events |
| `artists_fts` | `name` | Full-text index for artists |

**Auto-sync triggers** (9 total): `programs_ai/au/ad`, `events_ai/au/ad`, `artists_ai/au/ad` — keep FTS tables in sync on INSERT/UPDATE/DELETE.

> `fts5_rebuild_all()` is called automatically after ICS import. A graceful `LIKE '%q%'` fallback activates when FTS tables are absent.

---

## 📊 Performance

### ICS Files vs. SQLite (500 events)

| Metric | ICS Files | SQLite | Improvement |
|--------|-----------|--------|-------------|
| Initial page load | 2.1s | 0.18s | **11.7x faster** |
| Filter by artist | 1.8s | 0.09s | **20x faster** |
| Get all organizers | 1.5s | 0.05s | **30x faster** |
| Memory usage | 45 MB | 12 MB | **3.75x less** |

### Why SQLite is Faster

1. **No parsing** — data already structured in the DB
2. **Indexed queries** — fast lookups on `start`, `location`, `categories`
3. **Application cache** — data version (10 min) + credits (1 hour)
4. **Compiled SQL** — optimized query execution via PDO

---

## 🔄 Database Management

### SQLite CLI

```bash
sqlite3 data/calendar.db

# View all tables
.tables
# → programs, events, program_requests, event_requests, artist_requests,
#   credits, admin_users, event_organizers, contact_channels,
#   artists, artist_variants, program_artists, venues, venue_variants,
#   event_pictures, *_fts (FTS5 virtual tables)

# Show table schema
.schema programs

# Count programs
SELECT COUNT(*) FROM programs;

# Programs on specific date
SELECT * FROM programs WHERE DATE(start) = '2026-02-07' ORDER BY start;

.quit
```

### Backup & Restore

Use **Admin Panel → Backup tab** for GUI-based backup/restore, or via CLI:

```bash
# Backup
cp data/calendar.db backups/calendar.db.backup

# Restore
cp backups/calendar.db.backup data/calendar.db
```

### Optimize (run monthly on large databases)

```bash
sqlite3 data/calendar.db "VACUUM;"   # Compact and reclaim space
sqlite3 data/calendar.db "ANALYZE;"  # Update query planner statistics
```

### Troubleshooting

| Error | Cause | Fix |
|-------|-------|-----|
| `unable to open database file` | `data/` missing or DB not created | Run `setup.php` or `php tools/import-ics-to-sqlite.php` |
| `database is locked` | Another process holds the DB | Check `lsof data/calendar.db`; restart PHP |
| `database disk image is malformed` | Corruption | Restore from backup (Admin → Backup tab) or re-import from ICS |
| Data not updating | Stale cache or browser cache | Re-run import + change `APP_VERSION` in `config/app.php` |

---

## 🔗 File Relationships

```
config.php (bootstrap)
    ├── config/app.php          → APP_VERSION, VENUE_MODE, MULTI_EVENT_MODE
    ├── config/admin.php        → Auth fallback, IP whitelist, SESSION_TIMEOUT
    ├── config/security.php     → Rate limiting
    ├── config/database.php     → DB_PATH
    ├── config/cache.php        → Cache TTL constants
    ├── config/google.php       → GOOGLE_* (from google-config.json)
    ├── config/email.php        → EMAIL_* SMTP (from email-config.json)
    ├── config/telegram.php     → TELEGRAM_* (from telegram-config.json)
    ├── config/webpush.php      → WEBPUSH_* VAPID (from webpush-config.json)
    ├── config/favorites.php    → FAVORITES_* (HMAC from favorites-config.json)
    ├── functions/helpers.php   → get_db(), event helpers, timezone
    ├── functions/cache.php     → Cache read/write/invalidate
    ├── functions/admin.php     → Auth + RBAC + rate limiting + 2FA flow
    ├── functions/totp.php      → Admin TOTP 2FA helpers
    ├── functions/security.php  → Sanitize, CSRF, headers, IP whitelist
    ├── functions/audit.php     → Admin audit log (redacted JSON Lines)
    ├── functions/seo.php       → Meta/OG/Twitter/JSON-LD
    ├── functions/ics.php       → Shared ICS helpers
    ├── functions/ads.php       → AdSense unit rendering
    ├── functions/telegram.php  → Telegram Bot API + notify state
    ├── functions/webpush.php   → Web Push VAPID/aes128gcm + SSRF allow-list
    ├── functions/favorites.php → Anonymous favorites slug + storage
    ├── functions/search.php    → FTS5 search
    └── functions/email.php     → Request email notifications

index.php
    ├── config.php              → bootstrap
    ├── IcsParser.php           → if no DB (fallback mode)
    └── data/calendar.db        → via get_db() or IcsParser

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

*Idol Stage Timetable v16.7.1*
