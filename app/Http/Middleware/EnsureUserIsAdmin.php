<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only block signed-in users who lack the required role.
        // Unauthenticated requests pass through so Filament can show its login page.
        $user = auth()->user();

        if ($user && !$user->hasAdminPanelAccess()) {
            abort(403, 'Access denied. Admin privileges required.');
        }

        return $next($request);
    }
}
