<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a valid, unexpired PIN session and re-validates the user against the
 * DB on every request so a deactivated or deleted user is locked out
 * immediately. Ported from Amaira / revenue_laravel.
 */
class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $ok = hasUsers() && isAuthenticated();

        if ($ok) {
            try {
                $stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? AND active = 1");
                $stmt->execute([currentUserId()]);
                $user = $stmt->fetch();
                if (!$user) {
                    $ok = false;
                } else {
                    // Keep session name/admin flag fresh (admin may have edited this user)
                    session([
                        'user_name' => $user['name'],
                        'is_admin'  => (int) $user['is_admin'] === 1,
                    ]);
                }
            } catch (\Throwable $e) {
                $ok = false;
            }
        }

        if (!$ok) {
            authLogout();
            // Store the full absolute URL (not the raw request URI): when the app
            // runs from a subfolder, getRequestUri() already contains the base
            // path, and redirect() would prepend it again → /ad-manage/ad-manage.
            // A full URL is passed through by redirect() unchanged.
            if ($request->isMethod('get')) {
                session(['redirect_after_login' => $request->fullUrl()]);
            }
            return redirect('/login');
        }

        return $next($request);
    }
}
