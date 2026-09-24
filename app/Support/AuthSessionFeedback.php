<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\View\View;

final class AuthSessionFeedback
{
    public const LOGIN_DURATION_MS = 1200;

    public const LOGOUT_DURATION_MS = 850;

    public static function login(User $user, string $redirectUrl): View
    {
        $firstName = $user->firstName();

        return view('auth.session-feedback', [
            'mode' => 'login',
            'title' => 'Acesso autorizado',
            'greeting' => 'Bem-vindo, '.$firstName,
            'subtitle' => 'Preparando seu ambiente...',
            'redirectUrl' => $redirectUrl,
            'durationMs' => self::LOGIN_DURATION_MS,
            'productName' => (string) config('app.name'),
        ]);
    }

    public static function logout(string $redirectUrl): View
    {
        return view('auth.session-feedback', [
            'mode' => 'logout',
            'title' => 'Sessão encerrada',
            'greeting' => null,
            'subtitle' => 'Você saiu com segurança.',
            'redirectUrl' => $redirectUrl,
            'durationMs' => self::LOGOUT_DURATION_MS,
            'productName' => (string) config('app.name'),
        ]);
    }
}
