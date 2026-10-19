<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Monolog\Handler\NullHandler;
use Tests\TestCase;

class LoginRedirectAndTestLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_path_redirects_to_masuk(): void
    {
        $this->get('/login')->assertRedirect('/masuk');
        $this->followingRedirects()->get('/login')->assertOk()->assertSee('name="identity"', false);
    }

    public function test_logged_in_user_opening_login_still_ends_outside_login_page(): void
    {
        $mahasiswa = User::factory()->role(UserRole::MahasiswaKipk)->create();

        $this->actingAs($mahasiswa)->get('/login')->assertRedirect('/masuk');
        $this->actingAs($mahasiswa)->get('/masuk')->assertRedirect();
    }

    public function test_tests_do_not_write_to_laravel_log(): void
    {
        // LOG_CHANNEL=null di phpunit.xml: env() mengubah "null" menjadi null dan LogManager memakai kanal 'null'.
        $handlers = logger()->getLogger()->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(NullHandler::class, $handlers[0]);

        $log = storage_path('logs/laravel.log');
        $ukuranSebelum = is_file($log) ? filesize($log) : 0;

        logger()->error('Pesan uji yang tidak boleh masuk laravel.log');
        clearstatcache();

        $this->assertSame($ukuranSebelum, is_file($log) ? filesize($log) : 0);
    }
}
