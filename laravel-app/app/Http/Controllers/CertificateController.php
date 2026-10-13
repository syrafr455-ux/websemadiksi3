<?php

namespace App\Http\Controllers;

use App\Enums\PeranSertifikat;
use App\Models\Certificate;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\Sertifikat;
use App\Services\CertificateGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $hashVerificationResult = null; // null, 'match', 'mismatch'
        $uploadedHash = null;

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
                    $hashVerificationResult = 'mismatch';
                }
            }
        }

        // Jika kode diinputkan (dan belum dicocokkan via file hash)
        if ($code && ! $sertifikat) {
            $likeOp = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
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
        }

        return view('verifikasi-sertifikat', [
            'code' => $code,
            'sertifikat' => $sertifikat,
            'certificate' => $legacyCertificate,
            'hashVerificationResult' => $hashVerificationResult,
            'uploadedHash' => $uploadedHash,
            'title' => 'Pusat Verifikasi Keaslian Sertifikat Digital',
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
