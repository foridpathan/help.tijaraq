<?php

namespace App\Integrations\Tijaraq\Http\Middleware;

use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Support\CanonicalRequest;
use App\Integrations\Tijaraq\Support\NonceStore;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use App\Integrations\Tijaraq\Support\Uuid;
use Closure;
use Illuminate\Http\Request;

class VerifyHmacSignature
{
    public function __construct(protected NonceStore $nonces) {}

    public function handle(Request $request, Closure $next)
    {
        $keyId = (string) $request->header('X-Tijaraq-Key-Id');
        $secret = config('tijaraq-integration.api_keys')[$keyId] ?? null;
        if ($keyId === '' || !$secret) {
            throw IntegrationException::unknownKey();
        }

        $this->assertTimestamp($request);

        $signature = strtolower((string) $request->header('X-Tijaraq-Signature'));
        $expected = CanonicalRequest::sign(
            CanonicalRequest::fromRequest($request),
            $secret,
        );
        if ($signature === '' || !hash_equals($expected, $signature)) {
            throw IntegrationException::invalidSignature();
        }

        // the nonce is only consumed by requests with a valid signature so
        // unauthenticated callers cannot burn nonces
        $nonce = (string) $request->header('X-Tijaraq-Nonce');
        if (!Uuid::isV4($nonce)) {
            throw IntegrationException::invalidSignature('Invalid nonce.');
        }
        if (!$this->nonces->claim($nonce)) {
            throw IntegrationException::replayedNonce();
        }

        app()->instance(TijaraqContext::class, $this->buildContext($request));

        return $next($request);
    }

    protected function assertTimestamp(Request $request): void
    {
        $timestamp = (string) $request->header('X-Tijaraq-Timestamp');
        if (!ctype_digit($timestamp)) {
            throw IntegrationException::staleTimestamp();
        }

        $tolerance = (int) config('tijaraq-integration.timestamp_tolerance', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            throw IntegrationException::staleTimestamp();
        }
    }

    protected function buildContext(Request $request): TijaraqContext
    {
        $tenant = trim((string) $request->header('X-Tijaraq-Tenant'));
        $userId = trim((string) $request->header('X-Tijaraq-User'));
        $email = trim((string) $request->header('X-Tijaraq-User-Email'));
        $verified = (string) $request->header('X-Tijaraq-Email-Verified');
        $scope = (string) $request->header('X-Tijaraq-Scope');
        $requestId = (string) $request->header('X-Tijaraq-Request-Id');

        $name = $this->decodeName((string) $request->header('X-Tijaraq-User-Name'));

        $valid =
            $tenant !== '' &&
            strlen($tenant) <= 64 &&
            $userId !== '' &&
            strlen($userId) <= 64 &&
            filter_var($email, FILTER_VALIDATE_EMAIL) &&
            in_array($verified, ['0', '1'], true) &&
            in_array($scope, [TijaraqContext::SCOPE_OWN, TijaraqContext::SCOPE_COMPANY], true) &&
            Uuid::isV4($requestId);

        if (!$valid) {
            throw IntegrationException::invalidSignature(
                'Missing or invalid identity headers.',
            );
        }

        $idempotencyKey = $request->header('X-Tijaraq-Idempotency-Key');

        return new TijaraqContext(
            tenant: $tenant,
            externalUserId: $userId,
            email: $email,
            name: $name,
            emailVerified: $verified === '1',
            scope: $scope,
            requestId: $requestId,
            idempotencyKey: $idempotencyKey ? (string) $idempotencyKey : null,
        );
    }

    protected function decodeName(string $encoded): string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        $name = $decoded === false ? '' : trim($decoded);
        return mb_substr($name !== '' && mb_check_encoding($name, 'UTF-8') ? $name : 'Customer', 0, 191);
    }
}
