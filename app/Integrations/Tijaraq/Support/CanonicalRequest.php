<?php

namespace App\Integrations\Tijaraq\Support;

use Illuminate\Http\Request;

/**
 * Builds the canonical string that is signed with HMAC-SHA256:
 *
 *   UPPERCASE_METHOD
 *   /api/integration/v1/<path>
 *   <sorted RFC3986 query>
 *   <x-tijaraq-* headers except signature, lowercased, sorted, name:value>
 *   <sha256 hex of the raw body>
 */
class CanonicalRequest
{
    public const HEADER_PREFIX = 'x-tijaraq-';
    public const SIGNATURE_HEADER = 'x-tijaraq-signature';

    public static function fromRequest(Request $request): string
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower($name)] = (string) ($values[0] ?? '');
        }

        return self::build(
            method: $request->getMethod(),
            path: $request->getPathInfo(),
            rawQuery: (string) $request->server->get('QUERY_STRING', ''),
            headers: $headers,
            bodyHash: self::bodyHash($request),
        );
    }

    public static function build(
        string $method,
        string $path,
        string $rawQuery,
        array $headers,
        string $bodyHash,
    ): string {
        return implode("\n", [
            strtoupper($method),
            self::normalizePath($path),
            self::canonicalQuery($rawQuery),
            self::canonicalHeaders($headers),
            $bodyHash,
        ]);
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return $path === '/' ? $path : rtrim($path, '/');
    }

    public static function canonicalQuery(string $rawQuery): string
    {
        if ($rawQuery === '') {
            return '';
        }

        $pairs = [];
        foreach (explode('&', $rawQuery) as $part) {
            if ($part === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $pairs[] = [
                urldecode(str_replace('+', '%20', $key)),
                urldecode(str_replace('+', '%20', $value)),
            ];
        }

        usort($pairs, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return implode(
            '&',
            array_map(
                fn($pair) => rawurlencode($pair[0]) .
                    '=' .
                    rawurlencode($pair[1]),
                $pairs,
            ),
        );
    }

    public static function canonicalHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $name = strtolower($name);
            if (
                !str_starts_with($name, self::HEADER_PREFIX) ||
                $name === self::SIGNATURE_HEADER
            ) {
                continue;
            }
            $lines[$name] = $name . ':' . trim((string) $value);
        }
        ksort($lines, SORT_STRING);

        return implode("\n", $lines);
    }

    // multipart uploads sign the raw bytes of the file, everything else the
    // raw request body
    public static function bodyHash(Request $request): string
    {
        $contentType = strtolower(
            (string) $request->headers->get('Content-Type'),
        );

        if (str_starts_with($contentType, 'multipart/form-data')) {
            $file = $request->file('file');
            $path = $file?->getRealPath();
            return $path && is_file($path)
                ? hash_file('sha256', $path)
                : hash('sha256', '');
        }

        return hash('sha256', $request->getContent());
    }

    public static function sign(string $canonical, string $secret): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }
}
