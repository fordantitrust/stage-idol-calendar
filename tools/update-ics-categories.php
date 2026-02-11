<?php
/**
 * Script สำหรับเพิ่ม CATEGORIES field ในไฟล์ .ics
 * โดยใช้ข้อมูลจาก ORGANIZER CN=*
 */

echo "=== ICS Categories Update Script ===\n\n";

$icsFolder = '../ics';
$files = glob($icsFolder . '/*.ics');

if (empty($files)) {
    echo "No .ics files found in folder: $icsFolder/\n";
    exit;
}

echo "Found " . count($files) . " file(s)\n\n";

$successCount = 0;
$errorCount = 0;
$skipCount = 0;

foreach ($files as $file) {
    echo "Processing: $file\n";

    $content = file_get_contents($file);
    if ($content === false) {
        echo "  ❌ Error reading file\n";
        $errorCount++;
        continue;
    }

    $modified = false;

    // แยก VEVENT ออกมาและประมวลผลทีละ event
    $newContent = preg_replace_callback(
        '/BEGIN:VEVENT(.*?)END:VEVENT/s',
        function($matches) use (&$modified) {
            $eventContent = $matches[1];

            // ตรวจสอบว่ามี CATEGORIES อยู่แล้วหรือไม่
            if (preg_match('/^CATEGORIES:/m', $eventContent)) {
                // มี CATEGORIES อยู่แล้ว ข้าม
                return $matches[0];
            }

            // หาชื่อศิลปินจาก ORGANIZER
            $artistName = null;

            // ลองหา CN="..." ก่อน (มี double quotes)
            if (preg_match('/ORGANIZER[;:].*?CN="([^"]+)"/m', $eventContent, $cnMatch)) {
                $artistName = $cnMatch[1];
            }
            // ถ้าไม่มี quotes ให้หา CN=... จนถึง :mailto หรือจบบรรทัด
            elseif (preg_match('/ORGANIZER[;:].*?CN=([^;]+?)(?::mailto|:|;|$)/m', $eventContent, $cnMatch)) {
                $artistName = trim($cnMatch[1]);
            }

            // ถ้าไม่พบ ORGANIZER หรือ CN ให้ข้าม
            if ($artistName === null) {
                return $matches[0];
            }

            // Escape special characters ตาม RFC 5545
            $escapedArtistName = $artistName;
            $escapedArtistName = str_replace('\\', '\\\\', $escapedArtistName);
            $escapedArtistName = str_replace(',', '\\,', $escapedArtistName);
            $escapedArtistName = str_replace(';', '\\;', $escapedArtistName);
            $escapedArtistName = str_replace("\n", '\\n', $escapedArtistName);
            $escapedArtistName = str_replace("\r", '', $escapedArtistName);

            // เพิ่ม CATEGORIES ก่อน STATUS หรือ SEQUENCE
            $categoriesLine = "CATEGORIES:" . $escapedArtistName . "\r\n";

            // หาตำแหน่งที่เหมาะสมในการแทรก (ก่อน STATUS หรือ SEQUENCE)
            if (preg_match('/(STATUS:|SEQUENCE:)/m', $eventContent, $insertMatch, PREG_OFFSET_CAPTURE)) {
                $insertPos = $insertMatch[0][1];
                $eventContent = substr_replace($eventContent, $categoriesLine, $insertPos, 0);
            } else {
                // ถ้าไม่เจอ STATUS/SEQUENCE ให้ใส่ก่อนจบ event
                $eventContent = rtrim($eventContent) . "\r\n" . $categoriesLine;
            }

            $modified = true;
            return 'BEGIN:VEVENT' . $eventContent . 'END:VEVENT';
        },
        $content
    );

    if (!$modified) {
        echo "  ⏭️  Skipped (all events already have CATEGORIES)\n";
        $skipCount++;
        continue;
    }

    // สร้าง backup ก่อนเขียนทับ
    $backupFile = $file . '.backup';
    if (copy($file, $backupFile)) {
        echo "  💾 Backup created: $backupFile\n";
    }

    // เขียนกลับไปยังไฟล์
    if (file_put_contents($file, $newContent) !== false) {
        echo "  ✅ Updated successfully\n";
        $successCount++;
    } else {
        echo "  ❌ Error writing file\n";
        $errorCount++;
    }

    echo "\n";
}

echo "=== Summary ===\n";
echo "✅ Successfully updated: $successCount file(s)\n";
echo "⏭️  Skipped: $skipCount file(s)\n";
echo "❌ Errors: $errorCount file(s)\n";
echo "\nBackup files created with .backup extension\n";
echo "\nDone!\n";
?>
