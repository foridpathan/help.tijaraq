<?php

namespace App\Integrations\Tijaraq\Support;

use App\Conversations\Models\Conversation;

/**
 * Translates between the contract vocabulary (open|pending|closed|locked,
 * low|medium|high|urgent) and the helpdesk columns.
 */
class TicketMaps
{
    public const STATUS_KEYS = [
        Conversation::STATUS_OPEN => 'open',
        Conversation::STATUS_PENDING => 'pending',
        Conversation::STATUS_CLOSED => 'closed',
        Conversation::STATUS_LOCKED => 'locked',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public static function statusKey(int|null $category): string
    {
        return self::STATUS_KEYS[$category] ?? 'open';
    }

    public static function statusCategory(string $key): ?int
    {
        $category = array_search($key, self::STATUS_KEYS, true);
        return $category === false ? null : $category;
    }

    public static function priorityKey(int|null $value): string
    {
        $value = (int) $value;
        if ($value <= 1) {
            return 'low';
        }
        return match (true) {
            $value === 2 => 'medium',
            $value === 3 => 'high',
            default => 'urgent',
        };
    }

    public static function priorityValue(string $key): int
    {
        return (int) (config('tijaraq-integration.priority_map')[$key] ?? 2);
    }

    public static function authorType(string|null $author): string
    {
        return match ($author) {
            Conversation::AUTHOR_USER => 'customer',
            Conversation::AUTHOR_AGENT => 'agent',
            default => 'system',
        };
    }
}
