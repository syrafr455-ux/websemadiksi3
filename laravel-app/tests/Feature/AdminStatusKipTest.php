<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PenerimaKipk;
use App\Models\User;
use App\Notifications\StatusKipkDiperbarui;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminStatusKipTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '1234.567.89012.1766.761';

    private const DIGIT = '1234567890121766761';

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = User::factory()->superAdmin()->create();
    }

    protected function penerima(string $nomor, string $nama, array $atribut = []): PenerimaKipk
    {
        $p = (new PenerimaKipk(['nama_lengkap' => $nama] + $atribut))->aturNomor($nomor);
        $p->save();

        return $p;
    }

    protected function kipkTertaut(): array
    {
        $user = User::factory()->role(UserRole::MahasiswaKipk)->create(['name' => 'Ahmad Fauzan', 'nim' => '2241010001']);
        $p = $this->penerima(self::NOMOR, 'Ahmad Fauzan', [
            'nim' => '2241010001', 'user_id' => $user->id, 'cara_terhubung' => 'impor_nim',
            'dicocokkan_pada' => now(), 'sumber' => 'Impor daftar UNUSA (daftar.csv)',
        ]);

        return [$user, $p];
    }

    protected function umumUnusa(string $nama, string $nim): User
    {
        return User::factory()->role(UserRole::UserUmum)->create(['name' => $nama, 'nim' => $nim, 'jalur_pendaftaran' => User::JALUR_UNUSA]);
    }

    public function test_pengguna_page_shows_masked_kip_details_but_never_the_full_number(): void
    {
        [$user, $p] = $this->kipkTertaut();
        $p->update(['status_tinjau' => PenerimaKipk::TINJAU_PERLU, 'alasan_tinjau' => PenerimaKipk::ALASAN_HILANG]);

        $html = $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()->getContent();
        $this->assertStringNotContainsString(self::DIGIT, $html);
        $this->assertStringNotContainsString(self::NOMOR, $html);

        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $m);
        $baris = collect(json_decode($m[1], true)['props']['users']['data'])->firstWhere('id', $user->id);
        $this->assertSame('****.***.*****.1766.761', $baris['penerima_kipk']['nomor_tersamar']);
        $this->assertSame('impor_nim', $baris['penerima_kipk']['cara_terhubung']);
        $this->assertSame('perlu_ditinjau', $baris['penerima_kipk']['status_tinjau']);
        $this->assertNotNull($baris['penerima_kipk']['dicocokkan_pada']);
        $this->assertArrayNotHasKey('nomor_pendaftaran_kip', $baris['penerima_kipk']);
    }

    public function test_super_admin_can_reveal_full_number_and_it_is_audited(): void
    {
        [, $p] = $this->kipkTertaut();

        $this->actingAs($this->admin)->postJson(route('admin.penerima-kipk.nomor', $p->id))
            ->assertOk()->assertExactJson(['nomor' => self::NOMOR])->assertHeader('Cache-Control', 'no-store, private');

        $audit = AuditLog::where('action', 'buka_nomor_kip')->sole();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame($p->id, $audit->metadata['penerima_id']);
        $this->assertStringNotContainsString(self::DIGIT, json_encode($audit->toArray()));
    }

    public function test_other_roles_cannot_reveal_review_or_change_status(): void
    {
        [$user, $p] = $this->kipkTertaut();

        foreach ([UserRole::VerifikatorBerkas, UserRole::PenilaiKarya, UserRole::MahasiswaKipk, UserRole::UserUmum] as $role) {
            $lain = User::factory()->role($role)->create();
            $this->actingAs($lain)->postJson(route('admin.penerima-kipk.nomor', $p->id))->assertForbidden()->assertJsonMissing(['nomor' => self::NOMOR]);
            $this->actingAs($lain)->postJson(route('admin.penerima-kipk.tinjau', $p->id), ['keputusan' => 'pertahankan', 'alasan' => 'percobaan'])->assertForbidden();
            $this->actingAs($lain)->postJson(route('admin.users.status-kip', $user->id), ['status' => 'bukan_kipk', 'alasan' => 'percobaan'])->assertForbidden();
        }

        $this->assertSame(0, AuditLog::where('action', 'buka_nomor_kip')->count());
        $this->assertSame(UserRole::MahasiswaKipk, $user->fresh()->role);
    }

    public function test_recipient_page_filters_by_link_and_review_status(): void
    {
        [$user] = $this->kipkTertaut();
        $this->penerima('2222.333.44444.5555.666', 'Belum Terhubung');
        $pengklaim = $this->umumUnusa('Siti R', '2241010002');
        $this->penerima('3333.444.55555.6666.777', 'Siti Rahmawati', [
            'status_tinjau' => PenerimaKipk::TINJAU_PERLU, 'alasan_tinjau' => PenerimaKipk::ALASAN_KLAIM_NAMA, 'klaim_user_id' => $pengklaim->id,
        ]);

        $nama = fn (?string $status) => collect($this->actingAs($this->admin)->get(route('admin.penerima-kipk', array_filter(['status' => $status])))
            ->assertOk()->viewData('penerima')->items())->pluck('nama_lengkap')->sort()->values()->all();

        $this->assertSame(['Ahmad Fauzan'], $nama('terhubung'));
        $this->assertSame(['Belum Terhubung'], $nama('belum_terhubung'));
        $this->assertSame(['Siti Rahmawati'], $nama('perlu_ditinjau'));
        $this->assertCount(3, $nama(null));

        $this->actingAs($this->admin)->get(route('admin.penerima-kipk'))
            ->assertSee($user->name)->assertSee('Klaim oleh Siti R')->assertSee('Perlu ditinjau')
            ->assertDontSee(self::DIGIT);
    }

    public function test_approving_and_rejecting_claims_requires_reason_and_is_audited(): void
    {
        $pengklaim = $this->umumUnusa('Siti R', '2241010002');
        $p = $this->penerima('3333.444.55555.6666.777', 'Siti Rahmawati', [
            'status_tinjau' => PenerimaKipk::TINJAU_PERLU, 'alasan_tinjau' => PenerimaKipk::ALASAN_KLAIM_NAMA, 'klaim_user_id' => $pengklaim->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.tinjau', $p->id), ['keputusan' => 'setujui', 'alasan' => ''])
            ->assertSessionHasErrors('alasan');
        $this->assertSame(UserRole::UserUmum, $pengklaim->fresh()->role);

        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.tinjau', $p->id), ['keputusan' => 'setujui', 'alasan' => 'Nama sesuai KTM (singkatan nama belakang)'])
            ->assertSessionHas('success');

        $this->assertSame(UserRole::MahasiswaKipk, $pengklaim->fresh()->role);
        $p->refresh();
        $this->assertSame($pengklaim->id, $p->user_id);
        $this->assertSame('persetujuan_admin', $p->cara_terhubung);
        $this->assertSame(PenerimaKipk::TINJAU_NORMAL, $p->status_tinjau);
        $this->assertStringContainsString('Nama sesuai KTM', AuditLog::where('action', 'status_kip_berubah')->sole()->description);
        Notification::assertSentTo($pengklaim, StatusKipkDiperbarui::class);

        // Tolak klaim lain.
        $lain = $this->umumUnusa('Budi', '2241010003');
        $q = $this->penerima('4444.555.66666.7777.888', 'Bambang Sutrisno', [
            'status_tinjau' => PenerimaKipk::TINJAU_PERLU, 'alasan_tinjau' => PenerimaKipk::ALASAN_KLAIM_NAMA, 'klaim_user_id' => $lain->id,
        ]);
        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.tinjau', $q->id), ['keputusan' => 'tolak', 'alasan' => 'Bukan orang yang sama'])
            ->assertSessionHas('success');

        $q->refresh();
        $this->assertNull($q->klaim_user_id);
        $this->assertNull($q->user_id);
        $this->assertSame(PenerimaKipk::TINJAU_NORMAL, $q->status_tinjau);
        $this->assertSame(UserRole::UserUmum, $lain->fresh()->role);
        $this->assertStringContainsString('Bukan orang yang sama', AuditLog::where('action', 'klaim_kip_ditolak')->sole()->description);
    }

    public function test_missing_recipient_can_be_kept_or_revoked(): void
    {
        [$user, $p] = $this->kipkTertaut();
        $p->update(['status_tinjau' => PenerimaKipk::TINJAU_PERLU, 'alasan_tinjau' => PenerimaKipk::ALASAN_HILANG]);

        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.tinjau', $p->id), ['keputusan' => 'pertahankan', 'alasan' => 'Masih aktif menurut BAAK'])
            ->assertSessionHas('success');
        $this->assertSame(PenerimaKipk::TINJAU_NORMAL, $p->fresh()->status_tinjau);
        $this->assertSame(UserRole::MahasiswaKipk, $user->fresh()->role);

        $p->refresh()->update(['status_tinjau' => PenerimaKipk::TINJAU_PERLU, 'alasan_tinjau' => PenerimaKipk::ALASAN_HILANG]);
        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.tinjau', $p->id), ['keputusan' => 'cabut', 'alasan' => 'Sudah lulus'])
            ->assertSessionHas('success');
        $this->assertSame(UserRole::UserUmum, $user->fresh()->role);
        $this->assertNull($p->fresh()->user_id);
        $this->assertTrue(AuditLog::where('action', 'status_kip_berubah')->where('description', 'like', '%Sudah lulus%')->exists());
    }

    public function test_manual_status_change_requires_reason_and_is_audited(): void
    {
        $umum = $this->umumUnusa('Rina', '2241010004');

        $this->actingAs($this->admin)->post(route('admin.users.status-kip', $umum->id), ['status' => 'kipk', 'alasan' => ''])
            ->assertSessionHasErrors('alasan');
        $this->assertSame(UserRole::UserUmum, $umum->fresh()->role);

        $this->actingAs($this->admin)->post(route('admin.users.status-kip', $umum->id), ['status' => 'kipk', 'alasan' => 'SK penetapan susulan'])
            ->assertSessionHas('success');
        $this->assertSame(UserRole::MahasiswaKipk, $umum->fresh()->role);

        $this->actingAs($this->admin)->post(route('admin.users.status-kip', $umum->id), ['status' => 'bukan_kipk', 'alasan' => 'Salah input sebelumnya'])
            ->assertSessionHas('success');
        $this->assertSame(UserRole::UserUmum, $umum->fresh()->role);

        $deskripsi = AuditLog::where('action', 'status_kip_berubah')->pluck('description')->implode(' | ');
        $this->assertStringContainsString('SK penetapan susulan', $deskripsi);
        $this->assertStringContainsString('Salah input sebelumnya', $deskripsi);

        // Staf tidak bisa diberi status KIP-K.
        $juri = User::factory()->role(UserRole::PenilaiKarya)->create();
        $this->actingAs($this->admin)->post(route('admin.users.status-kip', $juri->id), ['status' => 'kipk', 'alasan' => 'percobaan'])->assertStatus(422);
    }
}
