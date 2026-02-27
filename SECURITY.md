# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 2.0.x   | :white_check_mark: |
| 1.x.x   | :x:                |

---

## Reporting a Vulnerability

**Please DO NOT report security vulnerabilities through public GitHub issues.**

Instead, please report them via:
- Email: [Insert your security contact email]
- Twitter DM: [@FordAntiTrust](https://x.com/FordAntiTrust)

You should receive a response within 48 hours. If accepted, we will work on a fix and coordinate the release.

---

## Security Best Practices for Deployment

### 1. Change Default Credentials

**Before deploying**, change admin credentials in `config.php`:

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
    '127.0.0.1',           // localhost
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

# Database - read/write for web server only
chmod 600 calendar.db
chown www-data:www-data calendar.db

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
- Don't put `calendar.db` in web-accessible directory
- Or use `.htaccess` to deny access:
```apache
<Files "calendar.db">
    Require all denied
</Files>
```

---

### 7. Rate Limiting

Configured in `api/request.php`:
- Default: 10 requests per hour per IP
- Adjust as needed based on expected usage

For DDoS protection, use Cloudflare or similar CDN.

---

### 8. Security Headers

Headers are automatically set in `config.php`:
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
- Check for updates: [Releases](https://github.com/yourusername/stage-idol-calendar/releases)

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

✅ **Role-Based Access Control** (Added in v1.2.5, updated in v2.0.0)
- Two roles: `admin` (full access) and `agent` (events management only)
- Defense in depth: Server-side HTML hiding + API-level role enforcement
- Admin-only actions: user management, backup/restore
- Safety guards: Cannot delete self, cannot change own role, must keep 1+ active admin

---

## Known Limitations

### Current Version (2.0.1)

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

⚠️ **Two-Factor Authentication**
- Not yet implemented
- Recommended for high-security deployments

---

## Security Checklist

Before going live:

- [ ] Changed default admin credentials
- [ ] Generated strong password hash (`php tools/generate-password-hash.php`)
- [ ] Set `PRODUCTION_MODE` to `true` in `config/app.php`
- [ ] Configured HTTPS
- [ ] Set proper file permissions
- [ ] Enabled IP whitelist (if applicable) in `config/admin.php`
- [ ] Configured backups
- [ ] Reviewed security headers
- [ ] Tested admin login
- [ ] Tested rate limiting
- [ ] Run automated tests: `php tests/run-tests.php`
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

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [SQLite Security](https://www.sqlite.org/security.html)

---

**Last Updated:** 2026-02-27
**Version:** 2.0.1
