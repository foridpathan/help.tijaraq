<?php namespace App\Conversations\Agent\Controllers;

use App\Conversations\Agent\Actions\ConversationListLoader;
use App\Conversations\Agent\Actions\CreateConversationAsAgent;
use App\Conversations\Agent\Actions\DeleteMultipleConversations;
use App\Conversations\Agent\Actions\FullConversationLoader;
use App\Conversations\Agent\Actions\SendTicketReplyEmail;
use App\Conversations\Events\ConversationsUpdated;
use App\Conversations\Models\Conversation;
use Common\Core\BaseController;
use Illuminate\Support\Facades\Auth;

class AgentConversationsController extends BaseController
{
    public function index()
    {
        $this->authorize('index', Conversation::class);

        $response = (new ConversationListLoader())->load(request()->all());

        return $this->success($response);
    }

    public function show(int $id)
    {
        $conversation = Conversation::findOrFail($id);

        $this->authorize('show', $conversation);

        $response = (new FullConversationLoader())->loadData($conversation);

        return $this->success($response);
    }

    public function store()
    {
        $this->authorize('store', Conversation::class);

        $data = $this->validate(
            request(),
            [
                'type' => 'required|in:ticket,chat',
                'user_id' => 'required|integer|exists:users,id',
                'subject' => 'required_if:type,ticket|nullable|min:3|max:180',
                'message.body' => 'required|string|min:3',
                'message.attachments' => 'array|max:10',
                'status_id' => 'int|exists:conversation_statuses,id',
                'attributes' => 'array',
            ],
            [],
            [
                'message.body' => 'message',
                'user_id' => 'customer',
            ],
        );

        $conversation = (new CreateConversationAsAgent())->execute($data);

        if ($conversation->type === 'ticket' && $conversation->latestMessage) {
            (new SendTicketReplyEmail())->execute(
                $conversation,
                $conversation->latestMessage,
                Auth::user(),
            );
        }

        return response()->json(['conversation' => $conversation]);
    }

    public function update(int $id)
    {
        $conversation = Conversation::findOrFail($id);
        $this->authorize('update', $conversation);

        $updatedEvent = new ConversationsUpdated([$conversation]);

        $data = $this->validate(request(), [
            'subject' => 'min:3|max:255',
            'user_id' => 'integer|exists:users,id',
            'attributes' => 'array',
        ]);

        if (isset($data['attributes'])) {
            $conversation->updateCustomAttributes($data['attributes']);
        }

        $conversation->fill($data)->save();

        $updatedEvent->dispatch([$conversation]);

        return $this->success(['conversation' => $conversation]);
    }

    public function destroy(string $ids)
    {
        $conversationIds = explode(',', $ids);

        $this->blockOnDemoSite();
        $this->authorize('destroy', Conversation::class);

        (new DeleteMultipleConversations())->execute($conversationIds);

        return $this->success([], 204);
    }
}
