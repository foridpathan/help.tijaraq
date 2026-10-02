<?php

namespace App\Integrations\Tijaraq\Listeners;

use App\Conversations\Events\ConversationsAssignedToAgent;
use App\Conversations\Models\Conversation;

/**
 * ticket.assigned. The same assignment can also arrive through
 * ConversationsUpdated; both paths build the same deterministic event id so
 * the unique event_id column dedupes them.
 */
class OnConversationsAssignedToAgent
{
    use EmitsWebhooks;

    public function handle(ConversationsAssignedToAgent $event): void
    {
        $this->safely(function () use ($event) {
            $current = Conversation::query()
                ->whereIn('id', $event->conversations->pluck('id'))
                ->whereNotNull('external_company_id')
                ->with(['user', 'assignee', 'group', 'status'])
                ->get();

            foreach ($current as $conversation) {
                $this->emitAssigned($conversation);
            }
        });
    }
}
