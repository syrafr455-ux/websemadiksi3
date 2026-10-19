<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AlurImporCsv;
use App\Http\Controllers\Controller;
use App\Models\MasterMahasiswaUnusa;
use App\Services\Impor\ImporMasterMahasiswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Data Mahasiswa UNUSA": master mahasiswa hasil impor CSV (super_admin).
 */
class MasterMahasiswaController extends Controller
{
    use AlurImporCsv;

    public function __construct(private readonly ImporMasterMahasiswa $impor) {}

    protected function jenisImpor(): string
    {
        return 'master_mahasiswa';
    }

    protected function routeIndex(): string
    {
        return 'admin.mahasiswa-unusa';
    }

    protected function routePratinjau(): string
    {
        return 'admin.mahasiswa-unusa.pratinjau';
    }

    protected function namaTemplate(): string
    {
        return 'template_master_mahasiswa_unusa.csv';
    }

    protected function isiTemplate(): string
    {
        return ImporMasterMahasiswa::template();
    }

    public function index(Request $request): View
    {
        $mahasiswa = MasterMahasiswaUnusa::with('programStudi.fakultas')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->input('q'));
                $query->where(fn ($w) => $w->where('nim', 'like', "%{$q}%")->orWhere('nama_lengkap', 'like', "%{$q}%"));
            })
            ->orderBy('nim')
            ->paginate(25)
            ->withQueryString();

        return view('admin.mahasiswa-unusa.index', [
            'mahasiswa' => $mahasiswa,
            'total' => MasterMahasiswaUnusa::count(),
        ]);
    }

    public function pratinjau(): View|RedirectResponse
    {
        $file = $this->fileImpor();
        if (! $file) {
            return $this->tanpaFile();
        }

        return view('admin.mahasiswa-unusa.pratinjau', [
            'namaFile' => $file['nama_file'],
            'periksa' => $this->impor->periksa($file['isi']),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $file = $this->fileImpor();
        if (! $file) {
            return $this->tanpaFile();
        }

        return $this->selesai($this->impor->simpan($file['isi'], $file['nama_file'], $request->user()));
    }
}
