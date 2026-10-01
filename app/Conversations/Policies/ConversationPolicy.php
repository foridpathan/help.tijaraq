<?php namespace App\Conversations\Policies;

use App\Conversations\Models\Conversation;
use App\Models\User;
use Common\Core\Policies\BasePolicy;

class ConversationPolicy extends BasePolicy
{
    public function index(User $user, $userId = null)
    {
        return $this->hasVariablePermission($user, 'update') ||
            $user->id === (int) $userId;
    }

    public function show(User $user, Conversation $conversation)
    {
        return $this->hasVariablePermission($user, 'update') ||
            $user->id === $conversation->user_id;
    }

    public function store(?User $user)
    {
        if (!$user?->email && settings('tickets.guest_tickets')) {
            return true;
        }

        return $this->hasVariablePermission($user, 'create') ||
            $this->hasVariablePermission($user, 'update');
    }

    public function update(User $user, ?Conversation $conversation = null)
    {
        return $this->hasVariablePermission($user, 'update');
    }

    public function destroy(User $user)
    {
        return $this->hasVariablePermission($user, 'update');
    }

    public function reply(User $user, Conversation $conversation)
    {
        return $user->id === $conversation->user_id ||
            $this->update($user, $conversation);
    }

    protected function hasVariablePermission(User $user, string $action): bool
    {
        return $this->hasPermission($user, "tickets.$action") ||
            $this->hasPermission($user, "conversations.$action");
    }
}
