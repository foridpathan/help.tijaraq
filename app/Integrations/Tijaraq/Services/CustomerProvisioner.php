<?php

namespace App\Integrations\Tijaraq\Services;

use App\Models\User;
use Common\Auth\Actions\CreateUser;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Just-in-time customer provisioning for merchants coming from TijaraQ.
 *
 * - Never links to (or modifies) an agent or admin account.
 * - Links to an existing non-agent user by email only when the email is
 *   verified and that user has no external identity yet.
 * - Otherwise creates a user with email=null and keeps the address as a
 *   secondary email.
 * - Never touches agent type or roles of an existing account.
 */
class CustomerProvisioner
{
    public const SOURCE = 'tijaraq';

    public function provision(
        string $tenant,
        string $externalUserId,
        string $email,
        string $name,
        bool $emailVerified,
    ): User {
        $user = $this->findByExternalId($externalUserId);

        if ($user) {
            return $this->refresh($user, $tenant, $email, $name, $emailVerified);
        }

        try {
            return $this->createOrLink(
                $tenant,
                $externalUserId,
                $email,
                $name,
                $emailVerified,
            );
        } catch (UniqueConstraintViolationException $e) {
            // concurrent request created the same external user
            $user = $this->findByExternalId($externalUserId);
            if (!$user) {
                throw $e;
            }
            return $this->refresh($user, $tenant, $email, $name, $emailVerified);
        }
    }

    public static function isProtected(User $user): bool
    {
        return $user->isAgent() || $user->hasExactPermission('admin');
    }

    // a customer that came from TijaraQ and has no staff role
    public static function isLockedCustomer(User $user): bool
    {
        return $user->external_source === self::SOURCE &&
            !self::isProtected($user);
    }

    protected function findByExternalId(string $externalUserId): ?User
    {
        return User::query()
            ->where('external_source', self::SOURCE)
            ->where('external_user_id', $externalUserId)
            ->first();
    }

    protected function createOrLink(
        string $tenant,
        string $externalUserId,
        string $email,
        string $name,
        bool $emailVerified,
    ): User {
        $existing = User::query()->where('email', $email)->first();

        if (
            $existing &&
            $emailVerified &&
            !self::isProtected($existing) &&
            !$existing->external_user_id &&
            !$existing->external_source
        ) {
            $existing
                ->forceFill([
                    'external_source' => self::SOURCE,
                    'external_user_id' => $externalUserId,
                    'external_company_id' => $tenant,
                ])
                ->save();
            return $existing;
        }

        // verified address nobody else uses: it can be the primary email
        $usePrimary = $emailVerified && !$existing;

        $params = [
            'name' => $name,
            'type' => 'user',
        ];
        if ($usePrimary) {
            $params['email'] = $email;
            $params['email_verified_at'] = now();
        } else {
            $params['secondary_email'] = $email;
        }

        $user = (new CreateUser())->execute($params);
        $user
            ->forceFill([
                'external_source' => self::SOURCE,
                'external_user_id' => $externalUserId,
                'external_company_id' => $tenant,
            ])
            ->save();

        return $user;
    }

    protected function refresh(
        User $user,
        string $tenant,
        string $email,
        string $name,
        bool $emailVerified,
    ): User {
        $changes = [];

        if ($name !== '' && $user->name !== $name) {
            $changes['name'] = $name;
        }
        if ($user->external_company_id !== $tenant) {
            $changes['external_company_id'] = $tenant;
        }

        $known = $user->getRawOriginal('email') === $email;
        if (!$known) {
            $takenByAnother = User::query()
                ->where('email', $email)
                ->where('id', '!=', $user->id)
                ->exists();

            if ($emailVerified && !$takenByAnother) {
                $changes['email'] = $email;
                $changes['email_verified_at'] = now();
            } elseif (
                !$user->secondaryEmails()->where('address', $email)->exists()
            ) {
                $user->secondaryEmails()->create(['address' => $email]);
            }
        }

        if ($changes) {
            $user->forceFill($changes)->save();
        }

        return $user;
    }
}
