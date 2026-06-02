<?php
/**
 * SMTP email notification helpers.
 */

function email_is_enabled(): bool {
    return defined('EMAIL_ENABLED') && EMAIL_ENABLED === true;
}

function email_parse_recipients(string $raw): array {
    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $emails = [];
    foreach ($parts as $part) {
        $email = trim($part);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[strtolower($email)] = $email;
        }
    }
    return array_values($emails);
}

function email_log(string $level, string $message, array $context = []): void {
    $logDir = __DIR__ . '/../cache/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $safeContext = [];
    foreach ($context as $key => $value) {
        if (preg_match('/password|token|secret/i', (string)$key)) {
            continue;
        }
        $safeContext[$key] = is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($level) . ' ' . $message;
    if ($safeContext) {
        $line .= ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents($logDir . '/email.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function email_header_encode(string $value): string {
    $value = trim(str_replace(["\r", "\n"], '', $value));
    return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
}

function email_address(string $email, string $name = ''): string {
    $email = trim(str_replace(["\r", "\n"], '', $email));
    $name = trim(str_replace(["\r", "\n"], '', $name));
    return $name !== '' ? email_header_encode($name) . ' <' . $email . '>' : $email;
}

function email_smtp_read($socket): string {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function email_smtp_command($socket, string $command, array $expectedCodes): string {
    if ($command !== '') {
        fwrite($socket, $command . "\r\n");
    }
    $response = email_smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP command failed: ' . trim($response));
    }
    return $response;
}

function email_build_message(string $subject, string $htmlBody, string $textBody, string $fromEmail, string $fromName, array $toEmails): string {
    $boundary = 'b_' . bin2hex(random_bytes(12));
    $headers = [
        'From: ' . email_address($fromEmail, $fromName),
        'To: ' . implode(', ', $toEmails),
        'Subject: ' . email_header_encode($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    return implode("\r\n", $headers) . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $textBody . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $htmlBody . "\r\n\r\n"
        . '--' . $boundary . "--\r\n";
}

function email_send(string $subject, string $htmlBody, string $textBody, array $options = []): bool {
    if (empty($options['force']) && !email_is_enabled()) {
        return false;
    }

    $host = trim($options['smtp_host'] ?? EMAIL_SMTP_HOST);
    $port = (int)($options['smtp_port'] ?? EMAIL_SMTP_PORT);
    $encryption = strtolower(trim($options['smtp_encryption'] ?? EMAIL_SMTP_ENCRYPTION));
    $username = trim($options['smtp_username'] ?? EMAIL_SMTP_USERNAME);
    $password = (string)($options['smtp_password'] ?? EMAIL_SMTP_PASSWORD);
    $fromEmail = trim($options['from_email'] ?? EMAIL_FROM_EMAIL);
    $fromName = trim($options['from_name'] ?? EMAIL_FROM_NAME);
    $recipients = $options['recipients'] ?? email_parse_recipients(EMAIL_RECIPIENTS);

    if (is_string($recipients)) {
        $recipients = email_parse_recipients($recipients);
    }

    if ($host === '' || $port <= 0 || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || empty($recipients)) {
        email_log('WARN', 'Email skipped due to incomplete SMTP configuration');
        return false;
    }

    try {
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new RuntimeException('SMTP connect failed: ' . $errstr);
        }
        stream_set_timeout($socket, 15);

        email_smtp_command($socket, '', [220]);
        email_smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);

        if ($encryption === 'tls') {
            email_smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed');
            }
            email_smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        }

        if ($username !== '') {
            email_smtp_command($socket, 'AUTH LOGIN', [334]);
            email_smtp_command($socket, base64_encode($username), [334]);
            email_smtp_command($socket, base64_encode($password), [235]);
        }

        email_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        foreach ($recipients as $recipient) {
            email_smtp_command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        }
        email_smtp_command($socket, 'DATA', [354]);

        $message = email_build_message($subject, $htmlBody, $textBody, $fromEmail, $fromName, $recipients);
        fwrite($socket, str_replace("\n.", "\n..", $message) . "\r\n.\r\n");
        email_smtp_command($socket, '', [250]);
        email_smtp_command($socket, 'QUIT', [221]);
        fclose($socket);

        email_log('INFO', 'Email sent', ['subject' => $subject, 'recipients' => count($recipients)]);
        return true;
    } catch (Throwable $e) {
        if (isset($socket) && is_resource($socket)) {
            fclose($socket);
        }
        email_log('WARN', 'Email send failed', ['error' => $e->getMessage(), 'subject' => $subject]);
        return false;
    }
}

function email_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function email_site_name(): string {
    if (function_exists('get_site_title')) {
        $siteName = (string)get_site_title();
    } elseif (defined('APP_NAME')) {
        $siteName = (string)APP_NAME;
    } else {
        $siteName = 'Idol Stage Timetable';
    }

    $siteName = trim(str_replace(["\r", "\n"], '', $siteName));
    return $siteName !== '' ? $siteName : 'Idol Stage Timetable';
}

function email_extract_app_base_path(string $path): ?string {
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return null;
    }

    $parsedPath = parse_url($path, PHP_URL_PATH);
    if (is_string($parsedPath) && $parsedPath !== '') {
        $path = $parsedPath;
    }

    if (preg_match('#^(.*?)/(?:api|admin)(?:/|$)#', $path, $matches)) {
        $path = $matches[1];
    } elseif (preg_match('#\.php$#i', basename($path))) {
        $path = dirname($path);
    }

    $path = rtrim($path, '/');
    return ($path === '' || $path === '.' || $path === '/') ? '' : $path;
}

function email_app_base_path(): string {
    foreach (['SCRIPT_NAME', 'PHP_SELF', 'REQUEST_URI'] as $key) {
        $base = email_extract_app_base_path((string)($_SERVER[$key] ?? ''));
        if ($base !== null) {
            return $base;
        }
    }

    if (function_exists('get_base_path')) {
        return email_extract_app_base_path(get_base_path()) ?? '';
    }

    return '';
}

function email_admin_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = function_exists('get_safe_host') ? get_safe_host() : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = email_app_base_path();
    return $scheme . '://' . $host . rtrim($base, '/') . '/admin/';
}

