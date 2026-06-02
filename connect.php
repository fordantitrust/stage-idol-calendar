<?php
/**
 * Favorites Connect Page (/connect)
 * Scans a QR code from another device to restore fav_slug in this browser's localStorage.
 */
require_once 'config.php';
send_security_headers();
// Override camera permission — this page needs getUserMedia for QR scanning
header('Permissions-Policy: camera=(self)', true);

$siteTitle     = get_site_title();
$siteTheme     = get_site_theme();
$headerCoverBg = get_header_cover_bg();
$basePath      = get_base_path();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#E91E63">
    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <title><?= htmlspecialchars($siteTitle) ?> — เชื่อมต่อ Favorites</title>
    <?php seo_render_meta(['noindex' => true]); ?>
    <?php if (defined('GOOGLE_ANALYTICS_ID') && GOOGLE_ANALYTICS_ID): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars(GOOGLE_ANALYTICS_ID) ?>"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?= htmlspecialchars(GOOGLE_ANALYTICS_ID) ?>');
    </script>
    <?php endif; ?>
    <?php if (defined('GOOGLE_ADS_CLIENT') && GOOGLE_ADS_CLIENT): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars(GOOGLE_ADS_CLIENT, ENT_QUOTES, 'UTF-8') ?>"
         crossorigin="anonymous"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= asset_url('styles/common.css') ?>">
    <?php if ($siteTheme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?= asset_url('styles/themes/' . $siteTheme . '.css') ?>">
    <?php endif; ?>
    <style>
        .connect-wrap {
            max-width: 480px;
            margin: 0 auto;
            padding: 16px 16px 40px;
        }

        /* ── State cards ─────────────────────────────────────────────────── */
        .connect-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(233,30,99,.08);
        }
        .connect-card h2 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--sakura-deep, #C2185B);
            margin: 0 0 10px;
        }
        .connect-card p {
            font-size: .9rem;
            color: #555;
            margin: 0 0 14px;
            line-height: 1.6;
        }

        /* ── Viewfinder ──────────────────────────────────────────────────── */
        .viewfinder-wrap {
            position: relative;
            width: 100%;
            max-width: 360px;
            margin: 0 auto 14px;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            aspect-ratio: 1 / 1;
        }
        #qrVideo {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        /* Aiming frame overlay */
        .viewfinder-frame {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .viewfinder-frame::before,
        .viewfinder-frame::after,
        .vf-bl::before,
        .vf-br::before {
            content: '';
            position: absolute;
            width: 28px; height: 28px;
            border-color: var(--sakura-medium, #F48FB1);
            border-style: solid;
        }
        .viewfinder-frame::before  { top: 12px; left: 12px;  border-width: 3px 0 0 3px; border-radius: 4px 0 0 0; }
        .viewfinder-frame::after   { top: 12px; right: 12px; border-width: 3px 3px 0 0; border-radius: 0 4px 0 0; }
        .vf-bl::before { bottom: 12px; left: 12px;  border-width: 0 0 3px 3px; border-radius: 0 0 0 4px; }
        .vf-br::before { bottom: 12px; right: 12px; border-width: 0 3px 3px 0; border-radius: 0 0 4px 0; }

        /* Scan line animation */
        .scan-line {
            position: absolute;
            left: 12px; right: 12px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--sakura-medium, #F48FB1), transparent);
            animation: scanMove 2s linear infinite;
        }
        @keyframes scanMove {
            0%   { top: 14px; opacity: 1; }
            90%  { opacity: 1; }
            100% { top: calc(100% - 14px); opacity: 0; }
        }

        /* ── Status message ──────────────────────────────────────────────── */
        #scanStatus {
            text-align: center;
            font-size: .88rem;
            color: #777;
            min-height: 1.4em;
            margin-bottom: 12px;
        }
        #scanStatus.scanning { color: var(--sakura-dark, #E91E63); font-weight: 600; }
        #scanStatus.success  { color: #2e7d32; font-weight: 700; font-size: 1rem; }
        #scanStatus.error    { color: #c62828; }

        /* ── Buttons ─────────────────────────────────────────────────────── */
        .connect-btn-row {
            display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;
        }
        #startScanBtn {
            background: var(--sakura-gradient, linear-gradient(135deg,#FFB7C5,#E91E63));
            color: #fff; border: none;
            padding: 11px 22px; border-radius: 22px;
            font-size: .95rem; font-weight: 700;
            cursor: pointer; box-shadow: 0 2px 8px rgba(233,30,99,.25);
        }
        #startScanBtn:hover { opacity: .9; }
        #stopScanBtn {
            display: none;
            background: #fff; color: #c62828;
            border: 1.5px solid #ef5350;
            padding: 10px 20px; border-radius: 22px;
            font-size: .9rem; cursor: pointer;
        }
        #stopScanBtn:hover { background: #ffebee; }

        /* ── Paste URL fallback ──────────────────────────────────────────── */
        .paste-section {
            margin-top: 8px;
            border-top: 1px solid #f0d0db;
            padding-top: 14px;
        }
        .paste-section p { margin: 0 0 8px; }
        .paste-url-row {
            display: flex; gap: 8px;
        }
        #pasteUrlInput {
            flex: 1; min-width: 0;
            padding: 9px 12px; border: 1.5px solid #ddd;
            border-radius: 8px; font-size: .9rem;
        }
        #pasteUrlInput:focus {
            outline: none;
            border-color: var(--sakura-medium, #F48FB1);
        }
        #pasteUrlBtn {
            background: var(--sakura-medium, #F48FB1);
            color: #fff; border: none;
            padding: 9px 16px; border-radius: 8px;
            font-size: .9rem; cursor: pointer; white-space: nowrap;
        }
        #pasteUrlBtn:hover { opacity: .85; }
        #pasteUrlError {
            font-size: .82rem; color: #c62828;
            margin: 6px 0 0; display: none;
        }
    </style>
