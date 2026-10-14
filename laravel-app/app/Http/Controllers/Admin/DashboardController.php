<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use App\Models\KipkDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Admin Overview Dashboard.
     */
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user && $user->role === \App\Enums\UserRole::PenilaiKarya) {
            return redirect()->route('admin.penilaian');
        }

        if ($user && $user->role === \App\Enums\UserRole::VerifikatorBerkas) {
            return redirect()->route('admin.berkas');
        }

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Akses terbatas untuk Super Administrator.');
        }

        $pencairanForm = CustomForm::where('form_key', 'pencairan')->first();
        $pencairanCount = $pencairanForm ? CustomFormSubmission::where('custom_form_id', $pencairanForm->id)->count() : 0;
        $pencairanPending = $pencairanForm ? CustomFormSubmission::where('custom_form_id', $pencairanForm->id)->where('status', 'Menunggu Review')->count() : 0;
        $pendingDocs = KipkDocument::where('status', 'Menunggu Review')->count();

        $stats = [
            'total_students' => User::whereIn('role', [\App\Enums\UserRole::MahasiswaKipk->value, 'student'])->count(),
            'active_students' => User::whereIn('role', [\App\Enums\UserRole::MahasiswaKipk->value, 'student'])->where('is_blocked', false)->count(),
            'pending_verifications' => User::where('verification_status', 'Pending')->count(),
            'total_activities' => Activity::count(),
            'total_certificates' => Certificate::count(),
            'total_attendances' => Attendance::count(),
            'pending_documents' => $pendingDocs,
            'total_submissions' => CustomFormSubmission::count(),
            'pencairan_count' => $pencairanCount,
            'pencairan_pending' => $pencairanPending,
            'pencairan_status' => $pencairanPending > 0
                ? "{$pencairanPending} Pengajuan Menunggu Review (Total {$pencairanCount})"
                : ($pencairanCount > 0 ? "Semua Terverifikasi ({$pencairanCount} Pengajuan)" : "Jalur Aktif UNUSA (Belum ada antrean baru)"),
        ];

        $recentStudents = User::whereIn('role', [\App\Enums\UserRole::MahasiswaKipk->value, 'student'])->latest('id')->take(5)->get();
        $recentAttendances = Attendance::latest('id')->take(5)->get();
        $recentDocs = KipkDocument::latest('id')->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentStudents', 'recentAttendances', 'recentDocs'));
    }

    /**
     * Manage Students / Users (/admin/pengguna - route name: admin.users).
     * Compatible with PostgreSQL & Supabase schema.
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Search text case-insensitively using PostgreSQL 'ilike'
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($w) use ($q) {
                $w->where('name', 'ilike', "%{$q}%")
                  ->orWhere('email', 'ilike', "%{$q}%")
                  ->orWhere('nim', 'ilike', "%{$q}%")
                  ->orWhere('prodi', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // Safe order by existing primary key or created_at
        $users = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // If Inertia is installed/used, render Inertia component
        if (class_exists(\Inertia\Inertia::class)) {
            return \Inertia\Inertia::render('Admin/Pengguna', [
                'title' => 'Data Pengguna',
                'users' => $users,
                'filters' => $request->only(['q', 'role']),
            ]);
        }

        return view('admin.users.index', compact('users'));
    }

    public function toggleBlockUser(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin() && $user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $user->is_blocked = ! (bool) $user->is_blocked;
        $user->save();

        $status = $user->is_blocked ? 'dinonaktifkan' : 'diaktifkan kembali';
        return back()->with('success', "Akun {$user->name} berhasil {$status}.");
    }

    public function verifyUser(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $user->verification_status = 'Verified';
        $user->save();

        return back()->with('success', "Akun {$user->name} berhasil diverifikasi.");
    }

    /**
     * Manage Activities (Kegiatan).
     */
    public function kegiatan(): View
    {
        $activities = Activity::withCount('registrations')->latest('id')->paginate(10);
        return view('admin.kegiatan.index', compact('activities'));
    }

    public function storeKegiatan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'type' => ['required', 'string'],
            'date_text' => ['required', 'string'],
            'location' => ['required', 'string'],
            'price' => ['required', 'string'],
            'quota' => ['required', 'integer', 'min:1'],
            'xp_points' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'speaker' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $imageUrl = 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&auto=format&fit=crop&q=80';
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('activities', 'public');
            $imageUrl = Storage::url($path);
        }

        Activity::create([
            'title' => trim($validated['title']),
            'category' => $validated['category'],
            'type' => $validated['type'],
            'date_text' => trim($validated['date_text']),
            'location' => trim($validated['location']),
            'price' => trim($validated['price']),
            'quota' => (int) $validated['quota'],
            'xp_points' => (int) $validated['xp_points'],
            'description' => $validated['description'],
            'speaker' => $validated['speaker'],
            'image_url' => $imageUrl,
            'is_latest' => true,
            'is_closed' => false,
            'enable_seat_booking' => $request->boolean('enable_seat_booking'),
            'require_file_upload' => $request->boolean('require_file_upload'),
        ]);

        return back()->with('success', 'Kegiatan baru berhasil ditambahkan.');
    }

    public function deleteKegiatan(int $id): RedirectResponse
    {
        $activity = Activity::findOrFail($id);
        $activity->delete();

        return back()->with('success', 'Kegiatan berhasil dihapus.');
    }

    /**
     * Manage Certificates.
     */
    public function certificates(): View
    {
        $certificates = Certificate::with(['user', 'activity'])->latest('id')->paginate(15);
        $activities = Activity::all();
        $students = User::where('role', 'student')->get();

        return view('admin.certificates.index', compact('certificates', 'activities', 'students'));
    }

    public function storeCertificate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'activity_id' => ['required', 'exists:activities,id'],
            'code' => ['nullable', 'string', 'unique:certificates,code'],
            'issued_date' => ['required', 'date'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $activity = Activity::findOrFail($validated['activity_id']);

        $code = $validated['code'] ?: 'SMD-' . strtoupper(Str::slug($activity->category)) . '-' . date('Y') . '-' . rand(1000, 9999);

        Certificate::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'user_name' => $user->name,
            'activity_title' => $activity->title,
            'code' => $code,
            'status' => 'Berlaku',
            'issued_date' => $validated['issued_date'],
        ]);

        return back()->with('success', "Sertifikat dengan kode {$code} berhasil diterbitkan.");
    }

    public function deleteCertificate(int $id): RedirectResponse
    {
        Certificate::findOrFail($id)->delete();
        return back()->with('success', 'Sertifikat berhasil dihapus.');
    }

    /**
     * Manage Attendances.
     */
    public function attendances(): View
    {
        $attendances = Attendance::with(['user', 'activity'])->latest('id')->paginate(20);
        return view('admin.attendances.index', compact('attendances'));
    }

    public function updateAttendanceStatus(int $id, Request $request): RedirectResponse
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->status = $request->input('status', 'Hadir');
        $attendance->notes = $request->input('notes');
        $attendance->save();

        return back()->with('success', 'Status presensi diperbarui.');
    }

    /**
     * Manage KIP-K Documents.
     */
    public function berkas(): View
    {
        \Illuminate\Support\Facades\Gate::authorize('viewAny', KipkDocument::class);
        $documents = KipkDocument::latest('id')->paginate(15);
        return view('admin.berkas.index', compact('documents'));
    }

    public function downloadDoc(int $id)
    {
        $doc = KipkDocument::findOrFail($id);
        \Illuminate\Support\Facades\Gate::authorize('download', $doc);

        $relativePath = $doc->file_url ? str_replace('/storage/', '', parse_url($doc->file_url, PHP_URL_PATH)) : null;
        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->download($relativePath, $doc->file_name ?? 'berkas-kipk.pdf');
        }

        if ($doc->file_url) {
            return redirect($doc->file_url);
        }

        return back()->with('error', 'File berkas tidak ditemukan.');
    }

    public function updateDocStatus(int $id, Request $request): RedirectResponse
    {
        $doc = KipkDocument::findOrFail($id);
        \Illuminate\Support\Facades\Gate::authorize('verify', $doc);

        $doc->status = $request->input('status', 'Disetujui');
        $doc->score = (int) $request->input('score', 100);
        $doc->notes = $request->input('notes');
        $doc->verified_at = now();
        $doc->verified_by = auth()->user()->name;
        $doc->save();

        return back()->with('success', 'Status dan penilaian berkas berhasil disimpan.');
    }

    /**
     * Manage Custom Form Submissions.
     */
    public function submissions(): View
    {
        $submissions = CustomFormSubmission::with('form')->latest('id')->paginate(15);
        return view('admin.submissions.index', compact('submissions'));
    }

    public function updateSubmissionStatus(int $id, Request $request): RedirectResponse
    {
        $sub = CustomFormSubmission::findOrFail($id);
        $sub->status = $request->input('status', 'Disetujui');
        $sub->admin_notes = $request->input('admin_notes');
        $sub->save();

        return back()->with('success', 'Status formulir berhasil diperbarui.');
    }
}
