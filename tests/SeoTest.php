<?php
/**
 * SEO Helper Tests (v6.5.0)
 *
 * Covers: seo_full_url(), seo_truncate(), seo_render_meta(), seo_render_json_ld(),
 * JSON-LD schema key validation, and file/function existence checks.
 *
 * All function names use the testSeo* prefix to avoid collisions with the
 * 3,666 existing test functions that run in the same PHP process.
 */

if (!defined('APP_VERSION')) {
    require_once dirname(__DIR__) . '/config.php';
}

// ── File & function existence ─────────────────────────────────────────────────

function testSeoFileExists($t) {
    $t->assertTrue(
        file_exists(dirname(__DIR__) . '/functions/seo.php'),
        'functions/seo.php must exist'
    );
}

function testSeoConfigRequiresSeoPhp($t) {
    $src = file_get_contents(dirname(__DIR__) . '/config.php');
    $t->assertTrue(
        strpos($src, 'functions/seo.php') !== false,
        'config.php must require functions/seo.php'
    );
}

function testSeoFunctionSeoFullUrlExists($t) {
    $t->assertTrue(function_exists('seo_full_url'), 'seo_full_url() must be defined');
}

function testSeoFunctionSeoTruncateExists($t) {
    $t->assertTrue(function_exists('seo_truncate'), 'seo_truncate() must be defined');
}

function testSeoFunctionSeoRenderMetaExists($t) {
    $t->assertTrue(function_exists('seo_render_meta'), 'seo_render_meta() must be defined');
}

function testSeoFunctionSeoRenderJsonLdExists($t) {
    $t->assertTrue(function_exists('seo_render_json_ld'), 'seo_render_json_ld() must be defined');
}

// ── seo_full_url() ────────────────────────────────────────────────────────────

function testSeoFullUrlReturnsEmptyInCli($t) {
    $result = seo_full_url('/test');
    $t->assertEquals('', $result, 'seo_full_url() must return empty string in CLI context');
}

function testSeoFullUrlReturnsString($t) {
    $t->assertTrue(is_string(seo_full_url('/')), 'seo_full_url() must return a string');
}

function testSeoFullUrlEmptyPathReturnsString($t) {
    $t->assertTrue(is_string(seo_full_url('')), 'seo_full_url() must handle empty path');
}

function testSeoFullUrlPathWithoutLeadingSlash($t) {
    $t->assertTrue(
        is_string(seo_full_url('artists')),
        'seo_full_url() must handle path without leading slash'
    );
}

function testSeoFullUrlCliContractNoScheme($t) {
    // In CLI context the function returns '' — no http/https scheme
    $result = seo_full_url('/artists');
    $t->assertTrue(
        $result === '' || strpos($result, '://') !== false,
        'seo_full_url() must return empty string in CLI or absolute URL in web context'
    );
}

// ── seo_truncate() ────────────────────────────────────────────────────────────

function testSeoTruncateShortTextUnchanged($t) {
    $input  = 'สวัสดี นี่คือข้อความสั้น';
    $result = seo_truncate($input, 155);
    $t->assertEquals($input, $result, 'Short text must pass through unchanged');
}

function testSeoTruncateLongTextIsTruncated($t) {
    $input  = str_repeat('abcde ', 40); // 240 chars
    $result = seo_truncate($input, 155);
    $t->assertTrue(
        mb_strlen($result, 'UTF-8') <= 158,
        'Truncated text must be at most max + len(ellipsis) chars'
    );
}

function testSeoTruncateLongTextHasEllipsis($t) {
    $input  = str_repeat('abcde ', 40);
    $result = seo_truncate($input, 155);
    $t->assertContains('…', $result, 'Truncated text must end with ellipsis');
}

function testSeoTruncateStripsHtmlTags($t) {
    $input  = '<strong>Title</strong> — <em>subtitle</em>';
    $result = seo_truncate($input, 155);
    $t->assertFalse(
        strpos($result, '<strong>') !== false,
        'HTML tags must be stripped'
    );
    $t->assertContains('Title', $result, 'Text content must be preserved after stripping tags');
}

