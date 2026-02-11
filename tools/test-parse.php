<?php
/**
 * ทดสอบการ parse ไฟล์ Maipenrai_Schedule.ics
 */

require_once '../IcsParser.php';

echo "=== Testing Maipenrai_Schedule.ics ===\n\n";

// ใช้ file mode เพื่อทดสอบ
$parser = new IcsParser('../ics', false);

// อ่านไฟล์โดยตรง
$file = '../ics/Maipenrai_Schedule.ics';
$content = file_get_contents($file);

if ($content === false) {
    echo "❌ Cannot read file\n";
    exit(1);
}

// นับจำนวน VEVENT
preg_match_all('/BEGIN:VEVENT/i', $content, $matches);
$totalVEvents = count($matches[0]);
echo "📊 Total VEVENT blocks in file: $totalVEvents\n\n";

// แยก VEVENT ออกมา
preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $eventMatches);

echo "📋 Parsing events:\n";
$parsedCount = 0;
$failedCount = 0;

foreach ($eventMatches[1] as $index => $eventData) {
    $event = $parser->parseEvent($eventData);

    if ($event) {
        $parsedCount++;
        echo "  ✅ Event " . ($index + 1) . ": " . $event['title'] . "\n";
        echo "     Start: " . $event['start'] . "\n";
        echo "     End: " . $event['end'] . "\n";
        echo "     Location: " . $event['location'] . "\n";
        echo "     Categories: " . $event['categories'] . "\n";
        echo "     UID: " . $event['uid'] . "\n\n";
    } else {
        $failedCount++;
        echo "  ❌ Event " . ($index + 1) . ": Failed to parse\n";
        echo "     Raw data: " . substr($eventData, 0, 200) . "...\n\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total VEVENT blocks: $totalVEvents\n";
echo "Successfully parsed: $parsedCount\n";
echo "Failed to parse: $failedCount\n";

if ($failedCount > 0) {
    echo "\n⚠️  Some events failed to parse. Check the output above for details.\n";
} else {
    echo "\n✅ All events parsed successfully!\n";
}
?>
