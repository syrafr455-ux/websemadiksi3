<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PeriodeAkademik;
use App\Services\BatasKegiatanService;
use App\Support\Wib;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Periode akademik (semester) yang mereset batas jumlah kegiatan yang dihadiri. Hanya super_admin (rute role:super_admin).
 * Tanggal tidak diisi oleh sistem; pemilik proyek mengisinya dari kalender akademik.
 */
class PeriodeAkademikController extends Controller
{
    public function index(BatasKegiatanService $batas): View
    {
        return view('admin.periode-akademik.index', [
            'periodes' => PeriodeAkademik::orderByDesc('tanggal_mulai')->get(),
            'periodeBerjalan' => $batas->periodeBerjalan(),
            'batas' => $batas->batas(),
            'hariIni' => Wib::sekarang()->translatedFormat('d F Y'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);
        $periode = PeriodeAkademik::create($data);

        AuditLog::record($request->user()->id, 'periode_akademik_dibuat', "Menambah periode akademik '{$periode->nama}'.", $periode);

        return redirect()->route('admin.periode-akademik')->with('success', 'Periode akademik ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $periode = PeriodeAkademik::findOrFail($id);
        $periode->update($this->validasi($request, $periode->id));

        AuditLog::record($request->user()->id, 'periode_akademik_diubah', "Mengubah periode akademik '{$periode->nama}'.", $periode);

        return redirect()->route('admin.periode-akademik')->with('success', 'Periode akademik diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $periode = PeriodeAkademik::findOrFail($id);
        $nama = $periode->nama;
        $periode->delete();

        AuditLog::record($request->user()->id, 'periode_akademik_dihapus', "Menghapus periode akademik '{$nama}'.");

        return redirect()->route('admin.periode-akademik')->with('success', 'Periode akademik dihapus.');
    }

    /** @return array{nama: string, tanggal_mulai: string, tanggal_selesai: string} */
    private function validasi(Request $request, ?int $abaikanId = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai', function ($atribut, $nilai, $gagal) use ($request, $abaikanId) {
                $mulai = (string) $request->input('tanggal_mulai');
                // Tanggal tidak sah sudah dilaporkan aturan date_format; jangan dikirim ke query (PostgreSQL menolaknya dengan 500).
                foreach ([$mulai, (string) $nilai] as $tanggal) {
                    $sah = \DateTime::createFromFormat('Y-m-d', $tanggal);
                    if (! $sah || $sah->format('Y-m-d') !== $tanggal) {
                        return;
                    }
                }
                $bentrok = PeriodeAkademik::query()
                    ->when($abaikanId, fn ($q) => $q->where('id', '!=', $abaikanId))
                    ->where('tanggal_mulai', '<=', $nilai)
                    ->where('tanggal_selesai', '>=', $mulai)
                    ->first();
                if ($bentrok) {
                    $gagal("Periode tumpang tindih dengan {$bentrok->nama}.");
                }
            }],
        ], [
            'nama.required' => 'Nama periode wajib diisi.',
            'nama.max' => 'Nama periode maksimal 100 karakter.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_mulai.date_format' => 'Tanggal mulai harus berformat tahun-bulan-tanggal.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.date_format' => 'Tanggal selesai harus berformat tahun-bulan-tanggal.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);
        $data['nama'] = trim($data['nama']);

        return $data;
    }
}
