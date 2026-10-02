<?php

namespace App\Integrations\Tijaraq\Services;

use App\Conversations\Models\Conversation;
use App\Integrations\Tijaraq\Http\Resources\TicketPresenter;
use App\Integrations\Tijaraq\Jobs\DeliverWebhookJob;
use App\Integrations\Tijaraq\Models\WebhookDelivery;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Records an outbound webhook and queues its delivery. Only conversations
 * that belong to a TijaraQ tenant (external_company_id) emit events.
 */
class WebhookDispatcher
{
    public const EVENT_REPLIED = 'ticket.replied';
    public const EVENT_STATUS_CHANGED = 'ticket.status_changed';
    public const EVENT_ASSIGNED = 'ticket.assigned';

    public function __construct(protected TicketPresenter $presenter) {}

    public static function enabled(): bool
    {
        return (bool) config('tijaraq-integration.enabled') &&
            config('tijaraq-integration.webhook_url') &&
            config('tijaraq-integration.webhook_secret');
    }

    public function dispatch(
        Conversation $conversation,
        string $event,
        array $data,
        string $eventId,
    ): ?WebhookDelivery {
        if (!self::enabled() || !$conversation->external_company_id) {
            return null;
        }

        $conversation->loadMissing(['user', 'assignee', 'group', 'status']);

        $payload = [
            'event_id' => $eventId,
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'external_company_id' => $conversation->external_company_id,
            'external_user_id' => $conversation->user?->external_user_id,
            'ticket' => $this->presenter->ticket($conversation),
            'data' => $data,
        ];

        try {
            $delivery = WebhookDelivery::create([
                'event_id' => $eventId,
                'event' => $event,
                'conversation_id' => $conversation->id,
                'payload' => $payload,
                'status' => WebhookDelivery::PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            // same event already recorded (deterministic id): dedupe
            return null;
        }

        DeliverWebhookJob::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }

    public function requeue(WebhookDelivery $delivery): void
    {
        $delivery->update([
            'status' => WebhookDelivery::PENDING,
            'attempts' => 0,
            'last_error' => null,
        ]);

        DeliverWebhookJob::dispatch($delivery->id);
    }
}
