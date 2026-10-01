<?php

namespace App\Conversations\Agent\Controllers;

use App\Conversations\Models\Conversation;
use Common\Core\BaseController;
use Common\Tags\Tag;
use Illuminate\Support\Facades\Auth;

class ConversationTagsController extends BaseController
{
    public function index(int $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);

        $this->authorize('show', $conversation);

        return $this->success([
            'tags' => $conversation
                ->tags()
                ->get()
                ->map(
                    fn(Tag $tag) => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                    ],
                ),
        ]);
    }

    public function add()
    {
        $data = request()->validate([
            'tagId' => 'int',
            'newTagName' => 'string|max:100',
            'conversationIds' => 'required|array',
        ]);

        $conversations = Conversation::whereIn(
            'id',
            $data['conversationIds'],
        )->get();

        foreach ($conversations as $conversation) {
            $this->authorize('update', $conversation);
        }

        $tagId = isset($data['newTagName'])
            ? Tag::firstOrCreate(
                [
                    'name' => $data['newTagName'],
                ],
                ['user_id' => Auth::id()],
            )->id
            : $data['tagId'];

        app(Conversation::class)->attachTag(
            $tagId,
            $conversations->pluck('id')->toArray(),
        );

        return $this->success();
    }

    public function remove()
    {
        $data = request()->validate([
            'tagId' => 'required',
            'conversationIds' => 'required|array',
        ]);

        $conversations = Conversation::whereIn(
            'id',
            $data['conversationIds'],
        )->get();

        foreach ($conversations as $conversation) {
            $this->authorize('update', $conversation);
        }

        app(Conversation::class)->detachTag(
            $data['tagId'],
            $conversations->pluck('id')->toArray(),
        );

        return $this->success();
    }
}