function email_format_request_body(string $heading, array $rows): array {
    $adminUrl = email_admin_url();
    $htmlRows = '';
    $textRows = [];

    foreach ($rows as $label => $value) {
        $value = trim((string)$value);
        if ($value === '') {
            $value = '-';
        }
        $htmlRows .= '<tr><th style="text-align:left;padding:6px 10px;border-bottom:1px solid #eee;white-space:nowrap;">'
            . email_escape($label) . '</th><td style="padding:6px 10px;border-bottom:1px solid #eee;">'
            . nl2br(email_escape($value)) . '</td></tr>';
        $textRows[] = $label . ': ' . $value;
    }

    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#222;">'
        . '<h2>' . email_escape($heading) . '</h2>'
        . '<table style="border-collapse:collapse;width:100%;max-width:720px;">' . $htmlRows . '</table>'
        . '<p style="margin-top:18px;"><a href="' . email_escape($adminUrl) . '">Open Admin Requests tab</a></p>'
        . '</body></html>';

    $text = $heading . "\n\n" . implode("\n", $textRows) . "\n\nOpen Admin Requests tab: " . $adminUrl;
    return [$html, $text];
}

function email_notify_program_request_created($requestId, array $payload): bool {
    $title = trim((string)($payload['title'] ?? $payload['summary'] ?? 'Program Request'));
    $subject = '[' . email_site_name() . '] New Program Request';
    [$html, $text] = email_format_request_body('New Program Request #' . $requestId, [
        'Request Type' => $payload['type'] ?? $payload['request_type'] ?? '',
        'Title' => $title,
        'Start' => $payload['start'] ?? '',
        'End' => $payload['end'] ?? '',
        'Location' => $payload['location'] ?? '',
        'Organizer' => $payload['organizer'] ?? '',
        'Categories' => $payload['categories'] ?? '',
        'Event' => $payload['event_name'] ?? $payload['event_slug'] ?? '',
        'Requester Name' => $payload['requester_name'] ?? '',
        'Requester Email' => $payload['requester_email'] ?? '',
        'Note' => $payload['requester_note'] ?? $payload['note'] ?? '',
        'Description' => $payload['description'] ?? '',
    ]);
    return email_send($subject, $html, $text);
}

function email_notify_event_request_created($requestId, array $payload): bool {
    $name = trim((string)($payload['name'] ?? 'Event Request'));
    $subject = '[' . email_site_name() . '] New EventRequest';
    [$html, $text] = email_format_request_body('New EventRequest #' . $requestId, [
        'Request Type' => $payload['type'] ?? $payload['request_type'] ?? 'add',
        'Name' => $name,
        'Start Date' => $payload['start_date'] ?? '',
        'End Date' => $payload['end_date'] ?? '',
        'Requester Name' => $payload['requester_name'] ?? '',
        'Requester Email' => $payload['requester_email'] ?? '',
        'Note' => $payload['note'] ?? '',
        'Description' => $payload['description'] ?? '',
    ]);
    return email_send($subject, $html, $text);
}