</head>
<body>
<div class="container">
    <header<?php if ($headerCoverBg): ?> class="has-site-cover" style="--header-cover-url: url('<?= htmlspecialchars($basePath . '/' . $headerCoverBg, ENT_QUOTES, 'UTF-8') ?>')"<?php endif; ?>>
        <div class="header-top-left">
            <a href="<?= $basePath ?>/" class="home-icon-btn" data-i18n-title="nav.home" title="หน้าแรก">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 2L2 9h2v9h5v-5h2v5h5V9h2L10 2z" fill="currentColor"/>
                </svg>
            </a>
            <a href="<?= $basePath ?>/contact" class="home-icon-btn" data-i18n-title="nav.contact" title="ติดต่อเรา">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <a href="<?= $basePath ?>/how-to-use" class="home-icon-btn" data-i18n-title="nav.howToUse" title="วิธีการใช้งาน">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>
        <div class="language-switcher">
            <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
            <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
            <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
        </div>
        <h1 data-i18n="header.title"><?= htmlspecialchars($siteTitle) ?></h1>
        <nav class="header-nav">
            <a href="<?= $basePath ?>/artists" class="header-nav-link" data-i18n="nav.artists">🎤 ศิลปิน</a>
            <a href="<?= $basePath ?>/credits" class="header-nav-link" data-i18n="footer.credits">📋 แหล่งข้อมูลอ้างอิง</a>
        </nav>
    </header>

    <main class="connect-wrap">

        <!-- Scanner Card -->
        <div class="connect-card">
            <h2 data-i18n="connect.heading">📷 สแกน QR Code เพื่อเชื่อมต่อ Favorites</h2>

            <div class="viewfinder-wrap" id="viewfinderWrap" style="display:none">
                <video id="qrVideo" autoplay playsinline muted></video>
                <div class="viewfinder-frame">
                    <div class="vf-bl"></div>
                    <div class="vf-br"></div>
                    <div class="scan-line"></div>
                </div>
            </div>

            <p id="scanStatus" data-i18n="connect.tapStart">กดปุ่มด้านล่างเพื่อเปิดกล้อง</p>

            <div class="connect-btn-row">
                <button id="startScanBtn" onclick="startScan()" data-i18n="connect.start">📷 เปิดกล้องสแกน</button>
                <button id="stopScanBtn"  onclick="stopScan()"  data-i18n="connect.stop">■ หยุดสแกน</button>
            </div>

            <!-- Paste URL fallback -->
            <div class="paste-section">
                <p class="fav-qr-desc" data-i18n="connect.pasteDesc">หรือวาง URL ของหน้า Favorites โดยตรง:</p>
                <div class="paste-url-row">
                    <input type="url" id="pasteUrlInput" placeholder="https://…/my/…"
                           data-i18n-placeholder="connect.pastePlaceholder"
                           oninput="clearPasteError()" onkeydown="if(event.key==='Enter')applyPasteUrl()">
                    <button id="pasteUrlBtn" onclick="applyPasteUrl()" data-i18n="connect.pasteBtn">เชื่อมต่อ</button>
                </div>
                <p id="pasteUrlError" data-i18n="connect.pasteError">URL ไม่ถูกต้อง — ต้องเป็นลิงก์ /my/{slug}</p>
            </div>
        </div>

    </main>

    <footer>
        <div class="footer-text">
            <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
            <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
            <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a> <span class="footer-version">v<?= APP_VERSION ?></span></p>
        </div>
    </footer>
</div>

<script>
window.BASE_PATH  = <?= json_encode($basePath) ?>;
window.SITE_TITLE = <?= json_encode($siteTitle) ?>;
</script>
<script src="<?= $basePath ?>/js/translations.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= $basePath ?>/js/common.js?v=<?= APP_VERSION ?>"></script>
<script>
// ── Slug pattern (UUID v7 + HMAC 12 hex) ─────────────────────────────────────
const SLUG_RE = /[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}-[0-9a-f]{12}/;

let videoStream = null;
let scanRafId   = null;
let jsQRLoaded  = false;

