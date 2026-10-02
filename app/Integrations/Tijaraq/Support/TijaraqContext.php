<?php

namespace App\Integrations\Tijaraq\Support;

use App\Models\User;

/**
 * Verified identity of a signed integration request. Created by
 * VerifyHmacSignature, completed by ProvisionTijaraqCustomer.
 */
class TijaraqContext
{
    public const SCOPE_OWN = 'own';
    public const SCOPE_COMPANY = 'company';

    public ?User $user = null;

    public function __construct(
        public readonly string $tenant,
        public readonly string $externalUserId,
        public readonly string $email,
        public readonly string $name,
        public readonly bool $emailVerified,
        public readonly string $scope,
        public readonly string $requestId,
        public readonly ?string $idempotencyKey = null,
    ) {}

    public function isCompanyScope(): bool
    {
        return $this->scope === self::SCOPE_COMPANY;
    }

    public static function current(): ?self
    {
        return app()->bound(self::class) ? app(self::class) : null;
    }
}
