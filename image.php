<?php
/**
 * Server-side PNG schedule image generator (PHP GD)
 * GET /image?event=slug&artist[]=X&venue[]=Y&type[]=Z&q=search&lang=th|en|ja
 */
require_once 'config.php';
send_security_headers();

// ── GD availability ────────────────────────────────────────────────────────
if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PHP GD extension is not available.';
    exit;
}

// ── Font detection ─────────────────────────────────────────────────────────
$fontDir = __DIR__ . '/fonts/';
$fontCandidates = [
    // fonts/ directory (highest priority — place preferred Thai font here)
    // NOTE: Symbola.ttf is for emoji fallback only — it has NO Thai glyphs
    $fontDir . 'Sarabun-Regular.ttf',
    $fontDir . 'NotoSansThai-Regular.ttf',
    $fontDir . 'Prompt-Regular.ttf',
    $fontDir . 'Kanit-Regular.ttf',
    // Windows — Thai-capable fonts (Leelawadee UI has full Thai coverage)
    'C:/Windows/Fonts/LeelawUI.ttf',        // Leelawadee UI (Win10+)
    'C:/Windows/Fonts/leelawad.ttf',        // Leelawadee (older Win)
    'C:/Windows/Fonts/LeelUIsl.ttf',        // Leelawadee UI Semilight
    'C:/Windows/Fonts/cordia.ttf',          // Cordia New (Thai)
    'C:/Windows/Fonts/tahoma.ttf',
    'C:/Windows/Fonts/segoeui.ttf',
    'C:/Windows/Fonts/arial.ttf',
    // Linux / Docker — NotoSansThai (fonts-noto or fonts-noto-cjk package)
    '/usr/share/fonts/truetype/noto/NotoSansThai-Regular.ttf',
    '/usr/share/fonts/opentype/noto/NotoSansThai-Regular.ttf',
    '/usr/share/fonts/noto/NotoSansThai-Regular.ttf',
    // Linux — NotoSansCJK (fonts-noto-cjk package) — has Thai coverage
    '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
    '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
    '/usr/share/fonts/noto-cjk/NotoSansCJK-Regular.ttc',
    // Linux — Thai TLWG fonts (fonts-thai-tlwg package)
    '/usr/share/fonts/truetype/thai/Loma.ttf',
    '/usr/share/fonts/truetype/tlwg/Loma.ttf',
    '/usr/share/fonts/truetype/thai-tlwg/Loma.ttf',
    // Linux — fallback (partial Thai)
    '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
];
$fontBoldCandidates = [
    // fonts/ directory
    $fontDir . 'Sarabun-Bold.ttf',
    $fontDir . 'NotoSansThai-Bold.ttf',
    $fontDir . 'Prompt-Bold.ttf',
    $fontDir . 'Kanit-Bold.ttf',
    // Windows
    'C:/Windows/Fonts/LeelawUI.ttf',        // Leelawadee UI (no separate bold; reuse regular)
    'C:/Windows/Fonts/leelawad.ttf',
    'C:/Windows/Fonts/cordiab.ttf',         // Cordia New Bold
    'C:/Windows/Fonts/tahomabd.ttf',
    'C:/Windows/Fonts/segoeuib.ttf',
    'C:/Windows/Fonts/arialbd.ttf',
    // Linux / Docker — NotoSansThai Bold
    '/usr/share/fonts/truetype/noto/NotoSansThai-Bold.ttf',
    '/usr/share/fonts/opentype/noto/NotoSansThai-Bold.ttf',
    '/usr/share/fonts/noto/NotoSansThai-Bold.ttf',
    // Linux — NotoSansCJK Bold
    '/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc',
    '/usr/share/fonts/truetype/noto/NotoSansCJK-Bold.ttc',
    // Linux — Thai TLWG Bold
    '/usr/share/fonts/truetype/thai/LomaBold.ttf',
    '/usr/share/fonts/truetype/tlwg/LomaBold.ttf',
    '/usr/share/fonts/truetype/thai-tlwg/LomaBold.ttf',
    // Linux — fallback
    '/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
];

$fontRegular = null;
foreach ($fontCandidates as $f) { if (file_exists($f)) { $fontRegular = $f; break; } }
$fontBold = null;
foreach ($fontBoldCandidates as $f) { if (file_exists($f)) { $fontBold = $f; break; } }
if (!$fontBold) $fontBold = $fontRegular;

// ── Emoji / symbol fallback font ──────────────────────────────────────────
// Used for per-character fallback: emoji and symbols not in the main Thai font.
//
// IMPORTANT: NotoEmoji-Regular.ttf from Google Fonts (recent versions) uses
// CBDT/CBLC color bitmap tables. PHP GD renders these as transparent/invisible.
// Preferred fonts that work with GD (monochrome TrueType outlines):
//   • Symbola.ttf       — best coverage, place in fonts/ directory
//   • unifont.ttf       — covers most Unicode incl. Mathematical Alphanumeric (U+1D400–U+1D7FF)
//                         Linux: apt-get install fonts-unifont
//                         Windows: download unifont.ttf and place in fonts/ directory
//   • seguisym.ttf      — Windows system font (BMP symbols only, no SMP math)
$fontFallbackCandidates = [
    // fonts/ directory — place Symbola.ttf or unifont.ttf / unifont.otf here
    $fontDir . 'Symbola.ttf',
    $fontDir . 'unifont.ttf',
    $fontDir . 'unifont.otf',
    $fontDir . 'NotoEmoji-Regular.ttf',  // works only if your version uses outlines
    // Linux — GNU Unifont (covers Mathematical Alphanumeric Symbols U+1D400–U+1D7FF)
    // Install: apt-get install fonts-unifont
    '/usr/share/fonts/truetype/unifont/unifont.ttf',
    '/usr/share/fonts/unifont/unifont.ttf',
    // Windows — Segoe UI Symbol (BMP symbols/dingbats only; no SMP math chars)
    'C:/Windows/Fonts/seguisym.ttf',
    // Linux — NotoEmoji (works only if installed version uses outline/monochrome tables)
    '/usr/share/fonts/truetype/noto/NotoEmoji-Regular.ttf',
    '/usr/share/fonts/noto/NotoEmoji-Regular.ttf',
    '/usr/share/fonts/noto-emoji/NotoEmoji-Regular.ttf',
    // Linux — DejaVu (wide BMP symbol coverage, no SMP emoji)
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
];

