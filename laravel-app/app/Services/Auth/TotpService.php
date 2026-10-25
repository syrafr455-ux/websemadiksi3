<?php

namespace App\Services\Auth;

use Illuminate\Support\Str;

class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate random Base32 secret (default 16 chars = 80 bits, standard for TOTP).
     */
    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        $maxIndex = strlen(self::BASE32_CHARS) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $maxIndex)];
        }

        return $secret;
    }

    /**
     * Decode a Base32 string into binary data.
     */
    public function base32Decode(string $base32): string
    {
        $clean = strtoupper(trim(str_replace([' ', '-', '='], '', $base32)));
        if ($clean === '') {
            return '';
        }

        $binaryString = '';
        $len = strlen($clean);
        for ($i = 0; $i < $len; $i++) {
            $char = $clean[$i];
            $pos = strpos(self::BASE32_CHARS, $char);
            if ($pos === false) {
                continue;
            }
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        $totalBits = strlen($binaryString);
        for ($i = 0; $i + 8 <= $totalBits; $i += 8) {
            $bytes .= chr(bindec(substr($binaryString, $i, 8)));
        }

        return $bytes;
    }

    /**
     * Generate standard RFC 6238 TOTP 6-digit code for a secret at a specific timestamp.
     */
    public function generateCode(string $secret, ?int $timestamp = null, int $digits = 6, int $period = 30): string
    {
        $timestamp = $timestamp ?? time();
        $binarySecret = $this->base32Decode($secret);

        $timeWindow = (int) floor($timestamp / $period);

        // Pack 64-bit integer big-endian (compatible across 32-bit & 64-bit systems)
        $binaryTime = pack('NN', ($timeWindow >> 32) & 0xFFFFFFFF, $timeWindow & 0xFFFFFFFF);

        $hash = hash_hmac('sha1', $binaryTime, $binarySecret, true);

        // Dynamic truncation (RFC 4226 / RFC 6238)
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binaryVal = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $mod = 10 ** $digits;
        $otp = $binaryVal % $mod;

        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify TOTP code with window tolerance (default ±1 window = ±30s).
     */
    public function verifyCode(string $secret, string $code, int $window = 1, ?int $timestamp = null, int $digits = 6, int $period = 30): bool
    {
        $timestamp = $timestamp ?? time();
        $cleanCode = trim(str_replace([' ', '-'], '', $code));

        if (strlen($cleanCode) !== $digits || ! ctype_digit($cleanCode)) {
            return false;
        }

        for ($i = -$window; $i <= $window; $i++) {
            $checkTime = $timestamp + ($i * $period);
            $validCode = $this->generateCode($secret, $checkTime, $digits, $period);
            if (hash_equals($validCode, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build standard otpauth:// URL for authenticator apps (Google Authenticator, Aegis, Authy, etc.).
     */
    public function getOtpauthUrl(string $accountName, string $issuer, string $secret): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate 8 unique one-time recovery codes (plain for user display, hashed for storage).
     *
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            // Format: XXXX-XXXX (uppercase alphanumeric)
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
            $plain[] = $code;
            $hashed[] = hash('sha256', $code);
        }

        return [
            'plain' => $plain,
            'hashed' => $hashed,
        ];
    }

    /**
     * Verify and consume a recovery code from user's hashed recovery codes list.
     * Returns true if code is valid and consumed; false otherwise.
     *
     * @param  list<string>|null  $hashedCodes
     * @return array{valid: bool, remainingHashed: list<string>}
     */
    public function consumeRecoveryCode(string $rawCode, ?array $hashedCodes): array
    {
        if (empty($hashedCodes)) {
            return ['valid' => false, 'remainingHashed' => []];
        }

        $cleanCode = strtoupper(trim(str_replace(' ', '', $rawCode)));
        $incomingHash = hash('sha256', $cleanCode);

        $foundIndex = null;
        foreach ($hashedCodes as $idx => $hash) {
            if (hash_equals($hash, $incomingHash)) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === null) {
            return ['valid' => false, 'remainingHashed' => $hashedCodes];
        }

        // Consume the code by removing it from the array
        unset($hashedCodes[$foundIndex]);

        return [
            'valid' => true,
            'remainingHashed' => array_values($hashedCodes),
        ];
    }
}
