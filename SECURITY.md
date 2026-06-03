# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 16.5.x  | :white_check_mark: |
| 16.x.x  | :white_check_mark: |
| 15.x.x  | :white_check_mark: |
| < 15.0  | :x:                |

---

## Reporting a Vulnerability

**Please DO NOT report security vulnerabilities through public GitHub issues.**

Instead, please report them via:
- Email: annop@thaicyberpoint.com
- Twitter DM: [@FordAntiTrust](https://x.com/FordAntiTrust)

You should receive a response within 48 hours. If accepted, we will work on a fix and coordinate the release.

---

## Security Best Practices for Deployment

### 1. Change Default Credentials

**Before deploying**, change admin credentials. Use the Setup Wizard (`setup.php` Step 5) or generate a hash manually:

```bash
# Generate new password hash
php -r "echo password_hash('YOUR_STRONG_PASSWORD', PASSWORD_DEFAULT);"
```

Then update in `config/admin.php`:
```php
define('ADMIN_USERNAME', 'your_username');
define('ADMIN_PASSWORD_HASH', 'hash_from_above_command');
```

**Password Requirements:**
- At least 12 characters
- Mix of uppercase, lowercase, numbers, symbols
- Avoid common words or patterns

---

### 2. Enable IP Whitelist (Recommended)

Restrict admin access to trusted IPs only.

In `config/admin.php`:
```php
define('ADMIN_IP_WHITELIST_ENABLED', true);
define('ADMIN_ALLOWED_IPS', [
    '129.1.0.1',           // localhost
    '192.168.1.100',       // your office IP
    '192.168.1.0/24',      // your office network
]);
```

---

### 3. Enable Production Mode

In `config/app.php`:
```php
define('PRODUCTION_MODE', true);
```

This hides detailed error messages from users.

---

### 4. Secure File Permissions

```bash
# PHP files - read only for web server
chmod 644 *.php

# Database directory - read/write for web server
chmod 755 data/
chmod 600 data/calendar.db
chown -R www-data:www-data data/

# Directories
chmod 755 ics/ admin/ api/ tools/

# Cache directory - writable
chmod 755 cache/
```

---

### 5. HTTPS Only

**Always use HTTPS in production.**

For Apache, add to virtual host:
```apache
<VirtualHost *:443>
    # Force HTTPS
    Header always set Strict-Transport-Security "max-age=31536000"
    
    # Your other config...
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    Redirect permanent / https://yourdomain.com/
</VirtualHost>
```

For Nginx:
```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    
    # Add security headers
    add_header Strict-Transport-Security "max-age=31536000" always;
    
    # Your other config...
}
```

---

### 6. Database Security

**Backup regularly:**
```bash
# Automated backup (cron job)
0 2 * * * cp /path/to/calendar.db /path/to/backups/calendar-$(date +\%Y\%m\%d).db
```

**Prevent direct access:**
- Database is stored in `data/calendar.db` (not web root)
- The `data/` directory has `.htaccess` with `Deny from all` — HTTP requests return 403
- Do not move `calendar.db` to the web root

---

### 7. Rate Limiting

Configured in `api/request.php`:
- Default: 10 requests per hour per IP
- Adjust as needed based on expected usage

For DDoS protection, use Cloudflare or similar CDN.

---

### 8. Security Headers

Headers are automatically set in `functions/security.php`:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`

Additional headers via web server config:

**Apache** (`.htaccess`):
```apache
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

**Nginx**:
```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

---

### 9. Regular Updates

- Keep PHP updated
- Update dependencies (if any)
- Monitor security advisories
- Check for updates: [Releases](https://github.com/fordantitrust/stage-idol-calendar/releases)

---

## Security Features

### Built-in Protection

✅ **XSS Protection**
- Server-side: `htmlspecialchars()` on all output
- Client-side: Using `textContent` instead of `innerHTML`

✅ **SQL Injection Prevention**
- PDO prepared statements for all queries
- No dynamic SQL concatenation

✅ **CSRF Protection**
- Token-based validation for admin operations
- Session-based authentication

✅ **Rate Limiting**
- Prevents spam/abuse of request system
- IP-based tracking

✅ **Input Validation**
- Strict type checking
- Length limits
- Format validation

✅ **Authentication**
- Bcrypt password hashing
- Session-based login
- Optional IP whitelist

✅ **Role-Based Access Control** (Added in v1.2.5, expanded to 3 roles in v12.0.0)
- Three roles: `admin` (full access), `agent` (programs management), and `organizer` (scoped to assigned events only — see Organizer Role Authorization below)
- Defense in depth: Server-side HTML hiding + API-level role enforcement (`$adminOnlyActions` dispatcher list + per-function `require_api_admin_role()`)
- Admin-only actions: user management, backup/restore, contact channels, settings, and all log viewers/downloads (Telegram/Web Push/Email/Audit — see LOW-1)
- Safety guards: Cannot delete self, cannot change own role, must keep 1+ active admin

✅ **Feed & URL Security** (Added in v2.6.x–v2.7.x)
- `stream_url` scheme validation: rejects non-http/https schemes (e.g. `javascript:`) — prevents stored XSS
- ICS upload: MIME type validation + `BEGIN:VCALENDAR`/`END:VCALENDAR` structure check before parse
- Inactive event data leak: requesting inactive/unknown slug returns 404 instead of leaking all events
- Rate limit race condition: `flock(LOCK_EX)` wraps read→modify→write in `api/request.php`
- XSS in filter-tag removal: `json_encode()` instead of `addslashes()` for artist/venue/type onclick values
- LIKE SQL Injection (admin search): `%` and `_` wildcards escaped before LIKE query; `ESCAPE` clause added
- Directory access: `data/`, `cache/`, `backups/`, `ics/` all have `Deny from all` in `.htaccess`
- Path disclosure: public API error responses return generic messages; no server paths or PDO details
- Concurrent cache write: `LOCK_EX` flag in `file_put_contents()` for all cache file writes

✅ **Social URL Validation** (Added in v9.5.0)
- `sanitize_social_url()` in `admin/api.php` validates all artist social link fields (`social_facebook`, `social_instagram`, `social_twitter`, `social_tiktok`)
- Accepts only `http://` and `https://` schemes — rejects `javascript:`, `data:`, and other dangerous schemes preventing stored XSS
- `ticket_url` on events uses the same validation pattern

✅ **Google Config Security** (Added in v9.1.0)
- `config/google-config.json` stores GA4 ID and AdSense credentials; protected from HTTP access by `config/.htaccess` (`Require all denied` for every file under `config/`, since v15.5.0 — see LOW-2) — no key exposure via browser
- Google Analytics + AdSense settings edited exclusively through Admin UI (`analytics_config_get` / `analytics_config_save`); admin-role only; CSRF-protected
- Constants `GOOGLE_ANALYTICS_ID`, `GOOGLE_ADS_CLIENT`, `GOOGLE_ADS_SLOT_*` loaded at runtime from JSON — no credentials in committed PHP constants

✅ **Email Config Security** (Added in v9.6.0)
- `config/email-config.json` stores SMTP notification settings and is protected from direct HTTP access by `config/.htaccess`
- Email settings are edited through Admin UI only (`email_config_get`, `email_config_save`, `email_test_send`); admin-role only and CSRF-protected
- Request notification bodies escape HTML user input, while plain-text bodies remain readable
- Email delivery failures are logged to `cache/logs/email.log` and do not expose SMTP errors to public request submitters

✅ **Admin Audit Log** (Added in v14.0.0)
- `functions/audit.php` appends JSON Lines to `cache/logs/admin-audit-YYYY-MM-DD.log` with `FILE_APPEND | LOCK_EX`; the engine never throws
- `audit_redact()` recursively strips sensitive keys (`password`, `password_hash`, `twofa_secret`, `twofa_backup_codes`, `csrf_token`, `smtp_password`, `telegram_bot_token`, `webhook_secret`, …) before persistence
- Auth + write hooks across `functions/admin.php`, `admin/login.php`, `admin/api.php`, and the public request APIs record login/2FA/logout, CRUD, and rate-limit-blocked events
- Viewer endpoint requires `admin` role; daily rotation cron (`cron/rotate-admin-audit-logs.php`) is CLI-only behind `cron/.htaccess` deny-all; UA capture bounded to 500 chars to prevent log injection

✅ **Web Push Security** (Added in v15.0.0, hardened in v15.5.0)
- VAPID + RFC 8291 `aes128gcm` implemented in-process with PHP/OpenSSL primitives; no third-party dependency
- VAPID private key is never serialized to the client: `getWebPushConfig()` unsets `vapid_private_key_pem` before responding; key generation returns only the public key; admin-role + CSRF on all config endpoints
- `curl` send sets `CURLOPT_SSL_VERIFYPEER => true` and does not follow redirects
- **SSRF allow-list (MEDIUM-1):** `webpush_validate_endpoint()` accepts only known push services (FCM, Mozilla, Apple exact; `.notify.windows.com`, `.push.apple.com` suffix); HTTPS-only; IPv4/IPv6 literals rejected; enforced at subscribe time (`api/push.php`) **and** at send time (`webpush_send()` short-circuits `endpoint_not_allowed`), so pre-allow-list records are purged automatically by cron
- Dev localhost exception is double-gated by `WEBPUSH_ALLOW_LOCALHOST=true` **and** `PRODUCTION_MODE=false` (inert in production)

✅ **Admin Hardening** (Added in v15.5.0 — security audit revision 1–6)
- **Login CSRF (MEDIUM-2):** `admin/login.php` verifies `$_POST['csrf_token']` **before** rate-limit accounting, so an attacker without a valid token cannot exhaust a legitimate user's per-IP login budget; CSRF failures are audit-logged (`error_code=csrf_invalid`)
- **Log-viewer authorization (LOW-1):** `getTelegramLog()`, `getWebPushLog()`, `getEmailLog()` now require `admin` role (function-level + dispatcher-level `$adminOnlyActions`) — agents/organizers can no longer read notification logs
- **Directory hardening (LOW-2 / LOW-3):** `config/.htaccess` and `tools/.htaccess` switched to `Require all denied` (Apache 2.4) with a 2.2 `Deny from all` fallback; all `Allow from` LAN CIDRs removed
- **Secret hygiene (LOW-4):** `.gitignore` blocks `*.db`, `test-*.php`, `debug-*.php`; Telegram webhook secret retired (`webhook_secret=""`, `verify_telegram_request()` fail-closes on empty)

✅ **Favorites Secret Management** (Hardened in v16.5.3 — security audit revision 7, LOW-5)
- `FAVORITES_HMAC_SECRET` (signs the anonymous favorites slug) is loaded from `config/favorites-config.json`, which is gitignored (`config/*-config.json`) and HTTP-denied by `config/.htaccess` — never stored in a git-tracked source file
- `config/favorites.php` falls back to a placeholder when the JSON is absent; `tools/generate-favorites-secret.php` writes the gitignored JSON directly with an overwrite guard, closing the recurrence path
- Slug parsing uses a strict length check, regex, and `hash_equals()` (constant-time) HMAC comparison; favorites files are written atomically (`tmp + rename`) and rate-limited per IP under `flock(LOCK_EX)`

---

## Known Limitations

### Current Version (v16.5.3)

✅ **Session Security** (Implemented in v1.1.0)
- Session timeout (2 hours, configurable)
- Session ID regeneration on login/logout
- Secure/HttpOnly cookie flags
- Timing attack prevention with hash_equals()
- Race condition prevention with safe_session_start()

✅ **File Upload** (Implemented in v1.1.0)
- Admin can upload ICS files through the admin panel
- File type validation (extension + MIME type)
- File size limit (5MB)
- Preview before import with duplicate detection

✅ **CSRF Protection** (Implemented in v1.1.0)
- Token-based validation for all POST/PUT/DELETE requests
- X-CSRF-Token header required for admin API

✅ **IP Whitelist** (Implemented in v1.1.0)
- Optional IP restriction for admin panel
- Supports single IP, CIDR notation, and IPv6

✅ **Two-Factor Authentication** (Implemented in v10.0.0)
- Optional per-user TOTP 2FA for DB-managed admin users (`admin_users`)
- RFC 6238 compatible 6-digit codes with 30-second period and ±1 step verification window
- TOTP replay prevention via `twofa_last_used_step`
- One-time backup codes are stored as password hashes and consumed after use
- Config fallback users remain password-only for backward compatibility
- Since v10.1.0, 2FA schema migration is manual through `setup.php` or `php tools/migrate-add-admin-2fa-columns.php`; the Admin API caches confirmed schema readiness in `data/.admin_2fa_columns_ready`

✅ **Organizer Role Authorization** (Implemented in v12.2.0)
- Organizer users can manage only events listed in `event_organizers`
- `events.created_by_user_id` is audit-only and does not grant permanent access after unassignment
- Admin-only assignment endpoints require `admin` role and CSRF protection
- Organizer users are denied at the API layer for Requests, Event Request review actions, ICS Import, Artists, Users, Backup/Restore, Settings/config/contact, Email/Telegram/Analytics config, cross-user 2FA reset, and direct event activation
- Organizer activation flow requires a pending `event_requests.request_type='activate'` record; only admin/agent approval can set `events.is_active=1`
- Organizer Program artist references are autocomplete-only; API validation rejects artist names that do not already exist as `artists.name` or `artist_variants.variant`
- Since v12.3.0, organizer users can submit `artist_requests_create`; this creates a pending request only and never writes directly to `artists`. Admin/agent approval performs duplicate-name checks before creating the artist record.

---

## Security Checklist

Before going live:

- [ ] Changed default admin credentials
- [ ] Generated strong password hash (`php tools/generate-password-hash.php`)
- [ ] Set `PRODUCTION_MODE` to `true` in `config/app.php`
- [ ] Configured HTTPS
- [ ] Set proper file permissions
- [ ] Enabled IP whitelist (if applicable) in `config/admin.php`
- [ ] Enabled 2FA for DB-managed admin users where required
- [ ] Ran organizer migration if using organizer accounts: `php tools/migrate-add-organizer-role.php`
- [ ] Configured backups
- [ ] Reviewed security headers
- [ ] Tested admin login
- [ ] Tested rate limiting
- [ ] Run automated tests: `php tests/run-tests.php`
- [ ] Verify `config/*-config.json` (google, email, telegram, webpush, favorites) are NOT committed — all are gitignored by `config/*-config.json`
- [ ] Confirm `config/webpush-config.json` does NOT set `"allow_localhost": true` in production
- [ ] Confirm `config/favorites-config.json` exists with a generated `hmac_secret` and is not tracked by git
- [ ] Run required migrations: 2FA, organizer role, audit log, Web Push, venues
- [ ] Schedule log-rotation cron jobs (Telegram, Web Push, Email, Admin Audit)
- [ ] Verify direct HTTP access is blocked for `data/`, `cache/`, `backups/`, `ics/`, `cron/`, `config/`, and `tools/`
- [ ] Lock setup with `data/.setup_locked` after install/maintenance
- [ ] Updated contact information
- [ ] Removed test data from database

---

## Incident Response

If you discover a security issue:

1. **Contain**: Temporarily take site offline if critical
2. **Assess**: Determine scope of vulnerability
3. **Fix**: Apply patch or workaround
4. **Verify**: Test fix thoroughly
5. **Deploy**: Push fix to production
6. **Review**: Audit logs for evidence of exploitation
7. **Notify**: Inform users if data was compromised

---

## Additional Resources

- [Security Audit Report 2026](docs/SECURITY_AUDIT_2026.md) — full findings + remediation history (revision 7, re-verified at v16.5.x; all items closed, internal use only not public)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [SQLite Security](https://www.sqlite.org/security.html)

---

**Last Updated:** 2026-06-02
**Version:** 16.5.3
**Latest Audit:** `docs/SECURITY_AUDIT_2026.md` revision 7 — ✅ all findings closed (HIGH-1, MEDIUM-1/2, LOW-1→5); full suite 13,231 / 13,231