// Find a fallback font that actually renders Mathematical Alphanumeric Symbols
// (U+1D5D5 𝗕 = Mathematical Bold Sans-Serif B) with visible, distinct pixels in GD.
//
// Two-step test to reject both:
//  (a) color/bitmap fonts (CBDT/CBLC) → render transparent/invisible (0 pixels)
//  (b) fonts missing the glyph → render .notdef □ which looks identical for ALL missing chars
//
// Method: compare pixel count of 𝗕 (U+1D5D5) vs 𝗔 (U+1D5D4).
//   • Both are Mathematical Bold Sans-Serif letters with visibly different shapes.
//   • If font has actual glyphs → pixel counts differ (B ≠ A in shape) → accept.
//   • If font lacks them → both render as the SAME .notdef □ → identical counts → skip.
//   • If color/bitmap font → both render invisible → count == 0 → skip.
$fontFallback = null;
foreach ($fontFallbackCandidates as $_ff) {
    if (!file_exists($_ff)) continue;
    $_ti = imagecreatetruecolor(60, 60);
    $_wh = imagecolorallocate($_ti, 255, 255, 255);
    $_bl = imagecolorallocate($_ti, 0, 0, 0);

    // Test using BMP symbols (3-byte UTF-8 — GD handles these correctly on all systems).
    // ♾ (U+267E) vs ★ (U+2605): visually distinct; same .notdef pixel count if font lacks them.
    // Count pixels for ♾ (U+267E, PERMANENT PAPER SIGN / Infinity)
    imagefilledrectangle($_ti, 0, 0, 59, 59, $_wh);
    @imagettftext($_ti, 24, 0, 5, 45, $_bl, $_ff, "\xE2\x99\xBE");
    $_cntInf = 0;
    for ($_tx = 0; $_tx < 60; $_tx++)
        for ($_ty = 0; $_ty < 60; $_ty++)
            if (imagecolorat($_ti, $_tx, $_ty) !== $_wh) $_cntInf++;

    // Count pixels for ★ (U+2605, BLACK STAR)
    imagefilledrectangle($_ti, 0, 0, 59, 59, $_wh);
    @imagettftext($_ti, 24, 0, 5, 45, $_bl, $_ff, "\xE2\x98\x85");
    $_cntStar = 0;
    for ($_tx = 0; $_tx < 60; $_tx++)
        for ($_ty = 0; $_ty < 60; $_ty++)
            if (imagecolorat($_ti, $_tx, $_ty) !== $_wh) $_cntStar++;

    $_ti = null;
    // Accept if both visible AND distinct shapes (♾ ≠ ★ pixel count → font has actual glyphs)
    if ($_cntInf > 0 && $_cntStar > 0 && $_cntInf !== $_cntStar) { $fontFallback = $_ff; break; }
}
unset($_ff, $_ti, $_wh, $_bl, $_cntInf, $_cntStar, $_tx, $_ty);

// ── CJK / Japanese font ────────────────────────────────────────────────────
// Separate fallback font for Japanese (Hiragana, Katakana, Kanji) and CJK punctuation.
// Thai fonts do not include CJK glyphs, so Japanese text needs a dedicated font.
//
// Test: compare pixel count of か (U+304B) vs き (U+304D) — two Hiragana with distinct
// shapes. If counts differ → font has actual Hiragana glyphs → accept.
$fontCjkCandidates = [
    // ── fonts/ directory (shared hosting — primary source) ──────────────────
    // Dedicated Japanese fonts (best quality):
    $fontDir . 'NotoSansJP-Regular.ttf',
    $fontDir . 'NotoSansJP[wght].ttf',     // variable font variant (newer Google Fonts downloads)
    $fontDir . 'NotoSansCJK-Regular.ttf',
    $fontDir . 'NotoSansCJK-Regular.otf',
    // GNU Unifont covers full BMP including Hiragana/Katakana/Kanji (U+3000–U+9FFF).
    // Users who placed unifont here for symbol support automatically get Japanese too.
    $fontDir . 'unifont.ttf',
    $fontDir . 'unifont.otf',
    // ── System fonts (VPS / Docker / local dev) ──────────────────────────────
    // Windows
    'C:/Windows/Fonts/meiryo.ttc',
    'C:/Windows/Fonts/YuGothR.ttc',
    'C:/Windows/Fonts/msgothic.ttc',
    // Linux — Noto Sans CJK (fonts-noto-cjk package)
    '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
    '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
    '/usr/share/fonts/noto-cjk/NotoSansCJK-Regular.ttc',
    '/usr/share/fonts/opentype/noto/NotoSansCJKjp-Regular.otf',
    '/usr/share/fonts/noto/NotoSansCJKjp-Regular.otf',
    // Linux — GNU Unifont (fonts-unifont package)
    '/usr/share/fonts/truetype/unifont/unifont.ttf',
    '/usr/share/fonts/unifont/unifont.ttf',
];

$fontCjk = null;
foreach ($fontCjkCandidates as $_ff) {
    if (!file_exists($_ff)) continue;
    $_ti = imagecreatetruecolor(60, 60);
    $_wh = imagecolorallocate($_ti, 255, 255, 255);
    $_bl = imagecolorallocate($_ti, 0, 0, 0);

    // Count pixels for か (U+304B, HIRAGANA LETTER KA)
    imagefilledrectangle($_ti, 0, 0, 59, 59, $_wh);
    @imagettftext($_ti, 24, 0, 5, 45, $_bl, $_ff, "\xE3\x81\x8B");
    $_cntKa = 0;
    for ($_tx = 0; $_tx < 60; $_tx++)
        for ($_ty = 0; $_ty < 60; $_ty++)
            if (imagecolorat($_ti, $_tx, $_ty) !== $_wh) $_cntKa++;

    // Count pixels for き (U+304D, HIRAGANA LETTER KI)
    imagefilledrectangle($_ti, 0, 0, 59, 59, $_wh);
    @imagettftext($_ti, 24, 0, 5, 45, $_bl, $_ff, "\xE3\x81\x8D");
    $_cntKi = 0;
    for ($_tx = 0; $_tx < 60; $_tx++)
        for ($_ty = 0; $_ty < 60; $_ty++)
            if (imagecolorat($_ti, $_tx, $_ty) !== $_wh) $_cntKi++;

    $_ti = null;
    if ($_cntKa > 0 && $_cntKi > 0 && $_cntKa !== $_cntKi) { $fontCjk = $_ff; break; }
}
unset($_ff, $_ti, $_wh, $_bl, $_cntKa, $_cntKi, $_tx, $_ty);

if (!$fontRegular) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "No Unicode/Thai font found.\n";
    echo "Place 'Sarabun-Regular.ttf' in the fonts/ directory.\n";
    echo "Download: https://fonts.google.com/specimen/Sarabun\n";
    exit;
}

// ── Language labels ────────────────────────────────────────────────────────
$langParam = get_sanitized_param('lang', 10);
if (!in_array($langParam, ['th', 'en', 'ja'], true)) $langParam = 'th';

