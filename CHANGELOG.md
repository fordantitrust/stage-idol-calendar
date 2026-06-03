# Changelog

All notable changes to Idol Stage Timetable will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [16.7.1] - 2026-06-03

### Bug Fix — Time Jump overflow and PWA safe-area spacing

The v16.7.0 event-page time chips could overflow horizontally on desktop without an obvious way to reach later slots, and the fixed Date Jump Bar sat at the very top of standalone PWAs where it could collide with iOS Dynamic Island / notch safe areas.

- 🕒 **Desktop time-chip scrolling** — the Time Jump row now has left/right arrow buttons, mouse-wheel horizontal scrolling, a thin desktop scrollbar, and edge fade states so long time lists remain reachable without wrapping or pushing the page wider.
- 📐 **Overflow containment** — the date/time jump rows now use `min-width: 0`, nowrap chips, and hidden row overflow so long schedules stay within the event container.
- 📱 **Mobile behavior preserved** — mobile keeps compact swipeable time chips and hides the extra time arrows to avoid eating vertical space.
- 🏝️ **PWA safe area** — the fixed jump bar now uses `env(safe-area-inset-top, 0px)`, and JS scroll offsets include the computed top inset so jump targets are not hidden under the bar on notched devices.
- 🔄 **SemVer normalization** — normalized accidental `16.7.0rc1` values back into the regular release flow and bumped to `16.7.1`.

**Files changed:**

- `index.php`
- `styles/index.css`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `README.md`
- `WORKFLOW.md`
- `CHANGELOG.md`

> **Migration:** none — UI/CSS/JS only; no DB schema, cache, or API changes. PWA/browser assets are cache-busted by `APP_VERSION` / `CACHE_VERSION` v16.7.1.

## [16.7.0] - 2026-06-03

### Feature — Date + Time Jump with current-program navigation

Event detail pages now extend the existing Date Jump Bar into a compact date/time/current navigation control. The bar remains filter-aware and uses the already-normalized UTC epoch fields (`start_ts` / `end_ts`) for stable anchors and current-state checks, without changing the database, cache schema, or public APIs.

- 📅 **Stable epoch anchors** — program rows now use `id="program-{start_ts}-{id}"` and expose `data-start-ts`, `data-end-ts`, `data-program-id`, and `data-day-key`, so date/time/current jumps target deterministic rows even when display dates are timezone-adjusted.
- 🕒 **Time Jump row** — the jump bar builds one chip per unique `start_ts` in the active filtered day, labels it in the event timezone, and scrolls to the first program in that time slot.
- 🔴 **Current program button** — a filter-aware "Now" button appears only when a visible program is currently active. Multiple concurrent programs resolve to the earliest `start_ts`, then lowest program id. Rows currently in progress get a live current style.
- ⚡ **Client-side clock logic** — `Date.now() / 1000` is compared against `start_ts <= now < end_ts`; instant programs (`end_ts` empty or equal to `start_ts`) only count during a short 5-minute grace window after start.
- 🔁 **Live state refresh** — current state recalculates on load, language change, scroll, resize, and every 30 seconds. Jump highlights are transient and do not interfere with normal date/time navigation.
- 📊 **List + Gantt behavior** — pressing "Now" scrolls to the existing row/anchor when visible; in Gantt view it can scroll to the day section without forcing users back to List view.
- 🌏 **Translations** — added TH/EN/JA keys for `dateJump.now`, `dateJump.time`, and `dateJump.noCurrent`.

**Files changed:**

- `index.php`
- `styles/index.css`
- `js/translations.js`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `README.md`
- `WORKFLOW.md`
- `CHANGELOG.md`

> **Migration:** none — UI/client-side only; no DB schema, cache, or API changes. PWA/browser assets are cache-busted by `APP_VERSION` / `CACHE_VERSION` v16.7.0.

## [16.6.0] - 2026-06-03

### Feature — Favicon support (browser tab + apple-touch-icon)

The site previously declared no favicon at all — no `favicon.ico` file and no `<link rel="icon">` on any page — so browsers fell back to their default tab icon and every page silently 404'd on the automatic `/favicon.ico` request. The PWA icons in `manifest.json` only cover home-screen install, not the browser tab. This adds a real favicon plus icon `<link>` tags reusing the existing sakura PWA icons.

- 🖼️ **`favicon.ico` (new, project root)** — a multi-size icon (16×16, 32×32, 48×48) where each entry is stored as a PNG stream (PNG-compressed ICO entries, supported by all modern browsers), downscaled from `icon/icon-512.png`.
- 🧰 **`tools/generate-favicon.php` (new)** — GD-based generator: reads `icon/icon-512.png` (falls back to `icon/icon-192.png`), resamples to the three sizes, and hand-assembles the ICONDIR/ICONDIRENTRY container — no external `.ico` encoder needed. Re-run after changing the source icon.
- 🔗 **Icon `<link>` tags on every page** — added after the existing `<link rel="manifest">`: `rel="icon"` 192×192 + 72×72 PNG, `rel="icon"` → `favicon.ico` (`sizes="any"`), and `rel="apple-touch-icon"` 192×192. All hrefs go through `get_base_path()` / `asset_url()` (or document-relative paths on the root-level `setup.php`) so they resolve correctly under subdirectory installs.
- 📄 **Pages covered** — 12 public pages (`index`, `artist`, `artists`, `contact`, `credits`, `how-to-use`, `past-events`, `venue`, `venues`, `my`, `my-favorites`, `connect`) plus `admin/login.php` and `setup.php`. Other admin pages still get the tab icon via the root `favicon.ico` (browser auto-discovery on domain-root installs).
- 🎯 **No DB / runtime impact** — static assets + template `<head>` only.

**Files changed:**

- `favicon.ico` (new)
- `tools/generate-favicon.php` (new)
- `index.php`
- `artist.php`
- `artists.php`
- `contact.php`
- `credits.php`
- `how-to-use.php`
- `past-events.php`
- `venue.php`
- `venues.php`
- `my.php`
- `my-favorites.php`
- `connect.php`
- `admin/login.php`
- `setup.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `CHANGELOG.md`

> **Migration:** none — static/template only; no DB schema change. The `favicon.ico` is committed; re-run `php tools/generate-favicon.php` if the source icon changes. Returning visitors pick up the new tab icon once the browser revalidates the pages (cache-busted by the version bump / `CACHE_VERSION` v16.6.0).

## [16.5.5] - 2026-06-03

### Bug Fix — Header buttons hidden under the notch/camera in standalone PWA

When the site was installed as a PWA, the top header buttons (home / event-picker / contact / how-to-use on the left, language switcher on the right) were unreachable because the OS camera, notch, or Dynamic Island covered them. Most public pages set `apple-mobile-web-app-status-bar-style` to `black-translucent` together with `viewport-fit=cover`, which makes iOS extend the web content fully under the status bar safe area — but the header never reserved that space, so the absolutely-positioned buttons sat underneath the hardware cutout.

- 🐛 **Root cause** — the existing `@supports` safe-area block in `styles/common.css` padded only `body` (left/right/bottom), not the top; the header's absolutely-positioned `.header-top-left` and `.language-switcher` used fixed `top: 20px` (10px on mobile) that ignored `env(safe-area-inset-top)`, so under `black-translucent` standalone they rendered beneath the notch/camera.
- 🎯 **Approach — uniform shift** — the title (normal flow) and both button groups (absolute) all move down by **exactly** `env(safe-area-inset-top)` via `calc(<base> + env(...))`, so the original relative spacing (which never overlapped) is preserved and simply translated below the cutout. Using `max(<base>, env(...))` for padding while `calc(<base> + env(...))` for the buttons was the initial mistake — the two moved by different deltas and the language switcher dropped onto the title.
- 🔧 **`header` padding-top** — `calc(70px + env(safe-area-inset-top, 0px))` (60px base on mobile); the base value also seats the centered title clearly below the button row (which otherwise sat at the same vertical band and felt cramped). Horizontal insets are left to the existing `body` `@supports` block to avoid double-padding.
- 🔧 **`.header-top-left` (left buttons)** — `top`/`left` → `calc(20px + env(safe-area-inset-top/left, 0px))` (10px base on mobile).
- 🔧 **`.language-switcher` (right buttons)** — `top`/`right` → `calc(20px + env(safe-area-inset-top/right, 0px))` (10px base on mobile).
- 🔧 **`styles/index.css` mobile override** — the homepage/event pages also load `index.css`, whose `@media (max-width:768px)` rule re-pinned `.header-top-left { top: 10px; left: 10px }` **without** `env()` and won the cascade (loaded after `common.css`), so the left buttons stayed under the notch while the right side moved. Added the same `calc(... + env(...))` insets there to keep both files in sync.
- 🎯 **No visual change in a normal browser** — `env(safe-area-inset-*)` is `0` without a hardware cutout, so the desktop and in-browser layouts render exactly as before; only standalone PWAs on notched devices shift the header content down.
- ⚡ **Cache-bust** — version bump to v16.5.5 refreshes the `?v=APP_VERSION` query on `common.css` / `index.css`; `service-worker.js` `CACHE_VERSION` synced so installed PWAs pick up the fix on the next revalidation (no reinstall required).

**Files changed:**

- `styles/common.css`
- `styles/index.css`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `CHANGELOG.md`

> **Migration:** none — CSS-only; no DB schema change. Installed PWAs get the fix once the service worker revalidates `common.css` / `index.css` (cache-busted by the version bump / `CACHE_VERSION` v16.5.5) — no need to uninstall and reinstall the PWA.

## [16.5.3] - 2026-06-03

### UI — Switch site font to Noto Sans (Google Fonts)

Replaced the legacy `'Segoe UI', Tahoma, …` body font stack with **Noto Sans** referenced directly from Google Fonts. Because the site serves Thai/English/Japanese content, the import bundles **Noto Sans + Noto Sans Thai + Noto Sans JP** (weights `400..800`, `display=swap`) so all three scripts render in the same family. Monospace contexts (code, log viewers, URL inputs) are left untouched.

- 🔤 **`styles/common.css`** — added `@import` for the three Noto Sans families at the top of the file (before `:root`); changed the `body` font stack to `'Noto Sans', 'Noto Sans Thai', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`. Every public page, the admin panel, and the login page load `common.css`, so the new font cascades site-wide from this single point.
- 🧷 **Form-control inheritance** — added `input, textarea, select, button { font-family: inherit }` to `common.css`. Form controls don't inherit `font-family` from `body` by default, so buttons, search boxes, dropdowns, and filter inputs were still rendering in the browser default font — this reset makes them use Noto Sans too. (Monospace inputs that set their own font, e.g. URL/hex fields, are unaffected since they override `inherit`.)
- 🔘 **Event action-bar button fit** — Noto Sans JP is wider than the previous Segoe UI, so the Japanese action-bar labels (e.g. 画像として保存, カレンダーにエクスポート, 追加・編集をリクエスト) overflowed the row and wrapped mid-label on desktop. Fixed in `styles/index.css`: `.filter-buttons` now `flex-wrap: wrap` (the row wraps to a new line instead of shrinking buttons), and `.filter-buttons .btn` uses `font-size: 0.9em`, tighter `padding: 12px 18px`, and `white-space: nowrap` so each label stays on one line. The ≤768px mobile override keeps its existing 3-per-row layout and adds `white-space: normal` so narrow buttons can still wrap.
- 🛠️ **`admin/help.php` + `admin/help-en.php`** — both already load `common.css`; overrode their inline `body { font-family: sans-serif }` with the same Noto Sans stack so the help pages match.
- 🧰 **`setup.php`** — added Google Fonts `<link>` (with `preconnect`) in `<head>` so the fresh-install branch (which renders before `common.css` is available) still gets Noto Sans; updated the inline `body` font stack; added the same form-control `font-family: inherit` reset to its always-applied style block so both the fresh-install and config-loaded branches cover form controls. The config-loaded branch inherits the font via `common.css`.
- 🚫 **Out of scope** — `image.php` server-side PNG export already uses bundled Noto Sans TTF files via PHP GD and is unrelated to this web-font change; `offline.html` keeps its system-font stack because it renders offline where the cross-origin Google Fonts aren't cached; monospace usages and hard-failure error pages (no `common.css`) unchanged.
- ⚡ **Cache-bust** — version bump to v16.5.3 refreshes the `?v=APP_VERSION` query on `common.css` so returning users pick up the new font automatically; `service-worker.js` `CACHE_VERSION` synced.

**Files changed:**

- `styles/common.css`
- `styles/index.css`
- `admin/help.php`
- `admin/help-en.php`
- `setup.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `CHANGELOG.md`

> **Migration:** none — CSS/template-only; no DB schema change. Returning users get Noto Sans once the browser revalidates `common.css` (cache-busted by the version bump) and the service worker (`CACHE_VERSION` v16.5.3).

## [16.5.2] - 2026-06-02

### Security (LOW-5) — Move Favorites HMAC secret out of the git-tracked config file

A diff-based review of the working tree (security audit revision 7) found the live `FAVORITES_HMAC_SECRET` had been written directly into `config/favorites.php` — a **git-tracked** source file that is **not** covered by the `config/*-config.json` `.gitignore` rule. Committing it would have leaked the secret into history (same recurrence class as HIGH-1). The secret signs the favorites slug, so disclosure would let an attacker forge slugs and read/modify any user's follow list, viewer timezone, and push subscriptions. Verified the secret was **never committed** (`git log -S` finds it only in the working tree; `HEAD` still carried the placeholder), so there was no actual disclosure — severity **LOW**.

- 🔐 **Secret relocated** — `FAVORITES_HMAC_SECRET` now lives in `config/favorites-config.json`, matched by the existing `config/*-config.json` `.gitignore` rule and HTTP-denied by the `config/.htaccess` deny-all (LOW-2). The tracked file no longer contains any secret.
- 🔧 **Loader updated** — `config/favorites.php` loads `hmac_secret` from the JSON (mirroring `config/telegram.php`), falling back to the `REPLACE_WITH_GENERATED_SECRET` placeholder when the file is absent.
- 🧰 **Generator hardened** — `tools/generate-favorites-secret.php` now writes the gitignored JSON directly (with an overwrite guard that refuses to clobber an existing secret), instead of instructing the operator to paste the secret into the tracked file — closing the recurrence path.
- 📖 **Audit doc** — `docs/SECURITY_AUDIT_2026.md` updated to revision 7: re-verified all 7 prior findings (HIGH-1, MEDIUM-1/2, LOW-1→4) intact at v16.5.x, reviewed new released surface (v15.5.0→v16.5.1) with no findings, recorded and resolved LOW-5.
- 🧪 tests **13,231/13,231 pass** (no regression; `FavoritesTest` 84/84 with secret loaded from JSON)

**Files changed:**

- `config/favorites.php`
- `tools/generate-favorites-secret.php`
- `docs/SECURITY_AUDIT_2026.md`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `TESTING.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `CHANGELOG.md`

> **Migration:** none — no DB schema change. On existing installs the secret continues to load from the working-tree value until moved; create `config/favorites-config.json` with `{"hmac_secret": "<existing 64-hex>"}` (or re-run `php tools/generate-favorites-secret.php` for a fresh secret — note this invalidates all existing Favorites URLs). `config/favorites-config.json` is gitignored and must never be committed.

## [16.5.1] - 2026-06-02

### Docs — Help & How-to-Use coverage brought up to v16.5.0

The Admin Help pages and the public How-to-Use guide had drifted behind the features that shipped from v15.7.0 through v16.5.0. This documentation-only release closes that gap. No runtime code, schema, or API changed; tests remain **13,231/13,231**.

- 📖 **Admin Help (TH + EN)** — Telegram section: added the `/tz [zone|auto]` command (v16.1.1), changed `/notify on|off` → `/notify on|off|summary` with all three modes explained (v16.2.0), updated `/status` to include timezone + notify mode, and added a new **"Notification Modes & Timezone"** subsection (per-program / daily summary / user-timezone display with parenthetical local time)
- 🔴 **How-to-Use — new section: Live Now** (v16.1.0) — explains the homepage strip showing programs on now + starting within 60 min, auto-refresh every 30 s, device-clock status across timezones
- 🏛️ **How-to-Use — new section: All Venues** (v16.0.0 / v16.0.1) — `/venues` portal + `/venue/{id}` profile + clickable venue cells + `🌐 Online` badge behaviour
- 📊 **How-to-Use — My Favorites** — documented the 📋 List / 📊 Timeline toggle (v15.8.0) with overlap visualisation and user-timezone axis
- 🔔 **How-to-Use — Telegram** — added `/tz`, `/notify summary`, a timezone annotation note, and the daily-summary-only mode
- ❓ **How-to-Use — FAQ** — rewrote the offline answer to mention PWA Offline Cache (v15.7.0); the old answer ("can't be used offline") was outdated
- 🌐 **i18n** — added/updated translation keys across all three languages (TH/EN/JA) in `js/translations.js`: `section25.*` (Live Now), `section26.*` (Venues), `section17.myupcoming.timeline`, `section20.notifications.summary`, `section20.tz.*`, `section20.controls.tz`, plus revised `section20.controls.notify/status` and `section6.a2`; 2 new TOC entries
- 🎯 **Out of scope** — backend-only features (v16.4.0 CLI social import, v16.5.0 cross-event group query) are not surfaced in user-facing help; they remain documented in tool/setup docs

**Files changed:**

- `admin/help.php`
- `admin/help-en.php`
- `how-to-use.php`
- `js/translations.js`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `TESTING.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `CHANGELOG.md`
- `README.md`

> **Migration:** none — documentation/i18n only; no DB schema change. Users pick up the new help/how-to-use content once the browser revalidates the service worker (`CACHE_VERSION` bumped to v16.5.1).

## [16.5.0] - 2026-06-01

### Feature — Cross-event section also references each artist's group

The "งานอื่นที่เกี่ยวข้องกับศิลปิน" (cross-event artists) section on an event page lists other active/upcoming events where the current event's artists also appear. Until now it matched only the **same artist_id** — so if member X performs at this event but X's **group/วง G** performs elsewhere (tagged as the group, not X individually), that event stayed invisible. This release expands the match set to also include each solo artist's parent group, surfacing those group appearances. It reuses the same solo→group resolution already used by My Upcoming Programs (`my.php`).

- 🎤 **Group-aware match set** — the cross-event query in `index.php` now `UNION`s each current-event artist's parent `group_id` into the `IN (...)` lookup, so events where the artist's group performs are included alongside same-artist events
- 🏷️ **Group chips link to the group profile** — surfaced group appearances render as `.cross-event-artist-chip` links to `/artist/{group_id}`, reusing the existing markup/CSS; chips are deduped per event by artist id
- 🗃️ **Name resolved in-query** — the query joins `artists` and selects `artist_name` per row so group names resolve correctly even for groups not present in the current event (which aren't in `$artistMeta`); the render path keeps the old `$artistMeta` lookup as a fallback for stale cache entries
- 🎯 **Out of scope** — no group→member expansion (solo→group only); no DB schema change, migration, or CSS change
- 🧪 tests **13,231/13,231 pass** (no regression; query/render-only change)

**Files changed:**

- `index.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — query/render-only change; no DB schema change. The `artist_other_events` query cache picks up the new `artist_name` field on its next rebuild (TTL 1h or on the next program/artist write).

## [16.4.0] - 2026-06-01

### Feature — Bulk-import artist social links from CSV/JSON (CLI tool)

Artist social links (Facebook / Instagram / Twitter-X / TikTok) could only be set one artist at a time through the Admin artist edit modal — the existing `artists_bulk_import` flow imports names only. Editing socials for dozens of members across several groups was impractical. This release adds a CLI tool that reads a spreadsheet-style CSV (or JSON) and updates social columns in bulk.

- 🧰 **`tools/import-artist-socials.php`** — `php tools/import-artist-socials.php <file.csv> [--dry-run] [--overwrite]`. Reads a header-row CSV (or `.json` array) and updates `artists.social_facebook/instagram/twitter/tiktok`
- 🔎 **Name-first matching with ID fallback** — each row is resolved by exact `artists.name`, then by `artist_variants` alias, and finally by an explicit `id` column. Ambiguous name matches (>1 artist) are skipped and reported — never guessed
- 🔗 **URL validation** — only `http(s)://` values are stored, mirroring `sanitize_social_url()` in `admin/api.php`. Invalid/empty cells are ignored
- 🛟 **Non-destructive defaults** — an empty cell leaves the existing value untouched; a non-empty value fills only blank fields by default. Pass `--overwrite` to replace existing links; `--dry-run` previews every change without writing
- 🈶 **Flexible headers** — case-insensitive aliases (`name`/`artist`/`ชื่อ`, `id`, `facebook`/`fb`, `instagram`/`ig`, `twitter`/`x`, `tiktok`/`tt`, plus the full `social_*` names); UTF-8 BOM tolerated so Excel-saved CSVs work
- 🔄 **Cache invalidation** — calls `invalidate_artist_query_cache()` + `invalidate_data_version_cache()` when any row is written
- 📄 **`tools/sample-artist-socials.csv`** — Excel-friendly UTF-8-BOM template demonstrating name-match and id-fallback rows
- 🎯 **Out of scope** — no Admin UI change; no DB schema change; group/solo membership is not modified

**Files changed:**

- `tools/import-artist-socials.php`
- `tools/sample-artist-socials.csv`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — CLI dev tool only; no DB schema change. Reuses existing `artists.social_*` columns (added in v9.5.0).

## [16.3.0] - 2026-06-01

### Feature — Artist profile links in Calendar view programs

The Calendar view (`venue_mode = 'calendar'`) shows programs in two interactive surfaces — the mobile **day panel** (tap a day → list of programs) and the **detail modal** (tap a program → details). In both, artists were rendered as plain text on the `🎤` line. Everywhere else in the app (list view, artist filter, cross-event section) an artist name links to its profile at `/artist/{id}` — Calendar view was the one place a user could not click through. This release adds those links to both calendar surfaces.

- 🎤 **Clickable artists** — `openCalendarDetailModal()` and the day-panel `.cal-dp-item-artist` line now render each artist as a `/artist/{id}` link (opens in a new tab). Artists not present in the `artists` table (raw categories fallback) stay as plain comma-separated text — no broken links
- 🗃️ **Data plumbing** — `index.php` augments each event in `window.CALENDAR_EVENTS` with an `artists` array (`{id, name}`) derived from `$programArtistIdMap`, mirroring the list-view program-categories cell logic; `categories` remains as the text fallback. No cache-shape or DB change
- 🧰 **`calArtistLinksHtml(ev)`** helper in `js/common.js` — builds the link markup, escapes names via `escapeHtml()`, uses the existing `BASE_PATH` JS const; falls back to `ev.categories || ev.organizer`
- 🖱️ **Click isolation** — the whole `.cal-dp-item` opens the detail modal, so the day-panel click guard now also early-returns on `.cal-artist-link` (alongside the existing `.cal-dp-join` guard) so tapping a link navigates instead of opening the modal
- 🎨 **`.cal-artist-link`** styling added to `styles/common.css` (theme accent color, dotted→solid underline on hover)
- 🧪 Tests **13,231/13,231 pass** (JS/PHP render-only change; no behavioral regressions)

**Files changed:**

- `index.php`
- `js/common.js`
- `styles/common.css`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — JS/PHP/CSS only; no DB schema change. The `artists` field is attached to `CALENDAR_EVENTS` at render time.

## [16.2.0] - 2026-06-01

### Feature — Telegram "daily summary only" notification mode

Telegram notifications were binary: `/notify on|off`. Because the cron checked the on/off flag **first** and returned early when off, turning notifications off also silenced the daily 9 AM summary — there was no way to keep just the once-a-day digest while muting the per-program "starting soon" pings. This release adds a third mode so a user can receive **only** the daily summary. Control is via the `/notify` bot command only (no UI added); mute (`/mute N`) still silences everything temporarily.

- 🔔 **Three notification modes** — favorites JSON gains `telegram_notify_mode` ∈ `all` | `summary` | `off`. `all` (default) = per-program reminders + daily summary; `summary` = daily summary only; `off` = nothing. Backward compatible with the legacy `telegram_notify_enabled` boolean (`false` → `off`, `true`/absent → `all`)
- 🤖 **`/notify on|off|summary`** — the command now accepts `summary`; it persists `telegram_notify_mode` (and keeps the legacy boolean in sync) and replies with mode-specific copy. New message key `notify_summary` (TH/EN/JA); `notify_invalid`, welcome and help text updated to list all three options
- ⚙️ **Cron gating** — `cron/send-telegram-notifications.php` wraps the per-program window query + send loop in `if ($perProgram)` (`telegram_per_program_enabled()`), so `summary` mode skips per-program reminders while the daily-summary block still runs. The `off`-mode and mute early-returns are unchanged, so mute still suppresses both channels
- 📊 **`/status`** — the notify line is now 3-state (On / Daily summary only / Off) driven by `telegram_get_notify_mode()`
- 🧰 **Helpers** — `telegram_get_notify_mode($favData)` and `telegram_per_program_enabled($favData)` in `functions/telegram.php`; `telegram_notify_is_enabled()` reimplemented as `mode !== 'off'` (preserves existing behaviour)
- 🎯 **Out of scope** — Web Push (no daily-summary feature); `my.php` UI (Telegram on/off has never had a web toggle)
- 🧪 **`TelegramTest` +15** — full suite **13,231/13,231 pass**

**Files changed:**

- `functions/telegram.php`
- `cron/send-telegram-notifications.php`
- `api/telegram.php`
- `tests/TelegramTest.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — no DB schema change; favorites JSON gains `telegram_notify_mode` only when a user runs `/notify summary` or `/notify off`. Existing links default to `all` (unchanged behaviour).

## [16.1.1] - 2026-06-01

### Feature — Notifications use the viewer's own timezone

Telegram and Web Push notifications previously showed the program time in the event's timezone with the **site default** (Asia/Bangkok) in parentheses. A fan following from another country (e.g. Tokyo) saw the Bangkok clock, not their own. Notifications now annotate the **viewer's** local time instead. The notification *firing* time was already UTC-correct and is unchanged — only the displayed clock is affected.

- 🕐 **Viewer-local parenthetical** — `telegram_format_notification($program, $userTz)` and the web push cron keep event-local time primary and append the viewer's local time labelled with its IANA zone, e.g. `18:00 (19:00 Asia/Tokyo)`. When the viewer TZ equals the event TZ no parenthetical is shown; when no viewer TZ is known the legacy site-default annotation is preserved (backward compatible)
- 🗃️ **Unified timezone model** — single source of truth in the favorites JSON: `user_timezone` (effective IANA zone) + `user_timezone_manual` (sticky override flag). Per-device web push subscriptions also carry their own `tz`. Resolver `fav_resolve_user_timezone($favData, $subTz)` priority: manual override wins for every channel; otherwise per-device tz → `user_timezone` → site default
- 🌐 **my.php timezone picker** — a settings row controls the notification timezone for both channels: "Automatic (detected: …)" plus a region-grouped list. Manual pick is sticky; "Automatic" clears the override and follows the browser
- 🔄 **Auto re-sync on travel** — the browser TZ is captured on page load (mode `sync`, ignored while a manual override is active) and web push devices refresh their stored `tz` when the OS timezone changes, so notifications stay correct after moving countries
- ✈️ **Telegram `/tz` command** — `/tz` shows the effective zone + mode; `/tz Asia/Tokyo` sets a manual override; `/tz auto` reverts to the browser-detected zone; `/status` now shows the effective timezone. Added to the dispatcher, welcome/help text, and i18n message keys `tz_set`/`tz_invalid`/`tz_current`/`tz_auto` (TH/EN/JA)
- 🔌 **API** — `api/favorites.php?action=set_timezone` (modes `manual`/`auto`/`sync`); `api/push.php` subscribe accepts `tz`, validates it, and refreshes `tz`+`lang` when an endpoint re-subscribes
- 🧰 **Helper** — `is_valid_timezone($tz)` in `functions/helpers.php`
- 🎯 **Out of scope (already correct)** — notification firing time (UTC-based), personal ICS feed (`my-feed.php`, localized per device by calendar apps), `/today` `/tomorrow` `/week` and the daily summary (counts, not times)
- 🌏 **i18n** — `tz.settingLabel`, `tz.auto`, `tz.effective` added in TH/EN/JA
- 🧪 **`TelegramTest` +21, `WebPushTest` +6** — full suite **13,088/13,088 pass**

**Files changed:**

- `functions/helpers.php`
- `functions/telegram.php`
- `api/favorites.php`
- `api/push.php`
- `api/telegram.php`
- `cron/send-telegram-notifications.php`
- `cron/send-web-push-notifications.php`
- `my.php`
- `js/translations.js`
- `tests/TelegramTest.php`
- `tests/WebPushTest.php`
- `service-worker.js`
- `config/app.php`
- `docs/TIMEZONE_NOTIFICATION_PLAN.md`
- `CHANGELOG.md`

> **Migration:** none — no DB schema change; favorites JSON gains optional `user_timezone` / `user_timezone_manual` keys on first use; web push subscriptions gain an optional per-device `tz` on next subscribe/visit.

## [16.1.0] - 2026-06-01

### Feature — "Live Now" strip on the homepage listing

The multi-event homepage listing previously showed only overview data (hero carousel, monthly calendar, events grid) with no real-time signal of what is happening *right now*. A new **Live Now** strip at the top of the listing (above the hero) shows 🔴 programs currently on stage and ⏭️ programs starting within 60 minutes, aggregated across **all active events**.

- 🔴 **Currently playing + starting soon** — two groups (`#liveNowCurrentRows`, `#liveNowSoonRows`); each row shows title/artist, 📍 venue, event name, event-local time, a live countdown, and a 🔴 Watch Live button when the program has a `stream_url`; clicking a row opens that event's timetable
- 🌐 **Timezone-correct & cache-safe** — PHP emits candidate programs (today ±1 day) as ISO-8601-with-offset absolute instants via `(new DateTime($p['start'], $tz))->format('c')`; the client classifies live/soon against its own clock, so cross-timezone events and the 1-hour listing cache never produce a stale "live" state. Cross-TZ rows append a `(HH:MM local)` annotation reusing the `tz.localTime` key (v16.0.x pattern)
- ♻️ **Reuses existing architecture** — candidate query joins active events excluding `DEFAULT_EVENT_SLUG`, index-backed by `idx_programs_start`; result stored in the existing `query_listing.json` cache under a new `live_programs` key (no new cache file, no DB schema change)
- 🔄 **Self-refreshing** — `renderLiveNow()` runs on `DOMContentLoaded`, every 30 s via `setInterval`, and on `appLangChange`; promotes "starting soon" → "live" and updates countdowns without a page reload; the whole strip hides itself when nothing is live or soon (no empty box)
- 🌏 **i18n** — `live.nowTitle`, `live.soonTitle`, `live.timeLeft`, `live.startsIn`, `live.watch` added in TH/EN/JA
- 🎯 **Scope** — homepage listing only (multi-event); single-event pages unchanged
- 🧪 **`LiveNowTest`** — 15 new tests; full suite **12,847/12,847 pass**

**Files changed:**

- `index.php`
- `styles/index.css`
- `js/translations.js`
- `tests/LiveNowTest.php`
- `tests/run-tests.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `CHANGELOG.md`

> **Migration:** none — PHP/JS/CSS only; no DB schema changes. The listing cache picks up `live_programs` automatically on next rebuild (TTL 1 h) or after any program write.

## [16.0.13] - 2026-06-01

### Enhancement — Cross-timezone local time annotation in Telegram & WebPush notifications

When a program's event timezone differs from `DEFAULT_TIMEZONE`, notifications now append the site-default local time in parentheses so recipients immediately see both the official event time and their own local equivalent without confusion.

- 🔔 **Telegram** (`functions/telegram.php`) — `telegram_format_notification()` appends `(HH:MM–HH:MM)` local time after the event-local time string when `event_timezone ≠ DEFAULT_TIMEZONE`; for single-time programs (start == end) only start is shown; cross-day programs retain the existing `(next day)` suffix
- 📱 **WebPush** (`cron/send-web-push-notifications.php`) — notification body appends `(HH:MM)` local start time when event TZ differs; compact one-time format fits notification constraints
- 🎯 **Same-timezone → no change** — Bangkok user watching Bangkok event sees no extra annotation; annotation appears only for cross-timezone programs
- ✅ Tests **11,813/11,813 pass**

**Example (Taipei event, Bangkok user):**
- Before: `⏰ 18:00–18:40`
- After:  `⏰ 18:00–18:40 (17:00–17:40)`

**Files changed:**

- `functions/telegram.php`
- `cron/send-web-push-notifications.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `CHANGELOG.md`

> **Migration:** none — PHP-only fix; no DB schema changes

## [16.0.12] - 2026-05-31

### Bug Fix — Telegram & WebPush notifications fired at wrong time for non-Bangkok event timezones

Both notification cron scripts compared `programs.start` (stored in the event's own timezone, e.g. `Asia/Taipei`) against a notification window string computed in `DEFAULT_TIMEZONE` (`Asia/Bangkok`). A Taipei event program stored as `"18:00"` is Bangkok `17:00`, but the cron treated the stored `"18:00"` as if it were Bangkok `18:00`, so notifications fired 1 hour late — 30 minutes after the event started instead of 30 minutes before. The same mismatch made `telegram_format_notification()` display event-local times using hardcoded `'Asia/Bangkok'`, showing the wrong hour in the notification message body for any cross-TZ event.

- 🐛 **Root cause** — both cron scripts used `DEFAULT_TIMEZONE` strings for the SQL `BETWEEN` window without fetching `e.timezone` per program; `telegram_format_notification()` in `functions/telegram.php` hardcoded `new DateTimeZone('Asia/Bangkok')` for both `$start` and `$end`
- 🔧 **SQL fix** — added `COALESCE(e.timezone, :defaultTz) AS event_timezone` to both cron `SELECT` queries; replaced exact `BETWEEN` window strings with a ±14 h loose pre-filter (`$looseStartStr` / `$looseEndStr`) that covers every possible UTC offset without fetching the entire programs table
- 🔧 **PHP exact check** — after the SQL fetch, added a per-program UTC-timestamp comparison: `(new DateTime($prog['start'], $evTz))->getTimestamp()` vs `(windowStart + windowEnd) / 2` ± `halfWindowDur`; programs outside the real window are skipped regardless of what the loose SQL returned — mirrors the `my.php` v16.0.3 fix identically
- 🔧 **WebPush display time** — `cron/send-web-push-notifications.php` line 255 used `date('H:i', strtotime($prog['start']))` which silently treated the stored string as Bangkok time; replaced with `(new DateTime($prog['start'], $evTz))->format('H:i')` (reusing `$evTz` already computed for the exact check)
- 🔧 **Telegram notification body** — `telegram_format_notification()` hardcoded `'Asia/Bangkok'`; now uses `$program['event_timezone']` with `DEFAULT_TIMEZONE` / `'Asia/Bangkok'` fallback, so the ⏰ time shown in the push message correctly reflects the event's local timezone
- ✅ Tests **11,813 / 11,813** pass

**Files changed:**

- `cron/send-telegram-notifications.php`
- `cron/send-web-push-notifications.php`
- `functions/telegram.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `SECURITY.md`
- `ICS_FORMAT.md`
- `CHANGELOG.md`

> **Migration:** none — cron / PHP-only fix; no DB schema changes.

## [16.0.11] - 2026-05-28

### Bug Fix — My Timeline mobile horizontal scroll broken with 3+ events

On mobile, the My Timeline view could display the first two event columns but the user could not horizontally scroll to reach a third (or fourth, fifth …) event. The `overflow-x: auto` on the scroll container looked correct in CSS but never produced a scrollbar — instead the entire timeline card overflowed the viewport horizontally, body's `overflow-x: hidden` then cropped it, and the right-side events were unreachable.

- 🐛 **Root cause** — `renderFavTimelineDay()` was emitting the calculated `minWidth` (50px time-axis + N × 140px event columns on mobile) as an **inline `min-width` on `.fav-tl-chart` itself** (the `overflow-x: auto` container). With min-width on the scroll container, the container itself grew past viewport width instead of staying at viewport width with overflowing inner content. The browser then had nothing to scroll inside `.fav-tl-chart` (its content was exactly its width), so `overflow-x: auto` produced no scrollbar; meanwhile the wide `.fav-tl-chart` pushed the entire `.fav-timeline-day` card out beyond the viewport edge, getting clipped by the body's overflow rules
- 🔧 **Fix** — moved the inline `min-width` from `.fav-tl-chart` (scroll container) to `.fav-tl-header` and `.fav-tl-body` (the inner content). Now the scroll container fills the parent card at viewport width, but its inner header and body force themselves to at least `minWidth` wide — overflowing the container, which triggers `overflow-x: auto` to render a horizontal scrollbar; the user can swipe/drag inside the timeline card to reach all events while the card itself stays within the viewport
- 📊 **Net layout change** — same visual width per column (180 / 140 px), same gridlines and bar positioning math (v16.0.8 user-local minutes unchanged), only the element that carries `min-width` shifted one level inward
- 🎯 **Same-event-count cases (1–2 events on mobile)** — these always fit inside the viewport anyway, so the visual is identical to v16.0.10
- ✅ **Verified** — generated HTML in the JS template now has `class="fav-tl-chart"` with no inline width, `class="fav-tl-header" style="min-width: Xpx"`, and `class="fav-tl-body" style="height: Ypx; min-width: Xpx"`; tests **11,813 / 11,813** pass

**Files changed:**

- `my.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — CSS/JS-only.

## [16.0.10] - 2026-05-28

### Bug Fix — `init_database` left venues out of sync with seeded sample programs

`testVenuesSeededFromLocations` started failing because the `venues` table can end up with fewer rows than the distinct `programs.location` values — the v16.0.0 invariant the test guards. Reproduction path: running Setup Wizard's "Initialize Database" recreates the `programs` table and seeds three sample programs (`Main Stage`, `Main Stage`, `Sub Stage`) but the `venues` table is freshly created empty in the same step; `venue_resolve_canonical()` is only wired into the admin CRUD/ICS-import paths, not into the bulk seed `INSERT INTO programs (...)` loop, so the seeded locations never get registered as venues. Result: `venues=0, distinct=2`, test fails. The same pattern would bite anyone re-running `init_database` on an existing installation, or anyone who imported programs before v16.0.0 (when `venue_resolve_canonical` did not exist).

- 🐛 **Root cause** — `init_database` block in `setup.php` (the Setup Wizard's fresh-install path) seeds 3 sample programs (`Opening Ceremony` / `Artist Performance` / `Closing Stage` with locations `Main Stage` / `Sub Stage`) via a direct `INSERT INTO programs (...)` loop, bypassing the `venue_resolve_canonical()` helper that normally auto-registers new venues
- 🔧 **Fix 1 — re-sync after seed** — added `INSERT OR IGNORE INTO venues (name) SELECT DISTINCT location FROM programs WHERE location IS NOT NULL AND location != ''` immediately after the sample-program seed loop in `init_database`; idempotent (only adds missing entries)
- 🔧 **Fix 2 — `run_all_migrations` safety net** — same `INSERT OR IGNORE` step added to the `run_all_migrations` action, with a count-check first that surfaces "Sync venues จาก programs.location — เพิ่ม N สถานที่" in the migration messages when something was actually re-synced; this catches installations that imported programs before `venue_resolve_canonical()` existed (any pre-v16.0.0 ICS import path)
- 🛡️ **Test guarantee** — the v16.0.0 `testVenuesSeededFromLocations` (`assertGreaterThanOrEqual($distinct, $venueCount, ...)`) is now genuinely maintained as an invariant by both initialization paths; full suite **11,813 / 11,813** pass

**Files changed:**

- `setup.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none for new installs (the `init_database` path now self-syncs). For an existing DB that has the gap (e.g. `venues=0, distinct=2` from before this fix), run `setup.php` → "Run All Migrations" — the new safety-net step will sync the missing locations and report the count. Or run the one-liner: `php -r 'require "config.php"; $db=new PDO("sqlite:".DB_PATH); $db->exec("INSERT OR IGNORE INTO venues (name) SELECT DISTINCT location FROM programs WHERE location IS NOT NULL AND location != \"\""); invalidate_venue_query_cache();'`

## [16.0.9] - 2026-05-28

### My Timeline — flip bar label: user-local as primary, event-local in parens

v16.0.8 moved the Timeline's positioning math to user-local minutes so cross-TZ overlap detection works correctly, but the bar label was still showing the event-local time as the bold primary text with `(HH:MM local)` underneath. That created a small visual inconsistency: the bar sat on the axis row matching user-local 17:00 but the bold number inside the bar said `18:00` (the Taipei event-local time). This release flips the label priority so the bold number on each bar matches the user-local axis row it sits on, with the event-local time moved into parens beneath — consistent with how every axis-aligned chart elsewhere (Gantt on `index.php`) shows the primary axis-matching time first.

- 📊 **Primary = user-local** — the bold `.fav-tl-bar-time` line now reads the user-local HH:MM range (computed from the cached `p._uStart` / `p._uEnd` set in v16.0.8 by `_favProgMins(p)`); this is the time that matches the bar's vertical position on the chart
- 🔢 **Secondary (parens) = event-local** — when the program's event TZ differs from the browser TZ, a small italic `(18:00)` line is appended underneath the bold primary; the parens contains the event-local HH:MM range (already stored in `p.time` from the PHP renderer); no timezone name in the parens to keep the line compact — the lane's `🕐 Asia/Taipei` header chip already tells the user which TZ that parens number is in
- 🎯 **Same-TZ → unchanged** — when event TZ equals user TZ, only the primary time line is rendered (just like before) — no parens, no italic sub-line; the Timeline for same-TZ users is byte-identical to v16.0.8
- 🧰 **`_favFormatMin(min)`** — new tiny helper (`Math.floor / String.padStart`) to format minutes-since-midnight back to `HH:MM`; needed because the primary label is now derived from numeric minutes, not from the pre-formatted `p.start_iso` string
- 🎨 **CSS class rename** — `.fav-tl-bar-time-local` → `.fav-tl-bar-time-sub` to reflect that the secondary line is no longer always "local" (it's now "event-local" when the bar's primary became user-local); same italic / muted / small style, same DOM position
- 🪧 **Bar `title` (tooltip) updated** — native browser tooltip on hover now reads `${userTime} ${programTitle} [${eventTime} ${eventTz}]` for cross-TZ programs (e.g. `17:00–17:40 時空Astria [18:00–18:40 Asia/Taipei]`), so users can see both forms by hovering
- ✅ **Verified** — `lint OK`; no remaining references to the old `.fav-tl-bar-time-local` class anywhere; tests **11,813 / 11,813** pass

**Files changed:**

- `my.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — JS/CSS only.

## [16.0.8] - 2026-05-28

### My Upcoming Programs Timeline — align the time-axis to the user's local clock

The Timeline view on `/my/{slug}` (added in v15.8.0) was originally designed to visualise time overlap between programs the user follows. v16.0.5 added per-bar `(HH:MM local)` annotations and per-lane `🕐 Asia/Taipei` chips so users could see which timezone each lane was in, but the **time-axis itself** was still event-local: each lane's bars were positioned by the program's event-local minutes (`p.start_iso` = "HH:MM"). This made overlap detection wrong for mixed-TZ followed events — a Taipei 18:00 program and a Bangkok 17:00 program both happen at UTC 10:00 (i.e. truly overlap) but they appeared on different rows of the axis (row 18 vs row 17) so neither the overlap zone strip nor the date-header "🔴 มี event ทับซ้อน" badge fired. The whole point of the Timeline (real-time conflict detection) was undermined for cross-TZ schedules.

This release moves the **positioning math** to user-local minutes while keeping bar **labels** in event-local form (matching venue announcements). Visually: same-TZ users see no change; cross-TZ users see bars from different lanes now line up by real time, overlap zones fire correctly, and the existing `(HH:MM local)` annotation underneath each bar's bold event-local time matches the bar's actual row on the axis.

- 🌐 **`_favUserLocalMin(isoStr)`** — new helper: parses an ISO-8601-with-offset string (`2026-05-29T18:00:00+08:00`, already produced by the v16.0.3+ PHP renderer) into the browser's local minutes-since-midnight via `Intl.DateTimeFormat(... timeZone: userTz ... hourCycle: 'h23').formatToParts()`; handles the rare "24:00" return value for midnight; returns `null` on failure so callers can fall back to event-local minutes
- 🌐 **`_favProgMins(p)`** — caches user-local start/end minutes on the program object as `p._uStart` / `p._uEnd` after the first call, with graceful fallback to `_favTimeToMin(p.start_iso)` / `p.end_iso` when no ISO+offset is available (e.g. stale query-cache data from before v16.0.3); used by every site that previously called `_favTimeToMin(p.start_iso)` so all of: lane-sort key (first start time), `minHour` / `maxHour` axis range, bar `top` / `height` positioning, within-event overlap detection, cross-event overlap-zone computation now agree on the same minute value
- 📊 **Bar label vs position** — the bar still shows the bold event-local time (`p.start_iso` = "18:00") matching what the venue announces, with the `(17:00 local)` italic line underneath; only the bar's vertical **position** on the chart shifted to user-local row 17:00. The lane's `🕐 Asia/Taipei` header chip continues to show which TZ the bar's primary label is in
- 🎯 **No-op for same-TZ users** — when every followed event's timezone equals the browser's resolved TZ, `_favProgMins(p)` returns the same minutes as the previous `_favTimeToMin(p.start_iso)` call (since event-local = user-local), so the Timeline looks identical to v16.0.7
- ✅ **Verified** — `_favUserLocalMin` and `_favProgMins` defined and called in 6+ sites across the inline script; tests **11,813 / 11,813** pass

**Files changed:**

- `my.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — JS-only on top of v16.0.3's ISO-with-offset data shape. The `_favProgMins()` helper falls back to the previous event-local minutes for any program that lacks `start_full` / `end_full` (e.g. cached before v16.0.3), so the change is non-breaking for stale data.

## [16.0.7] - 2026-05-28

### Profile pages — cross-timezone local-time annotation on `/artist/{id}` and `/venue/{id}`

Final follow-up to the v16.0.2 → v16.0.6 timezone series. The artist profile page (`/artist/{id}`) and the venue profile page (`/venue/{id}`) both list programs grouped by event, with each event potentially in its own timezone — exactly the same multi-TZ situation as the `/my/{slug}` list view fixed in v16.0.4. Until this release, those two profile pages displayed the event-local time (which was correct since v16.0.2) but provided no hint of the user's local-time equivalent, so a Bangkok user opening the SSr Vol.68 Taipei artist saw `18:20` with no indication that it meant `17:20` on their own clock. The `/venues` portal lists venue cards with program *counts* (no time fields) and so needs no change.

- 🗃️ **PHP — SQL `e.timezone AS event_timezone`** — added to all three queries: artist's own programs, the artist's group programs (when the artist is a solo member of a group), and the venue's programs. Cache stores `event_timezone` automatically on the next refresh; existing cache files render without the annotation (graceful — old data simply doesn't trigger the chip) and refresh naturally on next program/venue write
- 🗃️ **PHP — `data-utc-start` / `data-utc-end` / `data-event-tz` on time cell** — `render_programs_table()` (artist.php) and `render_venue_programs()` (venue.php) compute UTC ms via `(new DateTime($p['start'], new DateTimeZone($evTz)))->getTimestamp() * 1000` and emit the three attributes on the second `<td class="prog-time">` (the time cell, not the date cell); `$evTz = $p['event_timezone'] ?: DEFAULT_TIMEZONE` so the literal `'Asia/Bangkok'` is never hardcoded
- 🌐 **JS — shared `annotateProfileTimes()` in `common.js`** — scans every `td.prog-time[data-event-tz][data-utc-start]`, skips rows where the cell's `data-event-tz` equals the browser's resolved TZ, and otherwise appends `<span class="prog-time-local">(17:20 local)</span>` (or range `(17:20–17:40 local)`) inside the cell; the function auto-runs on `DOMContentLoaded` (so both `artist.php` and `venue.php` get it for free since they both load `common.js`) and again on `appLangChange` (which strips the existing `.prog-time-local` spans and re-renders to refresh the "local" word per language); reuses the existing `tz.localTime` translation key — no new i18n keys
- 🎨 **CSS — `.prog-time-local`** — `styles/artist.css` (shared between `artist.php` and `venue.php`) gets a small italic, muted, block-level chip styled to fit underneath the existing tabular `.prog-time` cell without disrupting column widths
- 🚫 **`/venues` portal** — intentionally unchanged; the portal grid shows venue names + program counts + a "🌐 Online" badge for online platforms, but no program times, so cross-TZ annotation is not applicable
- 🧹 **Cache hygiene** — invalidated `query_artist_*.json` + `query_portal.json` + `query_venue_*.json` + `query_portal_venues.json` once so the new `event_timezone` field starts being cached from the next page hit; future writes invalidate naturally via the existing `invalidate_artist_query_cache()` / `invalidate_venue_query_cache()` hooks
- ✅ **Verified** — `/artist/445` (a member of the Taipei event SSr Vol.68) renders `data-event-tz="Asia/Taipei"` on each program's time cell; `/venue/2` (a Bangkok-event venue) correctly renders `data-event-tz="Asia/Bangkok"` (no annotation for Bangkok users since the TZ matches the browser); tests **11,813 / 11,813** pass

**Files changed:**

- `artist.php`
- `venue.php`
- `js/common.js`
- `styles/artist.css`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — PHP/JS/CSS only. The query caches were force-invalidated once during this release so the new `event_timezone` field becomes available on the next request; no schema change.

## [16.0.6] - 2026-05-28

### Event-page Gantt tooltip — show user-local time too

Companion to v16.0.5. The Gantt/Timeline view's program bars now show `(HH:MM local)` underneath the bold event-local time, but clicking a bar opens a detailed tooltip (program title, venue, time range, artists, description) and the time row in that tooltip still showed only event-local time. Users who relied on the tooltip to confirm "what time should I actually tune in" got the right answer for same-TZ events but lost the local-time hint they had on the bar itself.

- 🌐 **Tooltip time row gains local annotation** — `showEventTooltip()` in `js/common.js` now appends a small italic `(HH:MM local)` (or range `(HH:MM–HH:MM local)`) line beneath the existing event-local time when `window.EVENT_TIMEZONE` differs from the browser's resolved timezone; computed from the `data-utc-start` / `data-utc-end` UTC ms timestamps already attached to each bar in v16.0.5 (no new data attribute needed); class `.tooltip-time-local` with inline styling matching the per-bar `.gantt-program-time-local-v` style; reuses the existing `tz.localTime` translation key (no new i18n)
- 🎯 **Same-TZ → unchanged** — when the browser TZ equals the event TZ, the tooltip is identical to v16.0.5 (no extra line)
- ✅ **Verified** — `.tooltip-time-local` is created inside the existing `timeP` paragraph, so positioning logic in the tooltip is unaffected; tests **11,813 / 11,813** pass

**Files changed:**

- `js/common.js`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — JS-only enhancement on top of v16.0.5's data attributes.

## [16.0.5] - 2026-05-28

### Timezone follow-up — DEFAULT_TIMEZONE constant + Timeline/Gantt local-time annotation

Three small follow-ups to the v16.0.2 → v16.0.4 timezone work: (1) the v16.0.3/v16.0.4 additions in `my.php` / `my-feed.php` hardcoded the literal `'Asia/Bangkok'` as the per-program fallback timezone (the project already has a `DEFAULT_TIMEZONE` constant — added v4.0.0 — that is the canonical place to change the default); (2) the event-page Gantt/Timeline view (`venue_mode=multi` on `index.php`) showed each bar's event-local time correctly but never displayed the user-local equivalent the way the list view does, leaving Bangkok users looking at a Taipei Gantt page with no way to tell that `18:00` on the chart meant `17:00` on their own clock; (3) the same gap existed on `/my/{slug}` Timeline view added in v15.8.0 — bars showed event-local time but with no annotation, and the per-event lane header didn't indicate which timezone that lane was in.

- 🔧 **`DEFAULT_TIMEZONE` constant** — `my.php` (4 sites) and `my-feed.php` (2 sites) now reference `DEFAULT_TIMEZONE` instead of the literal `'Asia/Bangkok'`; SQL `COALESCE(e.timezone, 'Asia/Bangkok')` replaced by plain `e.timezone AS event_timezone` with PHP-side `$p['event_timezone'] ?: DEFAULT_TIMEZONE` fallback (cleaner: no literal duplicated in SQL, single source of truth in `config/app.php`)
- 🌐 **Event-page Gantt local-time annotation** — `renderGanttChart()` in `js/common.js` now emits `data-utc-start` / `data-utc-end` (UTC ms from each program's `start_ts` / `end_ts`) on every `.gantt-program-vertical` bar; new `annotateGanttLocalTime()` is called after `ganttView.innerHTML = renderGanttChart(...)` to inject a small italic `(HH:MM local)` span (class `.gantt-program-time-local-v`) next to the existing bold event-local time when `window.EVENT_TIMEZONE` differs from the browser's resolved timezone; `updateTimezoneLabels()` was extended to refresh the "local" label word on language switch (no new translation keys — the existing `tz.localTime` already handles TH / EN / JA)
- 🌐 **My Timeline cross-TZ annotation** — `renderFavTimelineDay()` in `my.php` was updated in two places: each `.fav-tl-bar` (the program bar) now emits `<div class="fav-tl-bar-time-local">(17:20 local)</div>` underneath the existing event-local `<div class="fav-tl-bar-time">18:20</div>` when the program's `event_tz` differs from the browser TZ; each `.fav-tl-event-header` (the per-event column header at the top of the timeline) now shows a small italic `· 🕐 Asia/Taipei` chip when that lane's TZ differs from the browser TZ — so users can tell at a glance both "what's this lane's clock?" (header) and "what would that be in my own clock?" (per-bar)
- 🎯 **Same TZ → unchanged UI** — when the browser TZ equals the event TZ (e.g. a Bangkok user looking at a Bangkok event), no annotations are added on either the Gantt or the My Timeline view; the UI is identical to v16.0.4 for the common case
- 🧰 **`_favUserTz()`** — small cached helper in `my.php` for the browser's resolved timezone (used per-bar during render and per-lane in the header); avoids calling `Intl.DateTimeFormat().resolvedOptions().timeZone` once per program/lane on every re-render
- ✅ **Verified** — `data-event-tz="Asia/Taipei"` propagates from PHP through to the Timeline bars; `.fav-tl-event-tz` chip and `.fav-tl-bar-time-local` annotation CSS present; Gantt template emits `data-utc-start`; `annotateGanttLocalTime` is defined and wired into the Gantt-view switch handler; tests **11,813 / 11,813** pass

**Files changed:**

- `my.php`
- `my-feed.php`
- `js/common.js`
- `styles/index.css`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — JS/PHP-only enhancement on top of v16.0.4's data shape. The `DEFAULT_TIMEZONE` constant is unchanged (`Asia/Bangkok`) so existing installs see identical defaults; sites that want a different default now have a single place to change it.

## [16.0.4] - 2026-05-28

### My Upcoming Programs: Cross-Timezone Local-Time Annotation

After v16.0.3 fixed the underlying timestamps, programs displayed event-local times correctly (Taipei 18:20 actually showed `18:20`), but a Bangkok-based user looking at the My Upcoming Programs page had no way to tell that `18:20` meant `17:20` in their own wall clock — the event page (`index.php`) already shows `(HH:MM local)` annotations next to each program time and a `🕐 Asia/Tokyo (Asia/Bangkok)` header badge, but the per-user / cross-event My Upcoming Programs page didn't have an equivalent. This release adds the missing annotation, with the extra challenge that a single page mixes programs from multiple event timezones (so the annotation has to be per-row, not per-page).

- 🌐 **Per-row `(HH:MM local)` annotation** — for every `.fav-program-row` whose `data-event-tz` differs from the browser's resolved timezone, JS appends a small italic `(17:20 local)` (or range `(17:20–17:40 local)`) line inside the existing `.fav-time` cell; user-local time computed from `data-start` / `data-end` (ISO-8601 with offset, added in v16.0.3) via `Date.toLocaleTimeString([], { timeZone: userTz })`, so the value is always the user's actual wall-clock time
- 🕐 **Per-row TZ chip** — when the event's timezone differs from the user's, a small `· 🕐 Asia/Taipei` chip is appended to the existing `.fav-prog-meta` line (next to event name / location / categories) so the user can see which TZ the event-local time is in without hovering or guessing
- 🗃️ **PHP data shape** — `$calPrograms[$date][]` now carries `start_full` (ISO-8601 with offset, e.g. `2026-05-29T18:20:00+08:00`), `end_full`, and `event_tz` per program, so the day modal's JS-generated rows can be annotated identically to the main list
- 🔄 **Re-annotation hooks** — `_favAnnotateTimezones()` is called from `DOMContentLoaded`, again after `openDayModal()` inserts new rows, and again after `changeLanguage()` (which strips the old `.fav-time-local` spans first because the literal "local" word is translated through the existing `tz.localTime` translation key — no new i18n keys needed)
- 🎯 **No-op when timezones match** — users in Asia/Bangkok viewing Bangkok events, or users in Asia/Taipei viewing Taipei events, see exactly the same UI as before; the annotation only appears for cross-TZ rows so the page stays uncluttered for the common case
- ✅ **Verified** — created a test slug following SSr Vol.68 (Taipei UTC+8); each row carries `data-event-tz="Asia/Taipei"` + ISO-with-offset timestamps; `.fav-time-local` and `.fav-tz-chip` CSS hooks present; tests **11,813 / 11,813** pass

**Files changed:**

- `my.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — JS-only enhancement on top of v16.0.3's data shape. Refresh `/my/{slug}` to see the new annotations.

## [16.0.3] - 2026-05-28

### Bug Fix — My Upcoming Programs + Personal ICS Feed Wrong Time Across Timezones

Companion to v16.0.2. The "My Upcoming Programs" page (`/my/{slug}`) and the personal ICS subscription feed (`/my/{slug}/feed`) had two separate timezone bugs that became visible once an event in a non-Bangkok timezone was added: the "Now Playing" highlight on `/my/{slug}` was off by the offset between the event's timezone and the browser's timezone (a Bangkok user following SSr Vol.68 in Taipei would see the NOW badge stay on for an hour after the program ended), and the personal ICS feed wrote UTC timestamps that assumed Bangkok-local stored time, so calendar apps subscribed to the feed put Taipei programs at the wrong UTC instant (Taipei 18:00 was emitted as `20260529T112000Z` = Bangkok 18:20 = Taipei 19:20, instead of the correct `20260529T102000Z`).

Critically, this page aggregates programs from **multiple followed events that may each have their own timezone**, so a single page-wide default is not enough — the fix must look up each program's event timezone individually.

- 🐛 **`my.php` "Now Playing" highlight** — `data-start` / `data-end` on `.fav-program-row` were emitted as the raw stored string `2026-05-29T18:00:00`, with no timezone marker; JS `new Date(...)` then parsed them as **browser-local** time, so a Bangkok browser interpreted Taipei 18:00 as Bangkok 18:00 (which is UTC 11:00, not the actual UTC 10:00) → "now" comparison was off by the TZ offset
- 🐛 **`my-feed.php` DTSTART/DTEND** — used `gmdate('Ymd\THis\Z', strtotime($p['start']))` which `strtotime`-interprets the stored string in **PHP's default Bangkok timezone**, producing UTC timestamps offset by the event-TZ-vs-Bangkok difference; calendar apps then displayed every non-Bangkok program at the wrong time
- 🔧 **Per-program timezone** — both `my.php` and `my-feed.php` SQL now `SELECT … COALESCE(e.timezone, 'Asia/Bangkok') AS event_timezone` so each row carries its own event's TZ
- 🔧 **`my.php` emits ISO-8601 with explicit offset** — `data-start="2026-05-29T18:20:00+08:00"` for Taipei programs (PHP `(new DateTime($p['start'], $eventTz))->format('c')`); JS `new Date(row.dataset.start)` parses these to the correct UTC instant regardless of the browser's timezone, so the Now Playing comparison works for followed events in any TZ. Removed the now-unnecessary `.replace(' ', 'T')` since the ISO format always uses `T`
- 🔧 **`my-feed.php` computes UTC via event TZ** — `gmdate('Ymd\THis\Z', (new DateTime($p['start'], $eventTz))->getTimestamp())`; Taipei 18:20 → `20260529T102000Z` (correct) instead of the previous Bangkok-interpreted `20260529T112000Z`
- ✅ **Verified end-to-end** — created a test slug following an SSr Vol.68 (Taipei, UTC+8) artist (`時空Astria` #445); confirmed `/my/{slug}` emits `data-start="2026-05-29T18:20:00+08:00"` and `/my/{slug}/feed` emits `DTSTART:20260529T102000Z` (= Taipei 18:20)
- 🚫 **Out of scope (already correct)** — the displayed time on each row (`<div class="fav-time">18:20</div>`) is read from `substr($p['start_date'], 11, 5)`, which is the event-local stored string — TZ-safe by construction. The Timeline view (`v15.8.0`) uses `start_iso` / `end_iso` for positioning on a per-day axis; the axis is event-local minutes, which displays each program at the hour it actually starts in its own venue's wall clock (the intuitive view for "what time should I tune in"). The mini calendar / day-modal lists are also `substr`-based — display-only, TZ-safe

**Files changed:**

- `my.php`
- `my-feed.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — display-only fix on `my.php`; for `my-feed.php`, subscribed calendar apps refresh on their next pull (Apple ≤ 1 hour, Google ≤ 24 hours, Outlook ≤ 24 hours) and will then show the corrected times. Users with Outlook Web who want the fix immediately should remove and re-subscribe to the personal feed.

## [16.0.2] - 2026-05-28

### Bug Fix — Event Page Showed Wrong Time for Non-Bangkok Timezones

Events whose timezone differs from the server-side PHP default (`Asia/Bangkok`, set in `config/app.php`) were rendering program times offset by the timezone difference on the event page. Example: SSr Vol.68 is `Asia/Taipei` (UTC+8); its stored start `2026-05-29T18:00:00` is Taipei-local, but the list view showed `17:00` (1 hour off — Bangkok). Tokyo events (UTC+9) were off by 2 hours, Pacific events by more. The Gantt and Calendar views, the ICS feed, the ICS export, and the venue/artist profile tables were already correct — the bug was isolated to the list view rendering on `index.php`.

- 🐛 **Root cause** — `$start_ts` / `$end_ts` were correctly built as UTC timestamps by `(new DateTime($event['start'], $eventTzObj))->getTimestamp()`, but the four downstream `date(...)` calls that produced the visible time (`data-start` / `data-end`) and the date headers / date-jump bar used PHP's default timezone instead of the event timezone, so the timestamp was re-formatted as Bangkok-local time
- 🔧 **Fix** — added an `$evFmt($ts, $fmt)` closure that wraps the UTC timestamp in a `DateTime` set to `$eventTzObj` and formats from there; replaced 10 `date()` calls in `index.php` (program-time `data-start` / `data-end`, day grouping key, day-header `d`/`m`/`Y`/`w`, date-jump-bar `d`/`m`/`w`)
- ✅ **Verified end-to-end** — SSr Vol.68 (`Asia/Taipei`) now renders `data-start="18:00"` (matches stored 18:00 Taipei local) instead of the previous incorrect `17:00`; date headers render the Taipei date even when the event spans a Bangkok day boundary
- 🚫 **Out of scope (already correct)** — `feed.php` and `export.php` use `(new DateTime($event['start'], $tz))->format(...)` (DateTime keeps its own TZ on `format()`), so `DTSTART;TZID=Asia/Taipei:20260529T180000` was already correct. `image.php` PNG export groups by `date('Y-m-d', strtotime($p['start']))` which roundtrips in the same default TZ and preserves the stored date string. The calendar view (`venue_mode=calendar`) and Gantt view use `ev.start.substring(...)` directly on the event-local stored string — also TZ-safe

**Files changed:**

- `index.php`
- `service-worker.js`
- `config/app.php`
- `SETUP.md`
- `API.md`
- `PROJECT-STRUCTURE.md`
- `INSTALLATION.md`
- `TESTING.md`
- `CHANGELOG.md`

> **Migration:** none — display-only fix. Existing `programs.start` / `programs.end` data is unchanged (it was already correct event-local text); the bug was purely in how `index.php` rendered those values. Refresh the page to see the corrected time.

## [16.0.1] - 2026-05-28

### Venue: Hide Online Platforms from Portal

Online platforms (YouTube, Instagram Live, X Spaces) are programs' "locations" technically, but they are not physical venues and only cluttered the `/venues` portal grid. v16.0.0 had no way to distinguish them; admins were either stuck with them in the listing or had to delete and lose the `programs.location` link. This release adds an opt-in `is_online` flag so online platforms stay first-class venues (programs still link to `/venue/{id}`, autocomplete still suggests them) but get hidden from the portal grid.

- 🗃️ **Schema** — `venues.is_online INTEGER DEFAULT 0` added via idempotent `ALTER TABLE` in `tools/migrate-add-venues-table.php` + matching `CREATE TABLE` / `ALTER TABLE` in `setup.php` (`init_database` + `run_all_migrations`); existing rows default to 0
- 🌱 **Auto-seed** — the migration auto-flags `YouTube`, `Instagram Live`, `X Spaces` as `is_online = 1` so the portal is clean on first re-run; idempotent (only updates rows that are currently 0)
- 🌐 **Translation note (TH)** — all user-visible Thai labels that referred to a venue as "เวที" (stage) are now "สถานที่" (place) — both in the v16.0.0 venue UI and in pre-existing strings (`filter.venue`, `table.venue`, `modal.venue`, `gantt.venue`, `artist.colVenue`, `programs.allVenues`, `bulkEdit.venueHint`, `section2.filter2.*`, `section3.calendar.step1`, `section7.feature1`, `filter.noVenue`, etc. + `admin/index.php` / `index.php` / `how-to-use.php` / `image.php` / `admin/help.php` HTML fallbacks). Affects display only (no logic / no schema change); existing translations.js test counts unchanged
- 🛠️ **Admin** — venue Add/Edit modal gets a `🌐 Online platform (hidden from /venues portal)` checkbox + hint; venue list row shows a blue `🌐 Online` badge next to the name when flagged; `venues_create` / `venues_update` accept `is_online`, `venues_get` / `venues_list` return it (all guarded with a `PRAGMA table_info` probe for backward-compat with un-migrated installs)
- 🌐 **Portal `/venues`** — query filters `WHERE COALESCE(v.is_online, 0) = 0`; online venues are completely hidden from the grid
- 🌐 **`/venue/{id}`** — still works for online venues (accessible via the program-cell link); renders the placeholder icon as `🌐` instead of `🏛️` and shows the `🌐 Online Platform` badge in the header. `venue.badgeOnline` translation key added in TH / EN / JA
- 🧪 **`VenueTest`** — 7 new tests (column exists + defaults to 0; YouTube/Instagram Live/X Spaces seeded; portal SQL filter; admin API binds `:is_online`; venue page renders badge; 3 languages have `venue.badgeOnline`); full suite **11,813 / 11,813** passes 100%

**Files changed:**

- `tools/migrate-add-venues-table.php` — added idempotent ALTER + online seed step
- `setup.php` — venues CREATE TABLE includes `is_online` + `run_all_migrations` ALTER fallback
- `admin/api.php` — `createVenue` / `updateVenue` / `getVenue` / `listVenues` handle `is_online` (with `PRAGMA` probe)
- `admin/index.php` — `is_online` checkbox in venue modal, JS load/save, 🌐 Online badge in venue list
- `admin/js/admin-i18n.js` — `venues.fieldIsOnline`, `venues.isOnlineHint`, `venues.onlineBadge` (TH + EN)
- `venues.php` — portal query filters online venues
- `venue.php` — header icon + badge react to `is_online`; SELECT picks `is_online`
- `js/translations.js` — `venue.badgeOnline` (TH / EN / JA) + system-wide "เวที" → "สถานที่" TH copy
- `index.php`, `artist.php`, `how-to-use.php`, `image.php`, `admin/help.php` — Thai labels updated to "สถานที่"
- `tests/VenueTest.php` — 7 new tests
- `service-worker.js`, `config/app.php` — bumped to v16.0.1 (via `sync-sw-version.php`)
- `CHANGELOG.md` — release notes

> **Migration:** re-run `php tools/migrate-add-venues-table.php` (idempotent — adds the column if missing and flags the 3 known online platforms). Existing custom online venues can be flagged via Admin › Venues › Edit › 🌐 checkbox.

## [16.0.0] - 2026-05-27

### Venue / Location Dedup System

Introduced a canonical layer for program locations, mirroring the Artist Reuse architecture. Previously `programs.location` was free text and its suggestions came from `SELECT DISTINCT location`, so any spelling/spacing/case difference created a brand-new "venue" — the venue list grew duplicated and the homepage venue filter became cluttered. The new system keeps `location` as canonical text (no `venue_id` FK — lightweight by design, so the ~30 files that read/filter location are untouched) but adds a `venues` table + `venue_variants` aliases that drive autocomplete, auto-canonicalisation on save/import, an admin Merge tool, and public venue profile pages.

- 🗃️ **Schema** — new `venues` (`id`, `name UNIQUE`, `description`, `map_url`, timestamps) + `venue_variants` (`venue_id` FK ON DELETE CASCADE, `variant`, `UNIQUE(venue_id, variant)`); indexes `idx_venues_name`, `idx_venue_variants_venue_id`
- 🔁 **`venue_resolve_canonical($db, $raw, $allowCreate)`** in `admin/api.php` — mirror of `syncProgramArtists()`: exact name match → variant lookup → auto-create new venue (admin/agent only); returns the canonical string. Called before binding `programs.location` in `createProgram()`, `updateProgram()`, `bulkUpdatePrograms()`, and `confirmIcsImport()` so known aliases auto-normalise and new venues self-register
- 🌱 **Seeding** — `tools/migrate-add-venues-table.php` seeds `venues` from existing `DISTINCT programs.location` and seeds `venue_variants` from the historical dedup mapping (the A–H groups merged earlier, e.g. `Phenix Pratunam` → `Lot of Live, 3rd Fl. Phenix Pratunam`), so re-imports of old names auto-canonicalise instead of re-duplicating
- 🛠️ **Admin Venues tab** (admin/agent) — list with program/variant counts, search, sort, pagination; Add/Edit modal (name/description/map_url; rename rewrites `programs.location`); Variants modal (add/remove aliases); bulk-select + **Merge** modal (pick canonical target, others become variants, all matching programs + variants rewritten to the target name); program-form and bulk-edit venue datalists now pull canonical names from `venues_list` (graceful fallback to `programs_venues`)
- 🌐 **Public `venue.php` → `/venue/{id}`** — venue profile listing upcoming programs grouped by event (matched by `location = name OR location IN (variants)`), description + map link, alternate-names section; query-cached as `query_venue_{id}.json`; 404 on unknown venue
- 🌐 **Public `venues.php` → `/venues`** — venue portal grid with program counts + real-time search; cached as `query_portal_venues.json`; `🏛️ สถานที่` nav link added to the homepage listing header
- 🔗 **Clickable venue cells** — `index.php` builds `$venueMeta[location → id]` (name + variants, cached in `query_event_{id}.json`) and renders the venue cell as a link to `/venue/{id}`
- 🔌 **Admin API** — `venues_list`, `venues_get`, `venues_create`, `venues_update`, `venues_delete`, `venues_autocomplete`, `venues_variants_list/create/delete`, `venues_merge`; venue CRUD/merge are admin/agent; organizer allowlist gains read-only `venues_autocomplete` + `venues_list`
- 🔄 **Cache** — new `invalidate_venue_query_cache()` (`query_venue_*.json` + `query_portal_venues.json`); program writes + venue writes invalidate it; merge/rename also invalidate data-version + feed caches (programs.location changes); both patterns added to `invalidate_all_caches()`
- 🧪 **`VenueTest`** — 27 new tests (schema, migration idempotency, exact/variant resolve, cascade delete, dispatch/allowlist/source hooks, cache, public-page matching, `.htaccess` route, i18n keys, dedup-seed verification); **11,806 tests total**, 100% pass
- 🧪 Updated `FeedTest::testAdminApiHasSixFeedCacheInvalidations` → expects 8 `invalidate_feed_cache()` calls (6 program ops + 2 venue ops that rewrite `programs.location`)
- 🚫 **Out of scope** — no `venue_id` FK on programs; export/feed/image/api location queries unchanged; venue cover images; ICS feed per venue

**Files changed:**

- `tools/migrate-add-venues-table.php` — new (create tables + seed venues + seed dedup variants; idempotent)
- `tools/dedup-locations.sql` — new (one-off SQL used to consolidate the existing duplicate locations, A–H groups)
- `venue.php` — new (public venue profile page)
- `venues.php` — new (public venue portal)
- `tests/VenueTest.php` — new (27 tests)
- `admin/api.php` — `venue_resolve_canonical()` + 10 venue API functions + dispatch cases + organizer allowlist + venue-cache invalidation on program/venue writes + canonicalise location on create/update/bulk/import
- `functions/cache.php` — `invalidate_venue_query_cache()` + venue patterns in `invalidate_all_caches()`
- `admin/index.php` — Venues tab (desktop + mobile), section table + toolbars, Add/Edit/Variants/Merge modals, venue tab JS, datalists repointed to canonical names
- `admin/js/admin-i18n.js` — venue keys (TH + EN)
- `index.php` — `$venueMeta` build + cache + clickable venue cell + `🏛️ สถานที่` nav link
- `js/translations.js` — `nav.venues`, `venue.*`, `venuePortal.*` keys (TH / EN / JA)
- `.htaccess` — `RewriteRule ^venue/([0-9]+)` route
- `setup.php` — venues tables in `init_database` + `run_all_migrations` + `$hasVenuesTables` migration check
- `tests/run-tests.php` — registered `VenueTest`
- `tests/FeedTest.php` — updated feed-cache invalidation count to 8
- `service-worker.js` — `CACHE_VERSION` bumped to v16.0.0 (automatic via `sync-sw-version.php`)
- `config/app.php` — `APP_VERSION` bumped to v16.0.0
- `SETUP.md`, `API.md`, `PROJECT-STRUCTURE.md`, `INSTALLATION.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md` — version references updated (automatic via `tools/update-version.php`)
- `CHANGELOG.md` — added the v16.0.0 release entry (manual)
- `README.md` — added the v16.0.0 row to the Feature Timeline table (manual)

> **Migration:** run `php tools/migrate-add-venues-table.php` (or `setup.php` → Run All Migrations) on existing installs to create + seed the venues tables. No change to `programs.location` data is required; the homepage/feeds/exports keep working unchanged.

## [15.8.0] - 2026-05-22

### Timeline View — My Upcoming Programs

Added a per-day Gantt-style Timeline view on `/my/{slug}` alongside the existing List view. The Y-axis is **events** (each followed event becomes a horizontal lane), the X-axis is time (auto-fit to that day's min/max hour). Previously, when a single day had ≥2 followed events with overlapping program times, users had to manually compare timestamps row-by-row to spot the overlap — the Timeline now exposes it instantly through bar alignment.

- 📊 **Toggle List / Timeline** — two-button segmented control (📋 List / 📊 Timeline) above "📅 Upcoming Programs"; selection persisted in `localStorage` key `fav_view_mode`; preferred view restored automatically on next page load; toggle uses `is-active` class with subtle `box-shadow` reminiscent of an iOS segmented control
- 🗓️ **Per-day Gantt block** — each date with programs renders as its own block (`.fav-timeline-day`): date header (`.fav-tl-date-header`) followed by chart container (`.fav-tl-chart`) with sticky header row + horizontal scroll for days with many events; time axis column on the left (50 px mobile / 60 px desktop) shows `HH:00` hour ticks; each event occupies one column (140 px mobile / 180 px desktop); slot height 60 px mobile / 70 px desktop
- ⏱️ **Time range auto-fit** — `minHour` / `maxHour` computed from that day's programs only (not a fixed 0–24); minimizes whitespace; programs where `end ≤ start` get a minimum 30-minute display window to avoid 0-height bars
- 🎨 **Event color coding** — bars and event headers use the existing `.fav-ec-{0..5}` palette (pink / blue / green / amber / purple / teal) mapped via the `EVENT_COLOR_MAP[event_slug]` JS const that has existed since v4.0.3; CSS overrides set border-color + background tint per code; bars have `border: 1px solid` matching the event color over a white background for readability
- 🔴 **Cross-event overlap visualization** — when programs from ≥2 different events occupy the same time range:
  1. **Vertical overlap zone strip** — translucent red band (`rgba(229,57,53,.10)` + dashed top / bottom borders) covering the overlap time range, rendered in every affected event column at `z-index:1` (below bars at `z-index:2`); `pointer-events:none` so it doesn't intercept bar clicks
  2. **Date header badge** — red badge appended next to the date label: `🔴 มี event ทับซ้อน` / `🔴 Overlapping events` / `🔴 重複あり`
  3. **Bar layout itself** — since each event has its own column, bars at the same X-position but in different rows make the overlap visible from vertical alignment alone
- 🧮 **Overlap detection algorithms** — two separate functions:
  - `_favDetectWithinEventOverlaps(progs)` — pair-wise sweep within the same event (in case multiple programs of one event overlap, which is rare but possible); assigns dynamic `stackIndex` / `stackTotal` to split bar width equally (pattern ported from `js/common.js::detectOverlaps()`)
  - `_favFindCrossEventOverlaps(programs)` — sort-by-start sweep-line; collects zones `{startMin, endMin, slugs[]}` whenever two programs overlap AND have different `event_slug`; consumed by the overlap-strip renderer above
- 🖱️ **Click-to-detail** — clicking a bar opens the existing `openDayModal(date)` which shows that day's full program list with event / location / categories / stream URL — no separate detail popup needed
- 🌐 **i18n re-render on language switch** — `changeLanguage()` IIFE patch calls `renderFavTimeline()` again when the timeline is currently rendered (`_favTimelineRendered === true`); event names are static text, but the "Time" axis label, date-format month names, and overlap badge text all need to update on language switch
- 🗃️ **PHP data shape** — `$calPrograms[$date][]` now includes two new keys: `start_iso` (raw `HH:MM`) and `end_iso` (`HH:MM`, or falls back to `start_iso` when end is empty / `00:00`); existing fields (`time`, `title`, etc.) unchanged; no DB schema migration required
- 🌐 **New translation keys** — `fav.view.list`, `fav.view.timeline`, `fav.timeline.overlapHint` across TH / EN / JA (9 entries in `js/translations.js`)
- 🚫 **Out of scope** — the mini calendar's day modal (still list view); `/my-favorites/{slug}` page (no program data to render); ICS feed (unrelated to view mode)

**Files changed:**

- `my.php` — added `start_iso` + `end_iso` to `$calPrograms` shape; ~140 lines of new CSS for the view toggle + timeline (`.fav-section-header`, `.fav-view-toggle`, `.fav-vt-btn`, `.fav-timeline-day`, `.fav-tl-*`, event color overrides, `≤768px` media query); wrapped the Upcoming Programs section with `.fav-section-header` + `#favListView` / `#favTimelineView` containers; ~150 lines of new JS: `setFavView()`, `renderFavTimeline()`, `renderFavTimelineDay()`, `formatFavTimelineDates()`, `_favTimeToMin()`, `_favDetectWithinEventOverlaps()`, `_favFindCrossEventOverlaps()`, `openFavTimelineBar()`; patched the `changeLanguage()` IIFE + `DOMContentLoaded` to restore view mode
- `js/translations.js` — added `fav.view.list`, `fav.view.timeline`, `fav.timeline.overlapHint` to the TH / EN / JA blocks (9 entries total)
- `service-worker.js` — `CACHE_VERSION` bumped to v15.8.0 (automatic via `sync-sw-version.php`)
- `config/app.php` — `APP_VERSION` bumped to v15.8.0
- `SETUP.md`, `API.md`, `PROJECT-STRUCTURE.md`, `INSTALLATION.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md` — version references updated (automatic via `tools/update-version.php`)
- `CHANGELOG.md` — added the v15.8.0 release entry (manual)
- `README.md` — added the v15.8.0 row to the Feature Timeline table (manual)
- `WORKFLOW.md` — updated `Current version`, added v15.8.0 to Recent Release Notes, updated `Last Updated` (manual)

> **Manual smoke test:** open `/my/{slug}` → click **📊 Timeline** → list hides, timeline shows; check `localStorage.fav_view_mode === 'timeline'` in DevTools; reload the page → timeline view is restored; switch language TH ↔ EN ↔ JA → "Time" axis label, date headers, and overlap badge re-render; test an overlap case (two followed artists from different events with programs at overlapping times) → two bars in two columns + vertical red zone + 🔴 badge in the date header; click a bar → day modal opens.
>
> **Migration:** no DB schema migration; existing PWA users get the feature automatically on next service-worker revalidation (the `Cache-Control: no-cache, no-store, must-revalidate` headers on `service-worker.js` at `.htaccess:82-85` force the browser to re-fetch it).

## [15.7.0] - 2026-05-21

### PWA Offline Cache

Added a `fetch` handler with cache strategies to `service-worker.js` (previously push + lifecycle only). Users who open the PWA on poor signal — at the venue, on transit, on captive Wi-Fi — can now reload / reopen core pages they've already visited online; unvisited URLs serve a self-contained `offline.html` fallback.

- 🌐 **Cache strategy matrix** — six route types with distinct behavior:
  - **Static assets** (CSS / JS / icons / fonts / images, regex `/\.(css|js|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|otf)(\?.*)?$/i`) → cache-first, versioned (`app-static-v{VER}`)
  - **Visited HTML pages** → network-first with **3-second timeout** + cache fallback + `offline.html` last resort (`app-pages-v{VER}`)
  - **Public JSON API** (root `api.php` only) → stale-while-revalidate with `ETag` / `If-None-Match` conditional revalidation; on `304` the cached body is preserved and only its `X-SW-Cached-At` timestamp refreshes — never overwritten with the empty `304` response (`app-api-v{VER}`)
  - **User / private / write APIs + feeds + admin / setup / tools** → network-only (bypass): `api/favorites.php`, `api/push.php`, `api/request.php`, `api/event-request.php`, `api/telegram.php` (and their `/api/...` clean-URL variants), `feed.php`, `my-feed.php`, `/admin/`, `/setup.php`, `/tools/`, `service-worker.js` itself, `sync-sw-version.php`
  - **Cross-origin** (AdSense, GA, jsQR / Cropper / html2canvas CDNs) → bypass entirely
  - **Non-GET requests** → bypass entirely
- 🔄 **Freshness controls** — `MAX_API_CACHE_AGE_MS = 7 × 24 × 60 × 60 × 1000` ms; API cache entries older than 7 days are treated as a cache miss so users don't see misleadingly stale data (e.g. last month's calendar) when offline. `NETWORK_TIMEOUT_MS = 3000` ms protects against captive Wi-Fi and weak signal: the HTML strategy uses `Promise.race([fetch, setTimeout])` to fall back to cache when the network is too slow to respond. Every `cache.put` injects an `X-SW-Cached-At: <ms>` response header so freshness can be evaluated on read.
- 🗑️ **Activate cleanup** — `caches.keys()` iterates all cache names; any name starting with `app-` that isn't in `VALID_CACHES` for the current version is deleted. When `APP_VERSION` bumps, old static / page / API caches all clear automatically and the user re-fetches everything on next online use.
- 📦 **`PRECACHE_ASSETS`** — 11 assets cached on install: `offline.html`, `manifest.json`, three icons (`icon/icon-72.png`, `icon/icon-192.png`, `icon/icon-512.png`), four stylesheets (`styles/common.css`, `index.css`, `artist.css`, `portal.css`), and two scripts (`js/common.js`, `js/translations.js`) — all version-qualified with `?v=` query strings. Resolved with `new URL(p, self.registration.scope)` so subdir installs (e.g. `https://host/stage-idol-calendar/`) work correctly. Theme CSS files and uploaded images are runtime-cached when a visited page requests them, since the active theme and uploads are deployment / data dependent and can't be enumerated at install time.
- 🌸 **`offline.html`** (new, root level) — fully self-contained: inline `<style>` with sakura gradient pink card layout (no external CSS), inline `<script>` that picks language from `localStorage.lang` → `<html lang>` → `navigator.language` (TH / EN / JA). "🔄 ลองอีกครั้ง / Try again / 再試行" button calls `location.reload()`; "🏠 กลับหน้าแรก / Go home / ホームへ" button derives the deployment base from `location.pathname` by stripping known clean route prefixes (`/my/`, `/my-favorites/`, `/artist/`, `/event/`, `/artists`, `/connect`, `/contact`, `/past-events`, `/credits`, `/how-to-use`) — subdir-safe; no `.htaccess` rule needed (served as a plain static file).
- 🔧 **`sync-sw-version.php`** (new, root level) — dedicated CLI-only sync script that reads `APP_VERSION` from `config/app.php` and updates the `CACHE_VERSION` constant in `service-worker.js`. Idempotent (exits 0 with "already at v…" when the version matches); fail-loud (writes to STDERR + exits 1 when the file is missing or the pattern can't be matched); does **not** modify `tools/update-version.php`. The workflow is two manual steps: `php tools/update-version.php X.Y.Z` first, then `php sync-sw-version.php` to invalidate PWA caches.
- 🛡️ **Defense-in-depth web access blocking** — `sync-sw-version.php` has a `php_sapi_name() !== 'cli'` guard that returns HTTP 403 if accessed via the web, **and** `.htaccess` adds a `<Files "sync-sw-version.php">` block with `Require all denied` (Apache 2.4) plus an `Order deny,allow / Deny from all` 2.2 fallback so the script is denied at the Apache level before PHP ever runs.
- 🧪 **`PwaOfflineTest`** — 59 new tests added (cumulative suite total: 985). Coverage includes: `service-worker.js` source checks (CACHE_VERSION matches APP_VERSION, cache constants, PRECACHE_ASSETS, MAX_API_CACHE_AGE_MS / NETWORK_TIMEOUT_MS constants, install / activate / fetch handlers, network-only routes for all 5 sensitive APIs + feeds + admin / setup / tools + SW self + sync-sw-version, SWR helper for api.php, `cachePutWithTimestamp` + `X-SW-Cached-At` injection, `isCacheFresh` age check, ETag / `If-None-Match` revalidation, 304-doesn't-overwrite-body invariant, `Promise.race` timeout, `getOfflineUrl` from `self.registration.scope`, static asset regex), `offline.html` checks (exists, has DOCTYPE + title, contains no `<?php` / `<?=` tags, has Thai + English + Japanese text, has inline `<style>`, has no external CSS `<link>` or `<script src=...>`, has `location.reload` handler), `sync-sw-version.php` checks (exists at root, CLI guard via `php_sapi_name`, `http_response_code(403)`, requires `config/app.php`, uses `preg_match` on CACHE_VERSION, idempotent "already at" early-return), `.htaccess` checks (has `<Files "sync-sw-version.php">` block, Apache 2.4 `Require all denied`, Apache 2.2 `Deny from all` fallback in the same block), and a regression guard that `tools/update-version.php` does **not** reference `service-worker.js` (force-keeps the SW sync logic in its dedicated script). Push, notificationclick, and pushsubscriptionchange handler preservation are verified.
- 🚫 **Out of scope for v1** — iOS-specific `apple-touch-icon` link / `apple-mobile-web-app-title`; manifest `shortcuts` / `screenshots` arrays; Background Sync API for offline favorites mutations; push notification offline queue; client-driven precache of dynamic URLs (`/my/{slug}`, `/artist/{id}`) that the SW can't discover at install time without a postMessage handshake from the page.

**Files changed:**

- `service-worker.js` — rewrote: added `CACHE_VERSION` + 3 cache name constants, `VALID_CACHES` whitelist, `PRECACHE_ASSETS` array, `MAX_API_CACHE_AGE_MS` + `NETWORK_TIMEOUT_MS` constants, `NETWORK_ONLY_PATHS` array, 7 helpers (`getOfflineUrl`, `isStaticAsset`, `isNetworkOnly`, `isPublicApi`, `isHtmlRequest`, `cachePutWithTimestamp`, `isCacheFresh`, `refreshCacheTimestamp`, `revalidateApi`, `networkFirstWithTimeout`, `cacheFirstStatic`, `staleWhileRevalidate`), install handler with `cache.addAll`, activate handler with stale-cache cleanup, fetch handler with 6-stage routing. Push / notificationclick / pushsubscriptionchange handlers preserved unchanged.
- `offline.html` — new file at root; self-contained HTML / CSS / JS; 3 languages; no external dependencies
- `sync-sw-version.php` — new CLI-only sync script at root; idempotent; fail-loud
- `.htaccess` — added `<Files "sync-sw-version.php">` block with Apache 2.4 + 2.2 fallback
- `tests/PwaOfflineTest.php` — new test suite, 59 unique tests
- `tests/run-tests.php` — registered `PwaOfflineTest` (suite #25, cumulative tests: 985)
- `config/app.php` — version bump to v15.7.0
- `README.md`, `SETUP.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SECURITY.md`, `ICS_FORMAT.md`, `WORKFLOW.md` — version references updated

> **Migration:** no DB schema migration required. Existing PWA users get the new service worker automatically on next revalidation (the `Cache-Control: no-cache, no-store, must-revalidate` headers on `service-worker.js` at `.htaccess:82-85` force the browser to refetch it on every load).
>
> **Workflow note:** version bumps now require two manual CLI steps — `php tools/update-version.php X.Y.Z` to update `config/app.php` + `.md` files, then `php sync-sw-version.php` to update `CACHE_VERSION` in `service-worker.js`. The split keeps `tools/update-version.php` focused on docs / config while the SW sync handles runtime cache invalidation separately.

## [15.6.1] - 2026-05-21

### Bug Fix — Duplicate Telegram Notifications (Race Condition)

Fixes duplicate Telegram notifications caused by a read-clobber race between the Telegram cron and the Web Push cron when both run on the same 6-minute interval.

- 🐛 **Root cause — read-clobber race between `cron/send-telegram-notifications.php` and `cron/send-web-push-notifications.php`**:
  1. **T+0**: Both crons `fopen + LOCK_SH → read → unlock` simultaneously — each gets an in-memory snapshot of `$favData` that does **not** yet contain `telegram_notified[X]` for the current program
  2. **T+0.5–1.5s**: Both crons make HTTP calls (Telegram Bot API / Apple Web Push) — no file lock held during this window
  3. **T+1.5s**: Telegram cron acquires LOCK_EX, truncates, writes the **entire** `$favData` including `telegram_notified[X] = T+1.5s`
  4. **T+1.5–2.0s**: Web Push cron acquires LOCK_EX, truncates, writes its **own** `$favData` (still the T+0 snapshot lacking `telegram_notified[X]`) on top — Telegram cron's update is **clobbered**
  5. **T+360s (next cron tick)**: Telegram cron reads the file → no `telegram_notified[X]` → `telegram_should_notify()` returns `true` → **duplicate notification sent**
- ✅ **Fix — Read-Modify-Write under LOCK_EX**: Replaced the write-back step in both crons so each one re-reads the latest file content inside the exclusive lock and overwrites **only its own keys** instead of writing the entire `$favData`:
  - `cron/send-telegram-notifications.php` (in `process_favorites_file()`) — re-reads disk into `$latest`, then sets `$latest['telegram_notified'] = $telegramNotified` and (if the daily summary was sent this run) `$latest['telegram_summary_date'] = $favData['telegram_summary_date']`, then runs `telegram_cleanup_old_notifications($latest)` inside the lock window before writing back. The prior code ran cleanup on `$favData` and immediately discarded the result by overwriting `$favData['telegram_notified']` with the local — corrected as part of this fix.
  - `cron/send-web-push-notifications.php` (in `wp_process_favorites_file()`) — re-reads disk into `$latest`, then sets `$latest['push_subscriptions'] = $subs` (which already contains expired-subscription pruning + 7-day cleanup applied earlier), then writes back.
  - Fallback: if `json_decode($latestRaw, true)` returns a non-array (empty/corrupt file), the in-memory `$favData` is used as a fallback so the run still records its update.
- 🛡️ **Defense-in-depth side benefit**: This pattern also protects against web-request-vs-cron races. If `api/favorites.php?action=add/remove` or `api/push.php?action=subscribe/unsubscribe` writes to the favorites file via `fav_write()` (atomic tmp+rename) while a cron is running, the cron's final write now picks up the web change instead of clobbering it (and vice versa for the cron-specific keys).

**Files changed:**

- `cron/send-telegram-notifications.php` — read-modify-write under LOCK_EX in `process_favorites_file()` write-back step; `telegram_cleanup_old_notifications()` moved inside the lock block where it operates on the merged state
- `cron/send-web-push-notifications.php` — read-modify-write under LOCK_EX in `wp_process_favorites_file()` write-back step
- `config/app.php` — version bump to v15.6.1

> **Migration:** No DB schema migration. No cron schedule change required — the fix is purely at the code level and takes effect on the next cron tick after deploy. Existing stale entries in `telegram_notified` / `push_subscriptions[].notified` will be cleaned up automatically by the existing 7-day retention sweep.

## [15.6.0] - 2026-05-20

### Mobile UX — Favorites Pages Consolidation

Reduced vertical height on `/my/{slug}` and `/my-favorites/{slug}` by ~75–80% above the fold. Three stacked banners on mobile were eating ~240–300px before users reached Mini Calendar / Programs content; now consolidated to a single ~50–60px action chip bar. The "Followed Artists" section was also restructured from an always-expanded chip grid into a stats line with collapsible chips on demand.

- 🎨 **Action Chip Bar replaces 3 stacked banners** (`my.php`)
  - The "🔗 ย้าย Favorites ไปยังอุปกรณ์อื่น / PWA" (QR Transfer) collapsible section, "🔔 เชื่อมต่อ Telegram" banner, and "🔔 Web Push Notifications" banner are now a single `.fav-actions-bar` row containing 3 `.fav-action-chip` buttons
  - Each chip shows icon + label + (for stateful chips) a status dot — green `.is-active` when Telegram is linked or Web Push is subscribed, neutral gray when not
  - **QR Transfer chip** — click opens new `#qrTransferModal` (was inline expand); reuses existing `loadQrCode()` lazy-load logic
  - **Telegram chip** — click → if `.is-active` calls `unlinkTelegram()`; otherwise opens existing `#telegramLinkModal` (no change to the modal itself)
  - **Web Push chip** — click toggles `handlePushSubscribe()` / `handlePushUnsubscribe()` based on `.is-active` state; browser-unsupported state disables the chip with a tooltip
  - Responsive: `flex:1 1 0` with ellipsis labels; `@media (max-width:360px)` hides labels to keep 3 chips on one row
  - Removed dead CSS: `.fav-tg-banner`, `.fav-push-banner`, `.fav-transfer-section`, `.btn-transfer-toggle`, `.fav-tg-status`, `.fav-tg-dot` and related rules

- 🔧 **`checkPushStatus()` refactored to update chip class** (`my.php`)
  - Previous implementation updated `#pushStatusText` + `#pushSubscribeBtn` + `#pushUnsubscribeBtn` (now removed)
  - New implementation toggles `#pushChip` `.is-active` class based on subscription state
  - `handlePushSubscribe()` / `handlePushUnsubscribe()` updated to disable the chip during async operations
  - Unsupported-browser init guard sets `chip.disabled = true` + `chip.title = i18n('webpush.notSupported')` instead of writing to removed status element

- 🔧 **`my-favorites.php` parity** — QR section converted to modal (was inline expand)
  - Single `.fav-action-chip` (QR Transfer only — Telegram and Web Push banners were not present on this page)
  - Inlined `.req-modal-overlay` / `.req-modal` / `.req-modal-header` / `.req-modal-body` / `.req-close` CSS rules because `my-favorites.php` only loads `common.css` while `.req-modal*` styles live in `artist.css` (loaded by `my.php`) and `index.css` — without the inline copy the modal was `display:flex` but lacked `position:fixed; inset:0; background:rgba(...)` so it appeared invisible

- 📊 **Followed Artists → stats line + collapsible** (`my.php`)
  - Replaces always-expanded `.fav-section` with `.fav-followed-section` containing a single `<button class="fav-followed-toggle">` row showing "👥 ติดตาม {N} ศิลปิน · {M} upcoming programs" plus a `.fav-followed-caret` that rotates 180° on expand
  - Expanding reveals `.fav-followed-content` with the existing chip grid plus a new `.fav-manage-link` ("→ จัดการทั้งหมด") linking to `/my-favorites/{slug}` for batch management
  - Empty state (no followed artists): the toggle button is `disabled` and the empty-state message ("ยังไม่มีศิลปินที่ติดตาม...") renders below the disabled stats line — preserves the existing onboarding CTA
  - `toggleFollowedSection(btn)` JS function toggles `.is-open` class + `style.display` of content; uses `closest('.fav-followed-section')` so the function is reusable

- 🌐 **New i18n keys** in `js/translations.js` (all TH/EN/JA)
  - `actions.qrTransfer` ("ย้าย / PWA" / "Transfer / PWA" / "転送 / PWA")
  - `actions.telegram` ("Telegram" — same in all 3 locales)
  - `actions.push` ("แจ้งเตือน" / "Push" / "プッシュ")
  - `transfer.modalTitle` ("🔗 ย้าย Favorites ไปยังอุปกรณ์อื่น" / "🔗 Transfer Favorites to Another Device" / "🔗 Favoritesを他のデバイスに転送")
  - `fav.statsFollowing` ("ติดตาม" / "Following" / "フォロー中")
  - `fav.manageAll` ("→ จัดการทั้งหมด" / "→ Manage all" / "→ すべて管理")

**Files changed:**

- `my.php` — chip bar HTML/CSS/JS, QR transfer modal, refactored push status handling, followed artists section restructure, `toggleFollowedSection()` + `openQrTransferModal()` + `closeQrTransferModal()` + `handleTelegramChip()` + `handlePushChip()` JS functions
- `my-favorites.php` — chip bar (single chip), QR transfer modal, inlined `.req-modal*` CSS, `openQrTransferModal()` + `closeQrTransferModal()` JS functions
- `js/translations.js` — 6 new keys × 3 locales = 18 entries added
- `config/app.php` — version bump to v15.6.0

> **Migration:** no DB schema migration required; no breaking changes — purely client-side / template restructure. Existing slugs, favorites JSON, Telegram links, and Web Push subscriptions continue to work unchanged.

## [15.5.0] - 2026-05-20

### Security
Comprehensive security audit (2026-05-19) followed by full remediation (2026-05-20). All audit findings closed with regression tests. See `docs/SECURITY_AUDIT_2026.md` (revision 6) for the complete report. Test suite grew from 9361 → 9809 (+448 cumulative tests).

- 🔒 **HIGH-1 RESOLVED — Telegram webhook secret leak retired.**
  Removed debug scripts `test-telegram-webhook.php` and `test-webhook-verify.php` from working tree and `HEAD` (commit `dc182fc`). The latter file hardcoded a live `webhook_secret` (`cc73866…`) on lines 12 and 67. Cleared `webhook_secret` in `config/telegram-config.json` to empty string; `verify_telegram_request()` in `api/telegram.php:16` fail-closes (`return false`) on empty secret, so the historical value in commit `57242a0` no longer authenticates anything. Verified on 2026-05-20 that the public-facing `public-origin` (`fordantitrust/stage-idol-calendar`) has a separate history and never received the leaked commit — exposure was scoped to the private `origin` repo only.

- 🔒 **MEDIUM-1 RESOLVED — Web Push endpoint SSRF closed.**
  Added `webpush_allowed_hosts()` and `webpush_validate_endpoint()` in `functions/webpush.php`. Only FCM (`fcm.googleapis.com`), Mozilla autopush (`updates.push.services.mozilla.com`), Apple Web Push (`web.push.apple.com`) by exact match, plus `.notify.windows.com` (WNS) and `.push.apple.com` by suffix are accepted. HTTPS-only on the production path; IP literals (v4 and v6) rejected via `filter_var(...FILTER_VALIDATE_IP)`; bare suffix as full hostname rejected; lookalike-suffix attacks blocked by strict `str_ends_with`. New `WEBPUSH_ALLOW_LOCALHOST` flag gates a dev-only exception for `localhost / 127.0.0.1 / ::1 / host.docker.internal` over http or https, and only when `PRODUCTION_MODE = false`. Validation runs at subscribe time (`api/push.php`) and at send time (`webpush_send()` short-circuits with `expired = true` so the cron drops pre-allow-list records on the next tick).

- 🔒 **MEDIUM-2 RESOLVED — Login CSRF check added.**
  `admin/login.php` POST handler now calls `verify_csrf_token($_POST['csrf_token'])` **before** rate-limit accounting, so attackers without a valid token cannot exhaust the per-IP login budget for a legitimate user. CSRF failures audit-log with `action=login_blocked`, `error_code=csrf_invalid`. New `login.errCsrf` translation key in TH/EN ("Session หมดอายุ กรุณาโหลดหน้านี้ใหม่แล้วลองอีกครั้ง" / "Session expired. Please reload this page and try again."). 2FA flow compatible: the same session token covers both password and 2FA submission steps.

- 🔒 **LOW-1 RESOLVED — Log viewer authorization tightened.**
  `getTelegramLog()`, `getWebPushLog()`, `getEmailLog()` now call `require_api_admin_role()` (previously `require_login()` only — agents and organizers could read). Defense-in-depth at the dispatcher: all 8 log actions (`telegram_log_get/download`, `webpush_log_get/download`, `email_log_get/download`, `admin_audit_log_get/download`) added to `$adminOnlyActions` in `admin/api.php`.

- 🔒 **LOW-2 RESOLVED — `config/.htaccess` hardened.**
  Replaced extension-only `<FilesMatch "\.(php|json)$">` + inert `Allow from 127.0.0.1` lines with Apache 2.4 `Require all denied` plus an Apache 2.2 / `mod_access_compat` fallback (`Order deny,allow` + `Deny from all`). Every file under `config/` is now denied regardless of extension — future `.pem`, `.key`, `.env`, or `.txt` configs are protected automatically.

- 🔒 **LOW-3 RESOLVED — `tools/.htaccess` hardened.**
  Removed `Allow from 192.168.0.0/16` and `Allow from 10.0.0.0/8` lines that were dangerous on shared / cloud hosting (provider's internal network often overlaps LAN CIDRs). Now uses the same Apache 2.4 + 2.2-fallback deny-all pattern as `config/.htaccess`.

- 🔒 **LOW-4 RESOLVED — `.gitignore` extended.**
  Added `*.db`, `test-*.php`, and `debug-*.php` rules to prevent recurrence of HIGH-1. Inline note clarifies that existing tracked file `data/calendar.db` is unaffected (gitignore does not untrack already-committed files); explicit `git rm --cached data/calendar.db` is the way to untrack if desired.

### Tests Added (+~50 new regression tests; cumulative count +448)
- `tests/WebPushTest.php` — +33 tests covering allow-list contents, accept paths for FCM/Mozilla/Apple/WNS, reject paths (http to known host, attacker host, IPv4/IPv6 literals, empty host, garbage, lookalike suffix, bare suffix as full hostname), two-flag dev gate behavior, defense-in-depth in `webpush_send()`, source check that `api/push.php` no longer uses `FILTER_SANITIZE_URL` (now 68 tests total).
- `tests/AdminAuthTest.php` — +14 tests covering login CSRF (verify_csrf_token call, source order vs rate-limit, audit code, i18n markers, functional roundtrip) and log viewer authorization (4 viewers × `require_api_admin_role()` source check + dispatcher-list membership + organizer-list exclusion). Suite cumulative count: 38 → 46.
- `tests/IntegrationTest.php` — +8 tests covering `.gitignore` rules and the new `Require all denied` content in `config/.htaccess` and `tools/.htaccess`, plus absence of any `Allow from` directives in both files. Suite cumulative count: 108 → 121.

### Files Changed
- `config/app.php` (version bump)
- `config/webpush.php` (added `allow_localhost` default + `WEBPUSH_ALLOW_LOCALHOST` constant)
- `config/.htaccess` (rewrite to `Require all denied`)
- `config/telegram-config.json` (cleared `webhook_secret` to empty string)
- `functions/webpush.php` (`webpush_allowed_hosts()`, `webpush_validate_endpoint()`, guard in `webpush_send()`)
- `api/push.php` (replaced `FILTER_SANITIZE_URL` + regex with `webpush_validate_endpoint()` call)
- `admin/api.php` (`require_api_admin_role()` added to 3 log viewers; 8 log actions added to `$adminOnlyActions`)
- `admin/login.php` (CSRF gate before rate-limit; audit log on failure; new `login_csrf` error state)
- `admin/js/admin-i18n.js` (added `login.errCsrf` in TH and EN)
- `tools/.htaccess` (rewrite to `Require all denied`)
- `.gitignore` (added `*.db`, `test-*.php`, `debug-*.php`)
- `test-telegram-webhook.php` (deleted)
- `test-webhook-verify.php` (deleted)
- `tests/WebPushTest.php`, `tests/AdminAuthTest.php`, `tests/IntegrationTest.php`
- `docs/SECURITY_AUDIT_2026.md` (revisions 1–6: tracked CRITICAL → HIGH reclassification, MEDIUM closures, LOW closures, final FULLY REMEDIATED status with score 9.8 / 10)

> **Migration:** no DB schema migration required. **Operational note:** with `webhook_secret` cleared, the Telegram bot webhook handler rejects all incoming webhooks (fail-closed). To re-enable the bot, generate a fresh secret via Admin › Settings › Telegram and re-register the webhook with Telegram.

## [15.4.0] - 2026-05-19

### Added
- **`connect.php`** — new `/connect` page; camera-based QR scanner using `jsQR` library (multi-CDN fallback: jsdelivr → unpkg → cdnjs); animated viewfinder UI with scan-line; validates scanned slug against `SLUG_RE` regex; on success saves `fav_slug` to localStorage and redirects to `/my/{slug}`; paste-URL fallback for devices where camera is unavailable; `noindex, nofollow` meta; overrides `Permissions-Policy: camera=(self)` to allow camera access despite global `camera=()` security header; header/footer matches main site (site title, language switcher, nav links, footer-text structure with copyright + GitHub link)
- **🔗 Connect button in nav** — `injectFavNavButton()` in `js/common.js` now injects a 🔗 button (linking to `/connect`) when the user has no `fav_slug`; ⭐ and 📅 buttons still appear as before when a slug exists; 🔗 button is hidden on `/connect` and `/my*` pages
- **Section 24 in how-to-use.php** — new section `#s-connect` covering the two-device QR transfer flow (source + destination steps); TOC entry added; `section24.*` translation keys in 3 languages; also updated `section17.nav.tipText` to mention the 🔗 button
- **i18n keys** — `connect.*` (19 keys: title, heading, desc, tapStart, start, stop, opening, scanning, libError, camDenied, camError, verifying, invalidSlug, success, pasteDesc, pastePlaceholder, pasteBtn, pasteError) + updated `transfer.*` keys (showQr, desc, step1–3) + `section24.*` keys in TH/EN/JA

### Files Changed
- `connect.php` (new)
- `.htaccess`
- `js/common.js`
- `js/translations.js`
- `how-to-use.php`
- `config/app.php`

> **Migration:** no DB schema migration required.

## [15.3.0] - 2026-05-19

### Added
- **QR Code transfer section on `/my/{slug}` and `/my-favorites/{slug}`** — collapsible "🔗 ย้าย Favorites ไปยังอุปกรณ์อื่น / PWA" section below the fav-save-banner; generates a QR Code (via lazy-loaded `qrcodejs` CDN) encoding the `/my/{slug}` URL; scanning the QR from another device/PWA triggers the existing auto-save logic in `my.php` which sets `fav_slug` in that device's localStorage; step-by-step instructions guide users through the two-device flow; i18n keys `transfer.*` in TH/EN/JA

### Files Changed
- `my.php`
- `my-favorites.php`
- `js/translations.js`
- `config/app.php`

> **Migration:** no DB schema migration required.

## [15.2.0] - 2026-05-19

### Added
- **`cron/rotate-webpush-logs.php`** — daily log rotation for Web Push notification logs; renames `cache/logs/webpush-cron.log` to a dated archive (`webpush-cron-YYYY-MM-DD.log`); deletes archives older than 7 days; collision guard appends `-daily` suffix when a same-date size-rotated archive already exists; blocked via existing `cron/.htaccess`
- **`cron/rotate-email-logs.php`** — daily log rotation for Email notification logs; renames `cache/logs/email.log` to `email-YYYY-MM-DD.log`; deletes archives older than 7 days; same structure and safety guards as the webpush rotation script
- **Rotation cron instructions in Admin UI** — Web Push sub-tab shows daily rotation cron entry (`rotate-webpush-logs.php`); Email sub-tab shows daily rotation cron entry (`rotate-email-logs.php`); both output to shared `rotate-cron.log`
- **Admin Help pages updated** — `admin/help.php` + `admin/help-en.php` Web Push section Cron Job Setup now shows both the notification cron (every 15 min) and the daily rotation cron

### Files Changed
- `cron/rotate-webpush-logs.php` (new)
- `cron/rotate-email-logs.php` (new)
- `admin/index.php`
- `admin/js/admin-i18n.js`
- `admin/help.php`
- `admin/help-en.php`
- `config/app.php`

> **Migration:** no DB schema migration required; add the two new cron entries to your crontab.

## [15.1.0] - 2026-05-19

### Added
- **Web Push Log Viewer** — Admin UI › Settings › 📱 Web Push sub-tab now includes a log viewer section below the cron instructions; file selector dropdown lists `webpush-cron.log` + dated archives newest-first; filter input; scrollable table (Timestamp / Level / Message, sticky header, max-height); click any row to open a detail modal with full context JSON; `webpush_log_get` + `webpush_log_download` actions in `admin/api.php`
- **Email Log Viewer** — Admin UI › Settings › 📧 Email sub-tab similarly includes a log viewer; reads `cache/logs/email.log` + `email-YYYY-MM-DD.log` archives; same table + detail modal UX; `email_log_get` + `email_log_download` actions in `admin/api.php`
- **Shared cron log helpers** in `admin/index.php` — `_cronLevelBadge()`, `openCronLogDetail()`, `closeCronLogDetail()`, `_renderCronTable()` shared by both Web Push and Email log viewers; single `#cronLogDetailOverlay` modal reused by both viewers and the existing Telegram log viewer

### Fixed
- **PWA icons directory renamed `icons/` → `icon/`** — the `icons` directory name was blocked at the shared hosting server level, causing HTTP 404 for all PWA icon assets; renamed directory and updated all references: `manifest.json`, `manifest.php` (dynamic manifest), `service-worker.js`, `cron/send-web-push-notifications.php`, `tools/generate-pwa-icons.php`, `tests/WebPushTest.php`, `setup.php`, `admin/help.php`, `admin/help-en.php`

### Files Changed
- `admin/api.php`
- `admin/index.php`
- `admin/js/admin-i18n.js`
- `manifest.json`
- `manifest.php`
- `service-worker.js`
- `cron/send-web-push-notifications.php`
- `tools/generate-pwa-icons.php`
- `tests/WebPushTest.php`
- `setup.php`
- `admin/help.php`
- `admin/help-en.php`
- `config/app.php`

> **Migration:** no DB schema migration required; run `php tools/generate-pwa-icons.php` to regenerate icons into `icon/` directory if upgrading from v15.0.0.

## [15.0.0] - 2026-05-19

### Added
- **Web Push Notifications (PWA)** — VAPID EC P-256 + RFC 8291 aes128gcm push implementation using PHP 8.1+ built-ins; no Composer required
- `functions/webpush.php` — full crypto stack: `webpush_generate_vapid_keys()`, `webpush_vapid_jwt()` (ES256), `webpush_encrypt()` (RFC 8291 aes128gcm), `webpush_send()` (curl POST), `webpush_der_to_raw_sig()`, `webpush_raw_pubkey_to_pem()`, `webpush_urlsafe_b64encode/decode()`, `webpush_openssl_ec_config()` (Windows-safe via `php_ini_loaded_file()`), `webpush_is_enabled()`
- `config/webpush.php` — loads `config/webpush-config.json` and defines `WEBPUSH_ENABLED`, `WEBPUSH_VAPID_PUBLIC_KEY`, `WEBPUSH_VAPID_PRIVATE_KEY_PEM`, `WEBPUSH_VAPID_SUBJECT`, `WEBPUSH_SITE_URL`, `WEBPUSH_NOTIFY_BEFORE_MINUTES`, `WEBPUSH_MAX_SUBS_PER_TOKEN`
- `config/webpush-config.json` — default (disabled) VAPID config file; private key protected by existing `config/.htaccess`; includes `site_url` field
- `manifest.json` — Web App Manifest (name, short_name, start_url, display=standalone, theme_color=#E91E63, 3 icon sizes); enables PWA installability
- `service-worker.js` — handles `push` event (`showNotification`) and `notificationclick` event (focus/open window)
- `api/push.php` — public subscription API: `subscribe`, `unsubscribe`, `status` actions; requires HMAC-signed favorites slug; max `WEBPUSH_MAX_SUBS_PER_TOKEN` subscriptions per token; HTTP 400 without slug
- `cron/send-web-push-notifications.php` — mirrors Telegram cron structure; scans favorites JSON shards; respects `push_notify_enabled` + `push_mute_until`; removes HTTP 410 expired subscriptions; logs to `cache/logs/webpush-cron.log`
- `tools/generate-pwa-icons.php` — generates sakura-gradient PWA icons at 72/192/512 px using PHP GD
- `icons/icon-72.png`, `icons/icon-192.png`, `icons/icon-512.png` — generated PWA icon assets
- `icons/.htaccess` — allows HTTP serving of icons (no Deny from all)
- `tests/WebPushTest.php` — 45 automated tests (8 categories: Config/Constants, Crypto Functions, Static Files, Admin API, Public API, Cron, i18n, Config Loading)
- Admin Settings › 📱 **Web Push** sub-tab (9th sub-tab): VAPID key generation, subject email, notify-before minutes, enable toggle, cron setup instructions
- `webpush_config_get`, `webpush_config_save`, `webpush_vapid_generate` API actions in `admin/api.php`; private key never returned to UI; `site_url` persisted and validated
- **Site URL field** (`WEBPUSH_SITE_URL`) in Web Push admin sub-tab — full base URL of the site (e.g. `https://example.com/stage-idol-calendar`) used by cron to build correct notification links including subdirectory path; `webpush.siteUrlLabel` / `webpush.siteUrlHint` i18n keys (TH/EN)
- Web Push subscribe section in `my.php` (shown only when `webpush_is_enabled()` and slug present); `VAPID_PUBLIC_KEY` + `WEBPUSH_ENABLED` injected to JS
- `handlePushSubscribe()`, `handlePushUnsubscribe()`, `checkPushStatus()` JS functions in `my.php`
- SW registration + `urlBase64ToUint8Array()` helper in `js/common.js` DOMContentLoaded handler
- `webpush.subscribe`, `webpush.unsubscribe`, `webpush.title`, `webpush.permissionDenied`, `webpush.notSupported`, `webpush.subscribed`, `webpush.checking` i18n keys in `js/translations.js` (TH/EN/JA)
- `settings.webpush` i18n section in `admin/js/admin-i18n.js` (TH/EN)
- `<link rel="manifest">` + `<meta name="theme-color">` + Apple PWA meta tags in all public pages (`index.php`, `artist.php`, `artists.php`, `credits.php`, `contact.php`, `how-to-use.php`, `my.php`, `my-favorites.php`, `past-events.php`)
- `.htaccess` `Service-Worker-Allowed` + `Cache-Control: no-cache` headers for `service-worker.js` and `manifest.json`
- Web Push section in `admin/help.php` (Thai) and `admin/help-en.php` (English): setup guide, configuration table, cron command, key files, troubleshooting, browser support

### Fixed
- `webpush_encrypt()` — `openssl_pkey_derive()` argument order was reversed (`$ephemKey, $uaKey`) causing "Supplied key param is a public key" warning; fixed to `($uaKey, $ephemKey)` per PHP signature `(public_key, private_key, length)`
- `webpush_openssl_ec_config()` — Windows/XAMPP Apache context: `PHP_BINARY` points to `httpd.exe` so old `dirname(PHP_BINARY)` path lookup failed; fixed by using `php_ini_loaded_file()` as primary OpenSSL config path source
- Admin UI Web Push JS functions (`loadWebPushConfig`, `saveWebPushConfig`, `generateVapidKeys`) were absent from `admin/index.php` causing "Network error" on every button click
- `saveWebPushConfig()` and `generateWebPushVapidKeys()` in `admin/api.php` called `verify_csrf_token()` with no arguments causing `ArgumentCountError` — removed redundant calls (global `require_csrf_token()` at file top already handles CSRF)
- Cron `$vapidCfg` keys used short names (`public_key`, `private_key_pem`, `subject`) that did not match what `webpush_send()` and `webpush_vapid_jwt()` read (`vapid_public_key`, `vapid_private_key_pem`, `vapid_subject`); fixed key names
- Notification URL missing subdirectory base path (e.g. `/stage-idol-calendar`) when `WEBPUSH_VAPID_SUBJECT` is `mailto:` — cron now uses `WEBPUSH_SITE_URL` constant instead of deriving URL from VAPID subject; icon/badge paths also use `$baseUrl`

### Changed
- `config.php` — added `require_once` for `config/webpush.php` and `functions/webpush.php`
- `setup.php` — added Web Push config check step and `cache/logs/` directory entry (reused from v14.0.0)
- `tests/run-tests.php` — added `WebPushTest` to suite registry (24 suites total)
- `config/app.php` — version bump to v15.0.0

### Files Changed
- `functions/webpush.php` (new)
- `config/webpush.php` (new)
- `config/webpush-config.json` (new)
- `manifest.json` (new)
- `service-worker.js` (new)
- `api/push.php` (new)
- `cron/send-web-push-notifications.php` (new)
- `tools/generate-pwa-icons.php` (new)
- `icons/icon-72.png` (new)
- `icons/icon-192.png` (new)
- `icons/icon-512.png` (new)
- `icons/.htaccess` (new)
- `tests/WebPushTest.php` (new)
- `config.php`
- `config/app.php`
- `admin/api.php`
- `admin/index.php`
- `admin/js/admin-i18n.js`
- `admin/help.php`
- `admin/help-en.php`
- `my.php`
- `js/common.js`
- `js/translations.js`
- `index.php`
- `artist.php`
- `artists.php`
- `credits.php`
- `contact.php`
- `how-to-use.php`
- `my-favorites.php`
- `past-events.php`
- `.htaccess`
- `setup.php`
- `tests/run-tests.php`

> **Migration:** no DB schema migration required; run `php tools/generate-pwa-icons.php` once; configure VAPID keys and Site URL through Admin UI › Settings › 📱 Web Push.
> **Test Coverage:** All 9338 automated tests pass (100% pass rate, 24 suites)

## [14.0.0] - 2026-05-18

### Added
- **Admin Audit Log (Phase 1 — File-based)** — `functions/audit.php` core engine appends JSON Lines records to `cache/logs/admin-audit-YYYY-MM-DD.log`; 30-day retention via `cron/rotate-admin-audit-logs.php`
- `ADMIN_AUDIT_RETENTION_DAYS` and `ADMIN_AUDIT_LOG_DIR` constants in `config/admin.php`
- Audit hooks in `functions/admin.php` and `admin/login.php`: `login_success`, `login_failure`, `login_blocked`, `twofa_required`, `twofa_success`, `twofa_failure`, `logout`
- Audit hooks across all write actions in `admin/api.php`: programs, events, credits, artists, users, backup, settings, config, password/2FA
- Audit hooks in `api/request.php` and `api/event-request.php`: public Program/Event Request submissions (`program_request_create`, `event_request_create`) and rate-limit blocks
- `admin_audit_log_get` and `admin_audit_log_download` API endpoints in `admin/api.php`
- Settings › 🔎 Audit Log sub-tab in `admin/index.php`: parsed table (Timestamp / Action / Outcome / Actor / Entity / IP), sticky header, 400 px scrollable container, filter input, click-to-open detail modal with pretty-printed metadata
- Telegram Activity Log viewer upgraded from raw `<pre>` to same table+modal format (Timestamp / Level / Message; context in detail modal)
- `cache/logs/` directory check in `setup.php` migration status

### Files Changed
- `functions/audit.php` (new)
- `cron/rotate-admin-audit-logs.php` (new)
- `config/admin.php`
- `config.php`
- `functions/admin.php`
- `admin/login.php`
- `admin/api.php`
- `admin/index.php`
- `admin/js/admin-i18n.js`
- `api/request.php`
- `api/event-request.php`
- `setup.php`
- `config/app.php`

---

## [12.3.4] - 2026-05-11

### Fixed
- 🐛 **Request email admin URL** — the `Open Admin Requests tab` link in Program/Event Request notification emails now resolves to `/admin/` instead of `/api/admin/`, including fallback cases where the request path is read from `REQUEST_URI`.
- 🛡️ **Subdirectory-safe path handling** — email admin URL generation now normalizes `SCRIPT_NAME`, `PHP_SELF`, and `REQUEST_URI`, strips `/api` or `/admin`, and preserves app subdirectory deployments.

### Changed
- 🧪 **Email regression coverage** — `EmailNotificationTest` now verifies request-email HTML and plain-text bodies never link to `/api/admin`.
- 🔢 **Version bump** — `APP_VERSION` updated to `12.3.4`.

### Files Changed
- `functions/email.php` — robust app base path extraction for request notification admin links.
- `tests/EmailNotificationTest.php` — regression coverage for `/api/admin` email link fallback.
- `config/app.php` — version bump to `12.3.4`.
- `README.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SETUP.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md`, `WORKFLOW.md`, `CHANGELOG.md` — v12.3.4 release documentation.

## [12.3.3] - 2026-05-08

### Changed
- 📖 **Role-aware Admin Help** — Thai and English Admin Help now hide topics that the current role cannot use: admin sees the full guide, agent sees operational/review workflows, and organizer sees scoped Events / Programs / Credits / Artist Request guidance.
- 📝 **Admin Help content refresh** — Help documentation now covers organizer role behavior through v12.3.3, including Organizer Active Requests, Artist Requests, approve-to-artist behavior, and the current three-role permission matrix.
- 🔢 **Version bump** — `APP_VERSION` updated to `12.3.3`.

### Files Changed
- `admin/help.php`, `admin/help-en.php` — role-aware navigation/content visibility and v12.3.3 documentation updates.
- `config/app.php` — version bump to `12.3.3`.
- `README.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SETUP.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md`, `WORKFLOW.md`, `CHANGELOG.md` — v12.3.3 release documentation.

## [12.3.2] - 2026-05-08

### Fixed
- 🐛 **Artist Request approve visibility** — after approving an Artist Request, admin/agent UI now refreshes the Artist list so the newly created artist appears immediately.
- ✅ **Approve confirmation** — approve success toast includes the created `artist_id`, making it clear that the request created an `artists` record.

### Changed
- 🧪 **Regression coverage** — `OrganizerRoleTest` now checks that Artist Request approval inserts into `artists`, returns `artist_id`, and refreshes the admin/agent Artist list.
- 🔢 **Version bump** — `APP_VERSION` updated to `12.3.2`.

### Files Changed
- `admin/index.php` — refresh Artist list after Artist Request approval and show created artist id in the success toast.
- `tests/OrganizerRoleTest.php` — regression coverage for approve-to-artist behavior and UI refresh.
- `config/app.php` — version bump to `12.3.2`.
- `README.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SETUP.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md`, `WORKFLOW.md`, `CHANGELOG.md` — v12.3.2 release documentation.

## [12.3.1] - 2026-05-08

### Fixed
- 🐛 **Organizer Artist Request access** — organizer users entering the Artists tab no longer trigger the admin-only `artists_list` API before opening `Request new artist`.
- 🛡️ **Role guard regression** — `switchTab('artists')` now loads the full Artist database only for non-organizer roles.

### Changed
- 🔢 **Version bump** — `APP_VERSION` updated to `12.3.1`.

### Files Changed
- `admin/index.php` — removed the unconditional `loadArtists()` call from Artists tab switching so organizer request flow can open without a 403 error.
- `tests/OrganizerRoleTest.php` — regression coverage for organizer Artists tab access.
- `config/app.php` — version bump to `12.3.1`.
- `README.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SETUP.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md`, `WORKFLOW.md`, `CHANGELOG.md` — v12.3.1 release documentation.

## [12.3.0] - 2026-05-08

### Added
- 🎤 **Organizer Artist Requests** — organizers can request a new artist from `Artists › Request new artist` using the existing Artist form in request mode.
- 📝 **Requests › Artist Request** — admin/agent review flow now includes Artist Request as the fourth request group with approve/reject actions.
- 🗄️ **artist_requests table** — stores artist details, group/social fields, requester metadata, review status, admin note, reviewer, and timestamps.

### Changed
- 📊 **Dashboard requests** — request KPIs and breakdown now include Program, Event Guest, Event Active, and Artist requests.
- 🧭 **Organizer dashboard/actions** — organizer quick actions now focus on Events, Programs, Credits, and Request New Artist; request review breakdown, artist totals, import/settings/admin-only actions, and missing-artist-picture health are hidden.
- 📐 **Dashboard long tables** — `Upcoming Events` and `Programs by Event` now render as full-width stacked panels instead of two columns for long event names.
- 🔢 **Version bump** — `APP_VERSION` updated to `12.3.0`.

### Files Changed
- `admin/api.php` — Artist Request API actions, duplicate checks, approve-to-artist workflow, pending counts, and dashboard request breakdown.
- `admin/index.php` — organizer Artist tab, request-mode Artist modal, Artist Request sub-tab, badges, role-focused Dashboard, and full-width Dashboard table panels.
- `admin/js/admin-i18n.js` — Artist Request and Dashboard labels in Thai/English.
- `setup.php` — fresh install, Run All Migrations, Migration Status sorting, setup top navigation, and `artist_requests` status checks.
- `tools/migrate-add-artist-requests-table.php` — idempotent migration for `artist_requests` table and indexes.
- `tests/OrganizerRoleTest.php` — coverage for Artist Request migration/API/UI surface.
- `config/app.php` — version bump to `12.3.0`.
- `README.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SETUP.md`, `INSTALLATION.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md`, `WORKFLOW.md`, `CHANGELOG.md` — v12.3.0 release documentation.

## [12.2.0] - 2026-05-07

### Added
- 🎤 **Organizer Program autocomplete** — organizer users can use the existing Artist autocomplete while adding/editing Programs.
- 📍 **Central Venue autocomplete** — Venue suggestions now come from the shared program venue list for organizer users too.

### Changed
- Organizer Artist input is reference-only: organizer users must select/use existing artists and cannot create new artist records through Program categories.
- `syncProgramArtists()` now supports disabling automatic artist creation; organizer Program saves validate all artist references before insert/update.
- `APP_VERSION` updated to `12.2.0`.

### Files Changed
- `admin/api.php` — allows organizer `artists_autocomplete`, validates organizer artist references, disables organizer artist auto-create, and uses central venue suggestions.
- `admin/index.php` — organizer Artist tag input requires autocomplete selection and shows organizer-specific helper text.
- `admin/js/admin-i18n.js` — organizer autocomplete-only helper/error labels in Thai/English.
- `tests/OrganizerRoleTest.php` — coverage for organizer autocomplete access and artist reference-only policy.
- `config/app.php` — version bump to `12.2.0`.
- `README.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SETUP.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md`, `WORKFLOW.md`, `CHANGELOG.md` — v12.2.0 release documentation.

## [12.1.0] - 2026-05-07

### Added
- 🟡 **Organizer active request flow** — organizer users can submit activation requests for assigned inactive events via `events_request_activate`.
- ✅ **Admin/agent activation approval** — Event Requests now supports `request_type='activate'`; approving it sets the existing event to `is_active=1`.
- 🧭 **Separated request menus** — Admin Requests now shows `Program Requests`, `Event Requests (Guest)`, and `Event Active Requests (Organizer)`.

### Changed
- Organizer-created events are always saved inactive.
- Organizer users cannot directly turn on `is_active` while editing events; the Admin UI disables the Active checkbox for organizers and shows a Request Active action.
- Event request listing supports `request_group=guest|active` so guest add/edit requests and organizer activation requests do not mix in the same menu.
- `APP_VERSION` updated to `12.1.0`.

### Files Changed
- `admin/api.php` — added organizer activation request endpoint, blocked organizer direct activation, added activate approval handling, and split event request listing/counts by request group.
- `admin/index.php` — disabled organizer Active checkbox, added Request Active button, and split Requests menus into Program, Guest Event, and Organizer Active sections.
- `admin/js/admin-i18n.js` — request-active labels and activate request type labels in Thai/English.
- `tests/OrganizerRoleTest.php` — static coverage for organizer activation policy and UI/API surface.
- `config/app.php` — version bump to `12.1.0`.
- `README.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SETUP.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md`, `WORKFLOW.md`, `CHANGELOG.md` — v12.1.0 release documentation.

## [12.0.0] - 2026-05-07

### Added
- 👥 **Organizer role** — added `organizer` admin role for users who can manage only assigned events.
- 🔐 **Event ownership schema** — added `events.created_by_user_id` and `event_organizers(event_id, user_id, assigned_by, assigned_at)` with cleanup-friendly foreign keys and indexes.
- 🛡️ **Organizer API guards** — Events, Programs, Credits, event covers, header covers, and event pictures now enforce assigned-event ownership for organizer users.
- 🧑‍💼 **Assignment API** — added admin-only `event_organizers_list` and `event_organizers_update` endpoints.
- 🧪 **Tests** — added organizer role/static guard coverage and updated user role tests.

### Changed
- `APP_VERSION` updated to `12.0.0`.
- User Management now accepts `admin`, `agent`, and `organizer` roles.
- Organizer users see only Dashboard, Events, Programs, and Credits tabs in the Admin UI.

### Files Changed
- `admin/api.php` — organizer allow/deny matrix, ownership guards for Events/Programs/Credits/media, and admin-only event organizer assignment endpoints.
- `admin/index.php` — organizer tab visibility, User Management role option, role badge, and Event modal organizer assignment UI.
- `admin/js/admin-i18n.js` — organizer role and assignment labels in Thai/English.
- `functions/admin.php` — role helpers, least-privilege fallback, current user helper, and `can_manage_event()`.
- `setup.php` — fresh install and run-all migration support for `events.created_by_user_id` and `event_organizers`.
- `tools/migrate-add-organizer-role.php` — idempotent CLI migration for organizer ownership schema.
- `tests/UserManagementTest.php`, `tests/OrganizerRoleTest.php`, `tests/run-tests.php` — role helper, schema, API guard, UI surface, and suite registration coverage.
- `config/app.php` — version bump to `12.0.0`.
- `README.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SETUP.md`, `INSTALLATION.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md`, `WORKFLOW.md`, `CHANGELOG.md` — v12.0.0 release documentation.

## [11.0.0] - 2026-05-07

### Added
- 📊 **Admin Dashboard** — new first Admin tab focused on analytics: KPIs for Events, Programs, Credits, Artists, and pending Requests
- 🧭 **Dashboard analytics API** — `admin/api.php?action=dashboard_stats` returns read-only aggregate data with role-aware admin-only fields
- 🩺 **Content Health** — dashboard highlights missing event cover/card/header images, missing ticket URLs, and artists without display pictures
- ⚡ **Quick Actions** — dashboard shortcuts for adding Events/Programs, importing ICS, reviewing Requests, and opening Settings for admins

### Changed
- 🏠 **Admin default page** — Admin now opens on Dashboard before Events
- 🔢 **Version bump** — `APP_VERSION` updated to `11.0.0`

### Files Changed
- `admin/index.php` — Dashboard tab, responsive analytics UI, default tab switching, dashboard render logic
- `admin/api.php` — `dashboard_stats` endpoint with defensive table checks for older databases
- `admin/js/admin-i18n.js` — TH/EN Dashboard labels
- `config/app.php` — version bump to v11.0.0

---

## [10.1.0] - 2026-05-07

### Changed
- 🗄️ **Manual 2FA migration policy** — `admin/api.php` no longer auto-adds `admin_users.twofa_*` columns during normal API requests
- ⚡ **2FA schema readiness flag** — once the 2FA columns are confirmed, `admin/api.php` creates `data/.admin_2fa_columns_ready` and skips repeated schema checks until that file is removed
- 👥 **User API compatibility** — `users_list` and `users_get` keep working before the 2FA migration by returning `twofa_enabled = 0` and `twofa_confirmed_at = null`

### Migration
- Existing installs should run the 2FA migration manually through `setup.php` or `php tools/migrate-add-admin-2fa-columns.php`.
- Delete `data/.admin_2fa_columns_ready` if you need to force the Admin API to re-check the 2FA schema after a manual database change.

### Files Changed
- `admin/api.php` — replaced automatic 2FA schema migration with read-only schema detection, file-flag caching, endpoint guards, and user-list fallback fields
- `.gitignore` — ignores `data/.admin_2fa_columns_ready`
- `tests/TwoFactorAuthTest.php` — verifies Admin API does not auto-migrate and uses the schema readiness flag
- `config/app.php` — version bump to v10.1.0
- `README.md` — release notes, feature timeline, and test coverage updated for v10.1.0
- `API.md` — documents manual 2FA migration requirement and schema readiness flag behavior
- `SECURITY.md` — clarifies 2FA was introduced in v10.0.0 and schema migration control changed in v10.1.0
- `SETUP.md` — documents manual 2FA columns migration and `data/.admin_2fa_columns_ready`
- `INSTALLATION.md` — adds Admin 2FA manual migration note and restores `10.0.0.0/8` private network examples
- `TESTING.md` — adds 2FA manual migration/schema flag test case
- `PROJECT-STRUCTURE.md` — adds `data/.admin_2fa_columns_ready` runtime flag and updates TwoFactorAuthTest coverage summary
- `WORKFLOW.md` — updates current version and release workflow examples to v10.1.0
- `tests/README.md` — updates TwoFactorAuthTest coverage summary
- `admin/help.php` — adds Thai admin help note for v10.1.0 2FA migration behavior
- `admin/help-en.php` — adds English admin help note for v10.1.0 2FA migration behavior

---

## [10.0.0] - 2026-05-07

### Added
- 🔐 **Admin 2FA (TOTP / RFC 6238)** — DB-managed admin users can enable optional Authenticator app codes from the Change Password modal
- 🧾 **Backup codes** — one-time recovery codes are generated when enabling 2FA and can be regenerated after password + 2FA verification
- 👤 **Admin reset** — Users table shows 2FA status and lets admin reset another user's 2FA
- 🧪 **TwoFactorAuthTest** — coverage for RFC 6238 vectors, Base32, otpauth URI, replay prevention, backup codes, schema, API, UI, and i18n

### Changed
- 🔑 **Login flow** — users with enabled 2FA complete a pending second step before the admin session is finalized
- 🗄️ **Admin user schema** — adds `twofa_enabled`, `twofa_secret`, `twofa_backup_codes`, `twofa_confirmed_at`, and `twofa_last_used_step`

### Migration
- Run `php tools/migrate-add-admin-2fa-columns.php` for existing installs before enabling 2FA.

### Files Changed
- `functions/totp.php` (new) — RFC 6238 TOTP, Base32, otpauth URI, replay guard, and one-time backup-code helpers
- `tools/migrate-add-admin-2fa-columns.php` (new) — idempotent migration for 2FA columns on `admin_users`
- `tests/TwoFactorAuthTest.php` (new) — 2FA helper, schema, API, login flow, UI, and i18n coverage
- `functions/admin.php` — pending 2FA login flow and final session creation after TOTP/recovery verification
- `admin/login.php` — second-step 2FA form for users with 2FA enabled
- `admin/api.php` — 2FA setup, confirm, disable, backup-code regeneration, reset endpoints, and public 2FA user status fields
- `admin/index.php` — Change Password modal 2FA panel and Users table 2FA status/reset controls
- `admin/js/admin-i18n.js` — TH/EN labels for 2FA setup, login, backup codes, and user status
- `setup.php` — fresh-install and setup migration support for 2FA columns
- `config.php` — loads `functions/totp.php`
- `config/app.php` — version bump to v10.0.0
- `tests/run-tests.php` — registered TwoFactorAuthTest suite
- Documentation — updated `README.md`, `API.md`, `SECURITY.md`, `SETUP.md`, `TESTING.md`, `PROJECT-STRUCTURE.md`, `WORKFLOW.md`, and `tests/README.md`

---

## [9.6.0] - 2026-05-07

### Added
- 📧 **Email Notifications** — Admin can configure SMTP email notifications from Admin › Settings › 📧 Email; settings are stored in `config/email-config.json` and loaded by `config/email.php`
- 📬 **Request email alerts** — New Program Requests and Event Requests trigger admin notification emails through `email_notify_program_request_created()` and `email_notify_event_request_created()` when email notifications are enabled
- 🧪 **EmailNotificationTest** — coverage for email config loading, SMTP disabled guard, recipient parsing, escaped request email bodies, Admin Email API/UI wiring, request API notification hooks, and Requests empty-state rendering

### Changed
- 🎨 **Requests empty state** — normalized the `"No requests"` display in both Admin › Requests › Program Requests and Event Requests so the empty table row uses centered muted text with the correct column span
- 🛡️ **Email helper loading guard** — `admin/api.php` now defensively loads `functions/email.php` before Email Config/Test actions, preventing a production fatal if `config.php` is stale or deployed without the new helper load line
- ✉️ **Site-name email subjects** — Email test and request notification subjects now use the configured site name: `[<site name>] Email Notification Test`, `[<site name>] New Program Request`, and `[<site name>] New EventRequest`
- 🔗 **Admin email link target** — request notification emails now link to the real `/admin/` path and strip `/api` or duplicate `/admin` script directories from generated URLs

### Files Changed
- `config/email.php` (new) — loads runtime SMTP settings from `config/email-config.json` and defines `EMAIL_*` constants
- `config/email-config.json` (new) — runtime-editable SMTP settings; disabled by default and protected by `config/.htaccess`
- `functions/email.php` (new) — native SMTP helper, recipient parsing, logging, escaped HTML/plain-text request email body formatting, and request notification helpers
- `config.php` — loads email config and helper functions
- `admin/api.php` — `email_config_get`, `email_config_save`, and `email_test_send` actions
- `admin/index.php` — Settings Email sub-tab UI + Program/Event Requests empty-state display fix
- `admin/js/admin-i18n.js` — Email settings labels and messages
- `api/request.php` — sends Program Request email notification after successful submission
- `api/event-request.php` — sends Event Request email notification after successful submission
- `tests/EmailNotificationTest.php` (new) — email notification, defensive helper loading, and request empty-state tests
- `tests/run-tests.php` — registered EmailNotificationTest suite
- `config/app.php` — version bump to v9.6.0

---

## [9.5.0] - 2026-05-08

### Added
- 🔗 **Artist Social Links** — Facebook, Instagram, Twitter/X, and TikTok link fields added to each artist; social icons displayed as styled pill buttons on `/artist/{id}` profile page (header, before subscribe/follow buttons); icon buttons also shown on the `/artists` portal page — in group gradient cards (below member chips) and on solo artist cards; 4 new columns `social_facebook`, `social_instagram`, `social_twitter`, `social_tiktok TEXT DEFAULT NULL` in `artists` table
- 🎟️ **Ticket URL ("ซื้อบัตร" / "Get Ticket")** — each event can now store a ticket purchase URL (`ticket_url TEXT DEFAULT NULL`); when set, an orange "🎟️ ซื้อบัตร" button appears in the event-detail header nav before the data-version badge; hidden when empty; validated as `http(s)://` scheme; i18n key `event.buyTicket` (TH/EN/JA)
- 🔧 **`tools/migrate-add-artist-social-columns.php`** — idempotent migration adding the 4 social columns via `PRAGMA table_info` + `ALTER TABLE ADD COLUMN`
- 🔧 **`tools/migrate-add-ticket-url-column.php`** — idempotent migration adding `ticket_url` to `events` table

### Changed
- 🎨 **Artists portal solo cards restructured** — solo card wrapper changed from `<a>` to `<div>` (with inner `<a class="portal-solo-name-link">`) to allow valid nested anchor tags for social icon links; JS search filter unchanged (targets `data-name` on outer `.portal-solo-card`)

### Files Changed
- `tools/migrate-add-artist-social-columns.php` (new)
- `tools/migrate-add-ticket-url-column.php` (new)
- `admin/api.php` — `sanitize_social_url()` standalone function; `getArtist()` + `createArtist()` + `updateArtist()` include social fields; `createEvent()` + `updateEvent()` include `ticket_url`
- `admin/index.php` — 4 social link inputs in artist modal; `ticket_url` input in event modal; modal open/save JS updated for all new fields
- `admin/js/admin-i18n.js` — `event.ticketUrlLabel`, `event.ticketUrlHint`, `artist.socialLinksLabel` keys (TH/EN)
- `artist.php` — SELECT extended; `.artist-social-links` block rendered in artist header (before subscribe/follow buttons)
- `artists.php` — `portal_social_svg()` PHP helper; GROUP + SOLO queries extended; social icon buttons in group cards and solo cards; solo card wrapper changed from `<a>` → `<div>`
- `styles/artist.css` — `.artist-social-link`, `.artist-social-links` CSS classes
- `styles/portal.css` — `.portal-social-links`, `.portal-social-icon`, `.portal-solo-name-link` CSS; solo card styles updated for `<div>` wrapper
- `styles/common.css` — `.btn-ticket` orange gradient CSS class
- `index.php` — ticket URL button in event-detail `<nav class="header-nav">`
- `js/translations.js` — `event.buyTicket` key (TH/EN/JA)
- `setup.php` — migration checks + `$allTablesOk` + `run_all_migrations` + `init_database` + `add_artist_tables` updated for new columns
- `config/app.php` — version bump to v9.5.0

---

## [9.4.0] - 2026-05-07

### Added
- 📋 **Inline Credits on Event Pages** — "แหล่งข้อมูลอ้างอิง" section now rendered directly at the bottom of every event-detail page (below the cross-event artists section), using cards with title, description, and external link; no need to navigate to a separate credits page; powered by `get_cached_credits($eventId)`; CSS classes `.event-credits-section`, `.event-credits-title`, `.event-credits-list`, `.event-credits-item*` in `styles/index.css`

### Changed
- 🧹 **Event-detail header nav simplified** — removed "📋 แหล่งข้อมูลอ้างอิง" (credits) link and "🎤 ศิลปิน" (artists portal) link from the event-detail page's `<nav class="header-nav">`; only the data-version badge remains; listing-page nav is unchanged and retains all links
- 🧹 **Credits page event-picker removed** — the grid-dots 🎪 event-picker button and its full modal (with usort loop, search input, filter tabs, empty state) removed from `credits.php`; the listing-page event-picker is unaffected

### Files Changed
- `index.php` — inline credits PHP block inserted between cross-event `<?php endif; ?>` and `<footer>`; credits/artists links removed from event-detail `<nav class="header-nav">`
- `credits.php` — event-picker button block (guarded by `MULTI_EVENT_MODE && count($activeEvents) > 1`) deleted; event-picker modal block deleted
- `styles/index.css` — CSS classes for inline credits section added at end of file
- `config/app.php` — version bump to v9.4.0

---

## [9.3.0] - 2026-05-07

### Added
- 📝 **Event Request System** — users can now submit new event proposals via a new "📝 แจ้งเพิ่มงาน" button in the header nav; available on the listing page only (not individual event pages); add-only (not modify)
- 🗄️ **`event_requests` table** — stores request type (add), name, description, dates, requester info, status (pending/approved/rejected), admin note, and review metadata
- 🌐 **`api/event-request.php`** — public API with two actions: `submit` (POST, rate-limited 10 req/hr/IP, type='add' only) and `events` (GET, returns active events for reference)
- ⚙️ **Admin Event Requests section** — sub-toggle tabs in Requests tab ("📝 Program Requests" / "🗓️ Event Requests"); filter by status; table with type badge, event name, dates, reporter, status; detail modal with approve/reject with admin note
- 🔢 **Unified pending badge** — Requests tab badge now sums pending counts from both `program_requests` AND `event_requests` tables; backward compatible when `event_requests` table absent
- 🔧 **`tools/migrate-add-event-requests-table.php`** — idempotent migration creating the `event_requests` table

### Changed
- Admin approve (type=add): automatically creates a new event in the `events` table with auto-generated slug; `is_active=0` so newly created events start inactive and must be manually activated; invalidates listing query cache

### Files Changed
- `tools/migrate-add-event-requests-table.php` (new) — idempotent migration for `event_requests` table
- `api/event-request.php` (new) — public API for submitting event requests and listing active events
- `setup.php` — `event_requests` table in `init_database`, `run_all_migrations`; `$hasEventRequestsTable` detection; `$allTablesOk`; `$migrationChecks` entry
- `index.php` — nav link "📝 แจ้งเพิ่มงาน" (listing header nav only); Event Request modal HTML (add-only, no type radio toggle); JS functions (`openEventRequestModal`, `closeEventRequestModal`, `submitEventRequest`)
- `admin/api.php` — `event_requests_list`, `event_request_approve`, `event_request_reject`, `event_request_pending_count` actions; `getPendingCount()` updated; `require_api_admin_role()` fix; `is_active=0` on approve
- `admin/index.php` — Program/Event Requests sub-tabs; Event Requests section (table + pagination + detail modal + approve/reject flow); fetch URL fix; colspan fix; modal CSS fix; sub-tab badge `<span>` elements
- `admin/js/admin-i18n.js` — 21 new keys (TH+EN): `tab.programRequests`, `tab.eventRequests`, `evReq.*` table headers/labels/statuses, plus 7 missing detail modal keys
- `js/translations.js` — keys: `nav.eventRequest`, `evReq.titleAdd`, `evReq.name`, `evReq.startDate`, `evReq.endDate`, `evReq.description`, `evReq.submitSuccess` (TH/EN/JA)
- `config/app.php` — version bump to v9.3.0

---

## [9.2.0] - 2026-05-06

### Added
- 🖼️ **Site-wide Header Cover Background Image** — admins can upload a landscape cover image (1920×480 px, 4:1 ratio) that appears behind every page header site-wide; managed via Admin › Settings › Site sub-tab; stored in `uploads/site/` with directory listing and PHP execution blocked via `.htaccess`
- 🖼️ **Per-event Header Cover Image** — separate `header_cover_image` column (`events` table) for a 4:1 header banner per event; independent from the hero cover image (16:9) and card cover (4:3); set via Admin › Events › Cover Images section
- ✂️ **Cropper.js for site cover + event header cover** — both site-wide and per-event header covers use Cropper.js at 4:1 ratio before upload; cropper modal moved to top-level `<body>` child so it works from any admin tab including Settings (fixes hidden-parent `display:none` issue)
- 🔧 **`get_site_cover_bg()`** — reads `site_cover_bg` key from `cache/site-settings.json`; validates file exists before returning path
- 🔧 **`get_header_cover_bg(?$eventMeta)`** — priority chain: `event.header_cover_image` → site-wide cover → empty (gradient fallback); public pages inject `class="has-site-cover"` + `--header-cover-url` CSS variable on `<header>` when a cover image is available
- 📐 **Date Jump Bar centering fix** — `.date-jump-bar` is now a full-width fixed background container; inner `.date-jump-inner` wrapper handles `max-width: 1200px; margin: 0 auto` centering; previously `max-width + margin:auto` had no effect on the fixed element itself
- 🔧 **`tools/migrate-add-header-cover-image-column.php`** — idempotent migration adding `header_cover_image TEXT DEFAULT NULL` to `events` table

### Changed
- `header.has-site-cover` CSS — removed `linear-gradient` overlay; header now shows the cover image directly without a pink gradient tint on top; `background-image: var(--header-cover-url)` only
- Admin API `site_cover_bg_upload` — resizes to 1920×480 (4:1) instead of 1920×1080
- Admin API `title_get` — includes `site_cover_bg` in response so Settings UI loads everything in one request

### Files Changed
- `uploads/site/.htaccess` (new) — block PHP execution + directory listing for site cover uploads
- `tools/migrate-add-header-cover-image-column.php` (new) — idempotent migration adding `events.header_cover_image`
- `functions/helpers.php` — `get_site_cover_bg()`, `get_header_cover_bg()` helpers
- `styles/common.css` — `header.has-site-cover` rule (removed gradient overlay)
- `styles/index.css` — `.date-jump-bar` + `.date-jump-inner` restructure for centering
- `index.php` — listing header + event-detail header: inject `has-site-cover` class + `--header-cover-url`; `.date-jump-inner` wrapper
- `credits.php` — inject site-wide header cover
- `contact.php` — inject site-wide header cover
- `how-to-use.php` — inject site-wide header cover
- `past-events.php` — inject site-wide header cover
- `artist.php` — inject site-wide header cover
- `artists.php` — inject site-wide header cover
- `my.php` — inject site-wide header cover
- `my-favorites.php` — inject site-wide header cover
- `admin/api.php` — `site_cover_bg_upload` (1920×480), `site_cover_bg_delete`, `event_header_cover_upload`, `event_header_cover_delete`; `title_get` includes `site_cover_bg`
- `admin/index.php` — site cover Cropper.js + upload UI in Settings; per-event Header Cover section in Cover Images; `cropperModal` moved to top-level `<body>`
- `admin/js/admin-i18n.js` — i18n keys for Header Cover labels (TH + EN)
- `setup.php` — `header_cover_image` in CREATE TABLE, ALTER TABLE fallback, `run_all_migrations`, `$allTablesOk`, `$migrationChecks`
- `config/app.php` — version bump to v9.2.0

---

## [9.1.0] - 2026-05-05

### Added
- 📖 **"▼ อ่านเพิ่มเติม" button on homepage event cards** — event cards (`.event-card`) in the homepage grid now show description text (hidden via `height:0; overflow:hidden`) so `scrollHeight > clientHeight + 2` always fires for non-empty descriptions; button is injected by JS and aligned to the bottom of the card (`margin-top: auto` in flex column); clicking the button or the hidden description opens an event modal with full text + "📋 ดูตารางเวลา" link; `e.preventDefault()` + `e.stopPropagation()` guard navigation because `.event-card` is an `<a>` tag
- 📋 **Event description block on event timetable page** — `<div class="event-desc-block">` rendered before `.calendar-container` when `$eventMeta['description']` is non-empty; includes a styled title (`ℹ️ เกี่ยวกับงาน` / `About this Event` / `イベント概要`) and a card-framed body with left-border accent; translation key `event.about` added to `js/translations.js`
- 📄 **`past-events.php` redesign** — page now renders event cards using the same `events-grid` / `event-card` markup as the homepage (cover image with 4:3 fallback chain, badge, name, date, description button); 20 items per page; old `program-card` markup and `openModal()` JS replaced with `openEventCardModal()` matching the homepage pattern
- 📑 **Pagination on `credits.php`** — 20 credits per page in both views:
  - **Event-specific view**: standard `?page=N` pagination
  - **Global view** (`/credits`): flat pagination across all credits (20/page); current page's slice is re-grouped by event after slicing so group headings remain correct; pagination nav appears below the groups
  - URL base: global → `get_base_path() . '/credits'`; event-specific → `event_url('credits.php', $eventSlug)`

### Changed
- `styles/index.css` — `.event-card-description` and `.program-card-description` use `height:0; overflow:hidden; margin:0` (not `display:none`) so `scrollHeight` remains readable by the read-more JS
- `.event-card-body .program-card-readmore` — `margin-top: auto` pushes the button to the card bottom in a flex column context
- `past-events.php` — `$perPage` changed from 5 → 20; JS section rewritten to use `openEventCardModal()` with `.event-card-*` selectors

### New CSS
- `styles/credits.css` — `.pagination` block (copied from `styles/index.css`) so the credits page renders pagination without depending on `index.css`
- `styles/index.css` — `.event-desc-block`, `.event-desc-title`, `.event-desc-body` for the event description section

### Files Changed
- `index.php` — added `event-card-description` div to event card body; `openEventCardModal()` JS function + forEach handler; `event-desc-block` PHP block before calendar-container
- `past-events.php` — redesigned to use `events-grid` / `event-card` markup; 20 items per page; JS rewritten to use `openEventCardModal()` with `.event-card-*` selectors
- `credits.php` — pagination logic (20/page) for both event-specific and global views; global view re-groups paged slice by event_id
- `styles/index.css` — `.event-card-description` + `.program-card-description` use `height:0; overflow:hidden`; `.event-card-body .program-card-readmore` uses `margin-top:auto`; `.event-desc-block`, `.event-desc-title`, `.event-desc-body` styles
- `styles/credits.css` — added `.pagination` block for credits page pagination
- `js/translations.js` — added `event.about` key (TH/EN/JA)
- `config/app.php` — version bump to 9.1.0

> **Test Coverage**: All existing automated tests unaffected (no PHP logic, DB, or API changes)

## [9.0.0] - 2026-05-04

### Added
- 🔍 **FTS5 Full-Text Search** — site-wide full-text search across programs, events, and artists powered by SQLite FTS5 with `unicode61` tokenizer
  - **Search bar** — persistent search form (`fts-search-form`) on the homepage listing; submits `?q=` to the same page; ✕ clear button; accessible `aria-label`
  - **2-column results layout** — Main column (Programs, paginated 10/page) + Sidebar (Events top 5, Artists top 10); mobile stacks to single column
  - **Program results** — each card shows title, FTS5 highlighted snippet (`<mark>`), event name, date, time, and venue; links to the event page; sorted newest → oldest (`p.start DESC`)
  - **Event sidebar** — top 5 matching active events sorted by `start_date DESC`; links to event page
  - **Artist sidebar** — top 10 matching artists sorted by FTS5 relevance rank; links to `/artist/{id}`; "กลุ่ม / วง" badge for groups
  - **Pagination** — ellipsis-aware page navigator with `?q=...&page=N` URLs; `fts5_count_programs()` for total count
  - **FTS5 helpers** (`functions/search.php`) — `fts5_available()`, `fts5_escape()`, `fts5_count_programs()`, `fts5_search_programs()` (+offset), `fts5_count_events()`, `fts5_search_events()` (+offset), `fts5_search_artists()`, `fts5_search_all()`, `fts5_rebuild_all()`
  - **Graceful LIKE fallback** — all search functions fall back to `LIKE '%query%'` queries when FTS5 virtual tables are unavailable
  - **Admin integration** — Programs / Events / Artists list views in admin use FTS5 when `?q=` is present; `fts5_rebuild_all()` called after ICS import
  - **Public API** — `api.php?action=search&q=` returns JSON `{ programs, events, artists }`

### Schema
- **`programs_fts`** — FTS5 virtual table (`content=programs`; indexes `title`, `categories`, `organizer`, `description`)
- **`events_fts`** — FTS5 virtual table (`content=events`; indexes `name`, `description`)
- **`artists_fts`** — FTS5 virtual table (`content=artists`; indexes `name`)
- **6 auto-sync triggers** — `programs_ai/au/ad`, `events_ai/au/ad`, `artists_ai/au/ad` keep FTS indexes in sync on INSERT / UPDATE / DELETE

### New Files
- `functions/search.php` — all FTS5 helper functions
- `tools/migrate-add-fts5.php` — idempotent migration: creates FTS tables, triggers, and rebuilds indexes
- `tests/Fts5Test.php` — 45 automated tests (schema, triggers, helpers, API, UI, translations, setup)

### Files Changed
- `config.php` — added `require_once functions/search.php`
- `index.php` — FTS search block: PHP variables + 2-column HTML results layout with programs main + sidebar
- `api.php` — added `action=search` case calling `fts5_search_programs/events/artists`
- `admin/api.php` — `listPrograms()`, `listEvents()`, `listArtists()` use FTS when `?q=` present; `fts5_rebuild_all()` after ICS import
- `styles/index.css` — `.fts-search-form`, `.fts-search-input`, `.fts-results-layout` (2fr/1fr grid), `.fts-results-sidebar`, `.fts-result-item`, `.fts-sidebar-item`, `.fts-section-title`, `.fts-pagination`, `.fts-page-btn`, `.fts-result-snippet mark`
- `js/translations.js` — `search.*` keys (placeholder, button, resultsFor, noResults, programs, events, artists, group) in TH / EN / JA
- `setup.php` — FTS5 table and trigger presence checks (`$hasFts5Tables`, `$hasFts5Triggers`)
- `tests/run-tests.php` — registered `Fts5Test` suite
- `config/app.php` — version bump to 9.0.0

> **Test Coverage**: All 45 Fts5Test tests pass (100% pass rate)

---

## [8.0.0] - 2026-05-04

### Added
- 🎨 **Homepage Redesign — Ticket-Marketplace Style** — complete UI overhaul of the event listing homepage
  - **Hero Carousel** — 16:9 featured event slides; auto-rotates every 5s; pauses on hover; swipe support on mobile; prev/next/dots navigation; single slide renders as a static banner (no nav buttons); theme-gradient fallback when no cover image is set
  - **Hero + Calendar side-by-side** — two-column layout (hero 2fr / calendar 1fr); stacks vertically on mobile; compact calendar reduces font and cell sizes via `.listing-cal-compact`
  - **Events Grid** — 4-column responsive grid (3-col ≤1024px, 2-col ≤768px, 1-col ≤480px) replacing the previous vertical card list; entire card is clickable
  - **Categories Tiles** — aggregates `programs.program_type` across all active events; displays icon and count; 3-column grid; hidden when no program types exist
  - **Dual Cover Image System** — `events.cover_image` (Hero 16:9, 1600×900) and `events.cover_image_card` (Card 4:3, 800×600) managed independently
  - **Cropper.js integration** — lazy-loaded from CDN; aspect ratio locked to 16:9 (Hero) or 4:3 (Card); `getCroppedCanvas()` performs client-side crop → blob → upload; shared modal changes aspect ratio based on `cover_type`
  - **Admin Cover Upload UI** — two separate sections (Hero / Card) in the Event edit modal; individual previews and delete buttons; CSRF token sent via `X-CSRF-Token` header
  - **Event Card fallback chain**: `cover_image_card` → `cover_image` → first `event_pictures` row → theme gradient

### Changed
- **Homepage carousel threshold** — Hero Carousel renders with ≥ 1 event having a cover image (previously ≥ 2)
- **Listing pagination** — increased from 10 to 12 events per page to align with the 4-column grid layout
- **Listing cache** (`query_listing.json`) — added `event_covers` key (first event_picture per event_id) and `program_type_counts` key (aggregated counts by type)
- **`listing-cal-wrap`** — removed redundant `box-shadow` (duplicated by the `.listing-cal-compact` wrapper)
- **`hero-calendar-row`** — switched from `margin` to `padding: 30px 30px 0` to align with `.program-listing` indentation
- **`categories-section`** — uses `padding: 20px 30px 8px` to match `.program-listing` indentation
- **`listing-cal-wrap` / `listing-cal-compact` background** — changed from hardcoded `#fff` to `var(--sakura-bg-soft)` so the calendar tint adapts to all 11 themes automatically

### New Files
- `tools/migrate-add-event-cover-image-column.php` — idempotent migration adding `events.cover_image`
- `tools/migrate-add-event-cover-image-card-column.php` — idempotent migration adding `events.cover_image_card`
- `tests/EventCoverTest.php` — 44 automated tests

### Files Changed
- `index.php` — homepage listing branch restructured (hero carousel, events grid, categories tiles, listing cache extensions)
- `functions/helpers.php` — added `get_event_cover_image()`
- `admin/api.php` — added `uploadEventCover()`, `deleteEventCover()` with `cover_type` param (hero/card)
- `admin/index.php` — Cover Images section with Cropper.js crop modal (Hero 16:9 / Card 4:3)
- `admin/js/admin-i18n.js` — added cover image keys (TH + EN)
- `js/translations.js` — added `homepage.categoriesTitle`, `homepage.programs` keys (TH/EN/JA)
- `styles/index.css` — hero carousel, events grid, categories tiles, compact calendar CSS; `var(--sakura-bg-soft)` for calendar background
- `setup.php` — `cover_image` and `cover_image_card` column checks and migrations
- `tests/EventCoverTest.php` — new test suite (44 tests)
- `tests/run-tests.php` — registered `EventCoverTest` suite
- `config/app.php` — version bump to 8.0.0

> **Test Coverage**: All 44 EventCoverTest tests pass (100% pass rate)

---

## [7.4.1] - 2026-04-27

### Fixed
- **Admin program edit/duplicate showing wrong end date** — `toISOString()` converts Date to UTC before extracting the date portion, but `toTimeString()` uses local time; for Bangkok users (UTC+7), programs ending before 07:00 local time would display the end date as one day earlier than actual
  - Added `localDateStr(d)` helper that uses `d.getFullYear()`, `d.getMonth()`, `d.getDate()` (local timezone) instead of `toISOString()`
  - Fixed 4 locations: `openAddModal()` default date, `editEvent()`, `duplicateEvent()`, and ICS import preview edit form

### Files Changed
- `admin/index.php` — added `localDateStr()` helper; replaced all `date.toISOString().split('T')[0]` with `localDateStr(date)` in program form date population

## [7.4.0] - 2026-04-25

### Added
- **Bulk delete for event pictures** in Admin › Events picture section
  - New "☑ เลือก" toggle button enters select mode; clicking thumbnails toggles selection (blue outline + ✓ checkmark)
  - Yellow bulk-action bar shows selected count + "🗑️ ลบที่เลือก" button + Cancel
  - Deletes all selected pictures sequentially via existing `event_picture_delete` API; shows toast summary on completion
  - Drag-sort and preview lightbox automatically disabled while in select mode
  - "ยกเลิก" button or closing the modal exits select mode and deselects all

### Files Changed
- `admin/index.php` — CSS (`.pic-thumb.ep-selected`, `#eventPicSelectBar`), HTML (select button + bar), JS (`_epSelectMode` var, `_epPicClick`, `_updateSelectCount`, `togglePicSelectMode`, `bulkDeleteEventPictures`; updated `renderEventPictureGrid`, `resetEventPictureSection`)
- `admin/js/admin-i18n.js` — `event.selectMode`, `event.cancelSelect`, `event.deleteSelected`, `event.selectedCount` keys (TH + EN)
- `config/app.php` — version bump to 7.4.0

> **Test Coverage**: All 5,053 automated tests pass (100% pass rate)

## [7.3.0] - 2026-04-25

### Added
- **Upload progress bar** in Admin › Events picture section
  - Replaces simple spinner with a live progress bar + `X/N (Y%)` counter that updates after each file
  - After all uploads complete: green summary `✓ อัปโหลดสำเร็จ N รูป`; orange summary when some files failed (e.g. `✓ 2 รูปสำเร็จ (1 รูปล้มเหลว)`)
  - Progress bar turns orange/red on partial/total failure; auto-hides after 3 s
- **Click thumbnail to preview** — clicking any picture in the admin thumbnail grid opens a fullscreen lightbox
  - Full-size image centered over dark overlay; caption displayed below image
  - Prev/next navigation buttons (hidden when only one picture); keyboard support: Escape to close, ← → to navigate
  - Overlay click also closes the lightbox; keyboard listener added/removed on open/close

### Files Changed
- `admin/index.php` — CSS (progress bar + lightbox classes), HTML (progress block replaces spinner, lightbox HTML), JS (module vars, updated render/upload/reset functions, 5 new lightbox functions)
- `admin/js/admin-i18n.js` — `event.uploadDoneAll`, `event.uploadFailed` keys (TH + EN)
- `config/app.php` — version bump to 7.3.0

> **Test Coverage**: All 5,053 automated tests pass (100% pass rate)

## [7.2.0] - 2026-04-24

### Changed
- **Event pictures shard by event ID** — uploaded pictures are now stored in `uploads/events/{event_id}/{uniqid}.jpg` instead of the flat `uploads/events/{event_id}_{uniqid}.jpg`; prevents the folder from growing unbounded as events accumulate
  - Parent `uploads/events/.htaccess` Apache rules apply to all subdirectories automatically — no per-shard `.htaccess` needed
  - Backward compatible: existing pictures stored with the old flat path are still served and deleted correctly (DB stores the full relative path)

### Files Changed
- `admin/api.php` — `uploadEventPicture()`: `$uploadsDir` now includes `/{eventId}` shard; `$filename` drops the redundant `{eventId}_` prefix; `$relPath` reflects new structure
- `config/app.php` — version bump to 7.2.0

> **Test Coverage**: All 5,053 automated tests pass (100% pass rate)

## [7.1.0] - 2026-04-24

### Added
- **Drag-and-drop picture reordering** in Admin › Events › edit modal picture section
  - Each thumbnail card is now draggable (HTML5 Drag and Drop API, no external library)
  - Drag handle icon (⠿) shown on each card; dashed blue outline on drag-over target
  - Drop position determined by cursor midpoint (insert before or after target card)
  - Order auto-saved on drop via `event_pictures_reorder` API; hint shows "⏳ Saving…" → "✓ Order saved" → reverts to "Drag to reorder" after 2 s
  - i18n keys `event.dragToReorder`, `event.savingOrder`, `event.orderSaved` (TH + EN)

### Files Changed
- `admin/index.php` — `.pic-thumb` CSS classes, drag handle, `initPictureDragSort()`, `saveEventPictureOrder()`, order hint div
- `admin/js/admin-i18n.js` — 3 new keys (TH + EN)
- `config/app.php` — version bump to 7.1.0

> **Test Coverage**: All 5,053 automated tests pass (100% pass rate)

## [7.0.0] - 2026-04-24

### Added
- **Event Pictures Gallery** — upload and display photos per event at natural aspect ratios (no cropping)
  - Admin selects gallery layout per event: `grid1` (1 col), `grid2` (2 col), `grid3` (3 col, default), `masonry` (CSS column-count)
  - Stored in new `events.gallery_template TEXT DEFAULT 'grid3'` column
  - New `event_pictures` table: `id, event_id, filename, caption, display_order, created_at`; ON DELETE CASCADE; 2 indexes
  - Upload via Admin › Events › edit modal — picture section with file picker (multi-select), thumbnail grid, delete × button
  - Images processed by PHP GD new `processAndSaveImage()` with `mode='fit'` (scale to fit 1200×900, no upscale, no crop); JPEG 85%
  - Public gallery rendered in `index.php` before cross-event section; CSS class `template-{value}` drives layout
  - Responsive: grid3 → 2 col at ≤768px; all grid templates → 1 col at ≤480px; masonry col-count scales down
  - Lightbox with keyboard nav (Escape/ArrowLeft/ArrowRight), caption display
- **`tools/migrate-add-event-pictures-table.php`** — idempotent migration (CREATE TABLE + indexes + ALTER TABLE gallery_template)
- **`uploads/events/`** directory + `.htaccess` (blocks PHP execution + directory listing)
- **57 tests** in `tests/EventPicturesTest.php` — **รวม 5,053 tests** (18 suites, 100% pass rate)

### Changed
- `processAndSaveArtistImage()` in `admin/api.php` refactored as thin wrapper over new `processAndSaveImage(src, dst, maxW, maxH, imgType, mode)` — backward compatible, no behavior change

### Files Changed
**New files:**
- `tools/migrate-add-event-pictures-table.php` — idempotent DB migration
- `uploads/events/.htaccess` — blocks PHP execution + directory listing
- `tests/EventPicturesTest.php` — 57 automated tests

**Modified files:**
- `admin/api.php` — processAndSaveImage(), 4 event picture actions, gallery_template in CRUD
- `index.php` — event_pictures query, gallery HTML, lightbox HTML + JS
- `admin/index.php` — gallery template dropdown, picture section UI + JS functions
- `styles/index.css` — gallery grid templates CSS + lightbox CSS
- `js/translations.js` — `section.eventGallery` key (TH/EN/JA)
- `admin/js/admin-i18n.js` — gallery template + picture section keys (TH/EN)
- `setup.php` — event_pictures table + gallery_template column checks
- `Dockerfile` — mkdir uploads/events
- `tests/run-tests.php` — registered EventPicturesTest
- `config/app.php` — version bump to 7.0.0

> **Test Coverage**: All 5,053 automated tests pass (100% pass rate)

## [6.5.0] - 2026-04-22

### Added
- **Comprehensive SEO Support** — zero-DB-query SEO helper layer wired into all public pages; search engines and social networks now see full meta descriptions, Open Graph tags, Twitter Cards, canonical URLs, noindex directives, and JSON-LD structured data
- **`functions/seo.php`** — 4 CLI-safe helper functions:
  - `seo_full_url(string $path): string` — builds absolute URL; returns `''` in CLI context (prevents broken tags in test runs); detects HTTPS via `$_SERVER['HTTPS']` + `HTTP_X_FORWARDED_PROTO`
  - `seo_truncate(string $text, int $max = 155): string` — strips HTML, normalises whitespace, snaps to word boundary (Thai-text guard: never snaps back more than 50% of `$max`), appends `…` on truncation
  - `seo_render_meta(array $opts): void` — renders `<meta name="robots">` (noindex), `<meta name="description">`, `<link rel="canonical">`, full Open Graph (`og:type/title/site_name/locale/url/description/image+width+height`), full Twitter Card (`twitter:card/title/description/image`); `twitter:card = summary_large_image` when `og_image` provided, else `summary`; all values escaped with `htmlspecialchars(ENT_QUOTES, 'UTF-8')`
  - `seo_render_json_ld(array $schema): void` — emits `<script type="application/ld+json">` with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT`; no output for empty array
- **`index.php` SEO** — event meta description (`{name} — ตารางกิจกรรม {date} · {venue} · {count} ศิลปิน | {site}`); canonical URL; WebSite JSON-LD with SearchAction; Event JSON-LD (when viewing specific event) with startDate/endDate/eventStatus/organizer/location
- **`artist.php` SEO** — artist/group description with event count; `og:image` from cover or display picture when available; `og:type = profile`; MusicGroup/Person JSON-LD with member list (for groups); BreadcrumbList JSON-LD
- **`artists.php` SEO** — portal description with group/solo counts; ItemList JSON-LD for top 20 artists
- **`credits.php`, `contact.php`, `how-to-use.php`, `past-events.php` SEO** — canonical URL + meta description on each page
- **`my.php`, `my-favorites.php` noindex** — `<meta name="robots" content="noindex, nofollow">` prevents personal/user-specific pages from being indexed
- **`tests/SeoTest.php`** — 63 automated tests covering all 4 functions, all output scenarios (description tag, canonical, noindex, og:type/title/description/url/image/site_name/locale, twitter:card variants, JSON-LD script tag, valid JSON, Unicode not escaped, empty array), and source-level checks on all 8 modified public pages

### Files Changed
**New files:**
- `functions/seo.php` — SEO helper functions (4 functions)
- `tests/SeoTest.php` — 63 automated tests

**Modified files:**
- `config.php` — added `require_once __DIR__ . '/functions/seo.php'`
- `index.php` — SEO meta + WebSite schema + Event schema (after `</title>`, before GA block)
- `artist.php` — SEO meta + MusicGroup/Person schema + BreadcrumbList (after `</title>`, before GA block)
- `artists.php` — SEO meta + ItemList schema (after `</title>`, before GA block)
- `credits.php` — SEO meta (canonical + description)
- `contact.php` — SEO meta (canonical + description)
- `how-to-use.php` — SEO meta (canonical + description)
- `my.php` — noindex meta
- `my-favorites.php` — noindex meta
- `past-events.php` — SEO meta (canonical + description)
- `tests/run-tests.php` — added `SeoTest` to test file list
- `config/app.php` — version bump to 6.5.0

> **Test Coverage**: All 4,331 automated tests pass (100% pass rate)

## [6.4.1] - 2026-04-21

### Fixed
- 🐛 **Google AdSense leaderboard (728×90) not rendering** — `render_ad_unit()` in `functions/ads.php` used `data-ad-format="auto"` + `data-full-width-responsive="true"` for all slot types including fixed-size leaderboard and rectangle; AdSense interprets these attributes as responsive mode and refuses to render without an explicit container size; fixed by using slot-specific `<ins>` attributes: leaderboard → `style="display:inline-block;width:728px;height:90px"` (no `data-ad-format`), rectangle → `style="display:inline-block;width:300px;height:250px"` (no `data-ad-format`), responsive → `style="display:block"` + `data-ad-format="auto"` + `data-full-width-responsive="true"` (unchanged)
- 🎨 **Leaderboard hidden on mobile** — added `@media (max-width:767px) { .ads-leaderboard { display:none } }` to `styles/common.css`; 728px fixed-width ad overflows on narrow screens and AdSense may not serve it at all below that width

### Files Changed
- `functions/ads.php` — slot-specific `<ins>` style + attributes per type
- `styles/common.css` — hide `.ads-leaderboard` on mobile (< 768px)
- `config/app.php` — version bump to 6.4.1

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.4.0] - 2026-04-21

### Added
- **Google Admin UI** — Google Analytics & Google AdSense configurable via Admin UI › Settings › 🔵 Google sub-tab; same pattern as Telegram config (JSON file + PHP loader + API)
- **`config/google-config.json`** — stores `ga_id`, `ads_client`, `ads_slot_leaderboard`, `ads_slot_rectangle`, `ads_slot_responsive`; protected by existing `config/.htaccess` (`Deny from all` for `.json`)
- **`analytics_config_get` / `analytics_config_save`** API endpoints in `admin/api.php` — admin-role required; reads/writes `config/google-config.json`; fields HTML-escaped on output; `updated_at` timestamp on save
- **`loadAnalyticsSetting()` / `saveAnalyticsSetting()`** JS functions in `admin/index.php` — auto-loaded with Settings tab; populate/save all 5 fields; uses `decodeHtml()` pattern from other settings
- **i18n keys** in `admin/js/admin-i18n.js` — `settings.subtab.analytics`, `settings.analytics*` keys in both TH and EN dictionaries

### Changed
- **`config/analytics.php`** renamed to **`config/google.php`** — combined GA + AdSense loader; reads from `config/google-config.json` (like `config/telegram.php`), falling back to empty strings; constants `GOOGLE_ANALYTICS_ID`, `GOOGLE_ADS_CLIENT`, `GOOGLE_ADS_SLOT_*` unchanged for backward compatibility
- **Admin UI sub-tab** label changed from `📊 Analytics` to `🔵 Google`

### Documentation
- **Admin Help updated (TH + EN)** — `admin/help.php` and `admin/help-en.php` updated to cover v6.4.0:
  - Settings sub-tabs table updated from 6 → **7 sub-tabs**; added `🔵 Google` row (between Telegram and Disclaimer)
  - New `🔵 Google Services (v6.3.0–6.4.0)` section covering Google Analytics (GA4 Measurement ID) and Google AdSense (Publisher ID + 3 slot IDs), setup steps, and security callouts
  - Roles table updated: `Settings (Title + Theme + Disclaimer)` → `Settings (Title + Theme + Google + Disclaimer)`
- **All .md files updated** — README.md, API.md, PROJECT-STRUCTURE.md, SECURITY.md, SETUP.md, INSTALLATION.md, TESTING.md updated with v6.2.0–v6.4.0 entries (Sitemap, AdSense, Google Admin UI), new file listings, API endpoint docs, security checklist, and test counts

### Files Changed
**New files:**
- `config/google.php` — (renamed from `config/analytics.php`) GA + AdSense loader from JSON
- `config/google-config.json` — JSON config for GA + AdSense

**Modified files:**
- `config.php` — updated require to `config/google.php`
- `admin/api.php` — added `analytics_config_get` + `analytics_config_save` endpoints + functions; config file path updated to `google-config.json`
- `admin/index.php` — added 🔵 Google sub-tab button + HTML panel + JS functions + loader call
- `admin/js/admin-i18n.js` — added analytics i18n keys (TH + EN); sub-tab label updated to 🔵 Google
- `admin/help.php` — Settings sub-tabs (6→7), 🔵 Google section, Roles table
- `admin/help-en.php` — Settings sub-tabs (6→7), 🔵 Google section, Roles table
- `config/app.php` — version bump to 6.4.0

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.3.0] - 2026-04-21

### Added
- **Google AdSense System** — monetization system with enable/disable identical to Google Analytics (`GOOGLE_ADS_CLIENT = ''` = disabled)
- **`render_ad_unit(string $slot, string $class = ''): void`** helper in `functions/ads.php` — renders `<ins class="adsbygoogle">` with correct attributes; silently skips if publisher ID or slot ID is empty; supports 3 slot types: `leaderboard` (728×90), `rectangle` (300×250), `responsive` (auto)
- **AdSense `<head>` script** added to all 7 public pages (`index.php`, `artist.php`, `artists.php`, `how-to-use.php`, `credits.php`, `contact.php`, `past-events.php`) — loaded only when `GOOGLE_ADS_CLIENT` is set
- **8 ad placements** across public pages: leaderboard after event-detail header (`index.php`), responsive before cross-event section (`index.php`), rectangle after artist header (`artist.php`), leaderboard after header (`artists.php`), responsive before footer on `how-to-use.php`, `credits.php`, `contact.php`
- **`.ads-unit` CSS classes** in `styles/common.css` — `.ads-leaderboard` (max-width 728px), `.ads-rectangle` (max-width 336px), `.ads-responsive` (full width)
- **4 new constants** in `config/google.php` — `GOOGLE_ADS_CLIENT`, `GOOGLE_ADS_SLOT_LEADERBOARD`, `GOOGLE_ADS_SLOT_RECTANGLE`, `GOOGLE_ADS_SLOT_RESPONSIVE`

### Files Changed
**New files:**
- `functions/ads.php` — `render_ad_unit()` helper function

**Modified files:**
- `config/google.php` — added `GOOGLE_ADS_CLIENT` + 3 slot constants
- `config.php` — added `require_once functions/ads.php`
- `index.php` — AdSense head script + 2 ad placements
- `artist.php` — AdSense head script + 1 ad placement
- `artists.php` — AdSense head script + 1 ad placement
- `how-to-use.php` — AdSense head script + 1 ad placement
- `credits.php` — AdSense head script + 1 ad placement
- `contact.php` — AdSense head script + 1 ad placement
- `past-events.php` — AdSense head script (no placement)
- `styles/common.css` — added `.ads-unit` CSS classes
- `config/app.php` — version bump to 6.3.0

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.2.0] - 2026-04-20

### Added
- **Dynamic XML Sitemap** (`sitemap.php`) — generates RFC-compliant `sitemap.xml` at `/sitemap.xml` via `.htaccess` rewrite; includes static pages (`/`, `/artists`, `/how-to-use`, `/contact`, `/credits`), all active events (`/event/{slug}` + `/event/{slug}/credits`, excluding default slug which maps to root), and all artist profile pages (`/artist/{id}`); `lastmod` derived from `updated_at` in DB; per-URL `changefreq` and `priority` tuned by page type
- **Sitemap file cache** — output captured with `ob_start()`/`ob_get_clean()` and saved to `cache/sitemap.xml`; subsequent requests served via `readfile()` without touching SQLite; TTL 1 hour (`SITEMAP_CACHE_TTL`); `invalidate_sitemap_cache()` called automatically by Admin API on any event or artist write (create/update/delete — 6 call sites total); also cleared by `invalidate_all_caches()` on DB restore
- **Dynamic robots.txt** (`robots.php`) — serves `/robots.txt` via `.htaccess` rewrite; auto-injects `Sitemap:` directive with correct absolute URL (protocol + host + base path); Disallows only user-specific paths (`/my/`, `/my-favorites/`) — internal directories intentionally omitted to avoid disclosing server structure
- **`.htaccess` rewrite rules** — added `^sitemap\.xml$` → `sitemap.php` and `^robots\.txt$` → `robots.php` at the top of the rewrite block (before all other rules)

### Files Changed

**New files:**
- `sitemap.php` — dynamic XML sitemap generator with file cache
- `robots.php` — dynamic robots.txt with auto-injected Sitemap URL
- `robots.txt` — static fallback (superseded by `robots.php` via rewrite)

**Modified files:**
- `config/cache.php` — added `SITEMAP_CACHE_FILE` and `SITEMAP_CACHE_TTL` constants
- `functions/cache.php` — added `invalidate_sitemap_cache()`; added `sitemap.xml` to `invalidate_all_caches()` patterns
- `admin/api.php` — added `invalidate_sitemap_cache()` to `createEvent`, `updateEvent`, `deleteEvent`, `createArtist`, `updateArtist`, `deleteArtist`
- `.htaccess` — added rewrite rules for `sitemap.xml` and `robots.txt`
- `config/app.php` — version bump to 6.2.0

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

---

## [6.1.5] - 2026-04-18

### Fixed
- **`setup.php` missing migration check for v6.1.3 review columns** — `program_requests.admin_note`, `reviewed_at`, `reviewed_by` columns added in v6.1.3 were not tracked in the setup wizard's Migration Status checklist; existing databases without these columns would pass the `$allTablesOk` check and not surface the gap
  - Added `$hasRequestReviewColumns` detection via `PRAGMA table_info(program_requests)`
  - Added `$hasRequestReviewColumns` to `$allTablesOk` condition
  - Added v6.1.3 entry to `$migrationChecks` pointing to `run_all_migrations` action
  - Corrected migration block comment from v6.1.2 → v6.1.3
- **Back button in admin note form does nothing** — `showAdminNoteForm()` back button called `openRequestDetail(window._currentReqData)` which does not exist; changed to `viewRequestDetail(window._currentReqData?.id)` to correctly re-render the detail modal

### Files Changed

**Modified files:**
- `setup.php` — added `$hasRequestReviewColumns` detection, `$allTablesOk` condition, and `$migrationChecks` entry for v6.1.3
- `admin/index.php` — fixed back button in `showAdminNoteForm()` to call `viewRequestDetail()` with the stored request ID

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.4] - 2026-04-18

### Added
- **Admin note input on approve/reject** — replaced the bare `confirm()` dialog with an inline admin note form inside the request detail modal; clicking Approve or Reject now shows a textarea (optional) for an admin note before submitting; a Back button returns to the detail view without closing the modal
- `admin_note` is saved to `program_requests` and displayed in the detail modal under "หมายเหตุ Admin" when set; `reviewed_at` and `reviewed_by` are also recorded

### Fixed
- **`approveReq`/`rejectReq` ReferenceError** — table-row Approve/Reject buttons still called the old removed functions; replaced with `viewRequestDetail(id); showAdminNoteForm(id, action)` so they open the detail modal then immediately show the note form

### Files Changed

**Modified files:**
- `admin/index.php` — replaced `approveReq()`/`rejectReq()` with `showAdminNoteForm()` + `confirmReqAction()`; table-row buttons updated to match
- `admin/api.php` — `approveRequest()` and `rejectRequest()` now write `admin_note` (max 500 chars) from request body
- `admin/js/admin-i18n.js` — added `req.adminNote`, `req.adminNotePlaceholder`, `common.optional` keys (TH + EN)

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.3] - 2026-04-18

### Fixed
- **Admin approve/reject request fails** — `admin/api.php` `approveRequest()` and `rejectRequest()` UPDATE statements referenced `admin_note`, `reviewed_at`, `reviewed_by` columns that did not exist in the `program_requests` table (the schema was never updated when these fields were added to the API logic); SQLite rejected the UPDATE with a PDOException caught as a silent failure, so every approve/reject returned an error
  - Added the three missing columns to the live DB via `ALTER TABLE`
  - Updated `setup.php` `CREATE TABLE program_requests` to include the new columns for fresh installs
  - Added a migration block in `setup.php` `run_all_migrations` to add the columns to existing databases

### Files Changed

**Modified files:**
- `admin/api.php` — restored full `approveRequest()` and `rejectRequest()` UPDATE statements with `admin_note`, `reviewed_at`, `reviewed_by` now that columns exist
- `setup.php` — added `admin_note TEXT`, `reviewed_at DATETIME`, `reviewed_by TEXT` to `CREATE TABLE program_requests`; added migration block for existing DBs

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.2] - 2026-04-18

### Fixed
- **Request form submit fails with "Submit failed"** — `api/request.php` INSERT used stale column names that no longer match the actual `program_requests` table schema (renamed in a prior migration); the query silently threw a PDOException that was caught and returned as `{"success":false,"message":"Submit failed"}`
  - `type` → `request_type` (column has `NOT NULL` constraint — root cause of the exception)
  - `title` → `summary`
  - `requester_note` → `note`
- **Admin Requests tab not showing submitted requests** — `admin/api.php` `listRequests()` and `approveRequest()` used the same stale column names when reading back from the DB, causing the admin UI (which references `r.type`, `r.title`, `r.requester_note`) to receive `undefined` for all three fields and render nothing
  - `listRequests()`: changed `SELECT *` to `SELECT *, request_type AS type, summary AS title, note AS requester_note`; fixed PHP reference `$req['type']` → `$req['request_type']`
  - `approveRequest()`: same SELECT alias fix; fixed `$req['type']` → `$req['request_type']` and `$req['title']` → `$req['summary']` in INSERT/UPDATE statements

### Files Changed

**Modified files:**
- `api/request.php` — corrected column names in `$data` array keys and INSERT statement to match actual DB schema (`request_type`, `summary`, `note`)
- `admin/api.php` — added column aliases in `listRequests()` and `approveRequest()` SELECT queries; fixed PHP references to use actual column names when reading request data

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.1] - 2026-04-18

### Fixed
- **Telegram cron incorrect datetime comparison (T-separator bug)** — `cron/send-telegram-notifications.php` used raw `BETWEEN` string comparison in SQL for the notification window; programs stored with ISO 8601 `T` separator (e.g. `"2026-04-17T11:00:00"`) were falsely matched because SQLite lexicographic string comparison treats `T` (ASCII 84) as greater than space (ASCII 32), causing `"2026-04-17T11:00:00"` to rank *after* `"2026-04-17 23:53:24"` on the same date — resulting in programs notifying 12+ hours after their actual start time (e.g. an 11:00 program triggering a notification at 23:48)
  - Fixed by wrapping both sides of the comparison with SQLite's `datetime()` function: `datetime(p.start) BETWEEN datetime(:windowStart) AND datetime(:windowEnd)`
  - `datetime()` normalises both `T`-separator and space-separator formats to `YYYY-MM-DD HH:MM:SS` before comparison; no timezone conversion occurs since both sides are already in the event's local timezone (Bangkok)

### Files Changed

**Modified files:**
- `cron/send-telegram-notifications.php` — changed `p.start BETWEEN :windowStart AND :windowEnd` to `datetime(p.start) BETWEEN datetime(:windowStart) AND datetime(:windowEnd)`

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.0] - 2026-04-18

### Changed
- **Request form redesign — unified "Add / Edit" button** — merged the separate "Add Program" button and the per-row ✏️ "Edit Request" column into a single `btn-request` button in the action bar
  - **Removed "Edit Request" column** (`<th>` + `<td>` per row) from the program table — reduces table width and UI clutter
  - **New `btn-request` button** (sakura outline style, replacing `btn-warning`); label "📝 แจ้งเพิ่ม / แก้ไข" (TH) / "📝 Request Add / Edit" (EN) / "📝 追加・編集をリクエスト" (JA)
  - **Modal redesign** — added visible type radio toggle (Add / Edit) replacing `<input type="hidden">`; selecting "Edit" reveals a program dropdown that loads all programs in the current event and pre-fills all fields (title, venue, start/end date & time, categories, description) automatically
  - **Added "End Date" field** (`reqEndDate`) to support cross-day programs; auto-syncs when start date changes and end date is empty or earlier
  - **Removed Organizer field** from the form — not needed for the request workflow
  - **Renamed "Categories" label → "Artist / Group"** (TH: ศิลปิน) for clarity across all three languages
  - **`api/request.php` `action=programs`** — added `end`, `categories`, `description` to SELECT; changed ORDER to `start ASC`; increased LIMIT to 200

### Files Changed

**Modified files:**
- `index.php` — removed `<th class="col-edit-request">` + `<td class="program-action-cell">`; changed button to `btn-request`; redesigned modal (type radio, program dropdown, end date field, removed Organizer); added JS functions `onReqTypeChange()`, `onReqStartDateChange()`, `onProgramSelected()`, `loadProgramsIntoSelector()`; updated `openRequestModal()` and `submitRequest()`
- `styles/index.css` — removed `.btn-edit-request`, `.program-action-cell`; added `.btn-request` (sakura outline + `::before` divider)
- `js/translations.js` — updated `button.requestAdd`; changed `modal.categories`; added 7 new keys (`modal.requestType`, `modal.typeAdd`, `modal.typeModify`, `modal.selectProgram`, `modal.selectProgramPlaceholder`, `modal.startDate`, `modal.endDate`) for all three languages (TH/EN/JA)
- `api/request.php` — added columns to `getEvents()` SELECT; changed ORDER and LIMIT

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.0.1] - 2026-04-17

### Fixed
- **`setup.php` init_database missing artist picture columns** — `CREATE TABLE IF NOT EXISTS artists` in the `init_database` action was missing `display_picture` and `cover_picture` columns (added in v6.0.0); fresh installs created the `artists` table without these columns, causing setup migration status to show ⚠️ pending and `ArtistPictureTest` to fail after a clean database initialisation

### Files Changed

**Modified files:**
- `setup.php` — 5 fixes:
  - `init_database` action: added `display_picture TEXT DEFAULT NULL` + `cover_picture TEXT DEFAULT NULL` to `CREATE TABLE IF NOT EXISTS artists`; added `PRAGMA table_info(artists)` + `ALTER TABLE` fallback for existing databases that already have the `artists` table
  - `add_artist_tables` action: added `PRAGMA table_info(artists)` check in `else` branch to `ALTER TABLE` and add missing picture columns
  - `run_all_migrations` action: same `else` branch fix as `add_artist_tables`
  - `$allTablesOk`: added `&& $hasArtistPictureColumns` condition so setup shows ⚠️ when columns are missing
  - `$migrationChecks`: added v6.0.0 entry `artists.display_picture + artists.cover_picture columns` pointing to `add_artist_tables` action
- `config/app.php` — version bump to 6.0.1

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

---

## [6.0.0] - 2026-04-17

### Added
- 🖼️ **Artist Cover & Display Picture System** — full upload pipeline for per-artist profile images stored in `uploads/artists/`
  - **`display_picture`** — circular avatar (400×400 px), shown beside the artist name on `/artist/{id}` and as a hover tooltip on artist badge pills in the program list
  - **`cover_picture`** — landscape banner (1200×400 px), shown as a full-width background in the artist profile header; sakura gradient overlay ensures text remains readable
  - PHP GD **server-side resize + center-crop** (JPEG quality 85%); supports JPG, PNG, GIF, WEBP input; max file size 5 MB
  - `processAndSaveArtistImage()` helper handles aspect-ratio-aware center-crop before downscaling
  - Old file automatically deleted when a new upload replaces it

- 🛠️ **Admin UI — Artist Picture Section in Edit Modal**
  - Appears only in **edit mode** (artist must be saved first); hidden in create/copy modes
  - Display picture preview: 72×72 px circle; cover picture preview: full-width banner strip (80 px height)
  - `📸 เปลี่ยนรูป` button opens native file picker → uploads immediately via AJAX (no form re-submit needed)
  - `🗑️ ลบรูป` button appears only when a picture exists; confirmation dialog before delete
  - `showArtistPictureSection(artist)` / `resetArtistPictureSection()` JS helpers manage section visibility and populate previews on modal open/close
  - Uses `APP_ROOT` (not `BASE_PATH`) to build image URLs — avoids `/admin/uploads/...` wrong-path bug

- 🎨 **Artist Profile Page (`/artist/{id}`) — visual overhaul**
  - Header restructured with `.artist-header-top` flex row: circular avatar (or emoji placeholder 🎤/🎵) on the left, name + meta on the right
  - **Cover picture**: injected as `--cover-url` CSS variable on `.artist-profile-header.has-cover`; dark gradient overlay via `::before` pseudo-element keeps white text legible
  - **Display picture**: `<img class="artist-display-picture">` (100×100 px desktop, 72×72 px mobile) with white border + soft box-shadow
  - Falls back to emoji placeholder div when no picture is set

- 💬 **Hover tooltip on program list artist badges**
  - Single shared `<div id="artistDpTooltip">` with `position: fixed; z-index: 9999` — sidesteps all `overflow: hidden` clipping from `.events-table tbody tr` and `.program-card` parent elements
  - Artist badge wraps render `data-display-pic` + `data-display-name` attributes only when `display_picture` is set (no empty DOM nodes for unpictured artists)
  - JS delegate listener (`mouseover` / `mouseout`) on `document` — positions tooltip above the badge using `getBoundingClientRect()`; auto-flips below if insufficient top space
  - Auto-hides on `scroll` and `resize` events
  - Tooltip shows 60×60 px circle image + artist name

- 🗄️ **Database** — `display_picture TEXT DEFAULT NULL` + `cover_picture TEXT DEFAULT NULL` columns added to `artists` table
- 🔧 **Migration** — `tools/migrate-add-artist-pictures-column.php` (idempotent; also creates `uploads/artists/` directory)
- 📁 **New directory** — `uploads/artists/` with `.htaccess` blocking directory listing and PHP execution
- 🐳 **Dockerfile** — `mkdir -p uploads/artists` + `chmod -R 777 uploads/` added to build step

### Changed
- `admin/api.php` — `listArtists()` and `getArtist()` now SELECT `display_picture` and `cover_picture`; `escapeOutputData()` includes both picture fields; upload endpoint returns `path` (relative) instead of a PHP-computed `url` (which was using wrong `get_base_path()` in admin context)
- `setup.php` — both `CREATE TABLE artists` statements updated with new columns; `$toCreate` and `$dirChecks` arrays include `uploads/` and `uploads/artists/`

### New Admin API Actions
| Action | Method | Description |
|--------|--------|-------------|
| `artist_picture_upload` | POST multipart | Upload display or cover picture; resize; save; update DB |
| `artist_picture_delete` | POST JSON | Delete physical file; clear DB column |

### Files Changed

**New files:**
- `tools/migrate-add-artist-pictures-column.php` — idempotent migration (ALTER TABLE + create uploads dir)
- `uploads/.htaccess` — block directory listing + PHP execution
- `uploads/artists/.htaccess` — same
- `tests/ArtistPictureTest.php` — 61 automated tests

**Modified files:**
- `admin/api.php` — `listArtists()` / `getArtist()` select picture fields; `uploadArtistPicture()`, `deleteArtistPicture()`, `processAndSaveArtistImage()` new functions; upload returns `path` not `url`
- `admin/index.php` — picture section in artist edit modal; `showArtistPictureSection()`, `resetArtistPictureSection()`, `uploadArtistPicture()`, `deleteArtistPicture()` JS functions; uses `APP_ROOT` for image URLs
- `artist.php` — cover picture banner + display picture avatar in header
- `index.php` — `a.display_picture` in program_artists query; `data-display-pic` attributes on badge wraps; shared `#artistDpTooltip` fixed-position JS tooltip
- `styles/artist.css` — `.artist-display-picture`, `.artist-display-placeholder`, `.artist-profile-header.has-cover`, `.artist-header-top` rules
- `styles/index.css` — removed CSS hover card rules (replaced by JS `position:fixed` tooltip)
- `setup.php` — `CREATE TABLE artists` includes new columns; `$toCreate` + `$dirChecks` include `uploads/` and `uploads/artists/`
- `Dockerfile` — `mkdir -p uploads/artists` + `chmod -R 777 uploads/`
- `tests/run-tests.php` — registered `ArtistPictureTest` suite
- `config/app.php` — version bump to 6.0.0

> **Test Coverage**: All 3666 automated tests pass (100% pass rate, 16 suites)

---

## [5.5.3] - 2026-04-17

### Changed
- **Telegram notification window — dynamic half-window** — `cron/send-telegram-notifications.php` replaced hardcoded `±7.5 min` window with `halfWindow = min(notify_before / 2, 7.5) minutes`. Prevents the window from extending past program start for short notify times (e.g. notify=5 min → ±2.5 min; notify≥15 min → ±7.5 min capped).
- **Notify before — dropdown instead of free-text input** — `<input type="number">` replaced with `<select>` offering 5 / 10 / 15 / 30 / 60 minutes in Admin › Settings › Telegram.
- **Cron interval — recommendation instead of config field** — removed `cron_interval_minutes` as a user-configurable setting. Admin UI now shows a dynamic cron recommendation box (`updateCronRecommendation()`) that auto-calculates the optimal crontab interval using `floor(min(notify_before, 15) / 1.5)`, guaranteeing ≥150% coverage. The recommended command updates live when the notify dropdown changes.

### Files Changed
- `cron/send-telegram-notifications.php` — dynamic `$halfWindow = (int)(min(TELEGRAM_NOTIFY_BEFORE_MINUTES / 2, 7.5) * 60)`
- `config/telegram.php` — removed `cron_interval_minutes` from `$defaultTelegramConfig`; removed `TELEGRAM_CRON_INTERVAL_MINUTES` constant
- `config/telegram-config.json` — removed `cron_interval_minutes` field
- `admin/api.php` — removed `cron_interval_minutes` from `getTelegramConfig()` default and `saveTelegramConfig()` save logic
- `admin/index.php` — notify select replaces number input; cron dropdown replaced with recommendation box; `updateCronRecommendation()` JS function; `onchange` wired to notify select; called on config load
- `admin/js/admin-i18n.js` — updated hint keys (TH/EN); added `telegramCronInterval`/`telegramCronIntervalHint`/`telegramCronPathHint` keys; removed `(5-1440)` range hint from notify field
- `config/app.php` — version bump to 5.5.3

---

## [5.5.2] - 2026-04-16

### Fixed
- **Admin event/artist dropdowns showing HTML entities** — After the v5.3.1 server-side HTML escaping change, `escapeOutputData()` in `admin/api.php` began returning HTML-escaped strings (e.g. `Idol&#039;s`) in JSON responses. The `populateEventSelect()` function and artist group dropdown builders in `admin/index.php` were setting `option.textContent = meta.name` directly, which renders the raw string — displaying `&#039;` literally instead of `'`. Fixed by wrapping all 6 affected `option.textContent` assignments with `decodeHtml()`: 3 in `populateEventSelect()` (Recent / Active Events / Past Events optgroups) and 3 in artist group selects (artist form, bulk add-to-group modal, bulk remove-from-group modal).

### Files Changed
- `admin/index.php` — wrapped 6 `option.textContent` assignments in `decodeHtml()` (Recent / Active Events / Past Events optgroups + 3 artist group selects)
- `config/app.php` — version bump to 5.5.2

---

## [5.5.1] - 2026-04-15

### Fixed
- **Telegram group program resolution** — `_telegram_resolve_artists()` in `api/telegram.php` now adds the **parent group ID** when a followed artist is a group member, instead of expanding to all sibling members. This matches the same logic used by My Upcoming Programs (`my.php`): programs tagged to the group entity are now shown correctly via `/today`, `/tomorrow`, `/week`, `/upcoming`, and `/next` commands.
- **Telegram cron notification timezone bug** — `cron/send-telegram-notifications.php` was comparing notification window timestamps using `CAST(strftime('%s', p.start) AS INTEGER)` which treats stored datetimes as UTC. Since `p.start` is stored in event-local time (Bangkok, UTC+7), notifications were delayed by 7 hours (e.g. a program at 18:30 Bangkok triggered a notification at 01:20 the next day). Fixed by converting PHP window timestamps to event-timezone datetime strings and using `p.start BETWEEN :windowStart AND :windowEnd` for string comparison instead.
- **Telegram cron group resolution** — `cron/send-telegram-notifications.php` was expanding followed artists to sibling group members (B, C, D) instead of the parent group entity (G). Programs tagged to the group directly were never matched, causing missed notifications for group-level programs. Fixed to use parent-group lookup identical to `my.php` and `api/telegram.php`.

- **Admin backup timestamps showing UTC** — `createBackup()`, `listBackups()`, `restoreBackup()`, and `uploadAndRestoreBackup()` in `admin/api.php` all used `gmdate()` which returns UTC time. Backup filenames (e.g. `backup_20260415_113000.db`) and the created-at timestamps shown in the Backup UI were 7 hours behind Bangkok time. Fixed by replacing all 5 `gmdate()` calls with `date()`, which uses `Asia/Bangkok` already set via `date_default_timezone_set()` in `config/app.php`.

### Files Changed
- `api/telegram.php` — updated `_telegram_resolve_artists()` SQL from sibling-expansion to parent-group lookup
- `cron/send-telegram-notifications.php` — fixed timezone comparison (strftime → datetime string BETWEEN); fixed group resolution to use parent-group lookup matching `my.php`
- `admin/api.php` — replaced `gmdate()` with `date()` in all 5 backup timestamp calls (createBackup filename, createBackup response, listBackups display, restoreBackup auto-backup filename, uploadAndRestoreBackup auto-backup filename)
- `config/app.php` — version bump to 5.5.1

> **Test Coverage**: All 3064 automated tests pass (100% pass rate)

## [5.5.0] - 2026-04-15

### Added
- **5 New Themes** — expanded theme system from 7 to 12 themes; all new themes follow the same CSS variable pattern as existing themes and support image export (PHP GD palette), Admin theme picker (gradient preview), and per-event theme override
  - 🔴 **Crimson** — bold deep red (`#C62828`), energetic idol-stage feel
  - 🩵 **Teal** — teal/aqua (`#00796B`), fresh summer aquamarine between ocean and forest
  - 🌹 **Rose** — rose-gold (`#E11D48`), warm coral-pink distinct from sakura
  - 🌟 **Amber** — gold/amber (`#F57F17`), premium warm yellow-orange distinct from sunset
  - 🔷 **Indigo** — indigo/navy (`#3F51B5`), deep blue-purple bridging ocean and midnight

### Files Changed
- `styles/themes/crimson.css` — new theme file (28 CSS variables)
- `styles/themes/teal.css` — new theme file (28 CSS variables)
- `styles/themes/rose.css` — new theme file (28 CSS variables)
- `styles/themes/amber.css` — new theme file (28 CSS variables)
- `styles/themes/indigo.css` — new theme file (28 CSS variables)
- `functions/helpers.php` — added 5 themes to `$validThemes` array in `get_site_theme()`
- `image.php` — added 5 RGB palette entries in `$_palettes` for server-side image export
- `admin/index.php` — added 5 options in `conventionTheme` select + 5 entries in `THEME_OPTIONS` array (gradient preview)
- `admin/api.php` — added 5 themes to `$validThemes` whitelist in all 3 occurrences (`createEvent`, `updateEvent`, `saveThemeSetting`)
- `config/app.php` — version bump to 5.5.0

> **Test Coverage**: All 3064 automated tests pass (100% pass rate)

## [5.4.1] - 2026-04-15

### Fixed
- Fixed `<code>` tags disappearing in How-to-Use page Telegram section 4 (Schedule Commands) and section 5 (Notification Controls) when switching languages — moved `data-i18n` from `<li>` to `<span>` wrapping only the description text, keeping command names as static HTML; updated `translations.js` to store description-only values (removed command prefix) for all 3 languages (TH/EN/JA)

### Files Changed
- `how-to-use.php` — restructured `<li>` elements in sections 4 and 5: `<code>` as static HTML, `data-i18n` on `<span>` for description only
- `js/translations.js` — updated 12 translation keys (commands.* and controls.*) × 3 languages to description-only values
- `config/app.php` — version bump to 5.4.1

## [5.4.0] - 2026-04-15

### Added
- **New Telegram Bot Commands** — 8 new commands + 2 modified; full schedule browsing and notification control without leaving Telegram

  **Schedule Commands:**
  - `/tomorrow` — events + program count for tomorrow (same format as `/today`)
  - `/week` — next 7 days grouped by day, each day shows events + program count
  - `/artists` — list all followed artists (fetched from DB by followed IDs, sorted A–Z)
  - `/next` — alias for `/upcoming 1`; shows the single soonest upcoming program

  **Modified Commands:**
  - `/today` — changed format from full per-program detail to condensed event list + count per event
  - `/upcoming [N]` — default changed 5 → 3; now accepts optional numeric argument 1–10; invalid input shows error then proceeds with default 3

  **Notification Control Commands:**
  - `/lang th|en|ja` — change notification language directly in bot (previously required re-linking)
  - `/mute {N}` — mute push notifications for N hours (1–72); shows mute-until time in Asia/Bangkok timezone
  - `/notify on|off` — toggle push notifications on/off without unlinking; opt-out model (absent = on)
  - `/status` — account summary: followed artist count, current language, notification on/off, mute status

- **New favorites JSON fields** (no migration — absent = default):
  - `telegram_mute_until` — Unix timestamp; absent/0 = not muted
  - `telegram_notify_enabled` — bool; absent/null = true (opt-out)

- **Cron notification guards** — `send-telegram-notifications.php` now skips users with `telegram_notify_enabled = false` or active mute before processing any DB queries

- **Helper functions** (`functions/telegram.php`):
  - `find_favorites_by_chat_id(int $chat_id)` — shared shard-scan helper eliminating duplicate code across handlers
  - `telegram_is_muted(array $favData)` — checks `telegram_mute_until` vs `time()`
  - `telegram_notify_is_enabled(array $favData)` — opt-out model check
  - `telegram_format_events_list(array $programs, string $dateStr, string $language, string $context)` — condensed event+count format for `/today`, `/tomorrow`, `/week`
  - `_telegram_resolve_artists(array $ids)` — resolves group members; now used by all program-fetching commands including `/upcoming` and `/next`

- **54 new tests** in `tests/TelegramTest.php` (function existence, unit tests for muted/notify helpers, format tests, message keys for all 16 new keys × 3 languages, handler existence, router routes, cron guards) — **3064 total tests**

### Files Changed
- `functions/telegram.php` — new helpers: `find_favorites_by_chat_id()`, `telegram_is_muted()`, `telegram_notify_is_enabled()`, `telegram_format_events_list()`, `_telegram_resolve_artists()`
- `api/telegram.php` — added handlers for `/tomorrow`, `/week`, `/artists`, `/next`, `/lang`, `/mute`, `/notify`, `/status`; updated `/today` and `/upcoming`
- `cron/send-telegram-notifications.php` — added mute and notify-enabled guards before processing
- `tests/TelegramTest.php` — 54 new tests for new commands and helpers
- `tests/run-tests.php` — registered TelegramTest suite
- `js/translations.js` — 16 new translation keys × 3 languages for Telegram command responses
- `how-to-use.php` — updated Telegram section with new commands documentation

> **Test Coverage**: All 3064 automated tests pass (100% pass rate)

---

## [5.3.1] - 2026-04-14

### Security
- **Full Server-Side HTML Escaping for Admin API** — restored `escapeOutputData()` in `admin/api.php` to actually escape with `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE)` as defense-in-depth; previously was a no-op since v3.5.3
  - ✅ **escapeOutputData() restored** — covers all existing call sites: programs, requests, credits, events, users, artists, artist_variants (~14 endpoints)
  - ✅ **5 additional endpoints escaped** — `title_get`, `disclaimer_get`, `telegram_config_get`, `contact_channels_list`, `contact_channel_get` now escape string fields before returning JSON
  - ✅ **Fixed 2 XSS vulnerabilities** — `error.message` (line 4869) and `result.message` (line 5368) were directly concatenated into `innerHTML` without escaping; wrapped with `escapeHtml()`
- **`escapeHtml()` updated to attribute-safe** — added `.replace(/"/g, '&quot;')` so the function is safe in both text-node and HTML-attribute contexts
- **Added `decodeHtml()` JS helper** — decodes `htmlspecialchars()` entities back to raw text for form `.value` assignments; uses `<textarea>.innerHTML` (safe, no script execution); prevents `&#039;` appearing literally in edit form inputs
- **Removed double-escaping from display paths** — removed `escapeHtml()` wrapping from 40+ `innerHTML` table-display call sites where server-side escaping now handles protection (programs, requests, credits, events, users, artists, contact channels)
- **Wrapped ~40 form `.value` assignments** with `decodeHtml()` — all edit modals (program, credit, event, user, artist, copy), settings panels (site title, disclaimer, telegram, contact channel) now correctly show raw characters (`'`, `&`) instead of HTML entities
- **Unified `escHtml()` → `escapeHtml()`** — replaced all 6+ call sites of the duplicate `escHtml()` regex function with the canonical DOM-based `escapeHtml()`; deleted duplicate function definition; `colorizeLogOutput()` (telegram log viewer) retains escaping internally as log content must remain raw before colorization

### Files Changed
- `admin/api.php` — restored `escapeOutputData()`, added escaping to 5 endpoints, validated `stream_url` scheme
- `admin/index.php` — fixed 2 XSS vulnerabilities (`error.message`, `result.message`), added `decodeHtml()` helper, wrapped 40+ `.value` assignments, unified `escHtml()` → `escapeHtml()`

> **Security posture**: Admin API now applies HTML escaping at both server (JSON output) and client (innerHTML display) layers — defense-in-depth. Form inputs correctly show raw text via `decodeHtml()`.

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)

---

## [5.3.0] - 2026-04-14

### Added
- **Telegram Log Viewer in Admin UI** — View and download Telegram notification cron logs directly from the admin panel
  - 📋 **Log Viewer Section** — New section in Admin › Settings › 🤖 Telegram with file selector, refresh, and download buttons
  - 📂 **File Management** — Dropdown lists active log (`telegram-cron.log`) + dated archives (`telegram-cron-YYYY-MM-DD.log`), newest first
  - 🎨 **Colored Output** — Log entries color-coded by level: `[INFO]` (green), `[DEBUG]` (gray), `[WARN]` (orange), `[ERROR]` (red)
  - 📊 **Line Tracking** — Shows "Displaying X / Y lines" info; displays last 500 lines to prevent memory issues
  - ⬇️ **Download Logs** — Download selected log file directly via download button

### New API Endpoints
- `GET ?action=telegram_log_get[&file=FILENAME]` — Returns file list + last 500 lines of selected log
- `GET ?action=telegram_log_download[&file=FILENAME]` — Downloads full log file as attachment

### Implementation
- Auto-loads log viewer when switching to Telegram sub-tab in Admin Settings
- File selection validated against whitelist — no path traversal possible
- Admin-role required for downloads; login-only for viewing
- Responsive layout with color-coded terminal-style display

### Files Changed
- `admin/api.php` — Added `telegram_log_get` and `telegram_log_download` endpoints
- `admin/index.php` — Added Log Viewer HTML section + JS functions in Telegram sub-tab; updated `switchSettingsSubtab()` to auto-load logs
- `admin/js/admin-i18n.js` — Added 3 new i18n keys (TH + EN): `settings.telegramLogTitle`, `settings.telegramLogRefresh`, `settings.telegramLogDownload`
- `config/app.php` — Version bump to 5.3.0

> **User Experience**: Admins can now monitor Telegram notification cron health without SSH access
> **Security**: File access validated, admin-role protected for downloads

## [5.2.0] - 2026-04-14

### Added
- **Telegram Log Rotation & Cleanup** — Dedicated daily cron script for log management
  - 🔄 **Daily Rotation** — `cron/rotate-telegram-logs.php` renames `cache/logs/telegram-cron.log` to dated archives (`telegram-cron-YYYY-MM-DD.log`) every day
  - 🗑️ **Automatic Cleanup** — Deletes archived logs older than 7 days via scheduled cron job
  - 🔒 **Security Hardening** — Added `cron/.htaccess` with `Deny from all` for Apache-level HTTP access blocking
  - 📋 **Flexible Output** — Script outputs timestamped messages to STDOUT for easy log capture and monitoring

### Implementation
- Non-destructive addition — existing 10 MB size-based rotation in `send-telegram-notifications.php` remains as safety valve
- Both rotation mechanisms coexist peacefully; `glob` pattern `telegram-cron-*.log` captures both daily and size-rotated archives
- Cron scheduling recommendation: `0 0 * * * php /path/to/cron/rotate-telegram-logs.php >> /path/to/cache/logs/rotate-cron.log 2>&1`

### Files Changed
- `cron/rotate-telegram-logs.php` *(new)* — daily log rotation and 7-day cleanup script (CLI-only)
- `cron/.htaccess` *(new)* — Apache-level HTTP access protection for cron directory
- `config/app.php` — version bump to 5.2.0

> **Robustness**: Log rotation now combines daily schedule + automatic retention, eliminating manual log cleanup burden
> **Security**: Added Apache-level directory protection for all cron scripts

## [5.1.1] - 2026-04-14

### Added
- **Admin Help Documentation (Thai)** — Updated `admin/help.php` with v5.1.0 features
  - 📝 **Header & Account Settings** — Added App Version Badge explanation
  - ⚙️ **Settings Tab Sub-tabs** — Documented new 6 sub-tabs structure (Site • Contact • Users • Backup • Telegram • Disclaimer)
  - 📋 Comprehensive table explaining each Settings sub-tab function and category
  - Support for all admin role explanations

- **Admin Help Documentation (English)** — Updated `admin/help-en.php` with v5.1.0 features
  - 📝 **Header & Account Settings** — Added App Version Badge explanation (English)
  - ⚙️ **Settings Tab Sub-tabs** — Documented new 6 sub-tabs structure with English descriptions
  - 📋 Comprehensive table with English function descriptions
  - Full English documentation parity with Thai version

- **How-to-Use Guide (3 Languages)** — Verified `how-to-use.php` internationalization support
  - 🌍 Confirmed 3-language support (Thai/English/日本語) via i18n system
  - 📝 Verified footer version display updates automatically from APP_VERSION constant
  - 🔍 Confirmed all data-i18n attributes for proper translations

### Files Changed
- `admin/help.php` — Updated Settings Sub-tabs documentation with v5.1.0 feature explanation (Thai)
- `admin/help-en.php` — Updated Settings Sub-tabs documentation with v5.1.0 feature explanation (English)
- `how-to-use.php` — Verified internationalization; no content changes needed (i18n-driven)

> **Documentation Quality**: Admin help files now comprehensively document v5.1.0 Settings sub-tabs changes
> **i18n Coverage**: how-to-use.php supports full 3-language experience via translations.js

## [5.1.0] - 2026-04-14

### Added
- **Admin UI Settings Sub-tabs** — Reorganized Admin Settings with cleaner sub-tab navigation
  - 📝 Site (Title + Theme)
  - ✉️ Contact (Channel management)
  - 👤 Users (User management)
  - 💾 Backup (Backup/restore)
  - 🤖 Telegram (Notification settings)
  - ⚠️ Disclaimer (Multilingual disclaimer)
- **App Version Badge** — Added version display (e.g., `v5.1.0`) in admin header between language toggle and help link

### Changed
- **Settings Tab Structure** — Removed redundant Users, Backup, and Contact top-level tabs; consolidated into Settings sub-tabs for cleaner navigation
  - Old: 7 top-level tabs (Programs, Events, Requests, Credits, Import, Artists, Settings + Users + Backup + Contact)
  - New: 7 top-level tabs (Programs, Events, Requests, Credits, Import, Artists, Settings) with 6 organized sub-tabs inside Settings
  - Sub-tab order optimized: Site → Contact → Users → Backup → Telegram → Disclaimer

### Files Changed
- `config/app.php` — Version bump to 5.1.0
- `admin/index.php` — Reorganized Settings sub-tabs, removed old Users/Backup/Contact sections, updated `switchTab()` and `switchSettingsSubtab()` functions, removed redundant main tab buttons, added version badge in header
- `admin/js/admin-i18n.js` — No changes needed (sub-tab keys already defined)

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)
> **Backward Compatibility**: Full compatibility maintained; Settings functionality unchanged, only UI organization improved

## [5.0.0] - 2026-04-14

### Added
- **Telegram Bot Notifications** — Users can now link their Telegram account to receive automatic push notifications before upcoming programs
  - 🔔 **Link Telegram** — New UI section in "My Upcoming Programs" (`/my/{slug}`) page to connect Telegram account via 2 methods:
    - **Method 1 (Recommended)**: Click "เปิด Telegram" button → Opens deep-link to bot with `start` parameter pre-filled
    - **Method 2 (Fallback)**: Manual search and `/start {slug}` command entry (for when deep-link unavailable)
    - Clear visual separation between methods with color-coded info boxes
    - Slug removal from modal (already embedded in deep-link button and command instructions)
  - 🌐 **In-bot Language Selection** — Users select language (Thai/English/日本語) via inline keyboard buttons after `/start` command
  - ⚡ **Per-program Notifications** — Automatic push notification N minutes before each program starts (configurable, default 60 minutes)
  - 📅 **Daily Summary Notifications** — Automatic summary of all upcoming programs grouped by event, sent at 9:00 AM each day
  - 🔄 **Cron-based Delivery** — CLI script runs every 15 minutes to scan favorites and send notifications; no DB schema changes needed
    - Shard directory scanning in both webhook handler (`/upcoming`, `/today` commands) and cron script properly discovers favorites files
  - 🔐 **Secure Linking** — Uses existing HMAC-signed favorites slug for authentication; Telegram chat_id stored in favorites JSON
  - 🌐 **Multilingual** — Full support for Thai, English, and 日本語; notifications formatted with program details
  - ⚙️ **Admin UI Settings** — Configure Bot Token, Bot Username, Webhook Secret, Notify Minutes from Admin › Settings › 🤖 Telegram Notifications
  - 🧹 **Production-ready code** — All verbose debugging infrastructure removed, kept only standard error logging for reliability

### Files Changed
- `config/telegram.php` *(new)* — Bot configuration loader (JSON → PHP constants)
- `config/telegram-config.json` *(new)* — Runtime settings (edited via Admin UI)
- `functions/telegram.php` *(new)* — API helpers, account linking, message formatting, notification utilities
- `api/telegram.php` *(new)* — Webhook handler for `/start`, `/stop`, `/upcoming` commands
- `cron/send-telegram-notifications.php` *(new)* — Cron script for sending notifications every 15 minutes
- `tools/setup-telegram-webhook.php` *(new)* — Helper script to register webhook URL with Telegram Bot API
- `TELEGRAM_SETUP.md` *(new)* — Setup guide (Thai)
- `TELEGRAM_SETUP_EN.md` *(new)* — Setup guide (English)
- `config.php` — load telegram config and functions
- `my.php` — Telegram linking UI section with modal and JavaScript functions
- `api/favorites.php` — new `unlink_telegram` action to disconnect Telegram
- `js/translations.js` — 24+ new translation keys (TH/EN/JA) for Telegram UI
- `admin/index.php` — Telegram notifications settings section in Settings tab
- `admin/api.php` — three new API endpoints: `telegram_config_get`, `telegram_config_save`, `telegram_webhook_test`
- `admin/js/admin-i18n.js` — 18+ new translation keys for Telegram settings UI
- `admin/help.php` — "🤖 Telegram Notifications" section (Thai)
- `admin/help-en.php` — "🤖 Telegram Notifications" section (English)
- `config/.htaccess` — deny HTTP access to .json files in config directory

### Configuration
Users need to set up Telegram bot integration via Admin UI:
1. Go to Admin › Settings › 🤖 Telegram Notifications
2. Enter Bot Token, Bot Username
3. Generate or paste Webhook Secret
4. Set Notify Minutes (default 60)
5. Click "Test Webhook" to verify
6. Click "Save Telegram"

Then add cron job: `*/15 * * * * php /path/to/cron/send-telegram-notifications.php >> /var/log/tg-notify.log 2>&1`

### Architecture
- **No DB migration** — Telegram metadata stored in existing favorites JSON files (`telegram_chat_id`, `telegram_notified` map)
- **Idempotent** — Notifications tracked with program ID and timestamp to prevent duplicates (window ±7.5 minutes)
- **Scalable** — Hybrid design: JSON-based for <1000 users, can migrate to DB table when needed
- **Flexible** — Notification window, history retention, and duplicate prevention all configurable
- **Reliable shard discovery** — Webhook handler and cron script properly iterate `cache/favorites/{3-char hex shard}/*.json` structure

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)
> **Code Quality**: Production-ready with debug code cleaned up; no warnings or excessive logging

## [4.5.1] - 2026-04-12

### Fixed
- **Admin filter state not persisting after program edit** — Event filter dropdown (`eventMetaFilter`) in Programs/Requests/Credits tabs now preserves selected value when reloading data after editing and saving a program
  - **Issue**: `populateEventSelect()` rebuilt dropdown options but didn't restore the previously selected event, causing filter to appear "empty" despite being set
  - **Root cause**: Dropdown HTML rebuild cleared all optgroups and options without preserving the `selected` state
  - **Fix**: Save dropdown's current selected value before rebuild, then restore it after new options are appended
  - **Result**: Filter selection now visually persists across modal close → API receives correct event_id parameter → correct filtered data displayed

### Changed
- **`populateEventSelect()` function** — Now includes value preservation logic for all event selector dropdowns

### Files Changed
- `admin/index.php` — Updated `populateEventSelect()` to save/restore selected value
- `config/app.php` — Version bump to 4.5.1

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)

## [4.5.0] - 2026-04-11

### Changed
- **SUMMARY field format in ICS feeds** — Standardized format across all feed types to `Program Title [Event Name]` (event name moved from prefix to suffix in My Upcoming Programs)
  - **export.php**: Changed from `Program Title` to `Program Title [Event Name]`
  - **feed.php (single event)**: Changed from `Program Title` to `Program Title [Event Name]`
  - **feed.php (artist feed)**: Added per-program event name support; now shows correct event name for each program when artist performs at multiple events
  - **my-feed.php**: Changed from `[Event Name] Program Title` to `Program Title [Event Name]` for consistency
  - **Benefit**: More readable calendar appointments with program name visible first, then event context in brackets

### Fixed
- **Artist feed SUMMARY using wrong event name** — Artist feed was showing artist name instead of program's actual event name in SUMMARY field (breaking for artists with multiple events)
  - Added `$eventNameMap` to fetch per-program event information from database
  - SUMMARY now correctly shows event name for each program: `"Program Title [Idol Stage Feb 2026]"`, `"Program Title [Japan Expo 2026]"`, etc.

### Files Changed
- `export.php` — Updated SUMMARY format to include event name
- `feed.php` — Added per-program event name map for artist feeds; updated SUMMARY format
- `my-feed.php` — Changed SUMMARY format from prefix to suffix event name
- `tests/FeedTest.php` — Updated test to match flexible SUMMARY pattern (either `$eventName` or `$summaryEventName`)

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)

## [4.4.1] - 2026-04-10

### Fixed
- **Events tab filter dropdowns rendering empty** — `data-i18n` attribute was placed on `<select>` element instead of first `<option>`, causing the i18n system to replace the entire select's textContent and delete all options; moved `data-i18n` to the first option in `eventActiveFilter` and `eventVenueFilter` dropdowns
- **Events tab date filters not resetting pagination** — Date range inputs (`eventDateFrom`, `eventDateTo`) didn't reset `eventsCurrentPage` to 1 when filters changed; added `eventsCurrentPage=1;` to `onchange` handlers
- **Events table body ID mismatch** — HTML had `id="conventionsTableBody"` but JavaScript searched for `id="eventsConventionsTableBody"`; renamed HTML table body ID to match
- **Removed debugging code** — Cleaned up 39 `console.log()` statements and CSS debug borders (red/blue) left from troubleshooting

### Files Changed
- `admin/index.php` — Fixed filter dropdown i18n, pagination reset, table ID mismatch, removed debugging code

## [4.4.0] - 2026-04-06

### Added
- **Events Tab Feature Parity with Programs Tab** — Admin Events tab now has complete filtering, pagination, and sorting capabilities matching Programs tab
  - ✨ **Server-side filtering**: active status (is_active), venue mode, date range (date_from, date_to)
  - ✨ **Server-side pagination**: configurable page size (20/50/100), page navigation with info display
  - ✨ **Server-side sorting**: sortable columns (ID, Name, Start Date, End Date, Active, Programs) with visual indicators
  - 🔧 **N+1 query fix**: Subquery for event_count instead of loop SELECT per event
  - 📊 **Pagination controls**: Previous/Next buttons with page info and total count display
  - 🎨 **Search box layout**: Search bar spans full width; filter controls and buttons wrap to next lines

### Changed
- **Events API endpoint (`admin/api.php` `listEvents()`)** — Updated to support pagination and filtering parameters; now returns `{ events: [...], pagination: {...} }` structure matching Programs API
  - **New parameters**: `search`, `is_active`, `venue_mode`, `date_from`, `date_to`, `sort`, `order`, `page`, `limit`
  - **Query optimization**: Uses subquery for `event_count` instead of N+1 loop queries
  - **Default pagination**: 20 events per page, supports up to 100
- **Admin UI toolbar layout** — Search box now takes full width on its own line; filter dropdowns and buttons wrap to subsequent lines
- **Add Program/Event buttons** — Now span full width on their own line in toolbar

### Fixed
- **Recent events dropdown not showing**: Fixed API call to fetch all events (limit=100) instead of default limit (20), ensuring recent events are found in the filtered list
- **Recent events sort order**: Recent events now display in selection order (newest first) instead of arbitrary order
- **Recent section placement**: Recent group now appears at the top of event dropdown (before Active/Past groups) for quick access
- **Import workflow**: Added "📥 Import ไฟล์ถัดไป" button on summary screen to allow clearing and importing next file without leaving Import tab

### Files Changed
- `admin/api.php` — Enhanced `listEvents()` with full pagination, filtering, and sorting
- `admin/index.php` — Updated Events toolbar, added pagination controls, fixed dropdown event ordering, improved import summary
- `admin/js/admin-i18n.js` — Added 12 new translation keys for Events filters and pagination (TH/EN)
- `config/app.php` — App constants

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)

## [4.3.0] - 2026-04-06

### Added
- **Smart Event Dropdown Filtering** — Admin Programs/Requests/Credits tabs now display events grouped by status (Active/Past) instead of flat list
  - 📌 **Recent Events section** — Top 3 recently selected events pinned at the top of dropdown for quick access; stored in `localStorage`
  - 🎪 **Active Events group** — Events with end_date ≥ today, sorted by start_date DESC (newest first)
  - 📋 **Past Events group** — Events with end_date < today, sorted by start_date DESC (newest first)
- **Automatic Recent Event Tracking** — Selecting an event from `eventMetaFilter` dropdown automatically saves it to recent list; dropdown re-renders to show updated recent section
- **Helper Functions**:
  - `getRecentEvents()` — retrieves recent events from localStorage
  - `saveRecentEvent(eventId, eventName)` — adds/updates event in recent list (top 3)
  - `groupAndSortEvents(metas)` — separates active/past events and sorts by date
  - `populateEventSelect(selectId, allMetas, recentIds)` — renders optgroups with proper grouping

### Changed
- Event dropdown in Admin panel now uses `<optgroup>` for visual separation instead of flat list
- All 6 event filter selectors (`eventMetaFilter`, `reqEventMetaFilter`, `creditsEventMetaFilter`, `eventConvention`, `creditEventMetaId`, `icsImportEventMeta`) use the same grouping logic for consistency

### Files Changed
- `admin/index.php` — Added smart event dropdown with Recent/Active/Past grouping
- `config/app.php` — Constants for recent events feature

## [4.2.0] - 2026-04-04

### Added
- **Bilingual Admin UI (TH/EN)** — `admin/js/admin-i18n.js` new file with 200+ translation keys per language; TH/EN language toggle button in Admin panel header and login page; preference saved to `localStorage` (`admin_lang`)
- **`adminT(key)`** — core translation lookup function; returns Thai or English string based on current language
- **`applyAdminTranslations()`** — scans DOM for `data-i18n`, `data-i18n-placeholder`, and `data-i18n-title` attributes and applies translations; called on page load and language switch
- **`changeAdminLang(lang)`** — switches language, updates toggle button state, dispatches `adminLangChange` custom event
- **`adminLangChange` custom event** — listened to by `admin/index.php`; re-calls the active tab's `loadX()` function so JS-rendered table rows (which use `adminT()` inline in template literals) are rebuilt in the new language
- **`data-i18n` attributes** — added to 280+ static HTML elements across `admin/index.php` and `admin/login.php`: tab labels, toolbar buttons, table headers, form labels, form hints (`<small>`), modal titles and body text, bulk-bar labels, status badges, and confirmation messages
- **Dynamic content translated** — all JS-generated HTML (program rows, request rows, artist rows, credits rows, user rows) uses `adminT()` for "Edit", "Delete", "Copy", "Variants", "View" buttons and status badge labels so they switch language on toggle

### Changed
- `admin/login.php` language toggle syncs with `admin/index.php` via same `localStorage` key — switching language on the login page persists to the admin panel and vice versa
- All form hints (`<small class="form-hint">`) including Artist modal, Variants modal, Import Artists modal, Bulk Edit modal, Event modal, and ICS Import artist-mapping hint are now fully bilingual

### Files Changed
- `admin/js/admin-i18n.js` (new) — Core i18n lookup function `adminT()` with 200+ keys per language
- `admin/index.php` — Language toggle button, `data-i18n` attributes on 280+ elements, dynamic content translation
- `admin/login.php` — Language toggle synced with admin panel via localStorage
- `config/app.php` — App constants

## [4.1.0] - 2026-04-01

### Added
- **Cross-day programs** — Admin form now has separate "วันที่สิ้นสุด" (end date) field alongside the existing end time; programs spanning midnight (e.g. 23:00–02:00 next day) can now be saved correctly
- **+N badge on public schedule (list view)** — When a program's end date differs from its start date, a pink superscript badge (`+1`, `+2`, …) appears next to the end time in the list view
- **+N badge in calendar view** — Calendar chips, day-panel time rows, and the detail modal all show the same `+N` superscript badge when a program ends on a later date; `calCrossDay(ev)` helper function added to `common.js`
- **Auto-sync end date** — Changing the start date in the admin form automatically advances the end date if it would otherwise precede the new start date

### Changed
- Admin program form layout: date + time fields reorganised into two rows (start date/time row, end date/time row) for clarity
- `.program-time-nextday` and `.cal-chip-nextday` share a single CSS rule block for consistent styling

### Files Changed
- `admin/index.php` — End date field in program form with auto-sync logic
- `index.php` — Cross-day badge (`+N`) display in list view and event detail
- `js/common.js` — `calCrossDay()` helper for calendar view cross-day detection
- `styles/index.css` — `.program-time-nextday` styling

## [4.0.3] - 2026-03-26

### Added
- **My Upcoming Programs — event color coding** — each event gets a distinct pastel background + left-border accent (6 colors cycling: pink, blue, green, amber, purple, teal); applies to both the main program list and the mini calendar day modal; `now-playing` highlight still overrides event color

### Files Changed
- `my.php` — Event color map, pastel background + left-border accent styling

## [4.0.2] - 2026-03-26

### Fixed
- **ICS Export filter mismatch** — `exportToIcs()` in `common.js` was not forwarding `type[]` filter to export URL; type filter was silently ignored on export
- **ICS Export artist filter mismatch** — `export.php` was filtering artists against raw `categories` text field, while `index.php` (v3.0.0+) uses the `program_artists` junction table with canonical artist names; artists selected in UI could be missed in exported ICS; `export.php` now mirrors `index.php` logic (junction table first, fallback to categories text)

### Files Changed
- `js/common.js` — Forward `type[]` filter parameter in `exportToIcs()`
- `export.php` — Mirror `index.php` artist filter logic (junction table + variants)

## [4.0.1] - 2026-03-25

### Fixed
- 🌐 **Timezone label language switch** — `program-time-local` spans now re-render label text (`เวลาท้องถิ่น` / `local time` / `現地時刻`) on language change via `appLangChange` event; `updateTimezoneLabels(lang)` reads stored `data-localtime` attribute instead of recomputing
- 🌐 **Timezone badge inline** — event page timezone badge changed from tooltip (`title`) to inline text: `🕐 Asia/Tokyo (Asia/Bangkok)` when client timezone differs; `🕐 Asia/Bangkok` when same
- 🕐 **Local time shows full range** — `program-time-local` now shows start–end range `(10:00–11:00 local)` instead of start only; `data-utc-end` attribute added to `.program-time` span in `index.php`
- 📐 **`program-time-local` block layout** — changed to `display: block; margin-top: 2px` so local time appears on its own line below the event-timezone time
- 🛡️ **Duplicate span guard** — `initTimezoneDisplay()` checks `nextSibling.classList` before appending to prevent duplicate `.program-time-local` spans on re-call
- 📅 **Calendar view local time** — `calLocalTimeRange(ev)` helper added; local time now shown in all three calendar surfaces:
  - **Chip** (desktop grid): `cal-chip-time-local` span on new line via `flex-wrap`; chip gets `cal-chip-has-local` class
  - **Day panel** (mobile): `cal-dp-item-time-local` div after time row
  - **Detail modal**: `cal-detail-time-local` div below the time heading
- 🔴 **Day panel Live button separate line** — `.cal-dp-join` changed to `display: block; width: fit-content; margin-top: 0.4rem` so 🔴 Live button is always on its own line

### Files Changed
- `index.php` — Timezone badge inline display, `data-utc-end` for local time conversion
- `js/common.js` — `updateTimezoneLabels()`, local time range re-render on language switch
- `styles/index.css` — `.program-time-local` block layout, calendar detail modal local time styling
- `styles/common.css` — `.event-timezone` badge styling
- `tests/TimezoneTest.php` — Tests for v4.0.1 timezone label and layout fixes

## [4.0.0] - 2026-03-25

### Added
- **Per-event Timezone** — `timezone TEXT DEFAULT 'Asia/Bangkok'` column in `events` table; each event can have its own timezone (e.g. Asia/Tokyo, America/Los_Angeles)
- **Timezone badge** on event page header — shows the event's timezone; if browser timezone differs, badge shows tooltip with user's local timezone
- **Local time conversion** — JS `initTimezoneDisplay()` in `common.js`; detects browser timezone vs event timezone mismatch; appends `(HH:MM local time)` after program times for users in a different timezone
- **`data-utc` attribute** on `.program-time` spans — UTC Unix timestamp for JS timezone conversion via `Intl.DateTimeFormat`
- **`window.EVENT_TIMEZONE`** injected in `index.php` for client-side timezone handling
- **ICS export with TZID format** — `export.php` and `feed.php` now use `DTSTART;TZID=Asia/Bangkok:20260319T100000` format + VTIMEZONE block instead of UTC `Z` format; `X-WR-TIMEZONE` reflects per-event timezone
- **`icsVtimezone(string $tzid): string`** in `functions/ics.php` — generates RFC 5545-compliant VTIMEZONE block with STANDARD + DAYLIGHT components (auto-detected via PHP `DateTimeZone::getTransitions()`)
- **`icsOffsetString(int $seconds): string`** in `functions/ics.php` — formats UTC offset as ±HHMM
- **`get_event_timezone($eventMeta): string`** in `functions/helpers.php` — priority: event.timezone → DEFAULT_TIMEZONE → 'Asia/Bangkok'
- **`define('DEFAULT_TIMEZONE', 'Asia/Bangkok')`** in `config/app.php`
- **Admin timezone picker** — `<select id="conventionTimezone">` with 16 common timezones in 4 region groups (Asia, Europe, Americas, Pacific)
- **Image export timezone label** — `image.php` footer shows timezone alongside generated timestamp
- **Migration** — `tools/migrate-add-timezone-column.php` (idempotent); `setup.php` CREATE TABLE includes timezone column
- **CSS** — `.event-timezone` (monospace header badge) and `.program-time-local` (small italic local time annotation) in `styles/index.css`
- **i18n** — `tz.badge` and `tz.localTime` keys in `js/translations.js` (TH/EN/JA)
- **Admin Help Pages** — `admin/help.php` (Thai) and `admin/help-en.php` (English) each have a new "🌐 Per-event Timezone (v4.0.0)" section covering: effects table (ICS/feed, event page, image export), how-to-set steps, 16-option timezone reference table, and 4 verification methods (ICS export test, live feed test, browser badge test with DevTools Sensors, automated CLI test)
- **67 new automated tests** in `tests/TimezoneTest.php` — DB schema, migration idempotency, DEFAULT_TIMEZONE constant, `get_event_timezone()` priority logic, `icsOffsetString()`, `icsVtimezone()` RFC 5545 VTIMEZONE block, UTC timestamp computation, DB CRUD, export.php/feed.php TZID format, index.php injection, admin API, translations.js keys, common.js `initTimezoneDisplay()`, CSS classes, setup.php integration — **total: 2509 tests (14 suites)**

### Changed
- `index.php` `normalizedEvents` timestamp computation changed from `strtotime()` to `new DateTime($t, $eventTzObj)->getTimestamp()` for correct UTC when event timezone ≠ Asia/Bangkok
- `admin/api.php` `createEvent()` and `updateEvent()` now accept and persist `timezone` field with PHP `DateTimeZone` validation

### Files Changed
- `tools/migrate-add-timezone-column.php` (new) — Idempotent migration adding `timezone` column to `events` table
- `tests/TimezoneTest.php` (new) — 67 automated tests for all timezone features
- `config/app.php` — `DEFAULT_TIMEZONE` constant definition
- `functions/helpers.php` — `get_event_timezone()` priority logic with validation
- `functions/ics.php` — `icsVtimezone()` RFC 5545 VTIMEZONE block generation, `icsOffsetString()` UTC offset formatting
- `admin/api.php` — Timezone field acceptance in event CRUD operations
- `admin/index.php` — Timezone picker (`<select>`) in event form with 16 timezone options
- `admin/help.php` — Help page section "🌐 Per-event Timezone" with effects table and verification methods (Thai)
- `admin/help-en.php` — Same help section in English
- `export.php` — DTSTART with TZID format, VTIMEZONE block prepended to ICS feed
- `feed.php` — Per-event timezone in X-WR-TIMEZONE, VTIMEZONE block generation
- `index.php` — Event timezone badge display, local time conversion via `initTimezoneDisplay()`
- `image.php` — Timezone label in PNG image footer
- `js/common.js` — `initTimezoneDisplay()` function for client-side local time conversion
- `js/translations.js` — `tz.badge` and `tz.localTime` keys (TH/EN/JA)
- `styles/index.css` — `.event-timezone` badge and `.program-time-local` annotation styling
- `setup.php` — Timezone column in CREATE TABLE and setup wizard integration
- `tests/run-tests.php` — Test runner updates for new TimezoneTest suite

## [3.7.0] - 2026-03-25

### Added
- 🎤 **Artist & Group Portal** (`artists.php`) — new public page at `/artists` listing every group and solo artist in the system; groups displayed as gradient cards showing group name, member count, program count, and clickable member chips; solo artists shown in a responsive grid; all items link to their `/artist/{id}` profile page
- 🔍 **Real-time search** — search bar filters both group cards (including member names inside each card) and solo artist cards simultaneously with no page reload; matching member chip highlighted in yellow
- 🗂️ **Tab filter** — three tabs (All / Groups / Solo) let users narrow the view instantly client-side
- 📊 **Stats bar** — shows total group count and total artist count at a glance
- 🌐 **i18n** — full TH/EN/JA support via `data-i18n` attributes and new `portal.*` + `nav.artists` translation keys in `js/translations.js`
- ⚡ **Query cache** — portal data (groups + members + solo artists) cached in `cache/query_portal.json` (TTL 1 hr); invalidated automatically by `invalidate_artist_query_cache()` whenever artists or variants change
- 🔗 **Nav link on homepage** — `🎤 ศิลปิน` link added to `<nav class="header-nav">` on both the event-listing header and the event-detail header in `index.php`; placed before `📋 แหล่งข้อมูลอ้างอิง`

### Files Changed
- `artists.php` (new, previously `portal.php`) — Artist & Group Portal page with real-time search and tab filters
- `styles/portal.css` (new) — Styling for group cards, member chips, and solo artist grid
- `js/translations.js` — New `portal.*` and `nav.artists` translation keys (TH/EN/JA)
- `functions/cache.php` — `invalidate_artist_query_cache()` integration
- `index.php` — Nav link to artists portal on event-listing and event-detail headers
- `config/app.php` — App constants

---

## [3.6.12] - 2026-03-25

### Added
- 🎤 **Admin Artists — member count badge for groups** — group rows in the Artists table now display a yellow badge showing the number of members (e.g. `3 คน`) immediately after the `กลุ่ม` type badge; badge is hidden when the group has no members yet; count is computed server-side via a subquery (`SELECT COUNT(*) FROM artists WHERE group_id = a.id AND is_group = 0`) added to `listArtists()` in `admin/api.php`

### Files Changed
- `admin/api.php` — Subquery for member count in `listArtists()`
- `admin/index.php` — Member count badge display in Artists table
- `config/app.php` — App constants

---

## [3.6.11] - 2026-03-24

### Fixed
- 🌐 **i18n: 404 page now multilingual** — the 404 error page previously had all text hardcoded in Thai; replaced the PHP echo block with a proper HTML template using `data-i18n` attributes, loading `translations.js`, and an inline script that reads `localStorage.lang` and applies translations; now renders correctly in TH/EN/JA like every other page in the app
- 🌐 **i18n: Filter empty-state text** — "no artist data" and "no venue data" messages inside the filter panel were hardcoded in Thai with no i18n; added `data-i18n` attributes (`filter.noArtist`, `filter.noVenue`) and added translation keys for all three languages
- 🌐 **i18n: `my.copyUrl` / `fav.copyUrl` keys were English in all locales** — Thai locale now uses `'📋 คัดลอก URL'` and Japanese locale now uses `'📋 URLをコピー'`
- 🌐 **i18n: `fav.noArtists` (JA) grammar** — `'フォロー中がいません'` (incomplete) → `'フォロー中のアーティストがいません'` (grammatically complete)
- 🌐 **i18n: `fav.statsPrograms` (JA) text truncated** — `'アップカミング'` → `'アップカミングプログラム'` to match the TH/EN meaning
- 🌐 **i18n: `howToUse.subtitle` stale `"your event"` placeholder** — Thai and Japanese locales contained the literal string `"your event"` instead of the site name; replaced with `'Idol Stage Timetable'` so the existing IIFE at the bottom of `translations.js` can substitute the custom title when one is configured
- 🌐 **i18n: `section1.desc` stale `"your event"` placeholder** — same fix applied to TH and JA
- 🌐 **i18n: `contact.disclaimer.text` stale `"your event organizers"` placeholder** — removed the unresolved placeholder from all three locales; text now reads as a complete, self-contained sentence
- 🐛 **`openLcalDayModal` status badge not translating** — `const translations` in `translations.js` is a block-scoped global and is not a property of `window`, so `window.translations` was always `undefined`; the event status badge in the homepage calendar day modal always showed the raw JS value `'ongoing'` / `'upcoming'` / `'past'` regardless of selected language; fixed by using `translations[lang]` directly instead of `window.translations[lang]`; same fix applied to the "▼ Read more" button initialisation
- 🐛 **Event Picker "currently viewing" badge not translating** — `✓ ดูอยู่` badge on the active event card had no `data-i18n` attribute and no translation key; added `data-i18n="eventPicker.viewing"` and translation keys for TH/EN/JA
- 🐛 **`window.currentLang` always undefined in inline scripts** — `currentLang` in `common.js` is declared as `let`, which is not a property of `window`; all inline scripts in `index.php` that read `window.currentLang` always got `undefined` and fell back to Thai regardless of the selected language; fixed by adding `window.currentLang = lang` inside `changeLanguage()` so it is always kept in sync
- 🐛 **Homepage calendar day modal not re-rendering on language switch** — the modal's innerHTML was built once on open and had no `data-i18n` attributes, so `updateLanguage()` could not update it; fixed by storing the active date in `window._lcalActiveDate` and listening for the new `appLangChange` custom event dispatched by `changeLanguage()`; the listing calendar grid and open day modal are both re-rendered immediately when language changes

### Added
- 🌐 **New translation keys** — `filter.noArtist`, `filter.noVenue`, `notFound.heading`, `notFound.desc`, `notFound.back`, `eventPicker.viewing` added in TH/EN/JA
- 🔧 **`appLangChange` custom DOM event** — `changeLanguage()` in `common.js` now dispatches `document.dispatchEvent(new CustomEvent('appLangChange', { detail: { lang } }))` after updating the page; page-specific inline scripts can listen to this event to re-render dynamic content without monkey-patching `window.changeLanguage`

### Files Changed
- `index.php`
- `js/translations.js`
- `js/common.js`
- `config/app.php`

## [3.6.10] - 2026-03-23

### Changed
- 🎨 **Event listing card header — dates displayed below the event name** — changed `.program-card-header` from row layout (name left / date right) to column layout so long event names have full width without being squeezed by the date
- 🎨 **Homepage calendar modal event cards — same layout change** — `.lcal-event-card-header` updated to match

### Files Changed
- `styles/index.css`

## [3.6.9] - 2026-03-22

### Added
- ✨ **Now-playing highlight on My Upcoming Programs** — when the page loads, programs that are currently in progress are highlighted with a distinct style so users can instantly see what is on right now; highlight is applied once on page load (no auto-refresh — users reload the page manually to update)

### Files Changed
- `my.php`

## [3.6.8] - 2026-03-21

### Fixed
- 🐛 **`credits.php` missing `BASE_PATH` → `fav_slug` cleared on visit** — `credits.php` was the only public page that did not define `window.BASE_PATH` / `const BASE_PATH` before loading `common.js`; when `injectFavNavButton()` ran, `base` fell back to `''` and the background validation fetch went to `/api/favorites?...` (root-relative) instead of the correct subdirectory path; sites hosted in a subdirectory (e.g. `/stage-idol-calendar/`) received a 404, which triggered `localStorage.removeItem('fav_slug')` and silently removed the user's favorites shortcut buttons; fixed by adding `const BASE_PATH = <?php echo json_encode(get_base_path()); ?>;` in the inline script before `common.js` loads

### Files Changed
- `credits.php`

## [3.6.7] - 2026-03-20

### Added
- 🔧 **`fav_slug` recovery UX** — when the favorites token in localStorage is expired or invalid, `my-favorites.php` and `my.php` error screens now show two recovery buttons: "🗑️ Clear from Browser" (removes `fav_slug` from localStorage and redirects to home) and "✨ Create New Favorites" (POSTs to `api/favorites.php?action=create`, saves the new slug to localStorage, and redirects to the new favorites URL) — users no longer need to open developer tools to recover from a stale token
- 🔧 **Silent self-healing in `injectFavNavButton()`** — after injecting ⭐/📅 nav buttons, a background `fetch` validates the stored slug against the server; on 400/404 response it automatically removes `fav_slug` from localStorage and removes the injected buttons
- 🌐 **Translation keys** — added `fav.clearStorage` and `fav.createError` in TH/EN/JA

### Files Changed
- `my-favorites.php`
- `my.php`
- `js/common.js`
- `js/translations.js`

## [3.6.6] - 2026-03-20

### Added
- 📋 **Table of Contents on How-to-Use page** — `<nav class="toc-section">` renders 18 section links in a 2-column grid (1 column on mobile); each item is an anchor link that jumps directly to the target section; labels use existing `data-i18n` keys and re-render automatically when switching TH/EN/JA
- 🔑 **`toc.title` translation key** — TH `📋 สารบัญ` / EN `📋 Table of Contents` / JA `📋 目次`

### Changed
- 🔀 **Section order reorganized by priority** — Overview → Event Picker → Homepage Calendar → Filtering → Date Jump Bar → Program Detail Modal → Live Stream → Gantt Chart → Calendar View → Save/Export → Artist Profile → Artist Feed Subscribe → My Favorites → Past Events → Submit Request → Language → Mobile → FAQ; all sections have `id="s-*"` attributes for anchor navigation

### Files Changed
- `how-to-use.php`
- `js/translations.js`
- `styles/how-to-use.css`

## [3.6.5] - 2026-03-20

### Added
- ⚡ **Homepage listing query cache** (`cache/query_listing.json`, TTL 3600s) — caches both `$activeEvents` (from `get_all_active_events()`) and `$listingCalData` (homepage calendar dot data) together in a single file; a cache hit skips both DB queries entirely; a cache miss runs both queries and saves the result; automatically invalidated when any program or event is modified

### Changed
- 🔄 **`invalidate_query_cache()`** — `query_listing.json` added to the invalidation list for both specific-event and global (null) call patterns
- 🔄 **Admin `createEvent()` / `updateEvent()` / `deleteEvent()`** — now call `invalidate_query_cache()` to bust `query_listing.json` when event metadata changes; previously event writes did not invalidate the query cache

### Files Changed
- `index.php`
- `functions/cache.php`
- `admin/api.php`

## [3.6.4] - 2026-03-20

### Added
- 📅 **Homepage Calendar View** — monthly calendar above the Events listing on the homepage; days with programs show a pink dot; clicking a day opens a modal listing the **Events** (conventions) active on that day — each shown as a mini event card with gradient header (name + date range), status badge (กำลังจัดงาน / กำลังจะมาถึง / จบแล้ว), and "📋 ดูตารางเวลา" button; calendar navigates per month (defaults to current month); shows all active events including past; language-aware month/day labels re-render on language switch

### Changed
- 🎨 **Calendar section title** — `"📅 ปฏิทินกิจกรรม"` header now has `margin-top: 10px` for better visual separation
- 🎨 **Events listing title** — renamed from `"Events"` → `"🎪 รายการกิจกรรม"` (EN: `🎪 Events`, JA: `🎪 イベント一覧`) with icon prefix

### Files Changed
- `index.php`
- `styles/index.css`
- `js/translations.js`
- `how-to-use.php`

## [3.6.3] - 2026-03-20

### Changed
- 🔔 **My Upcoming Programs — include group programs** — if a followed artist belongs to a group, programs linked to that group are now included automatically in `my.php` and `my-feed.php`; group IDs are resolved from `artists.group_id` and merged into the program query `artist_id IN (...)` set; no changes to followed-artist list or UI

### Files Changed
- `my.php`
- `my-feed.php`

## [3.6.2] - 2026-03-20

### Added
- 📊 **Admin Events tab — sortable columns** — the Events table in Admin Panel supports sorting by clicking any column header: `#`, `Name`, `Start Date`, `End Date`, `Active`, `Programs`; client-side sort (no API reload required); default sort `Start Date DESC` (newest first); click again to toggle asc/desc; ↕ / ↑ / ↓ icons indicate sort state

### Files Changed
- `admin/index.php`

## [3.6.1] - 2026-03-20

### Changed
- 🗂️ **Personal feed cache — shard co-location** — personal feed `.ics` cache files moved from `cache/feed_fav_{md5}.ics` (flat directory) into `cache/favorites/{shard}/{token}.ics` (same shard directory as the favorites `.json` file); `fav_cleanup_expired()` GC now deletes both `.json` and `.ics` for expired tokens together; no user-visible behavior change

### Files Changed
- `my-feed.php`
- `functions/favorites.php`

## [3.6.0] - 2026-03-20

### Added
- 🔔 **Personal ICS Subscription Feed** (`my-feed.php`) — live webcal feed scoped to a user's favorited artists; URL `/my/{slug}/feed` (via `.htaccess`); shows all upcoming programs from followed artists across active events; SUMMARY prefixed with `[Event Name]` for context in calendar apps; RFC 5545 compliant (line folding, CATEGORIES delimiter, VALARM 15-min reminder)
- 🔔 **Subscribe button on My Upcoming Programs** — 🔔 Subscribe button added to the Save URL banner; opens a modal with webcal:// link (Apple Calendar / iOS / Thunderbird) + https:// URL + Copy button + Outlook subscription instructions + sync frequency notice
- 📦 **`functions/ics.php`** — ICS helper functions (`icsLine`, `icsFold`, `icsEscape`, `icsEscapeText`) extracted from `feed.php` into a shared file; both `feed.php` and `my-feed.php` `require_once 'functions/ics.php'`

### Changed
- 🏗️ **`feed.php` refactor** — removed inline function definitions; now delegates to `functions/ics.php`; no behavior change

### Files Changed
- `my-feed.php` (new)
- `functions/ics.php` (new)
- `feed.php`
- `.htaccess`
- `my.php`
- `tests/FeedTest.php`

## [3.5.4] - 2026-03-20

### Fixed
- 🐛 **Admin artist profile link 404** — artist name links in Admin › Artists were pointing to `/admin/artist/{id}` instead of `/artist/{id}` because `BASE_PATH` resolves from `admin/index.php`'s `SCRIPT_NAME` and returns `/admin`; fixed by adding JS constant `APP_ROOT = dirname(BASE_PATH)` and using `APP_ROOT` for all links pointing to public pages

### Files Changed
- `admin/index.php`

## [3.5.3] - 2026-03-20

### Fixed
- 🐛 **Admin form HTML entity encoding** — `'` (single quote) and `&` entered in admin forms were being stored and re-displayed as `&#039;` and `&amp;` due to `htmlspecialchars()` being incorrectly applied to JSON API responses; removed `escapeOutputData()` side effects and all standalone `htmlspecialchars()` calls from `admin/api.php` JSON output paths — JSON transport now carries raw data; HTML escaping remains in `admin/index.php` JS layer (`escapeHtml()` on `innerHTML` insertions, `textContent`/`.value` for form fields)

### Files Changed
- `admin/api.php`

## [3.5.2] - 2026-03-20

### Added
- 📅 **Mini Calendar on My Upcoming Programs** — monthly calendar grid inserted between the "Followed Artists" section and the "Upcoming Programs" list; navigates only between months that have programs (◀ ▶ disabled at boundary); dates with programs show a pink dot; today is highlighted with a filled circle
- 🗓️ **Day Programs Modal** — clicking a date with a dot opens a modal showing all programs for that day in the same format as the list (time, title, type badge, event name, location, categories, Live button); closes on ✕ button, overlay click, or Escape key
- 🌐 **Calendar re-renders on language change** — month/year title, day-of-week headers, and modal date label all update immediately when switching TH/EN/JA
- 🧪 **FavoritesTest** — 84 new automated tests covering the full v3.5.x Favorites system: config constants, UUID v7 format/uniqueness, HMAC determinism, slug build/parse/tamper resistance, file I/O (write→read roundtrip, sharded path), `api/favorites.php` action structure (create/get/add/remove, rate-limit 429, slug validation), `my-favorites.php` solo/group split + sort controls + localStorage preference, `my.php` mini calendar + day modal + XSS-safe `JSON_HEX_TAG`, translations.js 3-language coverage, `js/common.js` nav injection, `artist.php` follow/unfollow, `.htaccess` routing, `how-to-use.php` section17 keys — **total 2036 tests** (13 suites)

### Changed
- 📝 **`how-to-use.php` section17 updated** — My Favorites description updated to mention the solo/group split; A→Z / Z→A sort sub-point added; My Upcoming Programs description updated to "grouped by date"; new "📅 Mini Calendar View" sub-section with 3 bullet points (position, dot indicators, day modal)
- 📝 **`js/translations.js` new keys** — `section17.myfav.sort` and `section17.cal.title` / `section17.cal.feature1-3` added in all 3 languages (TH/EN/JA)
- 📖 **`admin/help.php` + `admin/help-en.php` Artists tab** — three new sub-sections documented: **Copy Artist** (pre-fill behavior, variants checkbox, copy flow), **Bulk Import Artists** (Step 1 textarea → Step 2 results + summary), **Bulk Select & Bulk Actions** (Add to Group / Remove from Group table + `is_group=0` filter callout)

### Files Changed
- `my.php`
- `how-to-use.php`
- `js/translations.js`
- `admin/help.php`
- `admin/help-en.php`
- `tests/FavoritesTest.php` (new)
- `tests/run-tests.php`

## [3.5.1] - 2026-03-20

### Changed
- 🎤 **My Favorites — split into two sections** — solo artists (🎤) and groups (🎵) are now rendered in separate sections instead of a single mixed list; PHP splits `$artistIds` into `$solos` and `$groups` before rendering
- 🔃 **Sort controls per section** — each section has its own A→Z / Z→A sort buttons; sorting is applied client-side with `localeCompare` (locale-aware, handles Thai/Japanese); active sort button is highlighted; preference is saved to `localStorage` (`fav_sort_solo` / `fav_sort_group`) and restored on page load
- 🌐 **i18n** — new translation keys `fav.soloArtists`, `fav.groups`, `fav.sort`, `fav.sortAZ`, `fav.sortZA` added to TH / EN / JA

### Files Changed
- `my-favorites.php`
- `js/translations.js`

## [3.5.0] - 2026-03-20

### Added
- 📋 **Copy Artist modal** — "Copy" button on each artist row opens a pre-filled modal (name + " (copy)", same is_group and group_id); a "Variants to copy" section lists all source variants as checkboxes (all checked by default) with "Select all" / "Deselect all" buttons; all fields are editable before saving; after a successful create, selected variants are created one-by-one via `artists_variants_create`
- 👥 **Bulk artist selection + Bulk Add to Group** — per-row checkboxes with a Select All header checkbox; a yellow Bulk Toolbar appears when ≥ 1 artist is selected; "Add to Group" button opens a group picker modal; "Remove from Group" button clears `group_id` for all selected artists; artists with `is_group = 1` are automatically skipped server-side
- 📥 **Bulk Import Artists** — "📥 Import" button in the Artists toolbar; Step 1 modal accepts a newline-separated list (1 name per line, up to 500), an optional "Is Group" checkbox, and an optional target group dropdown; Step 2 shows a per-name result list (✅ created / ⚠️ duplicate / ❌ error) with a summary bar and a "← Back" button to import another batch; artist list auto-refreshes when any artists were created

- 🔒 **Access denied on `/my` and `/my-favorites` without slug** — visiting either page without a personal slug (UUID-HMAC) now shows a 🔒 "Access Denied" screen with a description and a home button, instead of a generic empty state
- 🌐 **Full 3-language support for `/my` and `/my-favorites`** — all UI text uses `data-i18n` attributes; new translation keys `fav.noAccess` and `fav.noAccessDesc` added to TH / EN / JA in `js/translations.js`

### Changed
- 🔄 **Artists table** — added a checkbox column (individual + select-all) for bulk selection
- 🔄 **`my.php` footer** — aligned with `index.php`: "Built with ❤️ for idol fans" tagline, GitHub link, version badge
- 🔄 **`my-favorites.php` footer** — same footer alignment as `index.php`
- 🔄 **`my.php` header nav** — both ⭐ My Favorites and 📅 My Upcoming Programs buttons always shown when slug is present; current page button highlighted (sakura-medium background)
- 🔄 **`my-favorites.php` header nav** — both ⭐ and 📅 buttons always shown when slug is present; current page button highlighted
- 🔄 **`my.php` program sort order** — Upcoming Programs are now sorted by program start datetime across all followed events (date-first grouping), instead of being grouped by event; each date group shows a date header, and each program row shows the event name as inline metadata; programs within the same date are ordered by start time (`ORDER BY p.start ASC`)

### API
- `POST admin/api.php?action=artists_bulk_set_group` — accepts `{ids[], group_id}`; updates `group_id` for multiple artists (`is_group = 0` only); `group_id = null` removes group membership
- `POST admin/api.php?action=artists_bulk_import` — accepts `{names[], is_group, group_id}`; inserts one artist per name; returns `{results: [{name, status, id?}]}` with `created/duplicate/error` statuses; invalidates caches when `created > 0`

### Files Changed
- `admin/api.php`
- `admin/index.php`
- `config/app.php`
- `my.php`
- `my-favorites.php`
- `js/translations.js`

## [3.4.0] - 2026-03-20

### Added
- ⭐ **Anonymous Favorites system** — users can follow artists without logging in; UUID v7 token + HMAC-signed slug (`{uuid}-{hmac[:12]}`); stored in `cache/favorites/{shard}/{uuid}.json`; TTL 365 days with auto-touch on each visit; `fav_maybe_cleanup()` probabilistic garbage collection
- 📅 **My Upcoming Programs** (`my.php`) — `/my/{uuid-hmac}`; server-side PHP dashboard showing upcoming programs from followed artists, grouped by event and date; Save URL banner (URL + Copy + warning); auto-saves slug to `localStorage`
- ⭐ **My Favorites** (`my-favorites.php`) — `/my-favorites/{uuid-hmac}`; server-side PHP page showing followed artist list with profile links and unfollow buttons; Save URL banner; link button to My Upcoming Programs; auto-saves slug to `localStorage`
- 🔗 **Persistent nav shortcuts** — `injectFavNavButton()` in `js/common.js`; when `fav_slug` exists in `localStorage`, injects ⭐ (`/my-favorites/{slug}`) and 📅 (`/my/{slug}`) as circular icon buttons into `.header-top-left` on all pages; skipped on `/my/` and `/my-favorites/` pages
- 🔌 **Favorites API** (`api/favorites.php`) — `action=follow`, `action=unfollow`, `action=get`, `action=remove`; HMAC validation on all write operations; rate limiting; returns artist details when `?details=1`
- ⚙️ **`config/favorites.php`** — `FAVORITES_DIR`, `FAVORITES_TTL`, `FAVORITES_HMAC_SECRET`, `FAVORITES_HMAC_LENGTH`, `FAVORITES_MAX_ARTISTS`, `FAVORITES_RATE_LIMIT`, `FAVORITES_RATE_WINDOW`, `FAVORITES_RL_DIR`
- 🛠️ **`tools/generate-favorites-secret.php`** — generates a secure 256-bit hex HMAC secret

### Changed
- 🔗 **`.htaccess`** — added `^my-favorites/([0-9a-f-]+)/?$` and `^my/([0-9a-f-]+)/?$` rewrite rules; `^api/favorites/?$` → `api/favorites.php`
- 🎨 **Page titles** — `/my/{slug}` = "📅 My Upcoming Programs"; `/my-favorites/{slug}` = "⭐ My Favorites"
- 🗑️ **Removed how-to-use icon** from event detail header (`index.php`) — reduces icon count on mobile
- 🔄 **`localStorage.fav_slug`** — now a shortcut helper only; auto-saved/replaced when visiting either favorites page via URL
- 🔄 **Follow button toggle** (`artist.php`) — ☆ ติดตาม / ★ ติดตามแล้ว toggles in-place without redirect; first-time follow (no existing `fav_slug`) redirects to `/my-favorites/{slug}`; subsequent follow/unfollow updates button state only

### Files Changed
- `my.php` (new)
- `my-favorites.php` (rewritten)
- `api/favorites.php` (new)
- `functions/favorites.php` (new)
- `config/favorites.php` (new)
- `tools/generate-favorites-secret.php` (new)
- `js/common.js`
- `js/translations.js`
- `styles/common.css`
- `.htaccess`
- `index.php`
- `artist.php`
- `setup.php`
- `config.php`

## [3.3.0] - 2026-03-19

### Added
- 🖼️ **Server-side image export** (`image.php`) — replaces html2canvas with PHP GD; generates PNG server-side with no external JS dependency; supports Thai/multi-byte text via TrueType fonts (Sarabun/Noto, fallback to system fonts); `fonts/README.md` added with font download instructions
- 🎨 **Image layout** — Sakura-themed table with alternating row colors; column header; date group headers; program rows with time badge (sakura-medium pink, white text, compact fixed height), title, venue, type badge, artist badges; vertical column separators between fields; footer with site title + generated timestamp
- 📋 **Single-venue image mode** — when `venue_mode=single`, venue column is removed and venue name (from first program) is shown below event title in image header
- 🔔 **Image export uses current filters** — `saveAsImage()` in `js/common.js` passes current URL query params (artist, venue, type, q, event) to `/image` endpoint; `_t` timestamp prevents browser caching
- 🗄️ **Image cache** (`cache/images/`) — generated PNGs cached server-side for 1 hour (key = md5 of event + filters + lang + APP_VERSION); served via `readfile()` on hit; auto-invalidated when programs are created/updated/deleted via `invalidate_image_cache()`; `IMAGE_CACHE_DIR` + `IMAGE_CACHE_TTL` constants in `config/cache.php`
- 🔤 **Three-font architecture** (`image.php`) — `gdText()` / `gdMeasure()` split text into per-character runs and route each character to the correct font: Thai/Latin → main font; Japanese/CJK → `$fontCjk`; BMP symbols → symbol fallback font
- 🔤 **Japanese / CJK font support** — `isCjkCodepoint()` detects Hiragana (U+3040–U+309F), Katakana (U+30A0–U+30FF), Kanji (U+4E00–U+9FFF), CJK Symbols & Punctuation (U+3000–U+303F, covers 【】「」『』 etc.), and Fullwidth Forms (U+FF00–U+FF9F); `$fontCjk` auto-detected via differential pixel test: か (U+304B) vs き (U+304D) — distinct shapes confirm real Hiragana glyphs
- 🔤 **GNU Unifont as shared-hosting CJK fallback** — `unifont.ttf` / `unifont.otf` added to CJK font candidates; users who place Unifont for symbol support automatically get Japanese rendering without additional files; covers full BMP including Hiragana, Katakana, and common Kanji
- 🔤 **SMP Math Alphanumeric normalization** — `gdNormalizeSmp()` converts Mathematical Alphanumeric Symbols (U+1D400–U+1D7FF) to base ASCII before GD rendering since PHP GD/libgd cannot handle 4-byte UTF-8 (SMP) on many systems; 𝗕𝗔𝗖𝗞 𝗜𝗡 𝗧𝗜𝗠𝗘 → BACK IN TIME; `gdMapMathChar()` covers all major letter/digit style ranges (Bold, Italic, Sans-Serif, Monospace, etc.)
- 🔤 **Reliable font detection** — symbol fallback: differential pixel test ♾ (U+267E) vs ★ (U+2605); CJK: か vs き; both use BMP 3-byte UTF-8 (reliable on all GD builds); rejects color/bitmap fonts (CBDT → 0 pixels) and fonts where both chars render as identical .notdef

### Changed
- 🔄 **`saveAsImage()` rewrite** — removed html2canvas lazy-load; replaced with `fetch()` to `/image` endpoint; downloads PNG via Blob URL
- 🔄 **Image cache key includes active font paths** — adding or replacing a font file automatically busts cached images; prevents serving stale PNGs generated before a font was installed
- 🐛 **Japanese labels now render correctly** — column headers (時間 プログラム 会場 タイプ), date group headers (2026年3月19日（水）), and "no programs" message (プログラムなし) were using `imagettftext()` directly (bypassing per-character routing); changed to `gdText()` so Japanese text is correctly routed to `$fontCjk`
- 🐛 **LIVE indicator `●` → `*`** — `●` (U+25CF) rendered as missing-glyph square on some fonts; replaced with ASCII `*` which all fonts support
- 📖 **`fonts/README.md` rewritten for shared hosting** — new structure: Section 1 (Thai font), Section 2 (GNU Unifont — recommended, covers symbols + Japanese in one file), Section 3 (Symbola — symbol-only alternative), Section 4 (dedicated Noto Sans JP — higher quality Japanese); warning about Google Fonts variable font vs static version; recommended setups table
- 🐳 **Dockerfile** — added `fonts-noto-cjk` for proper Japanese rendering in Docker; updated comments to reflect three-font architecture
- 🎨 **Theme-aware image palette** — generated PNG matches the event's theme; `get_site_theme($eventMeta)` is called before the cache check so each theme gets its own cached image; palette lookup table covers all 7 themes (sakura/ocean/forest/midnight/sunset/dark/gray) with per-theme RGB values for: header background (deep), column header (medium), accent badges, date section background, date text, borders, alternating row tint, and venue subtitle; theme is included in the image cache key so switching a theme automatically invalidates previous cached images
- 🐛 **Artist filter mismatch fix** — `image.php` was filtering artists via the `categories` text field while `index.php` uses the `program_artists` junction table (canonical artist names); fixed by reading `program_artists_map` from query cache when available, and querying the `program_artists` table directly when cache is cold; fallback to `categories` text when junction table is absent — mirrors `index.php` `$useArtistsTable` logic exactly
- ⚡ **Image cache key: `xxh128` replaces `md5`** — `hash('xxh128', ...)` is faster than `md5()` for non-cryptographic cache key generation; produces 32 hex chars (same length as md5); PHP 8.1+ built-in
- 🛠️ **`setup.php` v3.3.0 support** — GD extension check (`extension_loaded('gd') && function_exists('imagettftext')`) added as optional requirement in Step 1; `cache/images/` and `fonts/` directories added to Step 2 directory checks; font file detection for NotoSansThai, NotoSansJP, NotoEmoji, Symbola, unifont (all tested and confirmed working); summary badges (Thai/Latin ✅, Japanese/CJK ✅, Symbols/Emoji ✅); font rows displayed inline with directory rows using `check-row` class for consistent margin/padding

### Files Changed
- `image.php` (new)
- `js/common.js`
- `fonts/README.md` (new)
- `config/cache.php`
- `functions/cache.php`
- `Dockerfile`
- `nginx-clean-url.conf`
- `setup.php`

## [3.2.0] - 2026-03-19

### Added
- **Artist ICS Subscription Feed** (`/artist/{id}/feed`) — live `webcal://` + `https://` feed scoped to a single artist across all events; resolves artist name + all variant names from `artist_variants` table for `categories`-based filtering; cache file `cache/feed_artist_{id}_{hash}.ics` (TTL 1 hour); 404 on unknown artist
- **Group programs feed** (`/artist/{id}/feed?group=1`) — when artist belongs to a group, `?group=1` resolves the `group_id` and filters by group name + group variants; cache key includes `_own` / `_group` suffix
- **`styles/artist.css`** — extracted all inline `<style>` from `artist.php` into a standalone stylesheet (artist header, badges, programs table, toggle, `.btn`, `.btn-subscribe`, `.req-modal-overlay` / modal styles)

### Changed
- **Subscribe buttons on Artist Profile** — header card shows `🔔 <ArtistName>` button; members of a group get a second `🔔 <GroupName>` button for group programs feed; both buttons labeled with the actual name (not generic "Subscribe")
- **`openSubscribeModal(isGroup)` in `js/common.js`** — accepts `isGroup` flag; builds URL as `/artist/{id}/feed` or `/artist/{id}/feed?group=1`; falls back to existing event-feed logic when not on artist page
- **`invalidate_feed_cache()` in `functions/cache.php`** — always deletes `feed_artist_*.ics` alongside event-specific files, since artist feeds span all events
- **`.htaccess`** — new rewrite rule `^artist/([0-9]+)/feed/?$` → `feed.php?artist_id=$1` (placed before the existing artist profile rule)

### Files Changed
- `feed.php`
- `artist.php`
- `js/common.js`
- `functions/cache.php`
- `.htaccess`
- `styles/artist.css` (new)

## [3.1.0] - 2026-03-19

### Added
- **Query Cache for event page** (`index.php`) — DB query results (programs, venues, types, artists, artist maps, cross-event data) cached as `cache/query_event_{id}.json`; cache key includes `$eventId` (0 = no filter); IcsParser + all PDO queries skipped on cache hit; filtering still applied PHP-side from cached data
- **Query Cache for artist profile page** (`artist.php`) — artist info, members, variants, programs, and group programs cached as `cache/query_artist_{id}.json`; all DB queries skipped on cache hit; derived vars (`$byEvent`, `$groupByEvent`, `$totalPrograms`) re-computed from cached data on every request
- **`get_query_cache(string $filename): array|false`** — reads JSON cache file; returns `false` on miss, expiry, or decode error; uses `filemtime()` for TTL check
- **`save_query_cache(string $filename, array $data): void`** — writes array as JSON with `LOCK_EX` to prevent concurrent write corruption
- **`invalidate_query_cache(?int $eventId): bool`** — deletes `cache/query_event_{id}.json` + `cache/query_event_0.json` (global page); no `$eventId` = delete all `query_event_*.json`
- **`invalidate_artist_query_cache(): bool`** — deletes all `cache/query_artist_*.json` files
- **`QUERY_CACHE_DIR`** and **`QUERY_CACHE_TTL`** constants in `config/cache.php`; TTL default 3600 s; shares the `cache/` directory

### Changed
- `invalidate_all_caches()` — now also deletes `query_event_*.json` and `query_artist_*.json` patterns (used after DB restore)
- Admin API program write operations (create, update, delete, bulk delete, bulk update, ICS import confirm) — now call `invalidate_query_cache()` + `invalidate_artist_query_cache()` alongside existing `invalidate_data_version_cache()` + `invalidate_feed_cache()`
- Admin API artist write operations (create, update, delete) — now call `invalidate_artist_query_cache()` alongside existing `invalidate_data_version_cache()`
- Admin API variant write operations (create, delete) — now call `invalidate_artist_query_cache()` (previously had no cache invalidation)

### Files Changed
- `config/cache.php`
- `functions/cache.php`
- `index.php`
- `artist.php`
- `admin/api.php`
- `tools/update-version.php`
- `README.md`
- `PROJECT-STRUCTURE.md`
- `ICS_FORMAT.md`

### Fixed
- **`tools/update-version.php` — smart line-by-line replacement** — เปลี่ยนจาก global `str_replace` เป็น line-by-line พร้อม skip patterns; บรรทัดที่เป็น historical version label จะไม่ถูกแทนที่: `(vX.Y.Z+)` (introduced-in label), `**vX.Y.Z+**:` (bold introduced-in), `| vX.Y.Z |` (table Since column), `| **vX.Y.Z**` (Feature Timeline rows), `### vX.Y.Z —` (historical headings), upgrade guide references (`Upgrading from`, `new vX.Y.Z features`, `all vX.Y.Z features`), inline code comments (`= Something vX.Y.Z`)
- **`README.md`, `PROJECT-STRUCTURE.md`, `ICS_FORMAT.md`** — แก้ไข version labels ของ Artist Reuse System features (v3.0.0 → v3.1.0 ผิดพลาดจาก update-version.php ก่อนหน้า) กลับเป็น v3.0.0 ให้ถูกต้อง

---

## [3.0.0] - 2026-03-18

### Added
- **Artist Reuse System** — `artists` table as single source of truth across all events; artist records reused via `program_artists` junction table.
- **`program_artists` junction table** — many-to-many `programs ↔ artists`; ICS import auto-links CATEGORIES field to `artist_id` by direct name match and variant lookup
- **`artist_variants` table** — stores alias/variant names per artist; manageable via Admin UI variants modal
- **Artist Profile page** (`artist.php`) — `/artist/{id}`; displays all programs grouped by event; shows group members and variant names; `.htaccess` rewrite `^artist/([0-9]+)` → `artist.php?id=$1`
- **Artist Profile programs toggle** (`artist.php`) — pill-style toggle between "Programs ทั้งหมด" (own) and "Programs ในนามวง" (group); shown only when artist belongs to a group; default is own programs; choice persists in `localStorage` per artist
- **Clickable artist badges** — artist badge in program rows is a split pill: left button filters by artist, right `↗` link opens artist profile; uses `program_artists` junction for artist id
- **Artist filter — event count badge** — each artist checkbox shows a pink count bubble when the artist appears in multiple events, plus a `↗` profile link
- **"Also appears in" cross-event section** — rendered before the footer on every event page; groups shared artists by event as flex-wrap cards with artist chips linking to profiles
- **Admin Artists tab** — Variants column shows variant count per artist; Variants button opens modal to add/remove variant names; artist name is a link to the profile page
- **Migration** — `tools/migrate-add-artist-variants-table.php` (idempotent); auto-imports variants from `data/artists-mapping.json`
- **Admin API** — `artists_variants_list`, `artists_variants_create`, `artists_variants_delete`
- **`setup.php` bilingual support (TH / EN)** — language switcher (TH / EN buttons) in setup header; session-based detection (`$_SESSION['setup_lang']`, `?lang=th` / `?lang=en` GET param); all visible UI translated: lock banner, status banners, 6 step titles/badges/labels/descriptions, migration table, config summary, quick links, footer; JS `confirm()` / `alert()` strings injected via PHP `setupI18n` object using `json_encode()` for XSS safety

### Changed
- ICS import (`uploadAndParseIcs`, `confirmIcsImport`) now uses `artist_variants` DB table instead of `data/artists-mapping.json` for auto-linking artist names
- Artist filter in `index.php` reads from `artists` table directly instead of the `categories` text field (falls back to text field if `program_artists` table is absent)
- Admin Programs list — "Categories" column header renamed to **"Artist / Group"**
- Admin Program form — "Categories" label renamed to **"Artist / Group"**; plain text input replaced with **tag-input widget**: artist chips with `×` remove, autocomplete dropdown from `artists` table (🎤 solo / 🎵 group icons), type-and-Enter/comma to add free-text name; new artists created in `artists` table on Save
- Admin Bulk Edit — "Categories" label renamed to **"Artist / Group"**; same tag-input chip widget with autocomplete applied (shared via `createArtistTagInput()` factory function)

### Fixed
- `createProgram()` and `updateProgram()` now call `syncProgramArtists()` — categories edited through Admin UI are reflected in the `program_artists` junction table immediately, so artist filter on the public event page works correctly after saving
- `syncProgramArtists()` auto-creates a new `artists` record (`is_group = 0`) when a category name has no direct name match or variant match, preventing manually typed artist names from being silently dropped

### Added (continued)
- **`artists_autocomplete` Admin API** (`?action=artists_autocomplete&q=...`) — lightweight GET endpoint returning `id`, `name`, `is_group` for matching artists (up to 20; returns top 50 when query is empty); used by the tag-input widget in the program form
- **`createArtistTagInput()` JS factory function** (`admin/index.php`) — shared factory that initializes the tag-input widget for both the single-program form and the Bulk Edit form (different element IDs, same logic); eliminates code duplication; exposes `setValue()` and `reset()` on the returned public API object

### Files Changed
- `artist.php` *(new)*
- `tools/migrate-add-artist-variants-table.php` *(new)*
- `admin/api.php`
- `admin/index.php`
- `index.php`
- `.htaccess`
- `styles/index.css`
- `setup.php`
- `tests/ProgramTypeTest.php`

### Upgrade Notes

> **ℹ️ Not a breaking change** — existing data and all functionality continue to work unchanged after deploying the new code. Fallback code detects whether the new tables exist and gracefully falls back to the `categories` text field if they don't.

**What works without migration** (out of the box):
- ✅ Programs list, Gantt, Calendar view — unchanged
- ✅ Artist filter — works from `categories` text field (fallback mode)
- ✅ ICS import — works (fallback: skips artist auto-linking if tables absent)
- ✅ All admin operations — unchanged

**What requires migration** (to enable new v3.0.0 features):
- ❌ Artist Profile page (`/artist/{id}`) — empty until `artists` table is populated
- ❌ Split badge pills (filter + ↗ profile link) — shows plain badge instead
- ❌ Event-count bubble on artist filter — hidden
- ❌ "Also appears in" cross-event section — not rendered
- ❌ Admin Artists tab Variants modal — variants column empty

**Migration steps** (run once after deploying):

```bash
cd tools

# 1. Create tables + import variant names from data/artists-mapping.json
php migrate-add-artist-variants-table.php

# 2. Link existing programs to artists via CATEGORIES field
php migrate-artists-from-mapping.php
```

After migration, all v3.0.0 features activate automatically.

---

## [2.10.2] - 2026-03-13

### Fixed

- **Calendar view day panel language not updating** (`js/common.js`) — when changing language while a day panel was open, the date header in the panel remained in the previous language; fixed by persisting `_calActiveDayKey`/`_calActiveDayEvs` state in `openDayPanel()`, clearing in `closeDayPanel()`, and re-rendering the panel inside `renderAndMountCalendar()` after each language switch
- **PHP 8.5 deprecation: `ReflectionProperty::setAccessible()`** (`tests/run-tests.php`) — removed two `setAccessible(true)` calls that are no-ops since PHP 8.1 and deprecated in PHP 8.5

### Changed

- **CI/CD: PHP 8.4 and 8.5 added to test matrix** (`.github/workflows/tests.yml`) — extended `php-version` matrix from `['8.1','8.2','8.3']` to `['8.1','8.2','8.3','8.4','8.5']`; also added missing migration scripts `migrate-add-stream-url-column.php` and `migrate-add-contact-channels-table.php` to both `test` and `integration-check` jobs

### Files Changed
- `js/common.js`
- `tests/run-tests.php`
- `.github/workflows/tests.yml`

## [2.10.1] - 2026-03-13

### Fixed
- **`contact.php` long URL overflow on mobile iOS** — added `word-break: break-all` and `overflow-wrap: anywhere` on contact channel `<a>` tags to prevent long URLs from exceeding layout width on narrow screens
- **`credits.php` global view event order** — changed `ksort` to `krsort` so event groups are sorted newest-first (descending by `event_id`); "ทั่วไป" (no event) group moved to last position

### Files Changed
- `contact.php`
- `credits.php`

## [2.10.0] - 2026-03-13

### Added
- **Contact Channels (DB-driven)** — contact channels moved from hardcoded HTML to SQLite `contact_channels` table; Admin › Contact tab (admin role only) with full CRUD (icon, title, description, url, display_order, is_active); table auto-created via `ensureContactChannelsTable()` on first API call — no manual migration required; `setup.php` `init_database` creates the table on fresh install
- **Disclaimer multilingual** — disclaimer text in 3 languages (TH/EN/JA) editable from Admin › Settings; stored in `cache/site-settings.json` (keys: `disclaimer_th`, `disclaimer_en`, `disclaimer_ja`); `get_site_disclaimer()` helper in `functions/helpers.php`; PHP-side translation patching via inline `<script>` injected between translations.js and common.js
- **Migration script** — `tools/migrate-add-contact-channels-table.php` (idempotent)

### Changed
- **`contact.php`** — contact channels rendered server-side from DB; empty state shown when no channels are configured; disclaimer loaded via `get_site_disclaimer()`; removed "ขอบคุณ" (Thank You) section
- **`js/translations.js`** — added `contact.noChannels` key (TH/EN/JA); removed `contact.section3.*` and `contact.social.*` keys
- **`admin/api.php`** — added actions: `disclaimer_get`, `disclaimer_save`, `contact_channels_list`, `contact_channels_get`, `contact_channels_create`, `contact_channels_update`, `contact_channels_delete`
- **`admin/index.php`** — added Contact tab (desktop + mobile dropdown); Disclaimer textareas in Settings section; channel modal; JS functions: `loadDisclaimerSetting()`, `saveDisclaimerSetting()`, `loadContactChannels()`, `renderContactChannels()`, `openChannelModal()`, `closeChannelModal()`, `submitChannelForm()`, `deleteChannel()`
- **`admin/help.php` + `admin/help-en.php`** — added documentation for Contact tab and Disclaimer settings

### Files Changed
- `contact.php`
- `js/translations.js`
- `functions/helpers.php`
- `admin/api.php`
- `admin/index.php`
- `admin/help.php`
- `admin/help-en.php`
- `setup.php`
- `tools/migrate-add-contact-channels-table.php`

## [2.9.0] - 2026-03-13

### Added

- ✨ **Nav icon buttons — Contact & How-to-use** — "ติดต่อเรา" and "วิธีการใช้งาน" links removed from `<nav>` text links and replaced with circular icon buttons in `.header-top-left`; envelope SVG for contact, open-book SVG for how-to-use; consistent order across all pages: home → [event-schedule if in event context] → contact → how-to-use → event-picker
- ✨ **Home icon always goes to root** — home icon on `credits.php`, `how-to-use.php`, and `contact.php` previously linked to `event_url('index.php')` (current event); changed to `get_base_path() . '/'` so it always navigates to the root listing page regardless of event context
- ✨ **New event-schedule icon** — calendar SVG icon added between home and contact; appears only when `$eventMeta` is set (viewing in context of a specific event); links back to that event's schedule via `event_url('index.php')`; tooltip translates via `nav.eventSchedule` (TH: ตารางงาน / EN: Event Schedule / JA: イベント)
- ✨ **Event Picker Modal on `credits.php`** — event-picker grid-dots button and full modal added to `credits.php` header (same condition: `MULTI_EVENT_MODE && count > 1`); modal cards link to `credits.php` of the target event instead of `index.php`; `$activeEvents` and `$today` loaded in PHP header; event picker CSS moved from `styles/index.css` → `styles/common.css` so it is available to all pages
- ✨ **`credits.php` event-specific banner** — when viewing credits for a specific event, a prominent glassmorphism banner displays the event name in the header (`font-size: 1.35em, font-weight: 800`, `backdrop-filter: blur(12px)`, white border); replaces the small `event-subtitle` pill
- ✨ **`credits.php` global view grouped by event** — when no event slug is given, credits are grouped into sections by `event_id`; each section header shows a calendar icon + event name as a clickable link to that event's schedule; credits belonging to inactive/deleted events are hidden; "ทั่วไป" section for `event_id IS NULL` credits (no link)

### Changed

- 🎨 **Credits menu renamed to "แหล่งข้อมูลอ้างอิง"** — `footer.credits` and `listing.credits` translation keys updated in all 3 languages (TH: แหล่งข้อมูลอ้างอิง / EN: References / JA: 参考資料); `credits.title` and `credits.list.title` keys updated to Thai for the TH locale; related section headings (`credits.announcements.title`, `credits.channels.title`, `credits.disclaimer.title`) translated to Thai
- 🎨 **`credits.php` page title translated to Thai** — hardcoded fallback text in `<h1>` and `<h2>` updated from "Credits & References" to "แหล่งข้อมูลอ้างอิง"

### Files Changed
- `index.php`
- `credits.php`
- `how-to-use.php`
- `contact.php`
- `js/translations.js`
- `styles/common.css`
- `styles/index.css`
- `styles/credits.css`

## [2.8.0] - 2026-03-13

### Added

- ✨ **Event Picker Modal** — replaces native `<select>` dropdown for switching events; button is a 38px circular grid-dots icon (top-left, same position as old version badge); modal shows all active events as cards with name, date range, and status badge (Ongoing / Upcoming / Past); currently-viewed event highlighted with a "Viewing" badge
- ✨ **Event Picker search + filter** — real-time search by event name (UTF-8/Thai safe via `data-name` lowercase attribute); status filter tabs (All / Ongoing / Upcoming / Past); both filters combine as AND; "no results" empty state; i18n TH/EN/JA including placeholder
- ✨ **Version moved to footer (all pages)** — app version removed from top-left header badge on all pages including the event listing homepage; now appears inline after "Powered by Stage Idol Calendar" in the footer as `vX.X.X` in monospace on all public pages (`index.php`, `contact.php`, `credits.php`, `how-to-use.php`); `footer-version` CSS class in `styles/common.css`

### Changed

- 🎨 **`index.php` title bar includes event name** — `<title>` renders `[Event Name] - [Site Name]` when viewing a specific event for better social sharing previews (from v2.7.6)
- 🎨 **Gantt bar layout — time + title inline** — time and title on same row (`flex`); type badge below; title truncates with `…` (from v2.7.7)
- 🎨 **Event Picker modal sort order** — events sorted: currently-viewing (top) → ongoing (start DESC) → upcoming (start ASC, nearest first) → past (start DESC); `usort()` in modal render loop; filter tabs still work independently after sort
- 🎨 **Event Picker mobile layout** — bottom-sheet modal (slides up, `border-radius: 16px 16px 0 0`); grid switches to flex list; each row uses CSS grid (2-col: name+date left, badges right) so status badge and "Viewing" badge never overlap or wrap; dates restored on mobile

### Files Changed
- `index.php`
- `js/common.js`
- `js/translations.js`
- `styles/index.css`
- `styles/common.css`
- `contact.php`
- `credits.php`
- `how-to-use.php`

## [2.7.7] - 2026-03-13

### Changed

- 🎨 **Gantt bar layout — time + title inline** — inside each program bar, time and title are now displayed on the same row (`display: flex; align-items: baseline`) instead of stacked vertically; type badge moves below the row; title truncates with `…` (`white-space: nowrap; text-overflow: ellipsis`) instead of 2-line clamp; makes short bars more readable at a glance

### Files Changed
- `js/common.js`
- `styles/index.css`

## [2.7.6] - 2026-03-13

### Added

- ✨ **Event name in page title** — `index.php` `<title>` now renders `[Event Name] - [Site Name]` when viewing a specific event (e.g. `Idol Stage Feb 2026 - Idol Stage Timetable`); improves social sharing previews and browser tab clarity; falls back to site name only on the event listing page or when event name equals site name

### Fixed

- 🐛 **`js/common.js` unused variable** — `const lang` in `openCalendarDetailModal()` was declared but never referenced; removed to eliminate lint warning

### Files Changed
- `index.php`
- `js/common.js`

## [2.7.5] - 2026-03-12

### Fixed

- 🐛 **`feed.php` SUMMARY comma truncation** — `icsEscape()` was escaping commas to `\,` in SUMMARY; some calendar clients (iOS, Outlook) misinterpret `\,` and truncate the event title at that position. Added `icsEscapeText()` for single-value TEXT properties (SUMMARY, LOCATION, DESCRIPTION) that leaves commas unescaped — commas are not value delimiters in these properties, so this is safe and matches Apple Calendar / Google Calendar export behaviour. `icsEscape()` (with comma escaping) is still used for individual CATEGORIES values where comma IS the RFC 5545 value delimiter.
- 🐛 **`feed.php` calendar header properties unescaped** — `X-WR-CALNAME`, `X-WR-CALDESC`, and `PRODID` were outputting `$calName`/`$siteTitle` without any escaping; backslash/semicolon/newline in the event name or site title would produce a malformed ICS header. `X-WR-CALNAME` now uses `icsEscape()` (comma escaped to `\,` to prevent calendar-name truncation at comma in clients); `X-WR-CALDESC` and `PRODID` use `icsEscapeText()` (comma left unescaped as it is plain text).
- 🧪 **FeedTest +11 tests** — `_feed_icsEscapeText()` replica + 7 unit tests + 2 SUMMARY source-check tests + 2 header-escaping source-check tests; total 1630 tests (80 in FeedTest)

### Files Changed
- `feed.php`
- `tests/FeedTest.php`

## [2.7.4] - 2026-03-12

### Fixed
- 🔒 **`credits.php` inactive event data leak** — same root cause as v2.7.3: inactive slug caused `$eventId = null`, exposing credits from all events; now returns 404 page
- 🔒 **`index.php` `$_SERVER["SCRIPT_NAME"]` JS injection** — replaced bare `echo` with `json_encode()` for `BASE_PATH` constant; prevents JS syntax error if server path contains quotes or backslashes
- 🔒 **`api/request.php` datetime validation strengthened** — replaced lenient `strtotime()` check with `checkdate()` + explicit range checks (`hour ≤ 23`, `minute ≤ 59`, `second ≤ 59`); rejects overflow values such as month 13, Feb 31, or hour 25 that `strtotime()` silently accepted by rolling over
- 🔒 **`functions/cache.php` concurrent write without lock** — `file_put_contents()` in `get_data_version()` and `get_cached_credits()` now uses `LOCK_EX` flag to prevent cache file corruption under concurrent requests
- 🔒 **`admin/api.php` restore without guaranteed auto-backup** — `copy()` return value for auto-backup was ignored in both `restoreBackup()` and `uploadAndRestoreBackup()`; restore now aborts with an error if the auto-backup copy fails (e.g. disk full or permission denied), preventing data loss
- 🔒 **`admin/api.php` ICS upload MIME over-permissive** — removed `application/octet-stream` from allowed MIME types (accepted any binary file); added structural validation that uploaded content contains `BEGIN:VCALENDAR` and `END:VCALENDAR` before parsing
- 🔒 **`admin/api.php` `stream_url` scheme not validated** — `createProgram()` and `updateProgram()` stored any value for `stream_url` including `javascript:` URIs; now validates with `preg_match('/^https?:\/\//i')` and stores `null` for non-http(s) values, preventing stored XSS via stream URL
- 🐛 **`feed.php` TOCTOU race condition on cache read** — `file_exists()` + `readfile()` had a window where the cache file could be deleted (by a concurrent `invalidate_feed_cache()`) between the two calls, causing a PHP warning and empty response; replaced with a single `@file_get_contents()` call that gracefully falls through to regenerate on race loss

### Files Changed
- `credits.php`
- `index.php`
- `api/request.php`
- `functions/cache.php`
- `admin/api.php`
- `feed.php`

## [2.7.3] - 2026-03-12

### Fixed
- 🔒 **Inactive event data leak** — when a specific event slug was requested but the event was inactive (or did not exist), `get_event_by_slug()` returned `null`, causing `$eventId` to be `null`; `IcsParser` then fetched programs from **all events** instead of returning nothing
  - `feed.php` — returns HTTP 404 instead of serving another event's ICS feed
  - `export.php` — returns HTTP 404 instead of exporting another event's ICS file
  - `api.php` — returns HTTP 404 with an empty JSON array instead of leaking programs from other events
  - `api/request.php` `getEvents()` — returns an empty program list instead of returning programs from all events
  - `index.php` — renders a 404 HTML page with a link back to the homepage instead of displaying programs from all events

### Tests
- 🧪 **3 new tests in `IntegrationTest`** — cover the inactive event scenarios that triggered the data leak; total 1587 tests across 12 suites
  - `testGetEventBySlugReturnsNullForInactiveEvent` — `get_event_by_slug()` must return `null` when `is_active = 0`
  - `testGetEventIdReturnsNullForInactiveEvent` — `get_event_id()` must return `null` when event is inactive
  - `testGetAllActiveEventsExcludesInactiveEvent` — `get_all_active_events()` must not include inactive events

### Files Changed
- `feed.php`
- `export.php`
- `api.php`
- `api/request.php`
- `index.php`
- `tests/IntegrationTest.php`

## [2.7.2] - 2026-03-12

### Changed
- 🔧 **Rename `$eventMetaId` → `$eventId` across codebase** — the old name was a leftover from the `events_meta` era (before the v1.2.9 table rename to `events`); all public pages, APIs, cache functions, and tests now use the consistent name `$eventId`
  - **Files updated:** `feed.php`, `export.php`, `credits.php`, `index.php`, `api.php`, `api/request.php`, `tools/import-ics-to-sqlite.php`, `functions/cache.php`, `tests/FeedTest.php`
  - No functional changes — rename only

### Files Changed
- `feed.php`
- `export.php`
- `credits.php`
- `index.php`
- `api.php`
- `api/request.php`
- `tools/import-ics-to-sqlite.php`
- `functions/cache.php`
- `tests/FeedTest.php`

## [2.7.1] - 2026-03-11

### Added
- ✨ **Duration display in calendar detail** — detail modal and day panel now show program duration `(Xh Ym)` next to the time range; computed via `formatDuration()` helper in `js/common.js`

### Fixed
- 🐛 **Calendar view right-edge gap** — replaced `border: 1px solid` on `.month-calendar` with `box-shadow: inset 0 0 0 1px`; physical border was consuming 1px of content area, leaving a visible sub-pixel gap between the rightmost grid column and the border in rows with dark backgrounds (DOW header, trailing empty cells)
- 🐛 **Cell divider pixel-rounding artifact** — changed `.cal-dow` and `.cal-day` from `border-right` to `border-left`; right-side borders can leave a residual strip at the grid's right edge due to pixel rounding across 7 columns; left-side borders eliminate this by anchoring dividers to the leading edge of each column

### Files Changed
- `js/common.js`
- `styles/common.css`

## [2.7.0] - 2026-03-11

### Added
- 📅 **Calendar View (`venue_mode = 'calendar'`)** — third venue type alongside multi/single, designed for stream/online event schedules
  - Monthly 7-column grid with ◀ ▶ navigation; navigation is restricted to months that have programs (buttons hidden when only one month exists)
  - **Desktop**: per-day program chips — platform icon (📷/𝕏/▶️/🔴) + artist name + time; tap chip → detail modal (header: program title; body: time + Join Live button)
  - **Mobile**: dot indicators per day (up to 3 dots + "+N"); tap day → day panel below grid showing full program list with title, categories, time, type badge, description, and Live button; grid fills full width with `minmax(46px, 1fr)` columns, scrolls on narrow screens
  - All colors use CSS variables — compatible with all 6 themes (Ocean/Forest/Midnight/Sunset/Gray/Dark)
  - XSS-safe: index-based chip registry (`window._calChipEvents`) + panel-specific registry (`window._calDpEvents`) — no JSON in HTML attributes
  - List/Timeline toggle hidden in calendar mode
  - Full i18n: month/day names re-render automatically on language change (TH/EN/JA)
  - Admin Events form: added `Calendar` option to Venue Mode dropdown
  - Updated user guide (`how-to-use.php`) and admin help (`admin/help.php`, `admin/help-en.php`) with Calendar View documentation

### Files Changed
- `index.php`
- `admin/api.php`
- `admin/index.php`
- `admin/help.php`
- `admin/help-en.php`
- `how-to-use.php`
- `js/common.js`
- `js/translations.js`
- `styles/common.css`

## [2.6.5] - 2026-03-10

### Security
- 🔒 **XSS fix in filter tag removal buttons** — `index.php` onclick handlers for artist/type/venue tag-remove buttons used `addslashes()` (database escaping, not JS-safe) combined with `htmlspecialchars()`; replaced with `json_encode()` + `htmlspecialchars(ENT_QUOTES)` which correctly encodes all special characters for inline JavaScript context
  - **Affected lines**: `index.php:343`, `index.php:370`, `index.php:399`
- 🔒 **Race condition fix in public request rate limiting** — `checkRateLimit()` and `recordRequest()` in `api/request.php` had a TOCTOU race: concurrent requests could all pass the limit check before any recorded, multiplying effective limit; fixed with `flock(LOCK_EX)` wrapping the full read→modify→write cycle
- 🔒 **JSON error handling in rate limit files** — `json_decode()` return value was checked with `!$data` (falsy), silently treating corrupted files as empty; replaced with explicit `json_last_error() !== JSON_ERROR_NONE` check in both `checkRateLimit()` and `recordRequest()`

### Files Changed
- `index.php`
- `api/request.php`

---

## [2.6.4] - 2026-03-09

### Fixed
- 🐛 **ICS Import preview edit bug** — `editPreviewEvent()` referenced `getElementById('eventTitle')` which does not exist in the DOM (actual ID is `title`), causing `TypeError: Cannot set properties of null` and breaking the ✏️ edit button on all preview rows
- 🐛 **ICS Import preview edit missing fields** — `programType` and `streamUrl` were not populated when opening the preview edit modal, so existing values were lost silently on save
- 🐛 **ICS Import preview edit saved to DB instead of preview** — `saveEvent()` never read `window.previewEditIndex`, so clicking Save in preview-edit mode would POST a new record to the database instead of updating the in-memory preview; fixed by adding an early-return block that updates `uploadedEvents[index]` and re-renders the preview table
- 🐛 **`previewEditIndex` state leak** — `closeModal()` now resets `window.previewEditIndex = null` to prevent preview-edit mode from persisting into subsequent normal add/edit modal opens

### Files Changed
- `admin/index.php`

---

## [2.6.3] - 2026-03-06

### Fixed
- 🗑️ **ORGANIZER parse error fix (Outlook calendar wipe)** — removed `ORGANIZER` property from VEVENT in `feed.php`; this is the root cause of all events disappearing from Outlook after every subscription pull
  - **Root cause**: `ORGANIZER;CN="event name":mailto:...` applied `icsEscape()` to the CN parameter value, converting `,` → `\,` and `;` → `\;`; RFC 5545 QUOTED-STRING parameters do not use backslash escaping — the literal backslash characters caused Outlook's strict ICS parser to reject the VEVENT and stop processing the entire file; Outlook then cleared all existing calendar entries and imported nothing
  - **Fix**: `ORGANIZER` property removed from VEVENTs entirely (optional in METHOD:PUBLISH feeds); event name is already conveyed via `X-WR-CALNAME` at the VCALENDAR level
  - **Affected endpoint**: `GET /feed` and `GET /event/{slug}/feed`
  - **Impact**: High — all subscribed Outlook calendars would empty on every sync cycle
- 🔄 **ICS feed Cache-Control hardened for CDN/proxy bypass** — `Cache-Control` upgraded from `no-cache` to `no-store, no-cache`; `Pragma: no-cache` added for legacy proxy compatibility
  - **Reason**: `no-cache` alone allows CDN proxies (e.g. Cloudflare) to store the response and serve 304 from their own cache, bypassing origin ETag checks; `no-store` instructs all intermediate proxies not to store the response at all
- 🔄 **ICS feed sync fix for iOS Calendar** — `Cache-Control` changed from `public, max-age=3600` to `no-store, no-cache` so iOS always re-validates with the server on every poll; ETag + 304 Not Modified is still used to avoid re-downloading unchanged content
  - **Root cause**: `max-age=3600` instructed iOS to serve the cached feed for up to 1 hour without contacting the server at all — no `If-None-Match` request was ever sent during that window, so data changes made in the admin panel were invisible to subscribed calendars until the cache expired
- 📅 **DTSTAMP stability fix** — `DTSTAMP` (and new `LAST-MODIFIED`) in each VEVENT is now sourced from the program's `updated_at` database column instead of the current request timestamp
  - **Root cause**: `DTSTAMP` was set to `gmdate('Ymd\THis\Z')` (current time) on every request, so every feed refresh presented all events as newly modified while `SEQUENCE:0` remained constant
  - **Fix**: `DTSTAMP` and `LAST-MODIFIED` now reflect the actual last-edit time of each event; they only change when an admin modifies the program record
  - **Files changed**: `feed.php`, `IcsParser.php` (added `updated_at` to SELECT queries)

### Files Changed
- `feed.php`
- `IcsParser.php`

---

## [2.6.2] - 2026-03-05

### Fixed
- 🔒 **Directory access hardening** — `.htaccess` files in `data/`, `cache/`, `backups/`, and `ics/` previously allowed access from local network ranges (`192.168.0.0/16`, `10.0.0.0/8`); now set to `Deny from all` to prevent direct web access to sensitive files (database, backups, cache) from any IP
  - **Vulnerability**: Users on the same LAN could download `backups/*.db` (full database including password hashes), read `cache/login_attempts.json`, `cache/site-settings.json`, and raw `.ics` files
  - **Fix**: Commented out all `Allow from` rules — effective policy is now `Deny from all` for all four directories
  - **Affected directories**: `data/`, `cache/`, `backups/`, `ics/`
  - **Impact**: Medium (requires attacker to be on the same local network), but could expose full database content
- 🔒 **Path disclosure fix in public API** — `api/request.php` previously leaked server file paths and PDO error details in JSON error responses visible to anyone
  - **Vulnerability**: DB-not-found error returned `'Database file not found: /var/www/...'`; connection failure returned full `PDOException::getMessage()` including internal paths; query failure returned raw SQL error text
  - **Fix**: All three error responses replaced with generic messages (`'Service unavailable'`, `'Failed to fetch programs'`) — internal details no longer exposed
  - **Affected endpoint**: `GET/POST /api/request.php`
  - **Impact**: Low (information disclosure only), but reveals server filesystem layout to unauthenticated users

### Files Changed
- `data/.htaccess`
- `cache/.htaccess`
- `backups/.htaccess`
- `ics/.htaccess`
- `api/request.php`

---

## [2.6.1] - 2026-03-05

### Fixed
- 🔒 **LIKE SQL Injection prevention** — admin search queries in `listPrograms()` and `listCredits()` now properly escape LIKE wildcard characters (`%`, `_`) before constructing the WHERE clause; added ESCAPE clause to LIKE operators so special characters are treated as literals, not wildcards
  - **Vulnerability**: User input like `a%` would be interpreted as `LIKE '%a%%'` (match anything starting with 'a'), allowing predictable query result manipulation
  - **Fix**: Input is escaped via `str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search)` before being wrapped with wildcards; LIKE clause becomes `title LIKE :search ESCAPE '\\'` which treats escaped characters as literals
  - **Affected endpoints**: `GET /admin/api.php?action=programs_list` (programs search) and `GET /admin/api.php?action=credits_list` (credits search)
  - **Impact**: Low (admin-only, requires login + CSRF token), but essential for defense-in-depth

### Files Changed
- `admin/api.php`

---

## [2.6.0] - 2026-03-04

### Added
- 📅 **Date Jump Bar desktop scroll** — arrow buttons `◀` `▶` flanking the date buttons strip let mouse users scroll to dates outside the viewport; mousewheel over the strip now scrolls horizontally (`wheel` → `scrollLeft`); thin 3px sakura-tinted scrollbar shown on `@media (hover: hover)` devices only; `updateArrows()` hides the relevant arrow when the strip is already at its start/end
- 🔴 **Live Stream support (`stream_url`)** — new `stream_url TEXT DEFAULT NULL` column in `programs` table stores IG Live / X Spaces / YouTube Live links
  - **Public UI (`index.php`)**: rows with a stream URL get class `program-live` (subtle pink left glow); platform icon (📷 Instagram / 𝕏 X/Twitter / ▶️ YouTube / 🔴 other) + `🔴 เข้าร่วม` join button rendered inline in the title cell
  - **Admin UI (`admin/index.php`)**: `streamUrl` input (type=url) in the program form; list rows show `🔴` badge linked to the live URL; `openEditModal()` / `duplicateEvent()` pre-fill the field; `saveEvent()` includes `stream_url` in the payload
  - **Admin API (`admin/api.php`)**: `listPrograms`, `getProgram`, `createProgram`, `updateProgram`, and `confirmIcsImport` all read/write `stream_url`
  - **Public API (`api.php`)**: `stream_url` included in `$fieldsToEscape` → XSS-safe field in JSON response
  - **ICS Parser (`IcsParser.php`)**: parses RFC 5545 `URL:` property → `stream_url`; `getAllEventsFromDatabase()` SELECTs `stream_url`
  - **Export (`export.php`, `feed.php`)**: emits `URL:<stream_url>` VEVENT property when `stream_url` is not empty
  - **CSS (`styles/index.css`)**: `.program-live`, `.program-live-icon`, `.program-join-btn`, `.program-join-btn:hover`, `.stream-link-badge`
  - **Translations (`js/translations.js`)**: `badge.joinLive` key in TH (`🔴 เข้าร่วม`) / EN (`🔴 Join Live`) / JA (`🔴 参加する`)
  - **Migration (`tools/migrate-add-stream-url-column.php`)**: idempotent `ALTER TABLE` script for existing installs
  - **Setup (`setup.php`)**: `stream_url TEXT DEFAULT NULL` in `CREATE TABLE programs`; `fix_programs_title` migration preserves `stream_url`; `$allTablesOk` checks `$hasStreamUrlColumn`
  - **Tests (`tests/StreamUrlTest.php`)**: 31 new tests (schema, migration idempotency, CRUD, IcsParser URL parsing, admin/public API, export/feed URL: property, public/admin UI, CSS, translations, setup.php) — total **1584 tests** (12 suites)

### Files Changed
- `styles/index.css`
- `index.php`
- `admin/index.php`
- `admin/api.php`
- `api.php`
- `IcsParser.php`
- `export.php`
- `feed.php`
- `setup.php`
- `js/translations.js`
- `tests/run-tests.php`
- `tests/ProgramTypeTest.php`
- 🆕 `tools/migrate-add-stream-url-column.php`
- 🆕 `tests/StreamUrlTest.php`

---

## [2.5.4] - 2026-03-04

### Added
- 📖 **How-to-use expanded** — `how-to-use.php` updated to cover all current end-user features: Filter by Type (section 2 item 3, with renumbering), Subscribe to Calendar (🔔 section 3 item 3 with webcal/Google/Outlook steps), Date Jump Bar (new section), Description Modal/Read More (new section)
- 🌐 **Translation keys** — `js/translations.js` adds `section2.filter3.*`, `section3.subscribe.*`, `section9.*`, `section10.*` in all 3 languages (TH/EN/JA); renumbers `section2.action.title` → 4, `section2.selectedTags.title` → 5, `section2.quickFilter.title` → 6

### Files Changed
- `how-to-use.php`
- `js/translations.js`

---

## [2.5.3] - 2026-03-03

### Changed
- 🔧 **`GOOGLE_ANALYTICS_ID` moved to `config/analytics.php`** — extracted GA Measurement ID from `config/app.php` into a dedicated `config/analytics.php`; prevents `tools/update-version.php` from touching the site-specific GA ID when bumping the version; `config.php` bootstrap loads the new file automatically

### Files Changed
- 🆕 `config/analytics.php`
- `config/app.php`
- `config.php`

---

## [2.5.2] - 2026-03-03

### Added
- ⚡ **Feed static file cache** — `feed.php` now captures generated ICS output via `ob_start()`/`ob_get_clean()` and saves it to `cache/feed_{eventId}_{hash}.ics`; subsequent requests are served with `readfile()` — no SQLite query, no IcsParser instantiation, no event filtering on every hit
- 🔑 **Cache key includes sorted filter params** — `artist[]`, `venue[]`, `type[]` arrays are sorted before hashing so `?artist[]=A&artist[]=B` and `?artist[]=B&artist[]=A` always map to the same cache file
- 🗑️ **`invalidate_feed_cache($eventMetaId)`** — new function in `functions/cache.php`; deletes matching `feed_*.ics` files; when a specific event is invalidated, the global (`feed_0_*.ics`) cache is also cleared; `invalidate_all_caches()` updated to include feed ICS files
- 🔄 **Auto-invalidate on data change** — `admin/api.php` calls `invalidate_feed_cache()` immediately after `invalidate_data_version_cache()` at all 6 program write operations: `createProgram`, `updateProgram`, `deleteProgram`, `bulkDeletePrograms`, `bulkUpdatePrograms`, and `confirmIcsImport`
- ⚙️ **`FEED_CACHE_DIR` + `FEED_CACHE_TTL`** — new constants in `config/cache.php`; TTL default 3600 s (1 hour); directory is the existing `cache/` folder
- 🧪 **FeedTest** — 20 new cache tests added (total 69 / 1276 cumulative)

### Files Changed
- `feed.php`
- `functions/cache.php`
- `config/cache.php`
- `admin/api.php`
- 🆕 `tests/FeedTest.php` (+20 tests)

---

## [2.5.1] - 2026-03-03

### Fixed
- 🔧 **RFC 5545 line folding** — `feed.php` now folds any ICS property line exceeding 75 octets with CRLF + SPACE continuation; UTF-8 multi-byte character boundaries are respected (Thai characters 3 bytes/char are never split mid-sequence); required for strict RFC 5545 compliance and Outlook parsing
- 🏷️ **CATEGORIES comma delimiter fix** — `feed.php` previously escaped all commas (`\,`) in CATEGORIES via the shared escape function, causing Outlook to treat `Artist1\,Artist2` as a single category; fixed by splitting on `,` first, escaping each value individually (no comma escaping inside values), then rejoining with unescaped delimiter commas — Outlook now correctly shows N separate categories
- ✏️ **ICS text value escaping (`icsEscape()`)** — properly escapes backslash (first), semicolon, comma, and newline per RFC 5545 §3.3.11; used for SUMMARY, LOCATION, DESCRIPTION, ORGANIZER CN; CATEGORIES values go through the same function after being split, so delimiter commas in `implode(',', ...)` are never passed to the escaper
- 📧 **Outlook subscribe instructions** — subscribe modal now shows a dedicated Outlook instruction box (blue highlight) with step-by-step path: Outlook → Calendar → Add calendar → **Subscribe from web** → paste URL; clarifies that `webcal://` is for Apple/iOS/Thunderbird and `https://` is for Google Calendar / Outlook
- 📱 **Mobile action buttons compact layout** — filter buttons changed from `flex-direction: column` (6 full-width rows, ~338px) to `flex-wrap: wrap` with `flex: 1 1 calc(33.33% - 4px)` (3 per row = 2 rows, ~86px); scoped to `.filter-buttons .btn` so modal buttons are unaffected; reduced padding `8px 6px`, font `0.82em`, min-height `40px`
- 📱 **Subscribe modal URL input overflow** — flex container and input now have `min-width: 0` preventing flex overflow on narrow screens; input `font-size` raised to `1rem` (≥16px) to prevent iOS auto-zoom; `overflow: hidden; text-overflow: ellipsis` truncates long URLs instead of pushing the Copy button off-screen

### Files Changed
- `feed.php`
- `index.php`
- 🆕 `tests/FeedTest.php` (49 tests)

---

## [2.5.0] - 2026-03-03

### Added
- 🔔 **ICS Subscription Feed (`feed.php`)** — live calendar subscription endpoint; subscribe once and your calendar app auto-syncs whenever programs are added or changed; supports `webcal://` (Apple Calendar, iOS, Thunderbird) and `https://` (Google Calendar, Outlook)
  - URLs: `/feed` (all events) and `/event/{slug}/feed` (specific event) via existing `.htaccess` rules — no new rewrite rules needed
  - HTTP caching: `ETag` based on `get_data_version()`, `Cache-Control: public, max-age=3600`; calendar apps receive `304 Not Modified` when data is unchanged
  - Refresh hints: `X-PUBLISHED-TTL:PT1H` (Apple Calendar) + `REFRESH-INTERVAL;VALUE=DURATION:PT1H` (RFC 7986 / Google Calendar)
  - Filter parameters: `?artist[]=X&venue[]=Y&type[]=Z` — same as export.php
  - 15-minute `VALARM` reminder on every event (same as export.php)
- 🔔 **Subscribe button** — `🔔 Subscribe` button (`btn-subscribe`, purple gradient) added to filter action bar alongside Export; opens subscribe modal
- 🔔 **Subscribe modal** — shows webcal:// link (tap to open in Calendar App), https:// URL with Copy button, and Outlook-specific instructions; translations in TH/EN/JA
- 🗑️ **`invalidate_data_version_cache()`** — new function in `functions/cache.php`; deletes `cache/data_version*.json` for a specific event or all events; called by admin/api.php after every programs CRUD operation and ICS import so the feed ETag updates immediately without waiting for the 10-minute cache TTL

### Changed
- ⚡ **Admin programs CRUD triggers data version cache invalidation** — `createProgram()`, `updateProgram()`, `deleteProgram()`, `bulkDeletePrograms()`, `bulkUpdatePrograms()`, and `confirmIcsImport()` in `admin/api.php` all call `invalidate_data_version_cache()` on success; ensures subscribed calendar apps receive fresh data on their next poll after admin changes

### Files Changed
- 🆕 `feed.php`
- `index.php`
- `js/common.js`
- `js/translations.js`
- `functions/cache.php`
- `admin/api.php`
- `config/app.php`

---

## [2.4.7] - 2026-03-03

### Added
- 📍 **Venue display in single venue mode** — when `venue_mode = single`, a `📍 venue name` line appears below the event name subtitle in the page header; derived from the first entry in `$venues` (aggregated from programs); not shown in multi-venue mode

### Fixed
- 📱 **Event selector dropdown overflow on mobile** — `.program-selector select` now has `max-width: 100%` and `box-sizing: border-box`; on `≤768px` breakpoint `width: 100%; min-width: 0` overrides the desktop `min-width: 200px` so long event names no longer overflow the header

### Files Changed
- `index.php`

## [2.4.6] - 2026-03-03

### Changed
- 🃏 **Event listing: horizontal card layout** — main event listing page redesigned from vertical cards to horizontal-style cards; gradient header (name + date) spans full width at top, body section shows status badge + description + meta inline with "View Schedule" button on the right; mobile collapses to compact vertical card
- 📖 **Event description modal** — clicking/tapping a truncated description opens a modal with full event info (name, dates, status badge, full description, meta, link); "▼ Read more" chip button appears only when text is actually clamped (`scrollHeight > clientHeight`)
- 👆 **Read-more button: touch-friendly chip** — `▼ Read more` restyled from plain text link to pill-shaped chip with sakura background, border, `min-height: 30px`, and `-webkit-tap-highlight-color: transparent` for easier mobile tapping
- 📱 **Program table mobile: compact card redesign** — each program row now renders as a compact card with gradient time strip at top; reduced `tr` padding from 15px → 0 (cells handle own spacing), `td` padding from 8px 0 → 4px 12px; total vertical saving ~120px per card
- ✏️ **Edit button repositioned on mobile** — `program-action-cell` is `position: absolute; top: 33px; right: 8px` inside `position: relative` card; appears as 30×30px icon button in top-right of white body area without adding card height; fixed CSS specificity to override `width: 100% !important` using `.events-table tbody .program-action-cell`
- 🙈 **Empty cells hidden on mobile** — venue, type, and categories cells with no data receive `cell-empty` class and are `display: none !important`; removes all padding/space for empty fields
- ➖ **Removed `-` dash for empty data** — cells show nothing when data is absent (venue, type, categories, title fallback)
- ↔️ **Type + Categories on same line (mobile)** — `program-type-cell` and `program-categories-cell` changed to `display: inline-flex !important; width: auto !important` using higher-specificity selector to beat `td { width: 100% !important }`
- 🏷️ **Badge size unified** — `program-categories-badge` and `program-type-badge` share identical layout properties (`padding: 4px 12px`, `border-radius: 16px`, `font-size: 0.85em`, `margin: 2px 2px 2px 0`); only background/text color differs; mobile override reduces both equally (`padding: 3px 9px`, `font-size: 0.8em`)

### Files Changed
- `index.php`
- `styles/common.css`

---

## [2.4.5] - 2026-03-03

### Changed
- 🕐 **Collapse same-time display** — when a program's start time equals its end time (HH:MM), only the start time is shown (no `- end time`); applies to List view, Gantt tooltip, and Admin Programs list
- 📅 **Collapse same-date display** — when a convention's start date equals its end date, only the start date is shown on the event listing card (no `- end date`); Admin ICS import preview also collapses same-date and same-time ranges via new `formatDateTimeRange()` helper

### Files Changed
- `index.php`
- `admin/index.php`

---

## [2.4.4] - 2026-03-03

### Added
- 🔧 **`tools/update-version.php`** — automated version update script; updates `APP_VERSION` in `config/app.php` and 8 documentation files in a single command (`php tools/update-version.php X.Y.Z`); excludes `CHANGELOG.md` which require manual content
- 📅 **ICS Export: 15-minute reminder** — every exported VEVENT now includes a `VALARM` component (`TRIGGER:-PT15M`, `ACTION:DISPLAY`) so Google Calendar, Apple Calendar, and other RFC 5545-compliant apps will show a notification 15 minutes before each program

### Fixed
- 🧪 **`IntegrationTest::testDocumentationExists`** — removed `QUICKSTART.md` and `SQLITE_MIGRATION.md` from the docs list after both files were deleted (merged into README.md and PROJECT-STRUCTURE.md in this version)

### Files Changed
- `export.php`
- `tests/IntegrationTest.php`
- 🆕 `tools/update-version.php`

### Documentation
- 🌐 **Full English translation** — translated `SETUP.md`, `CHANGELOG.md`, `API.md`, and `PROJECT-STRUCTURE.md` from Thai/mixed to English
- 📝 **README.md Features section updated** — added missing v2.4.x features: Program Types, Quick Filter Badges, Date Jump Bar, Per-Event Theme, Site Title Setting, Setup Wizard; corrected Venue Mode table (List/Timeline toggle is `Visible` in single-venue mode, per v2.3.4 change); updated unit test count to 999 across 10 suites
- 🔀 **Documentation consolidation** — merged `QUICKSTART.md` into `README.md` (expanded Quick Start + Core Features table + Pro Tips section); merged `SQLITE_MIGRATION.md` into `PROJECT-STRUCTURE.md` (Database Schema, Performance benchmarks, and Management sections); deleted both merged source files
- ➕ **`API.md` updates** — added `?type=X` public API filter and `programs_types` admin endpoint (both introduced in v2.4.0 but missing from docs); translated all remaining Thai text

---

## [2.4.3] - 2026-03-02

### Added
- 🧪 **ProgramTypeTest** — 35 automated tests covering all changes in v2.4.x
  - **Schema**: `programs.program_type` column exists and is nullable
  - **Migration**: `tools/migrate-add-program-type-column.php` exists and is idempotent
  - **CRUD**: insert/read/update/delete `program_type` values including NULL
  - **Public API**: `?type=` filter works via `$typeFilter` variable
  - **Admin API**: `programs_types` action, `SELECT DISTINCT program_type`, CREATE/UPDATE/bulk-update handle `program_type`
  - **index.php UI**: `appendFilter()` function, `URLSearchParams`, `$hasTypes` flag, `.event-subtitle`, `data-i18n="table.type"`, clickable badges, `htmlspecialchars(json_encode())` pattern
  - **Translations**: `'table.type'` key present in all 3 languages (Type / Type / タイプ), appearing 3 times
  - **Admin UI v2.4.2**: `sortBy('categories')`, no `sortBy('organizer')`, `event.categories`, no `<th>Organizer</th>`
- 📊 **Total tests: 999** (increased from 964 → 999, all passing across 10 suites)

### Fixed
- 🐛 **setup.php `fix_programs_title` — `program_type` column lost after fix** — this action recreates the `programs` table from `summary` → `title`, but the new `CREATE TABLE` was missing the `program_type` column, causing it to be immediately dropped
  - Added `program_type TEXT DEFAULT NULL` to the `CREATE TABLE` that is always recreated
  - Checks whether `programs_old` already has `program_type`: if yes → includes the column in `INSERT SELECT` to copy values; if no → omits the column from `INSERT` (default is NULL)

### Files Changed
- `setup.php`
- 🆕 `tests/ProgramTypeTest.php` (35 tests)

---

## [2.4.2] - 2026-03-02

### Changed
- 🗂️ **Admin Programs List: Organizer → Categories** — renamed the "Organizer" column in the Admin Programs table to "Categories" (related artists)
  - Programs list table: header `Organizer` → `Categories`, sort key `organizer` → `categories`, data `event.organizer` → `event.categories`
  - ICS import preview table: header changed from "Organizer" to "Related Artists", data `event.organizer` → `event.categories`
  - The Add/Edit Program form still retains the Organizer field for editing existing data

### Files Changed
- `admin/index.php`

---

## [2.4.1] - 2026-03-02

### Added
- 🖱️ **Clickable Filter Badges** — click any badge in the table to instantly append a filter, without using the filter fields at the top
  - **Related Artists**: categories are split into individual artist badges — click to append an `artist[]` filter
  - **Type**: type badge in the "Type" column — click to append a `type[]` filter
  - `appendFilter(type, value)` JS function: appends a filter to the URL (doesn't remove existing filters), works with or without pre-existing filters, won't add duplicates
- 📋 **Program Type Column** — separates "Type" into its own dedicated column instead of being embedded in the title cell
  - Column is shown when the event has at least 1 program with a defined `program_type` (`$hasTypes = !empty($types)`)
  - Supports 3 languages (`table.type`: Type / Type / タイプ)
  - Badge is clickable → appends filter by type; rows without a type → display `-`

### Changed
- 🏷️ **Event Name Subtitle** — event name is displayed as a separate subtitle below the site title on the schedule page
  - Moved the event name out of `<h1>` (previously "Site Title - Event Name") into a separate `<div class="event-subtitle">`
  - Always shown when viewing any event's schedule — regardless of whether the dropdown selector is displayed
  - Benefit: when only one event exists in the system, the dropdown won't appear, but the event name still shows clearly below the site title

### Documentation
- 📖 **how-to-use.php updated** — added section "5. Quick filter from badges in the table" to the filtering section in all 3 languages (TH/EN/JA)
  - Describes artist badges (pink) and type badges (blue)
  - Explains append filter behavior (does not remove existing filters)

### Fixed
- 🐛 **SyntaxError in badge onclick** — `json_encode()` returned a string containing `"` which prematurely closed the HTML attribute; fixed with `htmlspecialchars(json_encode(...), ENT_QUOTES, 'UTF-8')`

### Files Changed
- `index.php`
- `js/translations.js`
- `how-to-use.php`

## [2.4.0] - 2026-03-02

### Added
- 🏷️ **Program Type System** — type classification system for programs (stage, booth, meet & greet, etc.)
  - `program_type TEXT DEFAULT NULL` column in `programs` table (backward compatible — NULL means no type)
  - Free-text entry: type any program type freely, with autocomplete from existing types in the system
  - **Admin form**: input + datalist in create/edit modal, badge in list view, bulk edit option
  - **Public filter UI**: checkbox group to filter by type (same as artist/venue filter) — shown only when data exists
  - **Program badge**: displays a blue badge above the program name in the main table
  - **Gantt Chart**: shows type label on program bar (small, at the top)
  - **Public API**: `?type=` filter parameter + `action=types` endpoint
  - **ICS Export**: `?type[]=` filter + `program_type` appended to CATEGORIES field
  - Migration script: `tools/migrate-add-program-type-column.php` (idempotent)
  - GitHub Actions: added migration to workflow
- 🏷️ **Program Type in ICS Import** — type can be set in 3 ways (listed in priority order)
  - `X-PROGRAM-TYPE:` field in the VEVENT block (per-event, highest priority)
  - "🏷️ Program Type (default)" field in Admin → Import UI (batch default for web upload)
  - `--type=value` argument when importing via CLI: `php tools/import-ics-to-sqlite.php --event=slug --type=stage`
  - `IcsParser::parseEvent()` now supports the `X-PROGRAM-TYPE:` field

### Fixed
- 🐛 **setup.php `init_database` missing `program_type` column** — `CREATE TABLE programs` in fresh install was missing `program_type TEXT DEFAULT NULL`, causing the status check `$allTablesOk = false` and the bottom button to display incorrectly
  - Added `program_type TEXT DEFAULT NULL` to the `CREATE TABLE` statement in the `init_database` handler

### Documentation
- 📖 **Admin Help Pages updated** (`admin/help.php`, `admin/help-en.php`)
  - Added Program Type field to the Add/Edit Program form table
  - Added X-PROGRAM-TYPE to the Supported ICS Fields table
  - Added section "Setting Program Type on Import" with a table of 3 methods
  - Added FAQ: Setting Program Type when importing ICS
  - Updated Bulk Edit description to include Program Type

### Changed
- ⬆️ **APP_VERSION** → `2.4.0` (cache busting)

### Files Changed
- `index.php`
- `admin/index.php`
- `admin/api.php`
- `api.php`
- `export.php`
- `IcsParser.php`
- `setup.php`
- `config/app.php`
- `.github/workflows/tests.yml`
- 🆕 `tools/migrate-add-program-type-column.php`

## [2.3.4] - 2026-03-02

### Fixed
- 🗓️ **Gantt Chart not showing in Single Venue Mode** — the toggle switch was hidden by `if ($currentVenueMode === 'multi')`, causing the `#viewToggle` element to not exist in the DOM, and `initializeView()` to not run
  - Removed the `venue_mode === 'multi'` condition — toggle switch now shows in all modes
  - Gantt Chart works in single venue mode (displays 1 column)

### Changed
- ⬆️ **APP_VERSION** → `2.3.4` (cache busting)

### Files Changed
- `index.php`

## [2.3.3] - 2026-03-02

### Fixed
- 🗓️ **Gantt Chart: programs 4+ not displaying when overlap exceeds 3** — the CSS class `stack-h-N` was designed for only 2 or 3 overlaps, but JS assigned directly from `stackIndex + 1`, causing program 4 to receive `stack-h-4` (1/3 center) which overlapped invisibly with `stack-h-1` and `stack-h-2`
  - Fixed by switching from CSS classes to inline styles dynamically calculated from `stackIndex / stackTotal`
  - Column space is divided equally among all programs regardless of count (N=4 → 25% each, N=5 → 20% each, …)
  - Removed CSS classes `stack-h-1` through `stack-h-5` (no longer used)

### Changed
- ⬆️ **APP_VERSION** → `2.3.3` (cache busting)

### Files Changed
- `index.php`
- `styles/common.css`

## [2.3.2] - 2026-03-02

### Fixed
- 🕐 **Inconsistent timezone across the system** — no timezone was defined, causing PHP to use the server timezone (Linux/Docker = UTC), resulting in `export.php` converting times incorrectly by ±7 hours
  - Added `date_default_timezone_set('Asia/Bangkok')` in `config/app.php` before all constants
- 🕐 **IcsParser discarding Z suffix** — `DTSTART:20260207T100000Z` (UTC 10:00 = Thailand 17:00) was being stored as `10:00:00` instead of `17:00:00`
  - Fixed `IcsParser::parseDateTime()` to detect the Z suffix and convert UTC → Asia/Bangkok before storing to DB

### Changed
- ⬆️ **APP_VERSION** → `2.3.2` (cache busting)

### Files Changed
- `config/app.php`
- `IcsParser.php`

## [2.3.1] - 2026-03-02

### Fixed
- 🐛 **Bulk Edit Programs not saving to database** — `bulkUpdatePrograms()` in `admin/api.php` mixed named parameters (`:location`, `:updated_at`) with positional `?` in the same WHERE IN clause
  - PDO does not support mixing both types — `execute()` ran successfully but no rows were updated (silent fail)
  - Fixed to use only named parameters: each ID uses `:id_0`, `:id_1`, … instead of `?`

### Changed
- ⬆️ **APP_VERSION** → `2.3.1` (cache busting)

### Files Changed
- `admin/api.php`

## [2.3.0] - 2026-03-02

### Added
- 📧 **Event Email Field** — added `email` column to the `events` table
  - Admin › Events form has a "Contact Email" input field
  - Stored as TEXT DEFAULT NULL; invalid email → stored as NULL (server-side `FILTER_VALIDATE_EMAIL`)
  - Migration script: `tools/migrate-add-event-email-column.php` (idempotent, safe to run multiple times)
- 📅 **ICS ORGANIZER Redesign** — changed the ORGANIZER in ICS export to represent the event/convention instead of the artist
  - `ORGANIZER;CN="Event Name":mailto:email@event.com` — following RFC 5545 semantics
  - Fallback: `noreply@stageidol.local` when no email is set (does not use the artist's email)
- 🧹 **Production Cleanup (Setup Wizard Step 6)** — system for deleting dev/docs files via `setup.php`
  - Check/delete files with grouped checkboxes (Docs, Tests, Tools, Docker, Nginx, CI/CD)
  - Whitelist-based security (prevents path traversal); locked when setup is locked
  - File groups:
    - **Docs**: `README.md`, `QUICKSTART.md`, `INSTALLATION.md`, `DOCKER.md`, `CHANGELOG.md`, `TESTING.md`, `SQLITE_MIGRATION.md`, `SECURITY.md`, `CONTRIBUTING.md`, `SETUP.md`, `API.md`, `PROJECT-STRUCTURE.md`, `LICENSE`
    - **Tests**: `tests/` directory
    - **Tools**: `tools/` directory
    - **Docker**: `Dockerfile`, `docker-compose.yml`, `docker-compose.dev.yml`, `.dockerignore`, `.env.example`
    - **Nginx**: `nginx-clean-url.conf`
    - **CI/CD**: `.github/`, `.gitignore`, `quick-test.bat`, `quick-test.sh`
- 🧪 **EventEmailTest** — 19 automated tests for the email field (637 total in the system)
  - Schema: email column nullable, TEXT type
  - CRUD: insert valid/null email, update email, update to null, read-back via SELECT *
  - Validation logic: accepts valid emails, rejects invalid/empty (returns null)
  - ICS ORGANIZER logic: uses event email, falls back to noreply, skips when no event meta
  - Migration: script exists, idempotent when column already present

### Changed
- ⬆️ **APP_VERSION** → `2.3.0` (cache busting)
- 🔧 **`tools/migrate-add-event-email-column.php`** — the migrated table is `events` (not `programs`)

### Files Changed
- `admin/index.php`
- `admin/api.php`
- `export.php`
- `setup.php`
- `config/app.php`
- 🆕 `tools/migrate-add-event-email-column.php`
- 🆕 `tests/EventEmailTest.php` (19 tests)

## [2.2.1] - 2026-02-28

### Fixed
- 🐛 **setup.php creates programs table with wrong schema** — `CREATE TABLE programs` used `summary TEXT` instead of `title TEXT NOT NULL`, causing Admin › Programs › create new program to fail (`"Failed to create event"`) because the PDOException was hidden by `PRODUCTION_MODE`
  - Fixed `CREATE TABLE programs` to match the actual schema (`title`, `uid NOT NULL`, `start NOT NULL`, `end NOT NULL`, FK `event_id`)
  - Added migration action `fix_programs_title` in `setup.php` for DBs installed with the old setup.php
  - Added Setup Wizard UI button **"Fix programs.title"** (shown when the programs table has `summary` instead of `title`)
  - `$allTablesOk` now also checks `$hasTitleColumn`
- 🐛 **Events listing page shows empty after init database** — `$showEventListing` counted all `$activeEvents` including the default event, triggering the events listing page but skipping the default event in the card loop → empty page
  - Fixed to use `$nonDefaultEvents` (filters out the default slug first) instead of `$activeEvents` in the condition
  - When only the default event exists → fallback to directly displaying calendar view

### Added
- 🌱 **Sample programs seed on Initialize Database** — `setup.php` automatically creates 3 sample programs (Opening Ceremony, Artist Performance, Closing Stage) using today's date as start/end, so the real layout is visible immediately after a fresh install
- 📖 **Admin Help Pages updated: Default Event behavior** (`admin/help.php` + `admin/help-en.php`)
  - Added table "Default Event and Events Listing Page" describing 3 cases (default only / has real events / direct URL access)
  - Added callout explaining that the default event is intentionally hidden from the Events listing page

### Files Changed
- `setup.php`
- `admin/help.php`
- `admin/help-en.php`

## [2.2.0] - 2026-02-27

### Added
- 📝 **Site Title Editable from Admin UI** — admins can change the site title via the Settings tab
  - Constant `APP_NAME` in `config/app.php` serves as the default/fallback
  - Helper `get_site_title()` in `functions/helpers.php` — reads `cache/site-settings.json` → fallback to `APP_NAME`
  - Admin API actions `title_get` / `title_save` + functions `getTitleSetting()` / `saveTitleSetting()`
  - Settings tab UI: input field + Save button (placed before the Site Theme picker)
  - All public pages: `<title>` and `<h1>` use `get_site_title()` dynamically
  - PHP injects `window.SITE_TITLE` before `translations.js` on every public page
  - ICS export: `PRODID`, `X-WR-CALNAME`, `X-WR-CALDESC` use `get_site_title()`
  - Storage: `cache/site-settings.json` — `{"site_title": "...", "updated_at": ...}` (general-purpose settings file)
- 🌐 **JS Translation Patching IIFE** in `js/translations.js`
  - Self-patching IIFE reads `window.SITE_TITLE` and replaces `'Idol Stage Timetable'` in all translation keys
  - Works automatically when the site title changes — supports all 3 languages
- 📖 **Admin Help Pages updated** to support Site Title
  - Added "📝 Site Title" subsection before "🎨 Site Theme" in the Settings section (TH + EN)
  - Updated Roles table: "Settings (Theme)" → "Settings (Title + Theme)"
  - Added FAQ: Site Title not updating after saving
- 🧪 **SiteSettingsTest** — 14 new tests (618 total in the system)
  - Tests `get_site_title()`: no cache, reads cache, empty/whitespace fallback, trim, malformed JSON
  - Tests Admin API: `title_get`/`title_save` cases, functions defined, `require_api_admin_role()` guard
  - Tests public pages: `get_site_title()` call, `window.SITE_TITLE` injection
  - Tests `js/translations.js`: has IIFE patching block
  - Tests `APP_NAME` constant is defined and non-empty

### Changed
- 🌐 **`header.subtitle` EN** changed from `'Idol Stage Timetable'` → `'Event Schedule'`
  - Makes the subtitle descriptive like TH (`'Idol Stage Event Schedule'`) and JA (`'アイドルステージタイムテーブル'`)
  - The brand name remains only in `header.title`

### Files Changed
- `config/app.php`
- `functions/helpers.php`
- `admin/api.php`
- `admin/index.php`
- `js/translations.js`
- `index.php`
- `export.php`
- `credits.php`
- `how-to-use.php`
- `contact.php`
- 🆕 `tests/SiteSettingsTest.php`

## [2.1.1] - 2026-02-27

### Added
- 🎨 **Per-Event Theme** — assign a separate color theme per event
  - `theme TEXT DEFAULT NULL` column in the `events` table
  - `get_site_theme($eventMeta = null)` accepts event meta to resolve the theme by priority:
    1. Event-specific theme (`events.theme`) — if set and valid
    2. Global theme (Settings tab, `cache/site-theme.json`)
    3. Default fallback: `dark`
  - Admin Event form has a theme picker (🌸 Sakura / 🌊 Ocean / 🌿 Forest / 🌙 Midnight / ☀️ Sunset / 🖤 Dark / 🩶 Gray)
  - All public pages pass `$eventMeta` to `get_site_theme()`: `index.php`, `credits.php`, `how-to-use.php`, `contact.php`
  - Migration script: `tools/migrate-add-theme-column.php` (idempotent)
  - Setup wizard support: fresh install creates the `theme` column automatically; existing install has a "+ theme column" button
- 🧪 **ThemeTest added 8 tests** (24 total / 464 in system)
  - Tests priority: event → global → dark fallback
  - Tests null/empty/invalid event theme fallback
  - Tests Admin API supports the theme field

### Changed
- 🎨 **Default theme fallback** changed from `sakura` → `dark`
  - `sakura` is only the base CSS in `common.css` (it has no override file of its own)
  - If no Global theme is set and the Event has no theme → uses `dark` theme

### Files Changed
- `functions/helpers.php`
- `admin/api.php`
- `admin/index.php`
- `index.php`
- `credits.php`
- `how-to-use.php`
- `contact.php`
- `setup.php`
- 🆕 `tools/migrate-add-theme-column.php`

## [2.1.0] - 2026-02-27

### Added
- 🎨 **Theme System** — admin sets a color theme for all public pages
  - Theme CSS files: `ocean.css` 🌊 Blue, `forest.css` 🌿 Green, `midnight.css` 🌙 Purple, `sunset.css` ☀️ Orange, `dark.css` 🖤 Charcoal, `gray.css` 🩶 Silver
  - "⚙️ Settings" tab in Admin panel (admin role only) with theme picker UI
  - Admin API: `theme_get`, `theme_save` actions in `admin/api.php`
  - Helper: `get_site_theme()` in `functions/helpers.php` (reads `cache/site-theme.json` + validates + fallback to sakura)
  - Public pages load theme CSS server-side in `<head>`
- 📖 **Admin Help Pages — fully updated to cover all features** (`admin/help.php` Thai + `admin/help-en.php` English)
  - Added ⚙️ Settings section: describes Site Theme, 7 available themes, steps to change theme
  - Updated overview: 8 tabs (added Settings), tab chips with full emoji icons
  - Updated Roles table: added Settings (Theme) row — admin ✅, agent ❌
  - Added FAQ: Changed theme but page color didn't change
  - TOC (mobile + desktop): added Settings link, renamed "Import ICS" → "Import"

### Changed
- 🎨 **CSS Extracted to External Files** — moved inline `<style>` blocks from PHP files to external CSS files
  - `index.php` → `styles/index.css` (file size reduced from ~90KB → ~43KB)
  - `credits.php` → `styles/credits.css`
  - `how-to-use.php` → `styles/how-to-use.css`
- 🧭 **Admin Nav Icons** — added emoji icons to all tabs in Admin panel (desktop + mobile)
  - 🎵 Programs, 🎪 Events, 📝 Requests, ✨ Credits, 📤 Import, 👤 Users, 💾 Backup, ⚙️ Settings
  - Renamed "Import ICS" → "Import" in nav (section content still describes ICS format)

### Files Changed
- `styles/index.css` *(new)* — extracted from `index.php` inline styles
- `styles/credits.css` *(new)* — extracted from `credits.php` inline styles
- `styles/how-to-use.css` *(new)* — extracted from `how-to-use.php` inline styles
- `styles/ocean.css` *(new)*, `styles/forest.css` *(new)*, `styles/midnight.css` *(new)*, `styles/sunset.css` *(new)*, `styles/dark.css` *(new)*, `styles/gray.css` *(new)* — theme CSS files
- `functions/helpers.php` — `get_site_theme()` helper
- `admin/api.php` — `theme_get` / `theme_save` API actions
- `admin/index.php` — Settings tab with theme picker
- `admin/help.php` — Settings section documentation (Thai)
- `admin/help-en.php` — Settings section documentation (English)
- `index.php`, `credits.php`, `how-to-use.php`, `contact.php` — load theme CSS server-side; inline styles moved to external files

## [2.0.1] - 2026-02-27

### Changed
- ⚙️ **Google Analytics ID configurable** — moved the Measurement ID from being hardcoded in each PHP file to a setting in `config/app.php`
  - Added constant `GOOGLE_ANALYTICS_ID` — set to `''` to disable Analytics
  - Updated `index.php`, `how-to-use.php`, `contact.php`, `credits.php` to use the constant instead of hardcoded values

### Files Changed
- `config/app.php` — `GOOGLE_ANALYTICS_ID` constant
- `index.php`, `how-to-use.php`, `contact.php`, `credits.php` — use constant instead of hardcoded GA ID

## [2.0.0] - 2026-02-27

### ⚠️ Breaking Changes
- 🗄️ **Database Schema Rename** — renamed all tables/columns **(must run migration script)**
  - Table `events` → `programs` (individual shows)
  - Table `events_meta` → `events` (meta events/conventions)
  - Table `event_requests` → `program_requests`
  - Column `programs.event_meta_id` → `programs.event_id` (FK to events)
  - Column `program_requests.event_id` → `program_requests.program_id` (FK to programs)
  - Column `program_requests.event_meta_id` → `program_requests.event_id` (FK to events)
  - Column `credits.event_meta_id` → `credits.event_id` (FK to events)
  - Migration script: `tools/migrate-rename-tables-columns.php` (idempotent)
- 🔌 **API Action Names Renamed**
  - Public API: `action=events` → `action=programs`
  - Admin API Programs: `list`→`programs_list`, `get`→`programs_get`, `create`→`programs_create`, `update`→`programs_update`, `delete`→`programs_delete`, `venues`→`programs_venues`, `bulk_delete`→`programs_bulk_delete`, `bulk_update`→`programs_bulk_update`
  - Admin API Events: `event_meta_list`→`events_list`, `event_meta_get`→`events_get`, `event_meta_create`→`events_create`, `event_meta_update`→`events_update`, `event_meta_delete`→`events_delete`
  - Request API: `action=events` → `action=programs`
- 🏷️ **Terminology Rename** — renamed terminology throughout the system
  - "Events" (individual shows) → **"Programs"**
  - "Conventions" → **"Events"**

### Added
- 🛠️ **Setup Wizard** (`setup.php`) — interactive system installer for fresh install and maintenance
  - 5 steps: System Requirements → Directories → Database → Import Data → Admin & Security
  - Auto-login after Initialize Database, inline password change, default credentials box
  - Lock/Unlock mechanism (`data/.setup_locked`), Auth gate (no login required for fresh install)
- 📖 **Admin Help Pages** — Admin Panel user guide
  - `admin/help.php` (Thai) + `admin/help-en.php` (English) with language switcher
  - Covers: Overview, Login, Header, Programs, Events, Requests, Credits, Import ICS, Users, Backup, Roles & Permissions, Tips & FAQ
  - "📖 Help" button in Admin header
- ⚡ **Database Indexes** (`tools/migrate-add-indexes.php`) — 7 indexes for 2-5x speed improvement
  - `idx_programs_event_id`, `idx_programs_start`, `idx_programs_location`, `idx_programs_categories` on `programs` table
  - `idx_program_requests_status`, `idx_program_requests_event_id` on `program_requests` table
  - `idx_credits_event_id` on `credits` table
  - Migration script is idempotent (`CREATE INDEX IF NOT EXISTS`)
- 🚦 **Login Rate Limiting** — limits login to no more than 5 attempts/15 minutes/IP
  - Functions: `check_login_rate_limit()`, `record_failed_login()`, `clear_login_attempts()`
  - Stores data in `cache/login_attempts.json`, displays remaining wait time
- 🔑 **`get_db()` Singleton** (`functions/helpers.php`) — PDO singleton for web context (1 connection/request)
- `tools/migrate-rename-tables-columns.php` — Migration script (idempotent) for existing databases

### Changed
- 📱 **Admin UI Mobile Responsive** — full mobile support (iOS + Android)
  - iOS Auto-Zoom Fix: date input `font-size: 0.9rem → 1rem` (prevents iOS zoom when focused)
  - Touch Targets: modal-close button `32×32px → 44×44px`, checkboxes `18px → 20px`, btn-sm `min-height: 40px`
  - Hamburger Tab Menu: dropdown navigation on mobile (≤600px) with badge + active state
  - Table Scroll Fix: wrapper div pattern (`<div class="table-scroll-wrapper">`) prevents iOS scroll capture
  - 3 Breakpoints: 768px (tablet), 600px (small phone), 480px (very small phone)
  - Help page TOC mobile: Sidebar hidden on mobile, uses collapsible dropdown instead
- 🌐 **HTTP Cache Headers** (`api.php`) — ETag + Cache-Control + 304 Not Modified
  - Programs/organizers/locations: max-age=300 (5 minutes), events_list: max-age=600 (10 minutes)
- ⚡ **Pre-computed Timestamps** (`index.php`) — `start_ts`/`end_ts` calculated once per record
  - Reduces repeated `strtotime()` calls in loops from 6 locations → calculated once per record
- 🌐 **Translation Updates** (`js/translations.js`) — updated for 3 languages (TH/EN/JA)
  - Key renames: `message.noEvents`→`message.noPrograms`, `table.event`→`table.program`, `gantt.noEvents`→`gantt.noPrograms`, `modal.eventName`→`modal.programName`
- 🎨 **CSS Class Renames** — `.event-*`→`.program-*`, `.gantt-event-*`→`.gantt-program-*`
- 🔧 **PHP Backend Function Renames**
  - `admin/api.php`: `listEvents()`→`listPrograms()`, `getEvent()`→`getProgram()`, `createEvent()`→`createProgram()`, `updateEvent()`→`updateProgram()`, `deleteEvent()`→`deleteProgram()`, `bulkDeleteEvents()`→`bulkDeletePrograms()`, `bulkUpdateEvents()`→`bulkUpdatePrograms()`
  - `admin/api.php`: `listEventMeta()`→`listEvents()`, `getEventMeta()`→`getEvent()`, `createEventMeta()`→`createEvent()`, `updateEventMeta()`→`updateEvent()`, `deleteEventMeta()`→`deleteEvent()`
  - `functions/helpers.php`: `get_event_meta_by_slug()`→`get_event_by_slug()`, `get_event_meta_id()`→`get_event_id()`
- ⚙️ **Admin Panel Tab Renames**: "Events"→"Programs", "🏟️ Conventions"→"🏟️ Events"
- `config/app.php`: APP_VERSION → '2.0.0'

### Documentation
- 🔌 **[API.md](API.md)** — complete API endpoint documentation (Public / Request / Admin APIs) with request/response examples
- 📁 **[PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md)** — file structure + function list + config constants + file relationships
- 📖 **[SETUP.md](SETUP.md)** — comprehensive Setup Wizard user guide
- Updated README, QUICKSTART, INSTALLATION, SQLITE_MIGRATION, TESTING to match the new schema

### Migration Guide (from v1.2.5)
```bash
# 1. Run schema migration (Breaking change — must do this first)
php tools/migrate-rename-tables-columns.php

# 2. Add database indexes (performance)
php tools/migrate-add-indexes.php
```

### Testing
- 🧪 **324 automated tests** — all passing (PHP 8.1, 8.2, 8.3)

### Files Changed
- `setup.php` *(new)* — Setup Wizard (5 steps: requirements, directories, database, import, security)
- `admin/help.php` *(new)* — Admin panel user guide (Thai)
- `admin/help-en.php` *(new)* — Admin panel user guide (English)
- `tools/migrate-rename-tables-columns.php` *(new)* — DB schema rename migration
- `tools/migrate-add-indexes.php` *(new)* — DB performance indexes
- `functions/helpers.php` — `get_db()` singleton, renamed helper functions
- `functions/admin.php` — updated for renamed tables
- `admin/api.php` — all renamed functions, new endpoints, mobile responsive
- `admin/index.php` — renamed tabs, mobile responsive layout
- `admin/login.php` — login rate limiting
- `index.php` — pre-computed timestamps, CSS class renames
- `api.php` — ETag + Cache-Control headers
- `js/translations.js` — renamed translation keys
- `config/app.php` — version bump

## [1.2.5] - 2026-02-18

### Added

- 👤 **User Management System** — manage admin users through the Admin panel
  - "👤 Users" tab in Admin panel (shown only for admin role)
  - User table: ID, Username, Display Name, Role, Active, Last Login, Actions
  - Create new user: username, password (min 8 chars), display_name, role, is_active
  - Edit user: password optional, username cannot be changed
  - Delete user: cannot delete self, must keep at least 1 admin
  - API endpoints: `users_list`, `users_get`, `users_create`, `users_update`, `users_delete`

- 🛡️ **Role-Based Access Control** — role-based permission system
  - 2 roles: `admin` (full access) and `agent` (events management only)
  - `admin` role: access all tabs + manage users + backup/restore
  - `agent` role: access only Events, Requests, Import ICS, Credits, Conventions
  - Defense in depth: PHP hides HTML elements + API-level role checks
  - Prevents lockout: cannot delete self, cannot change own role, cannot deactivate self
  - Must always have at least 1 active admin
  - Config fallback users always have admin role (backward compatible)
  - Role badge shown next to username in header
  - Helper functions: `get_admin_role()`, `is_admin_role()`, `require_admin_role()`, `require_api_admin_role()`
  - Migration script: `tools/migrate-add-role-column.php`

### Changed
- `functions/admin.php`: added `$_SESSION['admin_role']` in `admin_login()` + 4 role helper functions
- `admin/api.php`: added admin-only action gate for backup/users actions + 5 user CRUD endpoints
- `admin/index.php`: added Users tab/modal + hides Users/Backup tabs from agent role
- `config/app.php`: APP_VERSION → '1.2.5'

### Testing
- 🧪 **226 automated tests** (up from 207) — added 19 tests in `UserManagementTest.php`
  - Schema tests: role column, default values
  - Role helper tests: `get_admin_role()`, `is_admin_role()`
  - User CRUD tests: create, update, delete, validation
  - Permission tests: admin-only actions, agent restrictions

### Files Changed
- `tools/migrate-add-role-column.php` *(new)* — adds `role` column to `admin_users` table
- `tests/UserManagementTest.php` *(new)* — 19 automated tests
- `functions/admin.php` — `$_SESSION['admin_role']` + 4 role helper functions
- `admin/api.php` — admin-only action gate + 5 user CRUD endpoints
- `admin/index.php` — Users tab/modal, hide from agent role
- `config/app.php` — version bump

## [1.2.4] - 2026-02-17

### Added

- 🔐 **Database-based Admin Authentication** — moved credentials from config to SQLite
  - `admin_users` table supports multiple admin users (username, password_hash, display_name, is_active)
  - Login tries DB first → fallback to config constants (backward compatible)
  - Records `last_login_at` on every successful login
  - Dummy `password_verify` when username not found to prevent timing attacks
  - Migration script: `tools/migrate-add-admin-users-table.php`

- 🔑 **Change Password UI** — change password via Admin panel
  - "🔑 Change Password" button in Admin header (shown only for DB users)
  - Modal form: current password + new password + confirm password
  - Validation: must enter current password, new password minimum 8 characters
  - API endpoint: `POST ?action=change_password`

### Fixed
- 🐛 **Backup Delete Fix** — fixed issue where deleting a backup file showed "Invalid filename"
  - Changed HTTP method from DELETE to POST (Apache/Windows don't send body in DELETE request)
  - Fixed JS variable scope bug: `closeDeleteBackupModal()` was clearing the filename variable before `fetch` could use it
  - Saves filename as a local variable before closing the modal

### Changed
- `functions/admin.php`: added 4 functions (`admin_users_table_exists`, `get_admin_user_by_username`, `update_admin_last_login`, `change_admin_password`) + fixed `admin_login()` to read from DB first
- `config/admin.php`: `ADMIN_USERNAME` / `ADMIN_PASSWORD_HASH` are now fallback (deprecation comment)
- `tools/generate-password-hash.php`: recommends 3 methods to change password (Admin UI, config, SQL)
- `admin/api.php`: changed backup delete from DELETE to POST method
- Added 6 new tests (207 total from 189)

### Files Changed
- `tools/migrate-add-admin-users-table.php` *(new)* — creates `admin_users` table
- `functions/admin.php` — DB auth functions, `admin_login()` reads from DB first
- `config/admin.php` — config credentials become fallback only
- `admin/api.php` — `change_password` endpoint, backup delete → POST
- `admin/index.php` — Change Password button + modal in header
- `tools/generate-password-hash.php` — updated recommendations

## [1.2.3] - 2026-02-17

### Added

- 💾 **Backup/Restore System** — manage database backups through Admin UI
  - **Backup Tab**: new "💾 Backup" tab in Admin panel
  - **Create Backup**: creates a .db backup file and saves it on the server in `backups/`
  - **Download Backup**: downloads backup file to local machine
  - **Restore from Server**: choose to restore from a backup file stored on the server
  - **Upload & Restore**: upload a .db file from local machine to restore
  - **Delete Backup**: delete unwanted backup files
  - **Auto-Backup Safety**: automatically creates an auto-backup before every restore
  - **SQLite Validation**: verifies the SQLite header before restore
  - **Path Traversal Protection**: prevents path traversal attacks in filename

- 📂 **Database Directory Restructure** — reorganized directory structure
  - **`data/`**: moved `calendar.db` to `data/calendar.db`
  - **`backups/`**: stores backup files separately in `backups/` directory
  - **DB_PATH Constant**: uses `DB_PATH` constant instead of hardcoded path throughout the system
  - **Docker Updated**: updated docker-compose.yml to mount volume as `data/`

### Changed
- `config/database.php`: DB_PATH points to `data/calendar.db`
- `admin/api.php`: uses `DB_PATH` constant, backup dir changed to `backups/`
- `functions/cache.php`: added `invalidate_all_caches()` for restore
- Updated migration tools, tests, Docker files to use the new path

### Files Changed
- `config/database.php` — `DB_PATH` constant pointing to `data/calendar.db`
- `admin/api.php` — uses `DB_PATH`, backup dir → `backups/`
- `admin/index.php` — Backup tab with full backup/restore UI
- `functions/cache.php` — `invalidate_all_caches()` function
- `docker-compose.yml` — volume mount updated to `data/`
- `tools/` — updated all migration tools to use new paths
- `tests/` — updated tests to use new DB path

## [1.2.1] - 2026-02-12

### Added

- 🔗 **Clean URL Rewrite** - Remove `.php` extension from all public URLs
  - **`.htaccess`**: Apache rewrite rules for clean URLs and event path routing
  - **`nginx-clean-url.conf`**: Nginx configuration example for clean URLs
  - **Event Path Routing**: `/event/slug` → `index.php?event=slug`, `/event/slug/credits` → `credits.php?event=slug`
  - **Backward Compatible**: Old `.php` URLs still work
  - **Admin URLs unchanged**: `/admin/` paths remain as-is
  - **Updated `event_url()`**: Generates clean URLs (`/credits` instead of `/credits.php`)

- 📅 **Date Jump Bar** - Quick navigation between days in multi-day events
  - Fixed-position bar appears when scrolling past the calendar area
  - Shows day/month and weekday name for each date
  - Smooth scroll with offset for fixed bar height
  - IntersectionObserver highlights current visible date
  - Responsive design for mobile
  - Translatable label in all 3 languages

- 📦 **ICS Import Event Selector** - Choose target convention when importing ICS files
  - Dedicated dropdown in ICS upload area to select target convention
  - Convention name badge shown in preview stats

- 📋 **Admin Credits Per-Event** - Assign credits to specific conventions
  - Convention selector dropdown in credit create/edit form
  - Supports global credits (null = shown in all conventions)

- 🌏 **Complete i18n for Request Modal** - All form elements fully translated
  - 20 new translation keys for request modal (labels, buttons, messages) in TH/EN/JA
  - `data-i18n` attributes on all form labels and buttons
  - JavaScript alert/confirm messages use translation system
  - Added missing `credits.list.title` and `credits.noData` keys

### Changed
- Updated `event_url()` to generate clean event paths (`/event/slug/page`)
- Updated `exportToIcs()` to use clean URL paths
- Updated inline JS API calls to use clean URLs (`api/request` instead of `api/request.php`)

### Testing
- 🧪 **189 automated tests** (up from 187) - Added clean URL routing tests

### Files Changed
- `.htaccess` — Apache rewrite rules for clean URLs and event path routing
- `nginx-clean-url.conf` *(new)* — Nginx configuration example
- `functions/helpers.php` — `event_url()` generates clean URLs
- `admin/api.php` — credits per-event support
- `admin/index.php` — credits convention selector, date jump bar UI
- `index.php` — date jump bar, clean URL generation
- `js/common.js` — `exportToIcs()` uses clean URL paths
- `js/translations.js` — 20 new request modal keys

## [1.2.0] - 2026-02-11

### Added

- 🎪 **Multi-Event (Conventions) Support** - Manage multiple events/conventions in one system
  - **New Table**: `events_meta` for storing convention metadata (name, slug, dates, venue_mode, is_active)
  - **Convention Management**: Full CRUD for conventions via new "Conventions" tab in admin panel
  - **Event Scoping**: Each event, request, and credit can belong to a specific convention
  - **URL-based Selection**: Access conventions via `?event=slug` URL parameter
  - **Convention Selector**: Dropdown in header to switch between conventions (public + admin)
  - **Per-Convention Venue Mode**: Each convention can have its own `multi` or `single` venue mode
  - **Backward Compatible**: Existing data works without migration (null event_meta_id = global)
  - **Feature Flag**: `MULTI_EVENT_MODE` constant to enable/disable multi-event features
  - **Migration Script**: `tools/migrate-add-events-meta-table.php` creates tables and migrates existing data
  - **New Config Constants**: `DEFAULT_EVENT_SLUG`, `MULTI_EVENT_MODE` in `config/app.php`
  - **New Helper Functions**: `get_current_event_slug()`, `get_event_meta_by_slug()`, `get_event_meta_id()`, `get_all_active_events()`, `get_event_venue_mode()`, `event_url()`
  - **Admin API Endpoints**: `event_meta_list`, `event_meta_get`, `event_meta_create`, `event_meta_update`, `event_meta_delete`
  - **Public API**: New `events_list` action returns all active conventions; all actions support `?event=slug` filtering
  - **ICS Import**: `--event=slug` argument for CLI import tool
  - **Cache Scoping**: Data version and credits cache scoped per convention
  - **15 New Tests**: Multi-event helper functions, IcsParser filtering, cache scoping (total: 187 tests)

### Files Changed
- `tools/migrate-add-events-meta-table.php` *(new)* — creates `events_meta` table
- `config/app.php` — `MULTI_EVENT_MODE`, `DEFAULT_EVENT_SLUG` constants
- `functions/helpers.php` — 6 new multi-event helper functions
- `admin/api.php` — 5 new `event_meta_*` endpoints
- `admin/index.php` — Conventions tab with CRUD
- `index.php` — convention selector, per-convention filtering
- `export.php`, `credits.php`, `api.php`, `api/request.php` — convention scoping
- `IcsParser.php` — `--event=slug` argument support
- `tools/import-ics-to-sqlite.php` — `--event=slug` CLI argument
- `tests/IntegrationTest.php` — 15 new multi-event tests

## [1.1.0] - 2026-02-11

### Added

- 🐳 **Docker Support** - One-command deployment with Docker Compose
  - **Dockerfile**: PHP 8.1-apache with PDO SQLite, auto-creates directories and imports data
  - **docker-compose.yml**: Production setup with port 8000, volume mounts (ics, cache, database)
  - **docker-compose.dev.yml**: Development mode with live reload and error display
  - **.dockerignore**: Optimized build exclusions for smaller image size
  - **Health Check**: Built-in container health monitoring
  - **Auto-Setup**: Automatically creates tables and imports ICS files on first run
  - **DOCKER.md**: Comprehensive Docker deployment guide (Quick Start, Production, Development, Advanced)

- 📋 **Credits Management System** - Complete CRUD system for managing credits and references
  - **Database Table**: SQLite table with fields: id, title, link, description, display_order, created_at, updated_at
  - **Admin UI**: New "Credits" tab in admin panel with full management interface
    - Create, Read, Update, Delete operations
    - Search functionality with 300ms debounce
    - Sortable columns (ID, Title, Display Order)
    - Pagination with 20/50/100 per page options
    - Bulk selection with master checkbox
    - Bulk delete up to 100 credits at once
  - **Admin API**: 6 RESTful endpoints
    - `credits_list` - List with pagination, search, sorting
    - `credits_get` - Get single credit
    - `credits_create` - Create new credit
    - `credits_update` - Update existing credit
    - `credits_delete` - Delete single credit
    - `credits_bulk_delete` - Bulk delete with transaction support
  - **Public Display**: credits.php now loads from database instead of hardcoded HTML
  - **Validation**: Title required (max 200 chars), description optional (max 1000 chars)
  - **Migration Script**: `tools/migrate-add-credits-table.php` for database setup

- 🔄 **Cache System for Credits** - Performance optimization for credits page
  - **Cache Function**: `get_cached_credits()` in `functions/cache.php`
  - **TTL**: 1 hour (3600 seconds) configurable via `CREDITS_CACHE_TTL`
  - **Cache File**: `cache/credits.json` with timestamp and data
  - **Auto-Invalidation**: Cache automatically cleared on create/update/delete operations
  - **Fallback**: Returns empty array on cache miss or database error
  - **Performance**: Reduces database queries for frequently accessed credits data
  - **Configuration**: Settings in `config/cache.php`

- 📦 **Bulk Operations** - Admin can now manage multiple events simultaneously
  - Checkbox selection with master checkbox (select all/deselect all)
  - Bulk Delete - Delete up to 100 events at once with confirmation
  - Bulk Edit - Update venue, organizer, and categories for multiple events
  - Selection count display in bulk actions toolbar
  - Transaction handling with partial failure support
  - Visual feedback with selected row highlighting
  - Indeterminate checkbox state for partial selections

- 🎯 **Flexible Venue Entry** - Add new venues without limitations
  - Changed from `<select>` dropdown to `<input>` with `<datalist>`
  - Autocomplete suggestions from existing venues
  - Ability to type new venue names on-the-fly
  - Applies to both single event form and bulk edit modal

- 📤 **ICS Upload & Import** - Upload ICS files directly through Admin UI
  - File upload with validation (max 5MB, .ics files only)
  - MIME type checking (text/calendar, text/plain, application/octet-stream)
  - Preview parsed events before importing
  - Duplicate detection (checks against existing UIDs in database)
  - Per-event action: insert, update, or skip
  - Option to save uploaded file to `ics/` folder
  - Import statistics (inserted, updated, skipped, errors)

- 📊 **Per-Page Selector** - Customize events displayed per page
  - Options: 20, 50, or 100 events per page
  - Auto-reset to page 1 when changing page size
  - Works seamlessly with filters, search, and sorting
  - Dropdown integrated in admin toolbar

- 🎨 **Admin UI Improvements**
  - Professional Blue/Gray color scheme (distinct from user-facing Sakura theme)
  - Enhanced gradient header with icon
  - Card-style tab navigation
  - Improved contrast and readability
  - Fixed username and table header visibility issues

### Changed
- **Cache Configuration** (`config/cache.php`)
  - Added `CREDITS_CACHE_FILE` and `CREDITS_CACHE_TTL` constants
  - Organized cache settings for both data version and credits

- **Cache Functions** (`functions/cache.php`)
  - Added `get_cached_credits()` - Fetch credits with caching
  - Added `invalidate_credits_cache()` - Clear cache after modifications
  - Maintained existing `get_data_version()` function

- **Credits Display** (`credits.php`)
  - Replaced hardcoded HTML with database-driven dynamic content
  - Uses `get_cached_credits()` for optimized loading
  - Proper HTML escaping with `htmlspecialchars()`
  - Support for optional fields (link, description)
  - Empty state handling when no credits exist

- **Admin API** (`admin/api.php`)
  - Added 6 new switch cases for credits operations
  - Cache invalidation after all state-changing operations
  - Consistent error handling and JSON responses

- **Bulk Edit API** (`admin/api.php`)
  - Added support for categories field alongside venue and organizer
  - Validation ensures at least one field is provided
  - Dynamic UPDATE query construction based on provided fields
  - Maximum 100 events per bulk operation for performance

- **Admin Event List** (`admin/index.php`)
  - Added checkbox column to events table
  - Enhanced toolbar with bulk actions bar (shows when events selected)
  - Improved state management with `perPage` variable
  - Better pagination with customizable limits

### Security
- 🔒 **Enhanced Input Sanitization** - Comprehensive protection against XSS and injection attacks
  - **New Functions** in `functions/security.php`:
    - `sanitize_string()` - Remove null bytes, trim, length limits
    - `sanitize_string_array()` - Sanitize array inputs with max items limit
    - `get_sanitized_param()` - Safe GET parameter extraction (string)
    - `get_sanitized_array_param()` - Safe GET parameter extraction (array)
  - **Applied to**: `index.php`, `export.php`, `admin/api.php`
  - **Parameters sanitized**: artist, venue, search, date filters
  - **Protection**: Max length validation, null byte removal, array size limits

- 🛡️ **Session Security Improvements** - Complete rewrite of session management (`functions/admin.php`)
  - **Timing Attack Prevention**: Use `hash_equals()` for username comparison (constant-time)
  - **Session Fixation Prevention**: `session_regenerate_id()` before login and logout
  - **Session Timeout**: Automatic logout after 2 hours of inactivity (configurable)
  - **Secure Cookies**: httponly, secure (HTTPS), SameSite=Strict attributes
  - **Session Validation**: Check timeout on every request
  - **New constant**: `SESSION_TIMEOUT` in `config/admin.php` (default: 7200 seconds)

- 🔐 **JSON Security** - Safe JSON encoding in HTML attributes
  - **Changed**: `htmlspecialchars(json_encode())` → `json_encode()` with security flags
  - **Flags used**: `JSON_HEX_QUOT`, `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`
  - **Benefit**: No JSON structure corruption, safe in HTML attributes
  - **Applied to**: `index.php` request modal data attributes

- **Credits System Security**
  - All credits API endpoints protected by authentication (`require_api_login()`)
  - CSRF token validation for create/update/delete operations
  - SQL injection prevention via PDO prepared statements
  - XSS prevention via `htmlspecialchars()` on output
  - Input validation (required fields, length limits)
  - Rate limiting inherited from admin panel
  - Transaction rollback on bulk operation failures

- **General Security**
  - All bulk operations protected by CSRF tokens
  - Input validation for bulk IDs (max 100, integer sanitization)
  - Transaction rollback on errors
  - Prepared statements for all database operations
  - Safe session handling with race condition prevention

### Testing
- 🧪 **Automated Test Suite** - 187 comprehensive unit tests
  - **Test Framework**: Custom lightweight TestRunner with 20 assertion methods
  - **SecurityTest** (15 tests): Input sanitization, XSS protection, null byte injection, SQL injection prevention
  - **CacheTest** (11 tests): Cache creation, TTL, invalidation, hit/miss, error fallback
  - **AdminAuthTest** (15 tests): Session management, login/logout, timing attack resistance, password verification
  - **CreditsApiTest** (13 tests): Database CRUD, bulk operations, SQL injection protection, display order sorting
  - **IntegrationTest** (118 tests): File structure validation, configuration checks, full workflow simulation, API endpoints
  - **Test Runner**: `tests/run-tests.php` with colored output, test filtering, detailed statistics
  - **Quick Tests**: `quick-test.sh` (Linux/Mac) and `quick-test.bat` (Windows) for pre-commit testing
  - **CI/CD**: GitHub Actions workflow (`.github/workflows/tests.yml`) for automated testing on push/PR
    - Matrix testing across **PHP 8.1, 8.2, and 8.3** (all tests pass)
    - Separate jobs for security and integration tests
    - Automatic test result artifact upload on failure
  - **Documentation**:
    - `tests/README.md` - Automated testing guide (usage, assertions, writing tests, troubleshooting)
    - `TESTING.md` - Manual testing checklist with 129 test cases

### Documentation
- Updated README.md with cache system and testing information
- Updated QUICKSTART.md with testing section and quick test commands
- Updated INSTALLATION.md with testing & QA procedures, pre-production checklist
- Added credits migration script to tools documentation
- Updated file structure diagrams to include cache/ and tests/ directories
- Added TESTING.md with 129 manual test cases covering all features
- Added tests/README.md with comprehensive automated testing guide

### Files Changed
- `Dockerfile` *(new)*, `docker-compose.yml` *(new)*, `docker-compose.dev.yml` *(new)*, `.dockerignore` *(new)*, `DOCKER.md` *(new)* — Docker support
- `tools/migrate-add-credits-table.php` *(new)* — creates `credits` table
- `tests/run-tests.php` *(new)*, `tests/TestRunner.php` *(new)*, `tests/SecurityTest.php` *(new)*, `tests/CacheTest.php` *(new)*, `tests/AdminAuthTest.php` *(new)*, `tests/CreditsApiTest.php` *(new)*, `tests/IntegrationTest.php` *(new)* — automated test suite
- `quick-test.sh` *(new)*, `quick-test.bat` *(new)* — pre-commit test scripts
- `.github/workflows/tests.yml` *(new)* — CI/CD GitHub Actions
- `functions/security.php` — `sanitize_string()`, `sanitize_string_array()`, `get_sanitized_param()`
- `functions/admin.php` — session security rewrite (timing attack prevention, session fixation, timeout)
- `functions/cache.php` — `get_cached_credits()`, `invalidate_credits_cache()`
- `config/cache.php` — `CREDITS_CACHE_FILE`, `CREDITS_CACHE_TTL` constants
- `config/admin.php` — `SESSION_TIMEOUT` constant
- `admin/api.php` — 6 credits CRUD endpoints, bulk operations, CSRF protection
- `admin/index.php` — Credits tab, bulk delete/edit, ICS upload, per-page selector
- `credits.php` — DB-driven from `get_cached_credits()`
- `index.php`, `export.php` — input sanitization

## [1.0.0] - 2026-02-09

### Added
- 🌸 **Sakura Theme** - Beautiful cherry blossom theme with Japanese aesthetics
- 🌏 **Multi-language Support** - Thai, English, and Japanese (日本語) with proper html lang attributes
- 📱 **Responsive Design** - Full support for all screen sizes including iOS devices
- 📊 **Dual View Modes**
  - List View: Traditional table layout with full details
  - Gantt Chart View: Horizontal timeline showing event overlaps across venues
- 🔍 **Advanced Filtering**
  - Search by artist/performer name (with auto-select and clear button)
  - Filter by multiple artists
  - Filter by multiple venues
  - Selected tags display with one-click removal
- 📸 **Image Export** - Save calendar as PNG image (lazy-loaded html2canvas)
- 📅 **ICS Export** - Export filtered events to calendar apps (Google Calendar, Apple Calendar, etc.)
- 📝 **User Request System**
  - Users can request to add new events
  - Users can request to modify existing events
  - Rate limiting (10 requests per hour per IP)
  - Request form with pre-filled data for modifications
- ⚙️ **Admin Panel**
  - Full CRUD operations for events
  - Request management (approve/reject user requests)
  - Side-by-side comparison view for modification requests
  - Highlight changed fields (yellow) and new fields (green)
  - Search and filter by venue
  - Pagination support
  - Session-based authentication
  - Optional IP whitelist
- ⚡ **SQLite Database Support**
  - 10-20x faster than parsing ICS files
  - Efficient querying and filtering
  - Auto-generated unique IDs
  - Timestamps for created_at and updated_at
- 🔄 **Cache Busting** - Version-based cache control for CSS/JS files
- 🔒 **Security Features**
  - XSS Protection (server-side and client-side)
  - CSRF token validation
  - Security headers (CSP, X-Content-Type-Options, X-Frame-Options, etc.)
  - Rate limiting for API requests
  - Input validation and sanitization
  - Prepared statements (SQL injection protection)
- 🗂️ **ICS File Support** - Parse and display events from multiple ICS files
- 🌊 **iOS Scroll Indicators** - Gradient shadows on timeline for better UX on iOS
- 📊 **Auto Data Version** - Displays last update time from database

### Fixed
- **Critical Password Hash Bug** - Fixed admin login system that was broken due to password hash regenerating on every page load
  - Changed from dynamic `password_hash()` call to static hash constant
  - Added clear instructions in SECURITY.md for generating password hash
  - Prevents login failures caused by changing hash values
- **Missing `is_logged_in()` function** - Restored function that was accidentally omitted during config reorganization
- iOS Timeline Header Bug - Fixed venue headers not showing for 5+ venues on iOS Safari
  - Added explicit min-width to prevent compositing issues
  - Moved horizontal scroll to parent container
  - Fixed header/body desync on iOS
- Events sorting - Events now properly sorted by time after admin approval
- Modal overflow - Modals now scrollable on small screens
- PHP 7.0 compatibility - Replaced arrow functions with anonymous functions
- Navigation buttons i18n - All nav buttons now properly change language
- IcsParser - Now returns event `id` field for proper tracking

### Changed
- **Reorganized configuration system** for better maintainability:
  - Split monolithic `config.php` into modular structure
  - Created `config/` folder with categorized configuration files:
    - `config/app.php` - Application settings (version, production mode)
    - `config/admin.php` - Authentication & admin settings
    - `config/security.php` - Security & rate limiting
    - `config/database.php` - Database configuration
    - `config/cache.php` - Cache settings
  - Created `functions/` folder with categorized helper functions:
    - `functions/helpers.php` - General utilities
    - `functions/cache.php` - Cache-related functions
    - `functions/admin.php` - Authentication functions
    - `functions/security.php` - Security functions
  - Root `config.php` now acts as bootstrap file loading all configs
- **Reorganized file structure** with dedicated folders:
  - `styles/` for shared CSS
  - `js/` for shared JavaScript
  - `tools/` for development utilities
  - `admin/` for admin interface
  - `api/` for public APIs
- Removed redundant "Back" buttons
- Improved filter UX with selected tags display
- Enhanced admin comparison view for better change visibility

### Documentation
- README.md - Comprehensive feature documentation (updated for new config structure)
- INSTALLATION.md - Detailed installation guide with multiple deployment options
- QUICKSTART.md - 3-step quick start guide
- SQLITE_MIGRATION.md - Database migration and performance guide
- CHANGELOG.md - Version history (this file)
- LICENSE - MIT License
- CONTRIBUTING.md - Contribution guidelines
- SECURITY.md - Security policy and deployment best practices

### Files Changed
- `index.php` — main calendar page (initial version)
- `how-to-use.php` — user guide page
- `contact.php` — contact page
- `credits.php` — credits/references page
- `export.php` — ICS export endpoint
- `api.php` — public API endpoint
- `config.php` — bootstrap file
- `IcsParser.php` — ICS parser class
- `config/app.php`, `config/admin.php`, `config/security.php`, `config/database.php`, `config/cache.php` *(new)* — modular config files
- `functions/helpers.php`, `functions/cache.php`, `functions/admin.php`, `functions/security.php` *(new)* — helper function modules
- `admin/index.php`, `admin/api.php`, `admin/login.php` — admin interface
- `api/request.php` — user request API
- `styles/common.css` — shared Sakura theme styles
- `js/translations.js` — 3-language translations
- `js/common.js` — shared JS utilities
- `.htaccess` — rewrite rules
- `tools/import-ics-to-sqlite.php`, `tools/update-ics-categories.php`, `tools/migrate-add-requests-table.php`, `tools/migrate-add-credits-table.php` — development tools
