<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AlurImporCsv;
use App\Http\Controllers\Controller;
use App\Models\PenerimaKipk;
use App\Services\Impor\ImporPenerimaKipk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Daftar Penerima KIP-K": impor & pengelolaan daftar penerima berbasis nomor pendaftaran SIM KIP Kuliah (super_admin).
 */
class PenerimaKipkController extends Controller
{
    use AlurImporCsv;

    public function __construct(private readonly ImporPenerimaKipk $impor) {}

    protected function jenisImpor(): string
    {
        return 'penerima_kipk';
    }

    protected function routeIndex(): string
    {
        return 'admin.penerima-kipk';
    }

    protected function routePratinjau(): string
    {
        return 'admin.penerima-kipk.pratinjau';
    }

    protected function namaTemplate(): string
    {
        return 'template_daftar_penerima_kipk.csv';
    }

    protected function isiTemplate(): string
    {
        return ImporPenerimaKipk::template();
    }

    public function index(Request $request): View
    {
        $penerima = PenerimaKipk::with(['user:id,name,email,nim,role', 'pengklaim:id,name,email,nim'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->input('q'));
                $query->where(fn ($w) => $w->where('nim', 'like', "%{$q}%")->orWhere('nama_lengkap', 'like', "%{$q}%"));
            })
            ->orderBy('nama_lengkap')
            ->paginate(25)
            ->withQueryString();

        return view('admin.penerima-kipk.index', [
            'penerima' => $penerima,
            'total' => PenerimaKipk::count(),
        ]);
    }

    public function pratinjau(): View|RedirectResponse
    {
        $file = $this->fileImpor();
        if (! $file) {
            return $this->tanpaFile();
        }

        return view('admin.penerima-kipk.pratinjau', [
            'namaFile' => $file['nama_file'],
            'periksa' => $this->impor->pratinjau($file['isi']),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $file = $this->fileImpor();
        if (! $file) {
            return $this->tanpaFile();
        }

        $ringkasan = $this->impor->simpan($file['isi'], $file['nama_file'], $request->user(), $request->boolean('daftar_lengkap'));

        return $this->selesai($ringkasan)->with('info_sinkron', sprintf(
            'Sinkronisasi akun: %d akun dinaikkan menjadi mahasiswa_kipk, %d akun KIP-K ditautkan, %d penerima ditandai perlu ditinjau karena tidak ada di file.',
            $ringkasan['dinaikkan'] ?? 0,
            $ringkasan['ditautkan'] ?? 0,
            $ringkasan['ditandai_hilang'] ?? 0
        ));
    }
}
