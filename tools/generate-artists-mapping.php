<?php
/**
 * Generate Artists Mapping File
 * Idol Stage Timetable
 *
 * อ่านข้อมูล categories จาก programs ทั้งหมด แล้วจัดกลุ่มและ flag ปัญหา
 * สำหรับ review ก่อน migrate จริง
 *
 * Usage:
 *   cd tools && php generate-artists-mapping.php
 *
 * Output:
 *   data/artists-mapping.json
 *
 * หลังจาก review/แก้ mapping แล้ว ให้รัน:
 *   php migrate-artists-from-mapping.php --dry-run
 *   php migrate-artists-from-mapping.php
 */

require_once __DIR__ . '/../config.php';

echo "=== Generate Artists Mapping ===\n\n";

if (!defined('DB_PATH') || !file_exists(DB_PATH)) {
    echo "ERROR: Database not found at: " . (defined('DB_PATH') ? DB_PATH : '(DB_PATH not defined)') . "\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "ERROR: Cannot connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

// ---- Step 1: รวบรวม raw values จาก categories ----

$stmt = $db->query("
    SELECT id, categories
    FROM programs
    WHERE categories IS NOT NULL AND categories != ''
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// rawName → [program_ids...]
$rawToPrograms = [];

foreach ($rows as $row) {
    $parts = explode(',', $row['categories']);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $rawToPrograms[$part][] = (int)$row['id'];
    }
}

echo "Total programs with categories : " . count($rows) . "\n";
echo "Total distinct raw values      : " . count($rawToPrograms) . "\n\n";

// ---- Step 2: จัดกลุ่มด้วย case-insensitive key ----

// lowercaseKey → ['variants' => [], 'counts' => [name => int]]
$groups = [];

foreach ($rawToPrograms as $name => $programIds) {
    $key = mb_strtolower(trim($name), 'UTF-8');
    $groups[$key]['variants'][]       = $name;
    $groups[$key]['counts'][$name]    = count($programIds);
}

// ---- Step 3: ตรวจสอบและสร้าง output ----

// คำที่น่าจะไม่ใช่ชื่อศิลปิน (event/activity names)
$nonArtistKeywords = [
    'event',
    'lucky draw',
    'sign activity',
    'give bd stage',
    'ic45 birthday stage',
    'momo birthday stage',
    'asia-nadear show',
    'birthday stage',
];

$artists = [];

foreach ($groups as $key => $group) {
    $variants = array_values(array_unique($group['variants']));
    $counts   = $group['counts'];

    // เลือก canonical = variant ที่ปรากฏบ่อยที่สุด
    // ถ้าเท่ากัน เลือกอันที่ยาวที่สุด (มักมี proper casing มากกว่า)
    arsort($counts);
    $topCount    = reset($counts);
    $topVariants = array_keys(array_filter($counts, fn($c) => $c === $topCount));
    usort($topVariants, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
    $canonical = $topVariants[0];

    $totalPrograms = array_sum($counts);
    $notes  = [];
    $skip   = false;
    $flags  = [];

    // Flag: member-solo format "NAME - GROUP" → auto-extract canonical + group
    $detectedGroup = null;
    foreach ($variants as $v) {
        if (mb_strpos($v, ' - ') !== false) {
            $flags[] = 'member-solo';
            // Auto-extract: "ALICE - Mirai Mirai" → member="ALICE", group="Mirai Mirai"
            $dashParts     = explode(' - ', $v, 2);
            $memberName    = trim($dashParts[0]);
            $detectedGroup = trim($dashParts[1]);
            // Override canonical to just the member name
            $canonical = $memberName;
            $notes[]   = "auto-detected: member=\"{$memberName}\", group=\"{$detectedGroup}\" — ตรวจสอบ canonical และ group";
            break;
        }
    }

    // Flag: non-artist keyword
    foreach ($nonArtistKeywords as $kw) {
        if (mb_strtolower($canonical, 'UTF-8') === $kw ||
            mb_strpos(mb_strtolower($canonical, 'UTF-8'), $kw) !== false) {
            $flags[] = 'non-artist';
            $notes[] = 'อาจไม่ใช่ชื่อศิลปิน (ชื่อ activity/event) — ตรวจสอบและ set skip: true ถ้าไม่ใช่';
            $skip    = true;
            break;
        }
    }

    // Flag: multiple variants (case mismatch)
    if (count($variants) > 1) {
        $flags[] = 'case-mismatch';
        $notes[] = 'พบหลาย variant — ตรวจสอบ canonical name ที่ถูกต้อง';
    }

    $artists[] = [
        'canonical'      => $canonical,
        'group'          => $detectedGroup,   // ชื่อกลุ่มที่ศิลปินสังกัด (null = ไม่ระบุ) — แก้ไขได้
        'is_group'       => false,            // placeholder — จะ auto-set ในขั้นตอนถัดไป
        'variants'       => $variants,
        'program_count'  => $totalPrograms,
        'skip'           => $skip,
        'flags'          => $flags,
        'note'           => implode(' | ', $notes),
    ];
}

// ---- Step 4: Merge entries ที่มี canonical เดียวกัน ----
// เกิดเมื่อ "ALICE - Mirai Mirai" และ "Alice" ถูก detect แยกกัน แต่ canonical เดียวกัน
$mergedByCanonical = [];
foreach ($artists as $a) {
    $key = $a['canonical'];
    if (!isset($mergedByCanonical[$key])) {
        $mergedByCanonical[$key] = $a;
    } else {
        $m = &$mergedByCanonical[$key];
        $m['variants']      = array_values(array_unique(array_merge($m['variants'], $a['variants'])));
        $m['program_count'] += $a['program_count'];
        $m['flags']         = array_values(array_unique(array_merge($m['flags'], $a['flags'])));
        if (empty($m['group']) && !empty($a['group']))  $m['group']    = $a['group'];
        if (!empty($a['is_group']))                      $m['is_group'] = true;
        $notes = array_filter(array_unique(array_merge(
            $m['note'] ? explode(' | ', $m['note']) : [],
            $a['note'] ? explode(' | ', $a['note']) : []
        )));
        $m['note'] = implode(' | ', $notes);
        unset($m);
    }
}
$artists = array_values($mergedByCanonical);

// เรียงตาม canonical name
usort($artists, fn($a, $b) => strcmp(
    mb_strtolower($a['canonical'], 'UTF-8'),
    mb_strtolower($b['canonical'], 'UTF-8')
));

// ---- Step 5: Auto-detect is_group ----
// entry เป็น group ถ้า canonical ของมันถูก entry อื่น reference ผ่าน "group" field
$referencedGroups = [];
foreach ($artists as $a) {
    if (!empty($a['group'])) {
        $referencedGroups[mb_strtolower($a['group'], 'UTF-8')] = true;
    }
}

foreach ($artists as &$a) {
    $key        = mb_strtolower($a['canonical'], 'UTF-8');
    $a['is_group'] = isset($referencedGroups[$key]);
}
unset($a);

// ---- Step 6: สร้าง output JSON ----

$skipCount         = count(array_filter($artists, fn($a) => $a['skip']));
$caseMismatchCount = count(array_filter($artists, fn($a) => in_array('case-mismatch', $a['flags'])));
$memberSoloCount   = count(array_filter($artists, fn($a) => in_array('member-solo', $a['flags'])));
$isGroupCount      = count(array_filter($artists, fn($a) => $a['is_group'] && !$a['skip']));

$output = [
    'generated_at'    => date('Y-m-d H:i:s'),
    'summary' => [
        'total_entries'    => count($artists),
        'auto_skip'        => $skipCount,
        'is_group'         => $isGroupCount,
        'case_mismatch'    => $caseMismatchCount,
        'member_solo'      => $memberSoloCount,
    ],
    'instructions'    => [
        'STEP 1' => 'ตรวจสอบแต่ละ entry และแก้ไข canonical เป็นชื่อที่ถูกต้อง',
        'STEP 2' => 'ตั้ง skip: true สำหรับ entry ที่ไม่ใช่ศิลปิน เช่น "Event", "Lucky Draw"',
        'STEP 3' => 'entry ที่มี flags: ["member-solo"] — ตรวจสอบ canonical (ชื่อสมาชิก) และ group (ชื่อกลุ่ม) ที่ auto-detect มา',
        'STEP 4' => 'entry ที่มี flags: ["case-mismatch"] — เลือก canonical ที่ถูกต้อง (variant ทั้งหมดจะ merge เป็น canonical เดียว)',
        'STEP 5' => 'is_group: true = entry นี้เป็นกลุ่ม (auto-detect จาก member-solo); ตั้ง is_group: true ให้กลุ่มอื่นที่ต้องการได้เลย',
        'STEP 6' => 'รัน: php migrate-artists-from-mapping.php --dry-run  (ดู preview ก่อน)',
        'STEP 7' => 'รัน: php migrate-artists-from-mapping.php             (apply จริง)',
    ],
    'artists'         => $artists,
];

$outputPath = __DIR__ . '/../data/artists-mapping.json';
$json       = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (file_put_contents($outputPath, $json) === false) {
    echo "ERROR: Cannot write to $outputPath\n";
    exit(1);
}

echo "Summary:\n";
echo "  Total entries    : " . count($artists) . "\n";
echo "  Auto-skip        : $skipCount  (non-artist keywords)\n";
echo "  Is group         : $isGroupCount  (auto-detected groups → artist_groups)\n";
echo "  Case mismatch    : $caseMismatchCount  (same artist, different casing)\n";
echo "  Member-solo      : $memberSoloCount  (\"MEMBER - GROUP\" format)\n";
echo "\nOutput: data/artists-mapping.json\n\n";
echo "Please review the file, then run:\n";
echo "  php migrate-artists-from-mapping.php --dry-run\n";
exit(0);
