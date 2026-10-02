<?php

namespace App\Integrations\Tijaraq\Support;

class RedirectSanitizer
{
    /**
     * Only relative paths on this site are allowed. Anything else (absolute
     * URLs, protocol-relative "//host", backslash tricks, control chars)
     * falls back to the default.
     */
    public static function sanitize(?string $redirect, ?string $default = null): string
    {
        $default ??= '/';

        if (
            !is_string($redirect) ||
            $redirect === '' ||
            $redirect[0] !== '/' ||
            str_starts_with($redirect, '//') ||
            str_contains($redirect, '\\') ||
            preg_match('/[\x00-\x1f\x7f]/', $redirect)
        ) {
            return $default;
        }

        // "/%2f/evil.com" style protocol-relative bypasses
        $decoded = rawurldecode($redirect);
        if (str_starts_with($decoded, '//') || str_contains($decoded, '\\')) {
            return $default;
        }

        return $redirect;
    }
}
