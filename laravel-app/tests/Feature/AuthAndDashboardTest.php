<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_student_can_login_and_access_dashboard(): void
    {
        $student = User::where('role', UserRole::MahasiswaKipk->value)->first()
            ?? User::factory()->create(['role' => UserRole::MahasiswaKipk]);
        $student->update(['password' => Hash::make('Mahasiswa2026!')]);
        $this->assertNotNull($student);

        $loginResponse = $this->post(route('login.post'), [
            'identity' => $student->email,
            'password' => 'Mahasiswa2026!',
        ]);

        $loginResponse->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($student);

        $dashResponse = $this->actingAs($student)->get(route('dashboard.index'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee($student->name);
    }

    public function test_admin_can_login_and_access_admin_dashboard(): void
    {
        $admin = User::where('role', UserRole::SuperAdmin->value)->first()
            ?? User::factory()->create(['role' => UserRole::SuperAdmin]);
        $admin->update(['password' => Hash::make('AdminSemadiksi2026!')]);
        $this->assertNotNull($admin);

        $loginResponse = $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'AdminSemadiksi2026!',
        ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $dashResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Ringkasan Utama');
    }

    public function test_student_cannot_access_admin_dashboard(): void
    {
        $student = User::where('role', UserRole::MahasiswaKipk->value)->where('is_blocked', false)->first()
            ?? User::factory()->create(['role' => UserRole::MahasiswaKipk, 'is_blocked' => false]);
        $response = $this->actingAs($student)->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }
}
