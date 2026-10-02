<?php

namespace Tests\Feature\Integration;

use App\Integrations\Tijaraq\Services\IntegrationSetup;
use App\Integrations\Tijaraq\Support\JwtVerifier;
use App\Integrations\Tijaraq\Support\Uuid;
use App\Models\User;
use Common\Auth\Actions\CreateUser;
use Common\Auth\Permissions\Permission;
use Firebase\JWT\JWT;

/**
 * The admin dashboard page that generates the keys and writes the helpdesk
 * side. Never touches the real .env: a throw-away env file is used.
 */
class AdminSetupTest extends IntegrationTestCase
{
    protected string $envRelative;
    protected string $keyDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envRelative = 'storage/framework/testing/env-' . uniqid() . '.test';
        @mkdir(base_path('storage/framework/testing'), 0777, true);
        file_put_contents(base_path($this->envRelative), "APP_NAME=Test\n");

        $this->keyDir = sys_get_temp_dir() . '/tijaraq-setup-' . uniqid();
        config(['tijaraq-integration.sso.public_keys_path' => $this->keyDir]);

        app(IntegrationSetup::class)->useEnvFile($this->envRelative);
    }

    protected function tearDown(): void
    {
        @unlink(base_path($this->envRelative));
        foreach (glob($this->keyDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->keyDir);

        parent::tearDown();
    }

    protected function admin(): User
    {
        return (new CreateUser())->execute([
            'email' => 'setup-admin-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Setup Admin',
            'email_verified_at' => now(),
            'type' => 'agent',
            'permissions' => [['id' => Permission::where('name', 'admin')->value('id')]],
        ]);
    }

    protected function asAdmin(): static
    {
        $this->actingAs($this->admin(), 'sanctum');

        return $this->withHeaders([
            'Referer' => config('app.url') . '/',
            'Origin' => config('app.url'),
        ]);
    }

    protected function envFile(): string
    {
        return file_get_contents(base_path($this->envRelative));
    }

    protected function generate(array $overrides = [])
    {
        return $this->asAdmin()->postJson(
            '/api/v1/admin/tijaraq-integration/generate',
            array_merge(
                [
                    'parts' => ['api_key', 'webhook_secret', 'sso_key'],
                    'main_app_url' => 'http://app.tijaraq.test',
                    'helpdesk_url' => 'http://help.tijaraq.test',
                ],
                $overrides,
            ),
        );
    }

    public function test_only_admins_can_use_the_setup_endpoints(): void
    {
        $this->getJson('/api/v1/admin/tijaraq-integration')->assertStatus(401);

        $agent = $this->makeAgent();
        $this->actingAs($agent, 'sanctum');
        $headers = ['Referer' => config('app.url') . '/', 'Origin' => config('app.url')];

        $this->withHeaders($headers)->getJson('/api/v1/admin/tijaraq-integration')->assertStatus(403);
        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/tijaraq-integration/generate', ['parts' => ['api_key']])
            ->assertStatus(403);

        $this->assertStringNotContainsString('TIJARAQ', $this->envFile());
    }

    public function test_generate_creates_every_value_and_writes_the_helpdesk_env(): void
    {
        $response = $this->generate()->assertOk();

        $main = $response->json('main_app');
        foreach ([
            'HELPDESK_ENABLED', 'HELPDESK_BASE_URL', 'HELPDESK_API_KEY_ID', 'HELPDESK_API_SECRET',
            'HELPDESK_WEBHOOK_SECRET', 'HELPDESK_SSO_KID', 'HELPDESK_SSO_PRIVATE_KEY_PATH',
            'HELPDESK_SSO_ISSUER',
        ] as $key) {
            $this->assertNotEmpty($main[$key], "missing $key");
        }
        $this->assertSame('k1', $main['HELPDESK_API_KEY_ID']);
        $this->assertSame(96, strlen($main['HELPDESK_API_SECRET']));
        $this->assertSame(96, strlen($main['HELPDESK_WEBHOOK_SECRET']));
        $this->assertNotSame($main['HELPDESK_API_SECRET'], $main['HELPDESK_WEBHOOK_SECRET']);
        $this->assertSame('http://app.tijaraq.test', $main['HELPDESK_SSO_ISSUER']);

        $env = $this->envFile();
        $this->assertStringContainsString('APP_NAME=Test', $env);
        $this->assertStringContainsString('TIJARAQ_INTEGRATION_ENABLED=true', $env);
        $this->assertStringContainsString(
            'TIJARAQ_API_KEYS={"k1":"' . $main['HELPDESK_API_SECRET'] . '"}',
            $env,
        );
        $this->assertStringContainsString('TIJARAQ_WEBHOOK_SECRET=' . $main['HELPDESK_WEBHOOK_SECRET'], $env);
        $this->assertStringContainsString('TIJARAQ_WEBHOOK_URL=http://app.tijaraq.test/webhooks/helpdesk', $env);
        $this->assertStringContainsString('TIJARAQ_SSO_ISSUER=http://app.tijaraq.test', $env);
        $this->assertStringContainsString('TIJARAQ_SSO_AUDIENCE=http://help.tijaraq.test', $env);

        // never cached: the response holds secrets
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_generated_sso_key_pair_really_works(): void
    {
        $response = $this->generate(['parts' => ['sso_key']])->assertOk();

        $kid = $response->json('main_app.HELPDESK_SSO_KID');
        $pem = $response->json('private_key.pem');
        $this->assertFileExists("{$this->keyDir}/$kid.pem");
        $this->assertStringContainsString('BEGIN PUBLIC KEY', file_get_contents("{$this->keyDir}/$kid.pem"));
        $this->assertStringNotContainsString('PRIVATE', file_get_contents("{$this->keyDir}/$kid.pem"));

        // sign like the main app would, verify with the helpdesk verifier
        $now = time();
        $token = JWT::encode(
            [
                'iss' => 'http://app.tijaraq.test', 'aud' => 'http://help.tijaraq.test',
                'sub' => 'u1', 'cid' => 'c1', 'email' => 'x@example.test', 'email_verified' => true,
                'name' => 'X', 'scope' => 'own', 'jti' => Uuid::v4(),
                'iat' => $now, 'nbf' => $now, 'exp' => $now + 60,
            ],
            $pem,
            'RS256',
            $kid,
        );
        config([
            'tijaraq-integration.sso.issuer' => 'http://app.tijaraq.test',
            'tijaraq-integration.sso.audience' => 'http://help.tijaraq.test',
        ]);

        $claims = app(JwtVerifier::class)->verify($token);
        $this->assertSame('u1', $claims['sub']);
    }

    public function test_the_generated_api_secret_signs_requests_the_helpdesk_accepts(): void
    {
        $response = $this->generate(['parts' => ['api_key']])->assertOk();
        $secret = $response->json('main_app.HELPDESK_API_SECRET');

        // what the new env would load after the next request
        config(['tijaraq-integration.api_keys' => [$response->json('main_app.HELPDESK_API_KEY_ID') => $secret]]);

        $this->signed('GET', 'meta', ['key' => 'k1'])->assertOk();
    }

    public function test_rotation_adds_a_new_key_and_keeps_the_old_one(): void
    {
        config(['tijaraq-integration.api_keys' => ['k1' => $this->secret1]]);

        $response = $this->generate(['parts' => ['api_key'], 'keep_old_api_key' => true])->assertOk();

        $this->assertSame('k2', $response->json('main_app.HELPDESK_API_KEY_ID'));
        $env = $this->envFile();
        $this->assertStringContainsString('"k1":"' . $this->secret1 . '"', $env);
        $this->assertStringContainsString('"k2":"' . $response->json('main_app.HELPDESK_API_SECRET') . '"', $env);
        $this->assertNotEmpty($response->json('notes'));
    }

    public function test_generating_without_rotation_replaces_the_keys(): void
    {
        config(['tijaraq-integration.api_keys' => ['k1' => $this->secret1]]);

        $this->generate(['parts' => ['api_key']])->assertOk();

        $this->assertStringNotContainsString($this->secret1, $this->envFile());
    }

    public function test_status_reports_names_and_flags_but_never_secret_values(): void
    {
        $generated = $this->generate()->assertOk();
        config([
            'tijaraq-integration.api_keys' => ['k1' => $generated->json('main_app.HELPDESK_API_SECRET')],
            'tijaraq-integration.webhook_secret' => $generated->json('main_app.HELPDESK_WEBHOOK_SECRET'),
            'tijaraq-integration.webhook_url' => 'http://app.tijaraq.test/webhooks/helpdesk',
        ]);

        $status = $this->asAdmin()->getJson('/api/v1/admin/tijaraq-integration')->assertOk();

        $this->assertSame(['k1'], $status->json('status.api_key_ids'));
        $this->assertTrue($status->json('status.webhook_secret_set'));
        $this->assertTrue($status->json('status.ready'));
        $this->assertContains($generated->json('main_app.HELPDESK_SSO_KID'), $status->json('status.sso_kids'));

        $body = $status->getContent();
        $this->assertStringNotContainsString($generated->json('main_app.HELPDESK_API_SECRET'), $body);
        $this->assertStringNotContainsString($generated->json('main_app.HELPDESK_WEBHOOK_SECRET'), $body);
    }

    public function test_the_second_sso_key_gets_a_new_kid_and_the_first_stays(): void
    {
        $first = $this->generate(['parts' => ['sso_key']])->json('main_app.HELPDESK_SSO_KID');
        $second = $this->generate(['parts' => ['sso_key']])->json('main_app.HELPDESK_SSO_KID');

        $this->assertNotSame($first, $second);
        $this->assertFileExists("{$this->keyDir}/$first.pem");
        $this->assertFileExists("{$this->keyDir}/$second.pem");
    }

    public function test_update_changes_the_non_secret_settings(): void
    {
        $this->asAdmin()->putJson('/api/v1/admin/tijaraq-integration', [
            'enabled' => true,
            'webhook_url' => 'http://app.tijaraq.test/webhooks/helpdesk',
            'allowed_ips' => '203.0.113.10, 198.51.100.0/24',
            'rate_limit_per_tenant' => 200,
        ])->assertOk();

        $env = $this->envFile();
        $this->assertStringContainsString('TIJARAQ_INTEGRATION_ENABLED=true', $env);
        $this->assertStringContainsString('TIJARAQ_ALLOWED_IPS=203.0.113.10,198.51.100.0/24', $env);
        $this->assertStringContainsString('TIJARAQ_RATE_LIMIT_PER_TENANT=200', $env);

        // clearing the allow-list writes an empty value
        $this->asAdmin()->putJson('/api/v1/admin/tijaraq-integration', ['allowed_ips' => ''])->assertOk();
        $this->assertMatchesRegularExpression('/^TIJARAQ_ALLOWED_IPS=$/m', $this->envFile());
    }

    public function test_validation(): void
    {
        $this->generate(['main_app_url' => 'not a url'])->assertStatus(422);
        $this->generate(['parts' => ['nope']])->assertStatus(422);
        $this->generate(['parts' => []])->assertStatus(422);
        $this->generate(['key_id' => '../x'])->assertStatus(422);

        $this->asAdmin()->putJson('/api/v1/admin/tijaraq-integration', ['allowed_ips' => 'x; rm -rf'])
            ->assertStatus(422);
        $this->asAdmin()->putJson('/api/v1/admin/tijaraq-integration', ['rate_limit_per_tenant' => 1])
            ->assertStatus(422);
    }

    public function test_a_read_only_env_file_gives_a_clear_error(): void
    {
        app(IntegrationSetup::class)->useEnvFile('storage/framework/testing/does-not-exist.env');

        $this->generate()
            ->assertStatus(422)
            ->assertJsonPath('message', 'The .env file is not writable, so the helpdesk side cannot be saved.');
    }
}
