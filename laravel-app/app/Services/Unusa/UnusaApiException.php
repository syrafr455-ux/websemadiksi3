<?php

namespace App\Services\Unusa;

use RuntimeException;

/**
 * Galat saat memanggil API UNUSA. Pesan untuk admin dibedakan per jenis; pengguna umum tidak pernah
 * melihatnya (alur pendaftaran kembali ke data impor CSV).
 */
class UnusaApiException extends RuntimeException
{
    public const TIDAK_TERJANGKAU = 'tidak_terjangkau';

    public const HAK_AKSES = 'hak_akses';

    public const KONFIGURASI = 'konfigurasi';

    public const BATAS_LAJU = 'batas_laju';

    public const PEMUTUS_SIRKUIT = 'pemutus_sirkuit';

    public const LAINNYA = 'lainnya';

    public function __construct(public readonly string $jenis, string $pesan = '')
    {
        parent::__construct($pesan !== '' ? $pesan : self::pesanAdmin($jenis));
    }

    public static function pesanAdmin(string $jenis): string
    {
        return match ($jenis) {
            self::TIDAK_TERJANGKAU => 'Server UNUSA tidak terjangkau. Periksa VPN atau gateway.',
            self::HAK_AKSES => 'Akses ke API UNUSA ditolak (401/403). Periksa client id, client secret, scope, dan hak akses yang didaftarkan IT UNUSA.',
            self::KONFIGURASI => 'Konfigurasi API UNUSA belum lengkap. Periksa variabel UNUSA_API_* di .env.',
            self::BATAS_LAJU => 'Batas laju pemanggilan API UNUSA tercapai. Pemanggilan ditunda atau dialihkan ke data CSV.',
            self::PEMUTUS_SIRKUIT => 'Pemutus sirkuit API UNUSA aktif karena kegagalan berulang. Pemanggilan sementara dialihkan ke data CSV.',
            default => 'API UNUSA mengembalikan respons yang tidak terduga. Periksa log aplikasi dan hubungi IT UNUSA.',
        };
    }

    /**
     * Pesan netral untuk pengguna umum.
     */
    public static function pesanPengguna(): string
    {
        return 'Layanan data kampus sedang tidak dapat dihubungi. Coba lagi nanti atau hubungi admin.';
    }
}
