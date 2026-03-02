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
            __DIR__ . '/data'    => 'data/',
            __DIR__ . '/cache'   => 'cache/',
            __DIR__ . '/backups' => 'backups/',
            __DIR__ . '/ics'     => 'ics/',
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

            // Indexes สำหรับ performance
            $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_admin_users_username ON admin_users(username)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_programs_event_id ON programs(event_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_programs_start ON programs(start)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_programs_location ON programs(location)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_programs_categories ON programs(categories)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_program_requests_status ON program_requests(status)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_program_requests_event_id ON program_requests(event_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_credits_event_id ON credits(event_id)");

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
                // ตรวจว่า programs_old มี program_type หรือไม่ (อาจมีถ้าเคยรัน migration มาก่อน)
                $oldCols = $db->query("PRAGMA table_info(programs_old)")->fetchAll(PDO::FETCH_COLUMN, 1);
                $hasOldProgramType = in_array('program_type', $oldCols);
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
                    event_id INTEGER REFERENCES events(id),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                if ($hasOldProgramType) {
                    $db->exec("INSERT INTO programs (id, uid, title, start, end, location, organizer, description, categories, program_type, event_id, created_at, updated_at)
                               SELECT id, COALESCE(uid, 'uid-' || id || '@local'), COALESCE(summary, '(no title)'), start, end, location, organizer, description, categories, program_type, event_id, created_at, updated_at
                               FROM programs_old");
                } else {
                    $db->exec("INSERT INTO programs (id, uid, title, start, end, location, organizer, description, categories, event_id, created_at, updated_at)
                               SELECT id, COALESCE(uid, 'uid-' || id || '@local'), COALESCE(summary, '(no title)'), start, end, location, organizer, description, categories, event_id, created_at, updated_at
                               FROM programs_old");
                }
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
        'fix' => 'อัพเกรด PHP เป็น 8.1 หรือสูงกว่า',
    ],
    'pdo' => [
        'label' => 'PDO Extension',
        'ok' => extension_loaded('pdo'),
        'value' => extension_loaded('pdo') ? 'loaded' : 'not found',
        'fix' => 'เปิดใช้งาน extension=pdo ใน php.ini',
    ],
    'pdo_sqlite' => [
        'label' => 'PDO SQLite Extension',
        'ok' => extension_loaded('pdo_sqlite'),
        'value' => extension_loaded('pdo_sqlite') ? 'loaded' : 'not found',
        'fix' => 'เปิดใช้งาน extension=pdo_sqlite ใน php.ini',
    ],
    'mbstring' => [
        'label' => 'mbstring Extension',
        'ok' => extension_loaded('mbstring'),
        'value' => extension_loaded('mbstring') ? 'loaded' : 'not found',
        'fix' => 'เปิดใช้งาน extension=mbstring ใน php.ini',
    ],
];
$allPhpOk = !in_array(false, array_column($phpChecks, 'ok'));

// 2. Directories
$dirChecks = [
    'data'    => ['label' => 'data/', 'path' => __DIR__ . '/data', 'need_write' => true, 'purpose' => 'เก็บ database'],
    'cache'   => ['label' => 'cache/', 'path' => __DIR__ . '/cache', 'need_write' => true, 'purpose' => 'เก็บ cache files'],
    'backups' => ['label' => 'backups/', 'path' => __DIR__ . '/backups', 'need_write' => true, 'purpose' => 'เก็บ backup files'],
    'ics'     => ['label' => 'ics/', 'path' => __DIR__ . '/ics', 'need_write' => false, 'purpose' => 'เก็บไฟล์ ICS (optional)'],
];
foreach ($dirChecks as &$dc) {
    $dc['exists'] = is_dir($dc['path']);
    $dc['writable'] = $dc['exists'] && is_writable($dc['path']);
    $dc['ok'] = $dc['need_write'] ? ($dc['exists'] && $dc['writable']) : $dc['exists'];
}
unset($dc);
$allDirsOk = !in_array(false, array_column($dirChecks, 'ok'));
$missingDirs = array_filter($dirChecks, fn($d) => !$d['exists']);

// 3. Database & Tables
$dbExists = file_exists($dbPath);
$tableStatus = [];
$programCount = 0;
$eventCount = 0;
$adminCount = 0;
$hasRoleColumn = false;
$hasThemeColumn = false;
$hasEventEmailColumn = false;
$hasTitleColumn = false;
$hasProgramTypeColumn = false;
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
        }
        $hasTitleColumn = false;
        $hasProgramTypeColumn = false;
        if ($tableStatus['programs'] ?? false) {
            $pcols = $db->query("PRAGMA table_info(programs)")->fetchAll(PDO::FETCH_COLUMN, 1);
            $hasTitleColumn = in_array('title', $pcols);
            $hasProgramTypeColumn = in_array('program_type', $pcols);
        }

        $existingIndexes = $db->query("SELECT name FROM sqlite_master WHERE type='index'")->fetchAll(PDO::FETCH_COLUMN);
        $requiredIndexes = ['idx_programs_event_id', 'idx_programs_start', 'idx_credits_event_id'];
        $hasIndexes = count(array_intersect($requiredIndexes, $existingIndexes)) === count($requiredIndexes);

        unset($db);
    } catch (PDOException $e) {
        $dbError = $e->getMessage();
    }
}

