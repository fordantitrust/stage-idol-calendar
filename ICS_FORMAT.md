# ICS Format Guide - Idol Stage Calendar

This file describes the ICS (iCalendar) file format supported by this project for importing and exporting events.

---

## 📋 Table of Contents

1. [ICS File Structure](#ics-file-structure)
2. [Field Specifications](#field-specifications)
3. [Required vs Optional Fields](#required-vs-optional-fields)
4. [Date/Time Format](#datetime-format)
5. [Program Types](#program-types)
6. [Stream Platform URLs](#stream-platform-urls)
7. [Examples](#examples)
8. [Character Escaping](#character-escaping)
9. [Import/Export Guide](#importexport-guide)

---

## ICS File Structure

### Basic Template

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Idol Stage//NONSGML v1.0//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:Event Name
X-WR-TIMEZONE:Asia/Bangkok
X-WR-CALDESC:Description

BEGIN:VEVENT
[Properties]
END:VEVENT

BEGIN:VEVENT
[Properties]
END:VEVENT

END:VCALENDAR
```

---

## Field Specifications

### VCALENDAR Header (Required)

| Property | Value | Description |
|----------|-------|-------------|
| `BEGIN:VCALENDAR` | - | ICS file start marker |
| `VERSION` | `2.0` | iCalendar specification version (must be 2.0) |
| `PRODID` | `-//Idol Stage//NONSGML v1.0//EN` | Product identifier |
| `CALSCALE` | `GREGORIAN` | Calendar system (always GREGORIAN) |
| `METHOD` | `PUBLISH` | Method for calendar operations |
| `X-WR-CALNAME` | String | Display name of calendar |
| `X-WR-TIMEZONE` | `Asia/Bangkok` | Default timezone |
| `X-WR-CALDESC` | String | Description of calendar |
| `END:VCALENDAR` | - | ICS file end marker |

---

## VEVENT Properties

### Timing Properties

| Property | Format | Example | Description |
|----------|--------|---------|-------------|
| `UID` | String | `event-001@stageidol.local` | Unique identifier (must be unique per event) |
| `DTSTAMP` | `YYYYMMDDTHHmmssZ` | `20260305T100000Z` | Created/modified timestamp (UTC) |
| `DTSTART` | `YYYYMMDDTHHmmssZ` or `YYYYMMDD` | `20260308T140000Z` | Start time (UTC with Z suffix) |
| `DTEND` | `YYYYMMDDTHHmmssZ` or `YYYYMMDD` | `20260308T150000Z` | End time (UTC with Z suffix) |

### Event Details Properties

| Property | Format | Example | Description |
|----------|--------|---------|-------------|
| `SUMMARY` | String | `Artist A - Live Performance` | Event title (displayed in UI) |
| `DESCRIPTION` | String | `Live concert by ศิลปิน A` | Event description (multiline supported) |
| `LOCATION` | String | `Main Stage`, `Fan Meeting Hall` | Venue/location name |

### Organizer Properties

| Property | Format | Example | Description |
|----------|--------|---------|-------------|
| `ORGANIZER` | `CN="Name":mailto:email` | `ORGANIZER;CN="Event Name":mailto:event@example.com` | Event organizer info |

### Category Properties

| Property | Format | Example | Description |
|----------|--------|---------|-------------|
| `CATEGORIES` | Comma-separated | `Artist A,Artist B,Artist C` | Artists/groups (multiple allowed) |

### Program Type (Custom)

| Property | Format | Example | Description |
|----------|--------|---------|-------------|
| `X-PROGRAM-TYPE` | String | `Live Stream`, `Meet & Greet`, `Panel` | Program type/category (single value) |

### Stream Support

| Property | Format | Example | Description |
|----------|--------|---------|-------------|
| `URL` | Full URL | `https://www.instagram.com/live/username/` | Live stream link (IG, YouTube, X, etc.) |

### Status Properties

| Property | Value | Example | Description |
|----------|-------|---------|-------------|
| `STATUS` | String | `CONFIRMED` | Event status (CONFIRMED/TENTATIVE/CANCELLED) |
| `SEQUENCE` | Integer | `0` | Revision number (increment on updates) |

### Alarm/Reminder

| Property | Value | Example | Description |
|----------|-------|---------|-------------|
| `BEGIN:VALARM` | - | - | Alarm/reminder block start |
| `TRIGGER` | Duration | `-PT15M` | Trigger time (15 minutes before event) |
| `ACTION` | String | `DISPLAY` | Alarm action (always DISPLAY) |
| `DESCRIPTION` | String | `Reminder` | Alarm description |
| `END:VALARM` | - | - | Alarm block end |

---

## Required vs Optional Fields

### ✅ Required Fields

- `BEGIN:VEVENT`
- `END:VEVENT`
- `UID` (unique per event)
- `SUMMARY` (event title)
- `DTSTART` (event start time)

### ⚠️ Recommended Fields

- `DTEND` (event end time) - if not provided, defaults to DTSTART
- `LOCATION` (venue name)
- `CATEGORIES` (artist names) - if empty, will try ORGANIZER
- `DESCRIPTION` (event details)
- `URL` (stream link for live events)
- `X-PROGRAM-TYPE` (program type/category)

### ✓ Optional Fields

- `DTSTAMP` (auto-generated if missing)
- `STATUS` (defaults to CONFIRMED)
- `SEQUENCE` (defaults to 0)
- `BEGIN:VALARM` block (reminder)

---

## DateTime Format

### Format Specification

```
YYYYMMDDTHHmmssZ

YYYY = 4-digit year (e.g., 2026)
MM   = 2-digit month (01-12)
DD   = 2-digit day (01-31)
T    = Literal T separator
HH   = 2-digit hour (00-23, 24-hour format)
mm   = 2-digit minute (00-59)
ss   = 2-digit second (00-59)
Z    = UTC timezone indicator (must be present)
```

### Examples

```
20260308T140000Z     = 2026-03-08 14:00:00 UTC
20260308T143000Z     = 2026-03-08 14:30:00 UTC
20260308T150000Z     = 2026-03-08 15:00:00 UTC
20260308             = 2026-03-08 (all-day event, no time)
```

### Timezone Notes

- ⚠️ **Always use UTC time (with Z suffix)** - the parser will auto-convert to Asia/Bangkok
- Local time (without Z) is assumed to be Asia/Bangkok already
- 1 hour ahead of UTC = add 1 hour to Bangkok time

### Conversion Example

```
Bangkok Time:     14:00 (2:00 PM)
UTC Time:         07:00 (7:00 AM)  [Bangkok is UTC+7]
ICS Format:       20260308T070000Z
```

---

## Program Types

### Recommended Program Type Values

```
Live Stream         (Live online broadcast)
Meet & Greet        (Meet & Greet with artists)
Q&A Session         (Question & Answer session)
Panel Discussion    (Panel Discussion)
Workshop            (Workshop or masterclass)
Cover Performance   (Cover/Tribute performance)
VR Experience       (VR/Interactive experience)
Giveaway Draw       (Giveaway/Prize drawing)
Photo Session       (Photo opportunity)
Signing             (Autograph/Merchandise Signing)
Karaoke Battle      (Karaoke Competition)
Dance Performance   (Dance/Choreography)
Concert             (Full Concert)
Opening Ceremony    (Opening Event)
Closing Ceremony    (Closing Event)
Special Guest       (Guest Appearance)
Talk Show           (Talk/Interview)
```

### Custom Types

You can use any string value, not limited to the list above.

---

## Stream Platform URLs

### Instagram Live

```
https://www.instagram.com/live/{username}/
https://www.instagram.com/{username}/
```

### YouTube Live

```
https://www.youtube.com/live/{channel_id}
https://youtu.be/{video_id}
https://www.youtube.com/watch?v={video_id}
```

### X (Twitter) Spaces

```
https://twitter.com/i/spaces/{space_id}
https://x.com/i/spaces/{space_id}
```

### TikTok Live

```
https://www.tiktok.com/@{username}/live
https://www.tiktok.com/{user_id}/video/{video_id}
```

### Facebook Live

```
https://www.facebook.com/{page}/videos/{video_id}/
https://www.facebook.com/watch/?v={video_id}
```

### Twitch

```
https://www.twitch.tv/{channel_name}
https://www.twitch.tv/{channel_name}/live
```

### Line Live

```
https://live.line.me/{channel_id}
https://lin.ee/live/{channel_slug}
```

### Zoom/Google Meet (Generic)

```
https://zoom.us/j/{meeting_id}
https://meet.google.com/{meeting_code}
https://meet.jit.si/{room_name}
```

---

## Examples

### Example 1: Live Stream Event

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Idol Stage//NONSGML v1.0//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:Idol Stage Events
X-WR-TIMEZONE:Asia/Bangkok
X-WR-CALDESC:Live concerts and meets

BEGIN:VEVENT
UID:event-20260308-001@stageidol.local
DTSTAMP:20260305T100000Z
DTSTART:20260308T070000Z
DTEND:20260308T080000Z
SUMMARY:Artist A - Instagram Live Performance
DESCRIPTION:Live concert by Artist A on Instagram Live platform
LOCATION:Kaze Stage
ORGANIZER;CN="Idol Stage March 2026":mailto:info@idolstage.local
CATEGORIES:Artist A,J-Pop
URL:https://www.instagram.com/live/artistA_official/
X-PROGRAM-TYPE:Live Stream
STATUS:CONFIRMED
SEQUENCE:0
BEGIN:VALARM
TRIGGER:-PT15M
ACTION:DISPLAY
DESCRIPTION:Reminder
END:VALARM
END:VEVENT

END:VCALENDAR
```

### Example 2: Meet & Greet Event

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Idol Stage//NONSGML v1.0//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:Idol Stage Events
X-WR-TIMEZONE:Asia/Bangkok

BEGIN:VEVENT
UID:event-20260308-002@stageidol.local
DTSTAMP:20260305T100000Z
DTSTART:20260308T080000Z
DTEND:20260308T090000Z
SUMMARY:Artist B - Meet & Greet
DESCRIPTION:Photo session, autograph signing, and Q&A with Artist B
LOCATION:Fan Meeting Hall
ORGANIZER;CN="Idol Stage March 2026":mailto:info@idolstage.local
CATEGORIES:Artist B
X-PROGRAM-TYPE:Meet & Greet
STATUS:CONFIRMED
SEQUENCE:0
BEGIN:VALARM
TRIGGER:-PT15M
ACTION:DISPLAY
DESCRIPTION:Reminder - Get ready!
END:VALARM
END:VEVENT

END:VCALENDAR
```

### Example 3: Multiple Events in One ICS File

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Idol Stage//NONSGML v1.0//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:Idol Stage Convention
X-WR-TIMEZONE:Asia/Bangkok
X-WR-CALDESC:Complete schedule for convention

BEGIN:VEVENT
UID:event-20260308-001@stageidol.local
DTSTAMP:20260305T100000Z
DTSTART:20260308T000000Z
DTEND:20260308T010000Z
SUMMARY:Opening Ceremony
LOCATION:Main Hall
ORGANIZER;CN="Convention Organizers":mailto:convention@example.com
CATEGORIES:Ceremony
X-PROGRAM-TYPE:Opening Ceremony
STATUS:CONFIRMED
SEQUENCE:0
END:VEVENT

BEGIN:VEVENT
UID:event-20260308-002@stageidol.local
DTSTAMP:20260305T100000Z
DTSTART:20260308T070000Z
DTEND:20260308T080000Z
SUMMARY:Artist A - Live Performance
LOCATION:Stage A
ORGANIZER;CN="Convention Organizers":mailto:convention@example.com
CATEGORIES:Artist A
URL:https://www.youtube.com/live/channelA
X-PROGRAM-TYPE:Live Stream
STATUS:CONFIRMED
SEQUENCE:0
END:VEVENT

BEGIN:VEVENT
UID:event-20260308-003@stageidol.local
DTSTAMP:20260305T100000Z
DTSTART:20260308T080000Z
DTEND:20260308T090000Z
SUMMARY:Artist B - Panel Discussion
LOCATION:Meeting Room 1
ORGANIZER;CN="Convention Organizers":mailto:convention@example.com
CATEGORIES:Artist B,Moderator C
X-PROGRAM-TYPE:Panel Discussion
STATUS:CONFIRMED
SEQUENCE:0
END:VEVENT

BEGIN:VEVENT
UID:event-20260308-004@stageidol.local
DTSTAMP:20260305T100000Z
DTSTART:20260308T090000Z
DTEND:20260308T100000Z
SUMMARY:Meet & Greet - Artist A
LOCATION:Fan Meeting Area
ORGANIZER;CN="Convention Organizers":mailto:convention@example.com
CATEGORIES:Artist A
X-PROGRAM-TYPE:Meet & Greet
STATUS:CONFIRMED
SEQUENCE:0
END:VEVENT

BEGIN:VEVENT
UID:event-20260308-005@stageidol.local
DTSTAMP:20260305T100000Z
DTSTART:20260308T160000Z
DTEND:20260308T170000Z
SUMMARY:Closing Ceremony
LOCATION:Main Hall
ORGANIZER;CN="Convention Organizers":mailto:convention@example.com
CATEGORIES:Ceremony
X-PROGRAM-TYPE:Closing Ceremony
STATUS:CONFIRMED
SEQUENCE:0
END:VEVENT

END:VCALENDAR
```

---

## Character Escaping

### ICS Special Characters

The following characters must be escaped in ICS property values:

| Character | Escaped Form | Used In | Example |
|-----------|--------------|---------|---------|
| `\` (backslash) | `\\` | All fields | `Path\\to\\file` → `Path\\\\to\\\\file` |
| `,` (comma) | `\,` | CATEGORIES, DESCRIPTION, etc. | `A,B,C` → `A\,B\,C` |
| `;` (semicolon) | `\;` | DESCRIPTION, etc. | `A;B` → `A\;B` |
| `\n` (newline) | `\\n` | DESCRIPTION | Multiline text |
| `\r` (carriage return) | Remove | All fields | Not needed in CRLF |

### Auto-Escaping

The project **automatically escapes** these characters when:
- **Parsing ICS**: `decodeIcsValue()` in IcsParser.php
- **Exporting ICS**: `escapeIcsValue()` in export.php

You don't need to manually escape when creating ICS files - the parser handles it.

### Unescaped CATEGORIES Delimiter

**Important**: CATEGORIES field uses **unescaped comma** as delimiter:
```
CATEGORIES:Artist A,Artist B,Artist C
                  ↑ unescaped comma
```

If you need comma inside category name, escape it:
```
CATEGORIES:Artist A\, Jr.,Artist B
```

---

## Import/Export Guide

### Manual Import Steps

1. **Create ICS File**
   - Use the templates above
   - Save as `.ics` extension
   - Make sure it's UTF-8 encoded

2. **Upload via Admin Panel**
   - Go to `Admin › Programs › Upload ICS`
   - Click "Choose File" and select your `.ics`
   - Preview the events to be imported

3. **Review & Confirm**
   - Check title, location, and time
   - Select events to import (can skip some)
   - Choose action (Insert/Update/Skip) per event

4. **Complete Import**
   - System creates/updates database records
   - Files saved to `ics/` folder as `upload_YYYYMMDD_HHMMSS.ics`
   - ICS parsing creates `stream_url` from `URL:` property

### Automatic Export Steps

1. **Select Events**
   - Apply filters (artist, venue, type)
   - All filtered events will be exported

2. **Click "Export to Calendar"**
   - Browser downloads `.ics` file
   - Format: `stage-idol-calendar-YYYY-MM-DD.ics`
   - Can open in Google Calendar, Apple Calendar, Outlook, etc.

3. **Subscribe to Feed**
   - Click "Subscribe" button
   - Copy `webcal://` or `https://` link
   - Paste into calendar app
   - Calendar will auto-sync when events change

---

## Database Schema

### Programs Table Columns (from ICS)

| DB Column | ICS Property | Notes |
|-----------|--------------|-------|
| `uid` | `UID` | Unique identifier |
| `title` | `SUMMARY` | Event title |
| `start` | `DTSTART` | Start time (ISO 8601, Asia/Bangkok) |
| `end` | `DTEND` | End time (ISO 8601) |
| `location` | `LOCATION` | Venue name |
| `organizer` | `ORGANIZER` CN part | Extracted from CN parameter |
| `description` | `DESCRIPTION` | Event details |
| `categories` | `CATEGORIES` | Artist names (comma-separated) |
| `program_type` | `X-PROGRAM-TYPE` | Program type |
| `stream_url` | `URL` | Stream link |
| `event_id` | N/A | Convention/Event ID (foreign key) |

---

## Validation Rules

### Parser Validation

✅ **Event is imported if:**
- Has `SUMMARY` (title)
- Has `DTSTART` (start time)

❌ **Event is skipped if:**
- Missing `SUMMARY`
- Missing `DTSTART`
- Duplicate `UID` (already exists in DB)

### Field Validation

| Field | Rule | Action |
|-------|------|--------|
| `DTSTART`, `DTEND` | Invalid date format | Error message, event skipped |
| `UID` | Already exists | Detected as duplicate, ask user to update/skip |
| `CATEGORIES` | Empty | Uses `ORGANIZER` if available |
| `DTEND` | Missing | Defaults to `DTSTART` |
| `LOCATION` | Any | Flex venue mode or autocomplete |

---

## Troubleshooting

### Common Issues

#### ❌ "Event not found or parsing error"

**Cause**: Missing SUMMARY or DTSTART

**Solution**: Ensure every VEVENT has:
```ics
SUMMARY:Event Title
DTSTART:20260308T140000Z
```

#### ❌ "Timezone conversion issue"

**Cause**: Not using Z suffix in datetime

**Solution**: Always use Z suffix for UTC:
```ics
DTSTART:20260308T070000Z    ✅ Correct
DTSTART:20260308T140000     ❌ Wrong (assumes Bangkok)
```

#### ❌ "Stream URL not appearing"

**Cause**: Using wrong property name (not `URL:`)

**Solution**: Use `URL:` property exactly:
```ics
URL:https://www.instagram.com/live/username/   ✅
STREAM-URL:https://...                          ❌
```

#### ❌ "Program type not showing"

**Cause**: Using `CATEGORIES` instead of `X-PROGRAM-TYPE`

**Solution**: Separate fields:
```ics
CATEGORIES:Artist A,Artist B      (Artist names)
X-PROGRAM-TYPE:Live Stream         (Program type)
```

---

## Related Files

- [IcsParser.php](/IcsParser.php) - ICS parsing logic
- [export.php](/export.php) - ICS export/download
- [admin/api.php](/admin/api.php) - ICS upload endpoint
- [feed.php](/feed.php) - Live subscription feed
- [CHANGELOG.md](/CHANGELOG.md) - Version history and schema changes

---

**Last Updated**: 2026-03-05  
**Project**: Idol Stage Timetable v2.7.4
