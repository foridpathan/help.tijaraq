<?php

namespace App\Integrations\Tijaraq\Http\Middleware;

use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Services\CustomerProvisioner;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use Closure;
use Illuminate\Http\Request;

class ProvisionTijaraqCustomer
{
    public function __construct(protected CustomerProvisioner $provisioner) {}

    public function handle(Request $request, Closure $next)
    {
        $ctx = app(TijaraqContext::class);

        $ctx->user = $this->provisioner->provision(
            tenant: $ctx->tenant,
            externalUserId: $ctx->externalUserId,
            email: $ctx->email,
            name: $ctx->name,
            emailVerified: $ctx->emailVerified,
        );

        // an external identity must never resolve to a staff account
        if (CustomerProvisioner::isProtected($ctx->user)) {
            throw IntegrationException::invalidSignature(
                'Identity cannot be used with the integration API.',
            );
        }

        return $next($request);
    }
}
