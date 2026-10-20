<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\MenuAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Menu panel admin (layouts/admin.blade.php & AdminLayout.jsx) berasal dari App\Support\MenuAdmin
 * dan hanya berisi link yang boleh diakses role yang login.
 */
class MenuAdminPerRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public static function menuPerRole(): array
    {
        $semua = array_column(MenuAdmin::ITEM, 'route');

        return [
            'super_admin' => [UserRole::SuperAdmin, $semua],
            'verifikator_berkas' => [UserRole::VerifikatorBerkas, ['admin.berkas', 'admin.submissions']],
            'penilai_karya' => [UserRole::PenilaiKarya, ['admin.penilaian']],
            'mahasiswa_kipk' => [UserRole::MahasiswaKipk, []],
            'user_umum' => [UserRole::UserUmum, []],
        ];
    }

    protected function pengguna(UserRole $role): User
    {
        return User::factory()->role($role)->create(['is_blocked' => false]);
    }

    /** Route menu yang dirender Blade (atribut data-menu-admin). */
    protected function menuBlade(string $html): array
    {
        preg_match_all('/data-menu-admin="([^"]+)"/', $html, $m);

        return $m[1];
    }

    /** Route menu yang diterima React (prop Inertia menu_admin). */
    protected function menuReact(string $html): array
    {
        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $m);
        $page = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);

        return array_column($page['props']['menu_admin'], 'route');
    }

    #[DataProvider('menuPerRole')]
    public function test_menu_contains_exactly_the_links_the_role_may_open(UserRole $role, array $harapan): void
    {
        $user = $this->pengguna($role);

        $this->assertSame($harapan, array_column(MenuAdmin::untuk($user), 'route'));

        foreach (MenuAdmin::ITEM as $item) {
            $respons = $this->actingAs($user)->get(route($item['route']));

            if (in_array($item['route'], $harapan, true)) {
                $respons->assertOk();
            } else {
                $this->assertNotSame(200, $respons->getStatusCode(), "{$role->value} tidak boleh membuka {$item['route']}");
            }
        }
    }

    #[DataProvider('menuPerRole')]
    public function test_blade_and_react_layouts_render_the_same_menu(UserRole $role, array $harapan): void
    {
        if ($harapan === []) {
            // Peserta tidak punya panel admin; halaman React peserta tidak membawa menu admin.
            $user = $this->pengguna($role);
            $html = $this->actingAs($user)->get(route('dashboard.kegiatan.saya'))->assertOk()->getContent();
            $this->assertSame([], $this->menuReact($html));
            $this->assertSame([], $this->menuBlade($html));

            return;
        }

        $user = $this->pengguna($role);
        $halamanBlade = ['super_admin' => 'admin.dashboard', 'verifikator_berkas' => 'admin.berkas', 'penilai_karya' => null][$role->value];
        $halamanReact = ['super_admin' => 'admin.kegiatan', 'verifikator_berkas' => null, 'penilai_karya' => 'admin.penilaian'][$role->value];

        if ($halamanBlade) {
            $html = $this->actingAs($user)->get(route($halamanBlade))->assertOk()->getContent();
            $this->assertSame($harapan, $this->menuBlade($html));
            $this->assertStringContainsString('data-menu-admin="'.$halamanBlade.'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors bg-amber-600', $html, 'Link halaman aktif ditandai.');
        }

        if ($halamanReact) {
            $html = $this->actingAs($user)->get(route($halamanReact))->assertOk()->getContent();
            $this->assertSame($harapan, $this->menuReact($html));
        }
    }

    public function test_staff_login_lands_on_first_menu_page(): void
    {
        $harapan = [
            [UserRole::SuperAdmin, '/admin'],
            [UserRole::VerifikatorBerkas, '/admin/berkas'],
            [UserRole::PenilaiKarya, '/admin/penilaian'],
        ];

        foreach ($harapan as [$role, $tujuan]) {
            $user = User::factory()->role($role)->create(['password' => 'RahasiaKuat#2026']);
            $this->post(route('login.post'), ['identity' => $user->email, 'password' => 'RahasiaKuat#2026'])
                ->assertRedirect(url($tujuan));
            $this->post(route('logout'));
        }
    }

    public function test_legacy_student_role_value_is_not_used_anywhere(): void
    {
        $admin = $this->pengguna(UserRole::SuperAdmin);
        $mhs = User::factory()->mahasiswaKipk()->create(['name' => 'Ani KIP-K']);
        $umum = User::factory()->userUmum()->create(['name' => 'Budi Umum']);
        $juri = User::factory()->penilai()->create(['name' => 'Juri Lomba']);

        // Dropdown penerima sertifikat: peserta (mahasiswa_kipk & user_umum), bukan role 'student'.
        $penerima = $this->actingAs($admin)->get(route('admin.certificates'))->assertOk()->viewData('students');
        $this->assertEqualsCanonicalizing([$mhs->id, $umum->id], $penerima->pluck('id')->all());

        // Filter role memakai nilai enum; nilai lama 'student' diabaikan (tidak menyaring apa pun).
        $props = fn (string $url) => json_decode(preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $this->actingAs($admin)->get($url)->getContent(), $m) ? $m[1] : '{}', true)['props'];
        $this->assertSame([$mhs->id], array_column($props(route('admin.users', ['role' => 'mahasiswa_kipk']))['users']['data'], 'id'));
        $this->assertCount(4, $props(route('admin.users', ['role' => 'student']))['users']['data']);
        $this->assertSame(UserRole::values(), array_column($props(route('admin.users'))['roleOptions'], 'value'));

        // Penerbitan sertifikat hanya untuk peserta.
        $this->actingAs($admin)->post(route('admin.certificates.store'), ['user_id' => $juri->id, 'activity_id' => 1, 'issued_date' => '2026-10-20'])
            ->assertSessionHasErrors('user_id');
    }
}
