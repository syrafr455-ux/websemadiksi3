<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'role' => UserRole::UserUmum->value,
            'verification_status' => 'Pending',
            'is_blocked' => false,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role->value]);
    }

    public function superAdmin(): static
    {
        return $this->role(UserRole::SuperAdmin);
    }

    public function mahasiswaKipk(bool $verified = true): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::MahasiswaKipk->value,
            'nim' => fake()->unique()->numerify('22400####'),
            'verification_status' => $verified ? 'Verified' : 'Pending',
        ]);
    }

    public function userUmum(): static
    {
        return $this->role(UserRole::UserUmum);
    }

    public function verifikator(): static
    {
        return $this->role(UserRole::VerifikatorBerkas);
    }

    public function penilai(): static
    {
        return $this->role(UserRole::PenilaiKarya);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
