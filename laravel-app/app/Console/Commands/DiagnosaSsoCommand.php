<?php

namespace App\Console\Commands;

use App\Contracts\KipRecipientProvider;
use App\Contracts\UnusaAcademicProvider;
use App\Models\User;
use App\Services\Auth\KeycloakService;
use App\Support\Masking;
use App\Support\NormalisasiNim;
use Illuminate\Console\Command;

class DiagnosaSsoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'semadiksi:diagnosa-sso {nim : NIM mahasiswa yang akan didiagnosa}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnosa kelayakan akun untuk login SSO UNUSA secara read-only tanpa modifikasi database';

    /**
     * Execute the console command.
     */
    public function handle(
        KeycloakService $keycloak,
        UnusaAcademicProvider $academicProvider,
        KipRecipientProvider $kipProvider
    ): int {
        $nimRaw = (string) $this->argument('nim');
        $nim = NormalisasiNim::bersihkan($nimRaw);

        if ($nim === '') {
            $this->error('NIM tidak boleh kosong.');

            return self::FAILURE;
        }

        $nimTersamar = Masking::nim($nim);

        $this->newLine();
        $this->info('=== Diagnosa Login SSO SEMADIKSI (Read-Only) ===');
        $this->line("Target NIM: <comment>{$nimTersamar}</comment>");
        $this->newLine();

        // 1. Cek tabel users
        $users = User::where('nim', $nim)->where('is_unusa', true)->get();
        $userCount = $users->count();
        $user = $users->first();

        // 2. Cek data master
        $master = $academicProvider->cariMahasiswaByNim($nim);

        // 3. Cek data penerima KIP-K
        $penerima = $kipProvider->cariByNim($nim);

        // 4. Evaluasi SSO menggunakan logika yang sama persis dengan callback SSO
        $sub = $user?->keycloak_sub;
        $evaluasi = $keycloak->evaluasiLoginSso($nim, $sub);

        // Susun baris diagnosa
        $rows = [
            ['NIM (Tersamar)', $nimTersamar],
            ['Akun di SEMADIKSI', $userCount > 1 ? "Ada ({$userCount} akun ganda)" : ($user ? 'Ada' : 'Tidak Ada')],
            ['Peran Akun', $user?->role?->value ?? '-'],
            ['Status Aktif', $user ? ($user->is_blocked ? 'Diblokir' : 'Aktif') : '-'],
            ['Email Terverifikasi', $user ? ($user->email_verified_at ? 'Terverifikasi' : 'Belum terverifikasi') : '-'],
            ['keycloak_sub Tertaut', $user ? ($user->keycloak_sub ? 'Tertaut' : 'Tidak tertaut') : '-'],
            ['Data Master Mahasiswa', $master && ! empty($master['nim']) ? 'Ada' : 'Tidak Ada'],
            ['Penerima KIP-K', $penerima ? 'Terdaftar' : 'Tidak Terdaftar'],
            ['Kode Alasan Evaluasi', $evaluasi->kodeAlasan],
            ['Pesan ke Pengguna', $evaluasi->pesan],
            ['Prediksi Kelayakan SSO', $evaluasi->dapatLogin ? ($evaluasi->perluAktivasi ? 'Dapat Login (Akan diaktivasi via SSO)' : 'Dapat Login (Akun Aktif)') : 'Ditolak'],
        ];

        $this->table(['Parameter', 'Status / Nilai'], $rows);

        $this->newLine();
        if ($evaluasi->dapatLogin) {
            $this->info("HASIL: Akun memenuhi syarat untuk login SSO ({$evaluasi->kodeAlasan}).");
        } else {
            $this->warn("HASIL: Login SSO akan DITOLAK dengan alasan: {$evaluasi->kodeAlasan}.");
        }
        $this->newLine();

        return self::SUCCESS;
    }
}
