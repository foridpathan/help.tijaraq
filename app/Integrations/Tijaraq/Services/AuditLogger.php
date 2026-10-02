<?php

namespace App\Integrations\Tijaraq\Services;

use App\Integrations\Tijaraq\Models\AuditLog;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use Illuminate\Http\Request;

/** One row per mutating call and per attachment download. */
class AuditLogger
{
    public function log(
        string $action,
        TijaraqContext $ctx,
        Request $request,
        ?int $conversationId = null,
        array $meta = [],
    ): void {
        AuditLog::create([
            'external_company_id' => $ctx->tenant,
            'external_user_id' => $ctx->externalUserId,
            'action' => $action,
            'conversation_id' => $conversationId,
            'request_id' => $ctx->requestId,
            'ip' => $request->ip(),
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    // SSO has no signed context
    public function logSso(
        string $action,
        Request $request,
        ?string $tenant = null,
        ?string $externalUserId = null,
        array $meta = [],
    ): void {
        AuditLog::create([
            'external_company_id' => $tenant,
            'external_user_id' => $externalUserId,
            'action' => $action,
            'ip' => $request->ip(),
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }
}
