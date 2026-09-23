<?php

namespace App\Http\Middleware;

use App\Services\OperationalChatEligibility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendantAreaAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->active || $user->clinic_id === null) {
            abort(403);
        }

        $route = $request->route()?->getName();

        if ($route === 'attendant.profile') {
            return $next($request);
        }

        if ($route === 'attendant.messages' || str_starts_with((string) $route, 'attendant.messages.')) {
            abort_unless(app(OperationalChatEligibility::class)->canUseOperationalChat($user), 403);

            return $next($request);
        }

        abort_unless($user->canAccessAttendantPanel(), 403);

        return $next($request);
    }
}
