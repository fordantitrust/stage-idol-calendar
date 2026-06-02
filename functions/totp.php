<?php
/**
 * TOTP / 2FA helpers (RFC 6238)
 */

function totp_base32_encode(string $binary): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $out = '';

    for ($i = 0, $len = strlen($binary); $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
    }

    for ($i = 0, $len = strlen($bits); $i < $len; $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $out .= $alphabet[bindec($chunk)];
    }

    return $out;
}

function totp_base32_decode(string $base32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $clean = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $base32) ?? '');
    $bits = '';
    $out = '';

    for ($i = 0, $len = strlen($clean); $i < $len; $i++) {
        $pos = strpos($alphabet, $clean[$i]);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
        $out .= chr(bindec(substr($bits, $i, 8)));
    }

    return $out;
}

function totp_generate_secret(int $bytes = 20): string {
    return totp_base32_encode(random_bytes(max(10, $bytes)));
}

function totp_hotp(string $secretBytes, int $counter, int $digits = 6): string {
    $binCounter = pack('N2', intdiv($counter, 0x100000000), $counter & 0xffffffff);
    $hash = hash_hmac('sha1', $binCounter, $secretBytes, true);
    $offset = ord($hash[19]) & 0x0f;
    $code = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % (10 ** $digits);

    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}

function totp_code(string $base32Secret, ?int $time = null, int $period = 30, int $digits = 6): string {
    $time = $time ?? time();
    return totp_hotp(totp_base32_decode($base32Secret), intdiv($time, $period), $digits);
}

function totp_verify(string $base32Secret, string $code, ?int $time = null, int $window = 1, int $period = 30, int $digits = 6, ?int $lastUsedStep = null): array {
    $cleanCode = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($cleanCode) !== $digits) {
        return ['valid' => false, 'step' => null];
    }

    $time = $time ?? time();
    $currentStep = intdiv($time, $period);
    $secretBytes = totp_base32_decode($base32Secret);

    for ($offset = -$window; $offset <= $window; $offset++) {
        $step = $currentStep + $offset;
        if ($step < 0) {
            continue;
        }
        if ($lastUsedStep !== null && $step <= $lastUsedStep) {
            continue;
        }
        if (hash_equals(totp_hotp($secretBytes, $step, $digits), $cleanCode)) {
            return ['valid' => true, 'step' => $step];
        }
    }

    return ['valid' => false, 'step' => null];
}

function totp_otpauth_uri(string $issuer, string $account, string $secret): string {
    $label = rawurlencode($issuer . ':' . $account);
    $query = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ], '', '&', PHP_QUERY_RFC3986);

    return 'otpauth://totp/' . $label . '?' . $query;
}

function twofa_generate_backup_codes(int $count = 8): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = strtoupper(bin2hex(random_bytes(5)));
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }
    return $codes;
}

function twofa_hash_backup_codes(array $codes): string {
    $hashes = array_map(function($code) {
        return password_hash(twofa_normalize_backup_code((string)$code), PASSWORD_DEFAULT);
    }, $codes);
    return json_encode($hashes);
}

function twofa_normalize_backup_code(string $code): string {
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
}

function twofa_consume_backup_code(?string $jsonHashes, string $code): array {
    $hashes = json_decode((string)$jsonHashes, true);
    if (!is_array($hashes)) {
        $hashes = [];
    }

    $normalized = twofa_normalize_backup_code($code);
    foreach ($hashes as $idx => $hash) {
        if (is_string($hash) && password_verify($normalized, $hash)) {
            unset($hashes[$idx]);
            return ['valid' => true, 'hashes_json' => json_encode(array_values($hashes)), 'remaining' => count($hashes)];
        }
    }

    return ['valid' => false, 'hashes_json' => json_encode(array_values($hashes)), 'remaining' => count($hashes)];
}
