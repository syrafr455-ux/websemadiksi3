<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanSubmission;
use App\Services\SimpleXlsxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PenilaianKaryaController extends Controller
{
    /**
     * Tampilkan antarmuka penilaian karya untuk juri & super admin.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Juri hanya melihat lomba yang ditugaskan kepadanya; super_admin semua kegiatan berpengumpulan karya.
        $kegiatansWithSubmission = ($user->isSuperAdmin()
            ? Kegiatan::where('is_submission_active', true)
            : Kegiatan::whereHas('penilai', fn ($q) => $q->whereKey($user->id)))
            ->latest('id')
            ->get(['id', 'nama_kegiatan', 'kategori']);

        $selectedKegiatanId = $request->input('kegiatan_id', $kegiatansWithSubmission->first()?->id);

        if ($request->filled('kegiatan_id')) {
            Gate::authorize('nilaiKarya', Kegiatan::findOrFail($selectedKegiatanId));
        }

        $submissionsQuery = KegiatanSubmission::with(['registration.participantUser'])
            ->latest('id');

        if ($selectedKegiatanId) {
            $submissionsQuery->where('kegiatan_id', $selectedKegiatanId);
        } elseif (! $user->isSuperAdmin()) {
            // Juri tanpa penugasan tidak melihat karya apa pun.
            $submissionsQuery->whereRaw('1 = 0');
        }

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $submissionsQuery->where(function ($w) use ($q) {
                $w->where('nama_kelompok_peserta', 'ilike', "%{$q}%")
                    ->orWhereHas('registration', function ($r) use ($q) {
                        $r->where('participant_name', 'ilike', "%{$q}%")
                            ->orWhere('nomor_tiket', 'ilike', "%{$q}%");
                    });
            });
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'graded') {
                $submissionsQuery->whereNotNull('nilai');
            } elseif ($request->input('status') === 'submitted') {
                $submissionsQuery->whereNull('nilai');
            }
        }

        $submissions = $submissionsQuery->paginate(20)->withQueryString();

        return Inertia::render('Admin/Penilaian/Index', [
            'title' => 'Penilaian Karya & Penjurian',
            'kegiatans' => $kegiatansWithSubmission,
            'selectedKegiatanId' => (int) $selectedKegiatanId,
            'submissions' => $submissions,
            'filters' => $request->only(['q', 'status', 'kategori']),
        ]);
    }

    /**
     * Simpan penilaian skor & feedback dari juri untuk karya tertentu.
     */
    public function updateScore(Request $request, int $id): RedirectResponse
    {
        $submission = KegiatanSubmission::findOrFail($id);
        Gate::authorize('nilaiKarya', Kegiatan::withTrashed()->findOrFail($submission->kegiatan_id));

        $validated = $request->validate([
            'final_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'judge_notes' => ['nullable', 'string', 'max:2000'],
            'score_breakdown' => ['nullable', 'array'],
        ]);

        $submission->update([
            'final_score' => $validated['final_score'],
            'judge_notes' => $validated['judge_notes'] ?? null,
            'score_breakdown' => $validated['score_breakdown'] ?? null,
            'status' => 'graded',
            'graded_by' => auth()->user()->name,
            'graded_at' => now(),
        ]);

        AuditLog::record(
            auth()->id(),
            'grade_submission',
            "Memberikan nilai {$validated['final_score']} untuk karya '{$submission->title}' (ID: {$submission->id})",
            $submission
        );

        return back()->with('success', "Penilaian untuk karya '{$submission->title}' berhasil disimpan.");
    }

    /**
     * Ekspor daftar nilai karya (mendukung format .xlsx sebagai utama dan .csv sebagai cadangan).
     */
    public function export(Request $request, int $kegiatanId): SymfonyResponse
    {
        if ($request->query('format') === 'xlsx') {
            return $this->exportXlsx($kegiatanId);
        }

        return $this->exportCsv($kegiatanId);
    }

    /**
     * Ekspor daftar nilai karya ke format OpenXML Spreadsheet (.xlsx).
     */
    public function exportXlsx(int $kegiatanId): SymfonyResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);
        Gate::authorize('nilaiKarya', $kegiatan);

        $submissions = KegiatanSubmission::with('registration')
            ->where('kegiatan_id', $kegiatanId)
            ->orderBy('id', 'asc')
            ->get();

        $filename = 'nilai_karya_'.Str::slug($kegiatan->nama_kegiatan).'_'.date('Ymd_His').'.xlsx';

        $headers = ['ID_KARYA', 'JUDUL_KARYA', 'NAMA_PESERTA', 'NILAI', 'CATATAN'];
        $rows = [];

        foreach ($submissions as $sub) {
            $rows[] = [
                $sub->id,
                $sub->title ?? $sub->nama_kelompok_peserta ?? '',
                $sub->registration?->participant_name ?? '',
                $sub->final_score !== null ? (float) $sub->final_score : '',
                $sub->judge_notes ?? '',
            ];
        }

        $xlsxService = new SimpleXlsxService;
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_exp_').'.xlsx';
        $xlsxService->createXlsxFile($headers, $rows, $tempPath);

        return response()->streamDownload(function () use ($tempPath) {
            readfile($tempPath);
            @unlink($tempPath);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Ekspor daftar nilai karya ke file CSV (UTF-8 BOM RFC-4180 untuk Excel).
     */
    public function exportCsv(int $kegiatanId): StreamedResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);
        Gate::authorize('nilaiKarya', $kegiatan);

        $submissions = KegiatanSubmission::with('registration')
            ->where('kegiatan_id', $kegiatanId)
            ->orderBy('id', 'asc')
            ->get();

        $filename = 'nilai_karya_'.Str::slug($kegiatan->nama_kegiatan).'_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($submissions) {
            $handle = fopen('php://output', 'w');

            // Tulis BOM UTF-8 (\xEF\xBB\xBF)
            fwrite($handle, "\xEF\xBB\xBF");

            // Tulis Header CSV
            fputcsv($handle, [
                'ID_SUBMISSION',
                'NOMOR_TIKET',
                'NAMA_PESERTA',
                'NIM_IDENTITAS',
                'JUDUL_KARYA',
                'URL_BERKAS',
                'NILAI_AKHIR',
                'CATATAN_JURI',
                'STATUS_PENILAIAN',
                'DINILAI_OLEH',
            ]);

            foreach ($submissions as $sub) {
                fputcsv($handle, [
                    $sub->id,
                    $sub->registration?->nomor_tiket ?? '',
                    $sub->registration?->participant_name ?? '',
                    $sub->registration?->participant_identity ?? '',
                    SimpleXlsxService::neutralizeFormula($sub->title ?? ''),
                    $sub->file_url ?? '',
                    $sub->final_score ?? '',
                    SimpleXlsxService::neutralizeFormula($sub->judge_notes ?? ''),
                    $sub->status ?? 'submitted',
                    $sub->graded_by ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Impor file Excel (.xlsx) atau CSV nilai peserta secara massal.
     */
    public function import(Request $request, int $kegiatanId): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);
        Gate::authorize('nilaiKarya', $kegiatan);

        // Cari input file dari parameter yang mungkin dikirimkan
        $fileKey = $request->hasFile('file') ? 'file' : ($request->hasFile('excel_file') ? 'excel_file' : 'csv_file');

        $request->validate([
            $fileKey => ['required', 'file', 'max:2048'],
        ]);

        $uploadedFile = $request->file($fileKey);
        $originalExt = strtolower($uploadedFile->getClientOriginalExtension());
        $mimeType = $uploadedFile->getMimeType();

        $isXlsx = ($originalExt === 'xlsx' || in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/zip',
            'application/octet-stream',
        ], true));

        $allRows = [];

        if ($isXlsx && $originalExt !== 'csv' && $originalExt !== 'txt') {
            try {
                $xlsxService = new SimpleXlsxService;
                $allRows = $xlsxService->readXlsxFile($uploadedFile->getRealPath());
            } catch (\Throwable $e) {
                return back()->with('error', 'Gagal membaca berkas Excel: '.$e->getMessage());
            }
        } else {
            // Baca format CSV
            $handle = fopen($uploadedFile->getRealPath(), 'r');
            if (! $handle) {
                return back()->with('error', 'Gagal membaca berkas data penilaian.');
            }

            while (($data = fgetcsv($handle)) !== false) {
                $allRows[] = $data;
            }
            fclose($handle);
        }

        if (empty($allRows)) {
            return back()->with('error', 'Berkas penilaian kosong atau format tidak valid.');
        }

        // Baca dan periksa header (baris pertama)
        $headerRow = $allRows[0];
        $idIndex = false;
        $scoreIndex = false;
        $notesIndex = false;

        foreach ($headerRow as $i => $colName) {
            $cleanName = strtoupper(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', (string) $colName)));
            if (in_array($cleanName, ['ID_KARYA', 'ID_SUBMISSION', 'ID'], true)) {
                $idIndex = $i;
            } elseif (in_array($cleanName, ['NILAI', 'NILAI_AKHIR', 'FINAL_SCORE', 'SKOR', 'SCORE'], true)) {
                $scoreIndex = $i;
            } elseif (in_array($cleanName, ['CATATAN', 'CATATAN_JURI', 'NOTES', 'FEEDBACK'], true)) {
                $notesIndex = $i;
            }
        }

        if ($idIndex === false || $scoreIndex === false) {
            return back()->with('error', 'Header berkas harus memiliki kolom ID_KARYA dan NILAI.');
        }

        $dataRows = array_slice($allRows, 1);

        // Validasi: maksimal 2000 baris data
        if (count($dataRows) > 2000) {
            return back()->with('error', 'Jumlah baris data melebihi batas maksimal 2000 baris.');
        }

        $updatedCount = 0;
        $judgeName = auth()->user()->name;
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($dataRows as $row) {
                if (empty($row) || ! isset($row[$idIndex])) {
                    continue;
                }

                $rawId = trim((string) $row[$idIndex]);
                if ($rawId === '' || ! is_numeric($rawId)) {
                    continue;
                }

                $subId = (int) $rawId;
                $rawScore = isset($row[$scoreIndex]) ? trim((string) $row[$scoreIndex]) : '';

                if ($rawScore === '') {
                    continue;
                }

                $cleanScoreStr = str_replace(',', '.', $rawScore);

                // Tolak formula injection pada nilai
                if (SimpleXlsxService::startsWithFormula($rawScore) && ! is_numeric($cleanScoreStr)) {
                    DB::rollBack();

                    return back()->with('error', "Nilai untuk ID karya {$subId} tidak valid (terdeteksi formula atau format tidak didukung).");
                }

                if (! is_numeric($cleanScoreStr)) {
                    DB::rollBack();

                    return back()->with('error', "Nilai untuk ID karya {$subId} harus berupa angka numerik.");
                }

                $score = (float) $cleanScoreStr;
                if ($score < 0 || $score > 100) {
                    DB::rollBack();

                    return back()->with('error', "Nilai untuk ID karya {$subId} di luar rentang yang berlaku (harus 0 - 100).");
                }

                // Tolak ID yang bukan milik kegiatan ini
                $submission = KegiatanSubmission::where('id', $subId)
                    ->where('kegiatan_id', $kegiatan->id)
                    ->first();

                if (! $submission) {
                    DB::rollBack();

                    return back()->with('error', "ID karya {$subId} bukan bagian dari kegiatan ini atau tidak ditemukan.");
                }

                // Bersihkan catatan juri dari formula injection
                $rawNotes = ($notesIndex !== false && isset($row[$notesIndex])) ? trim((string) $row[$notesIndex]) : null;
                $cleanNotes = $rawNotes !== null ? SimpleXlsxService::sanitizeFormulaOnImport($rawNotes) : null;

                // Hanya nilai dan catatan yang diubah saat impor
                $submission->update([
                    'final_score' => $score,
                    'judge_notes' => $cleanNotes ?: $submission->judge_notes,
                    'status' => 'graded',
                    'graded_by' => $judgeName,
                    'graded_at' => $now,
                ]);

                $updatedCount++;
            }

            AuditLog::record(
                auth()->id(),
                'import_grades',
                "Impor massal {$updatedCount} nilai karya kegiatan '{$kegiatan->nama_kegiatan}'",
                $kegiatan
            );

            DB::commit();

            return back()->with('success', "Berhasil memperbarui {$updatedCount} nilai karya dari berkas.");
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Terjadi kesalahan saat mengimpor nilai: '.$e->getMessage());
        }
    }

    /**
     * Impor file CSV nilai yang diunggah juri (backward compatibility).
     */
    public function importCsv(Request $request, int $kegiatanId): RedirectResponse
    {
        return $this->import($request, $kegiatanId);
    }

    /**
     * Impor file XLSX nilai yang diunggah juri.
     */
    public function importXlsx(Request $request, int $kegiatanId): RedirectResponse
    {
        return $this->import($request, $kegiatanId);
    }
}
