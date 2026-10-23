<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class TesEmailCommand extends Command
{
    protected $signature = 'semadiksi:tes-email {alamat : Alamat email tujuan uji}';

    protected $description = 'Kirim email uji lewat konfigurasi MAIL_* dan jelaskan hasilnya (tanpa menampilkan kata sandi)';

    public function handle(): int
    {
        $alamat = trim((string) $this->argument('alamat'));
        if (! filter_var($alamat, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat email tidak valid.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER saat ini '{$mailer}': email tidak dikirim ke luar. Setel MAIL_MAILER=smtp di .env untuk mengirim lewat Gmail.");
        }

        try {
            Mail::raw(
                "Ini email uji dari SEMADIKSI.\nJika Anda menerimanya, pengiriman email sudah berfungsi.",
                fn ($pesan) => $pesan->to($alamat)->subject('Uji pengiriman email SEMADIKSI')
            );
        } catch (TransportExceptionInterface $e) {
            $this->error($this->jelaskan($e->getMessage()));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Pengiriman gagal: '.get_class($e).'. Periksa log aplikasi.');

            return self::FAILURE;
        }

        $this->info("Berhasil: email uji dikirim ke {$alamat} lewat '{$mailer}'. Periksa kotak masuk (dan folder spam).");

        return self::SUCCESS;
    }

    /**
     * Terjemahkan galat SMTP ke penjelasan yang mudah dipahami. Pesan asli tidak dicetak karena
     * bisa memuat nama pengguna.
     */
    private function jelaskan(string $pesan): string
    {
        $kecil = strtolower($pesan);

        if (str_contains($kecil, '535') || str_contains($kecil, 'username and password not accepted') || str_contains($kecil, 'authenticat')) {
            return 'Kredensial salah: Gmail menolak MAIL_USERNAME atau MAIL_PASSWORD. Pastikan MAIL_PASSWORD berupa sandi aplikasi Google (bukan sandi akun), verifikasi 2 langkah aktif, dan MAIL_FROM_ADDRESS sama dengan MAIL_USERNAME.';
        }

        if (str_contains($kecil, 'could not be established') || str_contains($kecil, 'timed out') || str_contains($kecil, 'refused')
            || str_contains($kecil, 'unreachable') || str_contains($kecil, 'resolve') || str_contains($kecil, 'connection')) {
            return 'Koneksi gagal atau diblokir jaringan: server tidak dapat menjangkau MAIL_HOST:MAIL_PORT (Gmail: smtp.gmail.com port 587). Periksa internet, firewall, atau apakah hosting memblokir port SMTP keluar.';
        }

        return 'Pengiriman gagal karena galat SMTP yang tidak dikenali. Periksa MAIL_* di .env dan log aplikasi.';
    }
}
