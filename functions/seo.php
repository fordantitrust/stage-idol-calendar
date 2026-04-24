<?php
/**
 * SEO Helper Functions (v6.5.0)
 *
 * Zero-DB-query helpers for rendering meta tags, Open Graph, Twitter Cards,
 * canonical URLs, noindex directives, and JSON-LD structured data.
 *
 * All functions are CLI-safe: seo_full_url() returns '' in CLI context so
 * that canonical/og:url tags are simply omitted during test runs.
 */

/**
 * Build an absolute URL from a path.
 *
 * Returns '' in CLI context (prevents broken tags in automated tests).
 */
function seo_full_url(string $path): string {
    if (php_sapi_name() === 'cli') {
        return '';
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme   = $isHttps ? 'https' : 'http';
    $host     = get_safe_host();
    $basePath = get_base_path();

    if ($path !== '' && $path[0] !== '/') {
        $path = '/' . $path;
    }

    return $scheme . '://' . $host . $basePath . $path;
}

/**
 * Truncate text to $max UTF-8 characters for use in meta descriptions.
 *
 * Strips HTML tags, normalises whitespace, snaps to last word boundary
 * (with a Thai-text guard so it never snaps back more than 50% of $max),
 * and appends '…' when truncation occurs.
 */
function seo_truncate(string $text, int $max = 155): string {
    $text = strip_tags($text);
    $text = trim(preg_replace('/\s+/u', ' ', $text));

    if (mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
    }

    $truncated = mb_substr($text, 0, $max, 'UTF-8');
    $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');

    if ($lastSpace !== false && $lastSpace > (int)($max * 0.5)) {
        $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
    }

    return rtrim($truncated) . '…';
}

/**
 * Render SEO meta tags inside <head>.
 *
 * Accepted $opts keys:
 *   title       string  Page title for og:title / twitter:title
 *   description string  Meta description (truncated to 155 chars)
 *   canonical   string  Absolute URL for <link rel="canonical"> and og:url
 *   noindex     bool    Emit <meta name="robots" content="noindex, nofollow">
 *   og_type     string  Open Graph type (default: 'website')
 *   og_image    string  Absolute image URL (triggers summary_large_image card)
 *   site_title  string  Override get_site_title() for og:site_name
 */
function seo_render_meta(array $opts = []): void {
    $title       = $opts['title']       ?? '';
    $description = $opts['description'] ?? '';
    $canonical   = $opts['canonical']   ?? '';
    $noindex     = (bool)($opts['noindex'] ?? false);
    $ogType      = $opts['og_type']     ?? 'website';
    $ogImage     = $opts['og_image']    ?? '';
    $siteTitle   = $opts['site_title']  ?? get_site_title();

    $ogTitle      = $title ?: $siteTitle;
    $truncatedDesc = $description !== '' ? seo_truncate($description) : '';

    $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    // ── robots ────────────────────────────────────────────────────────────────
    if ($noindex) {
        echo '    <meta name="robots" content="noindex, nofollow">' . "\n";
    }

    // ── description ───────────────────────────────────────────────────────────
    if ($truncatedDesc !== '') {
        echo '    <meta name="description" content="' . $esc($truncatedDesc) . '">' . "\n";
    }

    // ── canonical ─────────────────────────────────────────────────────────────
    if ($canonical !== '') {
        echo '    <link rel="canonical" href="' . $esc($canonical) . '">' . "\n";
    }

    // ── Open Graph ────────────────────────────────────────────────────────────
    echo '    <meta property="og:type" content="'      . $esc($ogType)    . '">' . "\n";
    echo '    <meta property="og:title" content="'     . $esc($ogTitle)   . '">' . "\n";
    echo '    <meta property="og:site_name" content="' . $esc($siteTitle) . '">' . "\n";
    echo '    <meta property="og:locale" content="th_TH">' . "\n";

    if ($canonical !== '') {
        echo '    <meta property="og:url" content="'   . $esc($canonical) . '">' . "\n";
    }
    if ($truncatedDesc !== '') {
        echo '    <meta property="og:description" content="' . $esc($truncatedDesc) . '">' . "\n";
    }
    if ($ogImage !== '') {
        echo '    <meta property="og:image" content="'        . $esc($ogImage) . '">' . "\n";
        echo '    <meta property="og:image:width" content="1200">'  . "\n";
        echo '    <meta property="og:image:height" content="630">'  . "\n";
    }

    // ── Twitter Card ──────────────────────────────────────────────────────────
    $twitterCard = $ogImage !== '' ? 'summary_large_image' : 'summary';
    echo '    <meta name="twitter:card" content="'    . $esc($twitterCard) . '">' . "\n";
    echo '    <meta name="twitter:title" content="'   . $esc($ogTitle)     . '">' . "\n";
    if ($truncatedDesc !== '') {
        echo '    <meta name="twitter:description" content="' . $esc($truncatedDesc) . '">' . "\n";
    }
    if ($ogImage !== '') {
        echo '    <meta name="twitter:image" content="' . $esc($ogImage) . '">' . "\n";
    }
}

/**
 * Render a JSON-LD structured data block inside <head>.
 *
 * Uses JSON_UNESCAPED_UNICODE so Thai text is not \uXXXX-escaped.
 * Emits nothing for an empty array.
 */
function seo_render_json_ld(array $schema): void {
    if (empty($schema)) {
        return;
    }
    echo '    <script type="application/ld+json">' . "\n";
    echo json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n" . '    </script>' . "\n";
}
