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
use App\Models\Kegiatan;
use App\Models\User;
use App\Services\Kip\StatusKipService;
use App\Services\UnusaAcademicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        \Illuminate\Support\Facades\Gate::authorize('viewAny', User::class);

        $query = User::query()
            ->with(['programStudi:id,nama,fakultas_id', 'fakultas:id,nama', 'lombaDinilai:id,nama_kegiatan', 'penerimaKipk', 'klaimKipk'])
            ->latest('id');

        // Pencarian case-insensitive (PostgreSQL ILIKE / SQLite LIKE)
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $likeOp = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
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
        \Illuminate\Support\Facades\Gate::authorize('create', User::class);

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
        \Illuminate\Support\Facades\Gate::authorize('update', $user);

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
        \Illuminate\Support\Facades\Gate::authorize('update', $user);
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

        DB::transaction(fn () => $jadiKipk
            ? $statusKip->tetapkanManual($user, $request->user(), $data['alasan'])
            : $statusKip->cabut($user, $request->user(), $data['alasan']));

        return back()->with('success', "Status KIP-K akun '{$user->name}' diperbarui.");
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
        \Illuminate\Support\Facades\Gate::authorize('toggleBlock', $user);

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
        \Illuminate\Support\Facades\Gate::authorize('verify', $user);

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
}