// ── Camera scanner ────────────────────────────────────────────────────────────
async function startScan() {
    setStatus('loading', t('connect.opening') || '⏳ กำลังเปิดกล้อง…');
    document.getElementById('startScanBtn').style.display = 'none';
    document.getElementById('stopScanBtn').style.display  = 'inline-block';

    // 1. Load jsQR lazily (try multiple CDNs)
    if (!jsQRLoaded) {
        const cdns = [
            'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js',
            'https://unpkg.com/jsqr@1.4.0/dist/jsQR.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.min.js',
        ];
        let loaded = false;
        for (const url of cdns) {
            try {
                await loadScript(url);
                loaded = true;
                break;
            } catch { /* try next */ }
        }
        if (!loaded) {
            setStatus('error', t('connect.libError') || '❌ โหลด library ไม่สำเร็จ');
            resetButtons();
            return;
        }
        jsQRLoaded = true;
    }

    // 2. Request camera (back camera preferred)
    try {
        videoStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
        });
    } catch (err) {
        const msg = err.name === 'NotAllowedError'
            ? (t('connect.camDenied')  || '❌ ไม่ได้รับสิทธิ์กล้อง กรุณาอนุญาตในการตั้งค่า')
            : (t('connect.camError')   || '❌ เปิดกล้องไม่ได้');
        setStatus('error', msg);
        resetButtons();
        return;
    }

    const video = document.getElementById('qrVideo');
    video.srcObject = videoStream;
    document.getElementById('viewfinderWrap').style.display = 'block';
    await video.play();

    setStatus('scanning', t('connect.scanning') || '🔍 กำลังสแกน…');
    scanLoop();
}

function scanLoop() {
    const video  = document.getElementById('qrVideo');
    const canvas = document.createElement('canvas');

    function tick() {
        if (!videoStream) return; // stopped
        if (video.readyState < video.HAVE_ENOUGH_DATA) {
            scanRafId = requestAnimationFrame(tick);
            return;
        }
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

        try {
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert',
            });
            if (code && code.data) {
                const slug = extractSlug(code.data);
                if (slug) { handleSlug(slug); return; }
            }
        } catch { /* jsQR error — keep scanning */ }

        scanRafId = requestAnimationFrame(tick);
    }
    scanRafId = requestAnimationFrame(tick);
}

function stopScan() {
    if (scanRafId) cancelAnimationFrame(scanRafId);
    if (videoStream) { videoStream.getTracks().forEach(t => t.stop()); videoStream = null; }
    document.getElementById('viewfinderWrap').style.display = 'none';
    setStatus('', t('connect.tapStart') || 'กดปุ่มด้านล่างเพื่อเปิดกล้อง');
    resetButtons();
}

// ── Paste URL fallback ────────────────────────────────────────────────────────
function applyPasteUrl() {
    const val  = document.getElementById('pasteUrlInput').value.trim();
    const slug = extractSlug(val);
    if (!slug) {
        document.getElementById('pasteUrlError').style.display = 'block';
        return;
    }
    handleSlug(slug);
}

function clearPasteError() {
    document.getElementById('pasteUrlError').style.display = 'none';
}

// ── Core: extract slug from URL or raw slug string ───────────────────────────
function extractSlug(text) {
    const m = text.match(SLUG_RE);
    return m ? m[0] : null;
}

// ── On successful scan ────────────────────────────────────────────────────────
async function handleSlug(slug) {
    stopScan();
    setStatus('scanning', t('connect.verifying') || '⏳ กำลังตรวจสอบ…');

    // Validate against API
    const base = window.BASE_PATH || '';
    try {
        const res = await fetch(base + '/api/favorites?action=get&slug=' + encodeURIComponent(slug));
        if (!res.ok) {
            setStatus('error', t('connect.invalidSlug') || '❌ Favorites ไม่พบหรือหมดอายุแล้ว');
            resetButtons();
            return;
        }
    } catch {
        // Network error — save anyway (offline-tolerant)
    }

    localStorage.setItem('fav_slug', slug);
    setStatus('success', t('connect.success') || '✅ เชื่อมต่อสำเร็จ! กำลังพาไปหน้า Favorites…');

    setTimeout(function () {
        window.location.href = base + '/my/' + encodeURIComponent(slug);
    }, 1200);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function setStatus(cls, msg) {
    const el = document.getElementById('scanStatus');
    el.className = cls;
    el.textContent = msg;
}

function resetButtons() {
    document.getElementById('startScanBtn').style.display = 'inline-block';
    document.getElementById('stopScanBtn').style.display  = 'none';
}

function t(key) {
    return (typeof translations !== 'undefined' && translations[currentLang])
        ? translations[currentLang][key] : '';
}

function loadScript(src) {
    return new Promise(function (resolve, reject) {
        const s = document.createElement('script');
        s.src = src; s.onload = resolve; s.onerror = reject;
        document.head.appendChild(s);
    });
}
</script>
</body>
</html>
