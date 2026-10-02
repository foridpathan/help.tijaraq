<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Session\Middleware\AuthenticateSession as BaseAuthenticateSession;

/**
 * Replacement for Sanctum's AuthenticateSession.
 *
 * Sanctum 4.2 compares the raw password hash with the session value while
 * Laravel 12 stores an HMAC of it, which logged every user out on the next
 * request. Laravel's own middleware understands both formats, but it asks
 * the DEFAULT guard for session methods (viaRemember, logoutCurrentDevice).
 * When a request is authenticated through the "sanctum" guard that guard is
 * a RequestGuard without them, so always talk to the session guard here and
 * only act when there actually is a session login.
 */
class AuthenticateSession extends BaseAuthenticateSession
{
    public function handle($request, Closure $next)
    {
        // token authenticated requests have no session login to verify
        if (!$request->hasSession() || !$request->user() || !$this->guard()->check()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    protected function guard()
    {
        return $this->auth->guard('web');
    }
}
