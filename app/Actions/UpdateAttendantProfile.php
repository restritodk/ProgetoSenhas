<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateAttendantProfile
{
    public const MAX_AVATAR_KB = 2048;

    /**
     * @param  array{name?: string, email?: string, current_password?: string}  $data
     */
    public function updateIdentity(User $actor, array $data): User
    {
        $name = isset($data['name']) ? trim((string) $data['name']) : $actor->name;
        $email = isset($data['email']) ? Str::lower(trim((string) $data['email'])) : $actor->email;

        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages([
                'name' => 'Informe um nome válido (até 120 caracteres).',
            ]);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Informe um e-mail válido.',
            ]);
        }

        $emailChanged = $email !== $actor->email;
        if ($emailChanged) {
            $password = (string) ($data['current_password'] ?? '');
            if ($password === '' || ! password_verify($password, (string) $actor->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Confirme com a senha atual para alterar o e-mail.',
                ]);
            }

            $taken = User::query()
                ->where('email', $email)
                ->whereKeyNot($actor->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'email' => 'Este e-mail já está em uso.',
                ]);
            }
        }

        $actor->forceFill([
            'name' => $name,
            'email' => $email,
        ])->save();

        return $actor->refresh();
    }

    public function updatePassword(User $actor, string $currentPassword, string $newPassword): void
    {
        if (! password_verify($currentPassword, (string) $actor->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha atual está incorreta.',
            ]);
        }

        if (mb_strlen($newPassword) < 8) {
            throw ValidationException::withMessages([
                'password' => 'A nova senha deve ter pelo menos 8 caracteres.',
            ]);
        }

        $actor->forceFill([
            'password' => $newPassword,
        ])->save();
    }

    public function storeAvatar(User $actor, UploadedFile $file): User
    {
        $this->assertValidAvatar($file);

        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages([
                'avatar' => 'Use uma imagem JPEG, PNG ou WEBP.',
            ]),
        };

        $directory = User::AVATAR_DIRECTORY.'/'.($actor->clinic_id ?? '0');
        $filename = Str::lower(Str::random(40)).'.'.$extension;
        $path = $file->storeAs($directory, $filename, User::AVATAR_DISK);

        if ($actor->avatar_path) {
            Storage::disk(User::AVATAR_DISK)->delete($actor->avatar_path);
        }

        $actor->forceFill(['avatar_path' => $path])->save();

        return $actor->refresh();
    }

    public function removeAvatar(User $actor): User
    {
        if ($actor->avatar_path) {
            Storage::disk(User::AVATAR_DISK)->delete($actor->avatar_path);
            $actor->forceFill(['avatar_path' => null])->save();
        }

        return $actor->refresh();
    }

    private function assertValidAvatar(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'avatar' => 'Não foi possível enviar a imagem.',
            ]);
        }

        if ($file->getSize() > self::MAX_AVATAR_KB * 1024) {
            throw ValidationException::withMessages([
                'avatar' => 'A imagem deve ter no máximo 2 MB.',
            ]);
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages([
                'avatar' => 'Use uma imagem JPEG, PNG ou WEBP.',
            ]);
        }
    }
}
