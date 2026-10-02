<?php

namespace App\Integrations\Tijaraq\Actions;

use App\Conversations\Customer\Actions\SubmitMessageAsCustomer;
use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationItem;
use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Support\TijaraqContext;

/** Wraps the vendor SubmitMessageAsCustomer action. */
class ReplyToTicket
{
    public function execute(
        TijaraqContext $ctx,
        Conversation $ticket,
        array $data,
    ): ConversationItem {
        if ($ticket->status_category === Conversation::STATUS_LOCKED) {
            throw IntegrationException::ticketLocked();
        }

        $attachmentIds = (new ResolveAttachmentIds())->execute(
            $ctx,
            $data['attachment_ids'] ?? [],
        );

        return (new SubmitMessageAsCustomer())->execute($ticket, [
            'body' => $data['body_html'],
            'attachments' => $attachmentIds,
            // a coworker (company scope) replies as themselves
            'user_id' => $ctx->user->id,
        ]);
    }
}
