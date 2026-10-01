<?php

namespace App\Conversations\Agent\Actions;

use App\Conversations\Actions\ConversationEventsCreator;
use App\Conversations\Events\ConversationsAssignedToAgent;
use App\Conversations\Events\ConversationsUpdated;
use App\Conversations\Models\Conversation;
use App\Models\User;
use App\Team\Models\Group;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConversationsAssigner
{
    public static function distributeUnassignedConversationsToAvailableAgents(
        bool $addEvent = false,
    ): Collection {
        $conversations = Conversation::query()
            ->whereNotClosed()
            ->whereAssignedToHuman()
            ->whereNull('assignee_id')
            ->with(['group', 'status'])
            ->limit(10)
            ->get();

        return $conversations->map(
            fn(
                Conversation $conversation,
            ) => static::assignConversationToFirstAvailableAgent(
                conversation: $conversation,
                addEvent: $addEvent,
            ),
        );
    }

    public static function assignConversationsToAgent(
        iterable $conversations,
        int $agentId,
        bool $addEvent = false,
    ): Collection {
        $conversations = collect($conversations);

        $original =
            isset($conversations[0]) &&
            $conversations[0] instanceof Conversation
                ? $conversations
                : Conversation::whereIn('id', $conversations)->get();
        $updatedEvent = new ConversationsUpdated($original);
        $updated = $original;

        $conversationsNotAssignedToAgent = $original->filter(
            fn($conversation) => $conversation->assignee_id !== $agentId,
        );

        if ($conversationsNotAssignedToAgent->isNotEmpty()) {
            // if conversation is currently assigned to a group agent is not part of, unassign
            $agentGroupIds = DB::table('group_user')
                ->where('user_id', $agentId)
                ->pluck('group_id');
            $conversationsToUnassign = $conversationsNotAssignedToAgent->filter(
                fn($c) => !is_null($c->group_id) &&
                    !$agentGroupIds->contains($c->group_id),
            );
            if ($conversationsToUnassign->isNotEmpty()) {
                Conversation::whereIn(
                    'id',
                    $conversationsToUnassign->pluck('id'),
                )->update(['group_id' => null]);
            }

            // assign conversations to agent
            $ids = $conversationsNotAssignedToAgent->pluck('id');
            Conversation::whereIn('id', $ids)->update([
                'assigned_to' => Conversation::ASSIGNED_AGENT,
                'assignee_id' => $agentId,
                'assigned_at' => now(),
            ]);

            $updated = Conversation::whereIn('id', $ids)->get();

            if ($addEvent) {
                $newAgent = User::find($agentId);
                $updated->each(
                    fn(
                        Conversation $conversation,
                    ) => (new ConversationEventsCreator(
                        $conversation,
                    ))->agentChanged($newAgent),
                );
            }

            $updatedEvent->dispatch($updated);
        }

        event(new ConversationsAssignedToAgent($updated));

        return $updated;
    }

    public static function assignConversationToFirstAvailableAgent(
        Conversation $conversation,
        array|null $except = [],
        bool $addEvent = false,
    ): Conversation {
        $group = $conversation->group ?? Group::findDefault();

        $allAgents = User::whereAgent()
            ->with(['groups', 'agentSettings', 'latestUserSession'])
            ->withActiveAssignedConversationsCount()
            ->get()
            ->reject(
                fn(User $a) => $except ? in_array($a->id, $except) : false,
            );

        // if group is set to manual assignment, put into queue
        if ($group->assignment_mode === 'manual') {
            $conversation->update([
                'assigned_to' => Conversation::ASSIGNED_AGENT,
                'assignee_id' => null,
                'assigned_at' => null,
                'group_id' => $group->id,
            ]);
            return $conversation;
        }

        $agentsAcceptingConversations = $allAgents->filter(
            // if it's a ticket, don't check wether agent was active recently
            fn(User $a) => ($conversation->type === 'ticket' ||
                $a->wasActiveRecently()) &&
                $a->acceptsConversations() &&
                $a->groups->contains('id', $group->id),
        );

        if ($agentsAcceptingConversations->isNotEmpty()) {
            $newAgentId = $agentsAcceptingConversations->first()['id'];
            [$conversation] = static::assignConversationsToAgent(
                collect([$conversation]),
                $newAgentId,
                addEvent: $addEvent,
            );
        } else {
            $wasAssignedToAgent = $conversation->assignee_id !== null;
            // put into queue, if no agents available
            $conversation->update([
                'assigned_to' => Conversation::ASSIGNED_AGENT,
                'assignee_id' => null,
                'assigned_at' => null,
                'group_id' => $group->id,
            ]);

            if ($addEvent && $wasAssignedToAgent) {
                (new ConversationEventsCreator($conversation))->addedToQueue();
            }
        }

        return $conversation;
    }
}
