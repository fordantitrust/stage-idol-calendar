# 🤖 Telegram Bot Notifications Setup Guide

ระบบแจ้งเตือนผ่าน Telegram Bot สำหรับ "My Upcoming Programs" - คู่มือการตั้งค่าแบบละเอียด

### Architecture
- **No DB migration** — Telegram metadata stored in existing favorites JSON files (`telegram_chat_id`, `telegram_notified` map)
- **Idempotent** — Notifications tracked with program ID and timestamp to prevent duplicates
- **Scalable** — Hybrid design: JSON-based for <1000 users, can migrate to DB table when needed
- **Flexible** — Notification window, history retention, and duplicate prevention all configurable

---

## 📋 เนื้อหา

1. [ความต้องการ](#ความต้องการ)
2. [ขั้นตอน 1: สร้าง Telegram Bot](#ขั้นตอน-1-สร้าง-telegram-bot)
3. [ขั้นตอน 2: ตั้งค่า Webhook](#ขั้นตอน-2-ตั้งค่า-webhook)
4. [ขั้นตอน 3: ตั้งค่าผ่าน Admin UI](#ขั้นตอน-3-ตั้งค่าผ่าน-admin-ui) ⭐ วิธีใหม่
5. [ขั้นตอน 4: ตั้งค่า Cron Job](#ขั้นตอน-4-ตั้งค่า-cron-job)
6. [ขั้นตอน 5: ทดสอบระบบ](#ขั้นตอน-5-ทดสอบระบบ)
7. [Troubleshooting](#troubleshooting)

---

## ความต้องการ

- ✅ Domain ที่เป็น HTTPS (Telegram ต้องการ HTTPS เท่านั้น)
- ✅ Server สามารถรัน cron jobs ได้ (หรือ PHP CLI)
- ✅ Telegram account สำหรับสร้าง bot
- ✅ SSH/shell access เพื่อตั้งค่า cron (สำหรับขั้นตอน 4 เท่านั้น)

---

## ขั้นตอน 1: สร้าง Telegram Bot

### 1.1 เปิด BotFather บน Telegram

1. เปิด Telegram
2. ค้นหา `@BotFather` หรือกดลิงก์ https://t.me/botfather
3. ส่งคำสั่ง `/start`

### 1.2 สร้าง Bot ใหม่

```
/newbot
```

BotFather ถามข้อมูล:

**Botname?** (ชื่อสำหรับแสดง, เช่น "Idol Stage Timetable Bot")
```
Idol Stage Timetable Bot
```

**Username?** (ชื่อ @username สำหรับใช้งาน, ต้องลงท้ายด้วย "bot")
```
IdolStageBot
```

### 1.3 บันทึก Bot Token

BotFather จะส่งข้อความมา:

```
Done! Congratulations on your new bot. You will find it at t.me/IdolStageBot. 
You can now add a description, about section and profile picture for your bot, see /help for a list of commands.

Use this token to access the HTTP API:
123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghij
```

**💾 บันทึกค่านี้ไว้:**
- Bot Token: `123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghij`
- Bot Username: `IdolStageBot`

---

## ขั้นตอน 2: ตั้งค่า Webhook

### 2.1 สร้าง Secret Token

สร้าง random string (อย่างน้อย 32 ตัวอักษร) สำหรับป้องกัน webhook requests:

```bash
# Linux/Mac
openssl rand -hex 32
# Output: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0

# หรือ Python
python3 -c "import secrets; print(secrets.token_hex(32))"
```

**💾 บันทึก:** `a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6`

### 2.2 ตั้งค่า Webhook URL

**⚠️ สำคัญ:** Domain ต้องเป็น HTTPS เท่านั้น (ไม่ support HTTP)

**⚠️ สำคัญ:** ถ้าแอปอยู่ใน subdirectory ต้องรวมลงไปใน URL ด้วย:

ตัวอย่าง:
- ✅ `https://idol-calendar.example.com/api/telegram` (ติดตั้งที่ root)
- ✅ `https://yourdomain.com/idoltrack/api/telegram` (ติดตั้งใน subdirectory `/idoltrack`)
- ✅ `https://yourdomain.com/calendar/api/telegram` (ติดตั้งใน subdirectory `/calendar`)
- ❌ `http://example.com/api/telegram` (HTTP ไม่ได้)
- ❌ `https://yourdomain.com/api/telegram` (ถ้าแอปอยู่ที่ `/idoltrack` — **หายไป subdirectory!**)

---

## ✨ ขั้นตอน 3: ตั้งค่าผ่าน Admin UI (วิธีใหม่ ⭐)

**วิธีนี้ง่ายและปลอดภัยกว่า!**

### 3.1 เข้า Admin Panel

1. ไปที่ `/admin/` ของไซต์ของคุณ
2. Login ด้วยบัญชี Admin

### 3.2 เปิดแท็บ Settings

1. คลิกแท็บ **⚙️ Settings** (ถ้าคุณเป็น admin role)
2. Scroll ลงมาหาส่วน **🤖 Telegram Notifications**

### 3.3 กรอกข้อมูล Bot

```
Bot Token:           [ป้อน Token ที่ได้จาก @BotFather]
Bot Username:        [ป้อน IdolStageBot]
Webhook Secret:      [คลิก 🔄 สร้าง เพื่อสร้างอัตโนมัติ]
Notify Minutes:      [60 (หรือตัวเลขอื่นที่ต้องการ)]
Enable:              [✓ เปิดใช้งาน]
```

### 3.4 ทดสอบ Webhook

1. คลิกปุ่ม **🧪 ทดสอบ Webhook**
2. รอให้ status แสดง **✅ Webhook OK**
3. หากเกิด error ให้ตรวจสอบ:
   - Domain เป็น HTTPS หรือไม่
   - Bot Token ถูกต้องหรือไม่

### 3.5 บันทึกการตั้งค่า

1. คลิกปุ่ม **💾 บันทึก Telegram**
2. รอสักครู่ จนกว่าจะเห็น **✅ บันทึกแล้ว**
3. เสร็จ! ✨

Config จะบันทึกอยู่ที่ `config/telegram-config.json` โดยอัตโนมัติ

> ℹ️ **หมายเหตุ:** หากใช้ Admin UI วิธีนี้ ไม่จำเป็นต้องรัน script `tools/setup-telegram-webhook.php` — ปุ่ม "🧪 ทดสอบ Webhook" ใน Admin UI จะลงทะเบียน webhook URL กับ Telegram Bot API โดยอัตโนมัติแล้ว

---

## ⚙️ ขั้นตอน 4: ตั้งค่า Cron Job

### 4.1 เปิด Crontab

```bash
crontab -e
```

### 4.2 เพิ่มบรรทัด Cron

เพิ่มบรรทัดต่อไปนี้:

```bash
# Telegram notifications - run every 15 minutes
*/15 * * * * php /path/to/stage-idol-calendar/cron/send-telegram-notifications.php >> /var/log/tg-notify.log 2>&1
```

**⚠️ สำคัญ:**
- แทน `/path/to/stage-idol-calendar` ด้วย path จริงของโปรเจค
- ตัวอย่าง: `/home/user/public_html/stage-idol-calendar/cron/send-telegram-notifications.php`

### 4.3 บันทึกและปิด

```
Ctrl+O (save)
Ctrl+X (exit nano)
```

### 4.4 ตรวจสอบ Cron

```bash
# Linux
crontab -l

# Output ควรแสดง:
# */15 * * * * php /path/to/cron/send-telegram-notifications.php >> /var/log/tg-notify.log 2>&1
```

---

## ขั้นตอน 5: ทดสอบระบบ

### 5.1 ทดสอบ Webhook จาก Admin UI (ง่ายที่สุด)

1. ไปที่ Admin › Settings › 🤖 Telegram Notifications
2. คลิกปุ่ม **🧪 ทดสอบ Webhook**
3. ดูสถานะในกล่อง "สถานะ Webhook"
   - ✅ ถ้า OK แสดงว่า webhook ลงทะเบียนสำเร็จ
   - ❌ ถ้า Error ให้ตรวจสอบ domain และ token

### 5.2 ทดสอบ Link Telegram (Manual)

1. ไปที่ `/my/{slug}` ของ favorites ของคุณ
2. เห็นปุ่ม "🔔 Link Telegram"
3. เปิด Telegram
4. ค้นหาบอท `@IdolStageBot`
5. ส่งคำสั่ง:
   ```
   /start YOUR_SLUG_HERE
   ```
   (แทน `YOUR_SLUG_HERE` ด้วย slug จริงจากหน้า)

6. บอท ตอบ:
   ```
   ✅ เชื่อมต่อสำเร็จ!
   คุณจะได้รับการแจ้งเตือนก่อนเริ่มโปรแกรมของศิลปินที่ติดตาม
   ```

### 5.3 ทดสอบ Notification (Manual)

```bash
php cron/send-telegram-notifications.php
```

Output ควรเป็น:

```
[2026-04-13 14:30:25] Starting Telegram notifications
[2026-04-13 14:30:25] Completed - Notified: 2, Skipped: 0, Errors: 0
```

✅ หมายถึง:
- ส่ง 2 notifications สำเร็จ
- ไม่มี error

### 5.4 ตรวจสอบ Log File

```bash
tail -f /var/log/tg-notify.log
```

ควรเห็น:

```
[2026-04-13 14:15:00] Starting Telegram notifications
[2026-04-13 14:15:02] Completed - Notified: 1, Skipped: 0, Errors: 0
[2026-04-13 14:30:00] Starting Telegram notifications
[2026-04-13 14:30:01] Completed - Notified: 0, Skipped: 2, Errors: 0
```

---

## Troubleshooting

### ❌ "Webhook registration failed" (ใน Admin UI test)

**ปัญหา:** ปุ่ม "ทดสอบ Webhook" ล้มเหลว

**แก้ไข:**
1. ตรวจสอบ Bot Token ถูกต้องหรือไม่
2. ตรวจสอบ Domain เป็น HTTPS หรือไม่
3. ตรวจสอบ Domain สามารถเข้าถึงจากอินเทอร์เน็ตได้หรือไม่:
   ```bash
   curl -I https://yourdomain.com/api/telegram
   ```

### ❌ "Link Telegram button not showing"

**ปัญหา:** ปุ่ม Link Telegram ไม่เห็นบนหน้า `/my/{slug}`

**แก้ไข:**
1. ตรวจสอบ enabled flag ใน Admin Settings:
   - ต้องมี ✓ ที่ "เปิดใช้งาน Telegram Notifications"
2. ตรวจสอบ Bot Token ตั้งค่าแล้วไหม:
   - ต้องไม่ว่างเปล่า
3. Clear browser cache:
   - Windows/Linux: `Ctrl+Shift+Delete`
   - Mac: `Cmd+Shift+Delete`

### ❌ "No notifications sent"

**ปัญหา:** Cron job ไม่ทำงาน

**แก้ไข:**
1. ตรวจสอบ cron status:
   ```bash
   # Linux
   ps aux | grep cron
   
   # macOS
   launchctl list | grep cron
   ```

2. ตรวจสอบ crontab:
   ```bash
   crontab -l
   ```

3. รัน script ด้วยตัวเองทดสอบ:
   ```bash
   php /path/to/cron/send-telegram-notifications.php
   ```

4. ตรวจสอบ permission:
   ```bash
   # ต้องสามารถอ่าน favorites folder
   ls -la cache/favorites/
   
   # ต้องสามารถเขียน log file
   touch /var/log/tg-notify.log
   chmod 666 /var/log/tg-notify.log
   ```

### ❌ "Message format error in Telegram"

**ปัญหา:** Notification แสดงผิดรูปแบบ

**แก้ไข:**
1. ตรวจสอบ timezone ของ event:
   ```bash
   sqlite3 data/calendar.db "SELECT id, name, timezone FROM events LIMIT 1;"
   ```

2. ลบ cache:
   ```bash
   rm -rf cache/query_*.json
   ```

3. รัน cron manual:
   ```bash
   php cron/send-telegram-notifications.php
   ```

---

## 🔍 Advanced Configuration

### เปลี่ยนเวลาแจ้งเตือน

1. ไปที่ Admin › Settings › 🤖 Telegram
2. แก้ไข "Notify Minutes" (กำหนดค่า):
   - `30` — แจ้งเตือน 30 นาทีก่อน
   - `60` — แจ้งเตือน 1 ชั่วโมงก่อน
   - `120` — แจ้งเตือน 2 ชั่วโมงก่อน
   - `1440` — แจ้งเตือน 1 วันก่อน
3. คลิก **💾 บันทึก Telegram**

### ปิด/เปิดระบบ

1. ไปที่ Admin › Settings › 🤖 Telegram
2. ติ๊ก/ยกเลิกช่อง "เปิดใช้งาน Telegram Notifications"
3. คลิก **💾 บันทึก Telegram**

### Unlink Telegram (ผู้ใช้)

ผู้ใช้สามารถยกเลิกการเชื่อมต่อได้จากหน้า `/my/{slug}`:
1. กดปุ่ม "❌ ยกเลิก"
2. ยืนยัน
3. Done!

---

## 📊 ตรวจสอบสถานะจาก Database

```bash
# เข้า SQLite
sqlite3 data/calendar.db

# ดูผู้ใช้ที่เชื่อมต่อ Telegram
SELECT COUNT(*) as connected_users
FROM (
    SELECT DISTINCT json_extract(json_data, '$.telegram_chat_id') as chat_id
    FROM favorites_data
    WHERE json_extract(json_data, '$.telegram_chat_id') IS NOT NULL
);
```

---

## 📚 ไฟล์ที่เกี่ยวข้อง

- **Config:** `config/telegram-config.json` (สร้างและจัดการผ่าน Admin UI)
- **Bootstrap:** `config/telegram.php` (โหลด config จาก JSON)
- **Functions:** `functions/telegram.php`
- **API Webhook:** `api/telegram.php`
- **Cron Script:** `cron/send-telegram-notifications.php`

---

## 📞 ติดต่อสำหรับปัญหา

- GitHub Issues: https://github.com/fordantitrust/stage-idol-calendar/issues
- Twitter: [@FordAntiTrust](https://twitter.com/FordAntiTrust)

---

## 📝 อ้างอิง

- [Telegram Bot API](https://core.telegram.org/bots/api)
- [Telegram Webhooks](https://core.telegram.org/bots/webhooks)
- [Crontab Tutorial](https://www.adminschoice.com/crontab-quick-reference)
