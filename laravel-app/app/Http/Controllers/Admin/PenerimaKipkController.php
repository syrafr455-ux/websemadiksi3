<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AlurImporCsv;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PenerimaKipk;
use App\Services\Impor\ImporPenerimaKipk;
use App\Services\Kip\StatusKipService;
use App\Support\NomorKip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public const FILTER_STATUS = [
        'belum_terhubung' => 'Belum terhubung',
        'terhubung' => 'Terhubung',
        'perlu_ditinjau' => 'Perlu ditinjau',
    ];

    public function index(Request $request): View
    {
        $status = $request->input('status');

        $penerima = PenerimaKipk::with(['user:id,name,email,nim,role', 'pengklaim:id,name,email,nim'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->input('q'));
                $query->where(fn ($w) => $w->where('nim', 'like', "%{$q}%")->orWhere('nama_lengkap', 'like', "%{$q}%"));
            })
            ->when($status === 'belum_terhubung', fn ($q) => $q->whereNull('user_id')->where('status_tinjau', PenerimaKipk::TINJAU_NORMAL))
            ->when($status === 'terhubung', fn ($q) => $q->whereNotNull('user_id'))
            ->when($status === 'perlu_ditinjau', fn ($q) => $q->where('status_tinjau', PenerimaKipk::TINJAU_PERLU))
            ->orderByRaw('CASE WHEN status_tinjau = ? THEN 0 ELSE 1 END', [PenerimaKipk::TINJAU_PERLU])
            ->orderBy('nama_lengkap')
            ->paginate(25)
            ->withQueryString();

        return view('admin.penerima-kipk.index', [
            'penerima' => $penerima,
            'total' => PenerimaKipk::count(),
            'jumlahDitinjau' => PenerimaKipk::where('status_tinjau', PenerimaKipk::TINJAU_PERLU)->count(),
            'filterStatus' => self::FILTER_STATUS,
        ]);
    }

    /**
     * Buka nomor pendaftaran lengkap (super_admin saja, lewat route group). Setiap pembukaan diaudit.
     */
    public function bukaNomor(Request $request, int $id): JsonResponse
    {
        $penerima = PenerimaKipk::findOrFail($id);

        AuditLog::record($request->user()->id, 'buka_nomor_kip', "Membuka nomor pendaftaran KIP lengkap penerima #{$penerima->id} ({$penerima->nama_lengkap})", null, [
            'penerima_id' => $penerima->id,
        ]);

        return response()->json(['nomor' => NomorKip::format($penerima->nomor_pendaftaran_kip)])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Tinjau baris "perlu_ditinjau": setujui/tolak klaim, atau pertahankan/cabut penerima yang hilang dari impor.
     */
    public function tinjau(Request $request, int $id, StatusKipService $statusKip): RedirectResponse
    {
        $penerima = PenerimaKipk::findOrFail($id);
        $data = $request->validate([
            'keputusan' => ['required', 'in:setujui,tolak,pertahankan,cabut'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan.required' => 'Alasan keputusan wajib diisi.',
            'alasan.min' => 'Alasan minimal 5 karakter.',
        ]);

        DB::transaction(fn () => match ($data['keputusan']) {
            'setujui' => $statusKip->setujuiKlaim($penerima, $request->user(), $data['alasan']),
            'tolak' => $statusKip->tolakKlaim($penerima, $request->user(), $data['alasan']),
            'pertahankan' => $statusKip->tinjauHilang($penerima, $request->user(), true, $data['alasan']),
            'cabut' => $statusKip->tinjauHilang($penerima, $request->user(), false, $data['alasan']),
        });

        return back()->with('success', 'Keputusan tinjauan disimpan dan tercatat di audit log.');
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
