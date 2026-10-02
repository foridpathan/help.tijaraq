<?php

namespace App\Integrations\Tijaraq\Listeners;

use App\Conversations\Models\Conversation;
use App\Integrations\Tijaraq\Services\WebhookDispatcher;
use App\Integrations\Tijaraq\Support\Uuid;
use Closure;
use Throwable;

/**
 * Shared by the webhook listeners. They run synchronously (cheap: one row +
 * one queued job) and must never break the vendor flow that fired the event.
 */
trait EmitsWebhooks
{
    protected function safely(Closure $callback): void
    {
        if (!WebhookDispatcher::enabled()) {
            return;
        }

        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function dispatcher(): WebhookDispatcher
    {
        return app(WebhookDispatcher::class);
    }

    // deterministic: (conversation, event, new value, updated_at)
    protected function eventId(
        Conversation $conversation,
        string $event,
        string|int|null $newValue,
    ): string {
        return Uuid::fromString(
            implode(':', [
                $conversation->id,
                $event,
                $newValue,
                $conversation->updated_at?->getTimestamp(),
            ]),
        );
    }

    protected function emitAssigned(Conversation $conversation): void
    {
        if (!$conversation->assignee_id) {
            return;
        }

        $conversation->loadMissing('assignee');

        $this->dispatcher()->dispatch(
            $conversation,
            WebhookDispatcher::EVENT_ASSIGNED,
            ['assignee_name' => $conversation->assignee?->name],
            $this->eventId(
                $conversation,
                WebhookDispatcher::EVENT_ASSIGNED,
                $conversation->assignee_id,
            ),
        );
    }
}
