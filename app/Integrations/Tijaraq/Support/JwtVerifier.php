<?php

namespace App\Integrations\Tijaraq\Support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Verifies the RS256 SSO token sent by app.tijaraq.com.
 *
 * Public keys live in <public_keys_path>/<kid>.pem so signing keys can be
 * rotated by adding a new kid.
 */
class JwtVerifier
{
    public const LEEWAY = 10;
    public const MAX_LIFETIME = 60;

    /**
     * @return array{sub:string,cid:string,email:string,email_verified:bool,name:string,scope:string,jti:string}
     * @throws RuntimeException message is for logs only, never shown to users
     */
    public function verify(string $token): array
    {
        $kid = $this->kidFromHeader($token);
        $key = new Key($this->publicKey($kid), 'RS256');

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = self::LEEWAY;
        try {
            // signature, nbf, iat and exp are validated by the library
            $claims = (array) JWT::decode($token, $key);
        } catch (Throwable $e) {
            throw new RuntimeException('token rejected: ' . $e->getMessage(), 0, $e);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $this->assertClaims($claims);

        // single-use
        $jti = (string) $claims['jti'];
        if (!Cache::add('tijaraq:sso:jti:' . hash('sha256', $jti), 1, 300)) {
            throw new RuntimeException('token already used');
        }

        return [
            'sub' => (string) $claims['sub'],
            'cid' => (string) $claims['cid'],
            'email' => (string) $claims['email'],
            'email_verified' => $this->toBool($claims['email_verified'] ?? false),
            'name' => trim((string) ($claims['name'] ?? '')) ?: 'Customer',
            'scope' => (string) ($claims['scope'] ?? 'own'),
            'jti' => $jti,
        ];
    }

    protected function assertClaims(array $claims): void
    {
        $config = config('tijaraq-integration.sso');

        if (($claims['iss'] ?? null) !== $config['issuer']) {
            throw new RuntimeException('bad issuer');
        }

        $aud = $claims['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];
        if (!in_array($config['audience'], $audiences, true)) {
            throw new RuntimeException('bad audience');
        }

        foreach (['sub', 'cid', 'email', 'jti', 'iat', 'exp', 'nbf'] as $claim) {
            if (!isset($claims[$claim]) || $claims[$claim] === '') {
                throw new RuntimeException("missing claim $claim");
            }
        }

        if ((int) $claims['exp'] - (int) $claims['iat'] > self::MAX_LIFETIME) {
            throw new RuntimeException('token lifetime too long');
        }

        if (
            strlen((string) $claims['sub']) > 64 ||
            strlen((string) $claims['cid']) > 64 ||
            !filter_var($claims['email'], FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException('invalid identity claims');
        }
    }

    protected function kidFromHeader(string $token): string
    {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            throw new RuntimeException('malformed token');
        }

        $header = json_decode(
            (string) base64_decode(strtr($segments[0], '-_', '+/'), true),
            true,
        );

        if (!is_array($header) || ($header['alg'] ?? null) !== 'RS256') {
            throw new RuntimeException('unsupported algorithm');
        }

        $kid = $header['kid'] ?? null;
        // kid becomes a file name: keep it strictly to safe characters
        if (!is_string($kid) || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $kid) || str_contains($kid, '..')) {
            throw new RuntimeException('invalid kid');
        }

        return $kid;
    }

    protected function publicKey(string $kid): string
    {
        $dir = config('tijaraq-integration.sso.public_keys_path');
        if (!$dir) {
            throw new RuntimeException('no public key path configured');
        }
        if (!str_starts_with($dir, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $dir)) {
            $dir = base_path($dir);
        }

        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $kid . '.pem';
        if (!is_file($path)) {
            throw new RuntimeException("unknown kid $kid");
        }

        return (string) file_get_contents($path);
    }

    protected function toBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
