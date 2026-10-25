<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengguna\MahasiswaKipkRequest;
use App\Http\Requests\Pengguna\PenggunaRequest;
use App\Http\Requests\Pengguna\PenilaiKaryaRequest;
use App\Http\Requests\Pengguna\SuperAdminRequest;
use App\Http\Requests\Pengguna\UserUmumRequest;
use App\Http\Requests\Pengguna\VerifikatorBerkasRequest;
use App\Models\AuditLog;
use App\Models\Fakultas;
use App\Models\Kegiatan;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\Kip\StatusKipService;
use App\Services\UnusaAcademicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Tampilkan matriks manajemen dan pelacakan pengguna.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query()
            ->with(['programStudi:id,nama,fakultas_id', 'fakultas:id,nama', 'lombaDinilai:id,nama_kegiatan', 'penerimaKipk', 'klaimKipk'])
            ->latest('id');

        // Pencarian case-insensitive (PostgreSQL ILIKE / SQLite LIKE)
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($w) use ($q, $likeOp) {
                $w->where('name', $likeOp, "%{$q}%")
                    ->orWhere('email', $likeOp, "%{$q}%")
                    ->orWhere('nim', $likeOp, "%{$q}%")
                    ->orWhere('prodi', $likeOp, "%{$q}%")
                    ->orWhere('asal_kampus', $likeOp, "%{$q}%")
                    ->orWhere('program_studi_teks', $likeOp, "%{$q}%")
                    ->orWhere('last_login_ip', $likeOp, "%{$q}%");
            });
        }

        // Hanya nilai enum UserRole yang dipakai sebagai filter (nilai lama seperti 'student' diabaikan).
        if ($role = UserRole::tryFrom((string) $request->input('role'))) {
            $query->where('role', $role->value);
        }

        if ($request->filled('is_blocked')) {
            $query->where('is_blocked', $request->boolean('is_blocked'));
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->input('verification_status'));
        }

        $users = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => User::count(),
            'super_admin' => User::where('role', UserRole::SuperAdmin->value)->count(),
            'mahasiswa_kipk' => User::where('role', UserRole::MahasiswaKipk->value)->count(),
            'user_umum' => User::where('role', UserRole::UserUmum->value)->count(),
            'verifikator_berkas' => User::where('role', UserRole::VerifikatorBerkas->value)->count(),
            'penilai_karya' => User::where('role', UserRole::PenilaiKarya->value)->count(),
            'blocked' => User::where('is_blocked', true)->count(),
        ];

        return Inertia::render('Admin/Pengguna/Index', [
            'title' => 'Data Pengguna & Tracking Akses',
            'users' => $users,
            'filters' => $request->only(['q', 'role', 'is_blocked', 'verification_status']),
            'stats' => $stats,
            'roleOptions' => UserRole::options(),
            'fakultasOptions' => app(UnusaAcademicService::class)->fakultasDenganProdi(),
            'lombaOptions' => Kegiatan::where('kategori', 'lomba')->orderBy('nama_kegiatan')->get(['id', 'nama_kegiatan', 'status']),
            'verifikatorEmailDomains' => VerifikatorBerkasRequest::domainEmail(),
        ]);
    }

    /**
     * Buat akun pengguna baru dengan salah satu dari 5 peran sistem.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $request->validate(['role' => ['required', Rule::enum(UserRole::class)]]);
        $roleEnum = UserRole::from($request->input('role'));

        // Validasi sesuai role (Form Request per role).
        $form = $this->formUntuk($roleEnum);

        $user = DB::transaction(function () use ($form, $roleEnum) {
            $user = new User($form->atributPengguna());
            $user->forceFill([
                'role' => $roleEnum,
                'verification_status' => 'Verified',
                'is_blocked' => false,
            ])->save();

            if ($form instanceof PenilaiKaryaRequest) {
                $user->lombaDinilai()->sync($form->lombaIds());
            }

            return $user;
        });

        AuditLog::record(
            auth()->id(),
            'create_user',
            "Membuat akun pengguna baru '{$user->name}' dengan role {$user->role->value} (ID: {$user->id})",
            $user
        );

        return back()->with('success', "Akun '{$user->name}' dengan peran {$user->role->label()} berhasil dibuat.");
    }

    /**
     * Ubah data pengguna. Role tidak berubah; password kosong berarti tidak diubah.
     */
    public function update(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        Gate::authorize('update', $user);

        if ($user->role === UserRole::MahasiswaKipk) {
            return back()->with('error', 'Edit akun Mahasiswa KIP-K belum tersedia di form ini.');
        }

        $form = $this->formUntuk($user->role);

        DB::transaction(function () use ($form, $user) {
            $user->fill($form->atributPengguna())->save();

            if ($form instanceof PenilaiKaryaRequest && $form->mengirimLomba()) {
                $user->lombaDinilai()->sync($form->lombaIds());
            }
        });

        AuditLog::record(
            auth()->id(),
            'update_user',
            "Memperbarui akun pengguna '{$user->name}' dengan role {$user->role->value} (ID: {$user->id})",
            $user
        );

        return back()->with('success', "Akun '{$user->name}' berhasil diperbarui.");
    }

    /**
     * Ubah status KIP-K akun secara manual (super_admin), wajib beralasan dan tercatat di audit log.
     */
    public function statusKip(Request $request, int $id, StatusKipService $statusKip): RedirectResponse
    {
        $user = User::findOrFail($id);
        Gate::authorize('update', $user);
        abort_unless(in_array($user->role, [UserRole::MahasiswaKipk, UserRole::UserUmum], true), 422, 'Status KIP-K hanya untuk akun mahasiswa.');

        $data = $request->validate([
            'status' => ['required', 'in:kipk,bukan_kipk'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan.required' => 'Alasan perubahan status wajib diisi.',
            'alasan.min' => 'Alasan minimal 5 karakter.',
        ]);

        $jadiKipk = $data['status'] === 'kipk';
        if ($jadiKipk === ($user->role === UserRole::MahasiswaKipk)) {
            return back()->with('error', 'Status KIP-K akun tidak berubah.');
        }

        // Akun KIP-K selalu akun UNUSA, dan NIM wajib unik di antara akun UNUSA.
        if ($jadiKipk && $user->nim && User::where('nim', $user->nim)->where('is_unusa', true)->whereKeyNot($user->id)->exists()) {
            return back()->with('error', 'NIM akun ini sudah dipakai akun mahasiswa UNUSA lain. Rapikan data NIM terlebih dahulu.');
        }

        DB::transaction(fn () => $jadiKipk
            ? $statusKip->tetapkanManual($user, $request->user(), $data['alasan'])
            : $statusKip->cabut($user, $request->user(), $data['alasan']));

        return back()->with('success', "Status KIP-K akun '{$user->name}' diperbarui.");
    }

    /**
     * Atur fakultas & program studi akun mahasiswa_kipk (mis. akun lama tanpa prodi) agar masuk cakupan
     * verifikator yang sesuai. Hanya dua field ini yang diubah; perubahan tercatat di audit log.
     */
    public function akademik(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        Gate::authorize('update', $user);
        abort_unless($user->role === UserRole::MahasiswaKipk, 422, 'Fakultas & prodi hanya diatur di sini untuk akun Mahasiswa KIP-K.');

        $data = $request->validate([
            'fakultas_id' => ['required', 'integer', Rule::exists('fakultas', 'id')],
            'program_studi_id' => ['required', 'integer', Rule::exists('program_studi', 'id')->where('fakultas_id', (int) $request->input('fakultas_id'))],
        ], [
            'fakultas_id.required' => 'Fakultas wajib dipilih.',
            'program_studi_id.required' => 'Program studi wajib dipilih.',
            'program_studi_id.exists' => 'Program studi tidak termasuk fakultas yang dipilih.',
        ]);

        $lama = ['fakultas_id' => $user->fakultas_id, 'program_studi_id' => $user->program_studi_id];
        $baru = ['fakultas_id' => (int) $data['fakultas_id'], 'program_studi_id' => (int) $data['program_studi_id']];
        if ($lama === $baru) {
            return back()->with('success', 'Fakultas & program studi tidak berubah.');
        }

        $user->forceFill($baru)->save();

        $nama = fn (array $ids): string => (ProgramStudi::find($ids['program_studi_id'])?->nama ?? '-')
            .' / '.(Fakultas::find($ids['fakultas_id'])?->nama ?? '-');
        AuditLog::record(
            auth()->id(),
            'update_user_akademik',
            "Mengatur fakultas & prodi akun '{$user->name}' (ID: {$user->id}): "
                .($lama['program_studi_id'] ? $nama($lama) : 'belum diatur').' -> '.$nama($baru),
            $user
        );

        return back()->with('success', "Fakultas & program studi akun '{$user->name}' diperbarui.");
    }

    /**
     * Form Request sesuai role; validasi berjalan saat di-resolve dari container.
     */
    protected function formUntuk(UserRole $role): PenggunaRequest
    {
        return app(match ($role) {
            UserRole::UserUmum => UserUmumRequest::class,
            UserRole::MahasiswaKipk => MahasiswaKipkRequest::class,
            UserRole::VerifikatorBerkas => VerifikatorBerkasRequest::class,
            UserRole::PenilaiKarya => PenilaiKaryaRequest::class,
            UserRole::SuperAdmin => SuperAdminRequest::class,
        });
    }

    /**
     * Nonaktifkan atau aktifkan kembali akun pengguna.
     */
    public function toggleBlock(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        Gate::authorize('toggleBlock', $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $user->is_blocked = ! (bool) $user->is_blocked;
        $user->save();

        $statusText = $user->is_blocked ? 'dinonaktifkan' : 'diaktifkan kembali';

        AuditLog::record(
            auth()->id(),
            'toggle_block_user',
            "Mengubah status blokir pengguna '{$user->name}' menjadi {$statusText} (ID: {$user->id})",
            $user
        );

        return back()->with('success', "Akun '{$user->name}' berhasil {$statusText}.");
    }

    /**
     * Verifikasi status akun pengguna.
     */
    public function verify(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        Gate::authorize('verify', $user);

        $user->verification_status = 'Verified';
        $user->save();

        AuditLog::record(
            auth()->id(),
            'verify_user',
            "Memverifikasi akun pengguna '{$user->name}' (ID: {$user->id})",
            $user
        );

        return back()->with('success', "Akun '{$user->name}' telah berhasil diverifikasi.");
    }

    /**
     * Reset 2FA akun pengguna oleh Super Admin (wajib menyertakan alasan).
     */
    public function resetTwoFactor(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan reset 2FA wajib diisi.',
            'reason.min' => 'Alasan reset minimal 5 karakter.',
        ]);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        AuditLog::record(
            auth()->id(),
            'reset_two_factor',
            "Mereset autentikasi dua faktor (2FA) akun '{$user->name}' (ID: {$user->id}). Alasan: {$validated['reason']}",
            $user
        );

        return back()->with('success', "Autentikasi dua faktor (2FA) akun '{$user->name}' berhasil diatur ulang.");
    }
}
