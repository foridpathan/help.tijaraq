<?php

namespace Livechat\Http;

use App\Conversations\Customer\Actions\CreateTicketAsCustomer;
use App\Conversations\Customer\Actions\SubmitMessageAsCustomer;
use App\Conversations\Agent\Actions\SubmitMessageAsAgent;
use App\Conversations\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Conversation::query()
            ->where('type', 'ticket')
            ->where('channel', 'livechat');

        if ($request->boolean('agent')) {
            Gate::authorize('index', Conversation::class);
            abort_unless($request->user()->isAgent(), 403);
        } else {
            $query->where('user_id', $request->user()->id);
        }

        $conversations = $query->with([
            'user:id,name',
            'latestMessage:id,conversation_id,type,body,author,created_at',
        ])->latest('id')->limit(50)->get([
            'id', 'subject', 'user_id', 'status_category', 'created_at', 'updated_at',
        ]);

        return response()->json([
            'enabled' => (bool) settings('chat.enabled', true),
            'conversations' => $conversations,
        ]);
    }

    public function store(Request $request, CreateTicketAsCustomer $create): JsonResponse
    {
        abort_unless(settings('chat.enabled', true), 403);
        Gate::authorize('store', Conversation::class);

        $data = $request->validate(['message' => 'required|string|min:2|max:4000']);
        $plainText = trim(strip_tags($data['message']));
        abort_if($plainText === '', 422, 'Message is required.');

        $conversation = $create->execute([
            'subject' => Str::limit($plainText, 100),
            'channel' => 'livechat',
            'message' => ['body' => nl2br(e($plainText))],
        ], $request->user());

        return response()->json(['conversation_id' => $conversation->id], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeChat($conversation);

        return response()->json([
            'conversation' => $conversation->load('user:id,name')->only([
                'id', 'subject', 'user_id', 'status_category', 'created_at', 'updated_at',
            ]) + ['user' => $conversation->user],
            'messages' => $conversation->messages()
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'type', 'body', 'author', 'created_at'])
                ->reverse()
                ->values(),
        ]);
    }

    public function reply(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(settings('chat.enabled', true), 403);
        $this->authorizeChat($conversation);
        Gate::authorize('reply', $conversation);
        abort_if($conversation->status_category <= Conversation::STATUS_LOCKED, 409);

        $data = $request->validate(['message' => 'required|string|min:1|max:4000']);
        $plainText = trim(strip_tags($data['message']));
        abort_if($plainText === '', 422, 'Message is required.');

        $payload = ['body' => nl2br(e($plainText))];
        $message = $request->user()->isAgent()
            ? app(SubmitMessageAsAgent::class)->execute($conversation, $payload)
            : app(SubmitMessageAsCustomer::class)->execute($conversation, $payload);

        return response()->json(['message_id' => $message->id], 201);
    }

    private function authorizeChat(Conversation $conversation): void
    {
        abort_unless($conversation->type === 'ticket' && $conversation->channel === 'livechat', 404);
        Gate::authorize('show', $conversation);
    }
}
