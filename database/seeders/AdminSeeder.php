<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('BOOTSTRAP_ADMIN_EMAIL');
        $password = env('BOOTSTRAP_ADMIN_PASSWORD');
        $clinicName = env('BOOTSTRAP_CLINIC_NAME', 'Clínica principal');
        $clinicSlug = env('BOOTSTRAP_CLINIC_SLUG', 'clinica-principal');

        if (! is_string($email) || ! is_string($password) || $email === '' || $password === '') {
            throw new RuntimeException('BOOTSTRAP_ADMIN_EMAIL e BOOTSTRAP_ADMIN_PASSWORD são obrigatórios.');
        }

        if (app()->environment('production')) {
            throw new RuntimeException('AdminSeeder não pode ser executado em produção.');
        }

        $clinic = Clinic::firstOrCreate(
            ['slug' => $clinicSlug],
            ['name' => $clinicName, 'active' => true],
        );

        User::updateOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => 'Administrador',
                'clinic_id' => $clinic->id,
                'password' => Hash::make($password),
                'active' => true,
            ],
        );
    }
}
