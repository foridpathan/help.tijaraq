<?php

namespace App\Integrations\Tijaraq\Actions;

use App\Conversations\Actions\ConversationEventsCreator;
use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationStatus;
use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Support\TijaraqContext;

/** Close / reopen via Conversation::changeStatus with the default statuses. */
class ChangeTicketStatus
{
    public function close(TijaraqContext $ctx, Conversation $ticket): Conversation
    {
        // closed and locked tickets are already final
        if ($ticket->status_category <= Conversation::STATUS_CLOSED) {
            return $ticket;
        }

        Conversation::changeStatus(ConversationStatus::getDefaultClosed(), [
            $ticket,
        ]);
        (new ConversationEventsCreator($ticket))->closedByCustomer($ctx->user);

        return $ticket->fresh();
    }

    public function reopen(TijaraqContext $ctx, Conversation $ticket): Conversation
    {
        if ($ticket->status_category === Conversation::STATUS_LOCKED) {
            throw IntegrationException::ticketLocked();
        }

        if ($ticket->status_category === Conversation::STATUS_CLOSED) {
            Conversation::changeStatus(ConversationStatus::getDefaultOpen(), [
                $ticket,
            ]);
        }

        return $ticket->fresh();
    }
}
