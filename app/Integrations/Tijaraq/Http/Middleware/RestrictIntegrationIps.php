<?php

namespace App\Integrations\Tijaraq\Http\Middleware;

use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class RestrictIntegrationIps
{
    public function handle(Request $request, Closure $next)
    {
        $allowed = config('tijaraq-integration.allowed_ips', []);

        // empty allow-list disables the check. $request->ip() relies on the
        // trusted proxy configuration being correct.
        if (!empty($allowed) && !IpUtils::checkIp((string) $request->ip(), $allowed)) {
            throw IntegrationException::ipNotAllowed();
        }

        return $next($request);
    }
}
