<?php
/**
 * ICS RFC 5545 Helper Functions
 * Idol Stage Timetable
 *
 * Shared by feed.php and my-feed.php.
 */

/**
 * Output one RFC 5545-compliant ICS line with CRLF and line folding.
 *
 * RFC 5545 §3.1: lines SHOULD NOT exceed 75 octets (excluding CRLF).
 * Longer lines are folded by inserting CRLF + SPACE (continuation).
 * Folding respects UTF-8 multi-byte character boundaries.
 */
function icsLine(string $line): void {
    echo icsFold($line) . "\r\n";
}

function icsFold(string $line): string {
    if (strlen($line) <= 75) {
        return $line;
    }

    $folded    = '';
    $lineBytes = 0;
    $i         = 0;
    $len       = strlen($line);

    while ($i < $len) {
        // Determine UTF-8 character byte length from leading byte
        $byte = ord($line[$i]);
        if ($byte < 0x80)      { $charLen = 1; }
        elseif ($byte < 0xE0)  { $charLen = 2; }
        elseif ($byte < 0xF0)  { $charLen = 3; }
        else                   { $charLen = 4; }

        // Fold before this character if it would exceed 75 bytes
        if ($lineBytes + $charLen > 75 && $lineBytes > 0) {
            $folded    .= "\r\n ";  // CRLF + SPACE continuation
            $lineBytes  = 1;        // the leading space is 1 byte
        }

        $folded    .= substr($line, $i, $charLen);
        $lineBytes += $charLen;
        $i         += $charLen;
    }

    return $folded;
}

/**
 * Escape special characters in an ICS text value per RFC 5545 §3.3.11.
 * Escapes: backslash, semicolon, comma, newline.
 *
 * Used for: individual CATEGORIES values (where comma IS a value delimiter).
 * For CATEGORIES the caller splits on ',' first so that the delimiter commas
 * are never passed into this function.
 */
function icsEscape(string $value): string {
    $value = str_replace('\\', '\\\\', $value); // backslash must be first
    $value = str_replace(';',  '\\;',  $value);
    $value = str_replace(',',  '\\,',  $value);
    $value = str_replace("\n", '\\n',  $value);
    $value = str_replace("\r", '',     $value);
    return $value;
}

/**
 * Escape special characters for single-value TEXT properties:
 * SUMMARY, LOCATION, DESCRIPTION.
 *
 * Commas are intentionally NOT escaped here.  RFC 5545 §3.3.11 requires
 * escaping commas in TEXT values, but in practice Apple Calendar, Google
 * Calendar, Outlook, and iOS Calendar all export SUMMARY with unescaped
 * commas — and some clients misinterpret the backslash in "\," causing the
 * title to be truncated at that position.  Since SUMMARY/LOCATION/DESCRIPTION
 * are single-value properties (comma is never a value delimiter in them),
 * leaving commas unescaped is universally safe for display.
 */
function icsEscapeText(string $value): string {
    $value = str_replace('\\', '\\\\', $value); // backslash must be first
    $value = str_replace(';',  '\\;',  $value);
    // commas left as-is — not a delimiter in single-value TEXT properties
    $value = str_replace("\n", '\\n',  $value);
    $value = str_replace("\r", '',     $value);
    return $value;
}

/**
 * Generate a VTIMEZONE block for the given TZID.
 * Uses PHP's DateTimeZone to determine offset and DST transitions.
 * Returns empty string if TZID is invalid.
 */
function icsVtimezone(string $tzid): string {
    try {
        $tz    = new DateTimeZone($tzid);
        $year  = (int)date('Y');
        $trans = $tz->getTransitions(mktime(0, 0, 0, 1, 1, $year), mktime(23, 59, 59, 12, 31, $year));

        // Collect one STANDARD state and one DAYLIGHT state (if DST observed)
        $stdState = null;
        $dstState = null;
        foreach (($trans ?: []) as $t) {
            if ($t['isdst']) {
                if (!$dstState) $dstState = $t;
            } else {
                if (!$stdState) $stdState = $t;
            }
        }
        // Fallback: use current offset if no transitions found
        if (!$stdState) {
            $dt       = new DateTime('now', $tz);
            $stdState = ['offset' => $tz->getOffset($dt), 'abbr' => date_format($dt, 'T'), 'isdst' => false];
        }

        $lines = ["BEGIN:VTIMEZONE", "TZID:" . $tzid];

        $stdFrom = $dstState ? $dstState['offset'] : $stdState['offset'];
        $lines[] = "BEGIN:STANDARD";
        $lines[] = "DTSTART:16010101T000000";
        $lines[] = "TZOFFSETFROM:" . icsOffsetString($stdFrom);
        $lines[] = "TZOFFSETTO:"   . icsOffsetString($stdState['offset']);
        $lines[] = "TZNAME:"       . ($stdState['abbr'] ?? 'STD');
        $lines[] = "END:STANDARD";

        if ($dstState) {
            $lines[] = "BEGIN:DAYLIGHT";
            $lines[] = "DTSTART:16010101T000000";
            $lines[] = "TZOFFSETFROM:" . icsOffsetString($stdState['offset']);
            $lines[] = "TZOFFSETTO:"   . icsOffsetString($dstState['offset']);
            $lines[] = "TZNAME:"       . ($dstState['abbr'] ?? 'DST');
            $lines[] = "END:DAYLIGHT";
        }

        $lines[] = "END:VTIMEZONE";
        return implode("\r\n", $lines) . "\r\n";
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Format a UTC offset in seconds as ±HHMM string (e.g. +0700, -0500).
 */
function icsOffsetString(int $seconds): string {
    $sign = $seconds >= 0 ? '+' : '-';
    $abs  = abs($seconds);
    return sprintf('%s%02d%02d', $sign, intdiv($abs, 3600), ($abs % 3600) / 60);
}
