<?php

namespace App\Actions;

use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class BootstrapFirstAdministrator
{
    /**
     * One-shot bootstrap for the first clinic administrator.
     * Refuses to run when any administrator already exists.
     *
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     clinic_name: string,
     *     clinic_slug: string,
     *     unit_name: string,
     *     unit_slug: string
     * }  $attributes
     */
    public function handle(array $attributes): User
    {
        if ($this->administratorExists()) {
            throw ValidationException::withMessages([
                'email' => 'Já existe um administrador cadastrado. Use o painel administrativo para gerenciar usuários.',
            ]);
        }

        $validated = $this->validate($attributes);

        return DB::transaction(function () use ($validated): User {
            $clinic = Clinic::query()->firstOrCreate(
                ['slug' => $validated['clinic_slug']],
                [
                    'name' => $validated['clinic_name'],
                    'active' => true,
                ],
            );

            $unit = Unit::query()->firstOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'slug' => $validated['unit_slug'],
                ],
                [
                    'name' => $validated['unit_name'],
                    'active' => true,
                ],
            );

            app(EnsureDefaultTicketTypes::class)->handle($clinic);
            app(EnsureDefaultUnitTicketOffers::class)->handle($clinic, $unit);
            app(EnsureDefaultSectorForUnit::class)->handle($unit);
            app(EnsureClinicRolePermissions::class)->handle($clinic);

            if (User::query()->where('email', $validated['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Já existe um usuário com este e-mail.',
                ]);
            }

            $user = new User;
            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'clinic_id' => $clinic->id,
                'role' => UserRole::ADMINISTRATOR,
                'active' => true,
            ])->save();

            $user->units()->syncWithoutDetaching([
                $unit->id => ['clinic_id' => $clinic->id],
            ]);

            return $user->refresh();
        });
    }

    public function administratorExists(): bool
    {
        return User::query()
            ->where('role', UserRole::ADMINISTRATOR)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     clinic_name: string,
     *     clinic_slug: string,
     *     unit_name: string,
     *     unit_slug: string
     * }
     */
    private function validate(array $attributes): array
    {
        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(8)],
            'clinic_name' => ['required', 'string', 'max:255'],
            'clinic_slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'unit_name' => ['required', 'string', 'max:255'],
            'unit_slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ], [
            'name.required' => 'Informe o nome do administrador.',
            'email.required' => 'Informe o e-mail do administrador.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha do administrador.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'clinic_name.required' => 'Informe o nome da clínica.',
            'clinic_slug.regex' => 'O slug da clínica deve conter apenas letras minúsculas, números e hífens.',
            'unit_name.required' => 'Informe o nome da unidade.',
            'unit_slug.regex' => 'O slug da unidade deve conter apenas letras minúsculas, números e hífens.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{name: string, email: string, password: string, clinic_name: string, clinic_slug: string, unit_name: string, unit_slug: string} $data */
        $data = $validator->validated();
        $data['email'] = Str::lower($data['email']);
        $data['clinic_slug'] = Str::lower($data['clinic_slug']);
        $data['unit_slug'] = Str::lower($data['unit_slug']);

        return $data;
    }
}
