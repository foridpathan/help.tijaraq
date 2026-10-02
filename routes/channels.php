<?php

use App\Core\HelpDeskChannel;
use Illuminate\Support\Facades\Gate;

Broadcast::channel(HelpDeskChannel::NAME, function (\App\Models\User $user) {
    return [
        'modelId' => $user->id,
        'modelType' => $user->type,
        'isAgent' => $user->isAgent(),
    ];
});

Broadcast::channel('tijaraq-chat-user.{userId}', function (\App\Models\User $user, string $userId) {
    return (string) $user->id === $userId;
});

Broadcast::channel('tijaraq-chat-agents', function (\App\Models\User $user) {
    return $user->isAgent() && Gate::forUser($user)->allows('index', \App\Conversations\Models\Conversation::class);
});