function testSeoTruncateNoEllipsisForShortText($t) {
    $result = seo_truncate('Hello world', 155);
    $t->assertFalse(
        strpos($result, '…') !== false,
        'Must not append ellipsis when text is within limit'
    );
}

function testSeoTruncateRespectsMaxParam($t) {
    $input  = str_repeat('a', 300);
    $result = seo_truncate($input, 100);
    $t->assertTrue(
        mb_strlen($result, 'UTF-8') <= 104,
        'Result length must not significantly exceed max'
    );
}

function testSeoTruncateExactMaxNotTruncated($t) {
    $input  = str_repeat('x', 155);
    $result = seo_truncate($input, 155);
    $t->assertFalse(strpos($result, '…') !== false, 'Exact-length text must not be truncated');
}

function testSeoTruncateNormalisesWhitespace($t) {
    $input  = "hello   \t  world";
    $result = seo_truncate($input, 155);
    $t->assertFalse(strpos($result, '   ') !== false, 'Multiple spaces must be collapsed');
}

// ── seo_render_meta() — output buffering ─────────────────────────────────────

function testSeoRenderMetaDescriptionTag($t) {
    ob_start();
    seo_render_meta(['description' => 'My test description']);
    $out = ob_get_clean();
    $t->assertContains('name="description"', $out, 'Must emit description meta tag');
    $t->assertContains('My test description', $out, 'Must include description text');
}

function testSeoRenderMetaCanonicalTag($t) {
    ob_start();
    seo_render_meta(['canonical' => 'https://example.com/artists']);
    $out = ob_get_clean();
    $t->assertContains('rel="canonical"', $out, 'Must emit canonical link tag');
    $t->assertContains('https://example.com/artists', $out, 'Canonical must include the URL');
}

function testSeoRenderMetaNoCanonicalWhenEmpty($t) {
    ob_start();
    seo_render_meta(['description' => 'test']);
    $out = ob_get_clean();
    $t->assertFalse(
        strpos($out, 'rel="canonical"') !== false,
        'Must not emit canonical when not provided'
    );
}

function testSeoRenderMetaOgTypeCustom($t) {
    ob_start();
    seo_render_meta(['og_type' => 'profile']);
    $out = ob_get_clean();
    $t->assertContains('og:type', $out, 'Must emit og:type');
    $t->assertContains('profile', $out, 'og:type value must be profile');
}

function testSeoRenderMetaOgTypeDefaultsToWebsite($t) {
    ob_start();
    seo_render_meta([]);
    $out = ob_get_clean();
    $t->assertContains('og:type', $out, 'og:type must always be emitted');
    $t->assertContains('website', $out, 'og:type must default to website');
}

function testSeoRenderMetaOgTitle($t) {
    ob_start();
    seo_render_meta(['title' => 'My Page Title']);
    $out = ob_get_clean();
    $t->assertContains('og:title', $out, 'Must emit og:title');
    $t->assertContains('My Page Title', $out, 'og:title must use provided title');
}

function testSeoRenderMetaOgDescription($t) {
    ob_start();
    seo_render_meta(['description' => 'My og description']);
    $out = ob_get_clean();
    $t->assertContains('og:description', $out, 'Must emit og:description');
    $t->assertContains('My og description', $out, 'og:description must include text');
}

function testSeoRenderMetaOgUrl($t) {
    ob_start();
    seo_render_meta(['canonical' => 'https://example.com/test']);
    $out = ob_get_clean();
    $t->assertContains('og:url', $out, 'Must emit og:url when canonical is set');
    $t->assertContains('https://example.com/test', $out, 'og:url must match canonical');
}

function testSeoRenderMetaOgImagePresent($t) {
    ob_start();
    seo_render_meta(['og_image' => 'https://example.com/img/cover.jpg']);
    $out = ob_get_clean();
    $t->assertContains('og:image"', $out, 'Must emit og:image when provided');
    $t->assertContains('https://example.com/img/cover.jpg', $out, 'og:image must include URL');
}