$labels = [
    'th' => [
        'time' => 'เวลา', 'program' => 'Program', 'venue' => 'เวที',
        'type' => 'ประเภท', 'artists' => 'ศิลปิน', 'noPrograms' => 'ไม่พบ Program',
        'generated' => 'สร้างโดย',
        'months' => ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
                     'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'],
        'days'   => ['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'],
        'dateFmt'=> function($ts, $L) {
            $d = date('j', $ts); $m = (int)date('n', $ts);
            $dow = (int)date('w', $ts); $y = (int)date('Y', $ts) + 543;
            return $L['days'][$dow] . ' ' . $d . ' ' . $L['months'][$m-1] . ' ' . $y;
        },
    ],
    'en' => [
        'time' => 'Time', 'program' => 'Program', 'venue' => 'Venue',
        'type' => 'Type', 'artists' => 'Artists', 'noPrograms' => 'No programs found',
        'generated' => 'Generated by',
        'months' => ['Jan','Feb','Mar','Apr','May','Jun',
                     'Jul','Aug','Sep','Oct','Nov','Dec'],
        'days'   => ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
        'dateFmt'=> function($ts, $L) {
            return $L['days'][(int)date('w',$ts)] . ', '
                 . $L['months'][(int)date('n',$ts)-1] . ' '
                 . date('j', $ts) . ', ' . date('Y', $ts);
        },
    ],
    'ja' => [
        'time' => '時間', 'program' => 'プログラム', 'venue' => '会場',
        'type' => 'タイプ', 'artists' => 'アーティスト', 'noPrograms' => 'プログラムなし',
        'generated' => '生成',
        'months' => ['1月','2月','3月','4月','5月','6月',
                     '7月','8月','9月','10月','11月','12月'],
        'days'   => ['日','月','火','水','木','金','土'],
        'dateFmt'=> function($ts, $L) {
            return date('Y', $ts) . '年'
                 . $L['months'][(int)date('n',$ts)-1]
                 . date('j', $ts) . '日（'
                 . $L['days'][(int)date('w', $ts)] . '）';
        },
    ],
];
$L = $labels[$langParam];

// ── Event & filter params ──────────────────────────────────────────────────
$eventSlug   = get_sanitized_param('event', '', 100);
$eventMeta   = $eventSlug ? get_event_by_slug($eventSlug) : null;
if ($eventSlug && !$eventMeta) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Event not found.'; exit;
}
$eventId     = $eventMeta ? intval($eventMeta['id']) : null;
$eventName   = $eventMeta ? $eventMeta['name']      : null;
$eventTz     = get_event_timezone($eventMeta);
$venueMode   = $eventMeta['venue_mode'] ?? (defined('VENUE_MODE') ? VENUE_MODE : 'multi');
$isSingleVenue = ($venueMode === 'single');

$filterArtists = get_sanitized_array_param('artist', 200, 50);
$filterVenues  = get_sanitized_array_param('venue',  200, 50);
$filterTypes   = get_sanitized_array_param('type',   200, 20);
$searchQuery   = get_sanitized_param('q', '', 200);

// ── Theme ──────────────────────────────────────────────────────────────────
$imgTheme = get_site_theme($eventMeta);

// ── Image cache check ──────────────────────────────────────────────────────
// Cache key encodes all params that affect image output + APP_VERSION (code changes bust cache)

$_sortedA = $filterArtists; sort($_sortedA);
$_sortedV = $filterVenues;  sort($_sortedV);
$_sortedT = $filterTypes;   sort($_sortedT);
$_cachePayload = json_encode([
    'event'   => $eventId,
    'artists' => $_sortedA,
    'venues'  => $_sortedV,
    'types'   => $_sortedT,
    'q'       => $searchQuery,
    'lang'    => $langParam,
    'version' => APP_VERSION,
    'theme'   => $imgTheme,
    // Include active font paths so adding/swapping a font busts old cached images
    'fonts'   => [$fontRegular, $fontBold, $fontFallback, $fontCjk],
]);
$imgCacheKey = hash('xxh128', $_cachePayload);
$imgCacheDir  = defined('IMAGE_CACHE_DIR') ? IMAGE_CACHE_DIR : (__DIR__ . '/cache');
$imgCacheFile = $imgCacheDir . '/img_' . ($eventId ?? '0') . '_' . $imgCacheKey . '.png';
$imgCacheTTL  = defined('IMAGE_CACHE_TTL') ? IMAGE_CACHE_TTL : 3600;

if (file_exists($imgCacheFile) && (time() - filemtime($imgCacheFile)) < $imgCacheTTL) {
    $slug     = $eventSlug ?: 'all';
    $filename = 'schedule-' . preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug))
              . '-' . date('Ymd') . '.png';
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    header('X-Image-Cache: HIT');
    readfile($imgCacheFile);
    exit;
}

// ── Fetch programs (query cache → direct PDO) ──────────────────────────────
$queryCacheFile = 'query_event_' . ($eventId ?? '0') . '.json';
$cached = get_query_cache($queryCacheFile);
$imgArtistMap = null;   // program_id => [name, ...] from program_artists junction table
$imgUseArtistsTable = false;

if ($cached && isset($cached['all_events'])) {
    $rawPrograms = $cached['all_events'];
    // Use junction-table artist map when available (same logic as index.php)
    if (!empty($cached['program_artists_map'])) {
        $imgArtistMap       = $cached['program_artists_map'];
        $imgUseArtistsTable = true;
    }
} else {
    try {
        $dbImg = new PDO('sqlite:' . DB_PATH);
        $dbImg->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($eventId !== null) {
            $stmtImg = $dbImg->prepare(
                "SELECT id, uid, title, start, end, location, organizer,
                        description, categories, program_type, stream_url,
                        event_id, updated_at
                 FROM programs WHERE event_id = :eid ORDER BY start ASC"
            );
            $stmtImg->execute([':eid' => $eventId]);
        } else {
            $stmtImg = $dbImg->query(
                "SELECT p.id, p.uid, p.title, p.start, p.end, p.location,
                        p.organizer, p.description, p.categories, p.program_type,
                        p.stream_url, p.event_id, p.updated_at
                 FROM programs p
                 JOIN events e ON p.event_id = e.id
                 WHERE e.is_active = 1
                 ORDER BY p.start ASC"
            );
        }
        $rawPrograms = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
        $stmtImg->closeCursor();
        $stmtImg = null;

        // Load program_artists junction map when artist filter is active
        if (!empty($filterArtists)) {
            $hasPATable = (bool)$dbImg->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='program_artists'"
            )->fetch();
            if ($hasPATable) {
                $imgUseArtistsTable = true;
                $ids = implode(',', array_map(fn($p) => (int)$p['id'], $rawPrograms));
                if ($ids !== '') {
                    $stmtPA = $dbImg->query(
                        "SELECT pa.program_id, a.name
                         FROM program_artists pa
                         JOIN artists a ON a.id = pa.artist_id
                         WHERE pa.program_id IN ($ids)"
                    );
                    foreach ($stmtPA->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $imgArtistMap[(string)$row['program_id']][] = $row['name'];
                    }
                    $stmtPA->closeCursor();
                    $stmtPA = null;
                }
            }
        }

        $dbImg = null;
    } catch (PDOException $ex) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Database error: ' . $ex->getMessage();
        exit;
    }
}

