<?php

namespace Tests\Unit;

use App\Integrations\Tijaraq\Support\CanonicalRequest;
use PHPUnit\Framework\TestCase;

/**
 * Fixed vectors shared with the main app (Prompt 2) so both sides agree on
 * the canonical string. Vectors were produced by an independent
 * implementation of the contract.
 *
 * Header ordering note: headers are sorted by lowercase NAME, so
 * "x-tijaraq-user" sorts before "x-tijaraq-user-email".
 */
class TijaraqCanonicalRequestTest extends TestCase
{
    public const SECRET =
        's3cr3t-s3cr3t-s3cr3t-s3cr3t-s3cr3t-s3cr3t-s3cr3t-s3cr3t-s3cr3t-s3cr3t-';

    protected function baseHeaders(array $overrides = []): array
    {
        return array_merge(
            [
                'x-tijaraq-key-id' => 'k1',
                'x-tijaraq-timestamp' => '1790900000',
                'x-tijaraq-nonce' => '6f1b6c1e-0b0a-4c3e-9d3a-0c1d2e3f4a5b',
                'x-tijaraq-tenant' => '42',
                'x-tijaraq-user' => '7',
                'x-tijaraq-user-email' => 'ali@example.com',
                'x-tijaraq-user-name' => 'QWxpIEtoYW4',
                'x-tijaraq-email-verified' => '1',
                'x-tijaraq-scope' => 'own',
                'x-tijaraq-request-id' => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d',
                // never part of the canonical string
                'x-tijaraq-signature' => 'ignored',
                'content-type' => 'application/json',
            ],
            $overrides,
        );
    }

    public function test_vector_post_with_body_and_idempotency_key(): void
    {
        $body = '{"subject":"Hi","body_html":"<p>x</p>"}';
        $headers = $this->baseHeaders([
            'x-tijaraq-idempotency-key' => '11111111-2222-4333-8444-555555555555',
        ]);

        $canonical = CanonicalRequest::build(
            'post',
            '/api/integration/v1/tickets/',
            '',
            $headers,
            hash('sha256', $body),
        );

        $expected = implode("\n", [
            'POST',
            '/api/integration/v1/tickets',
            '',
            'x-tijaraq-email-verified:1',
            'x-tijaraq-idempotency-key:11111111-2222-4333-8444-555555555555',
            'x-tijaraq-key-id:k1',
            'x-tijaraq-nonce:6f1b6c1e-0b0a-4c3e-9d3a-0c1d2e3f4a5b',
            'x-tijaraq-request-id:0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d',
            'x-tijaraq-scope:own',
            'x-tijaraq-tenant:42',
            'x-tijaraq-timestamp:1790900000',
            'x-tijaraq-user:7',
            'x-tijaraq-user-email:ali@example.com',
            'x-tijaraq-user-name:QWxpIEtoYW4',
            '8ed87fd73db7e6b448653943920e51cca2d66a624a4424deb1db328c04aa9b9e',
        ]);

        $this->assertSame($expected, $canonical);
        $this->assertSame(
            '04032afd4c601f777d86c79c50d5cdd3031424c80a111d69684b689fbe74d81a',
            CanonicalRequest::sign($canonical, self::SECRET),
        );
    }

    public function test_vector_get_with_sorted_rfc3986_query_and_empty_body(): void
    {
        $headers = $this->baseHeaders([
            'x-tijaraq-email-verified' => '0',
            'x-tijaraq-scope' => 'company',
        ]);

        $canonical = CanonicalRequest::build(
            'GET',
            '/api/integration/v1/tickets',
            'status=open&search=a%20b&page=2&z=%C3%A9',
            $headers,
            hash('sha256', ''),
        );

        $this->assertSame(
            "GET\n/api/integration/v1/tickets\npage=2&search=a%20b&status=open&z=%C3%A9\n" .
                implode("\n", [
                    'x-tijaraq-email-verified:0',
                    'x-tijaraq-key-id:k1',
                    'x-tijaraq-nonce:6f1b6c1e-0b0a-4c3e-9d3a-0c1d2e3f4a5b',
                    'x-tijaraq-request-id:0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d',
                    'x-tijaraq-scope:company',
                    'x-tijaraq-tenant:42',
                    'x-tijaraq-timestamp:1790900000',
                    'x-tijaraq-user:7',
                    'x-tijaraq-user-email:ali@example.com',
                    'x-tijaraq-user-name:QWxpIEtoYW4',
                ]) .
                "\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
            $canonical,
        );
        $this->assertSame(
            '647913dfb60df41a1f3fb989de819175bce76d539713af658632d80c99be685b',
            CanonicalRequest::sign($canonical, self::SECRET),
        );
    }

    public function test_plus_in_query_is_a_space_and_values_are_reencoded(): void
    {
        $this->assertSame(
            'a=b%20c&a=d',
            CanonicalRequest::canonicalQuery('a=d&a=b+c'),
        );
    }

    public function test_header_values_are_trimmed_and_non_tijaraq_headers_ignored(): void
    {
        $this->assertSame(
            'x-tijaraq-tenant:42',
            CanonicalRequest::canonicalHeaders([
                'X-Tijaraq-Tenant' => '  42 ',
                'Authorization' => 'Bearer x',
                'X-Tijaraq-Signature' => 'abc',
            ]),
        );
    }
}