function testSeoRenderMetaOgImageWidth($t) {
    ob_start();
    seo_render_meta(['og_image' => 'https://example.com/img/cover.jpg']);
    $out = ob_get_clean();
    $t->assertContains('og:image:width', $out, 'Must emit og:image:width');
    $t->assertContains('1200', $out, 'og:image:width must be 1200');
}

function testSeoRenderMetaOgImageHeight($t) {
    ob_start();
    seo_render_meta(['og_image' => 'https://example.com/img/cover.jpg']);
    $out = ob_get_clean();
    $t->assertContains('og:image:height', $out, 'Must emit og:image:height');
    $t->assertContains('630', $out, 'og:image:height must be 630');
}

function testSeoRenderMetaOgImageAbsent($t) {
    ob_start();
    seo_render_meta(['description' => 'no image here']);
    $out = ob_get_clean();
    $t->assertFalse(
        strpos($out, 'og:image') !== false,
        'Must not emit og:image when no image provided'
    );
}

function testSeoRenderMetaOgSiteName($t) {
    ob_start();
    seo_render_meta(['site_title' => 'My Idol Site']);
    $out = ob_get_clean();
    $t->assertContains('og:site_name', $out, 'Must emit og:site_name');
    $t->assertContains('My Idol Site', $out, 'og:site_name must use site_title');
}

function testSeoRenderMetaOgLocaleIsTh($t) {
    ob_start();
    seo_render_meta([]);
    $out = ob_get_clean();
    $t->assertContains('og:locale', $out, 'Must emit og:locale');
    $t->assertContains('th_TH', $out, 'og:locale must be th_TH');
}

function testSeoRenderMetaTwitterCardSummaryLargeImage($t) {
    ob_start();
    seo_render_meta(['og_image' => 'https://example.com/img.jpg']);
    $out = ob_get_clean();
    $t->assertContains('twitter:card', $out, 'Must emit twitter:card');
    $t->assertContains('summary_large_image', $out, 'twitter:card must be summary_large_image when image present');
}

function testSeoRenderMetaTwitterCardSummaryWhenNoImage($t) {
    ob_start();
    seo_render_meta(['description' => 'no image']);
    $out = ob_get_clean();
    $t->assertContains('twitter:card', $out, 'Must emit twitter:card');
    $t->assertFalse(
        strpos($out, 'summary_large_image') !== false,
        'twitter:card must NOT be summary_large_image when no image'
    );
    $t->assertContains('summary', $out, 'twitter:card must be summary when no image');
}

function testSeoRenderMetaTwitterTitle($t) {
    ob_start();
    seo_render_meta(['title' => 'Twitter Title Test']);
    $out = ob_get_clean();
    $t->assertContains('twitter:title', $out, 'Must emit twitter:title');
    $t->assertContains('Twitter Title Test', $out, 'twitter:title must include title text');
}

function testSeoRenderMetaTwitterDescription($t) {
    ob_start();
    seo_render_meta(['description' => 'Twitter desc test']);
    $out = ob_get_clean();
    $t->assertContains('twitter:description', $out, 'Must emit twitter:description');
    $t->assertContains('Twitter desc test', $out, 'twitter:description must include text');
}

function testSeoRenderMetaTwitterImagePresent($t) {
    ob_start();
    seo_render_meta(['og_image' => 'https://example.com/tw.jpg']);
    $out = ob_get_clean();
    $t->assertContains('twitter:image', $out, 'Must emit twitter:image when og_image provided');
}

function testSeoRenderMetaTwitterImageAbsent($t) {
    ob_start();
    seo_render_meta(['description' => 'no image']);
    $out = ob_get_clean();
    $t->assertFalse(
        strpos($out, 'twitter:image') !== false,
        'Must not emit twitter:image when no image provided'
    );
}

function testSeoRenderMetaNoindexWhenFlagSet($t) {
    ob_start();
    seo_render_meta(['noindex' => true]);
    $out = ob_get_clean();
    $t->assertContains('name="robots"', $out, 'Must emit robots meta when noindex=true');
    $t->assertContains('noindex', $out, 'robots meta must contain noindex');
    $t->assertContains('nofollow', $out, 'robots meta must contain nofollow');
}