$programs = array_values(array_filter($rawPrograms, function ($p) use (
    $filterArtists, $filterVenues, $filterTypes, $searchQuery,
    $imgArtistMap, $imgUseArtistsTable
) {
    if (!empty($filterArtists)) {
        $ok = false;
        if ($imgUseArtistsTable && $imgArtistMap !== null) {
            // Mirror index.php: use junction-table canonical names
            $names = $imgArtistMap[(string)($p['id'] ?? '')] ?? [];
            foreach ($filterArtists as $fa) {
                if (in_array($fa, $names, true)) { $ok = true; break; }
            }
        } else {
            // Fallback: categories text field (same as index.php fallback)
            $cats = array_map('trim', explode(',', $p['categories'] ?? ''));
            foreach ($filterArtists as $fa) {
                if (in_array($fa, $cats, true)) { $ok = true; break; }
            }
        }
        if (!$ok) return false;
    }
    if (!empty($filterVenues) && !in_array($p['location'] ?? '', $filterVenues, true)) return false;
    if (!empty($filterTypes)  && !in_array($p['program_type'] ?? '', $filterTypes, true)) return false;
    if ($searchQuery) {
        $hay = mb_strtolower(($p['title'] ?? '') . ' ' . ($p['categories'] ?? '') . ' ' . ($p['location'] ?? ''));
        if (mb_strpos($hay, mb_strtolower($searchQuery)) === false) return false;
    }
    return true;
}));

// Group by date
$byDate = [];
foreach ($programs as $p) { $byDate[date('Y-m-d', strtotime($p['start']))][] = $p; }
ksort($byDate);

// ── Layout constants ───────────────────────────────────────────────────────
const IMG_W    = 900;
const IMG_PAD  = 20;

// Column widths (total = IMG_W - 2*IMG_PAD = 860)
// Single venue: remove venue column (150px) → add to title
$cols = $isSingleVenue
    ? ['time' => 110, 'title' => 445, 'type' => 105, 'artists' => 200]
    : ['time' => 110, 'title' => 295, 'venue' => 150, 'type' => 105, 'artists' => 200];
$cx   = [];
$xCur = IMG_PAD;
foreach ($cols as $k => $w) { $cx[$k] = $xCur; $xCur += $w; }

const FS_BODY = 10.5;  // body text
const FS_HEAD = 15.0;  // image header title
const FS_SUB  =  9.5;  // subtitle / small
const FS_DATE = 11.5;  // date group header
const FS_COL  = 10.0;  // column header

const LINE_H  = 16;    // px between text lines
const ART_ADV = 21;    // px advance per artist badge (LINE_H + 5 gap)
const ROW_PAD =  7;    // top+bottom padding inside a program row
const MIN_ROH = 56;    // minimum row height
const IMG_HDR = 68;    // image header height (multi-venue)
// Single-venue adds a venue line to the header (+18px)
const COL_HDR = 30;    // column header height
const DT_HDR  = 34;    // date group header height
const FTR_H   = 26;    // footer height

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * Return true if the character is in a Unicode range that Thai/Latin fonts
 * typically do not cover (emoji, symbols, pictographs).
 *
 * FreeType's imagettfbbox() returns the .notdef (□) glyph width for missing
 * codepoints — always non-zero — so bbox-based detection is unreliable.
 * Unicode range checking is the correct approach.
 */
function isEmojiCodepoint(string $char): bool {
    static $cache = [];
    if (isset($cache[$char])) return $cache[$char];
    $cp = mb_ord($char, 'UTF-8');
    // BMP symbol ranges. CJK/Japanese ranges are handled separately by isCjkCodepoint().
    // SMP (U+10000+) chars are pre-processed by gdNormalizeSmp() before reaching here.
    return $cache[$char] = (
        ($cp >= 0x2194 && $cp <= 0x2199) ||   // Arrows subset (↔↕↖↗↘↙)
        ($cp >= 0x2300 && $cp <= 0x23FF) ||   // Miscellaneous Technical (⌚⌛⏰ etc.)
        ($cp >= 0x2600 && $cp <= 0x26FF) ||   // Miscellaneous Symbols (☀♥★♾ etc.)
        ($cp >= 0x2700 && $cp <= 0x27BF) ||   // Dingbats (✓✗✨ etc.)
        ($cp >= 0x2B00 && $cp <= 0x2BFF)      // Misc Symbols & Arrows (⬛⭐ etc.)
    );
}

/** Returns true for CJK / Japanese codepoints that need a dedicated CJK font. */
function isCjkCodepoint(string $char): bool {
    static $cache = [];
    if (isset($cache[$char])) return $cache[$char];
    $cp = mb_ord($char, 'UTF-8');
    return $cache[$char] = (
        ($cp >= 0x3000 && $cp <= 0x303F) ||   // CJK Symbols & Punctuation (【】「」『』 etc.)
        ($cp >= 0x3040 && $cp <= 0x309F) ||   // Hiragana
        ($cp >= 0x30A0 && $cp <= 0x30FF) ||   // Katakana
        ($cp >= 0x31F0 && $cp <= 0x31FF) ||   // Katakana Phonetic Extensions
        ($cp >= 0x4E00 && $cp <= 0x9FFF) ||   // CJK Unified Ideographs (Kanji)
        ($cp >= 0xF900 && $cp <= 0xFAFF) ||   // CJK Compatibility Ideographs
        ($cp >= 0xFF00 && $cp <= 0xFF9F)       // Fullwidth / Halfwidth Forms (ｱｲｳ ＡＢＣ etc.)
    );
}

/**
 * Normalize SMP characters (U+10000+) for GD rendering.
 *
 * PHP GD / libgd cannot render 4-byte UTF-8 sequences (SMP, U+10000+) correctly
 * on many systems — it decomposes them into individual bytes causing garbled output
 * like "ð□□□" instead of the intended character.
 *
 * This function converts Mathematical Alphanumeric Symbols (U+1D400–U+1D7FF) to
 * their base ASCII equivalents so they render readably:
 *   𝗕𝗔𝗖𝗞 𝗜𝗡 𝗧𝗜𝗠𝗘  →  BACK IN TIME
 *
 * All other SMP characters (emoji, musical symbols, etc.) are stripped since
 * GD cannot render them regardless of the font.
 */
