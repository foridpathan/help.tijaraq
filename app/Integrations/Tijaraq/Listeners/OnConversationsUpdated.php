<?php

namespace App\Integrations\Tijaraq\Listeners;

use App\Conversations\Events\ConversationsUpdated;
use App\Conversations\Models\Conversation;
use App\Integrations\Tijaraq\Services\WebhookDispatcher;
use App\Integrations\Tijaraq\Support\TicketMaps;

/**
 * ticket.status_changed and ticket.assigned.
 *
 * This listener is registered in the service provider's register() method,
 * i.e. BEFORE AppServiceProvider::registerEvents() adds the trigger-cycle
 * listener that returns false while a cycle is running (which stops every
 * later listener). Because ours runs first, changes made by triggers still
 * emit webhooks.
 */
class OnConversationsUpdated
{
    use EmitsWebhooks;

    public function handle(ConversationsUpdated $event): void
    {
        $this->safely(function () use ($event) {
            $ids = collect($event->conversationsAfterUpdate)
                ->map(fn($c) => data_get($c, 'id'))
                ->filter()
                ->values();

            if ($ids->isEmpty()) {
                return;
            }

            // authoritative current state, only tenant tickets
            $current = Conversation::query()
                ->whereIn('id', $ids)
                ->whereNotNull('external_company_id')
                ->with(['user', 'assignee', 'group', 'status'])
                ->get();

            foreach ($current as $conversation) {
                $before = $event->conversationsDataBeforeUpdate[$conversation->id] ?? null;
                if (!$before) {
                    continue;
                }

                $this->statusChanged($conversation, $before);

                if (
                    (int) ($before['assignee_id'] ?? 0) !==
                    (int) $conversation->assignee_id
                ) {
                    $this->emitAssigned($conversation);
                }
            }
        });
    }

    protected function statusChanged(Conversation $conversation, array $before): void
    {
        // tenants only see open|pending|closed|locked, so only a change of
        // that key is reported
        $from = TicketMaps::statusKey($before['status_category'] ?? null);
        $to = TicketMaps::statusKey($conversation->status_category);

        if ($from === $to) {
            return;
        }

        $this->dispatcher()->dispatch(
            $conversation,
            WebhookDispatcher::EVENT_STATUS_CHANGED,
            ['from' => $from, 'to' => $to],
            $this->eventId($conversation, WebhookDispatcher::EVENT_STATUS_CHANGED, $to),
        );
    }
}
