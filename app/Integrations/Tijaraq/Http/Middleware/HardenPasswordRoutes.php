<?php

namespace App\Integrations\Tijaraq\Http\Middleware;

use App\Integrations\Tijaraq\Services\CustomerProvisioner;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

/**
 * Appended to the "web" and "api" middleware groups. It only acts on the
 * credential endpoints:
 *
 * - forgot-password / reset-password: 5 attempts per minute per IP + email
 * - customers that come from TijaraQ (no staff role) cannot use password
 *   login, forgot-password or reset-password. They get the same generic
 *   response a stranger would get, so nothing reveals whether the account
 *   exists.
 */
class HardenPasswordRoutes
{
    protected const FORGOT = ['auth/forgot-password', 'api/v1/auth/password/email'];
    protected const RESET = ['auth/reset-password'];
    protected const LOGIN = ['auth/login', 'api/v1/auth/login'];

    public function handle(Request $request, Closure $next)
    {
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $isForgot = in_array($path, self::FORGOT, true);
        $isReset = in_array($path, self::RESET, true);
        $isLogin = in_array($path, self::LOGIN, true);

        if (!$isForgot && !$isReset && !$isLogin) {
            return $next($request);
        }

        $email = strtolower(trim((string) $request->input('email')));

        if ($isForgot || $isReset) {
            $this->throttle($request, $email);
        }

        if ($email !== '' && $this->belongsToLockedCustomer($email, $isLogin)) {
            if ($isLogin) {
                throw ValidationException::withMessages([
                    Fortify::username() => [trans('auth.failed')],
                ]);
            }
            if ($isForgot) {
                return app(SuccessfulPasswordResetLinkRequestResponse::class, [
                    'status' => Password::RESET_LINK_SENT,
                ])->toResponse($request);
            }

            return app(FailedPasswordResetResponse::class, [
                'status' => Password::INVALID_TOKEN,
            ])->toResponse($request);
        }

        return $next($request);
    }

    protected function throttle(Request $request, string $email): void
    {
        $key = 'tijaraq:password:' . sha1($request->ip() . '|' . $email);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new ThrottleRequestsException(
                'Too many attempts. Please try again later.',
                null,
                ['Retry-After' => RateLimiter::availableIn($key)],
            );
        }

        RateLimiter::hit($key, 60);
    }

    // true when every account behind this address is a locked customer
    protected function belongsToLockedCustomer(string $email, bool $primaryOnly): bool
    {
        $users = User::query()
            ->where(function ($query) use ($email, $primaryOnly) {
                $query->where('email', $email);
                if (!$primaryOnly) {
                    $query->orWhereHas(
                        'secondaryEmails',
                        fn($q) => $q->where('address', $email),
                    );
                }
            })
            ->get();

        return $users->isNotEmpty() &&
            $users->every(fn(User $u) => CustomerProvisioner::isLockedCustomer($u));
    }
}
