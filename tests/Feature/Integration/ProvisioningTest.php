<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use Common\Auth\Actions\CreateUser;
use Common\Auth\Permissions\Permission;

class ProvisioningTest extends IntegrationTestCase
{
    protected function provisioned(array $identity): User
    {
        $this->signed('GET', 'tickets', ['identity' => $identity])->assertOk();

        return User::where('external_source', 'tijaraq')
            ->where('external_user_id', $identity['user'])
            ->firstOrFail();
    }

    public function test_a_new_verified_merchant_is_created_with_the_primary_email(): void
    {
        $identity = $this->uniqueIdentity(['verified' => '1', 'tenant' => 'company-77']);

        $user = $this->provisioned($identity);

        $this->assertSame($identity['email'], $user->getRawOriginal('email'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($identity['name'], $user->name);
        $this->assertSame('company-77', $user->external_company_id);
        $this->assertSame('user', $user->type);
        $this->assertNull($user->password);
    }

    public function test_an_unverified_address_creates_a_user_with_null_email(): void
    {
        $identity = $this->uniqueIdentity(['verified' => '0']);

        $user = $this->provisioned($identity);

        $this->assertNull($user->getRawOriginal('email'));
        $this->assertSame(
            [$identity['email']],
            $user->secondaryEmails()->pluck('address')->all(),
        );
    }

    public function test_an_agent_email_collision_never_links_to_the_agent(): void
    {
        $agent = $this->makeAgent();
        $identity = $this->uniqueIdentity(['email' => $agent->email, 'verified' => '1']);

        $user = $this->provisioned($identity);

        $this->assertNotSame($agent->id, $user->id);
        $this->assertNull($user->getRawOriginal('email'));
        $this->assertSame([$agent->email], $user->secondaryEmails()->pluck('address')->all());

        $agent->refresh();
        $this->assertNull($agent->external_source);
        $this->assertNull($agent->external_user_id);
        $this->assertSame('agent', $agent->type);
    }

    public function test_an_admin_email_collision_never_links_either(): void
    {
        $admin = (new CreateUser())->execute([
            'email' => 'admin-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Admin',
            'email_verified_at' => now(),
            'permissions' => [
                ['id' => Permission::where('name', 'admin')->value('id')],
            ],
        ]);

        $identity = $this->uniqueIdentity(['email' => $admin->email, 'verified' => '1']);
        $user = $this->provisioned($identity);

        $this->assertNotSame($admin->id, $user->id);
        $this->assertNull($admin->refresh()->external_source);
    }

    public function test_a_verified_unlinked_customer_is_linked(): void
    {
        $customer = (new CreateUser())->execute([
            'email' => 'existing-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Existing Customer',
            'email_verified_at' => now(),
        ]);

        $identity = $this->uniqueIdentity(['email' => $customer->email, 'verified' => '1']);
        $user = $this->provisioned($identity);

        $this->assertSame($customer->id, $user->id);
        $this->assertSame('tijaraq', $customer->refresh()->external_source);
        $this->assertSame($identity['user'], $customer->external_user_id);
    }

    public function test_an_unverified_address_never_links_to_an_existing_customer(): void
    {
        $customer = (new CreateUser())->execute([
            'email' => 'existing-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Existing Customer',
            'email_verified_at' => now(),
        ]);

        $identity = $this->uniqueIdentity(['email' => $customer->email, 'verified' => '0']);
        $user = $this->provisioned($identity);

        $this->assertNotSame($customer->id, $user->id);
        $this->assertNull($customer->refresh()->external_source);
    }

    public function test_a_customer_with_another_external_identity_is_not_linked(): void
    {
        $customer = (new CreateUser())->execute([
            'email' => 'linked-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Linked Customer',
            'email_verified_at' => now(),
        ]);
        $customer->forceFill([
            'external_source' => 'tijaraq',
            'external_user_id' => 'someone-else',
            'external_company_id' => 'company-z',
        ])->save();

        $identity = $this->uniqueIdentity(['email' => $customer->email, 'verified' => '1']);
        $user = $this->provisioned($identity);

        $this->assertNotSame($customer->id, $user->id);
        $this->assertSame('someone-else', $customer->refresh()->external_user_id);
    }

    public function test_name_and_company_are_refreshed_and_the_user_is_not_duplicated(): void
    {
        $identity = $this->uniqueIdentity();
        $first = $this->provisioned($identity);

        $renamed = array_merge($identity, ['name' => 'New Name', 'tenant' => 'company-moved']);
        $second = $this->provisioned($renamed);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('New Name', $second->name);
        $this->assertSame('company-moved', $second->external_company_id);
        $this->assertSame(
            1,
            User::where('external_user_id', $identity['user'])->count(),
        );
    }

    public function test_a_changed_verified_email_is_updated(): void
    {
        $identity = $this->uniqueIdentity();
        $first = $this->provisioned($identity);

        $changed = array_merge($identity, ['email' => 'new-' . uniqid() . '@example.test']);
        $second = $this->provisioned($changed);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($changed['email'], $second->getRawOriginal('email'));
    }

    public function test_provisioning_never_touches_type_or_roles(): void
    {
        $identity = $this->uniqueIdentity();
        $user = $this->provisioned($identity);
        $roles = $user->roles()->pluck('roles.id')->all();

        // same identity again, different name
        $again = $this->provisioned(array_merge($identity, ['name' => 'Other']));

        $this->assertSame('user', $again->type);
        $this->assertSame($roles, $again->roles()->pluck('roles.id')->all());
    }

    public function test_a_staff_account_with_an_external_identity_is_refused(): void
    {
        $identity = $this->uniqueIdentity();
        $user = $this->provisioned($identity);
        $user->forceFill(['type' => 'agent'])->save();

        $this->signed('GET', 'tickets', ['identity' => $identity])
            ->assertStatus(401);
    }
}
