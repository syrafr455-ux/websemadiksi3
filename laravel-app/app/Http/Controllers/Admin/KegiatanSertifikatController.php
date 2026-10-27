<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisSertifikat;
use App\Enums\StatusSertifikat;
use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\KegiatanPenandatangan;
use App\Models\Penandatangan;
use App\Models\Sertifikat;
use App\Services\Kegiatan\KelayakanSertifikatService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman sertifikat per kegiatan (/admin/kegiatan/{id}/sertifikat): templat, penandatangan, dan tautan ke
 * halaman Terbitkan serta Arsip yang sudah ada (difilter ke kegiatan ini). Hanya super_admin (SertifikatPolicy).
 */
class KegiatanSertifikatController extends Controller
{
    public function show(int $id, KelayakanSertifikatService $kelayakan): Response
    {
        Gate::authorize('viewAny', Sertifikat::class);

        $kegiatan = Kegiatan::findOrFail($id);

        $peserta = $kelayakan->evaluasiSemuaPeserta($kegiatan);

        $ditugaskan = KegiatanPenandatangan::with('penandatangan:id,nama,gelar,jabatan')
            ->where('kegiatan_id', $kegiatan->id)
            ->orderBy('urutan')
            ->get()
            ->map(fn (KegiatanPenandatangan $baris) => [
                'id' => $baris->id,
                'penandatangan_id' => $baris->penandatangan_id,
                'nama' => $baris->penandatangan?->nama,
                'jabatan' => $baris->penandatangan?->jabatan,
                'jenis_sertifikat' => $baris->jenis_sertifikat ?? 'semua',
                'urutan' => $baris->urutan,
            ])->values();

        return Inertia::render('Admin/Kegiatan/Sertifikat', [
            'title' => 'Sertifikat Kegiatan',
            'kegiatan' => [
                'id' => $kegiatan->id,
                'nama_kegiatan' => $kegiatan->nama_kegiatan,
                'status' => $kegiatan->status?->value,
                'status_teks' => $kegiatan->status?->label(),
            ],
            'ringkasan' => [
                'layak' => $peserta->filter(fn ($p) => $p['layak'] && ! $p['sudah_terbit'])->count(),
                'terbit' => Sertifikat::where('kegiatan_id', $kegiatan->id)->where('status', StatusSertifikat::Aktif)->count(),
            ],
            'tautan' => [
                'kembali' => route('admin.kegiatan'),
                'terbitkan' => route('admin.certificates', ['tab' => 'terbitkan', 'kegiatan_id' => $kegiatan->id]),
                'arsip' => route('admin.certificates', ['tab' => 'arsip', 'kegiatan_id' => $kegiatan->id]),
            ],
            'penandatangan' => [
                'ditugaskan' => $ditugaskan,
                'master' => Penandatangan::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'gelar', 'jabatan']),
                'jenis' => collect(['semua' => 'Semua jenis'])
                    ->union(collect(JenisSertifikat::cases())->mapWithKeys(fn (JenisSertifikat $j) => [$j->value => $j->label()]))
                    ->all(),
            ],
        ]);
    }
}
