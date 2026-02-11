<?php
/**
 * Debug script to see exactly which events parse successfully
 */

require_once '../IcsParser.php';

$file = '../ics/FanMeetingHall.ics';
$parser = new IcsParser('ics', false); // file mode

$content = file_get_contents($file);
preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);

$totalEvents = count($matches[0]);
echo "Total VEVENT blocks: $totalEvents\n\n";

$parsed = [];
$failed = [];

foreach ($matches[1] as $index => $eventData) {
    $event = $parser->parseEvent($eventData);

    if ($event) {
        $parsed[] = [
            'index' => $index + 1,
            'title' => $event['title'],
            'uid' => $event['uid'],
            'start' => $event['start']
        ];
        echo "✅ Event " . ($index + 1) . ": " . $event['title'] . "\n";
        echo "   UID: " . $event['uid'] . "\n";
        echo "   Start: " . $event['start'] . "\n\n";
    } else {
        $failed[] = $index + 1;
        echo "❌ Event " . ($index + 1) . ": FAILED TO PARSE\n";

        // Show first few lines to debug
        $lines = explode("\n", $eventData);
        $preview = array_slice($lines, 0, 10);
        echo "   Preview:\n";
        foreach ($preview as $line) {
            echo "   " . trim($line) . "\n";
        }
        echo "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total events: $totalEvents\n";
echo "Parsed successfully: " . count($parsed) . "\n";
echo "Failed to parse: " . count($failed) . "\n";

if (!empty($failed)) {
    echo "\nFailed event numbers: " . implode(', ', $failed) . "\n";
}
?>
