<?php

namespace App\Services;

use App\Enums\PeranSertifikat;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Sertifikat;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateGeneratorService
{
    /**
     * Generate sertifikat dinamis untuk satu penerima.
     *
     * @param  Kegiatan  $kegiatan
     * @param  KegiatanPresensi|KegiatanRegistration|User|array  $recipient
     * @param  PeranSertifikat|string|null  $peran
     * @param  int|null  $presensiId
     * @return Sertifikat
     */
    public function generate(
        Kegiatan $kegiatan,
        KegiatanPresensi|KegiatanRegistration|User|array $recipient,
        PeranSertifikat|string|null $peran = null,
        ?int $presensiId = null
    ): Sertifikat {
        // Normalisasi data penerima
        $recipientData = $this->resolveRecipientData($recipient);
        $userId = $recipientData['user_id'];
        $namaLengkap = $recipientData['nama_lengkap'];
        $nim = $recipientData['nim'];
        $prodi = $recipientData['prodi'];

        if ($recipient instanceof KegiatanPresensi) {
            $presensiId = $recipient->id;
        }

        // Normalisasi peran
        if (is_string($peran)) {
            $peran = PeranSertifikat::tryFrom($peran) ?? PeranSertifikat::Peserta;
        } elseif (! $peran instanceof PeranSertifikat) {
            $peran = PeranSertifikat::Peserta;
        }

        // Tentukan nomor sertifikat
        $nomorSertifikat = Sertifikat::generateCertificateNumber($kegiatan);

        // Siapkan URL verifikasi publik
        $verificationUrl = url("/verifikasi?code={$nomorSertifikat}");

        // Generate QR code SVG sebagai base64 Data URI
        $qrSvg = QrCode::format('svg')
            ->size(130)
            ->errorCorrection('H')
            ->margin(1)
            ->generate($verificationUrl);

        $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        // Background template kustom jika ada
        $backgroundBase64 = null;
        if ($kegiatan->certificate_template_path && Storage::disk('public')->exists($kegiatan->certificate_template_path)) {
            $fullPath = Storage::disk('public')->path($kegiatan->certificate_template_path);
            $mime = mime_content_type($fullPath) ?: 'image/png';
            $bgData = file_get_contents($fullPath);
            if ($bgData !== false) {
                $backgroundBase64 = 'data:' . $mime . ';base64,' . base64_encode($bgData);
            }
        }

        // Tanggal terbit dan tanggal kegiatan
        Carbon::setLocale('id');
        $tanggalTerbit = Carbon::now()->translatedFormat('d F Y');
        $tanggalKegiatan = $kegiatan->tanggal_mulai
            ? Carbon::parse($kegiatan->tanggal_mulai)->translatedFormat('d F Y')
            : $tanggalTerbit;

        // Instance dummy sertifikat untuk render awal
        $dummySertifikat = new Sertifikat([
            'nomor_sertifikat' => $nomorSertifikat,
            'peran_sertifikat' => $peran,
            'document_hash' => 'TEMPORARY_HASH',
        ]);

        // Render PDF A4 landscape
        $viewData = [
            'sertifikat' => $dummySertifikat,
            'kegiatan' => $kegiatan,
            'namaLengkap' => $namaLengkap,
            'nim' => $nim,
            'prodi' => $prodi,
            'qrCodeBase64' => $qrCodeBase64,
            'backgroundBase64' => $backgroundBase64,
            'tanggalTerbit' => $tanggalTerbit,
            'tanggalKegiatan' => $tanggalKegiatan,
        ];

        $pdf = Pdf::loadView('certificates.dynamic', $viewData)
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $pdfContent = $pdf->output();

        // Simpan file PDF ke storage public
        $folderPath = "certificates/{$kegiatan->id}";
        $fileName = "{$nomorSertifikat}.pdf";
        $storageFilePath = "{$folderPath}/{$fileName}";

        Storage::disk('public')->put($storageFilePath, $pdfContent);

        // Hitung SHA-256 hash dokumen resmi
        $absolutePath = Storage::disk('public')->path($storageFilePath);
        $documentHash = hash_file('sha256', $absolutePath);

        // Simpan ke database
        $sertifikatRecord = Sertifikat::updateOrCreate(
            [
                'kegiatan_id' => $kegiatan->id,
                'user_id' => $userId,
                'presensi_id' => $presensiId,
            ],
            [
                'nomor_sertifikat' => $nomorSertifikat,
                'peran_sertifikat' => $peran,
                'file_pdf_path' => $storageFilePath,
                'document_hash' => $documentHash,
                'is_valid' => true,
                'issued_at' => now(),
            ]
        );

        return $sertifikatRecord;
    }

    /**
     * Generate sertifikat secara massal untuk seluruh peserta yang telah presensi pada kegiatan tertentu.
     *
     * @param  Kegiatan  $kegiatan
     * @return int Jumlah sertifikat berhasil dibuat
     */
    public function generateForKegiatan(Kegiatan $kegiatan): int
    {
        $presensis = KegiatanPresensi::where('kegiatan_id', $kegiatan->id)->get();
        $count = 0;

        foreach ($presensis as $presensi) {
            $this->generate($kegiatan, $presensi, PeranSertifikat::Peserta, $presensi->id);
            $count++;
        }

        return $count;
    }

    /**
     * Ekstrak atribut penerima dari berbagai jenis input model/array.
     */
    protected function resolveRecipientData(KegiatanPresensi|KegiatanRegistration|User|array $recipient): array
    {
        if ($recipient instanceof KegiatanPresensi) {
            return [
                'user_id' => $recipient->user_id,
                'nama_lengkap' => $recipient->nama_lengkap,
                'nim' => $recipient->nim,
                'prodi' => $recipient->prodi,
                'email' => $recipient->email,
            ];
        }

        if ($recipient instanceof KegiatanRegistration) {
            $user = $recipient->participantUser ?? $recipient->bookerUser;
            return [
                'user_id' => $recipient->participant_user_id ?? $recipient->booker_user_id,
                'nama_lengkap' => $recipient->ticket_holder_name ?: ($user?->name ?? 'Peserta Kegiatan'),
                'nim' => $recipient->ticket_holder_identity ?: ($user?->nim ?? null),
                'prodi' => $user?->prodi ?? null,
                'email' => $recipient->ticket_holder_email ?: ($user?->email ?? null),
            ];
        }

        if ($recipient instanceof User) {
            return [
                'user_id' => $recipient->id,
                'nama_lengkap' => $recipient->name,
                'nim' => $recipient->nim,
                'prodi' => $recipient->prodi,
                'email' => $recipient->email,
            ];
        }

        return [
            'user_id' => $recipient['user_id'] ?? null,
            'nama_lengkap' => $recipient['nama_lengkap'] ?? $recipient['name'] ?? 'Peserta Terdaftar',
            'nim' => $recipient['nim'] ?? null,
            'prodi' => $recipient['prodi'] ?? null,
            'email' => $recipient['email'] ?? null,
        ];
    }
}
