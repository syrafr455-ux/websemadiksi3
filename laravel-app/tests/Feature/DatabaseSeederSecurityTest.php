<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@semadiksi.ac.id';

    private const MAHASISWA_EMAILS = ['fauzan@student.unusa.ac.id', 'budi@student.unusa.ac.id'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['seeding.admin_password' => null, 'seeding.mahasiswa_password' => null]);
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    public function test_seeder_refuses_to_run_in_production_even_with_force(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->run();
            $this->fail('DatabaseSeeder seharusnya ditolak di production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak boleh dijalankan di production', $e->getMessage());
        }

        $this->assertSame(0, User::count());
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_seeder_source_has_no_hardcoded_passwords(): void
    {
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertDoesNotMatchRegularExpression('/Hash::make\(\s*[\'"]/', $source);
        $this->assertDoesNotMatchRegularExpression('/[\'"]password[\'"]\s*=>\s*[\'"]/', $source);
        $this->assertDoesNotMatchRegularExpression('/->password\s*=\s*[\'"]/', $source);
    }

    public function test_configured_passwords_are_used_and_never_printed(): void
    {
        config(['seeding.admin_password' => 'Admin-Uji-Rahasia-123', 'seeding.mahasiswa_password' => 'Mhs-Uji-Rahasia-456']);

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
            ->doesntExpectOutputToContain('Admin-Uji-Rahasia-123')
            ->doesntExpectOutputToContain('Mhs-Uji-Rahasia-456')
            ->doesntExpectOutputToContain('Password acak')
            ->assertSuccessful();

        $admin = User::where('email', self::ADMIN_EMAIL)->firstOrFail();
        $this->assertSame(UserRole::SuperAdmin, $admin->role);
        $this->assertTrue(Hash::check('Admin-Uji-Rahasia-123', $admin->password));

        foreach (self::MAHASISWA_EMAILS as $email) {
            $mahasiswa = User::where('email', $email)->firstOrFail();
            $this->assertSame(UserRole::MahasiswaKipk, $mahasiswa->role);
            $this->assertTrue(Hash::check('Mhs-Uji-Rahasia-456', $mahasiswa->password));
        }
    }

    public function test_random_passwords_are_generated_and_shown_when_not_configured(): void
    {
        Str::createRandomStringsUsingSequence([
            'AcakAdmin000000000000000',
            'AcakFauzan00000000000000',
            'AcakBudi0000000000000000',
        ]);

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
            ->expectsOutputToContain('Password acak dibuat untuk akun demo berikut')
            ->expectsOutputToContain('AcakAdmin000000000000000')
            ->expectsOutputToContain('AcakFauzan00000000000000')
            ->expectsOutputToContain('AcakBudi0000000000000000')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('AcakAdmin000000000000000', User::where('email', self::ADMIN_EMAIL)->value('password')));
        $this->assertTrue(Hash::check('AcakFauzan00000000000000', User::where('email', self::MAHASISWA_EMAILS[0])->value('password')));
        $this->assertTrue(Hash::check('AcakBudi0000000000000000', User::where('email', self::MAHASISWA_EMAILS[1])->value('password')));
    }

    public function test_existing_account_keeps_password_when_not_configured(): void
    {
        User::factory()->superAdmin()->create([
            'email' => self::ADMIN_EMAIL,
            'password' => Hash::make('Password-Lama-Admin'),
        ]);

        Str::createRandomStringsUsingSequence(['AcakFauzan00000000000000', 'AcakBudi0000000000000000']);

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
            ->expectsOutputToContain('Akun admin@semadiksi.ac.id sudah ada; password tidak diubah.')
            ->expectsOutputToContain('AcakFauzan00000000000000')
            ->assertSuccessful();

        $admin = User::where('email', self::ADMIN_EMAIL)->firstOrFail();
        $this->assertTrue(Hash::check('Password-Lama-Admin', $admin->password));
        $this->assertSame('Administrator SEMADIKSI', $admin->name);
    }

    public function test_existing_account_password_is_replaced_when_configured(): void
    {
        User::factory()->superAdmin()->create([
            'email' => self::ADMIN_EMAIL,
            'password' => Hash::make('Password-Lama-Admin'),
        ]);

        config(['seeding.admin_password' => 'Password-Baru-Admin']);

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
            ->doesntExpectOutputToContain('Password-Baru-Admin')
            ->assertSuccessful();

        $admin = User::where('email', self::ADMIN_EMAIL)->firstOrFail();
        $this->assertTrue(Hash::check('Password-Baru-Admin', $admin->password));
        $this->assertFalse(Hash::check('Password-Lama-Admin', $admin->password));
    }
}
