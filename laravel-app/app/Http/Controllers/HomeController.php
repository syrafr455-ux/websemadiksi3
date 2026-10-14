<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\BeritaAcara;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Landing page.
     */
    public function index(): View
    {
        $activities = Activity::where('is_closed', false)
            ->latest()
            ->take(3)
            ->get();

        $beritaAcara = BeritaAcara::latest()
            ->take(3)
            ->get();

        return view('home', compact('activities', 'beritaAcara'));
    }

    /**
     * Profil SEMADIKSI organization page.
     */
    public function profil(): View
    {
        return view('profil-semadiksi');
    }

    /**
     * Berita Acara public list.
     */
    public function beritaAcaraIndex(Request $request): View
    {
        $query = BeritaAcara::query();

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ilike', "%{$q}%")
                  ->orWhere('summary', 'ilike', "%{$q}%")
                  ->orWhere('location', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $beritaAcaraList = $query->latest()->paginate(9)->withQueryString();

        return view('berita-acara.index', compact('beritaAcaraList'));
    }

    /**
     * Berita Acara detail.
     */
    public function beritaAcaraShow(int $id): View
    {
        $item = BeritaAcara::findOrFail($id);

        $related = BeritaAcara::where('id', '!=', $id)
            ->latest()
            ->take(3)
            ->get();

        return view('berita-acara.show', compact('item', 'related'));
    }

    /**
     * Public Certificate Verification.
     */
    public function verifikasiSertifikat(Request $request): View
    {
        $code = trim($request->input('code', ''));
        $certificate = null;

        if ($code) {
            $certificate = Certificate::where('code', $code)
                ->orWhere('code', 'ilike', $code)
                ->first();
        }

        return view('verifikasi-sertifikat', compact('code', 'certificate'));
    }

    /**
     * Public quick attendance page.
     */
    public function absensiPublic(): View
    {
        $activities = Activity::where('is_closed', false)
            ->latest()
            ->get();

        return view('absensi-public', compact('activities'));
    }

    /**
     * Store public attendance.
     */
    public function storeAbsensiPublic(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'activity_id' => ['required', 'exists:activities,id'],
            'student_name' => ['required', 'string', 'max:255'],
            'student_nim' => ['required', 'string', 'max:50'],
            'university' => ['required', 'string', 'max:255'],
            'student_email' => ['nullable', 'email', 'max:255'],
            'student_phone' => ['nullable', 'string', 'max:30'],
            'proof_image' => ['required', 'image', 'max:5120'], // Max 5MB
            'location_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'activity_id.required' => 'Pilih kegiatan yang dihadiri.',
            'student_name.required' => 'Nama lengkap wajib diisi.',
            'student_nim.required' => 'NIM wajib diisi.',
            'proof_image.required' => 'Foto bukti kehadiran / selfie wajib diunggah.',
            'proof_image.image' => 'File bukti harus berupa gambar (JPG/PNG/WebP).',
            'proof_image.max' => 'Ukuran file gambar maksimal 5MB.',
        ]);

        $activity = Activity::findOrFail($validated['activity_id']);

        // Upload proof image securely
        $path = $request->file('proof_image')->store('attendances', 'public');
        $imageUrl = Storage::url($path);

        Attendance::create([
            'user_id' => auth()->id(),
            'activity_id' => $activity->id,
            'activity_title' => $activity->title,
            'activity_category' => $activity->category,
            'activity_date' => $activity->date_text,
            'student_name' => trim($validated['student_name']),
            'student_nim' => trim($validated['student_nim']),
            'university' => trim($validated['university']),
            'student_email' => $validated['student_email'] ? trim($validated['student_email']) : null,
            'student_phone' => $validated['student_phone'] ? trim($validated['student_phone']) : null,
            'proof_image_url' => $imageUrl,
            'proof_file_name' => $request->file('proof_image')->getClientOriginalName(),
            'status' => 'Hadir',
            'notes' => $validated['notes'] ? trim($validated['notes']) : 'Presensi mandiri tersimpan di PostgreSQL.',
            'location_name' => $validated['location_name'] ?? 'Kampus B UNUSA (Web Presensi)',
            'device_info' => $request->userAgent(),
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Presensi Anda berhasil dicatat dan diverifikasi di database sistem!');
    }
}
