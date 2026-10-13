<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityRegistration;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use App\Models\KipInfo;
use App\Models\KipkDocument;
use App\Models\Scholarship;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Student Dashboard Summary.
     */
    public function index(): View
    {
        $user = Auth::user();

        $registrationsCount = ActivityRegistration::where('user_id', $user->id)->count();
        $attendancesCount = Attendance::where('user_id', $user->id)->count();
        $certificatesCount = Certificate::where('user_id', $user->id)->count();
        $documentsCount = KipkDocument::where('user_id', $user->id)->count();

        // Calculate XP
        $registeredActivityIds = ActivityRegistration::where('user_id', $user->id)->pluck('activity_id');
        $totalXp = Activity::whereIn('id', $registeredActivityIds)->sum('xp_points');

        $upcomingActivities = Activity::where('is_closed', false)
            ->latest()
            ->take(4)
            ->get();

        $myRecentActivities = ActivityRegistration::with('activity')
            ->where('user_id', $user->id)
            ->latest()
            ->take(4)
            ->get();

        return view('dashboard.index', compact(
            'user',
            'registrationsCount',
            'attendancesCount',
            'certificatesCount',
            'documentsCount',
            'totalXp',
            'upcomingActivities',
            'myRecentActivities'
        ));
    }

    /**
     * Explore Activities.
     */
    public function kegiatan(Request $request): View
    {
        $query = Activity::query();

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ilike', "%{$q}%")
                  ->orWhere('description', 'ilike', "%{$q}%")
                  ->orWhere('location', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $activities = $query->latest()->paginate(9)->withQueryString();
        $myRegisteredIds = ActivityRegistration::where('user_id', Auth::id())->pluck('activity_id')->toArray();

        return view('dashboard.kegiatan', compact('activities', 'myRegisteredIds'));
    }

    /**
     * Activity Detail & Registration Modal/Page.
     */
    public function kegiatanShow(int $id): View
    {
        $activity = Activity::findOrFail($id);
        $registration = ActivityRegistration::where('user_id', Auth::id())
            ->where('activity_id', $id)
            ->first();

        $occupiedSeats = ActivityRegistration::where('activity_id', $id)
            ->whereNotNull('selected_seat')
            ->pluck('selected_seat')
            ->toArray();

        return view('dashboard.kegiatan-detail', compact('activity', 'registration', 'occupiedSeats'));
    }

    /**
     * Join / Register Activity.
     */
    public function registerKegiatan(int $id, Request $request): RedirectResponse
    {
        $activity = Activity::findOrFail($id);
        $user = Auth::user();

        // Check if already registered
        $existing = ActivityRegistration::where('user_id', $user->id)
            ->where('activity_id', $id)
            ->first();

        if ($existing) {
            return back()->with('error', 'Anda sudah terdaftar pada kegiatan ini.');
        }

        // Validate seat if seat booking enabled
        $selectedSeat = $request->input('selected_seat');
        if ($activity->enable_seat_booking && $selectedSeat) {
            $isOccupied = ActivityRegistration::where('activity_id', $id)
                ->where('selected_seat', $selectedSeat)
                ->exists();

            if ($isOccupied) {
                return back()->with('error', "Kursi {$selectedSeat} telah dipilih peserta lain. Silakan pilih kursi lain.");
            }
        }

        // Validate file upload if required
        $uploadedFileUrl = null;
        if ($activity->require_file_upload) {
            $request->validate([
                'upload_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ], [
                'upload_file.required' => 'Kegiatan ini mewajibkan unggah berkas persyaratan.',
            ]);

            $path = $request->file('upload_file')->store('registrations', 'public');
            $uploadedFileUrl = Storage::url($path);
        }

        ActivityRegistration::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'selected_seat' => $selectedSeat,
            'uploaded_file_url' => $uploadedFileUrl,
            'payment_status' => $activity->price === 'Gratis' ? 'Gratis' : 'Lunas',
            'status' => 'Terdaftar',
            'registered_at' => now(),
        ]);

        return redirect()->route('dashboard.kegiatan.saya')->with('success', "Pendaftaran kegiatan {$activity->title} berhasil!");
    }

    /**
     * My Registered Activities.
     */
    public function kegiatanSaya(Request $request)
    {
        $userId = Auth::id();

        $enterpriseRegistrations = \App\Models\KegiatanRegistration::with(['kegiatan', 'seat', 'payment', 'submission'])
            ->where(function ($q) use ($userId) {
                $q->where('participant_user_id', $userId)
                  ->orWhere('booker_user_id', $userId);
            })
            ->latest('id')
            ->get();

        $registrations = ActivityRegistration::with('activity')
            ->where('user_id', $userId)
            ->latest()
            ->paginate(10);

        if (class_exists(\Inertia\Inertia::class)) {
            return \Inertia\Inertia::render('Dashboard/KegiatanSaya', [
                'title' => 'Kegiatan Saya & E-Tiket',
                'enterpriseRegistrations' => $enterpriseRegistrations,
                'legacyRegistrations' => $registrations,
            ]);
        }

        return view('dashboard.kegiatan-saya', compact('registrations', 'enterpriseRegistrations'));
    }

    /**
     * Upload Karya / Submission for Registered Event.
     */
    public function uploadKarya(Request $request, int $registrationId): RedirectResponse
    {
        $registration = \App\Models\KegiatanRegistration::with('kegiatan')
            ->where('id', $registrationId)
            ->where(function ($q) {
                $q->where('participant_user_id', Auth::id())
                  ->orWhere('booker_user_id', Auth::id());
            })
            ->firstOrFail();

        if (! $registration->kegiatan || ! $registration->kegiatan->isSubmissionOpen()) {
            return back()->with('error', 'Batas waktu pengumpulan karya telah berakhir atau pengumpulan karya tidak dibuka.');
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'submission_file' => ['required', 'file', 'max:20480'],
        ]);

        $path = $request->file('submission_file')->store('kegiatan/submissions', 'public');
        $fileUrl = Storage::url($path);

        \App\Models\KegiatanSubmission::updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'kegiatan_id' => $registration->kegiatan_id,
                'title' => trim($request->input('title')),
                'description' => $request->input('description'),
                'file_url' => $fileUrl,
                'status' => 'submitted',
            ]
        );

        return back()->with('success', 'Karya berhasil diunggah dan siap dinilai oleh juri.');
    }

    /**
     * Cancel Registration.
     */
    public function cancelKegiatanSaya(int $id): RedirectResponse
    {
        $registration = ActivityRegistration::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $registration->delete();

        return back()->with('success', 'Pendaftaran kegiatan berhasil dibatalkan.');
    }

    /**
     * Certificates.
     */
    public function sertifikat(): View
    {
        $certificates = Certificate::with('activity')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('dashboard.sertifikat', compact('certificates'));
    }

    /**
     * Attendances Log & Form.
     */
    public function absensi(): View
    {
        $user = Auth::user();
        $attendances = Attendance::where('user_id', $user->id)->latest()->get();
        $activities = Activity::where('is_closed', false)->latest()->get();

        return view('dashboard.absensi', compact('attendances', 'activities', 'user'));
    }

    /**
     * Submit Attendance from student dashboard.
     */
    public function storeAbsensi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'activity_id' => ['required', 'exists:activities,id'],
            'proof_image' => ['required', 'image', 'max:5120'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $activity = Activity::findOrFail($validated['activity_id']);
        $user = Auth::user();

        $path = $request->file('proof_image')->store('attendances', 'public');
        $imageUrl = Storage::url($path);

        Attendance::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'activity_title' => $activity->title,
            'activity_category' => $activity->category,
            'activity_date' => $activity->date_text,
            'student_name' => $user->name,
            'student_nim' => $user->nim ?? '-',
            'university' => $user->university,
            'student_email' => $user->email,
            'student_phone' => $user->phone,
            'proof_image_url' => $imageUrl,
            'proof_file_name' => $request->file('proof_image')->getClientOriginalName(),
            'status' => 'Hadir',
            'notes' => $validated['notes'] ?? 'Presensi divalidasi via Portal Dashboard Mahasiswa.',
            'location_name' => $validated['location_name'] ?? 'Kampus B UNUSA',
            'device_info' => $request->userAgent(),
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Presensi berhasil dicatat.');
    }

    /**
     * Pencairan KIP-K.
     */
    public function pencairan(): View
    {
        $form = CustomForm::with(['sections.questions'])->where('form_key', 'pencairan')->first();
        $submissions = CustomFormSubmission::where('custom_form_id', $form?->id)
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('dashboard.pencairan', compact('form', 'submissions'));
    }

    public function storePencairan(Request $request): RedirectResponse
    {
        $form = CustomForm::where('form_key', 'pencairan')->firstOrFail();
        $user = Auth::user();

        $answers = $request->except(['_token']);

        CustomFormSubmission::create([
            'custom_form_id' => $form->id,
            'user_id' => $user->id,
            'applicant_name' => $user->name,
            'applicant_nim' => $user->nim,
            'applicant_email' => $user->email,
            'answers' => $answers,
            'status' => 'Menunggu Review',
        ]);

        return back()->with('success', 'Formulir pengajuan pencairan berhasil dikirim ke database!');
    }

    /**
     * Pelaporan KIP-K.
     */
    public function pelaporan(): View
    {
        $form = CustomForm::with(['sections.questions'])->where('form_key', 'pelaporan')->first();
        $submissions = CustomFormSubmission::where('custom_form_id', $form?->id)
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('dashboard.pelaporan', compact('form', 'submissions'));
    }

    public function storePelaporan(Request $request): RedirectResponse
    {
        $form = CustomForm::where('form_key', 'pelaporan')->firstOrFail();
        $user = Auth::user();

        $answers = $request->except(['_token']);

        CustomFormSubmission::create([
            'custom_form_id' => $form->id,
            'user_id' => $user->id,
            'applicant_name' => $user->name,
            'applicant_nim' => $user->nim,
            'applicant_email' => $user->email,
            'answers' => $answers,
            'status' => 'Menunggu Review',
        ]);

        return back()->with('success', 'Laporan keaktifan berhasil dikirim!');
    }

    /**
     * Monev Akademik.
     */
    public function monev(): View
    {
        $form = CustomForm::with(['sections.questions'])->where('form_key', 'monev')->first();
        $submissions = CustomFormSubmission::where('custom_form_id', $form?->id)
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('dashboard.monev', compact('form', 'submissions'));
    }

    public function storeMonev(Request $request): RedirectResponse
    {
        $form = CustomForm::where('form_key', 'monev')->firstOrFail();
        $user = Auth::user();

        $answers = $request->except(['_token']);

        CustomFormSubmission::create([
            'custom_form_id' => $form->id,
            'user_id' => $user->id,
            'applicant_name' => $user->name,
            'applicant_nim' => $user->nim,
            'applicant_email' => $user->email,
            'answers' => $answers,
            'status' => 'Menunggu Review',
        ]);

        return back()->with('success', 'Data evaluasi IPK & sosial ekonomi berhasil dikirim!');
    }

    /**
     * Dokumen KIP-K Repository.
     */
    public function berkas(): View
    {
        $user = Auth::user();
        $documents = KipkDocument::where('user_id', $user->id)->latest()->get();

        return view('dashboard.berkas', compact('user', 'documents'));
    }

    public function uploadBerkas(Request $request): RedirectResponse
    {
        $request->validate([
            'category' => ['required', 'string'],
            'tahap' => ['required', 'integer', 'in:1,2,3'],
            'title' => ['required', 'string', 'max:255'],
            'document_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $user = Auth::user();
        $file = $request->file('document_file');
        $path = $file->store('kipk_documents', 'public');
        $fileUrl = Storage::url($path);

        KipkDocument::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_nim' => $user->nim,
            'category' => $request->input('category'),
            'tahap' => (int) $request->input('tahap'),
            'title' => trim($request->input('title')),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => round($file->getSize() / (1024 * 1024), 2).' MB',
            'file_type' => $file->getClientOriginalExtension(),
            'file_url' => $fileUrl,
            'uploaded_by' => 'Mahasiswa',
            'status' => 'Menunggu Review',
            'score' => 0,
        ]);

        return back()->with('success', 'Berkas KIP-K berhasil diunggah.');
    }

    /**
     * Scholarship Info list.
     */
    public function beasiswa(): View
    {
        $scholarships = Scholarship::latest()->paginate(9);
        return view('dashboard.beasiswa', compact('scholarships'));
    }

    /**
     * Info KIP guidelines & FAQ.
     */
    public function infoKip(): View
    {
        $infoList = KipInfo::latest()->get();
        return view('dashboard.info-kip', compact('infoList'));
    }

    /**
     * Profile & Security Settings.
     */
    public function profil(): View
    {
        $user = Auth::user();
        return view('dashboard.profil', compact('user'));
    }

    public function updateProfil(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'angkatan' => ['required', 'string', 'max:10'],
            'prodi' => ['required', 'string', 'max:100'],
            'university' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = Storage::url($path);
        }

        $user->name = trim($validated['name']);
        $user->phone = $validated['phone'] ? trim($validated['phone']) : null;
        $user->angkatan = trim($validated['angkatan']);
        $user->prodi = trim($validated['prodi']);
        $user->university = trim($validated['university']);
        $user->save();

        return back()->with('success', 'Profil mahasiswa berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
        ]);

        $user = Auth::user();
        $user->password = Hash::make($validated['password']);
        $user->save();

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
