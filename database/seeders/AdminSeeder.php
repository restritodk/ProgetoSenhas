<?php

namespace Database\Seeders;

use App\Actions\EnsureDefaultTicketTypes;
use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('bootstrap.admin_email');
        $password = config('bootstrap.admin_password');
        $clinicName = (string) config('bootstrap.clinic_name');
        $clinicSlug = (string) config('bootstrap.clinic_slug');
        $unitName = (string) config('bootstrap.unit_name');
        $unitSlug = (string) config('bootstrap.unit_slug');

        if (! is_string($email) || ! is_string($password) || $email === '' || $password === '') {
            throw new RuntimeException('BOOTSTRAP_ADMIN_EMAIL e BOOTSTRAP_ADMIN_PASSWORD são obrigatórios.');
        }

        if (app()->isProduction()) {
            throw new RuntimeException('AdminSeeder não pode ser executado em produção.');
        }

        $clinic = Clinic::query()->firstOrCreate(
            ['slug' => $clinicSlug],
            ['name' => $clinicName, 'active' => true],
        );

        $unit = Unit::query()->firstOrCreate(
            ['clinic_id' => $clinic->id, 'slug' => $unitSlug],
            ['name' => $unitName, 'active' => true],
        );

        app(EnsureDefaultTicketTypes::class)->handle($clinic);

        $user = User::query()->firstOrNew([
            'email' => Str::lower($email),
        ]);

        $user->forceFill([
            'name' => 'Administrador',
            'clinic_id' => $clinic->id,
            'password' => $password,
            'active' => true,
            'role' => UserRole::ADMINISTRATOR,
        ])->save();

        $user->units()->syncWithoutDetaching([
            $unit->id => ['clinic_id' => $clinic->id],
        ]);
    }
}
