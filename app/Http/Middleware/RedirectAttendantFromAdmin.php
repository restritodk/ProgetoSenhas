<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectAttendantFromAdmin
{
    /**
     * Product rule: ATTENDANT role operates only in the attendant area.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isAttendant() && ! $request->routeIs('attendant.*', 'logout')) {
            return redirect()->route('attendant.panel');
        }

        return $next($request);
    }
}
