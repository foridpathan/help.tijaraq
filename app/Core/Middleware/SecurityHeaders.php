<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // binary downloads set their own headers
        if ($response instanceof BinaryFileResponse) {
            return $response;
        }

        $headers = $response->headers;

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' =>
                'camera=(), microphone=(), geolocation=(), payment=()',
        ];

        if ($request->isSecure()) {
            $defaults['Strict-Transport-Security'] =
                'max-age=31536000; includeSubDomains';
        }

        foreach ($defaults as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        return $response;
    }
}