function gdNormalizeSmp(string $text): string {
    // Fast path: no 4-byte UTF-8 lead byte (F0) means no SMP characters
    if (strpos($text, "\xF0") === false) return $text;
    $out = '';
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $cp = mb_ord($ch, 'UTF-8');
        if ($cp < 0x10000) { $out .= $ch; continue; }
        if ($cp >= 0x1D400 && $cp <= 0x1D7FF) { $out .= gdMapMathChar($cp); continue; }
        // Other SMP (emoji U+1F000+, musical U+1D000-U+1D3FF, etc.): strip
    }
    return $out;
}

/**
 * Map a Mathematical Alphanumeric codepoint (U+1D400–U+1D7FF) to its base ASCII char.
 * Covers the main contiguous letter and digit ranges; returns '' for gaps/unknown.
 */
function gdMapMathChar(int $cp): string {
    // Uppercase letter ranges — each is 26 consecutive codepoints mapping to A-Z
    static $upper = [
        [0x1D400, 0x1D419],  // Bold
        [0x1D434, 0x1D44D],  // Italic
        [0x1D468, 0x1D481],  // Bold Italic
        [0x1D4D0, 0x1D4E9],  // Bold Script
        [0x1D56C, 0x1D585],  // Bold Fraktur
        [0x1D5A0, 0x1D5B9],  // Sans-Serif
        [0x1D5D4, 0x1D5ED],  // Bold Sans-Serif  ← 𝗔𝗕𝗖...𝗭
        [0x1D608, 0x1D621],  // Sans-Serif Italic
        [0x1D63C, 0x1D655],  // Sans-Serif Bold Italic
        [0x1D670, 0x1D689],  // Monospace
    ];
    // Lowercase letter ranges — each is 26 consecutive codepoints mapping to a-z
    static $lower = [
        [0x1D41A, 0x1D433],  // Bold
        [0x1D44E, 0x1D467],  // Italic
        [0x1D482, 0x1D49B],  // Bold Italic
        [0x1D4EA, 0x1D503],  // Bold Script
        [0x1D586, 0x1D59F],  // Bold Fraktur
        [0x1D5BA, 0x1D5D3],  // Sans-Serif
        [0x1D5EE, 0x1D607],  // Bold Sans-Serif  ← 𝗮𝗯𝗰...𝘇
        [0x1D622, 0x1D63B],  // Sans-Serif Italic
        [0x1D656, 0x1D66F],  // Sans-Serif Bold Italic
        [0x1D68A, 0x1D6A3],  // Monospace
    ];
    // Digit ranges — each is 10 consecutive codepoints mapping to 0-9
    static $digits = [
        [0x1D7CE, 0x1D7D7],  // Bold
        [0x1D7D8, 0x1D7E1],  // Double-Struck
        [0x1D7E2, 0x1D7EB],  // Sans-Serif
        [0x1D7EC, 0x1D7F5],  // Bold Sans-Serif
        [0x1D7F6, 0x1D7FF],  // Monospace
    ];
    foreach ($upper  as [$s, $e]) { if ($cp >= $s && $cp <= $e) return chr(0x41 + $cp - $s); }
    foreach ($lower  as [$s, $e]) { if ($cp >= $s && $cp <= $e) return chr(0x61 + $cp - $s); }
    foreach ($digits as [$s, $e]) { if ($cp >= $s && $cp <= $e) return chr(0x30 + $cp - $s); }
    return '';  // Unknown math symbol: strip
}

/**
 * Pick the correct font for a single character.
 * Priority: CJK font (Japanese/Kanji) → symbol fallback (♾ ★ etc.) → main Thai font.
 * Uses Unicode range checking (reliable) instead of bbox-width probing.
 */
function gdPickFont(string $char, string $main, ?string $fallback): string {
    global $fontCjk;
    if ($fontCjk !== null && isCjkCodepoint($char)) return $fontCjk;
    if ($fallback !== null && isEmojiCodepoint($char)) return $fallback;
    return $main;
}

/**
 * Measure text pixel width with per-character font selection.
 */
function gdMeasure(string $text, float $size, string $font, ?string $fallback): int {
    global $fontCjk;
    $text = gdNormalizeSmp($text);
    if ($text === '') return 0;
    if ($fallback === null && $fontCjk === null) {
        $bb = @imagettfbbox($size, 0, $font, $text);
        return $bb ? abs($bb[4] - $bb[6]) : 0;
    }
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $w = 0; $runFont = null; $runText = '';
    foreach ($chars as $c) {
        $f = gdPickFont($c, $font, $fallback);
        if ($f === $runFont) { $runText .= $c; continue; }
        if ($runFont !== null) {
            $bb = @imagettfbbox($size, 0, $runFont, $runText);
            $w += $bb ? abs($bb[4] - $bb[6]) : 0;
        }
        $runFont = $f; $runText = $c;
    }
    if ($runFont !== null && $runText !== '') {
        $bb = @imagettfbbox($size, 0, $runFont, $runText);
        $w += $bb ? abs($bb[4] - $bb[6]) : 0;
    }
    return $w;
}

/**
 * Render text with per-character font fallback. Returns next X position.
 * Characters in the main font are batched into runs for efficiency.
 */
function gdText($img, float $size, int $x, int $y, $color, string $font, ?string $fallback, string $text): int {
    global $fontCjk;
    $text = gdNormalizeSmp($text);
    if ($text === '') return $x;
    if ($fallback === null && $fontCjk === null) {
        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
        $bb = @imagettfbbox($size, 0, $font, $text);
        return $x + ($bb ? abs($bb[4] - $bb[6]) : 0);
    }
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $runFont = null; $runText = ''; $curX = $x;
    foreach ($chars as $c) {
        $f = gdPickFont($c, $font, $fallback);
        if ($f === $runFont) { $runText .= $c; continue; }
        if ($runFont !== null && $runText !== '') {
            imagettftext($img, $size, 0, $curX, $y, $color, $runFont, $runText);
            $bb = @imagettfbbox($size, 0, $runFont, $runText);
            $curX += $bb ? abs($bb[4] - $bb[6]) : 0;
        }
        $runFont = $f; $runText = $c;
    }
    if ($runFont !== null && $runText !== '') {
        imagettftext($img, $size, 0, $curX, $y, $color, $runFont, $runText);
        $bb = @imagettfbbox($size, 0, $runFont, $runText);
        $curX += $bb ? abs($bb[4] - $bb[6]) : 0;
    }
    return $curX;
}

