<?php

namespace App\Console\Commands;

use App\Services\Auth\KeycloakService;
use Illuminate\Console\Command;

class PreflightCommand extends Command
{
    protected $signature = 'semadiksi:preflight';

    protected $description = 'Periksa kesiapan konfigurasi keamanan dan integrasi SEMADIKSI sebelum deployment (read-only)';

    public function handle(KeycloakService $keycloak): int
    {
        $this->newLine();
        $this->info('Memeriksa kesiapan konfigurasi SEMADIKSI...');
        $this->newLine();

        $rows = [];
        $adaKegagalanKritis = false;

        // 1. APP_ENV
        $env = (string) config('app.env', 'production');
        $isProd = $env === 'production';
        $rows[] = ['APP_ENV', $env, 'INFO', $isProd ? 'Mode produksi aktif' : 'Mode non-produksi'];

        // 2. APP_DEBUG
        $debug = (bool) config('app.debug');
        if ($isProd && $debug) {
            $adaKegagalanKritis = true;
            $rows[] = ['APP_DEBUG', 'true', 'FAIL', 'Harus false di lingkungan produksi'];
        } else {
            $rows[] = ['APP_DEBUG', $debug ? 'true' : 'false', 'PASS', $isProd ? 'Aman (nonaktif)' : 'Aktif untuk debugging'];
        }

        // 3. APP_KEY
        $key = (string) config('app.key');
        if ($key === '') {
            $adaKegagalanKritis = true;
            $rows[] = ['APP_KEY', '(kosong)', 'FAIL', 'APP_KEY wajib dibuat (php artisan key:generate)'];
        } else {
            $rows[] = ['APP_KEY', 'Terisi', 'PASS', 'Kunci enkripsi aplikasi tersedia'];
        }

        // 4. APP_URL
        $url = (string) config('app.url');
        if ($isProd && ! str_starts_with(strtolower($url), 'https://')) {
            $adaKegagalanKritis = true;
            $rows[] = ['APP_URL', $url, 'FAIL', 'Wajib menggunakan HTTPS di lingkungan produksi'];
        } else {
            $rows[] = ['APP_URL', $url, 'PASS', str_starts_with(strtolower($url), 'https://') ? 'HTTPS aktif' : 'HTTP lokal'];
        }

        // 5. SESSION_SECURE_COOKIE & SESSION_SAME_SITE & SESSION_HTTP_ONLY
        $secure = config('session.secure');
        $sameSite = (string) config('session.same_site', 'lax');
        $httpOnly = (bool) config('session.http_only', true);

        $secureLabel = $secure === true ? 'true' : ($secure === false ? 'false' : 'null (otomatis HTTPS)');
        if ($isProd && $secure === false) {
            $rows[] = ['SESSION_SECURE_COOKIE', $secureLabel, 'WARN', 'Disarankan true atau null pada lingkungan produksi'];
        } else {
            $rows[] = ['SESSION_SECURE_COOKIE', $secureLabel, 'PASS', 'Pengaturan cookie secure sesuai'];
        }

        $sameSiteValid = in_array(strtolower($sameSite), ['lax', 'strict'], true);
        $rows[] = [
            'SESSION_SAME_SITE',
            $sameSite,
            $sameSiteValid ? 'PASS' : 'WARN',
            $sameSiteValid ? 'Mitigasi CSRF aktif' : 'Disarankan disetel ke lax atau strict',
        ];

        $rows[] = [
            'SESSION_HTTP_ONLY',
            $httpOnly ? 'true' : 'false',
            $httpOnly ? 'PASS' : 'FAIL',
            $httpOnly ? 'Mencegah akses cookie via JavaScript' : 'Cookie sesi rentan XSS',
        ];

        // 6. SEED_*_PASSWORD
        $seedAdmin = (string) config('seeding.admin_password');
        $seedMhs = (string) config('seeding.mahasiswa_password');
        if ($isProd) {
            $rows[] = ['SEED_*_PASSWORD', '-', 'PASS', 'Seeder demo otomatis ditolak di lingkungan produksi'];
        } else {
            $statusSeed = ($seedAdmin !== '' || $seedMhs !== '') ? 'Tersetel' : 'Kosong (acak)';
            $rows[] = ['SEED_*_PASSWORD', $statusSeed, 'INFO', 'Password seeder akun demo lokal'];
        }

        // 7. Mail Configuration
        $mailer = (string) config('mail.default');
        $mailUser = (string) config('mail.mailers.smtp.username');
        $mailPass = (string) config('mail.mailers.smtp.password');
        $mailFrom = (string) config('mail.from.address');

        if ($mailer === 'smtp') {
            $userOk = $mailUser !== '' && $mailUser !== 'null';
            $passOk = $mailPass !== '' && $mailPass !== 'null';
            $fromMatches = $userOk && strtolower(trim($mailFrom)) === strtolower(trim($mailUser));

            if ($userOk && $passOk && $fromMatches) {
                $rows[] = ['MAIL (SMTP)', 'Lengkap', 'PASS', 'Username & password terisi; FROM sesuai'];
            } else {
                $catatan = [];
                if (! $userOk) {
                    $catatan[] = 'MAIL_USERNAME kosong';
                }
                if (! $passOk) {
                    $catatan[] = 'MAIL_PASSWORD kosong';
                }
                if ($userOk && ! $fromMatches) {
                    $catatan[] = 'MAIL_FROM_ADDRESS harus sama dengan MAIL_USERNAME';
                }
                $rows[] = ['MAIL (SMTP)', 'Perlu dicek', 'WARN', implode(', ', $catatan)];
            }
        } else {
            $rows[] = ['MAIL_MAILER', $mailer, 'INFO', 'Bukan SMTP (email tidak dikirim ke server luar)'];
        }

        // 8. Keycloak SSO
        $kcEnabled = (bool) config('services.keycloak.enabled');
        $kcBase = (string) config('services.keycloak.base_url');
        $kcSecret = (string) config('services.keycloak.client_secret');
        $kcActive = $keycloak->isActive();

        if ($kcActive) {
            $rows[] = ['KEYCLOAK SSO', 'Aktif', 'PASS', "Base URL: {$kcBase}, Realm: ".config('services.keycloak.realms', 'UNUSA')];
        } elseif ($kcEnabled) {
            $rows[] = ['KEYCLOAK SSO', 'Konfigurasi Belum Lengkap', 'WARN', 'KEYCLOAK_ENABLED=true namun base URL atau secret belum terisi'];
        } else {
            $rows[] = ['KEYCLOAK SSO', 'Nonaktif', 'INFO', 'Fitur SSO mati (tombol login SSO disembunyikan)'];
        }

        // 9. QUEUE_CONNECTION
        $queue = (string) config('queue.default');
        if ($isProd && $queue === 'sync') {
            $rows[] = ['QUEUE_CONNECTION', $queue, 'WARN', 'Di production disarankan database/redis agar worker memproses email di background'];
        } else {
            $rows[] = ['QUEUE_CONNECTION', $queue, 'PASS', "Koneksi antrean: {$queue}"];
        }

        $this->table(['Komponen', 'Nilai', 'Status', 'Keterangan'], $rows);
        $this->newLine();

        if ($adaKegagalanKritis) {
            $this->error('Ditemukan kegagalan kritis yang harus diperbaiki sebelum deployment.');

            return self::FAILURE;
        }

        $this->info('Pemeriksaan preflight selesai.');

        return self::SUCCESS;
    }
}
