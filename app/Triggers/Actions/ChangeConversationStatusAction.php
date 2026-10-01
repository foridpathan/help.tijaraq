<?php namespace App\Triggers\Actions;

use App\Conversations\Actions\ConversationEventsCreator;
use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationStatus;
use App\Triggers\Models\Trigger;

class ChangeConversationStatusAction implements TriggerActionInterface
{
    public function execute(
        Conversation $conversation,
        array $action,
        Trigger $trigger,
    ): Conversation {
        $statusId = $action['value']['status_id'] ?? null;
        $status = $statusId ? ConversationStatus::find($statusId) : null;

        if (!$status || $conversation->status_id === $status->id) {
            return $conversation;
        }

        $conversation::changeStatus($status, [$conversation->id]);

        if ($status->category <= Conversation::STATUS_CLOSED) {
            (new ConversationEventsCreator($conversation))->closedByTrigger();
        }

        // 'unload' status relationship in case it was already loaded
        // on given conversation so removed status is properly removed
        // the next time status relationship is accessed
        $conversation->unsetRelation('status');

        return $conversation;
    }
}