/** Wrap $text within $maxW px using per-character font selection; return array of lines. */
$wc = [];   // wrap cache
function gdWrap(string $text, string $font, float $size, int $maxW, array &$cache, ?string $fallback = null): array {
    if ($text === '') return [''];
    $k = $font . $size . $maxW . $text;
    if (isset($cache[$k])) return $cache[$k];
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $lines = []; $cur = '';
    foreach ($chars as $c) {
        $lw = gdMeasure($cur . $c, $size, $font, $fallback);
        if ($lw > $maxW && $cur !== '') {
            $sp = mb_strrpos($cur, ' ');
            if ($sp !== false && $sp > 0) {
                $lines[] = mb_substr($cur, 0, $sp);
                $cur     = mb_substr($cur, $sp + 1) . $c;
            } else {
                $lines[] = $cur; $cur = $c;
            }
        } else { $cur .= $c; }
    }
    if ($cur !== '') $lines[] = $cur;
    return $cache[$k] = $lines ?: [''];
}

/** Filled rounded rectangle. */
function gdRoundRect($img, int $x1, int $y1, int $x2, int $y2, int $r, $color): void {
    if ($x2 <= $x1 || $y2 <= $y1) return;
    $r = min($r, (int)(($x2 - $x1) / 2), (int)(($y2 - $y1) / 2));
    imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    foreach ([[$x1+$r,$y1+$r],[$x2-$r,$y1+$r],[$x1+$r,$y2-$r],[$x2-$r,$y2-$r]] as [$ex,$ey]) {
        imagefilledellipse($img, $ex, $ey, $r * 2, $r * 2, $color);
    }
}

/** Font ascent for vertical centering. */
function gdAscent(float $size, string $font): int {
    $bb = imagettfbbox($size, 0, $font, 'Ag');
    return abs($bb[7]);
}

/** Row height for one program. */
function calcRowH(array $p, array $cols, string $font, array &$cache, bool $singleVenue = false, ?string $fallback = null): int {
    $titleW = $cols['title'] - 8;
    $tl = count(gdWrap($p['title'] ?? '', $font, FS_BODY, $titleW, $cache, $fallback));
    $titleH = $tl * LINE_H;
    $vl     = isset($cols['venue']) ? count(gdWrap($p['location'] ?? '', $font, FS_BODY, $cols['venue'] - 8, $cache, $fallback)) : 0;
    $venueH = $singleVenue ? 0 : $vl * LINE_H;
    // Artists: count actual badges, each takes ART_ADV px
    $artCount = count(array_filter(array_map('trim', explode(',', $p['categories'] ?? ''))));
    $artH     = max(1, $artCount) * ART_ADV;
    // Time badge: compact fixed height (tLines * LINE_H + 14px padding + 2*margin)
    $tLines   = 1 + (!empty($p['end']) && substr($p['start'], 11, 5) !== substr($p['end'], 11, 5) ? 1 : 0)
                  + (!empty($p['stream_url']) ? 1 : 0);
    $timeH    = $tLines * LINE_H + 14 + 20;  // badge height + min 10px margin each side
    return max(max($titleH, $artH, $venueH, $timeH) + ROW_PAD * 2, MIN_ROH);
}

// ── Venue for header (single-venue: first program's location) ─────────────
$firstVenue = ($isSingleVenue && !empty($programs))
    ? ($programs[0]['location'] ?? '')
    : '';
$imgHdrH = ($isSingleVenue && $firstVenue !== '') ? IMG_HDR + 18 : IMG_HDR;

// ── Pass 1: calculate total height ────────────────────────────────────────
$totalH = $imgHdrH + COL_HDR;
foreach ($byDate as $rows) {
    $totalH += DT_HDR;
    foreach ($rows as $p) $totalH += calcRowH($p, $cols, $fontRegular, $wc, $isSingleVenue, $fontFallback);
}
$totalH += FTR_H + IMG_PAD;

// ── Pass 2: draw ──────────────────────────────────────────────────────────
$img = imagecreatetruecolor(IMG_W, $totalH);

// Palette — theme-aware
// Each entry: [hdrBg(deep), colHdr(medium), accent(dark), dateBg, dateTxt, border, rowEven, venHdr(light)]
$_palettes = [
    'sakura'   => [[194, 24,  91], [244,143,177], [233, 30, 99], [252,228,236], [136, 14, 79], [253,216,230], [255,249,252], [255,210,230]],
    'ocean'    => [[  1, 87, 155], [ 79,195,247], [  2,136,209], [225,245,254], [  1, 72,128], [200,237,254], [240,250,255], [179,229,252]],
    'forest'   => [[ 27, 94,  32], [102,187,106], [ 46,125, 50], [232,245,233], [ 27, 94, 32], [200,230,201], [241,248,241], [165,214,167]],
    'midnight' => [[ 74, 20, 140], [171, 71,188], [123, 31,162], [243,229,245], [ 74, 20,140], [225,190,231], [250,242,252], [206,147,216]],
    'sunset'   => [[230, 81,   0], [255,167, 38], [245,124,  0], [255,243,224], [230, 81,  0], [255,224,178], [255,251,240], [255,204,128]],
    'dark'     => [[ 38, 50,  56], [ 84,110,122], [ 55, 71, 79], [236,239,241], [ 38, 50, 56], [176,190,197], [245,247,248], [120,144,156]],
    'gray'     => [[ 97, 97,  97], [158,158,158], [117,117,117], [245,245,245], [ 97, 97, 97], [224,224,224], [250,250,250], [189,189,189]],
    'crimson'  => [[123,  0,   0], [239, 83, 80], [198, 40, 40], [255,245,245], [123,  0,  0], [255,205,210], [255,250,250], [255,205,210]],
    'teal'     => [[  0, 77,  64], [ 38,166,154], [  0,121,107], [224,242,241], [  0, 77, 64], [178,223,219], [240,250,249], [178,223,219]],
    'rose'     => [[159, 18,  57], [251,113,133], [225, 29, 72], [255,240,243], [159, 18, 57], [254,205,211], [255,245,247], [254,205,211]],
    'amber'    => [[230, 81,   0], [255,193,  7], [245,127, 23], [255,253,231], [230, 81,  0], [255,245,157], [255,255,245], [255,224,130]],
    'indigo'   => [[ 40, 53, 147], [ 92,107,192], [ 63, 81,181], [232,234,246], [ 40, 53,147], [197,202,233], [243,244,251], [159,168,218]],
];
$_pal = $_palettes[$imgTheme] ?? $_palettes['sakura'];
[$_hdr, $_med, $_acc, $_dbg, $_dtx, $_bdr, $_rew, $_ven] = $_pal;

