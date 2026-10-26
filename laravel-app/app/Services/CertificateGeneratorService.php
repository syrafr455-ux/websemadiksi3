<?php

namespace App\Services;

use App\Enums\PeranSertifikat;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Sertifikat;
use App\Models\User;
use App\Support\Wib;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateGeneratorService
{
    /**
     * Stamping template PDF resmi menggunakan FPDI dan vector QR matrix.
     *
     * @return string Konten PDF hasil stamping
     */
    public function stampPdfTemplateContent(
        string $templatePdfPathOrContent,
        string $nomorSertifikat,
        string $namaPenerima,
        ?string $kegiatanTitle = null,
        ?array $schemaConfig = null
    ): string {
        $isRawContent = ! @is_file($templatePdfPathOrContent);
        if ($isRawContent) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'tpl_cert_').'.pdf';
            file_put_contents($tmpFile, $templatePdfPathOrContent);
            $sourceFile = $tmpFile;
        } else {
            $sourceFile = $templatePdfPathOrContent;
        }

        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($sourceFile);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            // Stamping utama pada halaman pertama
            if ($pageNo === 1) {
                $w = $size['width'];
                $h = $size['height'];

                // 1. Nomor Seri Sertifikat: Dihapus dari tampilan kasat mata dokumen
                // Nomor seri tetap tersimpan di database dan tertanam di dalam URL QR Code validasi.

                // 2. Nama Lengkap Penerima di area tengah dokumen
                $nameY = $schemaConfig['name_pos_y'] ?? ($h * 0.45);
                $fontSize = mb_strlen($namaPenerima) > 30 ? 18 : 22;
                $pdf->SetFont('Arial', 'B', $fontSize);
                $pdf->SetTextColor(27, 109, 36); // #1B6D24 (SEMADIKSI Dark Green)
                $pdf->SetXY(0, $nameY);
                $pdf->Cell($w, 14, strtoupper($namaPenerima), 0, 1, 'C');

                // 3. QR Code Validasi di POJOK KANAN BAWAH dokumen
                $verificationUrl = url("/verifikasi?code={$nomorSertifikat}");
                $qrTargetSize = $schemaConfig['qr_size'] ?? 26; // 26 mm
                $qrMarginRight = $schemaConfig['qr_margin_right'] ?? 15; // 15 mm dari tepi kanan
                $qrMarginBottom = $schemaConfig['qr_margin_bottom'] ?? 15; // 15 mm dari tepi bawah
                $qrX = $schemaConfig['qr_pos_x'] ?? ($w - $qrTargetSize - $qrMarginRight);
                $qrY = $schemaConfig['qr_pos_y'] ?? ($h - $qrTargetSize - $qrMarginBottom);

                // Render Vector QR Code Matrix (bebas ketergantungan imagick)
                $qrCode = Encoder::encode($verificationUrl, ErrorCorrectionLevel::H());
                $matrix = $qrCode->getMatrix();
                $matrixWidth = $matrix->getWidth();
                $matrixHeight = $matrix->getHeight();
                $moduleSize = $qrTargetSize / $matrixWidth;

                // Bingkai putih di belakang QR Code
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Rect($qrX - 1.5, $qrY - 1.5, $qrTargetSize + 3, $qrTargetSize + 3, 'F');

                // Gambar modul-modul hitam
                $pdf->SetFillColor(26, 28, 28);
                for ($my = 0; $my < $matrixHeight; $my++) {
                    for ($mx = 0; $mx < $matrixWidth; $mx++) {
                        if ($matrix->get($mx, $my) === 1) {
                            $pdf->Rect(
                                $qrX + ($mx * $moduleSize),
                                $qrY + ($my * $moduleSize),
                                $moduleSize,
                                $moduleSize,
                                'F'
                            );
                        }
                    }
                }

                // Teks pendamping kecil di bawah QR Code: PINDAI UNTUK VALIDASI
                $pdf->SetFont('Arial', 'B', 6);
                $pdf->SetTextColor(100, 116, 139);
                $pdf->SetXY($qrX - 5, $qrY + $qrTargetSize + 1);
                $pdf->Cell($qrTargetSize + 10, 4, 'PINDAI UNTUK VALIDASI', 0, 0, 'C');
            }
        }

        $result = $pdf->Output('S');

        if ($isRawContent && isset($tmpFile) && file_exists($tmpFile)) {
            @unlink($tmpFile);
        }

        return $result;
    }

    /**
     * Penerbitan sertifikat langsung dari panel Admin (/admin/sertifikat).
     */
    public function publishFromAdmin(
        Kegiatan $kegiatan,
        string $namaPenerima,
        ?string $emailPenerima = null,
        ?User $user = null,
        ?string $customNomor = null,
        mixed $uploadedPdf = null,
        PeranSertifikat $peran = PeranSertifikat::Peserta
    ): Sertifikat {
        // 1. Tentukan nomor sertifikat format CERT-
        $nomorSertifikat = $customNomor ? trim($customNomor) : Sertifikat::generateCertificateNumber($kegiatan);
        if (! str_starts_with($nomorSertifikat, 'CERT-')) {
            $nomorSertifikat = 'CERT-'.ltrim($nomorSertifikat, '-');
        }

        // 2. Tentukan berkas template PDF
        $tempPath = null;
        if ($uploadedPdf instanceof UploadedFile) {
            $tempPath = $uploadedPdf->getRealPath();
            // Simpan juga sebagai template resmi kegiatan jika belum ada
            if (! $kegiatan->certificate_template_path) {
                $savedTpl = $uploadedPdf->store("kegiatan/templates/{$kegiatan->id}", 'public');
                $kegiatan->update(['certificate_template_path' => $savedTpl]);
            }
        } elseif ($kegiatan->certificate_template_path && Storage::disk('public')->exists($kegiatan->certificate_template_path)) {
            $tempPath = Storage::disk('public')->path($kegiatan->certificate_template_path);
        }

        // 3. Stamping atau Fallback DomPDF
        if ($tempPath && strtolower(pathinfo($tempPath, PATHINFO_EXTENSION)) === 'pdf') {
            $pdfContent = $this->stampPdfTemplateContent(
                $tempPath,
                $nomorSertifikat,
                $namaPenerima,
                $kegiatan->nama_kegiatan,
                $kegiatan->certificate_schema_config
            );
        } else {
            // Fallback jika tidak ada template PDF
            $viewName = view()->exists('certificates.template') ? 'certificates.template' : 'certificates.dynamic';
            $pdf = Pdf::loadView($viewName, [
                'sertifikat' => new Sertifikat(['nomor_sertifikat' => $nomorSertifikat, 'peran_sertifikat' => $peran]),
                'kegiatan' => $kegiatan,
                'namaLengkap' => $namaPenerima,
                'nim' => $user?->nim ?? '-',
                'prodi' => $user?->prodi ?? '-',
                'qrCodeBase64' => 'data:image/svg+xml;base64,'.base64_encode(
                    QrCode::format('svg')->size(130)->margin(1)->generate(url("/verifikasi?code={$nomorSertifikat}"))
                ),
                'backgroundBase64' => null,
                'tanggalTerbit' => Wib::sekarang()->translatedFormat('d F Y'),
                'tanggalKegiatan' => $kegiatan->tanggal_mulai ? Wib::dari($kegiatan->tanggal_mulai)->translatedFormat('d F Y') : Wib::sekarang()->translatedFormat('d F Y'),
            ])->setPaper('a4', 'landscape')
                ->setOption('isRemoteEnabled', true)
                ->setOption('isHtml5ParserEnabled', true);
            $pdfContent = $pdf->output();
        }

        // 4. Simpan file PDF ke storage public
        $folderPath = "certificates/{$kegiatan->id}";
        $fileName = "{$nomorSertifikat}.pdf";
        $storageFilePath = "{$folderPath}/{$fileName}";
        Storage::disk('public')->put($storageFilePath, $pdfContent);

        // 5. Hitung hash SHA-256 dokumen akhir
        $absolutePath = Storage::disk('public')->path($storageFilePath);
        $documentHash = hash_file('sha256', $absolutePath);

        // 6. Simpan record sertifikat ke tabel sertifikat
        return Sertifikat::create([
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user?->id,
            'nama_penerima' => $namaPenerima,
            'email_penerima' => $emailPenerima ?: ($user?->email),
            'nomor_sertifikat' => $nomorSertifikat,
            'peran_sertifikat' => $peran,
            'file_pdf_path' => $storageFilePath,
            'document_hash' => $documentHash,
            'is_valid' => true,
            'issued_at' => now(),
        ]);
    }

    /**
     * Generate sertifikat dinamis untuk satu penerima (alur presensi mandiri / registrasi).
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

        // Periksa apakah template berformat PDF tersedia
        $templatePath = null;
        if ($kegiatan->certificate_template_path && Storage::disk('public')->exists($kegiatan->certificate_template_path)) {
            $templatePath = Storage::disk('public')->path($kegiatan->certificate_template_path);
        }

        if ($templatePath && strtolower(pathinfo($templatePath, PATHINFO_EXTENSION)) === 'pdf') {
            // Gunakan stamping PDF dinamis dengan FPDI
            $pdfContent = $this->stampPdfTemplateContent(
                $templatePath,
                $nomorSertifikat,
                $namaLengkap,
                $kegiatan->nama_kegiatan,
                $kegiatan->certificate_schema_config
            );
        } else {
            // Siapkan URL verifikasi publik
            $verificationUrl = url("/verifikasi?code={$nomorSertifikat}");

            // Generate QR code SVG sebagai base64 Data URI
            $qrSvg = QrCode::format('svg')
                ->size(130)
                ->errorCorrection('H')
                ->margin(1)
                ->generate($verificationUrl);

            $qrCodeBase64 = 'data:image/svg+xml;base64,'.base64_encode($qrSvg);

            // Background template gambar kustom jika ada
            $backgroundBase64 = null;
            if ($templatePath) {
                $mime = mime_content_type($templatePath) ?: 'image/png';
                $bgData = file_get_contents($templatePath);
                if ($bgData !== false) {
                    $backgroundBase64 = 'data:'.$mime.';base64,'.base64_encode($bgData);
                }
            }

            // Tanggal terbit dan tanggal kegiatan, dalam WIB
            Carbon::setLocale('id');
            $tanggalTerbit = Wib::sekarang()->translatedFormat('d F Y');
            $tanggalKegiatan = $kegiatan->tanggal_mulai
                ? Wib::dari($kegiatan->tanggal_mulai)->translatedFormat('d F Y')
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

            $viewName = view()->exists('certificates.template') ? 'certificates.template' : 'certificates.dynamic';
            $pdf = Pdf::loadView($viewName, $viewData)
                ->setPaper('a4', 'landscape')
                ->setOption('isRemoteEnabled', true)
                ->setOption('isHtml5ParserEnabled', true);

            $pdfContent = $pdf->output();
        }

        // Simpan file PDF ke storage public
        $folderPath = "certificates/{$kegiatan->id}";
        $fileName = "{$nomorSertifikat}.pdf";
        $storageFilePath = "{$folderPath}/{$fileName}";

        Storage::disk('public')->put($storageFilePath, $pdfContent);

        // Hitung SHA-256 hash dokumen resmi
        $absolutePath = Storage::disk('public')->path($storageFilePath);
        $documentHash = hash_file('sha256', $absolutePath);

        // Simpan ke database
        $matchCriteria = ['kegiatan_id' => $kegiatan->id];
        if ($userId) {
            $matchCriteria['user_id'] = $userId;
        }
        if ($presensiId) {
            $matchCriteria['presensi_id'] = $presensiId;
        }

        $sertifikatRecord = Sertifikat::updateOrCreate(
            $matchCriteria,
            [
                'user_id' => $userId,
                'nama_penerima' => $namaLengkap,
                'email_penerima' => $recipientData['email'] ?? null,
                'presensi_id' => $presensiId,
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