function testSeoRenderMetaNoRobotsTagByDefault($t) {
    ob_start();
    seo_render_meta(['description' => 'public page']);
    $out = ob_get_clean();
    $t->assertFalse(
        strpos($out, 'name="robots"') !== false,
        'Must not emit robots meta when noindex not set'
    );
}

function testSeoRenderMetaEscapesHtml($t) {
    ob_start();
    seo_render_meta(['title' => '<script>alert(1)</script>', 'description' => '"test"']);
    $out = ob_get_clean();
    $t->assertFalse(strpos($out, '<script>') !== false, 'Must escape <script> in title');
    $t->assertContains('&lt;script&gt;', $out, 'Title XSS must be HTML-escaped');
}

// ── seo_render_json_ld() ─────────────────────────────────────────────────────

function testSeoRenderJsonLdEmitsScriptTag($t) {
    ob_start();
    seo_render_json_ld(['@type' => 'WebSite', '@context' => 'https://schema.org', 'name' => 'Test']);
    $out = ob_get_clean();
    $t->assertContains('<script type="application/ld+json">', $out, 'Must emit script tag with correct type');
    $t->assertContains('</script>', $out, 'Must close script tag');
}

function testSeoRenderJsonLdOutputIsValidJson($t) {
    ob_start();
    seo_render_json_ld(['@type' => 'WebSite', '@context' => 'https://schema.org', 'name' => 'Test']);
    $out = ob_get_clean();
    preg_match('/<script[^>]*>(.*?)<\/script>/s', $out, $m);
    $t->assertNotEmpty($m, 'Must have content between script tags');
    $decoded = json_decode(trim($m[1]), true);
    $t->assertNotNull($decoded, 'JSON-LD must be valid JSON: ' . json_last_error_msg());
}

function testSeoRenderJsonLdUnicodeNotEscaped($t) {
    ob_start();
    seo_render_json_ld(['name' => 'ศิลปิน', '@context' => 'https://schema.org']);
    $out = ob_get_clean();
    // If JSON_UNESCAPED_UNICODE is active, the literal Thai string appears in output.
    // If it were missing, json_encode would emit ศิล... instead.
    $t->assertContains('ศิลปิน', $out, 'Thai Unicode must appear literally — JSON_UNESCAPED_UNICODE must be set');
    // The JSON \uXXXX escaped form for ศ must NOT appear in the output
    $t->assertFalse(
        strpos($out, '\u0e28') !== false,
        'Must not contain \\u0e28 Unicode escape — JSON_UNESCAPED_UNICODE must be active'
    );
}

function testSeoRenderJsonLdEmptyArrayEmitsNothing($t) {
    ob_start();
    seo_render_json_ld([]);
    $out = ob_get_clean();
    $t->assertEquals('', $out, 'Empty array must produce no output');
}

// ── JSON-LD schema key validation ─────────────────────────────────────────────

function testSeoWebsiteSchemaContext($t) {
    ob_start();
    seo_render_json_ld(['@context' => 'https://schema.org', '@type' => 'WebSite',
                        'name' => 'Test', 'url' => 'https://example.com']);
    $out  = ob_get_clean();
    $data = json_decode(strip_tags($out), true);
    $t->assertEquals('https://schema.org', $data['@context'], '@context must be https://schema.org');
}

function testSeoWebsiteSchemaType($t) {
    ob_start();
    seo_render_json_ld(['@context' => 'https://schema.org', '@type' => 'WebSite',
                        'name' => 'Test', 'url' => 'https://example.com']);
    $out  = ob_get_clean();
    $data = json_decode(strip_tags($out), true);
    $t->assertEquals('WebSite', $data['@type'], '@type must be WebSite');
}

function testSeoEventSchemaStartDate($t) {
    ob_start();
    seo_render_json_ld(['@context' => 'https://schema.org', '@type' => 'Event',
                        'name' => 'Test Event', 'startDate' => '2026-05-01',
                        'endDate' => '2026-05-03',
                        'eventStatus' => 'https://schema.org/EventScheduled']);
    $out  = ob_get_clean();
    $data = json_decode(strip_tags($out), true);
    $t->assertEquals('2026-05-01', $data['startDate'], 'Event schema must have correct startDate');
}

