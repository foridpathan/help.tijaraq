<?php

namespace App\Integrations\Tijaraq\Actions;

use App\Conversations\Customer\Actions\CreateTicketAsCustomer;
use App\Conversations\Events\ConversationsUpdated;
use App\Conversations\Models\Conversation;
use App\Integrations\Tijaraq\Support\CreationHints;
use App\Integrations\Tijaraq\Support\TicketMaps;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use Illuminate\Support\Facades\DB;

/**
 * Wraps the vendor CreateTicketAsCustomer action. The caller (controller)
 * runs this inside the idempotency transaction.
 */
class CreateTicket
{
    public function execute(TijaraqContext $ctx, array $data): Conversation
    {
        $attachmentIds = (new ResolveAttachmentIds())->execute(
            $ctx,
            $data['attachment_ids'] ?? [],
        );

        // whitelisted array, never the raw request
        $payload = [
            'subject' => $data['subject'],
            'group_id' => $data['department_id'] ?? null,
            'channel' => 'tijaraq',
            'attributes' => ['category' => $data['category']],
            'message' => [
                'body' => $data['body_html'],
                'attachments' => $attachmentIds,
            ],
        ];

        $conversation = DB::transaction(
            fn() => CreationHints::with(
                [
                    'external_company_id' => $ctx->tenant,
                    'priority' => TicketMaps::priorityValue($data['priority']),
                ],
                fn() => (new CreateTicketAsCustomer())->execute(
                    $payload,
                    $ctx->user,
                ),
            ),
        );

        // the vendor action pauses ConversationsUpdated events while it builds
        // the ticket and never resumes them; resume so later changes made in
        // this process (and their webhooks) are not swallowed
        ConversationsUpdated::resumeDispatching();

        // read-only display of the tenant for agents
        $conversation->updateCustomAttributes(
            ['tijaraq_company_id' => $ctx->tenant],
            false,
        );

        return $conversation->fresh();
    }
}
