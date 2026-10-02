<?php

namespace Tests\Feature\Integration;

use App\Integrations\Tijaraq\Support\CanonicalRequest;
use App\Integrations\Tijaraq\Support\Uuid;
use App\Models\User;
use Common\Auth\Actions\CreateUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Helpers that sign requests exactly like app.tijaraq.com does.
 */
abstract class IntegrationTestCase extends TestCase
{
    use DatabaseTransactions;

    public const BASE = '/api/integration/v1/';

    protected string $secret1;
    protected string $secret2;

    protected array $identity = [
        'tenant' => 'company-a',
        'user' => 'user-1',
        'email' => 'merchant-1@example.test',
        'name' => 'Merchant One',
        'verified' => '1',
        'scope' => 'own',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret1 = str_repeat('a1b2c3d4', 10);
        $this->secret2 = str_repeat('z9y8x7w6', 10);

        config([
            'tijaraq-integration.enabled' => true,
            'tijaraq-integration.api_keys' => [
                'k1' => $this->secret1,
            ],
            'tijaraq-integration.allowed_ips' => [],
            'tijaraq-integration.rate_limit_per_tenant' => 1000,
            'tijaraq-integration.webhook_url' => null,
            'tijaraq-integration.webhook_secret' => null,
        ]);

        Cache::flush();
        RateLimiter::clear('x');
    }

    protected function uniqueIdentity(array $overrides = []): array
    {
        $n = uniqid();
        return array_merge(
            $this->identity,
            [
                'user' => "user-$n",
                'email' => "merchant-$n@example.test",
                'name' => "Merchant $n",
            ],
            $overrides,
        );
    }

    /**
     * Sends a signed request.
     *
     * Options: identity[], query[], json[], body, file, key (key id),
     * timestamp, nonce, idempotency, send_path / send_query / send_body
     * (tamper after signing), mutate (callable on headers after signing)
     */
    protected function signed(
        string $method,
        string $path,
        array $options = [],
    ): TestResponse {
        $identity = array_merge($this->identity, $options['identity'] ?? []);
        $keyId = $options['key'] ?? 'k1';
        $secret = $this->secretFor($keyId);

        $body = array_key_exists('json', $options)
            ? json_encode($options['json'])
            : $options['body'] ?? '';
        $rawQuery = http_build_query(
            $options['query'] ?? [],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
        /** @var UploadedFile|null $file */
        $file = $options['file'] ?? null;

        $headers = [
            'X-Tijaraq-Key-Id' => $keyId,
            'X-Tijaraq-Timestamp' => (string) ($options['timestamp'] ?? time()),
            'X-Tijaraq-Nonce' => $options['nonce'] ?? Uuid::v4(),
            'X-Tijaraq-Tenant' => $identity['tenant'],
            'X-Tijaraq-User' => $identity['user'],
            'X-Tijaraq-User-Email' => $identity['email'],
            'X-Tijaraq-User-Name' => rtrim(
                strtr(base64_encode($identity['name']), '+/', '-_'),
                '=',
            ),
            'X-Tijaraq-Email-Verified' => $identity['verified'],
            'X-Tijaraq-Scope' => $identity['scope'],
            'X-Tijaraq-Request-Id' => Uuid::v4(),
        ];
        if (isset($options['idempotency'])) {
            $headers['X-Tijaraq-Idempotency-Key'] = $options['idempotency'];
        }

        $lowered = array_change_key_case($headers, CASE_LOWER);
        $canonical = CanonicalRequest::build(
            method: $method,
            path: self::BASE . $path,
            rawQuery: $rawQuery,
            headers: $lowered,
            bodyHash: $file
                ? hash_file('sha256', $file->getRealPath())
                : hash('sha256', $body),
        );
        $headers['X-Tijaraq-Signature'] = CanonicalRequest::sign(
            $canonical,
            $secret,
        );

        if (isset($options['mutate'])) {
            $headers = $options['mutate']($headers);
        }

        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $server['HTTP_ACCEPT'] = 'application/json';
        $server['CONTENT_TYPE'] = $file
            ? 'multipart/form-data; boundary=----tijaraq'
            : 'application/json';

        $sendPath = $options['send_path'] ?? $path;
        $sendQuery = $options['send_query'] ?? $rawQuery;
        $sendBody = $options['send_body'] ?? $body;
        $uri = self::BASE . $sendPath . ($sendQuery !== '' ? "?$sendQuery" : '');

        return $this->call(
            $method,
            $uri,
            [],
            [],
            $file ? ['file' => $file] : [],
            $server,
            $file ? null : $sendBody,
        );
    }

    protected function secretFor(string $keyId): string
    {
        return config('tijaraq-integration.api_keys')[$keyId] ??
            ($keyId === 'k2' ? $this->secret2 : 'unknown-secret');
    }

    protected function ticketPayload(array $overrides = []): array
    {
        return array_merge(
            [
                'subject' => 'Orders are not syncing',
                'body_html' => '<p>Shopify orders stopped syncing</p>',
                'category' => 'general',
                'priority' => 'medium',
            ],
            $overrides,
        );
    }

    protected function createTicket(
        array $identity = [],
        array $payload = [],
    ): TestResponse {
        return $this->signed('POST', 'tickets', [
            'identity' => $identity,
            'json' => $this->ticketPayload($payload),
            'idempotency' => Uuid::v4(),
        ]);
    }

    protected function createTicketId(array $identity = [], array $payload = []): int
    {
        $response = $this->createTicket($identity, $payload);
        $response->assertStatus(201);
        return $response->json('id');
    }

    protected function makeAgent(string $label = 'agent'): User
    {
        return (new CreateUser())->execute([
            'email' => "$label-" . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => ucfirst($label),
            'email_verified_at' => now(),
            'type' => 'agent',
        ]);
    }

    protected function fakeFile(string $name = 'invoice.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 12, 'application/pdf');
    }
}
