<?php

namespace App\Integrations\Tijaraq\Listeners;

use App\Conversations\Events\ConversationMessageCreated;
use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationItem;
use App\Integrations\Tijaraq\Http\Resources\TicketPresenter;
use App\Integrations\Tijaraq\Services\WebhookDispatcher;
use App\Integrations\Tijaraq\Support\Uuid;

/** ticket.replied: agent replies only, never internal notes. */
class OnConversationMessageCreated
{
    use EmitsWebhooks;

    public function handle(ConversationMessageCreated $event): void
    {
        $this->safely(function () use ($event) {
            $message = $event->message;

            if (
                $message->type !== 'message' ||
                $message->type === ConversationItem::NOTE_TYPE ||
                $message->author !== Conversation::AUTHOR_AGENT
            ) {
                return;
            }

            $conversation = Conversation::find($event->conversation->id);
            if (!$conversation?->external_company_id) {
                return;
            }

            $this->dispatcher()->dispatch(
                $conversation,
                WebhookDispatcher::EVENT_REPLIED,
                ['message' => app(TicketPresenter::class)->message($message)],
                Uuid::fromString(
                    "{$conversation->id}:" .
                        WebhookDispatcher::EVENT_REPLIED .
                        ":{$message->id}",
                ),
            );
        });
    }
}
