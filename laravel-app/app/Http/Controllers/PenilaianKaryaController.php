<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PenilaianKaryaController extends Controller
{
    /**
     * Tampilkan antarmuka penilaian karya untuk juri & super admin.
     */
    public function index(Request $request): Response
    {
        $kegiatansWithSubmission = Kegiatan::where('is_submission_active', true)
            ->latest('id')
            ->get(['id', 'nama_kegiatan', 'kategori']);

        $selectedKegiatanId = $request->input('kegiatan_id', $kegiatansWithSubmission->first()?->id);

        $submissionsQuery = KegiatanSubmission::with(['registration.participantUser'])
            ->latest('id');

        if ($selectedKegiatanId) {
            $submissionsQuery->where('kegiatan_id', $selectedKegiatanId);
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
     * Ekspor daftar nilai karya ke file CSV (UTF-8 BOM RFC-4180 untuk Excel).
     */
    public function exportCsv(int $kegiatanId): StreamedResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);
        $submissions = KegiatanSubmission::with('registration')
            ->where('kegiatan_id', $kegiatanId)
            ->orderBy('id', 'asc')
            ->get();

        $filename = 'nilai_karya_'.\Illuminate\Support\Str::slug($kegiatan->nama_kegiatan).'_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($submissions) {
            $handle = fopen('php://output', 'w');

            // Tulis BOM UTF-8 (\xEF\xBB\xBF) agar Microsoft Excel membaca karakter Indonesia & aksen tanpa corrupt
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
                    $sub->title ?? '',
                    $sub->file_url ?? '',
                    $sub->final_score ?? '',
                    $sub->judge_notes ?? '',
                    $sub->status ?? 'submitted',
                    $sub->graded_by ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Impor file CSV nilai yang diunggah juri untuk mass-update nilai peserta.
     */
    public function importCsv(Request $request, int $kegiatanId): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return back()->with('error', 'Gagal membaca berkas CSV.');
        }

        // Baca header
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            return back()->with('error', 'Format berkas CSV tidak valid atau kosong.');
        }

        $idIndex = false;
        $scoreIndex = false;
        $notesIndex = false;

        foreach ($header as $i => $colName) {
            $cleanName = strtoupper(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $colName)));
            if ($cleanName === 'ID_SUBMISSION' || $cleanName === 'ID') {
                $idIndex = $i;
            } elseif ($cleanName === 'NILAI_AKHIR' || $cleanName === 'NILAI' || $cleanName === 'FINAL_SCORE') {
                $scoreIndex = $i;
            } elseif ($cleanName === 'CATATAN_JURI' || $cleanName === 'CATATAN' || $cleanName === 'NOTES') {
                $notesIndex = $i;
            }
        }

        if ($idIndex === false || $scoreIndex === false) {
            fclose($handle);
            return back()->with('error', 'Header CSV harus memiliki kolom ID_SUBMISSION dan NILAI_AKHIR.');
        }

        $updatedCount = 0;
        $judgeName = auth()->user()->name;
        $now = now();

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row) || ! isset($row[$idIndex])) {
                    continue;
                }

                $subId = (int) trim($row[$idIndex]);
                $scoreRaw = isset($row[$scoreIndex]) ? trim($row[$scoreIndex]) : '';

                if ($subId <= 0 || $scoreRaw === '') {
                    continue;
                }

                $score = (float) str_replace(',', '.', $scoreRaw);
                $notes = ($notesIndex !== false && isset($row[$notesIndex])) ? trim($row[$notesIndex]) : null;

                $submission = KegiatanSubmission::where('id', $subId)
                    ->where('kegiatan_id', $kegiatan->id)
                    ->first();

                if ($submission) {
                    $submission->update([
                        'final_score' => $score,
                        'judge_notes' => $notes ?: $submission->judge_notes,
                        'status' => 'graded',
                        'graded_by' => $judgeName,
                        'graded_at' => $now,
                    ]);
                    $updatedCount++;
                }
            }

            fclose($handle);

            AuditLog::record(
                auth()->id(),
                'import_grades',
                "Impor massal {$updatedCount} nilai karya kegiatan '{$kegiatan->nama_kegiatan}' via CSV",
                $kegiatan
            );

            DB::commit();

            return back()->with('success', "Berhasil memperbarui {$updatedCount} nilai karya dari berkas CSV.");
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            return back()->with('error', 'Terjadi kesalahan saat mengimpor CSV: '.$e->getMessage());
        }
    }
}
