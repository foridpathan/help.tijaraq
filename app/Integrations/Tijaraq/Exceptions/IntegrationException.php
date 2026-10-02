<?php

namespace App\Integrations\Tijaraq\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Renders itself as the contract error envelope:
 * {error:{code,message,details?}}
 */
class IntegrationException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }

    public static function invalidSignature(
        string $message = 'Signature verification failed.',
    ): static {
        return new static('invalid_signature', $message, 401);
    }

    public static function staleTimestamp(): static
    {
        return new static(
            'stale_timestamp',
            'The request timestamp is outside the allowed window.',
            401,
        );
    }

    public static function replayedNonce(): static
    {
        return new static(
            'replayed_nonce',
            'This nonce has already been used.',
            401,
        );
    }

    public static function unknownKey(): static
    {
        return new static('unknown_key', 'Unknown key id.', 401);
    }

    public static function ipNotAllowed(): static
    {
        return new static('ip_not_allowed', 'IP address not allowed.', 403);
    }

    public static function notFound(): static
    {
        return new static('not_found', 'Resource not found.', 404);
    }

    public static function idempotencyConflict(): static
    {
        return new static(
            'idempotency_conflict',
            'This idempotency key was already used with a different request.',
            409,
        );
    }

    public static function ticketLocked(): static
    {
        return new static(
            'ticket_locked',
            'This ticket is locked and cannot be changed.',
            409,
        );
    }

    public static function validation(
        string $message,
        ?array $details = null,
    ): static {
        return new static('validation_failed', $message, 422, $details);
    }

    public static function rateLimited(): static
    {
        return new static('rate_limited', 'Too many requests.', 429);
    }

    public static function maintenance(): static
    {
        return new static(
            'maintenance',
            'The service is temporarily unavailable.',
            503,
        );
    }

    public function render(): JsonResponse
    {
        return static::envelope(
            $this->errorCode,
            $this->getMessage(),
            $this->status,
            $this->details,
        );
    }

    public static function envelope(
        string $code,
        string $message,
        int $status,
        ?array $details = null,
        array $headers = [],
    ): JsonResponse {
        $error = ['code' => $code, 'message' => $message];
        if ($details) {
            $error['details'] = $details;
        }
        return response()->json(['error' => $error], $status, $headers);
    }
}
