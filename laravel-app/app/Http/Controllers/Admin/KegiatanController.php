<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Events\KegiatanUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Mail\KegiatanDiajukanMail;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanPersetujuan;
use App\Models\KegiatanRegistration;
use App\Models\Sertifikat;
use App\Models\User;
use App\Rules\ValidMeetingUrl;
use App\Services\EmailProtectionService;
use App\Services\Kegiatan\DenahKursiService;
use App\Services\Kegiatan\PetaKursiService;
use App\Services\UnusaFacilityService;
use App\Support\AksiPersetujuan;
use App\Support\Wib;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanController extends Controller
{
    public function __construct(
        protected UnusaFacilityService $facilityService,
        protected DenahKursiService $denah
    ) {}

    /**
     * Tampilkan daftar kegiatan enterprise di panel admin.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Kegiatan::class);

        $query = Kegiatan::query()
            ->with(['persetujuan.user:id,name,email', 'createdBy:id,name,email'])
            ->withCount(['seats', 'registrations', 'submissions'])
            ->latest('id');

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($w) use ($q) {
                $w->where('nama_kegiatan', 'ilike', "%{$q}%")
                    ->orWhere('kategori', 'ilike', "%{$q}%")
                    ->orWhere('narasumber', 'ilike', "%{$q}%")
                    ->orWhere('lokasi_nama', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->input('kategori'));
        }

        $kegiatans = $query->paginate(12)->withQueryString();
        $unusaFacilities = $this->facilityService->listCampuses();

        $currentUser = auth()->user();
        $currentUserId = $currentUser?->id;
        $isSuperAdmin = $currentUser?->isSuperAdmin() ?? false;

        $adaSuperAdminLain = User::where('role', UserRole::SuperAdmin)
            ->where('id', '!=', $currentUserId)
            ->where('is_blocked', false)
            ->exists();

        $menungguPersetujuanAnda = 0;
        if ($isSuperAdmin) {
            $menungguPersetujuanAnda = Kegiatan::query()
                ->where('status', KegiatanStatus::Diajukan)
                ->where(function ($q) use ($currentUserId) {
                    $q->whereNull('created_by')->orWhere('created_by', '!=', $currentUserId);
                })
                ->whereDoesntHave('persetujuan', function ($q) use ($currentUserId) {
                    $q->where('user_id', $currentUserId);
                })
                ->count();
        }

        $kegiatans->through(fn (Kegiatan $item) => $this->barisDaftar($item, $currentUser));

        // Dibuka dari tombol "Edit Kegiatan" di halaman detail: kegiatannya belum tentu ada di halaman daftar ini.
        $kegiatanEdit = null;
        if ($request->filled('edit')) {
            $untukEdit = Kegiatan::query()
                ->with(['persetujuan.user:id,name,email', 'createdBy:id,name,email'])
                ->withCount(['seats', 'registrations', 'submissions'])
                ->find((int) $request->input('edit'));
            $kegiatanEdit = $untukEdit ? $this->barisDaftar($untukEdit, $currentUser) : null;
        }

        return Inertia::render('Admin/Kegiatan/Index', [
            'title' => 'Manajemen Kegiatan Enterprise',
            'kegiatans' => $kegiatans,
            'filters' => $request->only(['q', 'status', 'kategori']),
            'unusaFacilities' => $unusaFacilities,
            'statusOptions' => KegiatanStatus::cases(),
            'menungguPersetujuanAnda' => $menungguPersetujuanAnda,
            'adaSuperAdminLain' => $adaSuperAdminLain,
            'kegiatanEdit' => $kegiatanEdit,
            'izinSertifikat' => Gate::allows('viewAny', Sertifikat::class),
        ]);
    }

    /**
     * Satu baris daftar kegiatan: data form edit, status persetujuan, dan tombol alur persetujuan.
     * Tautan rapat, meeting ID, dan passcode tidak pernah ikut; form edit hanya menerima penanda "sudah diisi".
     *
     * @return array<string, mixed>
     */
    protected function barisDaftar(Kegiatan $item, ?User $pengguna): array
    {
        $penyetujuWajib = $item->penyetujuWajib();
        $totalPenyetuju = $penyetujuWajib->count();
        $jumlahSetuju = $item->persetujuan->where('keputusan', 'setuju')->count();

        $statusTeks = match ($item->status) {
            KegiatanStatus::Draft => 'Draft',
            KegiatanStatus::Diajukan => "Menunggu persetujuan {$jumlahSetuju} dari {$totalPenyetuju}",
            KegiatanStatus::Published => 'Terbit',
            KegiatanStatus::Archived => 'Diarsipkan',
            KegiatanStatus::Cancelled => 'Dibatalkan',
            KegiatanStatus::Ongoing => 'Berlangsung',
            KegiatanStatus::Completed => 'Selesai',
            default => $item->status?->label() ?? 'Draft',
        };

        if ($item->status === KegiatanStatus::Draft && ! empty($item->catatan_penolakan)) {
            $statusTeks = 'Ditolak: '.Str::limit($item->catatan_penolakan, 40);
        }

        return [
            ...$item->toArray(),
            'status_teks' => $statusTeks,
            'catatan_penolakan' => $item->catatan_penolakan,
            'jumlah_setuju' => $jumlahSetuju,
            'total_penyetuju' => $totalPenyetuju,
            'creator_name' => $item->createdBy?->name,
            'tautan_rapat_tersimpan' => filled($item->tautan_rapat),
            'meeting_id_tersimpan' => filled($item->meeting_id),
            'passcode_rapat_tersimpan' => filled($item->passcode_rapat),
            ...AksiPersetujuan::untuk($item, $pengguna),
        ];
    }

    /**
     * Simpan kegiatan baru beserta denah kursi dan dokumen panduan.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Kegiatan::class);

        $this->normalizeKegiatanInput($request);
        $rules = $this->kegiatanRules();
        $rules['aksi_submit'] = ['nullable', 'string', 'in:draft,ajukan'];
        $validated = $request->validate($rules, $this->kegiatanMessages());

        $aksiSubmit = $validated['aksi_submit'] ?? null;
        $lokasiType = $validated['lokasi_type'];
        $metode = $validated['metode_pelaksanaan'] ?? ($lokasiType === 'online' ? 'online' : 'offline');
        $isOnlineOrHybrid = ($lokasiType === 'online' || in_array($metode, ['online', 'hybrid'], true));

        if ($aksiSubmit) {
            $statusKegiatan = $aksiSubmit === 'ajukan' ? KegiatanStatus::Diajukan : KegiatanStatus::Draft;
        } elseif (isset($validated['status'])) {
            $statusKegiatan = KegiatanStatus::tryFrom($validated['status']) ?? KegiatanStatus::Draft;
        } else {
            $statusKegiatan = KegiatanStatus::Published;
        }

        if ($statusKegiatan === KegiatanStatus::Diajukan) {
            $adaSuperAdminLain = User::where('role', UserRole::SuperAdmin)
                ->where('id', '!=', auth()->id())
                ->where('is_blocked', false)
                ->exists();

            if (! $adaSuperAdminLain) {
                throw ValidationException::withMessages([
                    'aksi_submit' => 'Tidak dapat mengajukan persetujuan karena tidak ada Super Administrator aktif lain dalam sistem.',
                ]);
            }

            if ($isOnlineOrHybrid && empty($validated['tautan_rapat'])) {
                throw ValidationException::withMessages([
                    'tautan_rapat' => 'Tautan rapat daring wajib diisi sebelum kegiatan dapat diajukan.',
                ]);
            }
        }

        $tanggalMulaiUtc = Wib::keUtc($validated['tanggal_mulai']);
        $tanggalSelesaiUtc = ! empty($validated['tanggal_selesai'])
            ? Wib::keUtc($validated['tanggal_selesai'])
            : $tanggalMulaiUtc->copy()->addHours(2);
        $registrationDeadlineUtc = Wib::keUtc($validated['registration_deadline'] ?? null);

        if ($registrationDeadlineUtc && $registrationDeadlineUtc->gt($tanggalMulaiUtc)) {
            throw ValidationException::withMessages([
                'registration_deadline' => 'Batas pendaftaran tidak boleh melewati waktu mulai kegiatan.',
            ]);
        }

        $fasilitas = $this->facilityService->pilihanKampus($validated['lokasi_type'], $validated);

        $panduanPath = null;
        if ($request->hasFile('panduan_file')) {
            $panduanPath = $request->file('panduan_file')->store('kegiatan/panduan', 'public');
        }

        $certTemplatePath = null;
        if ($request->hasFile('certificate_template_file')) {
            $certTemplatePath = $request->file('certificate_template_file')->store('kegiatan/templates', 'public');
        }

        DB::beginTransaction();
        try {
            $seatBookingActive = $request->boolean('is_seat_booking_active');
            $seatLayout = $this->denah->normalisasi($validated['seat_layout_config'] ?? null);

            $kegiatan = Kegiatan::create([
                'created_by' => auth()->id(),
                'nama_kegiatan' => trim($validated['nama_kegiatan']),
                'deskripsi' => $validated['deskripsi'] ?? null,
                'kategori' => $validated['kategori'],
                'metode_pelaksanaan' => $metode,
                'sasaran_peserta' => $validated['sasaran_peserta'] ?? 'umum',
                // Input jadwal dianggap WIB, disimpan dalam UTC.
                'tanggal_mulai' => $tanggalMulaiUtc,
                'tanggal_selesai' => $tanggalSelesaiUtc,
                'registration_deadline' => $registrationDeadlineUtc,
                'submission_deadline' => Wib::keUtc($validated['submission_deadline'] ?? null),
                'lokasi_type' => $lokasiType,
                ...$fasilitas,
                'lokasi_nama' => trim($validated['lokasi_nama']),
                'lokasi_detail' => $validated['lokasi_detail'] ?? null,
                'biaya' => (float) $validated['biaya'],
                'kuota' => (int) $validated['kuota'],
                'narasumber' => $this->normalizeNarasumber($validated['narasumber'] ?? null),
                'poin_xp' => (int) ($validated['poin_xp'] ?? 0),
                'file_panduan_pdf' => $panduanPath ?? 'kegiatan/panduan/default_panduan.pdf',
                'certificate_template_path' => $certTemplatePath,
                'certificate_schema_config' => $validated['certificate_schema_config'] ?? null,
                'is_seat_booking_active' => $seatBookingActive,
                'is_submission_active' => $request->boolean('is_submission_active'),
                'submission_instructions' => $validated['submission_instructions'] ?? null,
                'status' => $statusKegiatan,
                'seat_layout_config' => $seatBookingActive ? $seatLayout : null,
                // Kolom daring
                'platform_daring' => $validated['platform_daring'] ?? 'zoom',
                'tautan_rapat' => $validated['tautan_rapat'] ?? null,
                'meeting_id' => $validated['meeting_id'] ?? null,
                'passcode_rapat' => $validated['passcode_rapat'] ?? null,
                'join_button_mode' => $validated['join_button_mode'] ?? 'otomatis',
                'menit_buka_gabung' => isset($validated['menit_buka_gabung']) ? (int) $validated['menit_buka_gabung'] : 15,
                'batas_tutup_gabung' => ! empty($validated['batas_tutup_gabung']) ? Wib::keUtc($validated['batas_tutup_gabung']) : null,
                'izin_tautan_presensi_akhir' => $request->boolean('izin_tautan_presensi_akhir'),
                'wajib_tangkapan_layar' => $request->boolean('wajib_tangkapan_layar'),
            ]);

            // Buat denah kursi interaktif jika booking kursi aktif
            if ($seatBookingActive) {
                $this->denah->buat($kegiatan, $seatLayout);
            }

            AuditLog::record(
                auth()->id(),
                'create_kegiatan',
                "Membuat kegiatan baru '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
                $kegiatan
            );

            // Jika langsung diajukan, kirim email notifikasi ke semua penyetuju wajib lewat antrean pengamanan
            if ($statusKegiatan === KegiatanStatus::Diajukan) {
                foreach ($kegiatan->penyetujuWajib() as $penyetuju) {
                    EmailProtectionService::send($penyetuju->email, new KegiatanDiajukanMail($kegiatan, $penyetuju));
                }
            }

            DB::commit();

            return redirect()->route('admin.kegiatan')->with('success', 'Kegiatan enterprise berhasil disimpan.');
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($e instanceof ValidationException) {
                throw $e;
            }

            return back()->with('error', 'Gagal membuat kegiatan: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Tampilkan detail atau data edit kegiatan.
     */
    public function show(int $id): Response
    {
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('update', $kegiatan);

        $pendaftar = $kegiatan->registrations()
            ->with(['participantUser:id,name,email,nim', 'bookerUser:id,name,email', 'seat:id,seat_code,tier'])
            ->orderByDesc('needs_seat_reselection')
            ->orderBy('id')
            ->get()
            ->map(fn (KegiatanRegistration $reg) => [
                'id' => $reg->id,
                'nama_peserta' => $reg->ticket_holder_name,
                'identitas_peserta' => $reg->ticket_holder_identity,
                'email_peserta' => $reg->participantUser?->email ?? ($reg->delegation_data['email'] ?? null),
                // Booker hanya ditampilkan bila tiket dipesankan orang lain (delegasi).
                'booker' => $reg->booker_user_id && $reg->booker_user_id !== $reg->participant_user_id && $reg->bookerUser
                    ? ['name' => $reg->bookerUser->name, 'email' => $reg->bookerUser->email]
                    : null,
                'nomor_tiket' => $reg->nomor_tiket,
                'kursi' => $reg->seat?->seat_code,
                'status_pembayaran' => $reg->status_pembayaran?->value,
                'status_pembayaran_label' => $reg->status_pembayaran?->label(),
                'status_kehadiran' => $reg->status_kehadiran?->value,
                'checked_in_at' => $reg->checked_in_at,
                'needs_seat_reselection' => (bool) $reg->needs_seat_reselection,
                'created_at' => $reg->created_at,
            ]);

        $aktif = $kegiatan->registrations()->active()->count();

        $penyetujuWajib = $kegiatan->penyetujuWajib();
        $persetujuan = $kegiatan->persetujuan()->with('user:id,name,email')->get();
        $currentUserId = auth()->id();

        $daftarPenyetuju = $penyetujuWajib->map(function ($admin) use ($persetujuan) {
            $suara = $persetujuan->firstWhere('user_id', $admin->id);

            return [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'status' => $suara ? $suara->keputusan : 'menunggu',
                'catatan' => $suara?->catatan,
                'waktu' => $suara?->created_at?->toISOString(),
            ];
        });

        $creatorName = $kegiatan->createdBy?->name ?? 'Pembuat';

        $pesanStatus = null;
        if ($kegiatan->status === KegiatanStatus::Draft) {
            $pesanStatus = "Belum diajukan oleh {$creatorName}";
        }

        return Inertia::render('Admin/Kegiatan/Show', [
            'title' => 'Detail Kegiatan',
            'kegiatan' => [
                ...$kegiatan->only([
                    'id', 'nama_kegiatan', 'kategori', 'metode_pelaksanaan', 'sasaran_peserta',
                    'tanggal_mulai', 'tanggal_selesai', 'registration_deadline', 'submission_deadline',
                    'lokasi_type', 'lokasi_kampus', 'lokasi_gedung', 'lokasi_ruangan', 'lokasi_nama', 'lokasi_detail',
                    'biaya', 'kuota', 'is_seat_booking_active', 'booking_opens_at', 'booking_closes_at',
                    'venue_change_count', 'is_submission_active', 'file_panduan_url',
                ]),
                'created_by' => $kegiatan->createdBy ? [
                    'id' => $kegiatan->createdBy->id,
                    'name' => $kegiatan->createdBy->name,
                    'email' => $kegiatan->createdBy->email,
                ] : null,
                'status' => $kegiatan->status?->value,
                'seat_booking_open' => $kegiatan->isSeatBookingOpen(),
                'status_booking' => $kegiatan->statusBookingKursi(),
                'registration_open' => $kegiatan->isRegistrationOpen(),
                'platform_daring' => $kegiatan->platform_daring,
                'join_button_mode' => $kegiatan->join_button_mode,
                'menit_buka_gabung' => $kegiatan->menit_buka_gabung,
                'batas_tutup_gabung' => $kegiatan->batas_tutup_gabung,
                'is_gabung_manual_open' => $kegiatan->is_gabung_manual_open,
                'izin_tautan_presensi_akhir' => (bool) $kegiatan->izin_tautan_presensi_akhir,
                'wajib_tangkapan_layar' => (bool) $kegiatan->wajib_tangkapan_layar,
                'status_akses_gabung' => $kegiatan->statusAksesGabung(),
                'is_online' => $kegiatan->isOnline(),
                'is_hybrid' => $kegiatan->isHybrid(),
                'is_daring_or_hybrid' => $kegiatan->isDaringOrHybrid(),
            ],
            'approval' => [
                'status' => $kegiatan->status?->value,
                'created_by' => $kegiatan->created_by,
                'creator_name' => $creatorName,
                'pesan_status' => $pesanStatus,
                'catatan_penolakan' => $kegiatan->catatan_penolakan,
                'is_creator' => $currentUserId === $kegiatan->created_by,
                ...AksiPersetujuan::untuk($kegiatan->load('persetujuan'), auth()->user()),
                'bisa_terbit' => $penyetujuWajib->isNotEmpty(),
                'penyetuju_wajib' => $daftarPenyetuju,
                'semua_setuju' => $penyetujuWajib->isNotEmpty() && $daftarPenyetuju->every(fn ($p) => $p['status'] === 'setuju'),
            ],
            // Tab "Denah & Booking": peta kursi beserta pemegangnya, denah tersimpan, dan pilihan fasilitas untuk pindah venue.
            'kursi' => app(PetaKursiService::class)->untukAdmin($kegiatan),
            'denah' => [
                ...$this->denah->normalisasi($kegiatan->seat_layout_config ?? []),
                'kapasitas' => $this->denah->kapasitas($kegiatan->seat_layout_config ?? []),
            ],
            'unusaFacilities' => $this->facilityService->listCampuses(),
            'ringkasan' => [
                'pendaftar_aktif' => $aktif,
                'pendaftar_total' => $pendaftar->count(),
                'kursi_total' => $kegiatan->seats()->count(),
                'kursi_terisi' => $kegiatan->seats()->where('status', SeatStatus::Booked->value)->count(),
                'perlu_pilih_ulang' => $pendaftar->where('needs_seat_reselection', true)
                    ->whereIn('status_pembayaran', RegistrationPaymentStatus::activeValues())->count(),
                'hadir' => $pendaftar->where('status_kehadiran', 'checked_in')->count(),
            ],
            'pendaftar' => $pendaftar->values(),
        ]);
    }

    /**
     * Perbarui data kegiatan, periksa perubahan jadwal/lokasi & lindungi kursi booked.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::with('seats')->findOrFail($id);
        Gate::authorize('update', $kegiatan);

        $this->normalizeKegiatanInput($request);
        $validated = $request->validate($this->kegiatanRules(update: true), $this->kegiatanMessages());

        // Hanya field yang benar-benar dikirim yang diubah; field yang tidak dikirim tetap memakai nilai lama.
        $payload = $this->kegiatanUpdatePayload($request, $validated);

        // Pilihan kampus/gedung/ruangan hanya disimpan untuk lokasi kampus UNUSA.
        $lokasiType = $payload['lokasi_type'] ?? $kegiatan->lokasi_type;
        if ($lokasiType !== 'unusa_campus') {
            $payload = [...$payload, 'lokasi_kampus' => null, 'lokasi_gedung' => null, 'lokasi_ruangan' => null];
        } elseif (array_intersect(['lokasi_kampus', 'lokasi_gedung', 'lokasi_ruangan'], array_keys($validated))) {
            // Field yang dikirim dipakai apa adanya (kosong = ditolak); yang tidak dikirim memakai nilai lama.
            $pilihan = [];
            foreach (['lokasi_kampus', 'lokasi_gedung', 'lokasi_ruangan'] as $key) {
                $pilihan[$key] = array_key_exists($key, $validated) ? $validated[$key] : $kegiatan->{$key};
            }
            $payload = [...$payload, ...$this->facilityService->pilihanKampus($lokasiType, $pilihan)];
        }

        // jsonb PostgreSQL mengurutkan ulang key objek; isi narasumber yang sama tidak boleh dianggap perubahan.
        if (array_key_exists('narasumber', $payload) && $this->jsonSama($payload['narasumber'], $kegiatan->narasumber)) {
            unset($payload['narasumber']);
        }

        // Pada kegiatan terbit, field kritis dikunci dengan pesan jelas
        if (($kegiatan->status === KegiatanStatus::Published || $kegiatan->status === KegiatanStatus::Ongoing) && $kegiatan->persetujuan()->exists()) {
            $criticalFields = [
                'tanggal_mulai',
                'tanggal_selesai',
                'registration_deadline',
                'submission_deadline',
                'metode_pelaksanaan',
                'biaya',
                'sasaran_peserta',
                'kuota',
                'certificate_schema_config',
                'is_submission_active',
                'submission_instructions',
            ];

            foreach ($criticalFields as $field) {
                if (array_key_exists($field, $payload)) {
                    $oldVal = $kegiatan->{$field};
                    $newVal = $payload[$field];
                    $berubah = false;

                    if ($oldVal instanceof CarbonInterface && $newVal instanceof CarbonInterface) {
                        $berubah = ! $oldVal->equalTo($newVal);
                    } elseif (is_numeric($oldVal) && is_numeric($newVal)) {
                        $berubah = (float) $oldVal != (float) $newVal;
                    } elseif (is_bool($oldVal) || is_bool($newVal)) {
                        $berubah = (bool) $oldVal !== (bool) $newVal;
                    } else {
                        $berubah = $oldVal != $newVal;
                    }

                    if ($berubah) {
                        throw ValidationException::withMessages([
                            $field => 'Field kritis (jadwal, metode, biaya, kuota, atau aturan kehadiran & sertifikat) pada kegiatan yang sudah terbit telah dikunci dan tidak dapat diubah secara langsung.',
                        ]);
                    }
                }
            }
        }

        $mulai = $payload['tanggal_mulai'] ?? $kegiatan->tanggal_mulai;
        if (! array_key_exists('tanggal_selesai', $payload) && isset($payload['tanggal_mulai'])) {
            if ($kegiatan->tanggal_selesai && $kegiatan->tanggal_selesai->lte($mulai)) {
                $durasi = max(7200, $kegiatan->tanggal_mulai ? $kegiatan->tanggal_mulai->diffInSeconds($kegiatan->tanggal_selesai) : 7200);
                $payload['tanggal_selesai'] = $mulai->copy()->addSeconds($durasi);
            }
        }
        $selesai = array_key_exists('tanggal_selesai', $payload) ? $payload['tanggal_selesai'] : $kegiatan->tanggal_selesai;
        if ($mulai && $selesai && $selesai->lte($mulai)) {
            throw ValidationException::withMessages(['tanggal_selesai' => 'Waktu selesai harus setelah waktu mulai.']);
        }

        $regDeadline = array_key_exists('registration_deadline', $payload) ? $payload['registration_deadline'] : $kegiatan->registration_deadline;
        if ($mulai && $regDeadline && $regDeadline->gt($mulai)) {
            throw ValidationException::withMessages(['registration_deadline' => 'Batas pendaftaran tidak boleh melewati waktu mulai kegiatan.']);
        }

        // Mengedit kegiatan berstatus diajukan juga menghapus persetujuan
        if ($kegiatan->status === KegiatanStatus::Diajukan) {
            $kegiatan->persetujuan()->delete();
        }

        DB::beginTransaction();
        try {
            // Denah kursi hanya dibuat ulang bila konfigurasinya benar-benar berubah (atau belum ada kursi sama sekali).
            // Jalurnya sama dengan venue-config: kapasitas dicek, kursi peserta direset, email pilih ulang dikirim.
            // Dicek sebelum berkas disimpan agar penolakan kapasitas tidak meninggalkan file yatim di disk.
            $seatBookingActive = $payload['is_seat_booking_active'] ?? (bool) $kegiatan->is_seat_booking_active;
            if ($seatBookingActive && array_key_exists('seat_layout_config', $validated)) {
                // Kursi diblokir diatur di tab Denah & Booking; form ini tidak mengirimnya, jadi nilai lama dipertahankan.
                $layoutLama = $this->denah->normalisasi($kegiatan->seat_layout_config);
                $layoutBaru = $this->denah->normalisasi([
                    'blocked_seats' => $layoutLama['blocked_seats'] ?? [],
                    ...$validated['seat_layout_config'],
                ]);
                if ($layoutBaru !== $layoutLama || ! $kegiatan->seats()->exists()) {
                    $payload['seat_layout_config'] = $this->denah->terapkan($kegiatan, $layoutBaru, ruanganLama: $kegiatan->lokasi_nama);
                }
            }

            if ($request->hasFile('panduan_file')) {
                $payload['file_panduan_pdf'] = $request->file('panduan_file')->store('kegiatan/panduan', 'public');
            }

            if ($request->hasFile('certificate_template_file')) {
                $payload['certificate_template_path'] = $request->file('certificate_template_file')->store('kegiatan/templates', 'public');
            }

            $kegiatan->fill($payload);

            // Field jadwal/lokasi yang berubah dikabarkan ke peserta; tanggal dibandingkan & dilaporkan dalam WIB.
            $changedAttributes = [];
            foreach (Kegiatan::scheduleSensitiveFields() as $field) {
                if ($kegiatan->isDirty($field)) {
                    $changedAttributes[$field] = [
                        'old' => $this->formatChangedValue($kegiatan->getOriginal($field)),
                        'new' => $this->formatChangedValue($kegiatan->getAttribute($field)),
                    ];
                }
            }

            // save() tidak menulis apa pun (termasuk updated_at) bila tidak ada kolom yang berubah.
            $kegiatan->save();

            // Jika ada perubahan waktu/lokasi, trigger notifikasi email kepada peserta
            if (! empty($changedAttributes)) {
                event(new KegiatanUpdatedEvent($kegiatan, $changedAttributes));
            }

            AuditLog::record(
                auth()->id(),
                'update_kegiatan',
                "Memperbarui data kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
                $kegiatan
            );

            DB::commit();

            return back()->with('success', 'Kegiatan berhasil diperbarui.');
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal memperbarui kegiatan: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Hapus kegiatan (hanya jika belum ada peserta yang memesan tiket/kursi).
     */
    public function destroy(int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('delete', $kegiatan);

        $hasBooked = $kegiatan->registrations()->exists();
        if ($hasBooked) {
            return back()->with('error', 'Kegiatan tidak dapat dihapus karena sudah memiliki peserta terdaftar.');
        }

        DB::beginTransaction();
        try {
            $kegiatan->seats()->delete();
            $kegiatan->delete();

            AuditLog::record(
                auth()->id(),
                'delete_kegiatan',
                "Menghapus kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$id})",
                null
            );

            DB::commit();

            return redirect()->route('admin.kegiatan')->with('success', 'Kegiatan berhasil dihapus.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal menghapus kegiatan: '.$e->getMessage());
        }
    }

    /**
     * Ajukan kegiatan untuk persetujuan seluruh super admin.
     */
    public function ajukan(Request $request, int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('update', $kegiatan);

        if ($kegiatan->created_by && $kegiatan->created_by !== auth()->id()) {
            abort(403, 'Hanya pembuat kegiatan yang dapat mengajukan persetujuan.');
        }

        if ($kegiatan->status !== KegiatanStatus::Draft) {
            return back()->with('error', 'Hanya kegiatan berstatus draft yang dapat diajukan.');
        }

        if ($kegiatan->isDaringOrHybrid() && empty($kegiatan->tautan_rapat)) {
            return back()->with('error', 'Tautan rapat daring wajib diisi sebelum kegiatan dapat diajukan.');
        }

        $kegiatan->status = KegiatanStatus::Diajukan;
        $kegiatan->catatan_penolakan = null;
        $kegiatan->save();

        // Hapus persetujuan lama bila ada bekas penolakan sebelumnya
        $kegiatan->persetujuan()->delete();

        // Kirim email notifikasi ke semua penyetuju wajib lewat antrean pengamanan email
        $penyetujuWajib = $kegiatan->penyetujuWajib();
        foreach ($penyetujuWajib as $penyetuju) {
            EmailProtectionService::send($penyetuju->email, new KegiatanDiajukanMail($kegiatan, $penyetuju));
        }

        AuditLog::record(
            auth()->id(),
            'submit_kegiatan',
            "Mengajukan kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id}) untuk persetujuan seluruh super admin",
            $kegiatan
        );

        return back()->with('success', 'Kegiatan berhasil diajukan untuk persetujuan seluruh Super Admin.');
    }

    /**
     * Tarik kembali kegiatan diajukan ke status draft oleh pembuat kegiatan.
     */
    public function tarikDraft(int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('update', $kegiatan);

        if ($kegiatan->created_by && $kegiatan->created_by !== auth()->id()) {
            abort(403, 'Hanya pembuat kegiatan yang dapat menarik kembali kegiatan ke status draft.');
        }

        if ($kegiatan->status !== KegiatanStatus::Diajukan) {
            return back()->with('error', 'Hanya kegiatan berstatus diajukan yang dapat ditarik kembali ke draft.');
        }

        $kegiatan->status = KegiatanStatus::Draft;
        $kegiatan->save();

        // Hapus suara persetujuan yang sudah masuk
        $kegiatan->persetujuan()->delete();

        AuditLog::record(
            auth()->id(),
            'withdraw_kegiatan',
            "Menarik kembali kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id}) ke status draft",
            $kegiatan
        );

        return back()->with('success', 'Kegiatan berhasil ditarik kembali ke status draft.');
    }

    /**
     * Berikan persetujuan untuk kegiatan berstatus diajukan.
     */
    public function setujui(Request $request, int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('update', $kegiatan);

        if ($kegiatan->created_by && $kegiatan->created_by === auth()->id()) {
            abort(403, 'Pembuat kegiatan tidak dapat menyetujui kegiatannya sendiri.');
        }

        if ($kegiatan->status !== KegiatanStatus::Diajukan) {
            return back()->with('error', 'Hanya kegiatan berstatus diajukan yang dapat disetujui.');
        }

        KegiatanPersetujuan::updateOrCreate(
            ['kegiatan_id' => $kegiatan->id, 'user_id' => auth()->id()],
            [
                'keputusan' => 'setuju',
                'catatan' => null,
                'data_version' => $kegiatan->sidikJariData(),
                'ip_address' => $request->ip(),
            ]
        );

        AuditLog::record(
            auth()->id(),
            'approve_kegiatan',
            "Menyetujui permohonan penerbitan kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
            $kegiatan
        );

        $terbit = $kegiatan->cekDanPerbaruiStatusPersetujuan();

        return back()->with(
            'success',
            $terbit
                ? 'Seluruh Super Admin telah menyetujui. Kegiatan resmi diterbitkan.'
                : 'Persetujuan Anda telah dicatat. Menunggu persetujuan Super Admin lainnya.'
        );
    }

    /**
     * Tolak pengajuan kegiatan (dengan catatan wajib). Mengembalikan kegiatan ke draft.
     */
    public function tolak(Request $request, int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('update', $kegiatan);

        if ($kegiatan->created_by && $kegiatan->created_by === auth()->id()) {
            abort(403, 'Pembuat kegiatan tidak dapat menolak kegiatannya sendiri.');
        }

        if ($kegiatan->status !== KegiatanStatus::Diajukan) {
            return back()->with('error', 'Hanya kegiatan berstatus diajukan yang dapat ditolak.');
        }

        $validated = $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'catatan.required' => 'Catatan penolakan wajib diisi.',
            'catatan.min' => 'Catatan penolakan minimal 5 karakter.',
        ]);

        $catatan = trim($validated['catatan']);

        // Satu penolakan (catatan wajib) mengembalikan kegiatan ke draft dan menghapus semua persetujuan
        $kegiatan->persetujuan()->delete();
        $kegiatan->status = KegiatanStatus::Draft;
        $kegiatan->catatan_penolakan = $catatan;
        $kegiatan->save();

        AuditLog::record(
            auth()->id(),
            'reject_kegiatan',
            "Menolak pengajuan kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id}). Catatan: {$catatan}",
            $kegiatan
        );

        return back()->with('success', 'Kegiatan ditolak dan dikembalikan ke status draft.');
    }

    /**
     * Buka akses tombol gabung secara manual (override).
     */
    public function bukaAksesGabung(Request $request, int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('update', $kegiatan);

        $kegiatan->is_gabung_manual_open = true;
        $kegiatan->save();

        AuditLog::record(
            auth()->id(),
            'open_join_access',
            "Membuka akses tombol gabung ruang daring kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id}) secara manual",
            $kegiatan
        );

        return back()->with('success', 'Akses tombol gabung berhasil dibuka untuk peserta.');
    }

    /**
     * Tutup akses tombol gabung secara manual (override).
     */
    public function tutupAksesGabung(Request $request, int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('update', $kegiatan);

        $kegiatan->is_gabung_manual_open = false;
        $kegiatan->save();

        AuditLog::record(
            auth()->id(),
            'close_join_access',
            "Menutup akses tombol gabung ruang daring kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id}) secara manual",
            $kegiatan
        );

        return back()->with('success', 'Akses tombol gabung telah ditutup.');
    }

    /**
     * Perbarui pengaturan rapat daring (bukan field kritis, dicatat di audit log tanpa membocorkan rahasia).
     */
    public function updatePengaturanDaring(Request $request, int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('update', $kegiatan);

        $validated = $request->validate([
            'platform_daring' => ['required', 'string', 'in:zoom,meet,teams,lainnya'],
            'tautan_rapat' => ['required', 'url', 'max:500', new ValidMeetingUrl],
            'meeting_id' => ['nullable', 'string', 'max:100'],
            'passcode_rapat' => ['nullable', 'string', 'max:100'],
            'join_button_mode' => ['required', 'string', 'in:otomatis,manual'],
            'menit_buka_gabung' => ['required', 'integer', 'min:0', 'max:180'],
            'batas_tutup_gabung' => ['nullable', 'date'],
            'izin_tautan_presensi_akhir' => ['boolean'],
            'wajib_tangkapan_layar' => ['boolean'],
        ]);

        $kegiatan->platform_daring = $validated['platform_daring'];
        $kegiatan->tautan_rapat = $validated['tautan_rapat'];
        $kegiatan->meeting_id = $validated['meeting_id'] ?? null;
        if (array_key_exists('passcode_rapat', $validated)) {
            $kegiatan->passcode_rapat = $validated['passcode_rapat'];
        }
        $kegiatan->join_button_mode = $validated['join_button_mode'];
        $kegiatan->menit_buka_gabung = (int) $validated['menit_buka_gabung'];
        $kegiatan->batas_tutup_gabung = ! empty($validated['batas_tutup_gabung']) ? Wib::keUtc($validated['batas_tutup_gabung']) : null;
        $kegiatan->izin_tautan_presensi_akhir = $request->boolean('izin_tautan_presensi_akhir');
        $kegiatan->wajib_tangkapan_layar = $request->boolean('wajib_tangkapan_layar');
        $kegiatan->save();

        AuditLog::record(
            auth()->id(),
            'update_kegiatan_daring',
            "Memperbarui pengaturan rapat daring kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
            $kegiatan,
            [
                'platform' => $kegiatan->platform_daring,
                'mode' => $kegiatan->join_button_mode,
                'menit_buka' => $kegiatan->menit_buka_gabung,
                'izin_presensi_tautan' => $kegiatan->izin_tautan_presensi_akhir,
                'wajib_tangkapan_layar' => $kegiatan->wajib_tangkapan_layar,
            ]
        );

        return back()->with('success', 'Pengaturan rapat daring berhasil diperbarui.');
    }

    /**
     * Aturan validasi form kegiatan. Nama field = nama kolom model Kegiatan.
     * Saat update semua field opsional ('sometimes'): yang tidak dikirim tidak diubah.
     */
    protected function kegiatanRules(bool $update = false): array
    {
        $wajib = $update ? ['sometimes', 'required'] : ['required'];

        return [
            'nama_kegiatan' => [...$wajib, 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => [...$wajib, 'string', Rule::in(Kegiatan::KATEGORI)],
            'sasaran_peserta' => ['nullable', Rule::in(Kegiatan::SASARAN)],
            'metode_pelaksanaan' => ['nullable', Rule::in(Kegiatan::METODE)],
            'tanggal_mulai' => [...$wajib, 'date'],
            // Saat update, urutan mulai/selesai dicek terhadap nilai efektif (lihat update()).
            'tanggal_selesai' => $update ? ['nullable', 'date'] : ['nullable', 'date', 'after:tanggal_mulai'],
            'registration_deadline' => $update ? ['nullable', 'date'] : ['nullable', 'date', 'before_or_equal:tanggal_mulai'],
            'submission_deadline' => ['nullable', 'date'],
            'lokasi_type' => [...$wajib, Rule::in(Kegiatan::LOKASI_TYPES)],
            // Pilihan fasilitas UNUSA; disimpan hanya untuk lokasi_type = unusa_campus (lihat UnusaFacilityService::pilihanKampus()).
            'lokasi_kampus' => ['nullable', 'string', 'max:255'],
            'lokasi_gedung' => ['nullable', 'string', 'max:255'],
            'lokasi_ruangan' => ['nullable', 'string', 'max:255'],
            'lokasi_nama' => [...$wajib, 'string', 'max:255'],
            'lokasi_detail' => ['nullable', 'string', 'max:255'],
            'biaya' => [...$wajib, 'numeric', 'min:0'],
            'kuota' => [...$wajib, 'integer', 'min:1'],
            'poin_xp' => ['nullable', 'integer', 'min:0'],
            // Daftar narasumber kosong dikirim sebagai null (FormData membuang array kosong saat ada unggahan berkas).
            'narasumber' => ['nullable', 'array'],
            'narasumber.*' => ['array'],
            'narasumber.*.nama' => ['required', 'string', 'max:255'],
            'narasumber.*.gelar' => ['nullable', 'string', 'max:255'],
            'narasumber.*.instansi' => ['nullable', 'string', 'max:255'],
            'narasumber.*.foto' => ['nullable', 'string', 'max:500'],
            'is_seat_booking_active' => ['boolean'],
            'is_submission_active' => ['boolean'],
            'submission_instructions' => ['nullable', 'string'],
            'status' => $update ? ['sometimes', 'required', Rule::enum(KegiatanStatus::class)] : ['nullable', Rule::enum(KegiatanStatus::class)],
            'panduan_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'certificate_template_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:10240'],
            'certificate_schema_config' => ['nullable', 'array'],
            'platform_daring' => ['nullable', 'string', 'in:zoom,meet,teams,lainnya'],
            'tautan_rapat' => ['nullable', 'url', 'max:500', new ValidMeetingUrl],
            'meeting_id' => ['nullable', 'string', 'max:100'],
            'passcode_rapat' => ['nullable', 'string', 'max:100'],
            'join_button_mode' => ['nullable', 'string', 'in:otomatis,manual'],
            'menit_buka_gabung' => ['nullable', 'integer', 'min:0', 'max:180'],
            'batas_tutup_gabung' => ['nullable', 'date'],
            'izin_tautan_presensi_akhir' => ['boolean'],
            'wajib_tangkapan_layar' => ['boolean'],
            'seat_layout_config' => ['nullable', 'array'],
            'seat_layout_config.rows' => ['sometimes', 'array'],
            'seat_layout_config.rows.*' => ['string', 'max:3'],
            'seat_layout_config.cols_per_row' => ['sometimes', 'integer', 'min:1', 'max:50'],
            // Daftar kosong dikirim sebagai null (FormData membuang array kosong saat ada unggahan berkas).
            'seat_layout_config.vip_rows' => ['sometimes', 'nullable', 'array'],
            'seat_layout_config.vip_rows.*' => ['string', 'max:3'],
            'seat_layout_config.panitia_rows' => ['sometimes', 'nullable', 'array'],
            'seat_layout_config.panitia_rows.*' => ['string', 'max:3'],
            'seat_layout_config.blocked_seats' => ['sometimes', 'nullable', 'array'],
            'seat_layout_config.blocked_seats.*' => ['string', 'max:8'],
        ];
    }

    /**
     * Pesan validasi dalam bahasa Indonesia untuk form kegiatan.
     *
     * @return array<string, string>
     */
    protected function kegiatanMessages(): array
    {
        return [
            'tanggal_selesai.after' => 'Waktu selesai harus setelah waktu mulai.',
            'registration_deadline.before_or_equal' => 'Batas pendaftaran tidak boleh melewati waktu mulai kegiatan.',
        ];
    }

    /**
     * Seragamkan nilai input lama sebelum validasi: kategori "Workshop" -> "workshop",
     * lokasi "external_venue" (nama lama di form) -> "external" (nilai kolom).
     */
    protected function normalizeKegiatanInput(Request $request): void
    {
        if ($request->filled('kategori')) {
            $request->merge(['kategori' => strtolower(str_replace(' ', '_', trim((string) $request->input('kategori'))))]);
        }

        if ($request->input('lokasi_type') === 'external_venue') {
            $request->merge(['lokasi_type' => 'external']);
        }
    }

    /**
     * Payload update berisi HANYA kolom yang dikirim di request.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function kegiatanUpdatePayload(Request $request, array $validated): array
    {
        $payload = [];
        $ada = fn (string $key): bool => array_key_exists($key, $validated);

        foreach (['nama_kegiatan', 'lokasi_nama'] as $key) {
            if ($ada($key)) {
                $payload[$key] = trim($validated[$key]);
            }
        }

        foreach (['deskripsi', 'kategori', 'lokasi_type', 'lokasi_detail', 'submission_instructions', 'certificate_schema_config'] as $key) {
            if ($ada($key)) {
                $payload[$key] = $validated[$key];
            }
        }

        // Kolom NOT NULL: nilai kosong tidak boleh menimpa nilai lama.
        foreach (['sasaran_peserta', 'metode_pelaksanaan'] as $key) {
            if ($ada($key) && $validated[$key] !== null) {
                $payload[$key] = $validated[$key];
            }
        }

        // Input jadwal dianggap WIB, disimpan dalam UTC. Deadline yang dikirim kosong berarti dihapus.
        foreach (['tanggal_mulai', 'tanggal_selesai', 'registration_deadline', 'submission_deadline'] as $key) {
            if ($ada($key)) {
                $payload[$key] = Wib::keUtc($validated[$key]);
            }
        }

        if ($ada('biaya')) {
            $payload['biaya'] = (float) $validated['biaya'];
        }
        if ($ada('kuota')) {
            $payload['kuota'] = (int) $validated['kuota'];
        }
        if ($ada('poin_xp')) {
            $payload['poin_xp'] = (int) ($validated['poin_xp'] ?? 0);
        }

        foreach (['is_seat_booking_active', 'is_submission_active'] as $key) {
            if ($ada($key)) {
                $payload[$key] = $request->boolean($key);
            }
        }

        if ($ada('status')) {
            $payload['status'] = KegiatanStatus::from($validated['status']);
        }

        if ($ada('narasumber')) {
            $payload['narasumber'] = $this->normalizeNarasumber($validated['narasumber']);
        }

        foreach (['platform_daring', 'join_button_mode'] as $key) {
            if ($ada($key)) {
                $payload[$key] = $validated[$key];
            }
        }
        // Rahasia rapat tidak dikirim balik ke form edit, jadi isian kosong berarti "biarkan yang tersimpan".
        // Mengosongkan passcode/meeting ID dilakukan lewat Pengaturan Daring di halaman detail.
        foreach (['tautan_rapat', 'meeting_id', 'passcode_rapat'] as $key) {
            if ($ada($key) && filled($validated[$key])) {
                $payload[$key] = $validated[$key];
            }
        }
        if ($ada('menit_buka_gabung')) {
            $payload['menit_buka_gabung'] = (int) $validated['menit_buka_gabung'];
        }
        if ($ada('batas_tutup_gabung')) {
            $payload['batas_tutup_gabung'] = ! empty($validated['batas_tutup_gabung']) ? Wib::keUtc($validated['batas_tutup_gabung']) : null;
        }
        foreach (['izin_tautan_presensi_akhir', 'wajib_tangkapan_layar'] as $key) {
            if ($ada($key)) {
                $payload[$key] = $request->boolean($key);
            }
        }

        return $payload;
    }

    /**
     * Narasumber disimpan sebagai array of {nama, gelar?, instansi?, foto?}. Baris tanpa nama dibuang;
     * kunci yang dikirim dipertahankan (urutan tetap) agar data tersimpan kembali utuh.
     *
     * @return list<array<string, mixed>>|null
     */
    protected function normalizeNarasumber(?array $narasumber): ?array
    {
        $hasil = [];

        foreach ($narasumber ?? [] as $item) {
            $nama = trim((string) ($item['nama'] ?? ''));
            if ($nama === '') {
                continue;
            }

            $entry = ['nama' => $nama];
            foreach (['gelar', 'instansi', 'foto'] as $key) {
                if (array_key_exists($key, $item)) {
                    $entry[$key] = $item[$key];
                }
            }
            $hasil[] = $entry;
        }

        return $hasil === [] ? null : $hasil;
    }

    /**
     * Bandingkan dua nilai JSON tanpa memedulikan urutan key objek (urutan elemen list tetap berarti).
     */
    protected function jsonSama(mixed $a, mixed $b): bool
    {
        $urutkan = function (mixed $value) use (&$urutkan): mixed {
            if (! is_array($value)) {
                return $value;
            }
            $value = array_map($urutkan, $value);
            if (! array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };

        return $urutkan($a) === $urutkan($b);
    }

    /**
     * Nilai untuk notifikasi perubahan: tanggal dalam WIB ("Y-m-d H:i"), selain itu apa adanya.
     */
    protected function formatChangedValue(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return Wib::dari($value)->format('Y-m-d H:i');
        }

        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