$cWhite   = imagecolorallocate($img, 255, 255, 255);
$cRowEven = imagecolorallocate($img, $_rew[0], $_rew[1], $_rew[2]);
$cBorder  = imagecolorallocate($img, $_bdr[0], $_bdr[1], $_bdr[2]);
$cHdrBg   = imagecolorallocate($img, $_hdr[0], $_hdr[1], $_hdr[2]);
$cColHdr  = imagecolorallocate($img, $_med[0], $_med[1], $_med[2]);
$cDateBg  = imagecolorallocate($img, $_dbg[0], $_dbg[1], $_dbg[2]);
$cDateTxt = imagecolorallocate($img, $_dtx[0], $_dtx[1], $_dtx[2]);
$cFtrBg   = imagecolorallocate($img, 248, 248, 248);
$cTxtMain = imagecolorallocate($img,  30,  30,  30);
$cTxtWht  = imagecolorallocate($img, 255, 255, 255);
$cTxtGray = imagecolorallocate($img, 120, 120, 120);
$cTypeBg  = imagecolorallocate($img, $_acc[0], $_acc[1], $_acc[2]);
$cArtBg   = imagecolorallocate($img, $_med[0], $_med[1], $_med[2]);
$cTimeBg  = imagecolorallocate($img, $_med[0], $_med[1], $_med[2]);
$cLive    = imagecolorallocate($img, $_acc[0], $_acc[1], $_acc[2]);
$cVenHdr  = imagecolorallocate($img, $_ven[0], $_ven[1], $_ven[2]);

imagefilledrectangle($img, 0, 0, IMG_W - 1, $totalH - 1, $cWhite);

// ── Image header ───────────────────────────────────────────────────────────
imagefilledrectangle($img, 0, 0, IMG_W - 1, $imgHdrH - 1, $cHdrBg);
$siteTitle  = get_site_title();
$mainTitle  = $eventName ?: $siteTitle;

$aHdr = gdAscent(FS_HEAD, $fontBold);
$aSub = gdAscent(FS_SUB, $fontRegular);
gdText($img, FS_HEAD, IMG_PAD, IMG_PAD + $aHdr, $cTxtWht, $fontBold, $fontFallback, $mainTitle);
$subY = IMG_PAD + (int)(FS_HEAD * 1.6) + $aSub;
// Single-venue: venue from first program below event name
if ($firstVenue !== '') {
    gdText($img, FS_SUB, IMG_PAD, $subY, $cVenHdr, $fontRegular, $fontFallback, $firstVenue);
}

// Filter summary top-right
$parts = [];
if (!empty($filterArtists)) {
    $shown = array_slice($filterArtists, 0, 2);
    $parts[] = implode(', ', $shown) . (count($filterArtists) > 2 ? ' +' . (count($filterArtists) - 2) : '');
}
if (!empty($filterVenues)) {
    $shown = array_slice($filterVenues, 0, 2);
    $parts[] = implode(', ', $shown) . (count($filterVenues) > 2 ? ' +' . (count($filterVenues) - 2) : '');
}
if ($parts) {
    $summary = mb_substr(implode(' | ', $parts), 0, 70);
    $sw = gdMeasure($summary, FS_SUB, $fontRegular, $fontFallback);
    gdText($img, FS_SUB, IMG_W - IMG_PAD - $sw, IMG_PAD + $aHdr, $cTxtWht, $fontRegular, $fontFallback, $summary);
}
$genStr = date('d/m/Y H:i') . ' ' . $eventTz;
$aGen = gdAscent(FS_SUB, $fontRegular);
$bb = imagettfbbox(FS_SUB, 0, $fontRegular, $genStr);
$gw = abs($bb[4] - $bb[6]);
imagettftext($img, FS_SUB, 0, IMG_W - IMG_PAD - $gw,
    IMG_PAD + (int)(FS_HEAD * 1.6) + $aGen, $cTxtWht, $fontRegular, $genStr);

// ── Column headers ─────────────────────────────────────────────────────────
$y = $imgHdrH;
imagefilledrectangle($img, 0, $y, IMG_W - 1, $y + COL_HDR - 1, $cColHdr);
imageline($img, 0, $y + COL_HDR - 1, IMG_W - 1, $y + COL_HDR - 1, $cBorder);

$aCol = gdAscent(FS_COL, $fontBold);
$colLbls = $isSingleVenue
    ? ['time' => $L['time'], 'title' => $L['program'], 'type' => $L['type'], 'artists' => $L['artists']]
    : ['time' => $L['time'], 'title' => $L['program'], 'venue' => $L['venue'], 'type' => $L['type'], 'artists' => $L['artists']];
foreach ($colLbls as $k => $lbl) {
    $ty = $y + (int)((COL_HDR - FS_COL) / 2) + $aCol;
    gdText($img, FS_COL, $cx[$k] + 5, $ty, $cTxtWht, $fontBold, $fontFallback, $lbl);
}
// Vertical separators in column header (skip first — left edge of time col needs no border)
$xSep = IMG_PAD;
$isFirstColSep = true;
foreach ($cols as $w) {
    if (!$isFirstColSep) {
        imageline($img, $xSep, $y, $xSep, $y + COL_HDR - 1,
            imagecolorallocatealpha($img, 255, 255, 255, 60));
    }
    $isFirstColSep = false;
    $xSep += $w;
}
$y += COL_HDR;

