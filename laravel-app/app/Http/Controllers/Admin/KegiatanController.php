<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Events\KegiatanUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Mail\KegiatanDiajukanMail;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanPersetujuan;
use App\Models\KegiatanRegistration;
use App\Services\Kegiatan\DenahKursiService;
use App\Services\Kegiatan\PetaKursiService;
use App\Services\UnusaFacilityService;
use App\Support\Wib;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
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

        $menungguPersetujuanAnda = 0;
        if (auth()->user()?->isSuperAdmin()) {
            $currentUserId = auth()->id();
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

        return Inertia::render('Admin/Kegiatan/Index', [
            'title' => 'Manajemen Kegiatan Enterprise',
            'kegiatans' => $kegiatans,
            'filters' => $request->only(['q', 'status', 'kategori']),
            'unusaFacilities' => $unusaFacilities,
            'statusOptions' => KegiatanStatus::cases(),
            'menungguPersetujuanAnda' => $menungguPersetujuanAnda,
        ]);
    }

    /**
     * Simpan kegiatan baru beserta denah kursi dan dokumen panduan.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Kegiatan::class);

        $this->normalizeKegiatanInput($request);
        $validated = $request->validate($this->kegiatanRules(), $this->kegiatanMessages());

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
            $lokasiType = $validated['lokasi_type'];
            $seatBookingActive = $request->boolean('is_seat_booking_active');
            $seatLayout = $this->denah->normalisasi($validated['seat_layout_config'] ?? null);

            $kegiatan = Kegiatan::create([
                'created_by' => auth()->id(),
                'nama_kegiatan' => trim($validated['nama_kegiatan']),
                'deskripsi' => $validated['deskripsi'] ?? null,
                'kategori' => $validated['kategori'],
                'metode_pelaksanaan' => $validated['metode_pelaksanaan'] ?? ($lokasiType === 'online' ? 'online' : 'offline'),
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
                'status' => KegiatanStatus::tryFrom($validated['status'] ?? '') ?? KegiatanStatus::Published,
                'seat_layout_config' => $seatBookingActive ? $seatLayout : null,
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

            DB::commit();

            return redirect()->route('admin.kegiatan')->with('success', 'Kegiatan enterprise berhasil disimpan.');
        } catch (\Throwable $e) {
            DB::rollBack();

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

        $sudahMemberiSuara = (bool) $persetujuan->firstWhere('user_id', $currentUserId);

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
                'status' => $kegiatan->status?->value,
                'seat_booking_open' => $kegiatan->isSeatBookingOpen(),
                'status_booking' => $kegiatan->statusBookingKursi(),
                'registration_open' => $kegiatan->isRegistrationOpen(),
            ],
            'approval' => [
                'status' => $kegiatan->status?->value,
                'created_by' => $kegiatan->created_by,
                'creator_name' => $kegiatan->createdBy?->name,
                'is_creator' => $currentUserId === $kegiatan->created_by,
                'can_submit' => ($currentUserId === $kegiatan->created_by || ! $kegiatan->created_by) && $kegiatan->status === KegiatanStatus::Draft,
                'can_approve' => $currentUserId !== $kegiatan->created_by && $kegiatan->status === KegiatanStatus::Diajukan && ! $sudahMemberiSuara,
                'can_reject' => $currentUserId !== $kegiatan->created_by && $kegiatan->status === KegiatanStatus::Diajukan,
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

        $kegiatan->status = KegiatanStatus::Diajukan;
        $kegiatan->save();

        // Hapus persetujuan lama bila ada bekas penolakan sebelumnya
        $kegiatan->persetujuan()->delete();

        // Kirim email notifikasi ke semua penyetuju wajib lewat antrean
        $penyetujuWajib = $kegiatan->penyetujuWajib();
        foreach ($penyetujuWajib as $penyetuju) {
            Mail::to($penyetuju->email)->queue(new KegiatanDiajukanMail($kegiatan, $penyetuju));
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
