<?php
/**
 * Setup & Installation Checklist
 * Idol Stage Timetable
 *
 * หน้าตรวจสอบสถานะการติดตั้งและช่วยทำ setup ก่อนเริ่มใช้งาน
 * เข้าถึงได้ที่: /setup หรือ /setup.php
 */

// โหลด config (ถ้ามี)
$configLoaded = false;
$configError = '';
if (file_exists(__DIR__ . '/config.php')) {
    try {
        require_once __DIR__ . '/config.php';
        $configLoaded = true;
    } catch (Throwable $e) {
        $configError = $e->getMessage();
    }
}

$dbPath  = defined('DB_PATH') ? DB_PATH : __DIR__ . '/data/calendar.db';
$lockFile = __DIR__ . '/data/.setup_locked';
$isLocked = file_exists($lockFile);
$messages = [];

// Session ต้อง start ก่อน output HTML ทุกกรณี
// ป้องกัน "headers already sent" เมื่อ safe_session_start() ถูกเรียกทีหลัง
if ($configLoaded && function_exists('safe_session_start')) {
    safe_session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// Language Detection (TH / EN)
// ============================================================
if (!empty($_GET['lang']) && in_array($_GET['lang'], ['th', 'en'])) {
    $_SESSION['setup_lang'] = $_GET['lang'];
}
$setupLang = $_SESSION['setup_lang'] ?? 'th';
$isEn = ($setupLang === 'en');

// ============================================================
// Auth Gate
// ============================================================
// Fresh install (ยังไม่มี admin users) → เข้าได้โดยไม่ต้อง login
// Existing install (มี admin users แล้ว) → ต้อง login ก่อน
$_isFreshInstall = true;
if (file_exists($dbPath)) {
    try {
        $_qdb = new PDO('sqlite:' . $dbPath);
        $_qTable = $_qdb->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'"
        )->fetch();
        if ($_qTable) {
            $_qCount = (int)$_qdb->query("SELECT COUNT(*) FROM admin_users WHERE is_active=1")->fetchColumn();
            if ($_qCount > 0) {
                $_isFreshInstall = false;
            }
        }
        unset($_qdb, $_qTable, $_qCount);
    } catch (Exception $e) {}
}

if (!$_isFreshInstall) {
    // session ถูก start ไปแล้วข้างบน — ตรวจสอบ login ได้เลย
    $loggedIn = $configLoaded && function_exists('is_logged_in') && is_logged_in();
    if (!$loggedIn) {
        header('Location: admin/login.php');
        exit;
    }
}
unset($_isFreshInstall);

// Helper: ลบโฟลเดอร์แบบ recursive (ใช้ใน cleanup_dev_files)
function setup_delete_directory(string $dir): bool {
    if (!is_dir($dir)) return true;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? setup_delete_directory($path) : @unlink($path);
    }
    return @rmdir($dir);
}

