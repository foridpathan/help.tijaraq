<?php

namespace Tests\Feature\Integration;

use App\Integrations\Tijaraq\Support\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;

class HmacSignatureTest extends IntegrationTestCase
{
    public function test_valid_signed_request_succeeds(): void
    {
        $this->signed('GET', 'meta')
            ->assertOk()
            ->assertJsonStructure([
                'departments',
                'categories',
                'priorities',
                'statuses',
            ]);
    }

    public function test_valid_request_with_query_string_succeeds(): void
    {
        $this->signed('GET', 'tickets', [
            'query' => ['status' => 'open', 'search' => 'a b&c', 'per_page' => 5],
        ])
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_tampered_body_is_rejected(): void
    {
        $this->signed('POST', 'tickets', [
            'json' => $this->ticketPayload(),
            'idempotency' => Uuid::v4(),
            'send_body' => json_encode($this->ticketPayload(['subject' => 'Evil'])),
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    public function test_tampered_path_is_rejected(): void
    {
        $this->signed('GET', 'tickets', ['send_path' => 'meta'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    public function test_tampered_query_is_rejected(): void
    {
        $this->signed('GET', 'tickets', [
            'query' => ['status' => 'open'],
            'send_query' => 'status=closed',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    #[DataProvider('signedHeaders')]
    public function test_every_signed_header_is_covered_by_the_signature(
        string $header,
        string $tamperedValue,
    ): void {
        $this->signed('GET', 'meta', [
            'mutate' => function (array $headers) use ($header, $tamperedValue) {
                $headers[$header] = $tamperedValue;
                return $headers;
            },
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    public static function signedHeaders(): array
    {
        return [
            'tenant' => ['X-Tijaraq-Tenant', 'company-b'],
            'user' => ['X-Tijaraq-User', 'user-999'],
            'email' => ['X-Tijaraq-User-Email', 'attacker@example.test'],
            'name' => ['X-Tijaraq-User-Name', 'RXZpbA'],
            'verified' => ['X-Tijaraq-Email-Verified', '0'],
            'scope' => ['X-Tijaraq-Scope', 'company'],
            'request id' => ['X-Tijaraq-Request-Id', '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d'],
        ];
    }

    public function test_tampered_nonce_is_rejected(): void
    {
        $this->signed('GET', 'meta', [
            'mutate' => function (array $h) {
                $h['X-Tijaraq-Nonce'] = Uuid::v4();
                return $h;
            },
        ])->assertStatus(401);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $this->signed('GET', 'meta', ['timestamp' => time() - 400])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'stale_timestamp');

        $this->signed('GET', 'meta', ['timestamp' => time() + 400])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'stale_timestamp');
    }

    public function test_timestamp_inside_the_window_is_accepted(): void
    {
        $this->signed('GET', 'meta', ['timestamp' => time() - 250])->assertOk();
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $nonce = Uuid::v4();

        $this->signed('GET', 'meta', ['nonce' => $nonce])->assertOk();
        $this->signed('GET', 'meta', ['nonce' => $nonce])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'replayed_nonce');
    }

    public function test_unknown_key_id_is_rejected(): void
    {
        $this->signed('GET', 'meta', ['key' => 'k9'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unknown_key');
    }

    public function test_wrong_secret_is_rejected(): void
    {
        config(['tijaraq-integration.api_keys' => ['k1' => str_repeat('q', 64)]]);

        // the helper signs with the key it is told about, so sign with k2
        // while the server only knows k1 under a different secret
        $this->signed('GET', 'meta', ['key' => 'k1', 'mutate' => function ($h) {
            $h['X-Tijaraq-Signature'] = str_repeat('0', 64);
            return $h;
        }])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    public function test_key_rotation_accepts_both_keys_and_drops_the_old_one(): void
    {
        config([
            'tijaraq-integration.api_keys' => [
                'k1' => $this->secret1,
                'k2' => $this->secret2,
            ],
        ]);

        $this->signed('GET', 'meta', ['key' => 'k1'])->assertOk();
        $this->signed('GET', 'meta', ['key' => 'k2'])->assertOk();

        // k1 removed after the main app switched to k2
        config(['tijaraq-integration.api_keys' => ['k2' => $this->secret2]]);

        $this->signed('GET', 'meta', ['key' => 'k2'])->assertOk();
        $this->signed('GET', 'meta', ['key' => 'k1'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unknown_key');
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->signed('GET', 'meta', [
            'mutate' => function (array $h) {
                unset($h['X-Tijaraq-Signature']);
                return $h;
            },
        ])->assertStatus(401);
    }

    public function test_invalid_nonce_format_is_rejected(): void
    {
        $this->signed('GET', 'meta', ['nonce' => 'not-a-uuid'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    public function test_ip_allow_list_blocks_other_addresses(): void
    {
        config(['tijaraq-integration.allowed_ips' => ['203.0.113.10']]);

        $this->signed('GET', 'meta')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ip_not_allowed');

        config(['tijaraq-integration.allowed_ips' => ['127.0.0.1']]);
        $this->signed('GET', 'meta')->assertOk();
    }

    public function test_unknown_path_returns_the_contract_error(): void
    {
        $this->getJson('/api/integration/v1/nope')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_responses_never_expose_secrets(): void
    {
        $body = $this->signed('GET', 'meta')->getContent();

        $this->assertStringNotContainsString($this->secret1, $body);
    }
}
