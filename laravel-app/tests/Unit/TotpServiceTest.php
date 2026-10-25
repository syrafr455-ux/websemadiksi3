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
}
