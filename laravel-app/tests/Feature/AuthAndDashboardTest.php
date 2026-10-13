<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthAndDashboardTest extends TestCase
{
    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_student_can_login_and_access_dashboard(): void
    {
        $student = User::where('role', \App\Enums\UserRole::MahasiswaKipk->value)->first()
            ?? User::factory()->create(['role' => \App\Enums\UserRole::MahasiswaKipk]);
        $student->update(['password' => \Illuminate\Support\Facades\Hash::make('Mahasiswa2026!')]);
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
        $admin = User::where('role', \App\Enums\UserRole::SuperAdmin->value)->first()
            ?? User::factory()->create(['role' => \App\Enums\UserRole::SuperAdmin]);
        $admin->update(['password' => \Illuminate\Support\Facades\Hash::make('AdminSemadiksi2026!')]);
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
        $student = User::where('role', \App\Enums\UserRole::MahasiswaKipk->value)->where('is_blocked', false)->first();
        $response = $this->actingAs($student)->get(route('admin.dashboard'));
        
        $response->assertRedirect(route('admin.login'));
    }
}