$allTablesOk = $dbExists && !empty($tableStatus) && !in_array(false, $tableStatus) && $hasRoleColumn && $hasThemeColumn && $hasEventEmailColumn && $hasIndexes && $hasTitleColumn && $hasProgramTypeColumn;

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
<html lang="th">
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
            <p><?php echo htmlspecialchars(function_exists('get_site_title') ? get_site_title() : (defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable')); ?> — ตรวจสอบความพร้อมและ setup ก่อนเริ่มใช้งาน</p>
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
            <a href="admin/login.php?logout=1" style="color:rgba(255,255,255,0.8); font-size:0.78rem;">ออกจากระบบ</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Lock Banner (แสดงเสมอเมื่อ locked) -->
    <?php if ($isLocked): ?>
    <div class="lock-banner">
        <span class="icon">🔒</span>
        <div class="lock-banner-text">
            <strong>Setup Page ถูก Lock แล้ว</strong>
            <small>Setup actions ทั้งหมดถูกปิดกั้น — กด Unlock เพื่อเปิดใช้งานอีกครั้ง</small>
        </div>
        <form method="POST" onsubmit="return confirm('Unlock setup page?\nSetup actions จะสามารถรันได้อีกครั้ง')">
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
            <strong>Setup เสร็จสมบูรณ์!</strong>
            <?php if ($programCount > 0): ?>
            ระบบพร้อมใช้งานแล้ว — มี <?php echo number_format($programCount); ?> programs ใน database
            <?php else: ?>
            ระบบพร้อมใช้งานแล้ว — ยังไม่มีข้อมูล programs (เพิ่มทีหลังได้ผ่าน Admin Panel)
            <?php endif; ?>
        </div>
        <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
            <?php if (!$isLocked): ?>
            <form method="POST" onsubmit="return confirm('Lock setup page?\nจะป้องกันการรัน setup actions โดยไม่ตั้งใจ')">
                <button type="submit" name="action" value="lock" class="btn btn-lock" title="ล็อก setup เพื่อป้องกันการแก้ไขโดยไม่ตั้งใจ">
                    🔒 Lock Setup
                </button>
            </form>
            <?php endif; ?>
            <a href="<?php echo $programCount > 0 ? 'index.php' : 'admin/'; ?>" class="btn btn-success">
                <?php echo $programCount > 0 ? 'ไปหน้าหลัก →' : 'ไป Admin →'; ?>
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="status-banner warning">
        <span class="icon">⚠️</span>
        <div>
            <strong>ยังไม่ได้ทำ Setup</strong>
            กรุณาทำตามขั้นตอนด้านล่างให้ครบก่อนใช้งาน
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
            <span class="section-title">📋 ขั้นตอน Setup ทั้งหมด</span>
            <span class="section-badge <?php echo $setupComplete ? 'badge-ok' : 'badge-warning'; ?>">
                <?php echo $setupComplete ? 'เสร็จสมบูรณ์' : 'รอดำเนินการ'; ?>
            </span>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-num <?php echo $allPhpOk ? 'done' : ''; ?>">
                    <?php echo $allPhpOk ? '✓' : '1'; ?>
                </div>
                <div class="step-content">
                    <strong>ตรวจสอบ System Requirements</strong>
                    <p>PHP 8.1+ พร้อม extensions: PDO, PDO SQLite, mbstring</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo $allDirsOk ? 'done' : ''; ?>">
                    <?php echo $allDirsOk ? '✓' : '2'; ?>
                </div>
                <div class="step-content">
                    <strong>สร้าง Directories ที่จำเป็น</strong>
                    <p>โฟลเดอร์ <code>data/</code>, <code>cache/</code>, <code>backups/</code>, <code>ics/</code></p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo $allTablesOk ? 'done' : ''; ?>">
                    <?php echo $allTablesOk ? '✓' : '3'; ?>
                </div>
                <div class="step-content">
                    <strong>สร้างตาราง Database</strong>
                    <p>สร้างตาราง programs, events, program_requests, credits, admin_users และ indexes</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo $programCount > 0 ? 'done' : ''; ?>"
                     style="<?php echo $programCount == 0 && $allTablesOk ? 'background:#9e9e9e;' : ''; ?>">
                    <?php echo $programCount > 0 ? '✓' : '4'; ?>
                </div>
                <div class="step-content">
                    <strong>Import ข้อมูล Programs <span style="font-size:0.78rem; font-weight:normal; color:#888; background:#f5f5f5; padding:1px 6px; border-radius:10px;">optional</span></strong>
                    <p>วางไฟล์ .ics ใน <code>ics/</code> แล้ว import ผ่าน CLI หรือ Admin Panel — เพิ่มทีหลังได้</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo ($allTablesOk && !$usingDefaultPassword) ? 'done' : ''; ?>">
                    <?php echo ($allTablesOk && !$usingDefaultPassword) ? '✓' : '5'; ?>
                </div>
                <div class="step-content">
                    <strong>ตั้งค่า Admin Password</strong>
                    <p>เข้า Admin Panel และเปลี่ยน password เริ่มต้น</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num <?php echo $allDevFilesClean ? 'done' : ''; ?>"
                     style="<?php echo !$allDevFilesClean ? 'background:#9e9e9e;' : ''; ?>">
                    <?php echo $allDevFilesClean ? '✓' : '6'; ?>
                </div>
                <div class="step-content">
                    <strong>Production Cleanup <span style="font-size:0.78rem; font-weight:normal; color:#888; background:#f5f5f5; padding:1px 6px; border-radius:10px;">optional</span></strong>
                    <p>ลบไฟล์ dev/documentation ที่ไม่จำเป็นใน production environment</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick DB Init Banner (เฉพาะเมื่อผ่าน PHP+dirs แต่ยังไม่มี DB) -->
    <?php if ($needsDbInit && !$isLocked): ?>
    <div style="background:#fff3e0; border:2px solid #ffb74d; border-radius:12px; padding:20px 24px; margin-bottom:20px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <div style="font-size:2rem;">🗄️</div>
        <div style="flex:1; min-width:200px;">
            <strong style="font-size:1rem; color:#e65100; display:block; margin-bottom:4px;">ยังไม่ได้สร้าง Database</strong>
            <span style="font-size:0.88rem; color:#795548;">กด Initialize เพื่อสร้างตารางทั้งหมดครั้งเดียว พร้อม admin user และ default event</span>
        </div>
        <form method="POST" onsubmit="return confirm('สร้าง database ใหม่ใช่ไหม?')">
            <button type="submit" name="action" value="init_database" class="btn btn-warning" style="font-size:1rem; padding:12px 24px;">
                🗄️ Initialize Database
            </button>
        </form>
    </div>
    <?php elseif ($needsDbInit && $isLocked): ?>
    <div style="background:#fff3e0; border:2px solid #ffb74d; border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; gap:12px; opacity:0.6;">
        <div style="font-size:1.5rem;">🗄️</div>
        <div>
            <strong style="color:#e65100;">ยังไม่ได้สร้าง Database</strong>
            <span style="font-size:0.85rem; color:#795548; margin-left:8px;">🔒 Unlock ก่อนเพื่อ initialize</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Step 1: PHP Requirements -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $allPhpOk ? '✅' : '❌'; ?> ขั้นตอนที่ 1 — System Requirements
            </span>
            <span class="section-badge <?php echo $allPhpOk ? 'badge-ok' : 'badge-error'; ?>">
                <?php echo $allPhpOk ? 'ผ่าน' : 'ไม่ผ่าน'; ?>
            </span>
        </div>
        <div class="section-body">
            <?php foreach ($phpChecks as $key => $check): ?>
            <div class="check-row">
                <span class="check-icon"><?php echo $check['ok'] ? '✅' : '❌'; ?></span>
                <span class="check-label">
                    <?php echo htmlspecialchars($check['label']); ?>
                    <?php if (!$check['ok']): ?>
                    <span class="fix-hint">💡 <?php echo htmlspecialchars($check['fix']); ?></span>
                    <?php endif; ?>
                </span>
                <span class="check-value <?php echo $check['ok'] ? 'ok' : 'error'; ?>">
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
                <?php echo $allDirsOk ? '✅' : '⚠️'; ?> ขั้นตอนที่ 2 — Directories &amp; Permissions
            </span>
            <span class="section-badge <?php echo $allDirsOk ? 'badge-ok' : 'badge-warning'; ?>">
                <?php echo $allDirsOk ? 'พร้อม' : 'ต้องการการตั้งค่า'; ?>
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
            <p>พบโฟลเดอร์ที่ขาด — กดปุ่มเพื่อสร้างอัตโนมัติ หรือสร้างด้วยตัวเองผ่าน CLI</p>
            <form method="POST">
                <button type="submit" name="action" value="create_dirs" class="btn btn-primary">
                    📁 สร้างโฟลเดอร์ที่ขาด
                </button>
            </form>
        </div>
        <?php elseif (!$allDirsOk): ?>
        <div class="action-bar">
            <p>บางโฟลเดอร์ไม่มีสิทธิ์เขียน กรุณาตรวจสอบ permissions</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Step 3: Database -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $allTablesOk ? '✅' : '⚠️'; ?> ขั้นตอนที่ 3 — Database Setup
            </span>
            <span class="section-badge <?php echo $allTablesOk ? 'badge-ok' : 'badge-warning'; ?>">
                <?php echo $allTablesOk ? 'พร้อม' : ($dbExists ? 'ต้องการ Migration' : 'ยังไม่ได้สร้าง'); ?>
            </span>
        </div>
        <div class="section-body">
            <!-- DB File -->
            <div class="check-row">
                <span class="check-icon"><?php echo $dbExists ? '✅' : '❌'; ?></span>
                <span class="check-label">
                    ไฟล์ Database
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
            $tableLabels = [
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
                <span class="check-label"><code>events.email</code> <span style="color:#999;font-size:0.82rem;">ใช้ใน ICS export ORGANIZER mailto</span></span>
                <span class="check-value <?php echo $hasEventEmailColumn ? 'ok' : 'warning'; ?>">
                    <?php echo $hasEventEmailColumn ? 'exists' : 'missing'; ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Program Type Column -->
            <?php if ($tableStatus['programs'] ?? false): ?>
            <div class="check-row">
                <span class="check-icon"><?php echo $hasProgramTypeColumn ? '✅' : '⚠️'; ?></span>
                <span class="check-label"><code>programs.program_type</code> <span style="color:#999;font-size:0.82rem;">ประเภท program (stage, booth, ฯลฯ)</span></span>
                <span class="check-value <?php echo $hasProgramTypeColumn ? 'ok' : 'warning'; ?>">
                    <?php echo $hasProgramTypeColumn ? 'exists' : 'missing'; ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Indexes -->
            <div class="check-row">
                <span class="check-icon"><?php echo $hasIndexes ? '✅' : '⚠️'; ?></span>
                <span class="check-label">Performance Indexes <span style="color:#999;font-size:0.82rem;">เพิ่มความเร็ว query 2-5x</span></span>
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
                <p style="margin-bottom:8px;"><strong>Fresh Install</strong> — สร้างตารางทั้งหมดใหม่ทีเดียว</p>
                <form method="POST" onsubmit="return confirm('สร้าง database ใหม่ใช่ไหม?')">
                    <button type="submit" name="action" value="init_database" class="btn btn-primary">
                        🗄️ Initialize Database (สร้างทุกตาราง)
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div style="flex:1; min-width:200px;">
                <p style="margin-bottom:8px;"><strong>Existing Install</strong> — เพิ่ม/อัพเดทสิ่งที่ขาด</p>
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
                    <?php if (!$hasIndexes && ($tableStatus['programs'] ?? false)): ?>
                    <form method="POST">
                        <button type="submit" name="action" value="add_indexes" class="btn btn-secondary">
                            + indexes
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="flex:1; min-width:200px;">
                <p style="margin-bottom:8px;"><strong>หรือรัน migrations ผ่าน CLI</strong></p>
                <div class="code-block" style="font-size:0.78rem;">
<span class="comment"># ลำดับที่แนะนำสำหรับ fresh install:</span>
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
php migrate-add-program-type-column.php</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Step 4: Data Import -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $programCount > 0 ? '✅' : '⚠️'; ?> ขั้นตอนที่ 4 — Import ข้อมูล Programs
            </span>
            <span class="section-badge <?php echo $programCount > 0 ? 'badge-ok' : 'badge-warning'; ?>">
                <?php echo $programCount > 0 ? $programCount . ' programs' : 'ยังไม่มีข้อมูล'; ?>
            </span>
        </div>
        <div class="section-body">
            <!-- Programs count -->
            <div class="check-row">
                <span class="check-icon"><?php echo $programCount > 0 ? '✅' : '📭'; ?></span>
                <span class="check-label">Programs ใน Database</span>
                <span class="check-value <?php echo $programCount > 0 ? 'ok' : 'warning'; ?>">
                    <?php echo number_format($programCount); ?> programs
                </span>
            </div>

            <!-- Events (conventions) count -->
            <?php if ($allTablesOk): ?>
            <div class="check-row">
                <span class="check-icon"><?php echo $eventCount > 0 ? '✅' : '⚠️'; ?></span>
                <span class="check-label">Events (Conventions) ใน Database</span>
                <span class="check-value <?php echo $eventCount > 0 ? 'ok' : 'warning'; ?>">
                    <?php echo number_format($eventCount); ?> events
                </span>
            </div>
            <?php endif; ?>

            <!-- ICS Files -->
            <div class="check-row">
                <span class="check-icon"><?php echo count($icsFiles) > 0 ? '📂' : '📁'; ?></span>
                <span class="check-label">
                    ไฟล์ ICS ใน <code>ics/</code>
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
                <p><strong>วิธีที่ 1: Upload ICS ผ่าน Admin Panel</strong></p>
                <p style="margin-top:4px;">ไป Admin → แท็บ Programs → กดปุ่ม "📤 Import ICS"</p>
                <a href="admin/" class="btn btn-primary" style="margin-top:8px;">
                    ไป Admin Panel &rarr;
                </a>
            </div>
            <div style="flex:1; min-width:220px;">
                <p style="margin-bottom:6px;"><strong>วิธีที่ 2: Import ผ่าน CLI</strong></p>
                <div class="code-block" style="font-size:0.78rem;">
<span class="comment"># วางไฟล์ .ics ใน ics/ แล้วรัน:</span>
<span class="cmd">cd tools</span>
php import-ics-to-sqlite.php

<span class="comment"># หรือระบุ event:</span>
php import-ics-to-sqlite.php --event=slug</div>
            </div>
            <div style="flex:1; min-width:220px;">
                <p style="margin-bottom:6px;"><strong>วิธีที่ 3: เพิ่มผ่าน Admin UI</strong></p>
                <p style="font-size:0.85rem;color:#666;">ไป Admin → Programs → กดปุ่ม "➕ เพิ่ม Program" เพื่อเพิ่มทีละรายการ</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Step 5: Admin & Security -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                🔐 ขั้นตอนที่ 5 — Admin &amp; Security Setup
            </span>
            <span class="section-badge <?php echo ($allTablesOk && !$usingDefaultPassword) ? 'badge-ok' : 'badge-warning'; ?>">
                <?php echo $usingDefaultPassword ? 'ต้องเปลี่ยน Password' : 'ตรวจสอบแล้ว'; ?>
            </span>
        </div>
        <div class="section-body">
            <!-- Admin users -->
            <div class="check-row">
                <span class="check-icon"><?php echo $adminCount > 0 ? '✅' : '❌'; ?></span>
                <span class="check-label">Admin Users ใน Database</span>
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
                    <span class="fix-hint">⚠️ กำลังใช้ password เริ่มต้น — กรุณาเปลี่ยนทันที!</span>
                    <?php endif; ?>
                </span>
                <span class="check-value <?php echo $usingDefaultPassword ? 'warning' : 'ok'; ?>">
                    <?php echo $usingDefaultPassword ? 'default (ไม่ปลอดภัย)' : 'changed'; ?>
                </span>
            </div>

            <!-- IP Whitelist -->
            <div class="check-row">
                <span class="check-icon">
                    <?php echo defined('ADMIN_IP_WHITELIST_ENABLED') && ADMIN_IP_WHITELIST_ENABLED ? '🔒' : 'ℹ️'; ?>
                </span>
                <span class="check-label">
                    IP Whitelist
                    <span style="color:#999;font-size:0.82rem;">จำกัดการเข้า admin ตาม IP</span>
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
                    <span style="color:#999;font-size:0.82rem;">ซ่อน error details</span>
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
                ⚠️ กรุณาเปลี่ยน Password เริ่มต้นก่อนใช้งานจริง
            </p>

            <?php if ($defaultAdminUsername || $defaultAdminPasswordText || $defaultAdminPasswordFromConfig): ?>
            <!-- Default credentials box -->
            <div style="background:#e3f2fd; border:1px solid #90caf9; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:0.88rem;">
                <strong style="color:#1565c0;">🔑 ข้อมูล Login เริ่มต้น (สร้างโดย Initialize Database)</strong>
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
                            ดูได้ใน <code style="color:#0d47a1;">config/admin.php</code>
                            <span style="color:#888; font-size:0.8rem;"> (ADMIN_PASSWORD_HASH)</span>
                        </span>
                    </span>
                    <?php endif; ?>
                </div>
                <p style="color:#1565c0; font-size:0.8rem; margin-top:8px; opacity:0.8;">
                    ⬇️ เปลี่ยน password ด้านล่างนี้ทันที — อย่าใช้ password เริ่มต้นใน production
                </p>
            </div>
            <?php endif; ?>

            <form method="POST" onsubmit="return setupValidatePasswords()">
                <input type="hidden" name="action" value="set_admin_password">
                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                    <div style="flex:1; min-width:180px;">
                        <label style="font-size:0.85rem; display:block; margin-bottom:5px; color:#555; font-weight:500;">
                            Password ใหม่ <small style="font-weight:normal;">(อย่างน้อย 8 ตัวอักษร)</small>
                        </label>
                        <input type="password" name="new_password" id="setup_new_password"
                               style="width:100%; padding:9px 12px; border:2px solid #ddd; border-radius:7px; font-size:0.92rem; box-sizing:border-box;"
                               placeholder="กรอก password ใหม่" minlength="8" required>
                    </div>
                    <div style="flex:1; min-width:180px;">
                        <label style="font-size:0.85rem; display:block; margin-bottom:5px; color:#555; font-weight:500;">
                            ยืนยัน Password
                        </label>
                        <input type="password" name="confirm_password" id="setup_confirm_password"
                               style="width:100%; padding:9px 12px; border:2px solid #ddd; border-radius:7px; font-size:0.92rem; box-sizing:border-box;"
                               placeholder="กรอกซ้ำอีกครั้ง" required
                               oninput="setupClearPasswordError()">
                    </div>
                    <button type="submit" class="btn btn-warning" style="padding:10px 20px; font-size:0.92rem;">
                        🔑 เปลี่ยน Password
                    </button>
                </div>
                <p id="setup-pw-error" style="color:#b71c1c; font-size:0.82rem; margin-top:8px; display:none;">
                    ⚠️ Password ไม่ตรงกัน — กรุณากรอกอีกครั้ง
                </p>
            </form>
        </div>
        <?php else: ?>
        <div class="action-bar<?php echo $lockClass; ?>">
            <div style="flex:1;">
                <p><strong>เปลี่ยน Password:</strong> เข้า Admin → กดปุ่ม "🔑 Change Password" ที่ด้านบน</p>
                <p style="margin-top:4px;"><strong>หรือสร้าง hash ด้วย CLI:</strong></p>
                <div class="code-block" style="font-size:0.78rem; margin-top:6px;">
php tools/generate-password-hash.php your_new_password</div>
            </div>
            <?php if ($allTablesOk): ?>
            <a href="admin/" class="btn btn-primary">ไป Admin Panel &rarr;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Step 6: Production Cleanup -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">
                <?php echo $allDevFilesClean ? '✅' : '🧹'; ?> ขั้นตอนที่ 6 — Production Cleanup
            </span>
            <span class="section-badge <?php echo $allDevFilesClean ? 'badge-ok' : 'badge-info'; ?>">
                <?php echo $allDevFilesClean ? 'Clean' : "มี $devFilesExistCount รายการ"; ?>
            </span>
        </div>
        <?php if ($allDevFilesClean): ?>
        <div class="section-body">
            <div class="check-row">
                <span class="check-icon">✅</span>
                <span class="check-label">ไม่มีไฟล์ dev/documentation เหลืออยู่ในระบบ</span>
                <span class="check-value ok">clean</span>
            </div>
        </div>
        <?php else: ?>
        <div class="section-body">
            <div style="padding:12px 20px; font-size:0.88rem; color:#555; background:#f9f9f9; border-bottom:1px solid #f0f0f0;">
                ไฟล์ด้านล่างไม่จำเป็นสำหรับ production — เลือกและลบเพื่อลดพื้นที่และเพิ่มความปลอดภัย
                <span style="color:#e65100; font-weight:500; margin-left:4px;">⚠️ การลบไม่สามารถย้อนกลับได้</span>
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
                            <span style="font-size:0.78rem; font-weight:normal; color:#888; margin-left:4px;">(<?php echo count($groupExisting); ?> รายการ)</span>
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
            <p style="font-size:0.85rem; color:#666; flex:1;">เลือกไฟล์ที่ต้องการลบ — ไม่กระทบการทำงานของระบบ</p>
            <button type="button" class="btn btn-secondary" onclick="cleanupSelectAll(true)">เลือกทั้งหมด</button>
            <button type="button" class="btn btn-secondary" onclick="cleanupSelectAll(false)">ยกเลิกทั้งหมด</button>
            <button type="button" class="btn btn-warning" onclick="cleanupSubmit()">
                🗑️ ลบไฟล์ที่เลือก
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
            <p>แก้ไขไฟล์ <code>config/app.php</code> เพื่อปรับ version, venue mode, multi-event mode และ production mode</p>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">🔗 ลิงก์ที่เกี่ยวข้อง</span>
        </div>
        <div class="section-body">
            <div style="display:flex; flex-wrap:wrap; gap:10px; padding:16px 20px;">
                <a href="index.php" class="btn btn-secondary">🌸 หน้าหลัก</a>
                <a href="admin/" class="btn btn-secondary">⚙️ Admin Panel</a>
                <a href="admin/login.php" class="btn btn-secondary">🔐 Admin Login</a>
                <a href="how-to-use.php" class="btn btn-secondary">📖 วิธีใช้งาน</a>
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
        &nbsp;|&nbsp; <a href="index.php">ไปหน้าหลัก &rarr;</a>
        <?php endif; ?>
    </div>

</div>

<script>
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
    if (selected.length === 0) { alert('กรุณาเลือกไฟล์ที่ต้องการลบก่อน'); return; }
    var names = Array.from(selected).map(function(cb) { return cb.value; });
    if (confirm('ยืนยันลบ ' + selected.length + ' รายการ?\n\n' + names.join('\n') + '\n\n⚠️ การลบไม่สามารถย้อนกลับได้!')) {
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
