<?php

namespace Tests\Feature\Integration;

use App\Integrations\Tijaraq\Models\AuditLog;
use App\Integrations\Tijaraq\Support\RedirectSanitizer;
use App\Integrations\Tijaraq\Support\Uuid;
use App\Models\User;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\DataProvider;

class SsoTest extends IntegrationTestCase
{
    protected string $keyDir;
    protected $privateKey;
    protected string $kid = 'sso-2026-10';

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyDir = sys_get_temp_dir() . '/tijaraq-sso-' . uniqid();
        mkdir($this->keyDir, 0777, true);

        $this->privateKey = $this->makeKey($this->kid);

        config([
            'tijaraq-integration.sso.public_keys_path' => $this->keyDir,
            'tijaraq-integration.sso.issuer' => 'https://app.tijaraq.com',
            'tijaraq-integration.sso.audience' => 'https://help.tijaraq.com',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->keyDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->keyDir);

        parent::tearDown();
    }

    // Windows PHP builds need an explicit openssl.cnf
    protected function opensslOptions(): array
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        foreach ([
            getenv('OPENSSL_CONF') ?: null,
            dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
        ] as $candidate) {
            if ($candidate && is_file($candidate)) {
                $options['config'] = $candidate;
                break;
            }
        }
        return $options;
    }

    protected function newRsaKey()
    {
        $key = openssl_pkey_new($this->opensslOptions());
        $this->assertNotFalse($key, 'could not create an RSA key: ' . openssl_error_string());
        return $key;
    }
    protected function makeKey(string $kid)
    {
        $key = $this->newRsaKey();
        $details = openssl_pkey_get_details($key);
        file_put_contents("{$this->keyDir}/$kid.pem", $details['key']);

        return $key;
    }

    protected function token(array $overrides = [], ?string $kid = null, $key = null): string
    {
        $now = time();
        $claims = array_merge(
            [
                'iss' => 'https://app.tijaraq.com',
                'aud' => 'https://help.tijaraq.com',
                'sub' => 'sso-user-1',
                'cid' => 'company-55',
                'email' => 'sso-user@example.test',
                'email_verified' => true,
                'name' => 'Sso Merchant',
                'scope' => 'own',
                'jti' => Uuid::v4(),
                'iat' => $now,
                'nbf' => $now,
                'exp' => $now + 60,
            ],
            $overrides,
        );

        // firebase/php-jwt takes the PEM of the private key
        openssl_pkey_export($key ?? $this->privateKey, $pem, null, $this->opensslOptions());

        return JWT::encode($claims, $pem, 'RS256', $kid ?? $this->kid);
    }

    protected function sso(string $token, ?string $redirect = null)
    {
        $query = http_build_query(array_filter([
            'token' => $token,
            'redirect' => $redirect,
        ], fn($v) => $v !== null));

        return $this->get("/sso/tijaraq?$query");
    }

    public function test_a_valid_token_signs_the_merchant_in_and_redirects(): void
    {
        $response = $this->sso($this->token(), '/hc/tickets/12');

        $response->assertRedirect('/hc/tickets/12');
        $this->assertAuthenticated('web');

        $user = User::where('external_source', 'tijaraq')->where('external_user_id', 'sso-user-1')->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, auth('web')->id());
        $this->assertSame('company-55', $user->external_company_id);
        $this->assertSame('Sso Merchant', $user->name);

        $this->assertTrue(
            AuditLog::where('action', 'sso.login')->where('external_user_id', 'sso-user-1')->exists(),
        );
    }

    public function test_redirect_defaults_to_the_customer_tickets_page(): void
    {
        $this->sso($this->token())->assertRedirect(config('tijaraq-integration.sso.default_redirect'));
    }

    public function test_session_id_is_regenerated_on_login(): void
    {
        $this->get('/');
        $before = session()->getId();

        $this->sso($this->token(), '/');

        $this->assertNotSame($before, session()->getId());
    }

    public function test_expired_token_is_rejected(): void
    {
        $past = time() - 300;
        $response = $this->sso($this->token(['iat' => $past, 'nbf' => $past, 'exp' => $past + 60]));

        $response->assertStatus(401)->assertSee('Open from your TijaraQ dashboard');
        $this->assertGuest('web');
    }

    public function test_failure_page_never_reveals_the_reason(): void
    {
        $response = $this->sso($this->token(['aud' => 'https://evil.example']));

        $response
            ->assertStatus(401)
            ->assertSee('https://app.tijaraq.com/support', false)
            ->assertDontSee('audience')
            ->assertDontSee('aud');
        $this->assertGuest('web');
        $this->assertTrue(AuditLog::where('action', 'sso.failed')->exists());
    }

    public function test_a_reused_jti_is_rejected(): void
    {
        $jti = Uuid::v4();

        $this->sso($this->token(['jti' => $jti]))->assertRedirect();
        $this->assertAuthenticated('web');

        auth('web')->logout();
        $this->flushSession();

        $this->sso($this->token(['jti' => $jti]))->assertStatus(401);
        $this->assertGuest('web');
    }

    public function test_wrong_audience_and_issuer_are_rejected(): void
    {
        $this->sso($this->token(['aud' => 'https://app.tijaraq.com']))->assertStatus(401);
        $this->sso($this->token(['iss' => 'https://evil.example']))->assertStatus(401);
        $this->assertGuest('web');
    }

    public function test_token_lifetime_above_sixty_seconds_is_rejected(): void
    {
        $now = time();
        $this->sso($this->token(['iat' => $now, 'exp' => $now + 600]))->assertStatus(401);
        $this->assertGuest('web');
    }

    public function test_token_that_is_not_valid_yet_is_rejected(): void
    {
        $future = time() + 120;
        $this->sso($this->token(['iat' => $future, 'nbf' => $future, 'exp' => $future + 60]))
            ->assertStatus(401);
    }

    public function test_unknown_kid_and_unsafe_kid_are_rejected(): void
    {
        $this->sso($this->token([], 'rotated-away'))->assertStatus(401);
        $this->sso($this->token([], '../../etc/passwd'))->assertStatus(401);
        $this->assertGuest('web');
    }

    public function test_token_signed_with_another_key_is_rejected(): void
    {
        $attacker = $this->newRsaKey();

        $this->sso($this->token([], $this->kid, $attacker))->assertStatus(401);
        $this->assertGuest('web');
    }

    public function test_hs256_tokens_are_rejected(): void
    {
        $now = time();
        $token = JWT::encode(
            [
                'iss' => 'https://app.tijaraq.com', 'aud' => 'https://help.tijaraq.com',
                'sub' => 'x', 'cid' => 'y', 'email' => 'x@example.test', 'jti' => Uuid::v4(),
                'iat' => $now, 'nbf' => $now, 'exp' => $now + 60,
            ],
            str_repeat('k', 64),
            'HS256',
            $this->kid,
        );

        $this->sso($token)->assertStatus(401);
        $this->assertGuest('web');
    }

    public function test_key_rotation_by_kid(): void
    {
        $newKey = $this->makeKey('sso-2027-01');

        $this->sso($this->token([], 'sso-2027-01', $newKey))->assertRedirect();
        $this->assertAuthenticated('web');
    }

    public function test_garbage_token_is_rejected(): void
    {
        $this->sso('not.a.jwt')->assertStatus(401);
        $this->sso('')->assertStatus(401);
        $this->assertGuest('web');
    }

    public function test_sso_cannot_log_in_as_an_agent(): void
    {
        $agent = $this->makeAgent();

        // identity claims the agent's address: provisioned as a separate customer
        $this->sso($this->token(['email' => $agent->email, 'sub' => 'sso-agent-try']))->assertRedirect();

        $this->assertNotSame($agent->id, auth('web')->id());
        $this->assertSame('tijaraq', auth('web')->user()->external_source);
    }

    #[DataProvider('unsafeRedirects')]
    public function test_open_redirect_attempts_fall_back_to_the_default(string $redirect): void
    {
        $this->sso($this->token(), $redirect)
            ->assertRedirect(config('tijaraq-integration.sso.default_redirect'));
    }

    public static function unsafeRedirects(): array
    {
        return [
            'absolute url' => ['https://evil.example/phish'],
            'protocol relative' => ['//evil.example'],
            'backslash' => ['/\\evil.example'],
            'encoded protocol relative' => ['/%2f/evil.example'],
            'no leading slash' => ['evil.example'],
            'javascript' => ['javascript:alert(1)'],
            'newline' => ["/ok\r\nLocation: https://evil.example"],
        ];
    }

    public function test_redirect_sanitizer_keeps_safe_relative_paths(): void
    {
        $this->assertSame('/hc/tickets/5?x=1#top', RedirectSanitizer::sanitize('/hc/tickets/5?x=1#top', '/'));
        $this->assertSame('/', RedirectSanitizer::sanitize(null, '/'));
    }
}
