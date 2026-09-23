<?php

namespace App\Livewire;

use App\Actions\UpdateAttendantProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class AttendantProfile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public $avatar = null;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->active, 403);

        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function saveIdentity(UpdateAttendantProfile $updateAttendantProfile): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'current_password' => ['nullable', 'string'],
        ]);

        try {
            $updateAttendantProfile->updateIdentity(auth()->user(), [
                'name' => $this->name,
                'email' => $this->email,
                'current_password' => $this->current_password,
            ]);

            $this->current_password = '';
            $this->statusMessage = 'Dados atualizados.';
            $this->errorMessage = '';
            auth()->user()?->refresh();
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível salvar.';
            $this->statusMessage = '';
            throw $exception;
        }
    }

    public function savePassword(UpdateAttendantProfile $updateAttendantProfile): void
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'A confirmação da nova senha não confere.',
        ]);

        try {
            $updateAttendantProfile->updatePassword(
                auth()->user(),
                $this->current_password,
                $this->password,
            );

            $this->current_password = '';
            $this->password = '';
            $this->password_confirmation = '';
            $this->statusMessage = 'Senha alterada com sucesso.';
            $this->errorMessage = '';
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível alterar a senha.';
            $this->statusMessage = '';
            throw $exception;
        }
    }

    public function saveAvatar(UpdateAttendantProfile $updateAttendantProfile): void
    {
        $this->validate([
            'avatar' => ['required', 'image', 'max:'.UpdateAttendantProfile::MAX_AVATAR_KB],
        ], [
            'avatar.required' => 'Selecione uma imagem.',
            'avatar.image' => 'Envie uma imagem válida.',
            'avatar.max' => 'A imagem deve ter no máximo 2 MB.',
        ]);

        try {
            $updateAttendantProfile->storeAvatar(auth()->user(), $this->avatar);
            $this->avatar = null;
            $this->statusMessage = 'Foto atualizada.';
            $this->errorMessage = '';
            auth()->user()?->refresh();
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível enviar a foto.';
            $this->statusMessage = '';
            throw $exception;
        }
    }

    public function removeAvatar(UpdateAttendantProfile $updateAttendantProfile): void
    {
        $updateAttendantProfile->removeAvatar(auth()->user());
        $this->statusMessage = 'Foto removida.';
        $this->errorMessage = '';
        auth()->user()?->refresh();
    }

    public function render(): View
    {
        $user = auth()->user()?->fresh();
        $user?->loadMissing('clinic:id,name');

        return view('livewire.attendant-profile', [
            'user' => $user,
        ]);
    }
}
