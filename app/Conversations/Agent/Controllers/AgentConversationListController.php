<?php namespace App\Conversations\Agent\Controllers;

use App\Conversations\Actions\ConversationListBuilder;
use App\Conversations\Models\Conversation;
use Common\Core\BaseController;
use Illuminate\Support\Facades\Auth;

class AgentConversationListController extends BaseController
{
    public function __invoke($agentId)
    {
        $this->authorize('index', Conversation::class);

        $agentId = $agentId === 'me' ? Auth::id() : $agentId;

        $paginator = Conversation::where(
            'assignee_id',
            $agentId,
        )->cursorPaginate(25);

        $pagination = (new ConversationListBuilder())->cursorPagination(
            $paginator,
        );

        return $this->success(['pagination' => $pagination]);
    }
}
