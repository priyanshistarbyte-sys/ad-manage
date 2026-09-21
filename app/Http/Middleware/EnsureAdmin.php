<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-only guard. Ported from Amaira / revenue_laravel.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!isAdmin()) {
            abort(403, 'Access denied — admin only.');
        }
        return $next($request);
    }
}
