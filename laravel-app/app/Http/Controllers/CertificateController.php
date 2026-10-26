<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Kegiatan;
use App\Models\Sertifikat;
use App\Services\CertificateGeneratorService;
use App\Support\Wib;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    /**
     * Portal Verifikasi Sertifikat Publik (`/verifikasi?code=...` & `/verifikasi-sertifikat`)
     * Mendukung verifikasi kode dan verifikasi integritas hash dokumen SHA-256.
     */
    public function verify(Request $request): View
    {
        $code = trim($request->input('code', ''));
        $sertifikat = null;
        $legacyCertificate = null;
        $mockCertificate = null;
        $hashVerificationResult = null; // null, 'match', 'mismatch'
        $uploadedHash = null;

        // Default valid certificates list untuk kompatibilitas kode lama (Next.js)
        $defaultLegacyCerts = [
            'CERT-LKMB-2024-0891' => [
                'code' => 'CERT-LKMB-2024-0891',
                'title' => 'Sertifikat Latihan Kepemimpinan Mahasiswa Berprestasi (LKMB)',
                'recipient_name' => 'Ahmad Fauzan',
                'author' => 'SEMADIKSI Divisi Keorganisasian',
                'hash' => 'e566c805ba07b8c6869e1f37847d0287a9b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5',
                'date' => '2024-11-16 09:12:05',
                'download_url' => null,
                'signatories' => [
                    ['name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => '197508122002121001', 'type' => 'NIP', 'date' => '2024-11-16 09:12:05'],
                    ['name' => 'Muhammad Fatih (Ketua Umum SEMADIKSI)', 'nipNim' => '3130021045', 'type' => 'NIM', 'date' => '2024-11-16 09:12:05'],
                ],
            ],
            'CERT-VOL-2024-1102' => [
                'code' => 'CERT-VOL-2024-1102',
                'title' => 'Sertifikat Volunteer Mengajar Pesisir - SEMADIKSI Berbagi',
                'recipient_name' => 'Siti Nurhaliza',
                'author' => 'SEMADIKSI Divisi Pengabdian Masyarakat',
                'hash' => '8c7a6e112d88f6c99c5d01243170e881a9b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5',
                'date' => '2024-10-13 14:35:10',
                'download_url' => null,
                'signatories' => [
                    ['name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => '197508122002121001', 'type' => 'NIP', 'date' => '2024-10-13 14:35:10'],
                    ['name' => 'Fauzi Rahmat (Ketua Divisi Pengabdian)', 'nipNim' => '2140021088', 'type' => 'NIM', 'date' => '2024-10-13 14:35:10'],
                ],
            ],
            'CERT-WD-2024-0345' => [
                'code' => 'CERT-WD-2024-0345',
                'title' => 'Sertifikat Workshop Web Development Modern dengan Next.js',
                'recipient_name' => 'Muhammad Ilham',
                'author' => 'SEMADIKSI Divisi IPTEK & Humas',
                'hash' => 'f566c805ba07b8c6869e1f37847d0345a9b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5',
                'date' => '2024-09-05 16:40:02',
                'download_url' => null,
                'signatories' => [
                    ['name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => '197508122002121001', 'type' => 'NIP', 'date' => '2024-09-05 16:40:02'],
                    ['name' => 'Ferdian W. (Senior Frontend Engineer / Pemateri)', 'nipNim' => '198805202015041002', 'type' => 'NIP', 'date' => '2024-09-05 16:40:02'],
                ],
            ],
        ];

        // Jika user mengunggah berkas sertifikat untuk verifikasi integritas hash
        if ($request->hasFile('document_file')) {
            $uploadedFile = $request->file('document_file');
            if ($uploadedFile->isValid()) {
                $uploadedHash = hash_file('sha256', $uploadedFile->getRealPath());

                // Cari sertifikat berdasarkan hash dokumen
                $sertifikat = Sertifikat::with(['kegiatan', 'user', 'presensi'])
                    ->where('document_hash', $uploadedHash)
                    ->first();

                if ($sertifikat) {
                    $hashVerificationResult = 'match';
                    $code = $sertifikat->nomor_sertifikat;
                } else {
                    // Cek jika cocok dengan hash sertifikat mock
                    foreach ($defaultLegacyCerts as $mockKey => $mockItem) {
                        if ($mockItem['hash'] === $uploadedHash) {
                            $mockCertificate = $mockItem;
                            $hashVerificationResult = 'match';
                            $code = $mockKey;
                            break;
                        }
                    }
                    if (! $mockCertificate) {
                        $hashVerificationResult = 'mismatch';
                    }
                }
            }
        }

        // Jika kode diinputkan (dan belum dicocokkan via file hash)
        if ($code && ! $sertifikat && ! $mockCertificate) {
            $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $sertifikat = Sertifikat::with(['kegiatan', 'user', 'presensi'])
                ->where('nomor_sertifikat', $code)
                ->orWhere('nomor_sertifikat', $likeOp, $code)
                ->first();

            if (! $sertifikat) {
                // Fallback ke tabel sertifikat legacy
                $legacyCertificate = Certificate::where('code', $code)
                    ->orWhere('code', $likeOp, $code)
                    ->first();
            }

            if (! $sertifikat && ! $legacyCertificate) {
                // Fallback ke default valid certs (kompatibilitas kode lama)
                $upperCode = strtoupper($code);
                if (isset($defaultLegacyCerts[$upperCode])) {
                    $mockCertificate = $defaultLegacyCerts[$upperCode];
                }
            }
        }

        // Format terpadu untuk tampilan view
        $documentData = null;
        if ($sertifikat) {
            $formattedDate = $sertifikat->issued_at
                ? Wib::dari($sertifikat->issued_at)->translatedFormat('d F Y, H:i').' WIB'
                : '-';

            $documentData = [
                'code' => $sertifikat->nomor_sertifikat,
                'status' => $sertifikat->is_valid ? 'VALID' : 'TIDAK AKTIF',
                'title' => $sertifikat->kegiatan->nama_kegiatan ?? 'Sertifikat Kegiatan SEMADIKSI',
                'recipient_name' => $sertifikat->recipient_name,
                'author' => 'Divisi Penyelenggara / SEMADIKSI',
                'hash' => $sertifikat->document_hash,
                'date' => $formattedDate,
                'download_url' => route('sertifikat.download', $sertifikat->id),
                'signatories' => [
                    ['name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => '197508122002121001', 'type' => 'NIP', 'date' => $formattedDate],
                    ['name' => 'Muhammad Fatih (Ketua Umum SEMADIKSI)', 'nipNim' => '3130021045', 'type' => 'NIM', 'date' => $formattedDate],
                ],
            ];
        } elseif ($legacyCertificate) {
            $formattedDate = $legacyCertificate->issued_date
                ? Wib::dari($legacyCertificate->issued_date)->translatedFormat('d F Y, H:i').' WIB'
                : '-';

            $documentData = [
                'code' => $legacyCertificate->code,
                'status' => 'VALID',
                'title' => $legacyCertificate->activity_title,
                'recipient_name' => $legacyCertificate->user_name,
                'author' => 'SEMADIKSI Divisi Kemahasiswaan',
                'hash' => hash('sha256', $legacyCertificate->code.$legacyCertificate->user_name),
                'date' => $formattedDate,
                'download_url' => null,
                'signatories' => [
                    ['name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => '197508122002121001', 'type' => 'NIP', 'date' => $formattedDate],
                    ['name' => 'Muhammad Fatih (Ketua Umum SEMADIKSI)', 'nipNim' => '3130021045', 'type' => 'NIM', 'date' => $formattedDate],
                ],
            ];
        } elseif ($mockCertificate) {
            $documentData = [
                'code' => $mockCertificate['code'],
                'status' => 'VALID',
                'title' => $mockCertificate['title'],
                'recipient_name' => $mockCertificate['recipient_name'],
                'author' => $mockCertificate['author'],
                'hash' => $mockCertificate['hash'],
                'date' => $mockCertificate['date'],
                'download_url' => null,
                'signatories' => $mockCertificate['signatories'],
            ];
        }

        return view('verifikasi-sertifikat', [
            'code' => $code,
            'sertifikat' => $sertifikat,
            'certificate' => $legacyCertificate,
            'documentData' => $documentData,
            'hashVerificationResult' => $hashVerificationResult,
            'uploadedHash' => $uploadedHash,
            'title' => 'Portal Validasi Sertifikat SEMADIKSI',
        ]);
    }

    /**
     * Download sertifikat digital resmi.
     */
    public function download(int $id): BinaryFileResponse|RedirectResponse
    {
        $sertifikat = Sertifikat::with('kegiatan')->findOrFail($id);

        if (! $sertifikat->file_pdf_path || ! Storage::disk('public')->exists($sertifikat->file_pdf_path)) {
            return back()->with('error', 'Berkas PDF sertifikat tidak ditemukan di penyimpanan server.');
        }

        $fullPath = Storage::disk('public')->path($sertifikat->file_pdf_path);
        $downloadName = "Sertifikat-{$sertifikat->nomor_sertifikat}.pdf";

        return response()->download($fullPath, $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Super Admin: Upload template background resmi kegiatan (PNG/JPG/PDF).
     */
    public function uploadTemplate(Request $request, int $kegiatanId): JsonResponse|RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);

        $request->validate([
            'template_file' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:10240'], // Max 10MB
        ]);

        $path = $request->file('template_file')->store("kegiatan/templates/{$kegiatan->id}", 'public');

        $kegiatan->update([
            'certificate_template_path' => $path,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Template sertifikat resmi berhasil diunggah.',
                'template_url' => Storage::disk('public')->url($path),
            ]);
        }

        return back()->with('success', 'Template background sertifikat resmi berhasil diunggah.');
    }

    /**
     * Super Admin: Konfigurasi koordinat X/Y schema sertifikat.
     */
    public function updateSchemaConfig(Request $request, int $kegiatanId): JsonResponse|RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);

        $validated = $request->validate([
            'schema_config' => ['required', 'array'],
        ]);

        $kegiatan->update([
            'certificate_schema_config' => $validated['schema_config'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi tata letak sertifikat berhasil disimpan.',
            ]);
        }

        return back()->with('success', 'Konfigurasi tata letak sertifikat berhasil disimpan.');
    }

    /**
     * Super Admin: Trigger generate batch sertifikat untuk seluruh peserta yang telah hadir di kegiatan.
     */
    public function generateBatch(
        Request $request,
        int $kegiatanId,
        CertificateGeneratorService $generatorService
    ): JsonResponse|RedirectResponse {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);

        $count = $generatorService->generateForKegiatan($kegiatan);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menerbitkan {$count} sertifikat peserta.",
                'count' => $count,
            ]);
        }

        return back()->with('success', "Berhasil menerbitkan {$count} sertifikat resmi untuk kegiatan ini.");
    }
}
