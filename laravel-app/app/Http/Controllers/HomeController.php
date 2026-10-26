<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\BeritaAcara;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Support\Wib;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Landing page.
     */
    public function index(): View
    {
        if (Kegiatan::whereIn('status', [
            KegiatanStatus::Published,
            KegiatanStatus::Ongoing,
            KegiatanStatus::Completed,
        ])->exists()) {
            $kegiatanList = Kegiatan::whereIn('status', [
                KegiatanStatus::Published,
                KegiatanStatus::Ongoing,
                KegiatanStatus::Completed,
            ])
                ->latest('id')
                ->take(3)
                ->get();

            $activities = $kegiatanList->map(function (Kegiatan $k) {
                return (object) [
                    'id' => $k->id,
                    'title' => $k->nama_kegiatan,
                    'category' => ucfirst($k->kategori),
                    'date_text' => $k->tanggal_mulai ? Wib::dari($k->tanggal_mulai)->translatedFormat('d F Y') : '-',
                    'description' => $k->deskripsi,
                    'location' => $k->lokasi_nama ?: 'Kampus UNUSA',
                    'price' => (float) $k->biaya > 0 ? 'Rp '.number_format($k->biaya, 0, ',', '.') : 'Gratis',
                    'image_url' => null,
                    'is_kegiatan' => true,
                ];
            });
        } else {
            $activities = Activity::where('is_closed', false)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn(DB::raw('LOWER(status)'), ['draft', 'cancelled', 'dibatalkan']);
                })
                ->latest()
                ->take(3)
                ->get();
        }

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
     * Public quick attendance page.
     */
    public function absensiPublic(): View
    {
        $activities = Kegiatan::whereIn('status', [
            KegiatanStatus::Published,
            KegiatanStatus::Ongoing,
        ])
            ->latest('tanggal_mulai')
            ->get();

        return view('absensi-public', compact('activities'));
    }

    /**
     * Store public attendance.
     */
    public function storeAbsensiPublic(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'activity_id' => ['required'],
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

        $kegiatan = Kegiatan::find($validated['activity_id']);
        if (! $kegiatan) {
            $activity = Activity::find($validated['activity_id']);
            if ($activity) {
                $kegiatan = Kegiatan::where('legacy_activity_id', $activity->id)->first();
            }
        }

        if (! $kegiatan) {
            return back()->withInput()->withErrors(['activity_id' => 'Kegiatan tidak valid atau tidak ditemukan.']);
        }

        // Upload proof image securely
        $path = $request->file('proof_image')->store('attendances', 'public');
        $user = auth()->user();

        // Cari registrasi bila ada untuk mengupdate status_kehadiran
        $registration = null;
        if ($user) {
            $registration = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
                ->where(function ($q) use ($user) {
                    $q->where('participant_user_id', $user->id)
                        ->orWhere('booker_user_id', $user->id);
                })
                ->first();

            if ($registration && $registration->status_kehadiran !== AttendanceStatus::CheckedIn) {
                $registration->status_kehadiran = AttendanceStatus::CheckedIn;
                $registration->checked_in_at = now();
                $registration->save();
            }
        }

        KegiatanPresensi::create([
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user?->id,
            'registration_id' => $registration?->id,
            'nama_lengkap' => trim($validated['student_name']),
            'nim' => trim($validated['student_nim']),
            'prodi' => trim($validated['university']),
            'email' => $validated['student_email'] ? trim($validated['student_email']) : ($user?->email ?? 'peserta@semadiksi.id'),
            'nomor_telepon' => $validated['student_phone'] ? trim($validated['student_phone']) : '-',
            'signature_path' => $path,
            'check_in_at' => now(),
        ]);

        return back()->with('success', 'Presensi Anda berhasil dicatat dan diverifikasi di database sistem!');
    }
}
