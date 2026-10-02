<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Merchants from TijaraQ only come in through SSO. Agents and admins keep
 * their normal login.
 */
class LoginLockdownTest extends IntegrationTestCase
{
    protected function merchantWithPassword(string $password = 'Secret-12345'): User
    {
        $identity = $this->uniqueIdentity();
        $this->signed('GET', 'tickets', ['identity' => $identity])->assertOk();

        $user = User::where('external_user_id', $identity['user'])->firstOrFail();
        // a password could get set in many ways (admin, old data); it must
        // still not work for password login
        $user->forceFill(['password' => Hash::make($password)])->save();

        return $user->refresh();
    }

    public function test_a_tijaraq_customer_cannot_use_password_login(): void
    {
        $user = $this->merchantWithPassword();

        $this->postJson('/auth/login', [
            'email' => $user->getRawOriginal('email'),
            'password' => 'Secret-12345',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_the_failure_is_identical_to_a_wrong_password(): void
    {
        $user = $this->merchantWithPassword();
        $agent = $this->makeAgent();

        $locked = $this->postJson('/auth/login', [
            'email' => $user->getRawOriginal('email'),
            'password' => 'Secret-12345',
        ]);
        $wrong = $this->postJson('/auth/login', [
            'email' => $agent->email,
            'password' => 'definitely-wrong',
        ]);

        $this->assertSame($wrong->status(), $locked->status());
        $this->assertSame($wrong->json('errors.email'), $locked->json('errors.email'));
    }

    public function test_the_mobile_login_endpoint_is_locked_as_well(): void
    {
        $user = $this->merchantWithPassword();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->getRawOriginal('email'),
            'password' => 'Secret-12345',
            'token_name' => 'phone',
        ])->assertStatus(422);

        $this->assertGuest('web');
    }

    public function test_an_agent_can_still_log_in(): void
    {
        $agent = $this->makeAgent();

        $this->postJson('/auth/login', [
            'email' => $agent->email,
            'password' => 'Secret-12345',
        ])->assertSuccessful();

        $this->assertAuthenticatedAs($agent, 'web');
    }

    public function test_forgot_password_looks_successful_but_sends_nothing_to_a_tijaraq_customer(): void
    {
        Notification::fake();
        $user = $this->merchantWithPassword();

        $locked = $this->postJson('/auth/forgot-password', [
            'email' => $user->getRawOriginal('email'),
        ]);

        // same answer a real account gets: nothing to enumerate
        $agent = $this->makeAgent();
        $real = $this->postJson('/auth/forgot-password', ['email' => $agent->email]);

        $this->assertSame($real->status(), $locked->status());
        Notification::assertNothingSentTo($user);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->getRawOriginal('email')]);
    }

    public function test_forgot_password_also_covers_the_secondary_email(): void
    {
        Notification::fake();
        $identity = $this->uniqueIdentity(['verified' => '0']);
        $this->signed('GET', 'tickets', ['identity' => $identity])->assertOk();
        $user = User::where('external_user_id', $identity['user'])->firstOrFail();

        $this->postJson('/auth/forgot-password', ['email' => $identity['email']]);

        Notification::assertNothingSentTo($user);
    }

    public function test_reset_password_fails_like_an_invalid_token_for_a_tijaraq_customer(): void
    {
        $user = $this->merchantWithPassword();
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/auth/reset-password', [
            'token' => $token,
            'email' => $user->getRawOriginal('email'),
            'password' => 'N3w-Passw0rd!x',
            'password_confirmation' => 'N3w-Passw0rd!x',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('Secret-12345', $user->refresh()->password));
    }

    public function test_an_agent_can_still_reset_a_password(): void
    {
        $agent = $this->makeAgent();
        $token = Password::broker()->createToken($agent);

        $this->postJson('/auth/reset-password', [
            'token' => $token,
            'email' => $agent->email,
            'password' => 'N3w-Passw0rd!x',
            'password_confirmation' => 'N3w-Passw0rd!x',
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('N3w-Passw0rd!x', $agent->refresh()->password));
    }

    public function test_forgot_and_reset_password_are_throttled_per_ip_and_email(): void
    {
        $email = 'throttle-' . uniqid() . '@example.test';
        RateLimiter::clear('tijaraq:password:' . sha1('127.0.0.1|' . $email));

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/forgot-password', ['email' => $email])->assertStatus(
                $this->postJsonStatusIsNotThrottled(),
            );
        }

        $this->postJson('/auth/forgot-password', ['email' => $email])->assertStatus(429);
        $this->postJson('/auth/reset-password', [
            'token' => 'x', 'email' => $email, 'password' => 'a', 'password_confirmation' => 'a',
        ])->assertStatus(429);

        // another email is not affected
        $this->postJson('/auth/forgot-password', ['email' => 'other-' . uniqid() . '@example.test'])
            ->assertStatus($this->postJsonStatusIsNotThrottled());
    }

    // validation/"user not found" answers differ by setup, only 429 matters
    protected function postJsonStatusIsNotThrottled(): int
    {
        return 422;
    }
}
