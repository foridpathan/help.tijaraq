<?php

namespace App\Integrations\Tijaraq\Services;

use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Models\IdempotencyKey;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use App\Integrations\Tijaraq\Support\Uuid;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /tickets and POST /tickets/{id}/replies are idempotent per
 * (tenant, X-Tijaraq-Idempotency-Key) for 24 h.
 *
 * - same key + same request  -> original response + replay header
 * - same key + other request -> 409 idempotency_conflict
 *
 * The key row is inserted in the same DB transaction as the action, so a
 * failed action leaves no key behind and concurrent duplicates block on the
 * unique index until the first one commits.
 */
class IdempotentExecutor
{
    public const REPLAY_HEADER = 'X-Tijaraq-Idempotent-Replay';

    /**
     * @param Closure():array{0:int,1:array} $action returns [status, body]
     */
    public function run(
        TijaraqContext $ctx,
        Request $request,
        Closure $action,
    ): JsonResponse {
        $key = $ctx->idempotencyKey;
        if (!$key || !Uuid::isV4($key)) {
            throw IntegrationException::validation(
                'A valid X-Tijaraq-Idempotency-Key header is required.',
                ['idempotency_key' => ['Required UUIDv4.']],
            );
        }

        $hash = hash(
            'sha256',
            $request->getMethod() .
                ' ' .
                $request->getPathInfo() .
                "\n" .
                $request->getContent(),
        );

        $this->forgetExpired($ctx->tenant, $key);

        if ($existing = $this->find($ctx->tenant, $key)) {
            return $this->replay($existing, $hash);
        }

        try {
            return DB::transaction(function () use ($ctx, $key, $hash, $action) {
                $row = IdempotencyKey::create([
                    'external_company_id' => $ctx->tenant,
                    'key' => $key,
                    'request_hash' => $hash,
                    'created_at' => now(),
                ]);

                [$status, $body] = $action();

                $row->update([
                    'response_status' => $status,
                    'response_body' => $body,
                ]);

                return response()->json($body, $status);
            });
        } catch (UniqueConstraintViolationException $e) {
            // a concurrent request with the same key committed first
            $existing = $this->find($ctx->tenant, $key);
            if (!$existing) {
                throw $e;
            }
            return $this->replay($existing, $hash);
        }
    }

    protected function replay(IdempotencyKey $row, string $hash): JsonResponse
    {
        if (!hash_equals($row->request_hash, $hash)) {
            throw IntegrationException::idempotencyConflict();
        }

        return response()->json(
            $row->response_body ?? [],
            $row->response_status ?? 200,
            [self::REPLAY_HEADER => '1'],
        );
    }

    protected function find(string $tenant, string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('external_company_id', $tenant)
            ->where('key', $key)
            ->first();
    }

    protected function forgetExpired(string $tenant, string $key): void
    {
        IdempotencyKey::query()
            ->where('external_company_id', $tenant)
            ->where('key', $key)
            ->where(
                'created_at',
                '<',
                now()->subHours(
                    (int) config('tijaraq-integration.idempotency_ttl_hours', 24),
                ),
            )
            ->delete();
    }

    public static function prune(): int
    {
        return IdempotencyKey::query()
            ->where(
                'created_at',
                '<',
                now()->subHours(
                    (int) config('tijaraq-integration.idempotency_ttl_hours', 24),
                ),
            )
            ->delete();
    }
}
