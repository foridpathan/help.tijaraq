<?php

namespace App\Integrations\Tijaraq\Support;

/**
 * Tiny UUID helpers so webhook ids and nonce checks do not depend on the
 * ramsey/uuid factory.
 */
class Uuid
{
    public const V4_PATTERN =
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public static function v4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return self::format($bytes);
    }

    // deterministic id derived from a string (version 5 layout)
    public static function fromString(string $value): string
    {
        $bytes = substr(sha1('tijaraq-helpdesk:' . $value, true), 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return self::format($bytes);
    }

    public static function isV4(string $value): bool
    {
        return (bool) preg_match(self::V4_PATTERN, $value);
    }

    protected static function format(string $bytes): string
    {
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