function testSeoEventSchemaEndDate($t) {
    ob_start();
    seo_render_json_ld(['@context' => 'https://schema.org', '@type' => 'Event',
                        'name' => 'Test Event', 'startDate' => '2026-05-01',
                        'endDate' => '2026-05-03',
                        'eventStatus' => 'https://schema.org/EventScheduled']);
    $out  = ob_get_clean();
    $data = json_decode(strip_tags($out), true);
    $t->assertEquals('2026-05-03', $data['endDate'], 'Event schema must have correct endDate');
}

function testSeoEventSchemaEventStatus($t) {
    ob_start();
    seo_render_json_ld(['@context' => 'https://schema.org', '@type' => 'Event',
                        'name' => 'Test', 'startDate' => '2026-05-01',
                        'eventStatus' => 'https://schema.org/EventScheduled']);
    $out  = ob_get_clean();
    $data = json_decode(strip_tags($out), true);
    $t->assertEquals(
        'https://schema.org/EventScheduled',
        $data['eventStatus'],
        'eventStatus must be EventScheduled'
    );
}

// ── Page-level SEO insertion checks ──────────────────────────────────────────

function testSeoIndexPhpHasSeoRenderMeta($t) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $t->assertTrue(strpos($src, 'seo_render_meta(') !== false, 'index.php must call seo_render_meta()');
}

function testSeoIndexPhpHasSeoRenderJsonLd($t) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $t->assertTrue(strpos($src, 'seo_render_json_ld(') !== false, 'index.php must call seo_render_json_ld()');
}

function testSeoArtistPhpHasSeoRenderMeta($t) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $t->assertTrue(strpos($src, 'seo_render_meta(') !== false, 'artist.php must call seo_render_meta()');
}

function testSeoArtistPhpHasBreadcrumbList($t) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $t->assertTrue(strpos($src, 'BreadcrumbList') !== false, 'artist.php must include BreadcrumbList schema');
}

function testSeoArtistsPhpHasSeoRenderMeta($t) {
    $src = file_get_contents(dirname(__DIR__) . '/artists.php');
    $t->assertTrue(strpos($src, 'seo_render_meta(') !== false, 'artists.php must call seo_render_meta()');
}

function testSeoArtistsPhpHasItemList($t) {
    $src = file_get_contents(dirname(__DIR__) . '/artists.php');
    $t->assertTrue(strpos($src, 'ItemList') !== false, 'artists.php must include ItemList schema');
}

function testSeoCreditsPhpHasSeoRenderMeta($t) {
    $src = file_get_contents(dirname(__DIR__) . '/credits.php');
    $t->assertTrue(strpos($src, 'seo_render_meta(') !== false, 'credits.php must call seo_render_meta()');
}

function testSeoContactPhpHasSeoRenderMeta($t) {
    $src = file_get_contents(dirname(__DIR__) . '/contact.php');
    $t->assertTrue(strpos($src, 'seo_render_meta(') !== false, 'contact.php must call seo_render_meta()');
}

function testSeoHowToUsePhpHasSeoRenderMeta($t) {
    $src = file_get_contents(dirname(__DIR__) . '/how-to-use.php');
    $t->assertTrue(strpos($src, 'seo_render_meta(') !== false, 'how-to-use.php must call seo_render_meta()');
}

function testSeoPastEventsPhpHasSeoRenderMeta($t) {
    $src = file_get_contents(dirname(__DIR__) . '/past-events.php');
    $t->assertTrue(strpos($src, 'seo_render_meta(') !== false, 'past-events.php must call seo_render_meta()');
}

function testSeoMyPhpHasNoindex($t) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $t->assertTrue(strpos($src, "'noindex' => true") !== false, 'my.php must have noindex => true');
}

function testSeoMyFavoritesPhpHasNoindex($t) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $t->assertTrue(strpos($src, "'noindex' => true") !== false, 'my-favorites.php must have noindex => true');
}
