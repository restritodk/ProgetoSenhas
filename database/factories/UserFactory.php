<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    protected $model = User::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'active' => true,
            'role' => UserRole::ATTENDANT->value,
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->clinic_id === null) {
                return;
            }

            $unit = Unit::factory()->for($user->clinic)->create();
            $user->units()->attach($unit, ['clinic_id' => $user->clinic_id]);
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
