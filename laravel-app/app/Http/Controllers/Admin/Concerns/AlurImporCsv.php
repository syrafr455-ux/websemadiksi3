<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\Impor\PenyimpananImpor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alur impor CSV admin: unggah -> pratinjau (validasi per baris) -> simpan / batal.
 * File sementara disimpan terenkripsi (PenyimpananImpor) dan dihapus setelah selesai.
 */
trait AlurImporCsv
{
    abstract protected function jenisImpor(): string;

    abstract protected function routeIndex(): string;

    abstract protected function routePratinjau(): string;

    abstract protected function namaTemplate(): string;

    abstract protected function isiTemplate(): string;

    public function template(): Response
    {
        return response($this->isiTemplate(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->namaTemplate().'"',
        ]);
    }

    public function unggah(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Pilih file CSV yang akan diimpor.',
            'file.mimes' => 'File harus berformat CSV (simpan dari Excel sebagai "CSV UTF-8").',
        ]);

        $file = $request->file('file');
        PenyimpananImpor::simpan($this->jenisImpor(), (string) file_get_contents($file->getRealPath()), $file->getClientOriginalName());

        return redirect()->route($this->routePratinjau());
    }

    public function batal(): RedirectResponse
    {
        PenyimpananImpor::hapus($this->jenisImpor());

        return redirect()->route($this->routeIndex())->with('success', 'Impor dibatalkan; file sementara dihapus.');
    }

    /**
     * @return array{isi: string, nama_file: string}|null
     */
    protected function fileImpor(): ?array
    {
        return PenyimpananImpor::ambil($this->jenisImpor());
    }

    protected function tanpaFile(): RedirectResponse
    {
        return redirect()->route($this->routeIndex())->with('error', 'Tidak ada file impor yang sedang dipratinjau. Unggah ulang file CSV.');
    }

    /**
     * @param  array<string, int>  $ringkasan
     */
    protected function selesai(array $ringkasan): RedirectResponse
    {
        PenyimpananImpor::hapus($this->jenisImpor());
        $tersimpan = ($ringkasan['baru'] ?? 0) + ($ringkasan['perbarui'] ?? 0);

        return redirect()->route($this->routeIndex())->with('success', sprintf(
            'Impor selesai: %d baris tersimpan (%d baru, %d diperbarui), %d baris galat dilewati.',
            $tersimpan,
            $ringkasan['baru'] ?? 0,
            $ringkasan['perbarui'] ?? 0,
            $ringkasan['galat'] ?? 0
        ));
    }
}
