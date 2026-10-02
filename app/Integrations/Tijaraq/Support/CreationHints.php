<?php

namespace App\Integrations\Tijaraq\Support;

use Closure;

/**
 * The vendor CreateTicketAsCustomer action creates the conversation with a
 * fixed set of columns. While an integration ticket is being created, a
 * Conversation "creating" hook (see the service provider) copies these hints
 * onto the new row so external_company_id and priority are set before any
 * event fires.
 */
class CreationHints
{
    protected static ?array $current = null;

    public static function with(array $hints, Closure $callback): mixed
    {
        $previous = self::$current;
        self::$current = $hints;

        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }

    public static function current(): ?array
    {
        return self::$current;
    }
}