// ============================================================
// Handle POST Actions
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Lock setup page
    if ($action === 'lock') {
        if (!is_dir(__DIR__ . '/data')) {
            @mkdir(__DIR__ . '/data', 0755, true);
        }
        if (file_put_contents($lockFile, date('Y-m-d H:i:s') . "\n") !== false) {
            $isLocked = true;
            $messages[] = ['type' => 'success', 'text' => '🔒 Setup page ถูก lock แล้ว — ไม่สามารถรัน setup actions ได้จนกว่าจะ unlock'];
        } else {
            $messages[] = ['type' => 'error', 'text' => 'ไม่สามารถสร้าง lock file ได้ — ตรวจสอบ permission ของโฟลเดอร์ data/'];
        }
    }

    // Unlock setup page
    if ($action === 'unlock') {
        if (file_exists($lockFile) && @unlink($lockFile)) {
            $isLocked = false;
            $messages[] = ['type' => 'info', 'text' => '🔓 Setup page ถูก unlock แล้ว — สามารถรัน setup actions ได้'];
        } else {
            $messages[] = ['type' => 'error', 'text' => 'ไม่สามารถลบ lock file ได้'];
        }
    }

    // บล็อก setup actions ทั้งหมดเมื่อ locked (ยกเว้นเปลี่ยน password — เป็น security action)
    if ($isLocked && !in_array($action, ['lock', 'unlock', 'set_admin_password'])) {
        $messages[] = ['type' => 'error', 'text' => '🔒 Setup page ถูก lock อยู่ — กด Unlock ก่อนจึงจะรัน action ได้'];
        $action = '';
    }

    // สร้าง directories ที่ขาด
    if ($action === 'create_dirs') {
        $toCreate = [
            __DIR__ . '/data'         => 'data/',
            __DIR__ . '/cache'        => 'cache/',
            __DIR__ . '/cache/images' => 'cache/images/',
            __DIR__ . '/backups'      => 'backups/',
            __DIR__ . '/ics'          => 'ics/',
            __DIR__ . '/fonts'        => 'fonts/',
            __DIR__ . '/cache/favorites' => 'cache/favorites/',
        ];
        $created = 0;
        foreach ($toCreate as $path => $label) {
            if (!is_dir($path)) {
                if (@mkdir($path, 0755, true)) {
                    $messages[] = ['type' => 'success', 'text' => "สร้างโฟลเดอร์ <strong>$label</strong> เรียบร้อย"];
                    $created++;
                } else {
                    $messages[] = ['type' => 'error', 'text' => "ไม่สามารถสร้างโฟลเดอร์ <strong>$label</strong> — ตรวจสอบ permission"];
                }
            }
        }
        if ($created === 0) {
            $messages[] = ['type' => 'info', 'text' => "โฟลเดอร์ทั้งหมดมีอยู่แล้ว"];
        }
    }

    // สร้างตาราง database ทั้งหมด (fresh install)
    if ($action === 'init_database') {
        if (!is_dir(dirname($dbPath))) {
            @mkdir(dirname($dbPath), 0755, true);
        }
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // programs table — รายการ shows/performances
            $db->exec("CREATE TABLE IF NOT EXISTS programs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uid TEXT UNIQUE NOT NULL,
                title TEXT NOT NULL,
                start DATETIME NOT NULL,
                end DATETIME NOT NULL,
                location TEXT,
                organizer TEXT,
                description TEXT,
                categories TEXT,
                program_type TEXT DEFAULT NULL,
                stream_url TEXT DEFAULT NULL,
                event_id INTEGER REFERENCES events(id),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // events table — conventions / meta-events
            $db->exec("CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                start_date DATE,
                end_date DATE,
                venue_mode TEXT DEFAULT 'multi',
                is_active BOOLEAN DEFAULT 1,
                theme TEXT DEFAULT NULL,
                email TEXT DEFAULT NULL,
                timezone TEXT DEFAULT 'Asia/Bangkok',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // program_requests table — คำขอจากผู้ใช้
            $db->exec("CREATE TABLE IF NOT EXISTS program_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_type TEXT NOT NULL,
                program_id INTEGER,
                event_id INTEGER,
                summary TEXT,
                start DATETIME,
                end DATETIME,
                location TEXT,
                organizer TEXT,
                categories TEXT,
                description TEXT,
                requester_name TEXT,
                requester_email TEXT,
                note TEXT,
                status TEXT DEFAULT 'pending',
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // credits table — credits/references
            $db->exec("CREATE TABLE IF NOT EXISTS credits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                link TEXT,
                description TEXT,
                display_order INTEGER DEFAULT 0,
                event_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // contact_channels table — ช่องทางติดต่อ
            $db->exec("CREATE TABLE IF NOT EXISTS contact_channels (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                icon TEXT DEFAULT '',
                title TEXT NOT NULL DEFAULT '',
                description TEXT DEFAULT '',
                url TEXT DEFAULT '',
                display_order INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // admin_users table — ผู้ใช้ admin
            $db->exec("CREATE TABLE IF NOT EXISTS admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                display_name TEXT,
                role TEXT DEFAULT 'admin',
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login_at DATETIME
            )");

            // artists table — ศิลปิน/กลุ่ม (v3.0.0+)
            $db->exec("CREATE TABLE IF NOT EXISTS artists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                is_group INTEGER DEFAULT 0,
                group_id INTEGER REFERENCES artists(id),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // program_artists junction table — many-to-many (v3.0.0+)
            $db->exec("CREATE TABLE IF NOT EXISTS program_artists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                program_id INTEGER NOT NULL REFERENCES programs(id) ON DELETE CASCADE,
                artist_id INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(program_id, artist_id)
            )");

            // artist_variants table — alias names per artist (v3.0.0+)
            $db->exec("CREATE TABLE IF NOT EXISTS artist_variants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                artist_id INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
                variant TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(artist_id, variant)
            )");

            // Indexes สำหรับ performance
            $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_admin_users_username ON admin_users(username)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_programs_event_id ON programs(event_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_programs_start ON programs(start)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_programs_location ON programs(location)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_programs_categories ON programs(categories)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_program_requests_status ON program_requests(status)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_program_requests_event_id ON program_requests(event_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_credits_event_id ON credits(event_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_program_artists_program_id ON program_artists(program_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_program_artists_artist_id ON program_artists(artist_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_artist_variants_artist_id ON artist_variants(artist_id)");

            // Seed admin user ถ้ายังไม่มี
            $adminCount = $db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
            if ($adminCount == 0) {
                $username = defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin';
                $passwordHash = defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : password_hash('admin123', PASSWORD_DEFAULT);
                $now = date('Y-m-d H:i:s');
                $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, display_name, role, is_active, created_at, updated_at) VALUES (?, ?, ?, 'admin', 1, ?, ?)");
                $stmt->execute([$username, $passwordHash, $username, $now, $now]);
                $messages[] = ['type' => 'success', 'text' => "สร้าง admin user: <strong>$username</strong>"];
            }

            // สร้าง default event ถ้ายังไม่มี
            $eventCount = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
            if ($eventCount == 0) {
                $slug = defined('DEFAULT_EVENT_SLUG') ? DEFAULT_EVENT_SLUG : 'default';
                $venueMode = defined('VENUE_MODE') ? VENUE_MODE : 'multi';
                $now = date('Y-m-d H:i:s');
                $stmt = $db->prepare("INSERT INTO events (slug, name, venue_mode, is_active, created_at, updated_at) VALUES (?, 'Idol Stage Event', ?, 1, ?, ?)");
                $stmt->execute([$slug, $venueMode, $now, $now]);
                $eventId = $db->lastInsertId();
                $messages[] = ['type' => 'success', 'text' => "สร้าง default event (slug: <strong>$slug</strong>)"];

                // Seed sample programs เพื่อให้เห็นหน้าตาระบบทันที
                $programCount = $db->query("SELECT COUNT(*) FROM programs")->fetchColumn();
                if ($programCount == 0) {
                    $today = date('Y-m-d');
                    $samples = [
                        [
                            'uid'         => 'sample-001@idol-stage.local',
                            'title'       => 'Opening Ceremony',
                            'start'       => $today . ' 10:00:00',
                            'end'         => $today . ' 10:30:00',
                            'location'    => 'Main Stage',
                            'organizer'   => 'Idol Stage',
                            'description' => 'ตัวอย่าง program — แก้ไขหรือลบได้จาก Admin › Programs',
                            'categories'  => 'Idol Stage',
                        ],
                        [
                            'uid'         => 'sample-002@idol-stage.local',
                            'title'       => 'Artist Performance',
                            'start'       => $today . ' 11:00:00',
                            'end'         => $today . ' 12:00:00',
                            'location'    => 'Main Stage',
                            'organizer'   => 'Sample Artist',
                            'description' => 'ตัวอย่าง program — แก้ไขหรือลบได้จาก Admin › Programs',
                            'categories'  => 'Sample Artist',
                        ],
                        [
                            'uid'         => 'sample-003@idol-stage.local',
                            'title'       => 'Closing Stage',
                            'start'       => $today . ' 17:00:00',
                            'end'         => $today . ' 18:00:00',
                            'location'    => 'Sub Stage',
                            'organizer'   => 'All Artists',
                            'description' => 'ตัวอย่าง program — แก้ไขหรือลบได้จาก Admin › Programs',
                            'categories'  => 'All Artists',
                        ],
                    ];
                    $pstmt = $db->prepare("INSERT INTO programs (uid, title, start, end, location, organizer, description, categories, event_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($samples as $s) {
                        $pstmt->execute([$s['uid'], $s['title'], $s['start'], $s['end'], $s['location'], $s['organizer'], $s['description'], $s['categories'], $eventId, $now, $now]);
                    }
                    $messages[] = ['type' => 'info', 'text' => "เพิ่ม <strong>" . count($samples) . " sample programs</strong> — แก้ไขหรือลบได้จาก Admin › Programs"];
                }
            }

            $messages[] = ['type' => 'success', 'text' => "สร้างตาราง database และ indexes ทั้งหมดเรียบร้อย"];

            // Auto-login หลัง fresh install — ตั้ง session โดยตรง
            if (empty($_SESSION['admin_logged_in'])) {
                $seededUser = $db->query(
                    "SELECT id, username, display_name, role FROM admin_users WHERE is_active=1 ORDER BY id LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($seededUser) {
                    $_SESSION['admin_logged_in']    = true;
                    $_SESSION['admin_username']      = $seededUser['username'];
                    $_SESSION['admin_user_id']       = (int)$seededUser['id'];
                    $_SESSION['admin_display_name']  = $seededUser['display_name'] ?: $seededUser['username'];
                    $_SESSION['admin_role']          = $seededUser['role'] ?: 'admin';
                    $_SESSION['login_time']          = time();
                    $_SESSION['last_activity']       = time();
                    $messages[] = ['type' => 'info', 'text' => "🔐 Auto-login เรียบร้อย — เข้าสู่ระบบในฐานะ <strong>{$seededUser['username']}</strong>"];
                }
            }

        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Database error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // เพิ่ม role column (migration สำหรับ existing install)
    if ($action === 'add_role_column') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $cols = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('role', $cols)) {
                $db->exec("ALTER TABLE admin_users ADD COLUMN role TEXT DEFAULT 'admin'");
                $db->exec("UPDATE admin_users SET role = 'admin' WHERE role IS NULL");
                $messages[] = ['type' => 'success', 'text' => "เพิ่ม <strong>role column</strong> ใน admin_users เรียบร้อย"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "role column มีอยู่แล้ว"];
            }
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // เปลี่ยน admin password โดยตรงจากหน้า setup
    if ($action === 'set_admin_password') {
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 8) {
            $messages[] = ['type' => 'error', 'text' => 'Password ต้องมีอย่างน้อย 8 ตัวอักษร'];
        } elseif ($newPass !== $confirmPass) {
            $messages[] = ['type' => 'error', 'text' => 'Password ไม่ตรงกัน — กรุณากรอกอีกครั้ง'];
        } elseif (!file_exists($dbPath)) {
            $messages[] = ['type' => 'error', 'text' => 'ยังไม่มี database — กรุณา Initialize Database ก่อน'];
        } else {
            try {
                $db = new PDO('sqlite:' . $dbPath);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $now  = date('Y-m-d H:i:s');

                // อัพเดท user ที่ login อยู่ หรือ admin คนแรกในระบบ
                $targetUser = !empty($_SESSION['admin_username']) ? $_SESSION['admin_username'] : null;
                if ($targetUser) {
                    $stmt = $db->prepare("UPDATE admin_users SET password_hash=?, updated_at=? WHERE username=?");
                    $stmt->execute([$hash, $now, $targetUser]);
                    $affected = $stmt->rowCount();
                } else {
                    // Fresh install — อัพเดท admin คนแรก
                    $firstId = $db->query("SELECT id FROM admin_users WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
                    if ($firstId) {
                        $stmt = $db->prepare("UPDATE admin_users SET password_hash=?, updated_at=? WHERE id=?");
                        $stmt->execute([$hash, $now, $firstId]);
                        $affected = $stmt->rowCount();
                    } else {
                        $affected = 0;
                    }
                }

                if ($affected > 0) {
                    $messages[] = ['type' => 'success', 'text' => '🔑 เปลี่ยน Password เรียบร้อยแล้ว — password ใหม่พร้อมใช้งาน'];
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'ไม่พบ admin user ที่จะอัพเดท'];
                }
            } catch (PDOException $e) {
                $messages[] = ['type' => 'error', 'text' => 'Error: ' . htmlspecialchars($e->getMessage())];
            }
        }
    }

    // เพิ่ม theme column (migration สำหรับ existing install)
    if ($action === 'add_theme_column') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $ecols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('theme', $ecols)) {
                $db->exec("ALTER TABLE events ADD COLUMN theme TEXT DEFAULT NULL");
                $messages[] = ['type' => 'success', 'text' => "เพิ่ม <strong>theme column</strong> ใน events เรียบร้อย"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "theme column มีอยู่แล้ว"];
            }
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // เพิ่ม email column ใน events (migration สำหรับ existing install)
    if ($action === 'add_event_email_column') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $ecols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('email', $ecols)) {
                $db->exec("ALTER TABLE events ADD COLUMN email TEXT DEFAULT NULL");
                $messages[] = ['type' => 'success', 'text' => "เพิ่ม <strong>email column</strong> ใน events เรียบร้อย"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "email column มีอยู่แล้ว"];
            }
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // เพิ่ม program_type column ใน programs (migration สำหรับ existing install)
    if ($action === 'add_program_type_column') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pcols = $db->query("PRAGMA table_info(programs)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('program_type', $pcols)) {
                $db->exec("ALTER TABLE programs ADD COLUMN program_type TEXT DEFAULT NULL");
                $messages[] = ['type' => 'success', 'text' => "เพิ่ม <strong>program_type column</strong> ใน programs เรียบร้อย"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "program_type column มีอยู่แล้ว"];
            }
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // แก้ไข programs.summary → programs.title (fresh install ด้วย setup.php เก่า)
    if ($action === 'fix_programs_title') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pcols = $db->query("PRAGMA table_info(programs)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (in_array('summary', $pcols) && !in_array('title', $pcols)) {
                // SQLite ไม่รองรับ RENAME COLUMN ใน PHP 8 เก่า → สร้างตารางใหม่แทน
                $db->exec("BEGIN");
                $db->exec("ALTER TABLE programs RENAME TO programs_old");
                // ตรวจว่า programs_old มี program_type / stream_url หรือไม่ (อาจมีถ้าเคยรัน migration มาก่อน)
                $oldCols = $db->query("PRAGMA table_info(programs_old)")->fetchAll(PDO::FETCH_COLUMN, 1);
                $hasOldProgramType = in_array('program_type', $oldCols);
                $hasOldStreamUrl = in_array('stream_url', $oldCols);
                $db->exec("CREATE TABLE programs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    uid TEXT UNIQUE NOT NULL,
                    title TEXT NOT NULL,
                    start DATETIME NOT NULL,
                    end DATETIME NOT NULL,
                    location TEXT,
                    organizer TEXT,
                    description TEXT,
                    categories TEXT,
                    program_type TEXT DEFAULT NULL,
                    stream_url TEXT DEFAULT NULL,
                    event_id INTEGER REFERENCES events(id),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                // Build INSERT columns dynamically based on what old table has
                $insertCols = 'id, uid, title, start, end, location, organizer, description, categories';
                $selectCols = "id, COALESCE(uid, 'uid-' || id || '@local'), COALESCE(summary, '(no title)'), start, end, location, organizer, description, categories";
                if ($hasOldProgramType) { $insertCols .= ', program_type'; $selectCols .= ', program_type'; }
                if ($hasOldStreamUrl)   { $insertCols .= ', stream_url';   $selectCols .= ', stream_url'; }
                $insertCols .= ', event_id, created_at, updated_at';
                $selectCols .= ', event_id, created_at, updated_at';
                $db->exec("INSERT INTO programs ($insertCols) SELECT $selectCols FROM programs_old");
                $db->exec("DROP TABLE programs_old");
                $db->exec("COMMIT");
                $messages[] = ['type' => 'success', 'text' => "แก้ไข <strong>programs.summary → programs.title</strong> เรียบร้อย (รวม program_type column)"];
            } elseif (in_array('title', $pcols)) {
                $messages[] = ['type' => 'info', 'text' => "programs.title มีอยู่แล้ว (ไม่ต้องแก้ไข)"];
            } else {
                $messages[] = ['type' => 'warning', 'text' => "ไม่พบ column summary หรือ title ใน programs table"];
            }
        } catch (PDOException $e) {
            if (isset($db)) $db->exec("ROLLBACK");
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // เพิ่ม performance indexes
    if ($action === 'add_indexes') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sqls = [
                "CREATE INDEX IF NOT EXISTS idx_programs_event_id ON programs(event_id)",
                "CREATE INDEX IF NOT EXISTS idx_programs_start ON programs(start)",
                "CREATE INDEX IF NOT EXISTS idx_programs_location ON programs(location)",
                "CREATE INDEX IF NOT EXISTS idx_programs_categories ON programs(categories)",
                "CREATE INDEX IF NOT EXISTS idx_program_requests_status ON program_requests(status)",
                "CREATE INDEX IF NOT EXISTS idx_program_requests_event_id ON program_requests(event_id)",
                "CREATE INDEX IF NOT EXISTS idx_credits_event_id ON credits(event_id)",
            ];
            foreach ($sqls as $sql) {
                $db->exec($sql);
            }
            $messages[] = ['type' => 'success', 'text' => "เพิ่ม performance indexes ทั้งหมดเรียบร้อย"];
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // เพิ่ม stream_url column ใน programs (migration สำหรับ existing install — v2.6.0)
    if ($action === 'add_stream_url_column') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pcols = $db->query("PRAGMA table_info(programs)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('stream_url', $pcols)) {
                $db->exec("ALTER TABLE programs ADD COLUMN stream_url TEXT DEFAULT NULL");
                $messages[] = ['type' => 'success', 'text' => "เพิ่ม <strong>stream_url column</strong> ใน programs เรียบร้อย"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "stream_url column มีอยู่แล้ว"];
            }
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // สร้าง contact_channels table (migration สำหรับ existing install — v2.10.0)
    if ($action === 'add_contact_channels_table') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $existingTables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('contact_channels', $existingTables)) {
                $db->exec("CREATE TABLE contact_channels (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    icon TEXT DEFAULT '',
                    title TEXT NOT NULL DEFAULT '',
                    description TEXT DEFAULT '',
                    url TEXT DEFAULT '',
                    display_order INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $messages[] = ['type' => 'success', 'text' => "สร้างตาราง <strong>contact_channels</strong> เรียบร้อย"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "ตาราง contact_channels มีอยู่แล้ว"];
            }
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // สร้าง artist tables (migration สำหรับ existing install — v3.0.0)
    if ($action === 'add_artist_tables') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $existingTables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('artists', $existingTables)) {
                $db->exec("CREATE TABLE artists (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE NOT NULL,
                    is_group INTEGER DEFAULT 0,
                    group_id INTEGER REFERENCES artists(id),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $messages[] = ['type' => 'success', 'text' => "สร้างตาราง <strong>artists</strong> เรียบร้อย"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "ตาราง artists มีอยู่แล้ว"];
            }

            if (!in_array('program_artists', $existingTables)) {
                $db->exec("CREATE TABLE program_artists (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    program_id INTEGER NOT NULL REFERENCES programs(id) ON DELETE CASCADE,
                    artist_id INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(program_id, artist_id)
                )");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_program_artists_program_id ON program_artists(program_id)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_program_artists_artist_id ON program_artists(artist_id)");
                $messages[] = ['type' => 'success', 'text' => "สร้างตาราง <strong>program_artists</strong> เรียบร้อย"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "ตาราง program_artists มีอยู่แล้ว"];
            }

            if (!in_array('artist_variants', $existingTables)) {
                $db->exec("CREATE TABLE artist_variants (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    artist_id INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
                    variant TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(artist_id, variant)
                )");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_artist_variants_artist_id ON artist_variants(artist_id)");
                $messages[] = ['type' => 'success', 'text' => "สร้างตาราง <strong>artist_variants</strong> เรียบร้อย"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "ตาราง artist_variants มีอยู่แล้ว"];
            }

            $messages[] = ['type' => 'info', 'text' => "หลังสร้างตารางแล้ว รัน <code>php tools/migrate-add-artist-variants-table.php</code> เพื่อ import artist variants จาก ICS data"];
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // เพิ่ม timezone column ใน events (migration สำหรับ existing install — v4.0.0)
    if ($action === 'add_timezone_column') {
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $ecols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('timezone', $ecols)) {
                $db->exec("ALTER TABLE events ADD COLUMN timezone TEXT DEFAULT 'Asia/Bangkok'");
                $messages[] = ['type' => 'success', 'text' => "เพิ่ม <strong>events.timezone column</strong> เรียบร้อย (default: Asia/Bangkok)"];
            } else {
                $messages[] = ['type' => 'info', 'text' => "events.timezone column มีอยู่แล้ว"];
            }
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
        }
    }

    // รัน migrations ที่ค้างทั้งหมดในครั้งเดียว
    if ($action === 'run_all_migrations') {
        if (!file_exists($dbPath)) {
            $messages[] = ['type' => 'error', 'text' => 'ยังไม่มี database — กรุณา Initialize Database ก่อน'];
        } else {
            try {
                $db = new PDO('sqlite:' . $dbPath);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $existingTables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
                $existingCols   = [];
                foreach (['programs', 'events', 'admin_users'] as $t) {
                    if (in_array($t, $existingTables)) {
                        $existingCols[$t] = $db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_COLUMN, 1);
                    }
                }

                $ran = 0;

                // admin_users.role column
                if (in_array('admin_users', $existingTables) && !in_array('role', $existingCols['admin_users'] ?? [])) {
                    $db->exec("ALTER TABLE admin_users ADD COLUMN role TEXT DEFAULT 'admin'");
                    $messages[] = ['type' => 'success', 'text' => "✅ เพิ่ม <strong>admin_users.role</strong> column"];
                    $ran++;
                }

                // events.theme column
                if (in_array('events', $existingTables) && !in_array('theme', $existingCols['events'] ?? [])) {
                    $db->exec("ALTER TABLE events ADD COLUMN theme TEXT DEFAULT NULL");
                    $messages[] = ['type' => 'success', 'text' => "✅ เพิ่ม <strong>events.theme</strong> column"];
                    $ran++;
                }

                // events.email column
                if (in_array('events', $existingTables) && !in_array('email', $existingCols['events'] ?? [])) {
                    $db->exec("ALTER TABLE events ADD COLUMN email TEXT DEFAULT NULL");
                    $messages[] = ['type' => 'success', 'text' => "✅ เพิ่ม <strong>events.email</strong> column"];
                    $ran++;
                }

                // events.timezone column
                if (in_array('events', $existingTables) && !in_array('timezone', $existingCols['events'] ?? [])) {
                    $db->exec("ALTER TABLE events ADD COLUMN timezone TEXT DEFAULT 'Asia/Bangkok'");
                    $messages[] = ['type' => 'success', 'text' => "✅ เพิ่ม <strong>events.timezone</strong> column"];
                    $ran++;
                }

                // programs.program_type column
                if (in_array('programs', $existingTables) && !in_array('program_type', $existingCols['programs'] ?? [])) {
                    $db->exec("ALTER TABLE programs ADD COLUMN program_type TEXT DEFAULT NULL");
                    $messages[] = ['type' => 'success', 'text' => "✅ เพิ่ม <strong>programs.program_type</strong> column"];
                    $ran++;
                }

                // programs.stream_url column
                if (in_array('programs', $existingTables) && !in_array('stream_url', $existingCols['programs'] ?? [])) {
                    $db->exec("ALTER TABLE programs ADD COLUMN stream_url TEXT DEFAULT NULL");
                    $messages[] = ['type' => 'success', 'text' => "✅ เพิ่ม <strong>programs.stream_url</strong> column"];
                    $ran++;
                }

                // Performance indexes
                $existingIdx = $db->query("SELECT name FROM sqlite_master WHERE type='index'")->fetchAll(PDO::FETCH_COLUMN);
                $requiredIdx = [
                    'idx_programs_event_id'         => "CREATE INDEX IF NOT EXISTS idx_programs_event_id ON programs(event_id)",
                    'idx_programs_start'            => "CREATE INDEX IF NOT EXISTS idx_programs_start ON programs(start)",
                    'idx_programs_location'         => "CREATE INDEX IF NOT EXISTS idx_programs_location ON programs(location)",
                    'idx_programs_categories'       => "CREATE INDEX IF NOT EXISTS idx_programs_categories ON programs(categories)",
                    'idx_program_requests_status'   => "CREATE INDEX IF NOT EXISTS idx_program_requests_status ON program_requests(status)",
                    'idx_program_requests_event_id' => "CREATE INDEX IF NOT EXISTS idx_program_requests_event_id ON program_requests(event_id)",
                    'idx_credits_event_id'          => "CREATE INDEX IF NOT EXISTS idx_credits_event_id ON credits(event_id)",
                ];
                $missingIdx = array_diff(array_keys($requiredIdx), $existingIdx);
                if (!empty($missingIdx)) {
                    foreach ($missingIdx as $idxName) { $db->exec($requiredIdx[$idxName]); }
                    $messages[] = ['type' => 'success', 'text' => "✅ เพิ่ม <strong>performance indexes</strong> (" . count($missingIdx) . " indexes)"];
                    $ran++;
                }

                // contact_channels table
                if (!in_array('contact_channels', $existingTables)) {
                    $db->exec("CREATE TABLE contact_channels (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        icon TEXT DEFAULT '',
                        title TEXT NOT NULL DEFAULT '',
                        description TEXT DEFAULT '',
                        url TEXT DEFAULT '',
                        display_order INTEGER DEFAULT 0,
                        is_active INTEGER DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )");
                    $messages[] = ['type' => 'success', 'text' => "✅ สร้างตาราง <strong>contact_channels</strong>"];
                    $ran++;
                }

                // artists + program_artists + artist_variants tables
                if (!in_array('artists', $existingTables)) {
                    $db->exec("CREATE TABLE artists (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT UNIQUE NOT NULL,
                        is_group INTEGER DEFAULT 0,
                        group_id INTEGER REFERENCES artists(id),
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )");
                    $messages[] = ['type' => 'success', 'text' => "✅ สร้างตาราง <strong>artists</strong>"];
                    $ran++;
                }
                if (!in_array('program_artists', $existingTables)) {
                    $db->exec("CREATE TABLE program_artists (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        program_id INTEGER NOT NULL REFERENCES programs(id) ON DELETE CASCADE,
                        artist_id INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(program_id, artist_id)
                    )");
                    $db->exec("CREATE INDEX IF NOT EXISTS idx_program_artists_program_id ON program_artists(program_id)");
                    $db->exec("CREATE INDEX IF NOT EXISTS idx_program_artists_artist_id ON program_artists(artist_id)");
                    $messages[] = ['type' => 'success', 'text' => "✅ สร้างตาราง <strong>program_artists</strong>"];
                    $ran++;
                }
                if (!in_array('artist_variants', $existingTables)) {
                    $db->exec("CREATE TABLE artist_variants (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        artist_id INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
                        variant TEXT NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(artist_id, variant)
                    )");
                    $db->exec("CREATE INDEX IF NOT EXISTS idx_artist_variants_artist_id ON artist_variants(artist_id)");
                    $messages[] = ['type' => 'success', 'text' => "✅ สร้างตาราง <strong>artist_variants</strong>"];
                    $ran++;
                }

                if ($ran === 0) {
                    $messages[] = ['type' => 'info', 'text' => "✅ ไม่มี migration ที่ค้างอยู่ — database เป็นปัจจุบันแล้ว"];
                } else {
                    $messages[] = ['type' => 'success', 'text' => "🎉 รัน <strong>$ran migration(s)</strong> เสร็จเรียบร้อย — โปรดรีโหลดหน้านี้เพื่อดูสถานะอัปเดต"];
                }
                unset($db);
            } catch (PDOException $e) {
                $messages[] = ['type' => 'error', 'text' => "Error: " . htmlspecialchars($e->getMessage())];
            }
        }
    }

    // ลบไฟล์ dev/documentation ที่ไม่จำเป็นใน production
    if ($action === 'cleanup_dev_files') {
        $allowedFileList = [
            'README.md', 'DOCKER.md', 'INSTALLATION.md', 'SETUP.md', 'API.md', 'CHANGELOG.md', 'TESTING.md', 'SECURITY.md',
            'CONTRIBUTING.md', 'PROJECT-STRUCTURE.md', 'CLAUDE.md', 'LICENSE',
            '.env.example', 'Dockerfile', 'docker-compose.yml', 'docker-compose.dev.yml',
            '.dockerignore', 'nginx-clean-url.conf', 'quick-test.bat', 'quick-test.sh',
            '.gitignore',
        ];
        $allowedDirList = ['tests', 'tools', '.github'];

        $selectedItems = $_POST['cleanup_items'] ?? [];
        $deleted = [];
        $failed  = [];

        foreach ($selectedItems as $raw) {
            $raw = trim((string)$raw);
            if ($raw === '') continue;

            if (str_ends_with($raw, '/')) {
                // Directory
                $dirName = rtrim($raw, '/');
                if (!in_array($dirName, $allowedDirList)) continue;
                $path = __DIR__ . DIRECTORY_SEPARATOR . $dirName;
                if (!is_dir($path)) continue;
                if (setup_delete_directory($path)) {
                    $deleted[] = $raw;
                } else {
                    $failed[] = $raw;
                }
            } else {
                // File
                if (!in_array($raw, $allowedFileList)) continue;
                $path = __DIR__ . DIRECTORY_SEPARATOR . $raw;
                if (!file_exists($path)) continue;
                if (@unlink($path)) {
                    $deleted[] = $raw;
                } else {
                    $failed[] = $raw;
                }
            }
        }

        if (!empty($deleted)) {
            $messages[] = ['type' => 'success', 'text' => '🗑️ ลบเรียบร้อย (' . count($deleted) . ' รายการ): <code>' . implode('</code>, <code>', array_map('htmlspecialchars', $deleted)) . '</code>'];
        }
        if (!empty($failed)) {
            $messages[] = ['type' => 'error', 'text' => '⚠️ ลบไม่สำเร็จ (ตรวจสอบ permissions): <code>' . implode('</code>, <code>', array_map('htmlspecialchars', $failed)) . '</code>'];
        }
        if (empty($selectedItems)) {
            $messages[] = ['type' => 'info', 'text' => 'ไม่ได้เลือกรายการที่จะลบ'];
        }
    }
}

// ============================================================
// ตรวจสอบสถานะทั้งหมด
// ============================================================

// 1. PHP Requirements
$phpChecks = [
    'php_version' => [
        'label' => 'PHP Version (8.1+)',
        'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'value' => PHP_VERSION,
        'fix' => $isEn ? 'Upgrade PHP to 8.1 or higher' : 'อัพเกรด PHP เป็น 8.1 หรือสูงกว่า',
    ],
    'pdo' => [
        'label' => 'PDO Extension',
        'ok' => extension_loaded('pdo'),
        'value' => extension_loaded('pdo') ? 'loaded' : 'not found',
        'fix' => $isEn ? 'Enable extension=pdo in php.ini' : 'เปิดใช้งาน extension=pdo ใน php.ini',
    ],
    'pdo_sqlite' => [
        'label' => 'PDO SQLite Extension',
        'ok' => extension_loaded('pdo_sqlite'),
        'value' => extension_loaded('pdo_sqlite') ? 'loaded' : 'not found',
        'fix' => $isEn ? 'Enable extension=pdo_sqlite in php.ini' : 'เปิดใช้งาน extension=pdo_sqlite ใน php.ini',
    ],
    'mbstring' => [
        'label' => 'mbstring Extension',
        'ok' => extension_loaded('mbstring'),
        'value' => extension_loaded('mbstring') ? 'loaded' : 'not found',
        'fix' => $isEn ? 'Enable extension=mbstring in php.ini' : 'เปิดใช้งาน extension=mbstring ใน php.ini',
    ],
    'gd' => [
        'label' => 'GD Extension (image export)',
        'ok' => extension_loaded('gd') && function_exists('imagettftext'),
        'value' => extension_loaded('gd') ? (function_exists('imagettftext') ? ($isEn ? 'loaded + FreeType' : 'loaded + FreeType') : ($isEn ? 'loaded (no FreeType — Thai/JP fonts need FreeType)' : 'loaded (ไม่มี FreeType — ต้องการ FreeType สำหรับ Thai/JP fonts)')) : ($isEn ? 'not found' : 'not found'),
        'fix' => $isEn ? 'Enable extension=gd with FreeType support (--with-freetype). Required for server-side image export (image.php).' : 'เปิดใช้งาน extension=gd พร้อม FreeType support (--with-freetype) — จำเป็นสำหรับ server-side image export (image.php)',
        'optional' => true,
    ],
];
// GD is optional — don't block setup if missing
$allPhpOk = !in_array(false, array_map(fn($c) => $c['ok'] || !empty($c['optional']), $phpChecks));

// 1c. Favorites HMAC secret check (optional — only relevant if favorites feature is used)
$_favSecretOk = defined('FAVORITES_HMAC_SECRET') && FAVORITES_HMAC_SECRET !== 'REPLACE_WITH_GENERATED_SECRET';

// 2. Directories
$dirChecks = [
    'data'         => ['label' => 'data/',         'path' => __DIR__ . '/data',         'need_write' => true,  'purpose' => $isEn ? 'Stores database'                     : 'เก็บ database'],
    'cache'        => ['label' => 'cache/',        'path' => __DIR__ . '/cache',        'need_write' => true,  'purpose' => $isEn ? 'Stores cache files'                  : 'เก็บ cache files'],
    'cache_images' => ['label' => 'cache/images/', 'path' => __DIR__ . '/cache/images', 'need_write' => true,  'purpose' => $isEn ? 'Stores generated PNG images (v3.3.0)' : 'เก็บรูปภาพ PNG ที่ generate (v3.3.0)'],
    'backups'      => ['label' => 'backups/',      'path' => __DIR__ . '/backups',      'need_write' => true,  'purpose' => $isEn ? 'Stores backup files'                 : 'เก็บ backup files'],
    'ics'          => ['label' => 'ics/',          'path' => __DIR__ . '/ics',          'need_write' => false, 'purpose' => $isEn ? 'Stores ICS files (optional)'         : 'เก็บไฟล์ ICS (optional)'],
    'fonts'        => ['label' => 'fonts/',        'path' => __DIR__ . '/fonts',        'need_write' => false, 'purpose' => $isEn ? 'TrueType fonts for image export (optional, see fonts/README.md)' : 'TrueType fonts สำหรับ image export (optional, ดู fonts/README.md)'],
    'cache_favorites' => ['label' => 'cache/favorites/', 'path' => __DIR__ . '/cache/favorites', 'need_write' => true,  'purpose' => $isEn ? 'Stores anonymous favorites JSON files' : 'เก็บไฟล์ favorites (anonymous)'],
];
foreach ($dirChecks as &$dc) {
    $dc['exists'] = is_dir($dc['path']);
    $dc['writable'] = $dc['exists'] && is_writable($dc['path']);
    $dc['ok'] = $dc['need_write'] ? ($dc['exists'] && $dc['writable']) : $dc['exists'];
}
unset($dc);
$allDirsOk = !in_array(false, array_column($dirChecks, 'ok'));
$missingDirs = array_filter($dirChecks, fn($d) => !$d['exists']);

// 2b. Font files in fonts/ directory (v3.3.0 — tested & confirmed working)
$_fontsDir = __DIR__ . '/fonts';
$fontChecks = [
    // Thai / Latin main font (at least one required for image.php to render Thai)
    ['file' => 'NotoSansThai-Regular.ttf', 'role' => $isEn ? 'Thai/Latin — main font (recommended)'      : 'Thai/Latin — font หลัก (แนะนำ)',        'group' => 'thai'],
    ['file' => 'NotoSansThai-Bold.ttf',    'role' => $isEn ? 'Thai/Latin — bold variant'                 : 'Thai/Latin — ตัวหนา (optional)',         'group' => 'thai'],
    ['file' => 'Sarabun-Regular.ttf',      'role' => $isEn ? 'Thai/Latin — alternative main font'        : 'Thai/Latin — alternative (Sarabun)',     'group' => 'thai'],
    ['file' => 'Sarabun-Bold.ttf',         'role' => $isEn ? 'Thai/Latin — Sarabun bold'                 : 'Thai/Latin — Sarabun ตัวหนา',            'group' => 'thai'],
    ['file' => 'Prompt-Regular.ttf',       'role' => $isEn ? 'Thai/Latin — alternative main font'        : 'Thai/Latin — alternative (Prompt)',      'group' => 'thai'],
    ['file' => 'Kanit-Regular.ttf',        'role' => $isEn ? 'Thai/Latin — alternative main font'        : 'Thai/Latin — alternative (Kanit)',       'group' => 'thai'],
    // Japanese / CJK
    ['file' => 'NotoSansJP-Regular.ttf',   'role' => $isEn ? 'Japanese — Hiragana/Katakana/Kanji (high quality)' : 'Japanese — Hiragana/Katakana/Kanji คุณภาพสูง', 'group' => 'cjk'],
    ['file' => 'NotoSansCJK-Regular.otf',  'role' => $isEn ? 'Japanese/CJK — full CJK coverage'          : 'Japanese/CJK — ครอบคลุมเต็ม',           'group' => 'cjk'],
    // Symbol / Emoji
    ['file' => 'NotoEmoji-Regular.ttf',    'role' => $isEn ? 'Emoji/Symbol — BMP + color emoji'          : 'Emoji/Symbol — BMP + color emoji',       'group' => 'symbol'],
    ['file' => 'Symbola.ttf',              'role' => $isEn ? 'Symbol fallback — BMP symbols (no Japanese)': 'Symbol fallback — BMP symbols (ไม่มี Japanese)', 'group' => 'symbol'],
    // All-in-one fallback
    ['file' => 'unifont.otf',              'role' => $isEn ? 'Universal fallback — BMP symbols + Japanese': 'Universal fallback — BMP symbols + Japanese', 'group' => 'unifont'],
    ['file' => 'unifont.ttf',              'role' => $isEn ? 'Universal fallback — BMP symbols + Japanese': 'Universal fallback — BMP symbols + Japanese', 'group' => 'unifont'],
];
foreach ($fontChecks as &$fc) {
    $fc['found'] = file_exists($_fontsDir . '/' . $fc['file']);
    $fc['size']  = $fc['found'] ? round(filesize($_fontsDir . '/' . $fc['file']) / 1024) . ' KB' : '';
}
unset($fc);
$hasThaiFont    = !empty(array_filter($fontChecks, fn($f) => $f['group'] === 'thai'    && $f['found']));
$hasCjkFont     = !empty(array_filter($fontChecks, fn($f) => $f['group'] === 'cjk'     && $f['found']));
$hasSymbolFont  = !empty(array_filter($fontChecks, fn($f) => in_array($f['group'], ['symbol','unifont']) && $f['found']));
$hasUnifont     = !empty(array_filter($fontChecks, fn($f) => $f['group'] === 'unifont'  && $f['found']));
// Unifont covers both symbol AND CJK — count it for both
if ($hasUnifont) { $hasCjkFont = true; $hasSymbolFont = true; }
$fontsDirExists = is_dir($_fontsDir);

// 3. Database & Tables
$dbExists = file_exists($dbPath);
$tableStatus = [];
$programCount = 0;
$eventCount = 0;
$adminCount = 0;
$hasRoleColumn = false;
$hasThemeColumn = false;
$hasEventEmailColumn = false;
$hasTimezoneColumn = false;
$hasTitleColumn = false;
$hasProgramTypeColumn = false;
$hasStreamUrlColumn = false;
$hasContactChannelsTable = false;
$hasArtistTables = false;
$hasIndexes = false;
$dbError = '';
$existingIndexes = [];

if ($dbExists) {
    try {
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $existingTables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

        foreach (['programs', 'events', 'program_requests', 'credits', 'admin_users'] as $t) {
            $tableStatus[$t] = in_array($t, $existingTables);
        }
        $hasContactChannelsTable = in_array('contact_channels', $existingTables);
        $hasArtistTables = in_array('artists', $existingTables)
                        && in_array('program_artists', $existingTables)
                        && in_array('artist_variants', $existingTables);

        if ($tableStatus['programs'] ?? false) {
            $programCount = (int)$db->query("SELECT COUNT(*) FROM programs")->fetchColumn();
        }
        if ($tableStatus['events'] ?? false) {
            $eventCount = (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn();
        }
        if ($tableStatus['admin_users'] ?? false) {
            $adminCount = (int)$db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
            $cols = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            $hasRoleColumn = in_array('role', $cols);
        }
        if ($tableStatus['events'] ?? false) {
            $ecols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_COLUMN, 1);
            $hasThemeColumn = in_array('theme', $ecols);
            $hasEventEmailColumn = in_array('email', $ecols);
            $hasTimezoneColumn = in_array('timezone', $ecols);
        }
        $hasTitleColumn = false;
        $hasProgramTypeColumn = false;
        $hasStreamUrlColumn = false;
        if ($tableStatus['programs'] ?? false) {
            $pcols = $db->query("PRAGMA table_info(programs)")->fetchAll(PDO::FETCH_COLUMN, 1);
            $hasTitleColumn = in_array('title', $pcols);
            $hasProgramTypeColumn = in_array('program_type', $pcols);
            $hasStreamUrlColumn = in_array('stream_url', $pcols);
        }

        $existingIndexes = $db->query("SELECT name FROM sqlite_master WHERE type='index'")->fetchAll(PDO::FETCH_COLUMN);
        $requiredIndexes = ['idx_programs_event_id', 'idx_programs_start', 'idx_credits_event_id'];
        $hasIndexes = count(array_intersect($requiredIndexes, $existingIndexes)) === count($requiredIndexes);

        unset($db);
    } catch (PDOException $e) {
        $dbError = $e->getMessage();
    }
}

$allTablesOk = $dbExists && !empty($tableStatus) && !in_array(false, $tableStatus) && $hasRoleColumn && $hasThemeColumn && $hasEventEmailColumn && $hasTimezoneColumn && $hasIndexes && $hasTitleColumn && $hasProgramTypeColumn && $hasStreamUrlColumn && $hasContactChannelsTable && $hasArtistTables;

// Migration checklist — ใช้ตรวจว่าค้าง migration ตัวไหน
// แต่ละรายการมี: label, version, applied (bool), action (string action name สำหรับรัน)
$migrationChecks = $dbExists ? [
    ['label' => 'Core tables (programs, events, program_requests, credits)',     'version' => 'v1.0.0', 'applied' => !empty($tableStatus) && !in_array(false, $tableStatus), 'action' => null],
    ['label' => 'admin_users table',                                             'version' => 'v1.2.4', 'applied' => $tableStatus['admin_users'] ?? false,                      'action' => 'init_database'],
    ['label' => 'admin_users.role column',                                       'version' => 'v1.2.5', 'applied' => $hasRoleColumn,                                            'action' => 'add_role_column'],
    ['label' => 'events.theme column',                                           'version' => 'v2.1.1', 'applied' => $hasThemeColumn,                                           'action' => 'add_theme_column'],
    ['label' => 'events.email column',                                           'version' => 'v2.3.0', 'applied' => $hasEventEmailColumn,                                      'action' => 'add_event_email_column'],
    ['label' => 'programs.program_type column',                                  'version' => 'v2.4.0', 'applied' => $hasProgramTypeColumn,                                     'action' => 'add_program_type_column'],
    ['label' => 'programs.stream_url column',                                    'version' => 'v2.6.0', 'applied' => $hasStreamUrlColumn,                                       'action' => 'add_stream_url_column'],
    ['label' => 'Performance indexes (7 indexes)',                               'version' => 'v1.2.10','applied' => $hasIndexes,                                               'action' => 'add_indexes'],
    ['label' => 'contact_channels table',                                        'version' => 'v2.10.0','applied' => $hasContactChannelsTable,                                  'action' => 'add_contact_channels_table'],
    ['label' => 'artists + program_artists + artist_variants tables (v3.0.0)',   'version' => 'v3.0.0', 'applied' => $hasArtistTables,                                         'action' => 'add_artist_tables'],
    ['label' => 'events.timezone column',                                        'version' => 'v4.0.0', 'applied' => $hasTimezoneColumn,                                         'action' => 'add_timezone_column'],
] : [];
$pendingMigrations = array_filter($migrationChecks, fn($m) => !$m['applied'] && $m['action'] !== null);
$allMigrationsApplied = $dbExists && empty($pendingMigrations);

// 4. ICS Files
$icsDir = __DIR__ . '/ics';
$icsFiles = is_dir($icsDir) ? (glob($icsDir . '/*.ics') ?: []) : [];

// 5. Config
$configInfo = [
    'APP_VERSION'       => defined('APP_VERSION') ? APP_VERSION : null,
    'MULTI_EVENT_MODE'  => defined('MULTI_EVENT_MODE') ? (MULTI_EVENT_MODE ? 'true' : 'false') : null,
    'VENUE_MODE'        => defined('VENUE_MODE') ? VENUE_MODE : null,
    'PRODUCTION_MODE'   => defined('PRODUCTION_MODE') ? (PRODUCTION_MODE ? 'true' : 'false') : null,
    'DEFAULT_EVENT_SLUG' => defined('DEFAULT_EVENT_SLUG') ? DEFAULT_EVENT_SLUG : null,
];

// คำนวณ overall status
// setupComplete = PHP + dirs + tables พร้อม (programs เป็น optional)
$setupComplete = $allPhpOk && $allDirsOk && $allTablesOk;
$needsDbInit   = $allPhpOk && $allDirsOk && (!$dbExists || !$allTablesOk); // ผ่าน PHP+dirs แต่ยังไม่มี DB
$lockClass  = $isLocked ? ' locked-overlay' : ''; // CSS class สำหรับ action-bar เมื่อ locked

// 6. Dev files check — ไฟล์ที่ไม่จำเป็นใน production
$devFileGroups = [
    'docs' => [
        'label' => '📄 เอกสาร (Markdown)',
        'items' => [
            ['name' => 'README.md',            'type' => 'file'],
            ['name' => 'ICS_FORMAT.md',        'type' => 'file'],
            ['name' => 'DOCKER.md',            'type' => 'file'],
            ['name' => 'INSTALLATION.md',      'type' => 'file'],
            ['name' => 'SETUP.md',             'type' => 'file'],
            ['name' => 'API.md',               'type' => 'file'],
            ['name' => 'CHANGELOG.md',         'type' => 'file'],
            ['name' => 'TESTING.md',           'type' => 'file'],
            ['name' => 'SECURITY.md',          'type' => 'file'],
            ['name' => 'CONTRIBUTING.md',      'type' => 'file'],
            ['name' => 'PROJECT-STRUCTURE.md', 'type' => 'file'],
            ['name' => 'CLAUDE.md',            'type' => 'file'],
            ['name' => 'LICENSE',              'type' => 'file'],            
        ],
    ],
    'tests' => [
        'label' => '🧪 Test Suite',
        'items' => [
            ['name' => 'tests/', 'type' => 'dir'],
        ],
    ],
    'tools' => [
        'label' => '🔧 Development Tools',
        'items' => [
            ['name' => 'tools/', 'type' => 'dir'],
        ],
    ],
    'docker' => [
        'label' => '🐳 Docker Files',
        'items' => [
            ['name' => 'Dockerfile',             'type' => 'file'],
            ['name' => 'docker-compose.yml',     'type' => 'file'],
            ['name' => 'docker-compose.dev.yml', 'type' => 'file'],
            ['name' => '.dockerignore',          'type' => 'file'],
            ['name' => '.env.example',           'type' => 'file'],
        ],
    ],
    'nginx' => [
        'label' => '🌐 Nginx Config',
        'items' => [
            ['name' => 'nginx-clean-url.conf', 'type' => 'file'],
        ],
    ],
    'cicd' => [
        'label' => '🔄 CI/CD &amp; Scripts',
        'items' => [
            ['name' => '.github/', 'type' => 'dir'],
            ['name' => '.gitignore',     'type' => 'file'],
            ['name' => 'quick-test.bat', 'type' => 'file'],
            ['name' => 'quick-test.sh',  'type' => 'file'],
        ],
    ],
];

$devFilesExistCount = 0;
foreach ($devFileGroups as &$_dg) {
    foreach ($_dg['items'] as &$_di) {
        $checkPath = __DIR__ . '/' . rtrim($_di['name'], '/');
        $_di['exists'] = ($_di['type'] === 'dir') ? is_dir($checkPath) : file_exists($checkPath);
        if ($_di['exists']) $devFilesExistCount++;
    }
    unset($_di);
}
unset($_dg);
$allDevFilesClean = ($devFilesExistCount === 0);

// ตรวจสอบว่า admin ใช้ password เริ่มต้นหรือไม่
// ตรวจจาก DB ก่อน แล้ว fallback ไป config
// $defaultAdminPasswordText  = plaintext ถ้ารู้จัก ('admin123' / 'admin')
// $defaultAdminPasswordFromConfig = true ถ้า hash มาจาก config/admin.php (ไม่รู้ plaintext)
$usingDefaultPassword           = false;
$defaultAdminUsername           = null;
$defaultAdminPasswordText       = null;
$defaultAdminPasswordFromConfig = false;
if ($allTablesOk) {
    try {
        $_pwdb = new PDO('sqlite:' . $dbPath);
        $_pwRow = $_pwdb->query(
            "SELECT username, password_hash FROM admin_users WHERE is_active=1 ORDER BY id LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if ($_pwRow) {
            $defaultAdminUsername = $_pwRow['username'];
            $dbHash = $_pwRow['password_hash'];
            if (password_verify('admin123', $dbHash)) {
                $usingDefaultPassword     = true;
                $defaultAdminPasswordText = 'admin123';
            } elseif (password_verify('admin', $dbHash)) {
                $usingDefaultPassword     = true;
                $defaultAdminPasswordText = 'admin';
            } elseif (defined('ADMIN_PASSWORD_HASH') && $dbHash === ADMIN_PASSWORD_HASH) {
                // Hash ตรงกับ config — ยังไม่เคยเปลี่ยน password แต่ไม่รู้ plaintext
                $usingDefaultPassword           = true;
                $defaultAdminPasswordFromConfig = true;
            }
        }
        unset($_pwdb, $_pwRow, $dbHash);
    } catch (Exception $_e) {}
}
// Fallback: DB ยังไม่มี → ตรวจ config constant (เฉพาะกรณีที่ตาราง DB ยังไม่พร้อม)
if (!$usingDefaultPassword && !$allTablesOk && defined('ADMIN_PASSWORD_HASH')) {
    if (password_verify('admin123', ADMIN_PASSWORD_HASH)) {
        $usingDefaultPassword     = true;
        $defaultAdminPasswordText = 'admin123';
    } elseif (password_verify('admin', ADMIN_PASSWORD_HASH)) {
        $usingDefaultPassword     = true;
        $defaultAdminPasswordText = 'admin';
    } else {
        // config มี hash แต่ไม่รู้ plaintext — ถือว่า "ยังไม่ได้เปลี่ยน"
        $usingDefaultPassword           = true;
        $defaultAdminPasswordFromConfig = true;
    }
    if (!$defaultAdminUsername && defined('ADMIN_USERNAME')) {
        $defaultAdminUsername = ADMIN_USERNAME;
    }
}

?>
<!DOCTYPE html>
<html lang="<?= $setupLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Installation - <?php echo htmlspecialchars(function_exists('get_site_title') ? get_site_title() : (defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable')); ?></title>
    <?php if ($configLoaded): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <?php else: ?>
    <style>
        :root {
            --sakura-light: #FFB7C5;
            --sakura-medium: #F48FB1;
            --sakura-dark: #E91E63;
            --sakura-deep: #C2185B;
            --sakura-gradient: linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%);
            --sakura-bg: #FFF0F3;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 15px; line-height: 1.5; }
    </style>
    <?php endif; ?>
    <style>
        body {
            background: var(--sakura-bg, #FFF0F3);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .setup-container {
            max-width: 860px;
            margin: 0 auto;
        }

        /* Header */
        .setup-header {
            background: var(--sakura-gradient, linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%));
            color: white;
            padding: 28px 32px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .setup-header .icon { font-size: 2.4rem; }
        .setup-header h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .setup-header p { opacity: 0.9; font-size: 0.95rem; }

        /* Overall status banner */
        .status-banner {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 1rem;
        }
        .status-banner.complete { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-banner.ready    { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .status-banner.warning  { background: #fff8e1; color: #e65100; border: 1px solid #ffe0b2; }
        .status-banner.error    { background: #ffebee; color: #b71c1c; border: 1px solid #ffcdd2; }
        .status-banner .icon { font-size: 1.4rem; }

        /* Lock banner */
        .lock-banner {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            background: #fce4ec;
            color: #880e4f;
            border: 2px solid #f48fb1;
        }
        .lock-banner .icon { font-size: 1.5rem; }
        .lock-banner-text { flex: 1; }
        .lock-banner-text small { display: block; font-size: 0.8rem; font-weight: normal; opacity: 0.8; margin-top: 2px; }
        .btn-lock   { background: #880e4f; color: white; }
        .btn-lock:hover   { background: #6a0836; }
        .btn-unlock { background: #fff; color: #880e4f; border: 2px solid #f48fb1; }
        .btn-unlock:hover { background: #fce4ec; }

        /* Locked state overlay on action bars */
        .locked-overlay {
            position: relative;
        }
        .locked-overlay::after {
            content: '🔒 Locked';
            position: absolute;
            inset: 0;
            background: rgba(252, 228, 236, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            color: #880e4f;
            border-radius: 0 0 12px 12px;
            letter-spacing: 0.03em;
        }

        /* Messages */
        .messages { margin-bottom: 20px; }
        .msg {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .msg.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .msg.error   { background: #ffebee; color: #b71c1c; border-left: 4px solid #f44336; }
        .msg.info    { background: #e3f2fd; color: #1565c0; border-left: 4px solid #2196f3; }

        /* Section cards */
        .section {
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .section-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 500;
        }
        .badge-ok      { background: #e8f5e9; color: #2e7d32; }
        .badge-warning { background: #fff8e1; color: #e65100; }
        .badge-error   { background: #ffebee; color: #b71c1c; }
        .badge-info    { background: #e3f2fd; color: #1565c0; }
        .section-body { padding: 0; }

        /* Check rows */
        .check-row {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #fafafa;
            gap: 12px;
        }
        .check-row:last-child { border-bottom: none; }
        .check-icon { font-size: 1.1rem; min-width: 22px; text-align: center; }
        .check-label { flex: 1; font-size: 0.9rem; color: #444; }
        .check-label .fix-hint { font-size: 0.8rem; color: #999; display: block; margin-top: 2px; }
        .check-value {
            font-size: 0.82rem;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            background: #f5f5f5;
            color: #666;
        }
        .check-value.ok      { background: #e8f5e9; color: #2e7d32; }
        .check-value.warning { background: #fff8e1; color: #e65100; }
        .check-value.error   { background: #ffebee; color: #b71c1c; }

        /* Action section */
        .action-bar {
            padding: 16px 20px;
            background: #fafafa;
            border-top: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .action-bar p { font-size: 0.85rem; color: #666; flex: 1; min-width: 200px; }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary {
            background: var(--sakura-gradient, linear-gradient(135deg, #FFB7C5, #E91E63));
            color: white;
        }
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }
        .btn-secondary:hover { background: #eee; }
        .btn-success { background: #4caf50; color: white; }
        .btn-warning { background: #ff9800; color: white; }

        /* Code block */
        .code-block {
            background: #1e1e2e;
            color: #cdd6f4;
            padding: 14px 18px;
            border-radius: 8px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.83rem;
            line-height: 1.7;
            margin: 0;
            overflow-x: auto;
        }
        .code-block .cmd-label {
            color: #6c7086;
            font-size: 0.75rem;
            display: block;
            margin-bottom: 4px;
        }
        .code-block .cmd { color: #a6e3a1; }
        .code-block .comment { color: #6c7086; }

        /* Config table */
        .config-table {
            width: 100%;
            border-collapse: collapse;
        }
        .config-table td {
            padding: 10px 20px;
            border-bottom: 1px solid #fafafa;
            font-size: 0.88rem;
        }
        .config-table td:first-child { color: #666; width: 200px; font-family: monospace; font-size: 0.82rem; }
        .config-table td:last-child { color: #333; font-weight: 500; }
        .config-table tr:last-child td { border-bottom: none; }
        .config-na { color: #999 !important; font-style: italic; font-weight: normal !important; }

        /* Steps */
        .steps { padding: 16px 20px; }
        .step { display: flex; gap: 14px; margin-bottom: 14px; }
        .step:last-child { margin-bottom: 0; }
        .step-num {
            width: 28px;
            height: 28px;
            background: var(--sakura-dark, #E91E63);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .step-num.done { background: #4caf50; }
        .step-content { flex: 1; }
        .step-content strong { display: block; margin-bottom: 4px; font-size: 0.95rem; }
        .step-content p { font-size: 0.85rem; color: #666; margin-bottom: 8px; }

        /* Footer */
        .setup-footer {
            text-align: center;
            padding: 20px;
            color: #888;
            font-size: 0.85rem;
        }
        .setup-footer a { color: var(--sakura-dark, #E91E63); text-decoration: none; }

        /* Divider in code */
        .code-section { padding: 16px 20px; }

        /* Language switcher */
        .lang-switcher-setup {
            display: flex;
            gap: 4px;
            align-items: center;
            flex-shrink: 0;
        }
        .lang-btn-setup {
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.55);
            background: transparent;
            color: rgba(255,255,255,0.85);
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: background 0.15s;
            text-decoration: none;
            display: inline-block;
        }
        .lang-btn-setup.active {
            background: rgba(255,255,255,0.3);
            color: #fff;
            border-color: rgba(255,255,255,0.8);
        }
        .lang-btn-setup:hover { background: rgba(255,255,255,0.2); }

        @media (max-width: 600px) {
            body { padding: 12px; }
            .setup-header { padding: 20px; flex-direction: column; text-align: center; gap: 10px; }
            .action-bar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="setup-container">

    <!-- Header -->
    <div class="setup-header">
        <div class="icon">🌸</div>
        <div style="flex:1;">
            <h1>Setup &amp; Installation</h1>
            <p><?php echo htmlspecialchars(function_exists('get_site_title') ? get_site_title() : (defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable')); ?> — <?= $isEn ? 'Check readiness and configure before going live' : 'ตรวจสอบความพร้อมและ setup ก่อนเริ่มใช้งาน' ?></p>
        </div>
        <!-- Language switcher -->
        <div class="lang-switcher-setup">
            <?php $langBase = strtok($_SERVER['REQUEST_URI'] ?? '?', '?'); ?>
            <a href="<?= htmlspecialchars($langBase) ?>?lang=th" class="lang-btn-setup <?= $isEn ? '' : 'active' ?>">TH</a>
            <a href="<?= htmlspecialchars($langBase) ?>?lang=en" class="lang-btn-setup <?= $isEn ? 'active' : '' ?>">EN</a>
        </div>
        <?php if ($configLoaded && function_exists('is_logged_in') && is_logged_in()): ?>
        <div style="font-size:0.82rem; opacity:0.9; text-align:right; white-space:nowrap;">
            <?php
            $currentUser = '';
            if (!empty($_SESSION['admin_display_name'])) {
                $currentUser = $_SESSION['admin_display_name'];
            } elseif (!empty($_SESSION['admin_username'])) {
                $currentUser = $_SESSION['admin_username'];
            }
            ?>
            <?php if ($currentUser): ?>
            👤 <?php echo htmlspecialchars($currentUser); ?><br>
            <?php endif; ?>
            <a href="admin/login.php?logout=1" style="color:rgba(255,255,255,0.8); font-size:0.78rem;"><?= $isEn ? 'Logout' : 'ออกจากระบบ' ?></a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Lock Banner (แสดงเสมอเมื่อ locked) -->
    <?php if ($isLocked): ?>
    <div class="lock-banner">
        <span class="icon">🔒</span>
        <div class="lock-banner-text">
            <strong><?= $isEn ? 'Setup Page is Locked' : 'Setup Page ถูก Lock แล้ว' ?></strong>
            <small><?= $isEn ? 'All setup actions are blocked — click Unlock to re-enable' : 'Setup actions ทั้งหมดถูกปิดกั้น — กด Unlock เพื่อเปิดใช้งานอีกครั้ง' ?></small>
        </div>
        <form method="POST" onsubmit="return confirm(setupI18n.confirmUnlock)">
            <button type="submit" name="action" value="unlock" class="btn btn-unlock">
                🔓 Unlock
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Overall Status -->
    <?php if ($setupComplete): ?>
    <div class="status-banner complete">
        <span class="icon">✅</span>
        <div>
            <strong><?= $isEn ? 'Setup Complete!' : 'Setup เสร็จสมบูรณ์!' ?></strong>
            <?php if ($programCount > 0): ?>
            <?= $isEn ? 'System ready — ' . number_format($programCount) . ' programs in database' : 'ระบบพร้อมใช้งานแล้ว — มี ' . number_format($programCount) . ' programs ใน database' ?>
            <?php else: ?>
            <?= $isEn ? 'System ready — no programs yet (add via Admin Panel later)' : 'ระบบพร้อมใช้งานแล้ว — ยังไม่มีข้อมูล programs (เพิ่มทีหลังได้ผ่าน Admin Panel)' ?>
            <?php endif; ?>
        </div>
        <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
            <?php if (!$isLocked): ?>
            <form method="POST" onsubmit="return confirm(setupI18n.confirmLock)">
                <button type="submit" name="action" value="lock" class="btn btn-lock" title="<?= $isEn ? 'Lock setup to prevent accidental changes' : 'ล็อก setup เพื่อป้องกันการแก้ไขโดยไม่ตั้งใจ' ?>">
                    🔒 Lock Setup
                </button>
            </form>
            <?php endif; ?>
            <a href="<?php echo $programCount > 0 ? 'index.php' : 'admin/'; ?>" class="btn btn-success">
                <?= $programCount > 0 ? ($isEn ? 'Go to Homepage →' : 'ไปหน้าหลัก →') : ($isEn ? 'Go to Admin →' : 'ไป Admin →') ?>
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="status-banner warning">
        <span class="icon">⚠️</span>
        <div>
            <strong><?= $isEn ? 'Setup Incomplete' : 'ยังไม่ได้ทำ Setup' ?></strong>
            <?= $isEn ? 'Please complete all steps below before use.' : 'กรุณาทำตามขั้นตอนด้านล่างให้ครบก่อนใช้งาน' ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Messages -->
    <?php if (!empty($messages)): ?>
    <div class="messages">
        <?php foreach ($messages as $msg): ?>
        <div class="msg <?php echo $msg['type']; ?>"><?php echo $msg['text']; ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Step-by-step Overview -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">📋 <?= $isEn ? 'Setup Steps Overview' : 'ขั้นตอน Setup ทั้งหมด' ?></span>
            <span class="section-badge <?php echo $setupComplete ? 'badge-ok' : 'badge-warning'; ?>">
                <?= $setupComplete ? ($isEn ? 'Complete' : 'เสร็จสมบูรณ์') : ($isEn ? 'Pending' : 'รอดำเนินการ') ?>
            </span>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-num <?php echo $allPhpOk ? 'done' : ''; ?>">
                    <?php echo $allPhpOk ? '✓' : '1'; ?>
                </div>
                <div class="step-content">
                    <strong><?= $isEn ? 'System Requirements Check' : 'ตรวจสอบ System Requirements' ?></strong>
                    <p>PHP 8.1+ <?= $isEn ? 'with extensions' : 'พร้อม extensions' ?>: PDO, PDO SQLite, mbstring, GD+FreeType</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo $allDirsOk ? 'done' : ''; ?>">
                    <?php echo $allDirsOk ? '✓' : '2'; ?>
                </div>
                <div class="step-content">
                    <strong><?= $isEn ? 'Create Required Directories' : 'สร้าง Directories ที่จำเป็น' ?></strong>
                    <p><?= $isEn ? 'Folders' : 'โฟลเดอร์' ?> <code>data/</code>, <code>cache/</code>, <code>cache/images/</code>, <code>cache/favorites/</code>, <code>backups/</code>, <code>ics/</code>, <code>fonts/</code></p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo $allTablesOk ? 'done' : ''; ?>">
                    <?php echo $allTablesOk ? '✓' : '3'; ?>
                </div>
                <div class="step-content">
                    <strong><?= $isEn ? 'Create Database Tables' : 'สร้างตาราง Database' ?></strong>
                    <p><?= $isEn ? 'Create tables: programs, events, program_requests, credits, admin_users and indexes' : 'สร้างตาราง programs, events, program_requests, credits, admin_users และ indexes' ?></p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo $programCount > 0 ? 'done' : ''; ?>"
                     style="<?php echo $programCount == 0 && $allTablesOk ? 'background:#9e9e9e;' : ''; ?>">
                    <?php echo $programCount > 0 ? '✓' : '4'; ?>
                </div>
                <div class="step-content">
                    <strong><?= $isEn ? 'Import Programs Data' : 'Import ข้อมูล Programs' ?> <span style="font-size:0.78rem; font-weight:normal; color:#888; background:#f5f5f5; padding:1px 6px; border-radius:10px;">optional</span></strong>
                    <p><?= $isEn ? 'Place .ics files in <code>ics/</code> and import via CLI or Admin Panel — can be added later' : 'วางไฟล์ .ics ใน <code>ics/</code> แล้ว import ผ่าน CLI หรือ Admin Panel — เพิ่มทีหลังได้' ?></p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo ($allTablesOk && !$usingDefaultPassword) ? 'done' : ''; ?>">
                    <?php echo ($allTablesOk && !$usingDefaultPassword) ? '✓' : '5'; ?>
                </div>
                <div class="step-content">
                    <strong><?= $isEn ? 'Set Admin Password' : 'ตั้งค่า Admin Password' ?></strong>
                    <p><?= $isEn ? 'Access Admin Panel and change the default password' : 'เข้า Admin Panel และเปลี่ยน password เริ่มต้น' ?></p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo $allDevFilesClean ? 'done' : ''; ?>"
                     style="<?php echo !$allDevFilesClean ? 'background:#9e9e9e;' : ''; ?>">
                    <?php echo $allDevFilesClean ? '✓' : '6'; ?>
                </div>
                <div class="step-content">
                    <strong>Production Cleanup <span style="font-size:0.78rem; font-weight:normal; color:#888; background:#f5f5f5; padding:1px 6px; border-radius:10px;">optional</span></strong>
                    <p><?= $isEn ? 'Remove dev/documentation files not needed in production' : 'ลบไฟล์ dev/documentation ที่ไม่จำเป็นใน production environment' ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick DB Init Banner (เฉพาะเมื่อผ่าน PHP+dirs แต่ยังไม่มี DB) -->
    <?php if ($needsDbInit && !$isLocked): ?>
    <div style="background:#fff3e0; border:2px solid #ffb74d; border-radius:12px; padding:20px 24px; margin-bottom:20px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <div style="font-size:2rem;">🗄️</div>
        <div style="flex:1; min-width:200px;">
            <strong style="font-size:1rem; color:#e65100; display:block; margin-bottom:4px;"><?= $isEn ? 'Database Not Yet Created' : 'ยังไม่ได้สร้าง Database' ?></strong>
            <span style="font-size:0.88rem; color:#795548;"><?= $isEn ? 'Click Initialize to create all tables at once, including admin user and default event' : 'กด Initialize เพื่อสร้างตารางทั้งหมดครั้งเดียว พร้อม admin user และ default event' ?></span>
        </div>
        <form method="POST" onsubmit="return confirm(setupI18n.confirmInitDb)">
            <button type="submit" name="action" value="init_database" class="btn btn-warning" style="font-size:1rem; padding:12px 24px;">
                🗄️ Initialize Database
            </button>
        </form>
    </div>
    <?php elseif ($needsDbInit && $isLocked): ?>
    <div style="background:#fff3e0; border:2px solid #ffb74d; border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; gap:12px; opacity:0.6;">
        <div style="font-size:1.5rem;">🗄️</div>
        <div>
            <strong style="color:#e65100;"><?= $isEn ? 'Database Not Yet Created' : 'ยังไม่ได้สร้าง Database' ?></strong>
            <span style="font-size:0.85rem; color:#795548; margin-left:8px;">🔒 <?= $isEn ? 'Unlock first to initialize' : 'Unlock ก่อนเพื่อ initialize' ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Step 1: PHP Requirements -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $allPhpOk ? '✅' : '❌'; ?> <?= $isEn ? 'Step 1 — System Requirements' : 'ขั้นตอนที่ 1 — System Requirements' ?>
            </span>
            <span class="section-badge <?php echo $allPhpOk ? 'badge-ok' : 'badge-error'; ?>">
                <?= $allPhpOk ? ($isEn ? 'Pass' : 'ผ่าน') : ($isEn ? 'Fail' : 'ไม่ผ่าน') ?>
            </span>
        </div>
        <div class="section-body">
            <?php foreach ($phpChecks as $key => $check):
                $isOptional = !empty($check['optional']);
            ?>
            <div class="check-row">
                <span class="check-icon"><?php echo $check['ok'] ? '✅' : ($isOptional ? '⚠️' : '❌'); ?></span>
                <span class="check-label">
                    <?php echo htmlspecialchars($check['label']); ?>
                    <?php if ($isOptional && !$check['ok']): ?>
                    <span style="color:#999; font-size:0.8rem; margin-left:4px;"><?= $isEn ? '(optional)' : '(optional)' ?></span>
                    <?php endif; ?>
                    <?php if (!$check['ok']): ?>
                    <span class="fix-hint">💡 <?php echo htmlspecialchars($check['fix']); ?></span>
                    <?php endif; ?>
                </span>
                <span class="check-value <?php echo $check['ok'] ? 'ok' : ($isOptional ? 'warning' : 'error'); ?>">
                    <?php echo htmlspecialchars($check['value']); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Step 2: Directories -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $allDirsOk ? '✅' : '⚠️'; ?> <?= $isEn ? 'Step 2 — Directories &amp; Permissions' : 'ขั้นตอนที่ 2 — Directories &amp; Permissions' ?>
            </span>
            <span class="section-badge <?php echo $allDirsOk ? 'badge-ok' : 'badge-warning'; ?>">
                <?= $allDirsOk ? ($isEn ? 'Ready' : 'พร้อม') : ($isEn ? 'Needs Configuration' : 'ต้องการการตั้งค่า') ?>
            </span>
        </div>
        <div class="section-body">
            <?php foreach ($dirChecks as $dc): ?>
            <div class="check-row">
                <span class="check-icon"><?php echo $dc['ok'] ? '✅' : ($dc['exists'] ? '⚠️' : '📁'); ?></span>
                <span class="check-label">
                    <code><?php echo htmlspecialchars($dc['label']); ?></code>
                    <span style="color:#999; font-size:0.82rem; margin-left:6px;"><?php echo htmlspecialchars($dc['purpose']); ?></span>
                    <?php if ($dc['exists'] && !$dc['writable'] && $dc['need_write']): ?>
                    <span class="fix-hint">💡 chmod 755 <?php echo htmlspecialchars($dc['label']); ?></span>
                    <?php endif; ?>
                </span>
                <span class="check-value <?php echo $dc['ok'] ? 'ok' : (!$dc['exists'] ? 'error' : 'warning'); ?>">
                    <?php if ($dc['exists']): ?>
                        <?php echo $dc['need_write'] ? ($dc['writable'] ? 'writable' : 'not writable') : 'exists'; ?>
                    <?php else: ?>
                        missing
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($missingDirs)): ?>
        <div class="action-bar<?php echo $lockClass; ?>">
            <p><?= $isEn ? 'Missing directories found — click to create automatically, or create via CLI' : 'พบโฟลเดอร์ที่ขาด — กดปุ่มเพื่อสร้างอัตโนมัติ หรือสร้างด้วยตัวเองผ่าน CLI' ?></p>
            <form method="POST">
                <button type="submit" name="action" value="create_dirs" class="btn btn-primary">
                    📁 <?= $isEn ? 'Create Missing Directories' : 'สร้างโฟลเดอร์ที่ขาด' ?>
                </button>
            </form>
        </div>
        <?php elseif (!$allDirsOk): ?>
        <div class="action-bar">
            <p><?= $isEn ? 'Some directories are not writable. Please check permissions.' : 'บางโฟลเดอร์ไม่มีสิทธิ์เขียน กรุณาตรวจสอบ permissions' ?></p>
        </div>
        <?php endif; ?>

        <?php if ($fontsDirExists): ?>
        <!-- Font Files (v3.3.0) -->
        <div class="check-row" style="border-top:2px solid #f0f0f0; font-weight:600; font-size:0.9rem; color:#555; background:#fafafa;">
            🔤 <?= $isEn ? 'Font Files for Image Export' : 'Font Files สำหรับ Image Export' ?>
            <span style="font-size:0.8rem; font-weight:normal; color:#999; margin-left:8px;">
                <?= $isEn ? 'Place fonts in' : 'วางไฟล์ font ใน' ?> <code>fonts/</code>
            </span>
        </div>
            <?php
            // Pre-compute which groups have at least one found font
            $_groupFound = [];
            foreach ($fontChecks as $_f) {
                if ($_f['found']) $_groupFound[$_f['group']] = true;
            }
            $_shownMissing = [];
            foreach ($fontChecks as $fc):
                // Found fonts: always show
                // Missing fonts: show one per group only if that group has no found font
                if (!$fc['found']) {
                    if (!empty($_groupFound[$fc['group']])) continue;
                    if (isset($_shownMissing[$fc['group']])) continue;
                    $_shownMissing[$fc['group']] = true;
                }
            ?>
            <div class="check-row">
                <span class="check-icon"><?= $fc['found'] ? '✅' : '⚪' ?></span>
                <span class="check-label">
                    <code><?= htmlspecialchars($fc['file']) ?></code>
                    <span style="color:#999; font-size:0.82rem; margin-left:6px;"><?= htmlspecialchars($fc['role']) ?></span>
                </span>
                <span class="check-value <?= $fc['found'] ? 'ok' : 'warning' ?>">
                    <?= $fc['found'] ? htmlspecialchars($fc['size']) : ($isEn ? 'not found' : 'ไม่พบ') ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php if (!$hasThaiFont): ?>
            <div class="check-row" style="background:#fff8f0;">
                <span class="check-icon">⚠️</span>
                <span class="check-label" style="color:#e65100; font-size:0.85rem;">
                    <?= $isEn
                        ? 'No Thai font found. Image export will use system fonts (may not render Thai correctly on shared hosting). Download <strong>NotoSansThai-Regular.ttf</strong> from Google Fonts and place it in <code>fonts/</code>.'
                        : 'ไม่พบ Thai font — Image export จะใช้ system font (อาจแสดงภาษาไทยไม่ถูกต้องบน shared hosting) ดาวน์โหลด <strong>NotoSansThai-Regular.ttf</strong> จาก Google Fonts แล้ววางใน <code>fonts/</code>' ?>
                </span>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Favorites HMAC Secret check -->
        <div class="check-row" style="border-top:2px solid #f0f0f0; font-weight:600; font-size:0.9rem; color:#555; background:#fafafa;">
            ⭐ <?= $isEn ? 'Favorites System' : 'ระบบ Favorites' ?>
            <span style="font-size:0.8rem; font-weight:normal; color:#999; margin-left:8px;">
                <?= $isEn ? 'Anonymous favorites for tracking artists' : 'ติดตามศิลปินแบบ anonymous' ?>
            </span>
        </div>
        <div class="check-row">
            <span class="check-icon"><?= $_favSecretOk ? '✅' : '⚠️' ?></span>
            <span class="check-label">
                HMAC Secret
                <span style="color:#999; font-size:0.82rem; margin-left:6px;"><?= $isEn ? 'config/favorites.php' : 'config/favorites.php' ?></span>
                <?php if (!$_favSecretOk): ?>
                <span class="fix-hint">💡 <?= $isEn
                    ? 'Run <code>php tools/generate-favorites-secret.php</code> then paste into <code>config/favorites.php</code>'
                    : 'รัน <code>php tools/generate-favorites-secret.php</code> แล้ว paste ลงใน <code>config/favorites.php</code>' ?></span>
                <?php endif; ?>
            </span>
            <span class="check-value <?= $_favSecretOk ? 'ok' : 'warning' ?>">
                <?= $_favSecretOk ? ($isEn ? 'configured' : 'ตั้งค่าแล้ว') : ($isEn ? 'not configured' : 'ยังไม่ได้ตั้งค่า') ?>
            </span>
        </div>
    </div>

    <!-- Step 3: Database -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $allTablesOk ? '✅' : '⚠️'; ?> <?= $isEn ? 'Step 3 — Database Setup' : 'ขั้นตอนที่ 3 — Database Setup' ?>
            </span>
            <span class="section-badge <?php echo $allTablesOk ? 'badge-ok' : 'badge-warning'; ?>">
                <?= $allTablesOk ? ($isEn ? 'Ready' : 'พร้อม') : ($dbExists ? ($isEn ? 'Needs Migration' : 'ต้องการ Migration') : ($isEn ? 'Not Created' : 'ยังไม่ได้สร้าง')) ?>
            </span>
        </div>
        <div class="section-body">
            <!-- DB File -->
            <div class="check-row">
                <span class="check-icon"><?php echo $dbExists ? '✅' : '❌'; ?></span>
                <span class="check-label">
                    <?= $isEn ? 'Database File' : 'ไฟล์ Database' ?>
                    <span style="color:#999;font-size:0.8rem;margin-left:6px;">
                        <?php echo htmlspecialchars($dbPath); ?>
                    </span>
                </span>
                <span class="check-value <?php echo $dbExists ? 'ok' : 'error'; ?>">
                    <?php if ($dbExists): ?>
                        <?php echo number_format(filesize($dbPath) / 1024, 1); ?> KB
                    <?php else: ?>
                        not found
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($dbError): ?>
            <div class="check-row">
                <span class="check-icon">❌</span>
                <span class="check-label" style="color:#b71c1c;">Database Error: <?php echo htmlspecialchars($dbError); ?></span>
            </div>
            <?php endif; ?>

            <!-- Tables -->
            <?php
            $tableLabels = $isEn ? [
                'programs'         => 'programs — show/performance list',
                'events'           => 'events — conventions/meta-events',
                'program_requests' => 'program_requests — user requests',
                'credits'          => 'credits — credits & references',
                'admin_users'      => 'admin_users — admin accounts',
            ] : [
                'programs'         => 'programs — รายการ shows/performances',
                'events'           => 'events — conventions/meta-events',
                'program_requests' => 'program_requests — คำขอจากผู้ใช้',
                'credits'          => 'credits — credits & references',
                'admin_users'      => 'admin_users — ผู้ใช้ admin',
            ];
            foreach ($tableLabels as $table => $label): ?>
            <div class="check-row">
                <span class="check-icon"><?php echo ($tableStatus[$table] ?? false) ? '✅' : '⬜'; ?></span>
                <span class="check-label"><code><?php echo $table; ?></code> <span style="color:#999;font-size:0.82rem;"><?php echo substr($label, strlen($table) + 3); ?></span></span>
                <span class="check-value <?php echo ($tableStatus[$table] ?? false) ? 'ok' : 'error'; ?>">
                    <?php echo ($tableStatus[$table] ?? false) ? 'exists' : 'missing'; ?>
                </span>
            </div>
            <?php endforeach; ?>

            <!-- Role Column -->
            <?php if ($tableStatus['admin_users'] ?? false): ?>
            <div class="check-row">
                <span class="check-icon"><?php echo $hasRoleColumn ? '✅' : '⚠️'; ?></span>
                <span class="check-label"><code>admin_users.role</code> <span style="color:#999;font-size:0.82rem;">Role-based access control</span></span>
                <span class="check-value <?php echo $hasRoleColumn ? 'ok' : 'warning'; ?>">
                    <?php echo $hasRoleColumn ? 'exists' : 'missing'; ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Event Email Column -->
            <?php if ($tableStatus['events'] ?? false): ?>
            <div class="check-row">
                <span class="check-icon"><?php echo $hasEventEmailColumn ? '✅' : '⚠️'; ?></span>
                <span class="check-label"><code>events.email</code> <span style="color:#999;font-size:0.82rem;"><?= $isEn ? 'Used in ICS export ORGANIZER mailto' : 'ใช้ใน ICS export ORGANIZER mailto' ?></span></span>
                <span class="check-value <?php echo $hasEventEmailColumn ? 'ok' : 'warning'; ?>">
                    <?php echo $hasEventEmailColumn ? 'exists' : 'missing'; ?>
                </span>
            </div>
            <!-- Event Timezone Column -->
            <div class="check-row">
                <span class="check-icon"><?php echo $hasTimezoneColumn ? '✅' : '⚠️'; ?></span>
                <span class="check-label"><code>events.timezone</code> <span style="color:#999;font-size:0.82rem;"><?= $isEn ? 'Per-event timezone for ICS export & display (v4.0.0)' : 'Timezone ต่อ event สำหรับ ICS export และแสดงผล (v4.0.0)' ?></span></span>
                <span class="check-value <?php echo $hasTimezoneColumn ? 'ok' : 'warning'; ?>">
                    <?php echo $hasTimezoneColumn ? 'exists' : 'missing'; ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Program Type Column -->
            <?php if ($tableStatus['programs'] ?? false): ?>
            <div class="check-row">
                <span class="check-icon"><?php echo $hasProgramTypeColumn ? '✅' : '⚠️'; ?></span>
                <span class="check-label"><code>programs.program_type</code> <span style="color:#999;font-size:0.82rem;"><?= $isEn ? 'Program type (stage, booth, etc.)' : 'ประเภท program (stage, booth, ฯลฯ)' ?></span></span>
                <span class="check-value <?php echo $hasProgramTypeColumn ? 'ok' : 'warning'; ?>">
                    <?php echo $hasProgramTypeColumn ? 'exists' : 'missing'; ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Indexes -->
            <div class="check-row">
                <span class="check-icon"><?php echo $hasIndexes ? '✅' : '⚠️'; ?></span>
                <span class="check-label">Performance Indexes <span style="color:#999;font-size:0.82rem;"><?= $isEn ? 'Speed up queries 2–5x' : 'เพิ่มความเร็ว query 2-5x' ?></span></span>
                <span class="check-value <?php echo $hasIndexes ? 'ok' : 'warning'; ?>">
                    <?php echo $hasIndexes ? 'ok' : 'missing'; ?>
                </span>
            </div>
        </div>

        <!-- Actions -->
        <?php if (!$dbExists || !$allTablesOk): ?>
        <div class="action-bar<?php echo $lockClass; ?>" style="flex-wrap:wrap; gap:12px;">
            <?php if (!$dbExists || empty($tableStatus) || !($tableStatus['programs'] ?? false)): ?>
            <div style="flex:1; min-width:200px;">
                <p style="margin-bottom:8px;"><strong>Fresh Install</strong> — <?= $isEn ? 'Create all tables at once' : 'สร้างตารางทั้งหมดใหม่ทีเดียว' ?></p>
                <form method="POST" onsubmit="return confirm(setupI18n.confirmInitDb)">
                    <button type="submit" name="action" value="init_database" class="btn btn-primary">
                        🗄️ Initialize Database <?= $isEn ? '(create all tables)' : '(สร้างทุกตาราง)' ?>
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div style="flex:1; min-width:200px;">
                <p style="margin-bottom:8px;"><strong>Existing Install</strong> — <?= $isEn ? 'Add/update missing items' : 'เพิ่ม/อัพเดทสิ่งที่ขาด' ?></p>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php if (!($tableStatus['admin_users'] ?? false)): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="init_database" class="btn btn-warning">
                            + admin_users table
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (($tableStatus['admin_users'] ?? false) && !$hasRoleColumn): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_role_column" class="btn btn-warning">
                            + role column
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (($tableStatus['events'] ?? false) && !$hasThemeColumn): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_theme_column" class="btn btn-warning">
                            + theme column
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (($tableStatus['events'] ?? false) && !$hasEventEmailColumn): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_event_email_column" class="btn btn-warning">
                            + events.email column
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (($tableStatus['programs'] ?? false) && !$hasProgramTypeColumn): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_program_type_column" class="btn btn-warning">
                            + programs.program_type column
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (($tableStatus['programs'] ?? false) && !$hasTitleColumn): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="fix_programs_title" class="btn btn-warning">
                            Fix programs.title
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (($tableStatus['programs'] ?? false) && !$hasStreamUrlColumn): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_stream_url_column" class="btn btn-warning">
                            + programs.stream_url column
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (!$hasContactChannelsTable): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_contact_channels_table" class="btn btn-warning">
                            + contact_channels table
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (!$hasIndexes && ($tableStatus['programs'] ?? false)): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_indexes" class="btn btn-secondary">
                            + indexes
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (!$hasArtistTables && ($tableStatus['programs'] ?? false)): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_artist_tables" class="btn btn-warning">
                            + artist tables (v3.0.0)
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (($tableStatus['events'] ?? false) && !$hasTimezoneColumn): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_timezone_column" class="btn btn-warning">
                            + events.timezone column (v4.0.0)
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="flex:1; min-width:200px;">
                <p style="margin-bottom:8px;"><strong><?= $isEn ? 'Or run migrations via CLI' : 'หรือรัน migrations ผ่าน CLI' ?></strong></p>
                <div class="code-block" style="font-size:0.78rem;">
<span class="comment"><?= $isEn ? '# Recommended order for fresh install:' : '# ลำดับที่แนะนำสำหรับ fresh install:' ?></span>
<span class="cmd">cd tools</span>
php migrate-add-requests-table.php
php migrate-add-credits-table.php
php migrate-add-events-meta-table.php
php migrate-add-admin-users-table.php
php migrate-add-role-column.php
php migrate-rename-tables-columns.php
php migrate-add-indexes.php
php migrate-add-theme-column.php
php migrate-add-event-email-column.php
php migrate-add-program-type-column.php
php migrate-add-stream-url-column.php
php migrate-add-contact-channels-table.php
php migrate-add-artist-variants-table.php</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Migration Check Section -->
    <?php if ($dbExists): ?>
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $allMigrationsApplied ? '✅' : '⚠️'; ?> Migration Status
            </span>
            <span class="section-badge <?php echo $allMigrationsApplied ? 'badge-ok' : 'badge-warning'; ?>">
                <?php
                $pendingCount = count($pendingMigrations);
                echo $allMigrationsApplied ? ($isEn ? 'All applied' : 'ทุก migration ผ่านแล้ว') : "$pendingCount pending";
                ?>
            </span>
        </div>
        <div class="section-body">
            <table style="width:100%; border-collapse:collapse; font-size:0.88rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);">
                        <th style="text-align:left; padding:6px 8px; width:36px;"><?= $isEn ? 'Status' : 'สถานะ' ?></th>
                        <th style="text-align:left; padding:6px 8px;">Migration</th>
                        <th style="text-align:left; padding:6px 8px; width:80px;">Version</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($migrationChecks as $m): ?>
                    <tr style="border-bottom:1px solid var(--border); <?php echo $m['applied'] ? '' : 'background:rgba(255,160,0,0.06);'; ?>">
                        <td style="padding:7px 8px; font-size:1rem; text-align:center;">
                            <?php echo $m['applied'] ? '✅' : '❌'; ?>
                        </td>
                        <td style="padding:7px 8px; <?php echo $m['applied'] ? '' : 'font-weight:600; color:var(--warning,#c77b00);'; ?>">
                            <?php echo htmlspecialchars($m['label']); ?>
                            <?php if (!$m['applied']): ?>
                                <span style="font-size:0.78rem; font-weight:400; color:#888; margin-left:6px;"><?= $isEn ? '(not applied)' : '(ยังไม่ได้รัน)' ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:7px 8px; color:#888; font-size:0.8rem; font-family:monospace;">
                            <?php echo htmlspecialchars($m['version']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!$allMigrationsApplied): ?>
            <div class="action-bar<?php echo $lockClass; ?>" style="margin-top:16px;">
                <div>
                    <p style="margin:0 0 8px;"><strong><?= $isEn ? 'Found ' . count($pendingMigrations) . ' pending migration(s)' : 'พบ ' . count($pendingMigrations) . ' migration ที่ยังไม่ได้รัน' ?></strong></p>
                    <form method="POST" onsubmit="return confirm(setupI18n.confirmRunMigrations)">
                        <button type="submit" name="action" value="run_all_migrations" class="btn btn-primary">
                            🚀 Run All Pending Migrations (<?php echo count($pendingMigrations); ?>)
                        </button>
                    </form>
                </div>
                <div style="font-size:0.82rem; color:#888; align-self:flex-end;">
                    <?= $isEn ? 'Safe — all migrations check before running, will not overwrite existing data' : 'ปลอดภัย — ทุก migration ตรวจสอบก่อนรัน ไม่ overwrite ข้อมูลเดิม' ?>
                </div>
            </div>
            <?php else: ?>
            <div class="check-row" style="color:#2e7d32; font-weight:500;">
                ✅ <?= $isEn ? 'Database is up to date — all migrations applied' : 'Database เป็นปัจจุบัน — ทุก migration ผ่านแล้ว' ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Step 4: Data Import -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $programCount > 0 ? '✅' : '⚠️'; ?> <?= $isEn ? 'Step 4 — Import Programs Data' : 'ขั้นตอนที่ 4 — Import ข้อมูล Programs' ?>
            </span>
            <span class="section-badge <?php echo $programCount > 0 ? 'badge-ok' : 'badge-warning'; ?>">
                <?= $programCount > 0 ? $programCount . ' programs' : ($isEn ? 'No data' : 'ยังไม่มีข้อมูล') ?>
            </span>
        </div>
        <div class="section-body">
            <!-- Programs count -->
            <div class="check-row">
                <span class="check-icon"><?php echo $programCount > 0 ? '✅' : '📭'; ?></span>
                <span class="check-label"><?= $isEn ? 'Programs in Database' : 'Programs ใน Database' ?></span>
                <span class="check-value <?php echo $programCount > 0 ? 'ok' : 'warning'; ?>">
                    <?php echo number_format($programCount); ?> programs
                </span>
            </div>

            <!-- Events (conventions) count -->
            <?php if ($allTablesOk): ?>
            <div class="check-row">
                <span class="check-icon"><?php echo $eventCount > 0 ? '✅' : '⚠️'; ?></span>
                <span class="check-label"><?= $isEn ? 'Events (Conventions) in Database' : 'Events (Conventions) ใน Database' ?></span>
                <span class="check-value <?php echo $eventCount > 0 ? 'ok' : 'warning'; ?>">
                    <?php echo number_format($eventCount); ?> events
                </span>
            </div>
            <?php endif; ?>

            <!-- ICS Files -->
            <div class="check-row">
                <span class="check-icon"><?php echo count($icsFiles) > 0 ? '📂' : '📁'; ?></span>
                <span class="check-label">
                    <?= $isEn ? 'ICS Files in' : 'ไฟล์ ICS ใน' ?> <code>ics/</code>
                    <?php if (count($icsFiles) > 0): ?>
                    <span style="color:#999;font-size:0.8rem;display:block;margin-top:2px;">
                        <?php foreach ($icsFiles as $f): ?><?php echo basename($f); ?>&ensp;<?php endforeach; ?>
                    </span>
                    <?php endif; ?>
                </span>
                <span class="check-value <?php echo count($icsFiles) > 0 ? 'ok' : ''; ?>">
                    <?php echo count($icsFiles); ?> files
                </span>
            </div>
        </div>

        <?php if ($programCount == 0): ?>
        <div class="action-bar<?php echo $lockClass; ?>" style="flex-wrap:wrap; gap:16px;">
            <div style="flex:1; min-width:220px;">
                <p><strong><?= $isEn ? 'Method 1: Upload ICS via Admin Panel' : 'วิธีที่ 1: Upload ICS ผ่าน Admin Panel' ?></strong></p>
                <p style="margin-top:4px;"><?= $isEn ? 'Go to Admin → Programs tab → click "📤 Import ICS"' : 'ไป Admin → แท็บ Programs → กดปุ่ม "📤 Import ICS"' ?></p>
                <a href="admin/" class="btn btn-primary" style="margin-top:8px;">
                    <?= $isEn ? 'Go to Admin Panel &rarr;' : 'ไป Admin Panel &rarr;' ?>
                </a>
            </div>
            <div style="flex:1; min-width:220px;">
                <p style="margin-bottom:6px;"><strong><?= $isEn ? 'Method 2: Import via CLI' : 'วิธีที่ 2: Import ผ่าน CLI' ?></strong></p>
                <div class="code-block" style="font-size:0.78rem;">
<span class="comment"><?= $isEn ? '# Place .ics files in ics/ then run:' : '# วางไฟล์ .ics ใน ics/ แล้วรัน:' ?></span>
<span class="cmd">cd tools</span>
php import-ics-to-sqlite.php

<span class="comment"><?= $isEn ? '# Or specify an event:' : '# หรือระบุ event:' ?></span>
php import-ics-to-sqlite.php --event=slug</div>
            </div>
            <div style="flex:1; min-width:220px;">
                <p style="margin-bottom:6px;"><strong><?= $isEn ? 'Method 3: Add via Admin UI' : 'วิธีที่ 3: เพิ่มผ่าน Admin UI' ?></strong></p>
                <p style="font-size:0.85rem;color:#666;"><?= $isEn ? 'Go to Admin → Programs → click "➕ Add Program" to add one by one' : 'ไป Admin → Programs → กดปุ่ม "➕ เพิ่ม Program" เพื่อเพิ่มทีละรายการ' ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Step 5: Admin & Security -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                🔐 <?= $isEn ? 'Step 5 — Admin &amp; Security Setup' : 'ขั้นตอนที่ 5 — Admin &amp; Security Setup' ?>
            </span>
            <span class="section-badge <?php echo ($allTablesOk && !$usingDefaultPassword) ? 'badge-ok' : 'badge-warning'; ?>">
                <?= $usingDefaultPassword ? ($isEn ? 'Password Change Required' : 'ต้องเปลี่ยน Password') : ($isEn ? 'Verified' : 'ตรวจสอบแล้ว') ?>
            </span>
        </div>
        <div class="section-body">
            <!-- Admin users -->
            <div class="check-row">
                <span class="check-icon"><?php echo $adminCount > 0 ? '✅' : '❌'; ?></span>
                <span class="check-label"><?= $isEn ? 'Admin Users in Database' : 'Admin Users ใน Database' ?></span>
                <span class="check-value <?php echo $adminCount > 0 ? 'ok' : 'error'; ?>">
                    <?php echo $adminCount; ?> users
                </span>
            </div>

            <!-- Default password warning -->
            <div class="check-row">
                <span class="check-icon"><?php echo $usingDefaultPassword ? '⚠️' : '✅'; ?></span>
                <span class="check-label">
                    Admin Password
                    <?php if ($usingDefaultPassword): ?>
                    <span class="fix-hint">⚠️ <?= $isEn ? 'Using default password — please change immediately!' : 'กำลังใช้ password เริ่มต้น — กรุณาเปลี่ยนทันที!' ?></span>
                    <?php endif; ?>
                </span>
                <span class="check-value <?php echo $usingDefaultPassword ? 'warning' : 'ok'; ?>">
                    <?= $usingDefaultPassword ? ($isEn ? 'default (insecure)' : 'default (ไม่ปลอดภัย)') : 'changed' ?>
                </span>
            </div>

            <!-- IP Whitelist -->
            <div class="check-row">
                <span class="check-icon">
                    <?php echo defined('ADMIN_IP_WHITELIST_ENABLED') && ADMIN_IP_WHITELIST_ENABLED ? '🔒' : 'ℹ️'; ?>
                </span>
                <span class="check-label">
                    IP Whitelist
                    <span style="color:#999;font-size:0.82rem;"><?= $isEn ? 'Restrict admin access by IP' : 'จำกัดการเข้า admin ตาม IP' ?></span>
                </span>
                <span class="check-value <?php echo (defined('ADMIN_IP_WHITELIST_ENABLED') && ADMIN_IP_WHITELIST_ENABLED) ? 'ok' : ''; ?>">
                    <?php echo (defined('ADMIN_IP_WHITELIST_ENABLED') && ADMIN_IP_WHITELIST_ENABLED) ? 'enabled' : 'disabled'; ?>
                </span>
            </div>

            <!-- Production mode -->
            <div class="check-row">
                <span class="check-icon"><?php echo (defined('PRODUCTION_MODE') && PRODUCTION_MODE) ? '✅' : 'ℹ️'; ?></span>
                <span class="check-label">
                    Production Mode
                    <span style="color:#999;font-size:0.82rem;"><?= $isEn ? 'Hides error details' : 'ซ่อน error details' ?></span>
                </span>
                <span class="check-value <?php echo (defined('PRODUCTION_MODE') && PRODUCTION_MODE) ? 'ok' : 'warning'; ?>">
                    <?php echo (defined('PRODUCTION_MODE') && PRODUCTION_MODE) ? 'true' : 'false'; ?>
                </span>
            </div>
        </div>

        <?php if ($allTablesOk && $usingDefaultPassword): ?>
        <!-- Inline password change form — แสดงเมื่อยังใช้ password เริ่มต้น -->
        <div style="background:#fff8e1; border-left:4px solid #ff9800; padding:20px 20px 16px;">
            <p style="font-weight:600; color:#e65100; margin-bottom:14px; font-size:0.95rem;">
                ⚠️ <?= $isEn ? 'Please change the default password before going live' : 'กรุณาเปลี่ยน Password เริ่มต้นก่อนใช้งานจริง' ?>
            </p>

            <?php if ($defaultAdminUsername || $defaultAdminPasswordText || $defaultAdminPasswordFromConfig): ?>
            <!-- Default credentials box -->
            <div style="background:#e3f2fd; border:1px solid #90caf9; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:0.88rem;">
                <strong style="color:#1565c0;">🔑 <?= $isEn ? 'Default Login Credentials (created by Initialize Database)' : 'ข้อมูล Login เริ่มต้น (สร้างโดย Initialize Database)' ?></strong>
                <div style="margin-top:8px; display:flex; gap:16px; flex-wrap:wrap; align-items:center;">
                    <?php if ($defaultAdminUsername): ?>
                    <span>
                        Username:&ensp;
                        <code style="background:#fff; padding:3px 10px; border-radius:5px; border:1px solid #bbdefb; font-size:0.95rem; font-weight:600; color:#0d47a1;">
                            <?php echo htmlspecialchars($defaultAdminUsername); ?>
                        </code>
                    </span>
                    <?php endif; ?>
                    <?php if ($defaultAdminPasswordText): ?>
                    <span>
                        Password:&ensp;
                        <code style="background:#fff; padding:3px 10px; border-radius:5px; border:1px solid #bbdefb; font-size:0.95rem; font-weight:600; color:#0d47a1;">
                            <?php echo htmlspecialchars($defaultAdminPasswordText); ?>
                        </code>
                    </span>
                    <?php elseif ($defaultAdminPasswordFromConfig): ?>
                    <span style="color:#1565c0;">
                        Password:&ensp;
                        <span style="background:#fff; padding:3px 10px; border-radius:5px; border:1px solid #bbdefb; font-size:0.88rem; color:#555;">
                            <?= $isEn ? 'See' : 'ดูได้ใน' ?> <code style="color:#0d47a1;">config/admin.php</code>
                            <span style="color:#888; font-size:0.8rem;"> (ADMIN_PASSWORD_HASH)</span>
                        </span>
                    </span>
                    <?php endif; ?>
                </div>
                <p style="color:#1565c0; font-size:0.8rem; margin-top:8px; opacity:0.8;">
                    ⬇️ <?= $isEn ? 'Change the password below immediately — never use default passwords in production' : 'เปลี่ยน password ด้านล่างนี้ทันที — อย่าใช้ password เริ่มต้นใน production' ?>
                </p>
            </div>
            <?php endif; ?>

            <form method="POST" onsubmit="return setupValidatePasswords()">
                <input type="hidden" name="action" value="set_admin_password">
                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                    <div style="flex:1; min-width:180px;">
                        <label style="font-size:0.85rem; display:block; margin-bottom:5px; color:#555; font-weight:500;">
                            <?= $isEn ? 'New Password' : 'Password ใหม่' ?> <small style="font-weight:normal;"><?= $isEn ? '(at least 8 characters)' : '(อย่างน้อย 8 ตัวอักษร)' ?></small>
                        </label>
                        <input type="password" name="new_password" id="setup_new_password"
                               style="width:100%; padding:9px 12px; border:2px solid #ddd; border-radius:7px; font-size:0.92rem; box-sizing:border-box;"
                               placeholder="<?= $isEn ? 'Enter new password' : 'กรอก password ใหม่' ?>" minlength="8" required>
                    </div>
                    <div style="flex:1; min-width:180px;">
                        <label style="font-size:0.85rem; display:block; margin-bottom:5px; color:#555; font-weight:500;">
                            <?= $isEn ? 'Confirm Password' : 'ยืนยัน Password' ?>
                        </label>
                        <input type="password" name="confirm_password" id="setup_confirm_password"
                               style="width:100%; padding:9px 12px; border:2px solid #ddd; border-radius:7px; font-size:0.92rem; box-sizing:border-box;"
                               placeholder="<?= $isEn ? 'Confirm password' : 'กรอกซ้ำอีกครั้ง' ?>" required
                               oninput="setupClearPasswordError()">
                    </div>
                    <button type="submit" class="btn btn-warning" style="padding:10px 20px; font-size:0.92rem;">
                        🔑 <?= $isEn ? 'Change Password' : 'เปลี่ยน Password' ?>
                    </button>
                </div>
                <p id="setup-pw-error" style="color:#b71c1c; font-size:0.82rem; margin-top:8px; display:none;">
                    ⚠️ <?= $isEn ? 'Passwords do not match — please try again' : 'Password ไม่ตรงกัน — กรุณากรอกอีกครั้ง' ?>
                </p>
            </form>
        </div>
        <?php else: ?>
        <div class="action-bar<?php echo $lockClass; ?>">
            <div style="flex:1;">
                <p><strong><?= $isEn ? 'Change Password:' : 'เปลี่ยน Password:' ?></strong> <?= $isEn ? 'Go to Admin → click "🔑 Change Password" at the top' : 'เข้า Admin → กดปุ่ม "🔑 Change Password" ที่ด้านบน' ?></p>
                <p style="margin-top:4px;"><strong><?= $isEn ? 'Or generate hash via CLI:' : 'หรือสร้าง hash ด้วย CLI:' ?></strong></p>
                <div class="code-block" style="font-size:0.78rem; margin-top:6px;">
php tools/generate-password-hash.php your_new_password</div>
            </div>
            <?php if ($allTablesOk): ?>
            <a href="admin/" class="btn btn-primary"><?= $isEn ? 'Go to Admin Panel &rarr;' : 'ไป Admin Panel &rarr;' ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Step 6: Production Cleanup -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $allDevFilesClean ? '✅' : '🧹'; ?> <?= $isEn ? 'Step 6 — Production Cleanup' : 'ขั้นตอนที่ 6 — Production Cleanup' ?>
            </span>
            <span class="section-badge <?php echo $allDevFilesClean ? 'badge-ok' : 'badge-info'; ?>">
                <?= $allDevFilesClean ? 'Clean' : ($isEn ? "$devFilesExistCount items" : "มี $devFilesExistCount รายการ") ?>
            </span>
        </div>
        <?php if ($allDevFilesClean): ?>
        <div class="section-body">
            <div class="check-row">
                <span class="check-icon">✅</span>
                <span class="check-label"><?= $isEn ? 'No dev/documentation files remaining' : 'ไม่มีไฟล์ dev/documentation เหลืออยู่ในระบบ' ?></span>
                <span class="check-value ok">clean</span>
            </div>
        </div>
        <?php else: ?>
        <div class="section-body">
            <div style="padding:12px 20px; font-size:0.88rem; color:#555; background:#f9f9f9; border-bottom:1px solid #f0f0f0;">
                <?= $isEn ? 'Files below are not needed in production — select and delete to reduce size and increase security' : 'ไฟล์ด้านล่างไม่จำเป็นสำหรับ production — เลือกและลบเพื่อลดพื้นที่และเพิ่มความปลอดภัย' ?>
                <span style="color:#e65100; font-weight:500; margin-left:4px;">⚠️ <?= $isEn ? 'Deletion cannot be undone' : 'การลบไม่สามารถย้อนกลับได้' ?></span>
            </div>
            <form method="POST" id="cleanup-form">
                <input type="hidden" name="action" value="cleanup_dev_files">
                <?php foreach ($devFileGroups as $gKey => $group):
                    $groupExisting = array_filter($group['items'], fn($i) => $i['exists']);
                    if (empty($groupExisting)) continue;
                ?>
                <div style="border-bottom:1px solid #f5f5f5;">
                    <div style="padding:10px 20px; background:#fafafa; display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" id="grp-<?php echo $gKey; ?>"
                               onchange="cleanupToggleGroup('<?php echo $gKey; ?>', this.checked)"
                               style="width:16px;height:16px;cursor:pointer;accent-color:#E91E63;">
                        <label for="grp-<?php echo $gKey; ?>" style="font-size:0.88rem; font-weight:600; color:#444; cursor:pointer;">
                            <?php echo $group['label']; ?>
                            <span style="font-size:0.78rem; font-weight:normal; color:#888; margin-left:4px;">(<?php echo count($groupExisting); ?> <?= $isEn ? 'items' : 'รายการ' ?>)</span>
                        </label>
                    </div>
                    <?php foreach ($groupExisting as $item): ?>
                    <?php $cbId = 'cb-' . preg_replace('/[^a-z0-9]/i', '-', $item['name']); ?>
                    <div class="check-row" style="padding-left:44px;">
                        <input type="checkbox" name="cleanup_items[]" value="<?php echo htmlspecialchars($item['name']); ?>"
                               class="cleanup-cb grp-cb-<?php echo $gKey; ?>" id="<?php echo $cbId; ?>"
                               style="width:15px;height:15px;cursor:pointer;accent-color:#E91E63;margin-right:4px;"
                               onchange="cleanupUpdateGroupState('<?php echo $gKey; ?>')">
                        <label for="<?php echo $cbId; ?>" style="cursor:pointer; flex:1; font-size:0.88rem;">
                            <code><?php echo htmlspecialchars($item['name']); ?></code>
                        </label>
                        <span class="check-value warning"><?php echo $item['type'] === 'dir' ? 'directory' : 'file'; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </form>
        </div>
        <div class="action-bar<?php echo $lockClass; ?>">
            <p style="font-size:0.85rem; color:#666; flex:1;"><?= $isEn ? 'Select files to delete — will not affect system operation' : 'เลือกไฟล์ที่ต้องการลบ — ไม่กระทบการทำงานของระบบ' ?></p>
            <button type="button" class="btn btn-secondary" onclick="cleanupSelectAll(true)"><?= $isEn ? 'Select All' : 'เลือกทั้งหมด' ?></button>
            <button type="button" class="btn btn-secondary" onclick="cleanupSelectAll(false)"><?= $isEn ? 'Deselect All' : 'ยกเลิกทั้งหมด' ?></button>
            <button type="button" class="btn btn-warning" onclick="cleanupSubmit()">
                🗑️ <?= $isEn ? 'Delete Selected' : 'ลบไฟล์ที่เลือก' ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Config Summary -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">⚙️ Configuration Summary</span>
            <span class="section-badge badge-info">config/app.php</span>
        </div>
        <div class="section-body">
            <?php if (!$configLoaded): ?>
            <div class="check-row">
                <span class="check-icon">❌</span>
                <span class="check-label" style="color:#b71c1c;">
                    config.php โหลดไม่ได้
                    <?php if ($configError): ?>
                    <span class="fix-hint"><?php echo htmlspecialchars($configError); ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php else: ?>
            <table class="config-table">
                <?php foreach ($configInfo as $key => $value): ?>
                <tr>
                    <td><?php echo htmlspecialchars($key); ?></td>
                    <td><?php echo $value !== null ? htmlspecialchars($value) : '<span class="config-na">not defined</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
        <div class="action-bar">
            <p><?= $isEn ? 'Edit <code>config/app.php</code> to adjust version, venue mode, multi-event mode and production mode' : 'แก้ไขไฟล์ <code>config/app.php</code> เพื่อปรับ version, venue mode, multi-event mode และ production mode' ?></p>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">🔗 <?= $isEn ? 'Quick Links' : 'ลิงก์ที่เกี่ยวข้อง' ?></span>
        </div>
        <div class="section-body">
            <div style="display:flex; flex-wrap:wrap; gap:10px; padding:16px 20px;">
                <a href="index.php" class="btn btn-secondary">🌸 <?= $isEn ? 'Homepage' : 'หน้าหลัก' ?></a>
                <a href="admin/" class="btn btn-secondary">⚙️ Admin Panel</a>
                <a href="admin/login.php" class="btn btn-secondary">🔐 Admin Login</a>
                <a href="how-to-use.php" class="btn btn-secondary">📖 <?= $isEn ? 'How to Use' : 'วิธีใช้งาน' ?></a>
                <a href="credits.php" class="btn btn-secondary">📋 Credits</a>
                <a href="INSTALLATION.md" class="btn btn-secondary" target="_blank">📄 INSTALLATION.md</a>
                <a href="DOCKER.md" class="btn btn-secondary" target="_blank">🐳 DOCKER.md</a>
            </div>
        </div>
    </div>

    <div class="setup-footer">
        Idol Stage Timetable <?php echo defined('APP_VERSION') ? 'v' . APP_VERSION : ''; ?>
        — <a href="https://x.com/FordAntiTrust" target="_blank">@FordAntiTrust</a>
        <?php if ($setupComplete): ?>
        &nbsp;|&nbsp; <a href="index.php"><?= $isEn ? 'Go to Homepage &rarr;' : 'ไปหน้าหลัก &rarr;' ?></a>
        <?php endif; ?>
    </div>

</div>

<script>
var setupI18n = {
    confirmLock:         <?= json_encode($isEn
        ? "Lock the setup page?\nThis will block all setup actions until unlocked."
        : "Lock setup page?\nจะป้องกันการรัน setup actions โดยไม่ตั้งใจ") ?>,
    confirmUnlock:       <?= json_encode($isEn
        ? "Unlock the setup page?\nSetup actions will be enabled again."
        : "Unlock setup page?\nSetup actions จะสามารถรันได้อีกครั้ง") ?>,
    confirmInitDb:       <?= json_encode($isEn
        ? "Create a new database?"
        : "สร้าง database ใหม่ใช่ไหม?") ?>,
    confirmRunMigrations:<?= json_encode($isEn
        ? "Run all pending migrations (" . count($pendingMigrations) . " items)?\n\nSafe — all migrations are idempotent."
        : "รัน migrations ที่ค้างทั้งหมด (" . count($pendingMigrations) . " รายการ) ใช่ไหม?\n\nปลอดภัย — ทุก migration เป็น idempotent") ?>,
    cleanupAlert:        <?= json_encode($isEn ? 'Please select files to delete first.' : 'กรุณาเลือกไฟล์ที่ต้องการลบก่อน') ?>,
    cleanupCannotUndo:   <?= json_encode($isEn ? '⚠️ This cannot be undone!' : '⚠️ การลบไม่สามารถย้อนกลับได้!') ?>,
    cleanupConfirmPre:   <?= json_encode($isEn ? 'Confirm delete ' : 'ยืนยันลบ ') ?>,
    cleanupConfirmPost:  <?= json_encode($isEn ? ' item(s)?' : ' รายการ?') ?>,
    pwMismatch:          <?= json_encode($isEn ? 'Passwords do not match — please try again' : 'Password ไม่ตรงกัน — กรุณากรอกอีกครั้ง') ?>,
};

function cleanupToggleGroup(gKey, checked) {
    document.querySelectorAll('.grp-cb-' + gKey).forEach(function(cb) { cb.checked = checked; });
}
function cleanupUpdateGroupState(gKey) {
    var cbs = document.querySelectorAll('.grp-cb-' + gKey);
    var grpCb = document.getElementById('grp-' + gKey);
    if (!grpCb || !cbs.length) return;
    var checkedCount = Array.from(cbs).filter(function(cb) { return cb.checked; }).length;
    grpCb.checked = checkedCount === cbs.length;
    grpCb.indeterminate = checkedCount > 0 && checkedCount < cbs.length;
}
function cleanupSelectAll(checked) {
    document.querySelectorAll('.cleanup-cb').forEach(function(cb) { cb.checked = checked; });
    document.querySelectorAll('[id^="grp-"]').forEach(function(cb) { cb.checked = checked; cb.indeterminate = false; });
}
function cleanupSubmit() {
    var selected = document.querySelectorAll('.cleanup-cb:checked');
    if (selected.length === 0) { alert(setupI18n.cleanupAlert); return; }
    var names = Array.from(selected).map(function(cb) { return cb.value; });
    if (confirm(setupI18n.cleanupConfirmPre + selected.length + setupI18n.cleanupConfirmPost + '\n\n' + names.join('\n') + '\n\n' + setupI18n.cleanupCannotUndo)) {
        document.getElementById('cleanup-form').submit();
    }
}
function setupValidatePasswords() {
    var p1  = document.getElementById('setup_new_password');
    var p2  = document.getElementById('setup_confirm_password');
    var err = document.getElementById('setup-pw-error');
    if (!p1 || !p2) return true;
    if (p1.value !== p2.value) {
        err.style.display = 'block';
        p2.style.borderColor = '#f44336';
        p2.focus();
        return false;
    }
    return true;
}
function setupClearPasswordError() {
    var err = document.getElementById('setup-pw-error');
    var p2  = document.getElementById('setup_confirm_password');
    if (err) err.style.display = 'none';
    if (p2) p2.style.borderColor = '#ddd';
}
</script>
</body>
</html>