// ── No programs ────────────────────────────────────────────────────────────
if (empty($programs)) {
    $aNop = gdAscent(FS_DATE, $fontRegular);
    gdText($img, FS_DATE, IMG_PAD, $y + 30 + $aNop, $cTxtGray, $fontRegular, $fontFallback, $L['noPrograms']);
} else {

// ── Date groups ────────────────────────────────────────────────────────────
$rowIdx = 0;
$aBody  = gdAscent(FS_BODY, $fontBold);
$aSm    = gdAscent(FS_SUB, $fontRegular);
$aDate  = gdAscent(FS_DATE, $fontBold);

foreach ($byDate as $dateKey => $rows) {
    // Date header
    $ts = strtotime($dateKey);
    $dateLbl = $L['dateFmt']($ts, $L);
    imagefilledrectangle($img, 0, $y, IMG_W - 1, $y + DT_HDR - 1, $cDateBg);
    imageline($img, 0, $y + DT_HDR - 1, IMG_W - 1, $y + DT_HDR - 1, $cBorder);
    $ty = $y + (int)((DT_HDR - FS_DATE) / 2) + $aDate;
    gdText($img, FS_DATE, IMG_PAD + 8, $ty, $cDateTxt, $fontBold, $fontFallback, $dateLbl);
    $y += DT_HDR;

    // Program rows
    foreach ($rows as $p) {
        $rowH  = calcRowH($p, $cols, $fontRegular, $wc, $isSingleVenue, $fontFallback);
        $rowBg = ($rowIdx % 2 === 0) ? $cWhite : $cRowEven;
        imagefilledrectangle($img, 0, $y, IMG_W - 1, $y + $rowH - 1, $rowBg);
        imageline($img, 0, $y + $rowH - 1, IMG_W - 1, $y + $rowH - 1, $cBorder);
        $xSep = IMG_PAD;
        foreach ($cols as $w) { $xSep += $w; imageline($img, $xSep, $y, $xSep, $y + $rowH - 1, $cBorder); }

        $ty0 = $y + ROW_PAD + $aBody;   // first-line baseline

        // ── Time — compact fixed-height badge centered in row ──
        $startStr  = date('H:i', strtotime($p['start']));
        $endStr    = date('H:i', strtotime($p['end']));
        $hasEnd    = ($startStr !== $endStr);
        $hasLive   = !empty($p['stream_url']);
        $tLines    = 1 + ($hasEnd ? 1 : 0) + ($hasLive ? 1 : 0);
        $badgePadV = 7;   // px above/below text inside badge
        $badgePadH = 10;  // px left/right of badge from column edge
        $badgeH    = $tLines * LINE_H + $badgePadV * 2;
        $badgeW    = $cols['time'] - $badgePadH * 2;
        $badgeX1   = $cx['time'] + $badgePadH;
        $badgeY1   = $y + (int)(($rowH - $badgeH) / 2);
        gdRoundRect($img, $badgeX1, $badgeY1, $badgeX1 + $badgeW, $badgeY1 + $badgeH, 7, $cTimeBg);
        // Text: centered X in full column, baseline from badge top
        $tBaseY = $badgeY1 + $badgePadV + $aBody;
        $bbS = imagettfbbox(FS_BODY, 0, $fontBold, $startStr);
        $sxS = $cx['time'] + (int)(($cols['time'] - abs($bbS[4] - $bbS[6])) / 2);
        imagettftext($img, FS_BODY, 0, $sxS, $tBaseY, $cTxtWht, $fontBold, $startStr);
        if ($hasEnd) {
            $bbE = imagettfbbox(FS_SUB, 0, $fontRegular, $endStr);
            $sxE = $cx['time'] + (int)(($cols['time'] - abs($bbE[4] - $bbE[6])) / 2);
            imagettftext($img, FS_SUB, 0, $sxE, $tBaseY + LINE_H, $cTxtWht, $fontRegular, $endStr);
        }
        if ($hasLive) {
            $liveStr = '* LIVE';
            $bbL = imagettfbbox(FS_SUB - 1, 0, $fontRegular, $liveStr);
            $sxL = $cx['time'] + (int)(($cols['time'] - abs($bbL[4] - $bbL[6])) / 2);
            imagettftext($img, FS_SUB - 1, 0, $sxL, $tBaseY + LINE_H * ($hasEnd ? 2 : 1), $cLive, $fontRegular, $liveStr);
        }

        // ── Title (+ venue subtitle in single-venue mode) ──
        $titleLines = gdWrap($p['title'] ?? '', $fontBold, FS_BODY, $cols['title'] - 8, $wc, $fontFallback);
        foreach ($titleLines as $li => $line) {
            gdText($img, FS_BODY, $cx['title'] + 5, $ty0 + $li * LINE_H, $cTxtMain, $fontBold, $fontFallback, $line);
        }

        // ── Venue column (multi-venue only) ──
        if (!$isSingleVenue) {
            $venueLines = gdWrap($p['location'] ?? '', $fontRegular, FS_BODY, $cols['venue'] - 8, $wc, $fontFallback);
            foreach ($venueLines as $li => $line) {
                gdText($img, FS_BODY, $cx['venue'] + 5, $ty0 + $li * LINE_H, $cTxtMain, $fontRegular, $fontFallback, $line);
            }
        }

        // ── Type badge ──
        $typeText = $p['program_type'] ?? '';
        if ($typeText !== '') {
            $tw  = gdMeasure($typeText, FS_SUB, $fontRegular, $fontFallback) + 12;
            $th  = (int)(FS_SUB * 1.6);
            $bx1 = $cx['type'] + 4;
            $by1 = $y + (int)(($rowH - $th) / 2);
            gdRoundRect($img, $bx1, $by1, $bx1 + $tw, $by1 + $th, 4, $cTypeBg);
            gdText($img, FS_SUB, $bx1 + 6, $by1 + $th - 3, $cTxtWht, $fontRegular, $fontFallback, $typeText);
        }

        // ── Artist badges (with increased vertical spacing) ──
        $cats = array_filter(array_map('trim', explode(',', $p['categories'] ?? '')));
        $artY = $ty0;
        $ah   = (int)(FS_SUB * 1.55);   // badge height ≈ 14px
        foreach ($cats as $cat) {
            if ($artY - $ah + 2 > $y + $rowH - ROW_PAD) break;
            $artLines = gdWrap($cat, $fontRegular, FS_SUB, $cols['artists'] - 10, $wc, $fontFallback);
            $artText  = $artLines[0] . (count($artLines) > 1 ? '…' : '');
            $aw  = gdMeasure($artText, FS_SUB, $fontRegular, $fontFallback) + 10;
            gdRoundRect($img, $cx['artists'] + 4, $artY - $ah + 2,
                               $cx['artists'] + 4 + $aw, $artY + 3, 4, $cArtBg);
            gdText($img, FS_SUB, $cx['artists'] + 9, $artY, $cTxtMain, $fontRegular, $fontFallback, $artText);
            $artY += ART_ADV;
        }

        $y += $rowH;
        $rowIdx++;
    }
}

} // end if programs

// ── Footer ─────────────────────────────────────────────────────────────────
imagefilledrectangle($img, 0, $y, IMG_W - 1, $y + FTR_H - 1, $cFtrBg);
imageline($img, 0, $y, IMG_W - 1, $y, $cBorder);
$aFtr = gdAscent(FS_SUB, $fontRegular);
$footerLeft = $L['generated'] . ' ' . $siteTitle . '  ·  ' . date('d/m/Y H:i');
gdText($img, FS_SUB, IMG_PAD, $y + (int)((FTR_H + FS_SUB) / 2), $cTxtGray, $fontRegular, $fontFallback, $footerLeft);
$countStr = count($programs) . ' programs';
$bb = imagettfbbox(FS_SUB, 0, $fontRegular, $countStr);
$cw = abs($bb[4] - $bb[6]);
imagettftext($img, FS_SUB, 0, IMG_W - IMG_PAD - $cw,
    $y + (int)((FTR_H + FS_SUB) / 2), $cTxtGray, $fontRegular, $countStr);

// ── Save to image cache ─────────────────────────────────────────────────────
if (!is_dir($imgCacheDir)) {
    mkdir($imgCacheDir, 0755, true);
}
imagepng($img, $imgCacheFile, 6);

// ── Output PNG ─────────────────────────────────────────────────────────────
$slug     = $eventSlug ?: 'all';
$filename = 'schedule-' . preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug))
          . '-' . date('Ymd') . '.png';
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Image-Cache: MISS');

imagepng($img, null, 6);
$img = null;  // GDImage released (imagedestroy deprecated in PHP 8.x)
