<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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

        $query = User::query()->latest('id');

        // Pencarian case-insensitive (PostgreSQL ILIKE / SQLite LIKE)
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $likeOp = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($w) use ($q, $likeOp) {
                $w->where('name', $likeOp, "%{$q}%")
                  ->orWhere('email', $likeOp, "%{$q}%")
                  ->orWhere('nim', $likeOp, "%{$q}%")
                  ->orWhere('prodi', $likeOp, "%{$q}%")
                  ->orWhere('last_login_ip', $likeOp, "%{$q}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
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
        ]);
    }

    /**
     * Buat akun pengguna baru dengan salah satu dari 5 peran sistem.
     */
    public function store(Request $request): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'nim' => ['nullable', 'string', 'max:50', 'unique:users,nim'],
            'phone' => ['nullable', 'string', 'max:20'],
            'angkatan' => ['nullable', 'string', 'max:10'],
            'prodi' => ['nullable', 'string', 'max:100'],
            'university' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:'.implode(',', UserRole::values())],
            'password' => ['required', Password::min(8)],
        ]);

        $roleEnum = UserRole::from($validated['role']);

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'nim' => ! empty($validated['nim']) ? trim($validated['nim']) : null,
            'phone' => ! empty($validated['phone']) ? trim($validated['phone']) : null,
            'angkatan' => $validated['angkatan'] ?? null,
            'prodi' => $validated['prodi'] ?? null,
            'university' => $validated['university'] ?? 'Universitas Nahdlatul Ulama Surabaya',
            'kip_status' => $roleEnum === UserRole::MahasiswaKipk ? 'KIP UNUSA' : 'Umum',
            'verification_status' => 'Verified',
            'role' => $roleEnum,
            'is_blocked' => false,
            'password' => Hash::make($validated['password']),
        ]);

        AuditLog::record(
            auth()->id(),
            'create_user',
            "Membuat akun pengguna baru '{$user->name}' dengan role {$user->role->value} (ID: {$user->id})",
            $user
        );

        return back()->with('success', "Akun '{$user->name}' dengan peran {$user->role->label()} berhasil dibuat.");
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
