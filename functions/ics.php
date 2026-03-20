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
