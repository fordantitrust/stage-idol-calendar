# 🤖 Telegram Bot Notifications Setup Guide

Telegram Bot notification system for "My Upcoming Programs" - Detailed setup guide

### Architecture
- **No DB migration** — Telegram metadata stored in existing favorites JSON files (`telegram_chat_id`, `telegram_notified` map)
- **Idempotent** — Notifications tracked with program ID and timestamp to prevent duplicates
- **Scalable** — Hybrid design: JSON-based for <1000 users, can migrate to DB table when needed
- **Flexible** — Notification window, history retention, and duplicate prevention all configurable

---

## 📋 Table of Contents

1. [Requirements](#requirements)
2. [Step 1: Create Telegram Bot](#step-1-create-telegram-bot)
3. [Step 2: Configure Webhook](#step-2-configure-webhook)
4. [Step 3: Configure Config](#step-3-configure-config)
5. [Step 4: Setup Cron Job](#step-4-setup-cron-job)
6. [Step 5: Test the System](#step-5-test-the-system)
7. [Troubleshooting](#troubleshooting)

---

## Requirements

- ✅ Domain with HTTPS (Telegram requires HTTPS only)
- ✅ Server capable of running cron jobs (or PHP CLI)
- ✅ Telegram account to create a bot
- ✅ SSH/shell access to setup cron

---

## Step 1: Create Telegram Bot

### 1.1 Open BotFather on Telegram

1. Open Telegram
2. Search for `@BotFather` or visit https://t.me/botfather
3. Send the command `/start`

### 1.2 Create a New Bot

Send the command:

```
/newbot
```

BotFather will ask for information:

**Botname?** (Display name, e.g. "Idol Stage Timetable Bot")
```
Idol Stage Timetable Bot
```

**Username?** (@username for the bot, must end with "bot")
```
IdolStageBot
```

### 1.3 Save the Bot Token

BotFather will send you a message:

```
Done! Congratulations on your new bot. You will find it at t.me/IdolStageBot. 
You can now add a description, about section and profile picture for your bot, see /help for a list of commands.

Use this token to access the HTTP API:
123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghij
```

**💾 Save these values:**
- Bot Token: `123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghij`
- Bot Username: `IdolStageBot`

---

## Step 2: Configure Webhook

### 2.1 Generate a Secret Token

Create a random string (at least 32 characters) to secure webhook requests:

```bash
# Linux/Mac
openssl rand -hex 32
# Output: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0

# Or use Python
python3 -c "import secrets; print(secrets.token_hex(32))"
```

**💾 Save:** `a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6`

### 2.2 Setup Webhook URL

**⚠️ Important:** Domain must be HTTPS only (HTTP not supported)

**⚠️ Important:** If your app is in a subdirectory, include it in the URL:

Examples:
- ✅ `https://idol-calendar.example.com/api/telegram` (root installation)
- ✅ `https://yourdomain.com/idoltrack/api/telegram` (subdirectory `/idoltrack`)
- ✅ `https://yourdomain.com/calendar/api/telegram` (subdirectory `/calendar`)
- ❌ `http://example.com/api/telegram` (HTTP not supported)
- ❌ `https://yourdomain.com/api/telegram` (if app is at `/idoltrack` — **missing subdirectory!**)

---

## Step 3: Configure Config

### 3.1 Edit `config/telegram.php`

```bash
nano config/telegram.php
```

Or edit with your editor:

```php
<?php
// Bot credentials from @BotFather
define('TELEGRAM_BOT_TOKEN', '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghij');
define('TELEGRAM_BOT_USERNAME', 'IdolStageBot');    // Without @
define('TELEGRAM_WEBHOOK_SECRET', 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0');

// Notification settings
define('TELEGRAM_NOTIFY_BEFORE_MINUTES', 60);  // Notify 60 minutes before
define('TELEGRAM_ENABLED', true);              // Enable the feature
```

**Important values:**

| Key | Description |
|---|---|
| `TELEGRAM_BOT_TOKEN` | Token from BotFather (keep secure!) |
| `TELEGRAM_BOT_USERNAME` | Bot name without @ (e.g. `IdolStageBot`) |
| `TELEGRAM_WEBHOOK_SECRET` | Secret token for webhook validation |
| `TELEGRAM_NOTIFY_BEFORE_MINUTES` | Minutes before program to send notification (default 60) |
| `TELEGRAM_ENABLED` | Enable/disable system (true/false) |

### 3.2 Save the File

```
Ctrl+O (save)
Ctrl+X (exit nano)
```

---

## Step 4: Setup Cron Job

> ℹ️ **Note:** If you configured Telegram via Admin UI (recommended), you don't need to run the setup script below — the "🧪 Test Webhook" button in Admin UI automatically registers the webhook URL with Telegram Bot API. You only need to add the cron job.

### 4.1 Run Setup Script (Optional - CLI method)

If you prefer to use the command line, run:

```bash
php tools/setup-telegram-webhook.php https://yourdomain.com
```

Example output:

```
🔧 Setting up Telegram Webhook
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Bot Token: 123456789:ABC...
Webhook URL: https://yourdomain.com/api/telegram
Secret Token: a1b2c3d4...

✅ Webhook registered successfully!

Response:
{
  "ok": true,
  "result": true
}

📊 Webhook Info:
URL: https://yourdomain.com/api/telegram
Pending: 0
Last error: None

✨ Setup complete!
Now run: */15 * * * * php /path/to/cron/send-telegram-notifications.php >> /var/log/tg-notify.log 2>&1
```

### 4.2 Add Cron Job

Open crontab:

```bash
crontab -e
```

Add this line:

```bash
# Telegram notifications - run every 15 minutes
*/15 * * * * php /path/to/stage-idol-calendar/cron/send-telegram-notifications.php >> /var/log/tg-notify.log 2>&1
```

**⚠️ Important:**
- Replace `/path/to/stage-idol-calendar` with your actual project path
- Example: `/home/user/public_html/stage-idol-calendar/cron/send-telegram-notifications.php`

Save and exit (`Ctrl+X` → `y` → `Enter`)

### 4.3 Verify Cron

```bash
# Linux
crontab -l

# Output should show:
# */15 * * * * php /path/to/cron/send-telegram-notifications.php >> /var/log/tg-notify.log 2>&1
```

---

## Step 5: Test the System

### 5.1 Verify Webhook Registration

```bash
curl -X GET "https://api.telegram.org/botTOKEN/getWebhookInfo" \
  -H "Content-Type: application/json"
```

Replace `TOKEN` with your actual bot token

Example output:

```json
{
  "ok": true,
  "result": {
    "url": "https://yourdomain.com/api/telegram",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "ip_address": "1.2.3.4",
    "last_error_date": 0,
    "last_error_message": "",
    "last_synchronization_error_date": 0,
    "max_connections": 40,
    "allowed_updates": ["message"]
  }
}
```

✅ Check:
- `"url"` = your webhook URL
- `"pending_update_count"` = 0 (no pending)
- `"last_error_message"` = "" (no errors)

### 5.2 Test Telegram Link (Manual)

1. Go to `/my/{slug}` page of your favorites
2. Click "🔔 Link Telegram" button
3. Open Telegram
4. Search for bot `@IdolStageBot`
5. Send command:
   ```
   /start YOUR_SLUG_HERE
   ```
   (Replace `YOUR_SLUG_HERE` with your actual slug from the page)

6. Bot should reply:
   ```
   ✅ Connected successfully!
   You will receive notifications before programs from artists you follow
   ```

### 5.3 Test Notification (Manual)

```bash
php cron/send-telegram-notifications.php
```

Output should be:

```
[2026-04-13 14:30:25] Starting Telegram notifications
[2026-04-13 14:30:25] Completed - Notified: 2, Skipped: 0, Errors: 0
```

✅ Means:
- Sent 2 notifications successfully
- No errors

### 5.4 Check Log File

```bash
tail -f /var/log/tg-notify.log
```

You should see:

```
[2026-04-13 14:15:00] Starting Telegram notifications
[2026-04-13 14:15:02] Completed - Notified: 1, Skipped: 0, Errors: 0
[2026-04-13 14:30:00] Starting Telegram notifications
[2026-04-13 14:30:01] Completed - Notified: 0, Skipped: 2, Errors: 0
```

---

## Troubleshooting

### ❌ "Webhook registration failed"

**Problem:** Setup script failed

**Solution:**
```bash
# 1. Check Bot Token
php -r "echo 'Token: 123456789:ABC...';"

# 2. Check Domain HTTPS
curl -I https://yourdomain.com/api/telegram

# 3. Try again
php tools/setup-telegram-webhook.php https://yourdomain.com
```

### ❌ "getWebhookInfo shows empty URL"

**Problem:** Webhook not registered

**Solution:**
```bash
# Delete old webhook
curl -X POST "https://api.telegram.org/botTOKEN/deleteWebhook"

# Try again
php tools/setup-telegram-webhook.php https://yourdomain.com
```

### ❌ "No notifications sent"

**Problem:** Cron job not running

**Solution:**
1. Check cron status:
   ```bash
   # Linux
   ps aux | grep cron
   
   # macOS
   launchctl list | grep cron
   ```

2. Check crontab:
   ```bash
   crontab -l
   ```

3. Test script manually:
   ```bash
   php /path/to/cron/send-telegram-notifications.php
   ```

4. Check permissions:
   ```bash
   # Must be able to read favorites folder
   ls -la cache/favorites/
   
   # Must be able to write log file
   touch /var/log/tg-notify.log
   chmod 666 /var/log/tg-notify.log
   ```

### ❌ "Link Telegram button not showing"

**Problem:** Button not visible on page

**Solution:**
1. Check `TELEGRAM_ENABLED`:
   ```bash
   grep "TELEGRAM_ENABLED" config/telegram.php
   # Should show: define('TELEGRAM_ENABLED', true);
   ```

2. Check Bot Token is set:
   ```bash
   grep "TELEGRAM_BOT_TOKEN" config/telegram.php
   # Must not be empty
   ```

3. Clear browser cache:
   - Windows/Linux: `Ctrl+Shift+Delete`
   - Mac: `Cmd+Shift+Delete`

### ❌ "Message format error in Telegram"

**Problem:** Notification displays incorrectly

**Solution:**
1. Check event timezone:
   ```bash
   sqlite3 data/calendar.db "SELECT id, name, timezone FROM events LIMIT 1;"
   ```

2. Clear cache:
   ```bash
   rm -rf cache/query_*.json
   ```

3. Run cron manually:
   ```bash
   php cron/send-telegram-notifications.php
   ```

---

## 🔍 Advanced Configuration

### Change Notification Time

```php
// config/telegram.php
define('TELEGRAM_NOTIFY_BEFORE_MINUTES', 30);  // 30 minutes instead of 60
```

**Examples:**
- `30` — notify 30 minutes before
- `60` — notify 1 hour before
- `120` — notify 2 hours before
- `1440` — notify 1 day before

### Disable/Enable System

```php
// config/telegram.php
define('TELEGRAM_ENABLED', false);  // Disable
define('TELEGRAM_ENABLED', true);   // Enable
```

### Unlink Telegram (User)

Users can disconnect from the page `/my/{slug}`:
1. Click "❌ Unlink" button
2. Confirm
3. Done!

---

## 📊 Check Database Status

```bash
# Enter SQLite
sqlite3 data/calendar.db

# See connected users
SELECT COUNT(*) as connected_users
FROM (
    SELECT DISTINCT json_extract(json_data, '$.telegram_chat_id') as chat_id
    FROM favorites_data
    WHERE json_extract(json_data, '$.telegram_chat_id') IS NOT NULL
);
```

---

## 📞 Support

- GitHub Issues: https://github.com/fordantitrust/stage-idol-calendar/issues
- Twitter: [@FordAntiTrust](https://twitter.com/FordAntiTrust)

---

## 📝 Reference

- [Telegram Bot API](https://core.telegram.org/bots/api)
- [Telegram Webhooks](https://core.telegram.org/bots/webhooks)
- [Crontab Tutorial](https://www.adminschoice.com/crontab-quick-reference)
