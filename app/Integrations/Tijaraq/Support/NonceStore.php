<?php

namespace App\Integrations\Tijaraq\Support;

use Illuminate\Support\Facades\Cache;

class NonceStore
{
    /**
     * Returns true the first time a nonce is seen, false for a replay.
     *
     * NOTE: with the file/array cache this only protects a single node.
     * Multi-node deployments must use a shared cache store (redis,
     * database, memcached) so a nonce cannot be replayed on another node.
     */
    public function claim(string $nonce): bool
    {
        return Cache::add(
            "tijaraq:nonce:$nonce",
            1,
            config('tijaraq-integration.nonce_ttl', 600),
        );
    }
}
