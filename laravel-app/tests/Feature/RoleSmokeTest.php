<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: UserRole|null}>
     */
    public static function roleProvider(): array
    {
        return [
            'tamu' => [null],
            'mahasiswa_kipk' => [UserRole::MahasiswaKipk],
            'user_umum' => [UserRole::UserUmum],
            'verifikator_berkas' => [UserRole::VerifikatorBerkas],
            'penilai_karya' => [UserRole::PenilaiKarya],
            'super_admin' => [UserRole::SuperAdmin],
        ];
    }

    #[DataProvider('roleProvider')]
    public function test_parameterless_get_routes_do_not_return_500(?UserRole $role): void
    {
        $user = null;
        if ($role !== null) {
            $user = User::factory()->create([
                'role' => $role,
                'is_blocked' => false,
                'email_verified_at' => now(),
            ]);
        }

        $routes = Route::getRoutes();
        $uris = [];

        foreach ($routes as $route) {
            if (in_array('GET', $route->methods(), true)) {
                $uri = $route->uri();
                if (! str_contains($uri, '{')) {
                    $uris[] = '/'.ltrim($uri, '/');
                }
            }
        }

        $uris = array_unique($uris);
        $this->assertNotEmpty($uris);

        foreach ($uris as $uri) {
            $testClient = $user !== null
                ? $this->actingAs($user)->withSession(['two_factor_authenticated' => true])
                : $this;

            $response = $testClient->get($uri);
            $status = $response->status();

            $roleName = $role?->value ?? 'tamu';
            $this->assertNotSame(
                500,
                $status,
                "Route [{$uri}] menghasilkan HTTP 500 untuk role [{$roleName}]. Response: ".substr($response->getContent(), 0, 500)
            );
        }
    }
}
