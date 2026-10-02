<?php

/**
 * TijaraQ integration (app.tijaraq.com <-> help.tijaraq.com).
 * Every secret is read from the environment only. Never store them in the
 * settings table and never log them.
 */

$decodeKeys = function (?string $json): array {
    if (!$json) {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    return array_filter(
        $decoded,
        fn($secret, $id) => is_string($id) &&
            $id !== '' &&
            is_string($secret) &&
            $secret !== '',
        ARRAY_FILTER_USE_BOTH,
    );
};

return [
    'enabled' => (bool) env('TIJARAQ_INTEGRATION_ENABLED', false),

    // {"k1":"<64+ char random>"}. Add k2 during rotation, remove k1 afterwards.
    'api_keys' => $decodeKeys(env('TIJARAQ_API_KEYS')),

    'webhook_url' => env('TIJARAQ_WEBHOOK_URL'),
    'webhook_secret' => env('TIJARAQ_WEBHOOK_SECRET'),

    'sso' => [
        'issuer' => env('TIJARAQ_SSO_ISSUER', 'https://app.tijaraq.com'),
        'audience' => env('TIJARAQ_SSO_AUDIENCE', 'https://help.tijaraq.com'),
        // directory with one <kid>.pem public key per signing key
        'public_keys_path' => env(
            'TIJARAQ_SSO_PUBLIC_KEYS_PATH',
            'storage/app/private/tijaraq-sso/',
        ),
        'fallback_url' => 'https://app.tijaraq.com/support',
        'default_redirect' => '/hc/tickets',
    ],

    // empty list disables the IP allow-list
    'allowed_ips' => array_values(
        array_filter(
            array_map('trim', explode(',', (string) env('TIJARAQ_ALLOWED_IPS'))),
        ),
    ),

    'rate_limit_per_ip' => 600,
    'rate_limit_per_tenant' => (int) env('TIJARAQ_RATE_LIMIT_PER_TENANT', 120),

    'timestamp_tolerance' => 300,
    'nonce_ttl' => 600,
    'idempotency_ttl_hours' => 24,
    'meta_cache_seconds' => 600,

    // contract priority <-> conversations.priority (tinyint). The agent UI
    // shows 1 = Low, 2 = Normal, 3+ = High.
    'priority_map' => [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'urgent' => 4,
    ],

    // webhook delivery
    'webhook_timeout' => 10,
    'webhook_tries' => 6,
    'webhook_backoff' => [60, 300, 900, 3600, 10800],
];
