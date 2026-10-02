<?php

namespace Livechat\Events;

use App\Conversations\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(public Conversation $conversation, public string $kind = 'message')
    {
        $this->dontBroadcastToCurrentUser();
    }

    public static function notify(Conversation $conversation, string $kind = 'message'): void
    {
        if ($conversation->channel !== 'livechat') {
            return;
        }

        try {
            event(new self($conversation, $kind));
        } catch (Throwable $error) {
            // A transient websocket outage must not make a saved reply fail.
            Log::warning('Chat broadcast failed', [
                'conversation_id' => $conversation->id,
                'error_type' => $error::class,
            ]);
        }
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tijaraq-chat-user.' . $this->conversation->user_id),
            new PrivateChannel('tijaraq-chat-agents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->conversation->id,
            'kind' => $this->kind,
        ];
    }
}
