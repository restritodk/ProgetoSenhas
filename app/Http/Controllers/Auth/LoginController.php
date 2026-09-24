<?php

namespace App\Http\Controllers\Auth;

use App\Actions\ReleaseDesk;
use App\Http\Controllers\Controller;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\UserPresence;
use App\Support\AuthSessionFeedback;
use App\Support\LoginPresentation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create(ClinicBranding $branding, ClinicSettings $settings): View
    {
        return view('auth.login', [
            'loginBranding' => LoginPresentation::forGuest($branding, $settings),
        ]);
    }

    public function store(Request $request): View
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower($credentials['email']);

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password']])) {
            throw ValidationException::withMessages([
                'email' => 'Não foi possível autenticar com essas credenciais.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user === null || ! $user->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Não foi possível autenticar com essas credenciais.',
            ]);
        }

        if ($user->isAttendant()) {
            $destination = route('attendant.panel');
        } else {
            $destination = $user->canAccessAttendantPanel() && ! $user->isAdministrator() && ! $user->isSupervisor()
                ? route('attendant.panel')
                : route('dashboard');
        }

        $intended = $request->session()->pull('url.intended');
        $redirectUrl = is_string($intended) && $intended !== '' ? $intended : $destination;

        return AuthSessionFeedback::login($user, $redirectUrl);
    }

    public function destroy(Request $request, UserPresence $presence, ReleaseDesk $releaseDesk): View
    {
        $user = Auth::user();

        if ($user !== null) {
            $releaseDesk->handle($user);
            $presence->markOffline($user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return AuthSessionFeedback::logout(route('login'));
    }
}
