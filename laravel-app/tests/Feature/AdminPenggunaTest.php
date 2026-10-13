<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminPenggunaTest extends TestCase
{
    protected function getSuperAdmin(): User
    {
        return User::where('role', \App\Enums\UserRole::SuperAdmin->value)->first()
            ?? User::factory()->create(['role' => \App\Enums\UserRole::SuperAdmin]);
    }

    protected function getStudent(): User
    {
        return User::where('role', \App\Enums\UserRole::MahasiswaKipk->value)->first()
            ?? User::factory()->create(['role' => \App\Enums\UserRole::MahasiswaKipk]);
    }

    public function test_admin_can_access_admin_pengguna_route(): void
    {
        $admin = $this->getSuperAdmin();
        $this->assertNotNull($admin);

        // Access via route name 'admin.users' and URL '/admin/pengguna'
        $response = $this->actingAs($admin)->get(route('admin.users'));
        
        $response->assertStatus(200);
        $response->assertSee('Data Pengguna');
    }

    public function test_admin_pengguna_search_query_with_ilike(): void
    {
        $admin = $this->getSuperAdmin();
        $this->assertNotNull($admin);

        $student = $this->getStudent();
        $this->assertNotNull($student);

        // Test search query (case-insensitive)
        $response = $this->actingAs($admin)->get(route('admin.users', ['q' => strtolower($student->name)]));
        $response->assertStatus(200);
        $response->assertSee($student->name);
    }

    public function test_admin_can_toggle_block_user(): void
    {
        $admin = $this->getSuperAdmin();
        $student = $this->getStudent();
        $this->assertNotNull($student);

        $initialBlocked = (bool) $student->is_blocked;

        $response = $this->actingAs($admin)->patch(route('admin.users.toggle-block', $student->id));
        $response->assertRedirect();

        $student->refresh();
        $this->assertSame(! $initialBlocked, (bool) $student->is_blocked);

        // Restore initial status
        $student->update(['is_blocked' => $initialBlocked]);
    }
}
