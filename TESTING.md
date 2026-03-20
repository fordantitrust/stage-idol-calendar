# 🧪 Testing Guide - Stage Idol Calendar

Complete test cases for all features and security aspects.

---

## 📋 Table of Contents

1. [Setup Requirements](#setup-requirements)
2. [Credits Management System](#credits-management-system)
3. [Cache System](#cache-system)
4. [Security Testing](#security-testing)
5. [Admin Authentication](#admin-authentication)
6. [Programs Management](#programs-management)
7. [Request System](#request-system)
8. [Bulk Operations](#bulk-operations)
9. [Frontend Features](#frontend-features)
10. [Performance Testing](#performance-testing)
11. [Edge Cases & Error Handling](#edge-cases--error-handling)
12. [User Management & Roles](#user-management--roles)
13. [Artist Reuse System](#artist-reuse-system)

---

## Setup Requirements

### Test Environment

```bash
# Start PHP server
php -S localhost:8000

# Check PHP version
php -v  # Should be 8.1+

# Verify SQLite extension
php -m | grep -i sqlite  # Should show pdo_sqlite

# Check database exists
ls -la data/calendar.db
```

### Test Data Preparation

#### Option A: Setup Wizard (Recommended)

Open `http://localhost:8000/setup.php` and follow the 6-step wizard.

#### Option B: Manual CLI

See **[README.md — Option B: Manual CLI](README.md#option-b-manual-cli)** for the complete migration sequence.

```bash
# After running all migrations, clear cache:
rm -f cache/*.json
```

### Admin Credentials

Default credentials (set in `config/admin.php`):
- Username: `fordantitrust`
- Password: Generate and set hash

---

## 1. Credits Management System

### 1.1 List Credits

**Test Case**: Load credits list with pagination

**Steps**:
1. Login to admin panel
2. Click "Credits" tab
3. Verify credits table loads

**Expected Result**:
- ✅ Table shows: ID, Title, Link, Description, Order, Actions
- ✅ Pagination controls visible (if > 20 credits)
- ✅ Master checkbox in header
- ✅ Per-page selector (20/50/100)

**Test Data**:
```sql
-- Run in SQLite to add test data
INSERT INTO credits (title, link, description, display_order) VALUES
('Test Credit 1', 'https://example.com', 'Description 1', 0),
('Test Credit 2', 'https://example.com/2', 'Description 2', 1),
('Test Credit 3', '', 'No link credit', 2);
```

---

### 1.2 Create Credit

**Test Case 1.2.1**: Create valid credit

**Steps**:
1. Click "+ Add Credit"
2. Fill form:
   - Title: "Test Credit"
   - Link: "https://example.com"
   - Description: "Test description"
   - Display Order: 0
3. Click "Save"

**Expected Result**:
- ✅ Success toast message
- ✅ Credit appears in list
- ✅ Cache invalidated (check `cache/credits.json` deleted)
- ✅ Modal closes automatically

---

**Test Case 1.2.2**: Create credit with required field only

**Steps**:
1. Click "+ Add Credit"
2. Fill only Title: "Minimal Credit"
3. Leave Link, Description empty
4. Click "Save"

**Expected Result**:
- ✅ Credit created successfully
- ✅ Link shows "-" in list
- ✅ Description shows "-" in list

---

**Test Case 1.2.3**: Validation - Empty title

**Steps**:
1. Click "+ Add Credit"
2. Leave Title empty
3. Click "Save"

**Expected Result**:
- ❌ HTML5 validation error: "Please fill out this field"
- ❌ Form not submitted

---

**Test Case 1.2.4**: Validation - Title too long

**Steps**:
1. Fill Title with 201 characters
2. Click "Save"

**Expected Result**:
- ❌ Browser limits input to 200 characters (maxlength attribute)

---

**Test Case 1.2.5**: Validation - Invalid URL

**Steps**:
1. Fill Title: "Test"
2. Fill Link: "not-a-valid-url"
3. Click "Save"

**Expected Result**:
- ❌ HTML5 validation error: "Please enter a URL"

---

### 1.3 Update Credit

**Test Case 1.3.1**: Edit existing credit

**Steps**:
1. Click "Edit" button on any credit
2. Verify form pre-filled with current values
3. Change Title to "Updated Title"
4. Click "Save"

**Expected Result**:
- ✅ Success toast message
- ✅ Title updated in list
- ✅ Cache invalidated
- ✅ Modal closes

---

**Test Case 1.3.2**: Cancel edit (with changes)

**Steps**:
1. Click "Edit" button
2. Change any field
3. Click "Cancel"

**Expected Result**:
- ✅ Confirmation dialog: "You have unsaved changes"
- ✅ If confirm: modal closes, no changes saved
- ✅ If cancel: stays in modal

---

### 1.4 Delete Credit

**Test Case 1.4.1**: Delete single credit

**Steps**:
1. Click "Delete" button on credit
2. Verify confirmation modal shows credit title
3. Click "Delete"

**Expected Result**:
- ✅ Confirmation modal appears
- ✅ Credit title displayed correctly
- ✅ After confirm: credit removed from list
- ✅ Success toast message
- ✅ Cache invalidated

---

**Test Case 1.4.2**: Cancel delete

**Steps**:
1. Click "Delete" button
2. Click "Cancel"

**Expected Result**:
- ✅ Modal closes
- ✅ Credit still in list (not deleted)

---

### 1.5 Bulk Delete Credits

**Test Case 1.5.1**: Select and bulk delete multiple credits

**Steps**:
1. Check 3-5 credit checkboxes
2. Verify bulk actions bar appears with count
3. Click "🗑️ Delete Selected"
4. Verify count in confirmation modal
5. Click "Delete All"

**Expected Result**:
- ✅ Bulk actions bar shows correct count
- ✅ Confirmation modal shows correct count
- ✅ All selected credits deleted
- ✅ Success message with count
- ✅ Page refreshes, credits removed
- ✅ Cache invalidated

---

**Test Case 1.5.2**: Master checkbox - Select all

**Steps**:
1. Click master checkbox in header
2. Verify all credits selected
3. Uncheck master checkbox
4. Verify all credits deselected

**Expected Result**:
- ✅ Master checkbox selects/deselects all
- ✅ Bulk actions bar shows/hides correctly
- ✅ Count updates accurately

---

**Test Case 1.5.3**: Indeterminate state

**Steps**:
1. Check 2-3 credits (not all)
2. Verify master checkbox state

**Expected Result**:
- ✅ Master checkbox shows indeterminate state (dash/minus icon)
- ✅ Not fully checked, not fully unchecked

---

**Test Case 1.5.4**: Bulk delete limit (100 max)

**Steps**:
1. Create 101+ credits
2. Select all (>100)
3. Attempt bulk delete

**Expected Result**:
- ❌ Error message: "Maximum 100 credits per request"
- ❌ Operation not performed

---

### 1.6 Search Credits

**Test Case 1.6.1**: Search by title

**Steps**:
1. Enter "Test" in search box
2. Wait 300ms (debounce)

**Expected Result**:
- ✅ Results filtered to credits with "Test" in title
- ✅ Pagination resets to page 1
- ✅ Clear search button (✕) appears

---

**Test Case 1.6.2**: Search by description

**Steps**:
1. Enter text that appears in description
2. Wait for results

**Expected Result**:
- ✅ Credits with matching description shown

---

**Test Case 1.6.3**: Search with no results

**Steps**:
1. Enter "XYZ123NoMatch"
2. Wait for results

**Expected Result**:
- ✅ "No credits found" message shown
- ✅ Empty table

---

**Test Case 1.6.4**: Clear search

**Steps**:
1. Enter search term
2. Click ✕ button

**Expected Result**:
- ✅ Search box cleared
- ✅ Full list restored
- ✅ Page resets to 1

---

**Test Case 1.6.5**: Search debounce

**Steps**:
1. Type quickly: "T-e-s-t" (one character at a time)
2. Observe network requests

**Expected Result**:
- ✅ Only ONE request sent after 300ms delay
- ✅ No request per keystroke

---

### 1.7 Sort Credits

**Test Case 1.7.1**: Sort by ID

**Steps**:
1. Click "ID" column header
2. Click again

**Expected Result**:
- ✅ First click: ascending order (1, 2, 3...)
- ✅ Second click: descending order (3, 2, 1...)
- ✅ Sort icon shows direction

---

**Test Case 1.7.2**: Sort by Title

**Steps**:
1. Click "Title" column header
2. Verify alphabetical order

**Expected Result**:
- ✅ Sorted A-Z (ascending)
- ✅ Sorted Z-A (descending)

---

**Test Case 1.7.3**: Sort by Display Order

**Steps**:
1. Click "Order" column header
2. Verify numeric order

**Expected Result**:
- ✅ Sorted by display_order value
- ✅ Lower numbers first (ascending)

---

### 1.8 Pagination

**Test Case 1.8.1**: Navigate pages

**Steps**:
1. Create 30+ credits
2. Click "Next" button
3. Click "Previous" button

**Expected Result**:
- ✅ Page 2 shows items 21-40
- ✅ Page info shows "Page 2 / 2"
- ✅ Previous button goes back to page 1

---

**Test Case 1.8.2**: Change per-page limit

**Steps**:
1. Select "50 / page"
2. Verify results

**Expected Result**:
- ✅ Shows 50 items per page
- ✅ Pagination updates
- ✅ Resets to page 1

---

### 1.9 Public Display (credits.php)

**Test Case 1.9.1**: Load credits page

**Steps**:
1. Visit `http://localhost:8000/credits.php`
2. Verify credits displayed

**Expected Result**:
- ✅ All credits shown in order (display_order ASC)
- ✅ Title displayed
- ✅ Link clickable (if exists)
- ✅ Description shown (if exists)
- ✅ Opens in new tab (target="_blank")

---

**Test Case 1.9.2**: Empty state

**Steps**:
1. Delete all credits
2. Visit credits.php

**Expected Result**:
- ✅ "No credits available" message
- ✅ No errors

---

**Test Case 1.9.3**: XSS protection

**Steps**:
1. Create credit with title: `<script>alert('XSS')</script>`
2. Visit credits.php

**Expected Result**:
- ✅ Script tag displayed as text (escaped)
- ❌ Script NOT executed

---

---

## 2. Cache System

### 2.1 Data Version Cache

**Test Case 2.1.1**: Cache creation

**Steps**:
1. Delete `cache/data_version.json`
2. Visit index.php
3. Check file created

**Expected Result**:
- ✅ `cache/data_version.json` created
- ✅ Contains timestamp and version
- ✅ Version displayed in footer

---

**Test Case 2.1.2**: Cache TTL (10 minutes)

**Steps**:
1. Create cache file
2. Check timestamp
3. Wait 11 minutes
4. Reload page

**Expected Result**:
- ✅ Cache expired
- ✅ New cache file generated
- ✅ New timestamp

---

**Test Case 2.1.3**: Cache hit (within TTL)

**Steps**:
1. Load page (creates cache)
2. Reload page within 10 minutes
3. Check database query count

**Expected Result**:
- ✅ No database query for version
- ✅ Served from cache
- ✅ Faster response time

---

### 2.2 Credits Cache

**Test Case 2.2.1**: Cache creation on first load

**Steps**:
1. Delete `cache/credits.json`
2. Visit credits.php
3. Check file created

**Expected Result**:
- ✅ `cache/credits.json` created
- ✅ Contains credits data and timestamp

---

**Test Case 2.2.2**: Cache TTL (1 hour)

**Steps**:
1. Note cache timestamp
2. Wait 61 minutes
3. Reload credits.php

**Expected Result**:
- ✅ Cache expired
- ✅ Fresh data fetched from database
- ✅ New cache file created

---

**Test Case 2.2.3**: Auto-invalidation on create

**Steps**:
1. Verify cache exists
2. Create new credit via admin
3. Check cache file

**Expected Result**:
- ✅ Cache file deleted
- ✅ Next credits.php load creates new cache

---

**Test Case 2.2.4**: Auto-invalidation on update

**Steps**:
1. Verify cache exists
2. Update credit via admin
3. Check cache file

**Expected Result**:
- ✅ Cache file deleted

---

**Test Case 2.2.5**: Auto-invalidation on delete

**Steps**:
1. Verify cache exists
2. Delete credit via admin
3. Check cache file

**Expected Result**:
- ✅ Cache file deleted

---

**Test Case 2.2.6**: Cache fallback on error

**Steps**:
1. Rename/corrupt calendar.db
2. Visit credits.php

**Expected Result**:
- ✅ Empty array returned
- ✅ "No credits available" message
- ❌ No PHP errors displayed

---

---

## 3. Security Testing

### 3.1 XSS Protection

**Test Case 3.1.1**: Input sanitization - GET parameters

**Steps**:
1. Visit: `index.php?search=<script>alert('XSS')</script>`
2. Check page source

**Expected Result**:
- ✅ Script tag NOT executed
- ✅ Value sanitized by `get_sanitized_param()`
- ❌ No alert popup

---

**Test Case 3.1.2**: Output escaping - Credits title

**Steps**:
1. Create credit: Title = `"><script>alert('XSS')</script>`
2. View in admin table
3. View on credits.php

**Expected Result**:
- ✅ Script displayed as text
- ✅ Escaped with `htmlspecialchars()`
- ❌ Script NOT executed

---

**Test Case 3.1.3**: JSON injection - Request modal

**Steps**:
1. Create event with title: `Test"<img src=x onerror=alert(1)>`
2. Click edit button
3. Check modal data

**Expected Result**:
- ✅ Modal opens without error
- ✅ Data properly escaped
- ❌ No script execution

---

**Test Case 3.1.4**: Null byte injection

**Steps**:
1. Send POST request with null byte: `title=Test%00Admin`
2. Check stored data

**Expected Result**:
- ✅ Null byte removed by `sanitize_string()`
- ✅ Clean data stored

---

### 3.2 SQL Injection Protection

**Test Case 3.2.1**: Search parameter injection

**Steps**:
1. Search: `' OR '1'='1`
2. Check results

**Expected Result**:
- ✅ Prepared statements prevent injection
- ✅ Treated as literal string
- ❌ No unauthorized data access

---

**Test Case 3.2.2**: Bulk delete ID injection

**Steps**:
1. Send request: `{"ids": ["1 OR 1=1"]}`
2. Check operation

**Expected Result**:
- ✅ IDs sanitized with `intval()`
- ✅ Only valid integer IDs processed

---

### 3.3 CSRF Protection

**Test Case 3.3.1**: Missing CSRF token

**Steps**:
1. Send POST to `admin/api.php?action=credits_create` without token
2. Check response

**Expected Result**:
- ❌ HTTP 403 Forbidden
- ❌ Error: "CSRF token validation failed"

---

**Test Case 3.3.2**: Invalid CSRF token

**Steps**:
1. Send POST with wrong token: `X-CSRF-Token: invalid`
2. Check response

**Expected Result**:
- ❌ HTTP 403 Forbidden
- ❌ Operation not performed

---

**Test Case 3.3.3**: Valid CSRF token

**Steps**:
1. Get token from page
2. Send POST with correct token
3. Check response

**Expected Result**:
- ✅ Operation successful
- ✅ HTTP 200 OK

---

### 3.4 Session Security

**Test Case 3.4.1**: Session timeout

**Steps**:
1. Login to admin
2. Wait 2 hours + 1 minute
3. Perform any action

**Expected Result**:
- ❌ Session expired
- ✅ Redirected to login page
- ✅ Must login again

---

**Test Case 3.4.2**: Session fixation prevention

**Steps**:
1. Get session ID before login
2. Login successfully
3. Check session ID after login

**Expected Result**:
- ✅ Session ID changed after login
- ✅ Old session ID invalid

---

**Test Case 3.4.3**: Concurrent sessions

**Steps**:
1. Login in browser A
2. Login in browser B (same user)
3. Try to use both

**Expected Result**:
- ✅ Both sessions valid
- ✅ Independent session IDs

---

**Test Case 3.4.4**: Session cookie security

**Steps**:
1. Login to admin
2. Inspect cookies in browser DevTools

**Expected Result**:
- ✅ `httponly` flag set
- ✅ `SameSite=Lax` attribute set
- ✅ Session cookie expires when browser closes

---

### 3.5 Rate Limiting

**Test Case 3.5.1**: Request submission rate limit

**Steps**:
1. Submit 10 requests rapidly from same IP
2. Try 11th request

**Expected Result**:
- ✅ First 10 succeed
- ❌ 11th returns HTTP 429
- ❌ Error: "Too many requests"

---

**Test Case 3.6.8**: Rate limit window reset

**Steps**:
1. Hit rate limit
2. Wait 1 hour + 1 minute
3. Submit new request

**Expected Result**:
- ✅ Request allowed
- ✅ Counter reset

---

**Test Case 3.6.8**: Different IPs independent limits

**Steps**:
1. Submit 10 requests from IP A
2. Submit 1 request from IP B

**Expected Result**:
- ✅ IP B's request succeeds
- ✅ Limits independent per IP

---

### 3.6 Input Validation

**Test Case 3.6.8**: Max length - Title

**Steps**:
1. Submit credit with 201-character title

**Expected Result**:
- ❌ HTML maxlength prevents input
- ❌ Or server truncates to 200 chars

---

**Test Case 3.6.8**: Max length - Description

**Steps**:
1. Submit 1001-character description

**Expected Result**:
- ❌ HTML maxlength prevents input
- ❌ Or server truncates to 1000 chars

---

**Test Case 3.6.8**: Array size limit

**Steps**:
1. Submit bulk delete with 101 IDs

**Expected Result**:
- ❌ Error: "Maximum 100 credits per request"

---

---

## 4. Admin Authentication

### 4.1 Login

**Test Case 4.1.1**: Valid credentials

**Steps**:
1. Visit `/admin/login.php`
2. Enter correct username and password
3. Click "Login"

**Expected Result**:
- ✅ Redirected to `/admin/`
- ✅ Session created
- ✅ Username displayed in header

---

**Test Case 4.1.2**: Invalid username

**Steps**:
1. Enter wrong username
2. Enter correct password
3. Submit

**Expected Result**:
- ❌ Error message
- ❌ Not logged in

---

**Test Case 4.1.3**: Invalid password

**Steps**:
1. Enter correct username
2. Enter wrong password
3. Submit

**Expected Result**:
- ❌ Error message
- ❌ Not logged in

---

**Test Case 4.1.4**: Timing attack resistance

**Steps**:
1. Measure response time for invalid username
2. Measure response time for valid username + invalid password

**Expected Result**:
- ✅ Response times similar (constant-time comparison)
- ✅ No timing leak

---

**Test Case 4.1.5**: Empty fields

**Steps**:
1. Leave username/password empty
2. Submit

**Expected Result**:
- ❌ HTML5 validation error
- ❌ Form not submitted

---

### 4.2 Logout

**Test Case 4.2.1**: Normal logout

**Steps**:
1. Login successfully
2. Click "Logout"

**Expected Result**:
- ✅ Redirected to login page
- ✅ Session destroyed
- ✅ Session cookie deleted
- ❌ Cannot access admin pages

---

**Test Case 4.2.2**: Session cookie cleanup

**Steps**:
1. Logout
2. Check browser cookies

**Expected Result**:
- ✅ Session cookie removed or expired

---

### 4.3 Access Control

**Test Case 4.3.1**: Unauthenticated access - Admin page

**Steps**:
1. Logout (or clear session)
2. Visit `/admin/`

**Expected Result**:
- ❌ Redirected to `/admin/login.php`

---

**Test Case 4.3.2**: Unauthenticated access - API

**Steps**:
1. Logout
2. Send request to `/admin/api.php?action=credits_list`

**Expected Result**:
- ❌ HTTP 401 Unauthorized
- ❌ JSON error: "Authentication required"

---

**Test Case 4.3.3**: Direct login page access when logged in

**Steps**:
1. Login successfully
2. Visit `/admin/login.php`

**Expected Result**:
- ✅ Redirected to `/admin/` (already logged in)

---

---

## 5. Programs Management

### 5.1 List Programs

**Test Case 5.1.1**: Load programs list

**Steps**:
1. Login to admin
2. View "Programs" tab (default)

**Expected Result**:
- ✅ Programs table displayed
- ✅ Columns: Checkbox, ID, Title, Start, Location, Organizer, Actions
- ✅ Pagination visible

---

**Test Case 5.1.2**: Filter by venue

**Steps**:
1. Select venue from dropdown
2. Click "Search"

**Expected Result**:
- ✅ Only programs at selected venue shown

---

**Test Case 5.1.3**: Search by title

**Steps**:
1. Enter search term
2. Results filter

**Expected Result**:
- ✅ Matching programs shown
- ✅ Non-matching programs hidden

---

### 5.2 Create Program

**Test Case 5.2.1**: Create valid program

**Steps**:
1. Click "+ Add Program"
2. Fill all fields:
   - Title: "Test Program"
   - Start: "2026-03-01 10:00"
   - End: "2026-03-01 11:00"
   - Venue: "Stage A"
   - Organizer: "Test Artist"
   - Categories: "Test Artist"
3. Click "Save"

**Expected Result**:
- ✅ Program created
- ✅ Appears in list
- ✅ Success toast

---

**Test Case 5.2.2**: Validation - Empty title

**Steps**:
1. Click "+ Add Program"
2. Leave title empty
3. Fill other required fields
4. Submit

**Expected Result**:
- ❌ Validation error
- ❌ Form not submitted

---

**Test Case 5.2.3**: Validation - End before start

**Steps**:
1. Set Start: "2026-03-01 10:00"
2. Set End: "2026-03-01 09:00"
3. Submit

**Expected Result**:
- ❌ Validation error: "End time must be after start time"

---

### 5.3 Update Program

**Test Case 5.3.1**: Edit program

**Steps**:
1. Click "Edit" on program
2. Change title to "Updated Program"
3. Click "Save"

**Expected Result**:
- ✅ Program updated
- ✅ Changes visible in list
- ✅ Success message

---

### 5.4 Delete Program

**Test Case 5.4.1**: Delete single program

**Steps**:
1. Click "Delete" on program
2. Confirm deletion

**Expected Result**:
- ✅ Program removed
- ✅ Success message

---

### 5.5 Bulk Operations - Programs

**Test Case 5.5.1**: Bulk delete programs

**Steps**:
1. Select 3 programs
2. Click "🗑️ Delete Selected"
3. Confirm

**Expected Result**:
- ✅ All 3 programs deleted
- ✅ Success message with count

---

**Test Case 5.5.2**: Bulk edit venue

**Steps**:
1. Select multiple programs
2. Click "Edit Selected"
3. Change venue to "New Venue"
4. Submit

**Expected Result**:
- ✅ All selected programs updated
- ✅ Venue changed for all

---

**Test Case 5.5.3**: Bulk edit organizer

**Steps**:
1. Select programs
2. Bulk edit organizer only
3. Submit

**Expected Result**:
- ✅ Organizer updated
- ✅ Other fields unchanged

---

---

## 6. Request System

### 6.1 Submit Request

**Test Case 6.1.1**: Add event request

**Steps**:
1. On index.php, click "📝 Report New Event"
2. Fill form:
   - Title: "New Event"
   - Start/End times
   - Location, Organizer
   - Requester name & email
3. Submit

**Expected Result**:
- ✅ Success message
- ✅ Request saved to database
- ✅ Status = 'pending'

---

**Test Case 6.1.2**: Modify event request

**Steps**:
1. Click "✏️" button on existing event
2. Verify form pre-filled
3. Change title
4. Add note: "Please update this"
5. Submit

**Expected Result**:
- ✅ Success message
- ✅ Request type = 'modify'
- ✅ Original event_id stored

---

**Test Case 6.1.3**: Validation - Required fields

**Steps**:
1. Leave title empty
2. Submit

**Expected Result**:
- ❌ Error: "Title is required"
- ❌ Form not submitted

---

**Test Case 6.1.4**: Rate limiting

**Steps**:
1. Submit 10 requests
2. Submit 11th request

**Expected Result**:
- ❌ 11th request rejected
- ❌ "Too many requests" error

---

### 6.2 Admin - Review Requests

**Test Case 6.2.1**: List pending requests

**Steps**:
1. Login to admin
2. Click "Requests" tab

**Expected Result**:
- ✅ Pending requests shown
- ✅ Badge shows pending count
- ✅ Table shows: Type, Title, Start, Status, Actions

---

**Test Case 6.2.2**: View request details

**Steps**:
1. Click "👁️ View" on request

**Expected Result**:
- ✅ Modal shows all details
- ✅ Requester info visible
- ✅ Request data displayed

---

**Test Case 6.2.3**: Approve add request

**Steps**:
1. View "add" type request
2. Click "✅ Approve"

**Expected Result**:
- ✅ New event created in events table
- ✅ Request status = 'approved'
- ✅ Success message
- ✅ Badge count decreases

---

**Test Case 6.2.4**: Approve modify request

**Steps**:
1. View "modify" type request
2. Click "✅ Approve"

**Expected Result**:
- ✅ Existing event updated
- ✅ Request status = 'approved'
- ✅ Original event_id matched

---

**Test Case 6.2.5**: Reject request

**Steps**:
1. View request
2. Click "❌ Reject"

**Expected Result**:
- ✅ Request status = 'rejected'
- ✅ No event created/updated
- ✅ Success message

---

**Test Case 6.2.6**: Filter by status

**Steps**:
1. Select "Approved" from filter
2. Click search

**Expected Result**:
- ✅ Only approved requests shown

---

---

## 7. Bulk Operations

### 7.1 Master Checkbox

**Test Case 7.1.1**: Select all

**Steps**:
1. Click master checkbox

**Expected Result**:
- ✅ All visible items checked
- ✅ Bulk actions bar appears

---

**Test Case 7.1.2**: Deselect all

**Steps**:
1. Select all
2. Click master checkbox again

**Expected Result**:
- ✅ All items unchecked
- ✅ Bulk actions bar disappears

---

**Test Case 7.1.3**: Indeterminate state

**Steps**:
1. Manually select 2 out of 10 items

**Expected Result**:
- ✅ Master checkbox shows indeterminate state
- ✅ Bulk actions bar shows count: "2 items selected"

---

### 7.2 Bulk Actions Bar

**Test Case 7.2.1**: Show/hide based on selection

**Steps**:
1. Select 1 item
2. Deselect it

**Expected Result**:
- ✅ Bar appears when items selected
- ✅ Bar disappears when none selected

---

**Test Case 7.2.2**: Selection count accuracy

**Steps**:
1. Select 5 items
2. Deselect 2
3. Check count

**Expected Result**:
- ✅ Count shows "5 items selected"
- ✅ Then shows "3 items selected"

---

### 7.3 Row Highlighting

**Test Case 7.3.1**: Visual feedback

**Steps**:
1. Select row
2. Check styling

**Expected Result**:
- ✅ Selected row has class "selected"
- ✅ Background color changes
- ✅ Visual distinction clear

---

---

## 8. Frontend Features

### 8.1 Language Switching

**Test Case 8.1.1**: Switch to English

**Steps**:
1. Click language selector
2. Select "English"

**Expected Result**:
- ✅ All text changes to English
- ✅ `<html lang="en">` attribute set
- ✅ Preference saved to localStorage

---

**Test Case 8.1.2**: Switch to Japanese

**Steps**:
1. Select "日本語"

**Expected Result**:
- ✅ All text changes to Japanese
- ✅ `<html lang="ja">` attribute set

---

**Test Case 8.1.3**: Language persistence

**Steps**:
1. Change language to English
2. Refresh page

**Expected Result**:
- ✅ Language still English
- ✅ Loaded from localStorage

---

### 8.2 View Modes

**Test Case 8.2.1**: Switch to Timeline view

**Steps**:
1. On index.php, toggle view to Timeline
2. Verify display

**Expected Result**:
- ✅ Gantt chart view shown
- ✅ Events on timeline
- ✅ Venues on Y-axis, time on X-axis

---

**Test Case 8.2.2**: View persistence

**Steps**:
1. Switch to Timeline
2. Refresh page

**Expected Result**:
- ✅ Still in Timeline view
- ✅ Saved to localStorage

---

### 8.3 Search & Filter

**Test Case 8.3.1**: Search by artist

**Steps**:
1. Type artist name
2. Press Enter or wait

**Expected Result**:
- ✅ Results filtered
- ✅ Selected tag appears
- ✅ Can remove tag to clear filter

---

**Test Case 8.3.2**: Multi-select artists

**Steps**:
1. Check 2-3 artist checkboxes
2. Click "Search"

**Expected Result**:
- ✅ Events from all selected artists shown
- ✅ Tags display for each selection

---

**Test Case 8.3.3**: Filter by venue

**Steps**:
1. Check venue checkboxes
2. Search

**Expected Result**:
- ✅ Only events at selected venues shown

---

**Test Case 8.3.4**: Clear filters

**Steps**:
1. Apply filters
2. Click clear/reset button

**Expected Result**:
- ✅ All filters removed
- ✅ Full list restored

---

### 8.4 Export Features

**Test Case 8.4.1**: Export to ICS

**Steps**:
1. Apply filters (optional)
2. Click "📅 Export to Calendar"

**Expected Result**:
- ✅ .ics file downloaded
- ✅ Contains filtered events (or all if no filter)
- ✅ Can open in Google Calendar / Apple Calendar

---

**Test Case 8.4.2**: Save as image

**Steps**:
1. Click "📸 Save as Image"
2. Wait for html2canvas to load

**Expected Result**:
- ✅ PNG file downloaded
- ✅ Contains visible calendar
- ✅ Image quality good

---

---

## 9. Performance Testing

### 9.1 Database Performance

**Test Case 9.1.1**: Large dataset - Programs

**Steps**:
1. Import 1000+ programs
2. Load index.php
3. Measure load time

**Expected Result**:
- ✅ Page loads in < 2 seconds
- ✅ Database query efficient
- ✅ Pagination handles large dataset

---

**Test Case 9.1.2**: Large dataset - Credits

**Steps**:
1. Create 500+ credits
2. Load credits.php
3. Measure load time

**Expected Result**:
- ✅ First load: fetches from DB, creates cache
- ✅ Second load: serves from cache (< 100ms)

---

### 9.2 Cache Effectiveness

**Test Case 9.2.1**: Cache hit rate

**Steps**:
1. Clear all cache
2. Load credits.php 10 times within 1 hour
3. Check database query count

**Expected Result**:
- ✅ 1st load: DB query
- ✅ Loads 2-10: No DB query (cache hit)
- ✅ 100% cache hit rate

---

**Test Case 9.2.2**: Cache size

**Steps**:
1. Create 100 credits
2. Check cache file size

**Expected Result**:
- ✅ `credits.json` < 100KB
- ✅ Reasonable file size

---

### 9.3 API Response Times

**Test Case 9.3.1**: Credits list API

**Steps**:
1. Send `GET /admin/api.php?action=credits_list`
2. Measure response time

**Expected Result**:
- ✅ Response in < 500ms
- ✅ JSON properly formatted

---

---

## 10. Edge Cases & Error Handling

### 10.1 Database Errors

**Test Case 10.1.1**: Missing database file

**Steps**:
1. Rename `calendar.db`
2. Visit index.php

**Expected Result**:
- ✅ Graceful error message
- ✅ No PHP fatal errors
- ❌ Database connection failed message

---

**Test Case 10.1.2**: Corrupted database

**Steps**:
1. Corrupt `calendar.db` (edit in hex editor)
2. Visit admin panel

**Expected Result**:
- ✅ Error caught
- ✅ User-friendly message
- ❌ No sensitive error details exposed

---

### 10.2 Missing Tables

**Test Case 10.2.1**: Credits table not exists

**Steps**:
1. Drop credits table
2. Visit credits.php

**Expected Result**:
- ✅ Empty state or error message
- ❌ No SQL error exposed

---

**Test Case 10.2.2**: Requests table not exists

**Steps**:
1. Drop program_requests table
2. Submit request

**Expected Result**:
- ❌ Error: "Table does not exist"
- ✅ User-friendly message

---

### 10.3 Concurrent Requests

**Test Case 10.3.1**: Simultaneous bulk delete

**Steps**:
1. Open 2 browser tabs
2. Select same items in both
3. Bulk delete simultaneously

**Expected Result**:
- ✅ One succeeds
- ✅ Other gets "not found" error (already deleted)
- ✅ No database corruption

---

**Test Case 10.3.2**: Cache race condition

**Steps**:
1. Expire cache
2. Send 10 simultaneous requests to credits.php

**Expected Result**:
- ✅ All requests succeed
- ✅ Cache created once
- ✅ No file corruption

---

### 10.4 Malformed Input

**Test Case 10.4.1**: Invalid JSON in API

**Steps**:
1. Send POST to API with invalid JSON:
   ```
   Content-Type: application/json
   {invalid json}
   ```

**Expected Result**:
- ❌ HTTP 400 Bad Request
- ❌ Error: "Invalid JSON"

---

**Test Case 10.4.2**: SQL injection attempt

**Steps**:
1. Search: `'; DROP TABLE events; --`

**Expected Result**:
- ✅ Treated as literal string
- ✅ No SQL executed
- ✅ No tables dropped

---

**Test Case 10.4.3**: Path traversal attempt

**Steps**:
1. Try to access: `/admin/api.php?file=../../config/admin.php`

**Expected Result**:
- ❌ Access denied
- ❌ No file read

---

### 10.5 Browser Compatibility

**Test Case 10.5.1**: Chrome/Edge

**Steps**:
1. Test all features in Chrome

**Expected Result**:
- ✅ All features work

---

**Test Case 10.5.2**: Firefox

**Steps**:
1. Test all features in Firefox

**Expected Result**:
- ✅ All features work
- ✅ Consistent rendering

---

**Test Case 10.5.3**: Safari (iOS)

**Steps**:
1. Test on iPhone/iPad

**Expected Result**:
- ✅ Touch interactions work
- ✅ Timeline scrolls properly
- ✅ Modals display correctly

---

### 10.6 Mobile Responsive

**Test Case 10.6.1**: Mobile view - Calendar

**Steps**:
1. Resize to 375px width
2. Check layout

**Expected Result**:
- ✅ Timeline view optimized for mobile
- ✅ All buttons accessible
- ✅ No horizontal overflow

---

**Test Case 10.6.2**: Mobile view - Admin

**Steps**:
1. Access admin on mobile
2. Test CRUD operations

**Expected Result**:
- ✅ Tables scrollable
- ✅ Modals fit screen
- ✅ Forms usable

---

---

## 📊 Test Execution Checklist

### Pre-Deployment Tests (Must Pass)

- [ ] All credits CRUD operations work
- [ ] Cache system active and invalidating correctly
- [ ] No XSS vulnerabilities
- [ ] No SQL injection vulnerabilities
- [ ] CSRF protection working
- [ ] Session security (timeout, fixation prevention)
- [ ] Rate limiting effective
- [ ] Admin authentication secure
- [ ] All bulk operations functional
- [ ] Mobile responsive
- [ ] Cross-browser compatible

### Performance Benchmarks

- [ ] Index.php loads < 2 seconds (1000 programs)
- [ ] Credits.php loads < 100ms (with cache)
- [ ] Admin API responds < 500ms
- [ ] Image export completes < 5 seconds
- [ ] Database queries optimized (indexed)

### Security Audit

- [ ] All user inputs sanitized
- [ ] All outputs escaped
- [ ] Prepared statements used
- [ ] CSRF tokens validated
- [ ] Session cookies secure
- [ ] Rate limits enforced
- [ ] Error messages don't leak info

---

## 🔧 Testing Tools

### Manual Testing
- Browser DevTools (Network, Console, Application tabs)
- Multiple browsers (Chrome, Firefox, Safari)
- Mobile device testing (real device or emulator)

### Database Testing
```bash
# SQLite CLI
sqlite3 calendar.db
.tables
.schema credits
SELECT COUNT(*) FROM credits;
```

### Performance Testing
```bash
# Apache Bench
ab -n 100 -c 10 http://localhost:8000/credits.php

# cURL timing
curl -w "@curl-format.txt" -o /dev/null -s http://localhost:8000/credits.php
```

### Security Testing
- OWASP ZAP (Web application security scanner)
- Burp Suite (Penetration testing)
- SQL injection tester tools

---

## 📝 Bug Report Template

When bugs are found, use this format:

```markdown
**Title**: [Component] Brief description

**Severity**: Critical / High / Medium / Low

**Steps to Reproduce**:
1. Step 1
2. Step 2
3. Step 3

**Expected Result**:
What should happen

**Actual Result**:
What actually happened

**Environment**:
- PHP version: X.X.X
- Browser: Chrome 120
- OS: Windows 11

**Screenshots**:
(if applicable)

**Error Messages**:
(if any)
```

---

## ✅ Test Sign-Off

**Tested By**: _______________

**Date**: _______________

**Version**: v3.1.0

**Result**: Pass / Fail

**Notes**:
_________________________________
_________________________________

---

## 12. User Management & Roles

### 12.1 User Management (Admin Role)

**Test Case 12.1.1**: Users tab visibility for admin role

**Steps**:
1. Login as admin role user
2. Check admin panel tabs

**Expected Result**:
- ✅ "👤 Users" tab visible
- ✅ "💾 Backup" tab visible

---

**Test Case 12.1.2**: Users tab hidden for agent role

**Steps**:
1. Login as agent role user
2. Check admin panel tabs

**Expected Result**:
- ❌ "👤 Users" tab NOT visible
- ❌ "💾 Backup" tab NOT visible
- ✅ Programs, Requests, Import ICS, Credits, Events tabs visible

---

**Test Case 12.1.3**: Create new user

**Steps**:
1. Login as admin
2. Click "👤 Users" tab
3. Click "+ Add User"
4. Fill: username, display name, password (min 8 chars), role, active
5. Click "Save"

**Expected Result**:
- ✅ User created successfully
- ✅ User appears in list

---

**Test Case 12.1.4**: Edit user

**Steps**:
1. Click "Edit" on existing user
2. Change display name
3. Leave password empty (keep existing)
4. Click "Save"

**Expected Result**:
- ✅ Display name updated
- ✅ Password unchanged

---

**Test Case 12.1.5**: Delete user - cannot delete self

**Steps**:
1. Try to delete your own user account

**Expected Result**:
- ❌ Error: "Cannot delete your own account"

---

**Test Case 12.1.6**: Delete user - must keep 1 admin

**Steps**:
1. If only 1 active admin exists
2. Try to delete that admin

**Expected Result**:
- ❌ Error: "Cannot delete the last admin user"

---

**Test Case 12.1.7**: Cannot change own role

**Steps**:
1. Edit your own user
2. Try to change role from admin to agent

**Expected Result**:
- ❌ Error: "Cannot change your own role"

---

### 12.2 Role-Based API Protection

**Test Case 12.2.1**: Agent cannot access users API

**Steps**:
1. Login as agent user
2. Call `GET /admin/api.php?action=users_list`

**Expected Result**:
- ❌ HTTP 403 Forbidden
- ❌ JSON error: "Admin role required"

---

**Test Case 12.2.2**: Agent cannot access backup API

**Steps**:
1. Login as agent user
2. Call `POST /admin/api.php?action=backup_create`

**Expected Result**:
- ❌ HTTP 403 Forbidden
- ❌ JSON error: "Admin role required"

---

**Test Case 12.2.3**: Agent can access programs API

**Steps**:
1. Login as agent user
2. Call `GET /admin/api.php?action=programs_list`

**Expected Result**:
- ✅ HTTP 200 OK
- ✅ Programs list returned

---

---

## 13. Artist Reuse System

### 13.1 Artist Profile Page

**Test Case 13.1.1**: Artist profile page loads

**Steps**:
1. Go to `/artist/1` (replace `1` with a valid artist ID)

**Expected Result**:
- ✅ Page loads with artist name in header
- ✅ Programs grouped by event (active events first)
- ✅ Variant names displayed (if any)

---

**Test Case 13.1.2**: Invalid artist ID returns 404

**Steps**:
1. Go to `/artist/99999`

**Expected Result**:
- ✅ 404 page displayed
- ✅ No PHP errors

---

**Test Case 13.1.3**: Group artist shows member chips

**Steps**:
1. Navigate to a group artist profile

**Expected Result**:
- ✅ Member chips displayed linking to individual profiles
- ✅ Clicking a chip opens that member's profile

---

### 13.2 Clickable Artist Badges

**Test Case 13.2.1**: Artist badge splits into filter + profile link

**Steps**:
1. Open an event page with programs
2. Locate a program with linked artists (from `program_artists` table)

**Expected Result**:
- ✅ Badge renders as split pill: artist name (left) + ↗ (right)
- ✅ Clicking artist name appends filter
- ✅ Clicking ↗ opens `/artist/{id}` in new tab

---

**Test Case 13.2.2**: Unlinked artist renders as plain badge

**Steps**:
1. Find a program whose categories are not in `program_artists` table

**Expected Result**:
- ✅ Plain badge (no ↗ link) rendered
- ✅ Clicking badge still appends filter

---

### 13.3 Artist Variants (Admin)

**Test Case 13.3.1**: View variants for an artist

**Steps**:
1. Login as admin
2. Go to Admin › Artists tab
3. Click "Variants" on any artist

**Expected Result**:
- ✅ Modal opens showing existing variants as chips
- ✅ Variant count badge matches displayed variants

---

**Test Case 13.3.2**: Add a new variant

**Steps**:
1. In Variants modal, type a new variant name
2. Click Add

**Expected Result**:
- ✅ Variant appears immediately in the chip list
- ✅ Variant count badge updates

---

**Test Case 13.3.3**: Delete a variant

**Steps**:
1. In Variants modal, click ✕ on an existing variant

**Expected Result**:
- ✅ Variant removed from chip list immediately
- ✅ Variant count badge decreases

---

### 13.4 Cross-Event Section

**Test Case 13.4.1**: "Also appears in" section renders on event pages

**Steps**:
1. Open an event page that has artists appearing in other events

**Expected Result**:
- ✅ "🎪 ศิลปินนี้ยังปรากฏใน" / "Also appears in" section visible before footer
- ✅ Cards show other event names (link to schedule) + artist chips (link to profile)

---

**Test Case 13.4.2**: Section hidden on homepage listing

**Steps**:
1. Open the homepage (no `?event=` parameter)

**Expected Result**:
- ✅ Cross-event section not rendered

---

**Questions or Issues?**
Contact [@FordAntiTrust](https://x.com/FordAntiTrust)
