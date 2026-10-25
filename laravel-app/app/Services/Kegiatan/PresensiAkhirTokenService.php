<?php

namespace App\Services\Kegiatan;

class PresensiAkhirTokenService
{
    public const SLOT_SECONDS = 30;

    /**
     * Dapatkan kunci turunan dari APP_KEY khusus presensi akhir.
     */
    protected function getDerivedKey(): string
    {
        $appKey = (string) config('app.key');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return hash_hmac('sha256', 'semadiksi-presensi-akhir-secret-key', $appKey);
    }

    /**
     * Hitung nomor slot waktu 30 detik saat ini (atau dari timestamp tertentu).
     */
    public function currentSlot(?int $timestamp = null): int
    {
        $time = $timestamp ?? now()->timestamp;

        return (int) floor($time / self::SLOT_SECONDS);
    }

    /**
     * Sisa detik dalam slot 30 detik saat ini.
     */
    public function secondsRemainingInSlot(?int $timestamp = null): int
    {
        $time = $timestamp ?? now()->timestamp;

        return self::SLOT_SECONDS - ($time % self::SLOT_SECONDS);
    }

    /**
     * Hasilkan token presensi akhir bertanda tangan HMAC untuk slot tertentu.
     */
    public function generateToken(int $kegiatanId, ?int $slot = null): string
    {
        $slotNumber = $slot ?? $this->currentSlot();
        $payload = "{$kegiatanId}:{$slotNumber}";
        $signature = hash_hmac('sha256', $payload, $this->getDerivedKey());

        $combined = "{$payload}:{$signature}";

        // Encode base64url agar aman digunakan dalam URL / QR
        return rtrim(strtr(base64_encode($combined), '+/', '-_'), '=');
    }

    /**
     * Validasi token presensi akhir:
     * - Kegiatan ID harus cocok.
     * - Slot waktu harus slot saat ini atau slot sebelumnya (toleransi 1 slot = 30 detik).
     * - Tanda tangan HMAC harus valid.
     */
    public function verifyToken(int $kegiatanId, ?string $token): bool
    {
        if (! $token) {
            return false;
        }

        // Decode base64url
        $base64 = strtr($token, '-_', '+/');
        $pad = strlen($base64) % 4;
        if ($pad > 0) {
            $base64 .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        [$tokenKegiatanId, $tokenSlot, $tokenSig] = $parts;

        if ((int) $tokenKegiatanId !== (int) $kegiatanId) {
            return false;
        }

        $tokenSlot = (int) $tokenSlot;
        $currentSlot = $this->currentSlot();

        // Server menerima slot saat ini dan sebelumnya (dan toleransi +1 jika ada beda detik kecil)
        $allowedSlots = [$currentSlot, $currentSlot - 1, $currentSlot + 1];
        if (! in_array($tokenSlot, $allowedSlots, true)) {
            return false;
        }

        $expectedPayload = "{$tokenKegiatanId}:{$tokenSlot}";
        $expectedSig = hash_hmac('sha256', $expectedPayload, $this->getDerivedKey());

        return hash_equals($expectedSig, $tokenSig);
    }
}
