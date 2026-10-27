<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\StatusSertifikat;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityRegistration;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSubmission;
use App\Models\KipInfo;
use App\Models\KipkDocument;
use App\Models\PenerimaKipk;
use App\Models\Scholarship;
use App\Models\Sertifikat;
use App\Support\Wib;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Student Dashboard Summary.
     */
    public function index(): View
    {
        $user = Auth::user();

        // 1. Registrasi kegiatan (kegiatan baru + arsip lama)
        $newRegistrationsCount = KegiatanRegistration::where(function ($q) use ($user) {
            $q->where('participant_user_id', $user->id)
                ->orWhere('booker_user_id', $user->id);
        })->active()->count();
        $registrationsCount = $newRegistrationsCount + ActivityRegistration::where('user_id', $user->id)->count();

        // 2. Presensi kegiatan
        $newAttendancesCount = KegiatanPresensi::where(function ($q) use ($user) {
            if ($user->nim) {
                $q->where('nim', $user->nim);
            }
            if ($user->email) {
                $q->orWhere('email', $user->email);
            }
        })->count();
        $attendancesCount = $newAttendancesCount + Attendance::where('user_id', $user->id)->count();

        // 3. Sertifikat resmi
        $newCertificatesCount = Sertifikat::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereHas('presensi', function ($pq) use ($user) {
                    if ($user->nim) {
                        $pq->where('nim', $user->nim);
                    }
                    if ($user->email) {
                        $pq->orWhere('email', $user->email);
                    }
                });
        })->count();
        $certificatesCount = $newCertificatesCount + Certificate::where('user_id', $user->id)->count();

        $documentsCount = KipkDocument::where('user_id', $user->id)->count();

        // 4. Calculate XP
        $registeredKegiatanIds = KegiatanRegistration::where(function ($q) use ($user) {
            $q->where('participant_user_id', $user->id)
                ->orWhere('booker_user_id', $user->id);
        })->active()->pluck('kegiatan_id');
        $totalXp = (int) Kegiatan::whereIn('id', $registeredKegiatanIds)->sum('poin_xp');
        $oldRegActivityIds = ActivityRegistration::where('user_id', $user->id)->pluck('activity_id');
        $totalXp += (int) Activity::whereIn('id', $oldRegActivityIds)->sum('xp_points');

        // 5. Agenda mendatang (Kegiatan baru sesuai sasaran_peserta pengguna)
        $kegiatanQuery = Kegiatan::query()
            ->whereIn('status', [
                KegiatanStatus::Published,
                KegiatanStatus::Ongoing,
            ]);

        if (! $user->isSuperAdmin()) {
            if ($user->role === UserRole::MahasiswaKipk) {
                if ($user->isVerified()) {
                    $kegiatanQuery->whereIn('sasaran_peserta', ['kipk_only', 'internal_unusa', 'umum']);
                } else {
                    $kegiatanQuery->whereIn('sasaran_peserta', ['internal_unusa', 'umum']);
                }
            } elseif ($user->role === UserRole::UserUmum) {
                if ($user->hasUnusaEmail() || $user->is_unusa) {
                    $kegiatanQuery->whereIn('sasaran_peserta', ['internal_unusa', 'umum']);
                } else {
                    $kegiatanQuery->where('sasaran_peserta', 'umum');
                }
            } else {
                $kegiatanQuery->where('sasaran_peserta', 'umum');
            }
        }

        $upcomingKegiatan = $kegiatanQuery->orderBy('tanggal_mulai', 'asc')->take(4)->get();

        $upcomingActivities = $upcomingKegiatan->map(function (Kegiatan $k) {
            return (object) [
                'id' => $k->id,
                'title' => $k->nama_kegiatan,
                'category' => ucfirst($k->kategori),
                'date_text' => $k->tanggal_mulai ? Wib::dari($k->tanggal_mulai)->translatedFormat('d F Y') : '-',
                'price' => (float) $k->biaya > 0 ? 'Rp '.number_format($k->biaya, 0, ',', '.') : 'Gratis',
                'is_kegiatan' => true,
            ];
        });

        if ($upcomingActivities->isEmpty()) {
            $upcomingActivities = Activity::where('is_closed', false)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn(DB::raw('LOWER(status)'), ['draft', 'cancelled', 'dibatalkan']);
                })
                ->latest()
                ->take(4)
                ->get()
                ->map(fn ($act) => (object) [
                    'id' => $act->id,
                    'title' => $act->title,
                    'category' => $act->category,
                    'date_text' => $act->date_text,
                    'price' => $act->price,
                    'is_kegiatan' => false,
                ]);
        }

        // 6. My Recent Activities
        $newRecent = KegiatanRegistration::with(['kegiatan', 'seat'])
            ->where(function ($q) use ($user) {
                $q->where('participant_user_id', $user->id)
                    ->orWhere('booker_user_id', $user->id);
            })
            ->latest('id')
            ->take(4)
            ->get()
            ->map(function (KegiatanRegistration $reg) {
                return (object) [
                    'id' => $reg->id,
                    'title' => $reg->kegiatan?->nama_kegiatan ?? 'Kegiatan',
                    'date_text' => $reg->kegiatan?->tanggal_mulai ? Wib::dari($reg->kegiatan->tanggal_mulai)->translatedFormat('d F Y') : '-',
                    'seat' => $reg->seat?->seat_code ?? ($reg->needs_seat_reselection ? 'Perlu pilih ulang' : 'Reguler'),
                    'status' => $reg->status_pembayaran?->label() ?? 'Terdaftar',
                    'is_kegiatan' => true,
                ];
            });

        if ($newRecent->isNotEmpty()) {
            $myRecentActivities = $newRecent;
        } else {
            $myRecentActivities = ActivityRegistration::with('activity')
                ->where('user_id', $user->id)
                ->latest()
                ->take(4)
                ->get()
                ->map(function ($reg) {
                    return (object) [
                        'id' => $reg->id,
                        'title' => $reg->activity?->title ?? 'Kegiatan',
                        'date_text' => $reg->activity?->date_text ?? '-',
                        'seat' => $reg->selected_seat ?? 'Reguler',
                        'status' => $reg->status,
                        'is_kegiatan' => false,
                    ];
                });
        }

        // Status KIP-K: baris penerima yang terhubung (nomor hanya tampil tersamar) & klaim yang sedang ditinjau.
        $penerimaKipk = $user->penerimaKipk;
        $klaimDitinjau = $user->isUmumUnusa()
            && PenerimaKipk::where('klaim_user_id', $user->id)->where('status_tinjau', PenerimaKipk::TINJAU_PERLU)->exists();

        return view('dashboard.index', compact(
            'user',
            'registrationsCount',
            'attendancesCount',
            'certificatesCount',
            'documentsCount',
            'totalXp',
            'upcomingActivities',
            'myRecentActivities',
            'penerimaKipk',
            'klaimDitinjau'
        ));
    }

    /**
     * Explore Activities.
     */
    public function kegiatan(Request $request): View
    {
        $user = Auth::user();
        $query = Kegiatan::query()
            ->whereIn('status', [
                KegiatanStatus::Published,
                KegiatanStatus::Ongoing,
            ]);

        // Filter sasaran_peserta berdasarkan hak akses role peserta
        if ($user && ! $user->isSuperAdmin()) {
            if ($user->role === UserRole::MahasiswaKipk) {
                if ($user->isVerified()) {
                    $query->whereIn('sasaran_peserta', ['kipk_only', 'internal_unusa', 'umum']);
                } else {
                    $query->whereIn('sasaran_peserta', ['internal_unusa', 'umum']);
                }
            } elseif ($user->role === UserRole::UserUmum) {
                if ($user->hasUnusaEmail() || $user->is_unusa) {
                    $query->whereIn('sasaran_peserta', ['internal_unusa', 'umum']);
                } else {
                    $query->where('sasaran_peserta', 'umum');
                }
            } else {
                $query->where('sasaran_peserta', 'umum');
            }
        }

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($w) use ($q) {
                $w->where('nama_kegiatan', 'like', "%{$q}%")
                    ->orWhere('deskripsi', 'like', "%{$q}%")
                    ->orWhere('lokasi_nama', 'like', "%{$q}%");
            });
        }

        if ($request->filled('category')) {
            $category = strtolower(trim($request->input('category')));
            $query->where('kategori', $category);
        }

        $kegiatanPaginator = $query->orderBy('tanggal_mulai', 'asc')->paginate(9)->withQueryString();

        $myRegisteredIds = [];
        if ($user) {
            $myRegisteredIds = KegiatanRegistration::where(function ($q) use ($user) {
                $q->where('participant_user_id', $user->id)
                    ->orWhere('booker_user_id', $user->id);
            })
                ->active()
                ->pluck('kegiatan_id')
                ->unique()
                ->toArray();
        }

        $activities = $kegiatanPaginator->through(function (Kegiatan $k) {
            return (object) [
                'id' => $k->id,
                'title' => $k->nama_kegiatan,
                'category' => ucfirst($k->kategori),
                'type' => ucfirst($k->metode_pelaksanaan),
                'date_text' => $k->tanggal_mulai ? Wib::dari($k->tanggal_mulai)->translatedFormat('d F Y') : '-',
                'description' => $k->deskripsi,
                'location' => $k->lokasi_nama ?: 'Kampus UNUSA',
                'quota' => $k->kuota,
                'xp_points' => $k->poin_xp,
                'price' => (float) $k->biaya > 0 ? 'Rp '.number_format($k->biaya, 0, ',', '.') : 'Gratis',
                'image_url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&auto=format&fit=crop&q=80',
                'is_kegiatan' => true,
                'raw' => $k,
            ];
        });

        return view('dashboard.kegiatan', compact('activities', 'myRegisteredIds'));
    }

    /**
     * Activity Detail & Registration Modal/Page (dialihkan langsung ke alur kanonikal).
     */
    public function kegiatanShow(int $id): RedirectResponse
    {
        return redirect()->route('kegiatan.detail', $id);
    }

    /**
     * Join / Register Activity (dialihkan langsung ke alur pendaftaran kegiatan).
     */
    public function registerKegiatan(int $id, Request $request): RedirectResponse
    {
        return redirect()->route('kegiatan.detail', $id);
    }

    /**
     * My Registered Activities.
     */
    public function kegiatanSaya(Request $request)
    {
        $userId = Auth::id();

        $enterpriseRegistrations = KegiatanRegistration::with(['kegiatan', 'seat', 'payment', 'submission', 'gabung'])
            ->where(function ($q) use ($userId) {
                $q->where('participant_user_id', $userId)
                    ->orWhere('booker_user_id', $userId);
            })
            ->latest('id')
            ->get()
            ->each(function ($reg) use ($userId) {
                // Penanda untuk kartu tiket: status bayar (nilai enum), perlu pilih ulang kursi, dan siapa yang boleh memilih.
                $reg->append('payment_status');
                $reg->setAttribute('perlu_pilih_ulang', (bool) $reg->needs_seat_reselection);
                $reg->setAttribute('bisa_pilih_ulang', (bool) $reg->needs_seat_reselection && (int) $reg->booker_user_id === (int) $userId);

                $kegiatan = $reg->kegiatan;
                if ($kegiatan && ($kegiatan->metode_pelaksanaan === 'online' || $kegiatan->metode_pelaksanaan === 'hybrid' || $kegiatan->lokasi_type === 'online')) {
                    $statusAkses = $kegiatan->statusAksesGabung();
                    $waktuBukaWib = null;
                    if ($statusAkses === 'belum_dibuka' && $kegiatan->tanggal_mulai) {
                        $menit = (int) ($kegiatan->menit_buka_gabung ?? 15);
                        $waktuBukaUtc = $kegiatan->tanggal_mulai->copy()->subMinutes($menit);
                        $waktuBukaWib = Wib::format($waktuBukaUtc);
                    }

                    $reg->setAttribute('status_akses_gabung', $statusAkses);
                    $reg->setAttribute('waktu_buka_gabung_wib', $waktuBukaWib);
                    $reg->setAttribute('sudah_gabung', (bool) $reg->gabung);
                    $reg->setAttribute('pertama_gabung_pada', $reg->gabung?->pertama_pada ? Wib::format($reg->gabung->pertama_pada) : null);
                    $reg->setAttribute('jumlah_gabung', (int) ($reg->gabung?->jumlah ?? 0));
                }

                // Status Sertifikat
                $sertifikat = Sertifikat::where('kegiatan_id', $reg->kegiatan_id)
                    ->where(function ($q) use ($userId, $reg) {
                        $q->where('user_id', $userId);
                        if ($reg->id) {
                            $q->orWhere('registration_id', $reg->id);
                        }
                    })
                    ->where('status', StatusSertifikat::Aktif)
                    ->latest('id')
                    ->first();

                if ($sertifikat) {
                    $reg->setAttribute('sertifikat_status', 'terbit');
                    $reg->setAttribute('sertifikat_url', $sertifikat->file_url);
                    $reg->setAttribute('sertifikat_nomor', $sertifikat->nomor_sertifikat);
                    $reg->setAttribute('sertifikat_verification_url', url('/verifikasi?code='.$sertifikat->nomor_sertifikat));
                } else {
                    $hasAttended = KegiatanPresensi::where('kegiatan_id', $reg->kegiatan_id)
                        ->where(function ($q) use ($userId, $reg) {
                            $q->where('user_id', $userId);
                            if ($reg->id) {
                                $q->orWhere('registration_id', $reg->id);
                            }
                        })
                        ->exists();

                    if ($hasAttended || $reg->status_kehadiran === AttendanceStatus::CheckedIn) {
                        $reg->setAttribute('sertifikat_status', 'menunggu_verifikasi');
                        $reg->setAttribute('sertifikat_pesan', 'Menunggu verifikasi panitia');
                    } else {
                        $reg->setAttribute('sertifikat_status', 'belum_tersedia');
                        $reg->setAttribute('sertifikat_pesan', 'Belum presensi');
                    }
                }
            });

        $registrations = ActivityRegistration::with('activity')
            ->where('user_id', $userId)
            ->latest()
            ->paginate(10);

        if (class_exists(Inertia::class)) {
            return Inertia::render('Dashboard/KegiatanSaya', [
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
        $registration = KegiatanRegistration::with('kegiatan')
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

        KegiatanSubmission::updateOrCreate(
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
        $userId = Auth::id();
        $user = Auth::user();

        // Ambil dari tabel sertifikat alur baru
        $sertifikats = Sertifikat::with('kegiatan')
            ->where(function ($q) use ($userId, $user) {
                $q->where('user_id', $userId);
                if ($user?->email) {
                    $q->orWhere('email_penerima', $user->email);
                }
                if ($user?->nim) {
                    $q->orWhere('nim_penerima', $user->nim);
                }
            })
            ->latest('id')
            ->get();

        // Ambil dari tabel legacy jika ada
        $legacyCerts = Certificate::with('activity')
            ->where('user_id', $userId)
            ->latest('id')
            ->get();

        $mergedCertificates = collect();

        foreach ($sertifikats as $st) {
            $mergedCertificates->push((object) [
                'id' => $st->id,
                'code' => $st->nomor_sertifikat,
                'title' => $st->kegiatan->nama_kegiatan ?? 'Sertifikat Kegiatan SEMADIKSI',
                'recipient_name' => $st->nama_penerima ?: ($st->recipient_name ?: ($user?->name ?? 'Peserta')),
                'category' => $st->kegiatan->kategori ?? 'Kegiatan',
                'organizer' => 'SEMADIKSI Panitia Pelaksana',
                'date' => Carbon::parse($st->issued_at)->translatedFormat('d F Y'),
                'duration' => '8 JP (Jam Pelajaran)',
                'status' => $st->status === StatusSertifikat::Dicabut ? 'Dicabut' : 'Aktif',
                'is_active' => $st->status === StatusSertifikat::Aktif,
                'jenis' => $st->jenis?->value ?? 'peserta',
                'jenis_label' => $st->jenis?->label() ?? 'Peserta',
                'predikat' => $st->predikat,
                'document_hash' => $st->document_hash,
                'file_url' => $st->isAktif() ? route('sertifikat.download', $st->id) : null,
                'verification_url' => url('/verifikasi?code='.$st->nomor_sertifikat),
                'is_legacy' => false,
            ]);
        }

        foreach ($legacyCerts as $lc) {
            $mergedCertificates->push((object) [
                'id' => $lc->id,
                'code' => $lc->code,
                'title' => $lc->activity_title,
                'recipient_name' => $lc->user_name,
                'category' => $lc->activity->category ?? 'Kegiatan',
                'organizer' => 'SEMADIKSI Divisi Kemahasiswaan',
                'date' => Carbon::parse($lc->issued_date)->translatedFormat('d F Y'),
                'duration' => '8 JP',
                'status' => 'Aktif',
                'is_active' => true,
                'jenis' => 'peserta',
                'jenis_label' => 'Peserta',
                'predikat' => null,
                'document_hash' => hash('sha256', $lc->code.$lc->user_name),
                'file_url' => null,
                'verification_url' => url('/verifikasi?code='.$lc->code),
                'is_legacy' => true,
            ]);
        }

        $groupedCertificates = $mergedCertificates->groupBy('jenis_label');

        return view('dashboard.sertifikat', [
            'certificates' => $mergedCertificates,
            'groupedCertificates' => $groupedCertificates,
            'user' => $user,
        ]);
    }

    /**
     * Attendances Log & Form (Read-only riwayat kehadiran alur 2-tahap).
     */
    public function absensi(): View
    {
        $user = Auth::user();

        $newPresensis = KegiatanPresensi::with('kegiatan')
            ->where(function ($q) use ($user) {
                if ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('email', $user->email);
                    if ($user->nim) {
                        $q->orWhere('nim', $user->nim);
                    }
                }
            })
            ->latest('check_in_at')
            ->get()
            ->map(function ($kp) {
                return (object) [
                    'id' => $kp->id,
                    'activity_title' => $kp->kegiatan?->nama_kegiatan ?? 'Kegiatan',
                    'submitted_at' => $kp->check_in_at,
                    'location_name' => $kp->kegiatan?->lokasi_nama ?? 'Kampus B UNUSA',
                    'proof_image_url' => $kp->signature_url,
                    'status' => 'Hadir',
                    'sumber' => 'Presensi Resmi',
                    'is_legacy' => false,
                ];
            });

        $legacyAttendances = Attendance::where('user_id', $user?->id)
            ->latest('submitted_at')
            ->get()
            ->map(function ($att) {
                return (object) [
                    'id' => $att->id,
                    'activity_title' => $att->activity_title,
                    'submitted_at' => $att->submitted_at,
                    'location_name' => $att->location_name ?? 'Kampus B UNUSA',
                    'proof_image_url' => $att->proof_image_url,
                    'status' => $att->status ?? 'Hadir',
                    'sumber' => 'Legacy',
                    'is_legacy' => true,
                ];
            });

        $attendances = $newPresensis->concat($legacyAttendances);

        return view('dashboard.absensi', compact('attendances', 'user'));
    }

    /**
     * Submit Attendance from student dashboard (DIPENSIUNKAN).
     * Formulir swafoto mandiri tidak lagi menulis ke kegiatan_presensi atau mengubah status kehadiran.
     */
    public function storeAbsensi(Request $request): RedirectResponse
    {
        $msg = 'Formulir presensi swafoto mandiri telah dipensiunkan. Kehadiran resmi dicatat melalui alur 2 tahap: Check-in Tiket/Gabung Acara dan Presensi Akhir QR saat kegiatan berlangsung.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 422);
        }

        return redirect()->route('dashboard.absensi')->with('info', $msg);
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
        Gate::authorize('create', KipkDocument::class);

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

    public function downloadBerkas(int $id)
    {
        $doc = KipkDocument::findOrFail($id);
        Gate::authorize('download', $doc);

        $relativePath = $doc->file_url ? str_replace('/storage/', '', parse_url($doc->file_url, PHP_URL_PATH)) : null;
        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->download($relativePath, $doc->file_name ?? 'berkas-kipk.pdf');
        }

        if ($doc->file_url) {
            return redirect($doc->file_url);
        }

        return back()->with('error', 'File berkas tidak ditemukan.');
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
