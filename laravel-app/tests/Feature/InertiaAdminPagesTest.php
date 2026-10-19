<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Halaman React/Inertia admin harus mengirim halaman awal dalam format yang dibaca
 *
 * @inertiajs/react v3: <script data-page="app" type="application/json"> + <div id="app">.
 */
class InertiaAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create(['name' => 'Admin Uji']);
    }

    /**
     * Ambil objek halaman Inertia dari HTML awal persis seperti client v3 membacanya.
     */
    protected function initialPage(string $html): array
    {
        $this->assertStringContainsString('<div id="app"></div>', $html);
        $this->assertSame(1, preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $match),
            'Elemen <script data-page="app" type="application/json"> tidak ditemukan.');

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public static function adminPages(): array
    {
        return [
            'pengguna' => ['/admin/pengguna', 'Admin/Pengguna/Index', ['title', 'users', 'filters', 'stats', 'roleOptions']],
            'kegiatan' => ['/admin/kegiatan', 'Admin/Kegiatan/Index', ['title', 'kegiatans', 'filters', 'unusaFacilities', 'statusOptions']],
            'penilaian' => ['/admin/penilaian', 'Admin/Penilaian/Index', ['title', 'kegiatans', 'selectedKegiatanId', 'submissions', 'filters']],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_admin_page_renders_inertia_v3_initial_page_for_super_admin(string $path, string $component, array $propKeys): void
    {
        $response = $this->actingAs($this->admin)->get($path);

        $response->assertOk();
        $page = $this->initialPage($response->getContent());

        $this->assertSame($component, $page['component']);
        $this->assertSame($path, $page['url']);
        foreach (array_merge($propKeys, ['auth', 'flash', 'errors']) as $key) {
            $this->assertArrayHasKey($key, $page['props'], "Prop '{$key}' tidak ada di {$component}.");
        }
        $this->assertSame('Admin Uji', $page['props']['auth']['user']['name']);
        $this->assertSame(UserRole::SuperAdmin->value, $page['props']['auth']['user']['role']);

        // Shared prop bersarang (closure) harus sudah di-resolve: null, bukan objek kosong {}.
        $this->assertSame(['success' => null, 'error' => null, 'info' => null], $page['props']['flash']);

        // Respons kunjungan Inertia (X-Inertia) memakai komponen yang sama.
        $this->actingAs($this->admin)
            ->get($path, ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $page['version']])
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', $component)
            ->assertJsonPath('props.flash.success', null);
    }

    public function test_pengguna_page_lists_users_in_props(): void
    {
        User::factory()->role(UserRole::MahasiswaKipk)->create(['email' => 'mhs.uji@student.unusa.ac.id']);

        $page = $this->initialPage($this->actingAs($this->admin)->get('/admin/pengguna')->getContent());

        $emails = array_column($page['props']['users']['data'], 'email');
        $this->assertContains('mhs.uji@student.unusa.ac.id', $emails);
        $this->assertSame(2, $page['props']['stats']['total']);
    }

    public function test_flash_message_is_sent_as_string(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['success' => 'Kegiatan berhasil diperbarui.'])
            ->get('/admin/kegiatan');

        $page = $this->initialPage($response->getContent());

        $this->assertSame('Kegiatan berhasil diperbarui.', $page['props']['flash']['success']);
    }

    public function test_script_tag_in_props_cannot_break_out_of_initial_page_element(): void
    {
        $this->admin->update(['name' => 'Admin </script><script>alert(1)</script>']);

        $html = $this->actingAs($this->admin)->get('/admin/pengguna')->getContent();
        $page = $this->initialPage($html);

        $this->assertSame('Admin </script><script>alert(1)</script>', $page['props']['auth']['user']['name']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
