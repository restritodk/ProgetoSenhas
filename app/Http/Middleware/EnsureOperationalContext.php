<?php

namespace App\Http\Middleware;

use App\Services\OperationalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperationalContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(OperationalContext::class);
        $unit = $user ? $context->activeUnit($user, $request->session()) : null;
        $desk = $user ? $context->activeDesk($user, $request->session()) : null;

        if (! $user?->active || ! $user->clinic?->active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        $request->attributes->set('active_unit', $unit);
        $request->attributes->set('active_desk', $desk);

        return $next($request);
    }
}
