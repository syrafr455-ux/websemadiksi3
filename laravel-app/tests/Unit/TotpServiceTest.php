<?php

namespace Tests\Unit;

use App\Services\Auth\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    /**
     * Uji kesesuaian algoritma TOTP dengan vektor uji resmi RFC 6238 Appendix B.
     * Secret: ASCII "12345678901234567890" => Base32 "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ"
     */
    public function test_rfc_6238_test_vectors(): void
    {
        $totp = new TotpService;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        // RFC 6238 Appendix B test vectors for SHA-1 (digits=6, period=30)
        $vectors = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
        ];

        foreach ($vectors as $timestamp => $expectedCode) {
            $generated = $totp->generateCode($secret, $timestamp, 6, 30);
            $this->assertSame(
                $expectedCode,
                $generated,
                "Vektor uji RFC 6238 gagal pada timestamp: {$timestamp}"
            );

            // Verifikasi validasi kode tepat waktu
            $this->assertTrue($totp->verifyCode($secret, $expectedCode, 0, $timestamp));

            // Verifikasi toleransi window ±1 (periode 30 detik)
            $this->assertTrue($totp->verifyCode($secret, $expectedCode, 1, $timestamp + 29));
            $this->assertTrue($totp->verifyCode($secret, $expectedCode, 1, $timestamp - 29));
            $this->assertFalse($totp->verifyCode($secret, $expectedCode, 1, $timestamp + 65));
        }
    }

    public function test_recovery_codes_generation_and_consumption(): void
    {
        $totp = new TotpService;
        $codes = $totp->generateRecoveryCodes(8);

        $this->assertCount(8, $codes['plain']);
        $this->assertCount(8, $codes['hashed']);

        $plainCode = $codes['plain'][0];
        $hashedCodes = $codes['hashed'];

        // Coba konsumsi kode yang benar
        $res = $totp->consumeRecoveryCode($plainCode, $hashedCodes);
        $this->assertTrue($res['valid']);
        $this->assertCount(7, $res['remainingHashed']);

        // Coba konsumsi ulang kode yang sama (harus gagal, sekali pakai)
        $res2 = $totp->consumeRecoveryCode($plainCode, $res['remainingHashed']);
        $this->assertFalse($res2['valid']);
        $this->assertCount(7, $res2['remainingHashed']);

        // Coba konsumsi kode yang salah
        $res3 = $totp->consumeRecoveryCode('INVALID-CODE', $res['remainingHashed']);
        $this->assertFalse($res3['valid']);
    }

    /**
     * Uji pencegahan replay attack (kode yang sudah berhasil tidak bisa dipakai lagi dalam jendela waktu yang sama).
     */
    public function test_prevents_replay_attack_in_same_or_earlier_time_step(): void
    {
        $totp = new TotpService;
        $secret = $totp->generateSecret();
        $timestamp = 1700000000;

        $code = $totp->generateCode($secret, $timestamp);

        // Penggunaan pertama: Berhasil dan menghasilkan matchedStep
        $matchedStep = null;
        $success = $totp->verifyCode($secret, $code, 1, $timestamp, 6, 30, null, $matchedStep);
        $this->assertTrue($success);
        $this->assertNotNull($matchedStep);
        $this->assertSame((int) floor($timestamp / 30), $matchedStep);

        // Penggunaan kedua (replay attack pada waktu yang sama): DITOLAK
        $replayStep = null;
        $replayed = $totp->verifyCode($secret, $code, 1, $timestamp, 6, 30, $matchedStep, $replayStep);
        $this->assertFalse($replayed);
        $this->assertNull($replayStep);

        // Penggunaan kedua dalam toleransi jendela (misal +15 detik masih step yang sama): DITOLAK
        $replayedWindow = $totp->verifyCode($secret, $code, 1, $timestamp + 15, 6, 30, $matchedStep);
        $this->assertFalse($replayedWindow);

        // Waktu berikutnya (+30 detik, step baru) dengan kode baru: BERHASIL
        $nextTimestamp = $timestamp + 30;
        $nextCode = $totp->generateCode($secret, $nextTimestamp);
        $nextStep = null;
        $nextSuccess = $totp->verifyCode($secret, $nextCode, 1, $nextTimestamp, 6, 30, $matchedStep, $nextStep);
        $this->assertTrue($nextSuccess);
        $this->assertSame($matchedStep + 1, $nextStep);
    }
}
