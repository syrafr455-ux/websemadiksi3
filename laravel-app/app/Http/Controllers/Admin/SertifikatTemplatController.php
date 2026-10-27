<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisSertifikat;
use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\Sertifikat;
use App\Models\SertifikatTemplat;
use App\Services\Sertifikat\BerkasSertifikatService;
use App\Services\Sertifikat\BuatPdfSertifikat;
use App\Services\Sertifikat\TemplatSertifikatService;
use App\Support\Sertifikat\TataLetak;
use App\Support\Sertifikat\TeksTemplat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Editor templat sertifikat per kegiatan dan pembuatan berkas. Hanya super_admin (SertifikatPolicy::viewAny).
 */
class SertifikatTemplatController extends Controller
{
    public function __construct(
        private readonly TemplatSertifikatService $templat,
        private readonly BuatPdfSertifikat $pembuat,
        private readonly BerkasSertifikatService $berkas,
    ) {}

    private function kegiatan(int $id): Kegiatan
    {
        Gate::authorize('viewAny', Sertifikat::class);

        return Kegiatan::findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function aturanTemplat(): array
    {
        return [
            'jenis' => ['nullable', Rule::in(JenisSertifikat::values())],
            'orientasi' => ['required', Rule::in(TataLetak::ORIENTASI)],
            'layout' => ['required', 'array'],
            'teks' => ['present', 'array'],
        ];
    }

    public function simpan(Request $request, int $id): JsonResponse
    {
        $kegiatan = $this->kegiatan($id);
        $data = $request->validate($this->aturanTemplat());

        $templat = $this->templat->simpan($kegiatan, $data['jenis'] ?? null, $data['orientasi'], $data['layout'], $data['teks'], $request->user(), $request->ip());

        return response()->json(['pesan' => 'Templat disimpan.', 'versi' => $templat->versi, 'templat' => $this->templat->untukEditor($kegiatan)]);
    }

    public function reset(Request $request, int $id): JsonResponse
    {
        $kegiatan = $this->kegiatan($id);
        $data = $request->validate(['jenis' => ['nullable', Rule::in(JenisSertifikat::values())]]);

        $this->templat->reset($kegiatan, $data['jenis'] ?? null, $request->user());

        return response()->json(['pesan' => 'Templat dikembalikan ke bawaan.', 'templat' => $this->templat->untukEditor($kegiatan)]);
    }

    public function unggahLatar(Request $request, int $id): JsonResponse
    {
        $kegiatan = $this->kegiatan($id);
        $request->validate(['latar' => ['required', 'file']]);

        $hasil = $this->templat->simpanLatar($kegiatan, $request->file('latar'), $request->user());

        return response()->json([
            'pesan' => 'Latar diunggah.',
            'peringatan' => $hasil['rasio_cocok'] ? null : 'Rasio gambar tidak sama dengan halaman A4. Latar akan diisi penuh (diregangkan) mengikuti halaman.',
            'templat' => $this->templat->untukEditor($kegiatan),
        ]);
    }

    public function hapusLatar(Request $request, int $id): JsonResponse
    {
        $kegiatan = $this->kegiatan($id);
        $this->templat->hapusLatar($kegiatan, $request->user());

        return response()->json(['pesan' => 'Latar dihapus.', 'templat' => $this->templat->untukEditor($kegiatan)]);
    }

    /** Menyajikan latar dari disk privat hanya untuk super_admin (pratinjau di editor). */
    public function tampilLatar(int $id): Response
    {
        $kegiatan = $this->kegiatan($id);
        $path = SertifikatTemplat::where('kegiatan_id', $kegiatan->id)->whereNull('jenis')->value('latar_path');
        abort_unless($path && Storage::disk(TemplatSertifikatService::DISK)->exists($path), 404);

        return response(Storage::disk(TemplatSertifikatService::DISK)->get($path), 200, [
            'Content-Type' => str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function salin(Request $request, int $id): JsonResponse
    {
        $kegiatan = $this->kegiatan($id);
        $data = $request->validate(['sumber_id' => ['required', 'integer', 'exists:kegiatan,id']]);

        $jumlah = $this->templat->salinDari(Kegiatan::findOrFail($data['sumber_id']), $kegiatan, $request->user());

        return response()->json(['pesan' => "{$jumlah} templat disalin.", 'templat' => $this->templat->untukEditor($kegiatan)]);
    }

    /**
     * Pratinjau PDF dari tata letak yang sedang diedit (belum disimpan). Berisi watermark CONTOH, QR menunjuk
     * ke tautan contoh yang tidak valid, dan tidak memakai atau menghabiskan nomor seri sungguhan.
     */
    public function pratinjau(Request $request, int $id): Response
    {
        $kegiatan = $this->kegiatan($id);
        $data = $request->validate($this->aturanTemplat());
        $jenis = JenisSertifikat::tryFrom((string) ($data['jenis'] ?? '')) ?? JenisSertifikat::Peserta;

        $layout = TataLetak::validasi($data['layout'], $data['orientasi']);
        $teks = array_replace(
            TeksTemplat::bawaan()[$jenis->value],
            TeksTemplat::validasi($data['teks'])[$jenis->value] ?? [],
        );
        $tersimpan = $this->templat->untuk($kegiatan, $jenis);

        $pdf = $this->pembuat->pdf(
            layout: $layout,
            teks: $teks,
            orientasi: $data['orientasi'],
            latarDataUri: $this->templat->dataUriLatar($tersimpan['latar_path']),
            data: [
                'nama' => 'Nama Peserta Contoh',
                'asal_institusi' => 'Universitas Nahdlatul Ulama Surabaya',
                'jenis' => $jenis->label(),
                'predikat' => $jenis === JenisSertifikat::Juara ? 'Juara 1' : '',
                'kegiatan' => $kegiatan->nama_kegiatan,
                'tanggal' => '1 Januari 2026',
                'nomor' => 'CERT-CONTOH-0000-0000',
                'tanggal_terbit' => 'Surabaya, 1 Januari 2026',
            ],
            tautanVerifikasi: url('/verifikasi?code=CONTOH-TIDAK-VALID'),
            penandatangan: collect(range(1, 4))->map(fn (int $i) => [
                'nama_lengkap' => "Nama Penandatangan {$i}", 'jabatan' => 'Jabatan', 'identitas_terformat' => 'NIP: 1975********01',
            ])->all(),
            contoh: true,
        );

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="pratinjau-sertifikat.pdf"']);
    }

    /** Membuat berkas untuk sertifikat yang belum punya, 20 per permintaan; klien mengulang dengan kursor. */
    public function buatBerkasBatch(Request $request, int $id): JsonResponse
    {
        $kegiatan = $this->kegiatan($id);
        $kursor = (int) $request->input('kursor', 0);

        return response()->json($this->berkas->batch($kegiatan, max(0, $kursor)));
    }

    public function buatUlangBerkas(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAny', Sertifikat::class);
        $data = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:500']]);
        $sertifikat = Sertifikat::findOrFail($id);

        $hasil = $this->berkas->buatUlang($sertifikat, $request->user(), $data['alasan'], $request->ip());

        return response()->json(['berhasil' => $hasil->adaBerkas(), 'hash' => $hasil->document_hash, 'file_status' => $hasil->file_status]);
    }
}
