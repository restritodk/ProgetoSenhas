<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserPresence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
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

        if ($user?->isAttendant()) {
            return redirect()->route('attendant.panel');
        }

        $destination = $user?->canAccessAttendantPanel() && ! $user->isAdministrator() && ! $user->isSupervisor()
            ? route('attendant.panel')
            : route('dashboard');

        return redirect()->intended($destination);
    }

    public function destroy(Request $request, UserPresence $presence): RedirectResponse
    {
        $user = Auth::user();

        if ($user !== null) {
            $presence->markOffline($user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
