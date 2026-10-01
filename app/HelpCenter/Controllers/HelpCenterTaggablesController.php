<?php

namespace App\HelpCenter\Controllers;

use App\HelpCenter\Models\HcArticle;
use Common\Core\BaseController;
use Common\Tags\Tag;
use Illuminate\Support\Facades\DB;

class HelpCenterTaggablesController extends BaseController
{
    public function syncTags()
    {
        $this->authorize('update', HcArticle::class);

        $data = $this->validate(request(), [
            'taggableIds' => 'required|array',
            'taggableType' => 'required|string',
            'tags' => 'array',
            'tags.*' => 'string',
        ]);
        $taggableIds = $data['taggableIds'];
        $taggableType = $data['taggableType'];

        $tagIds = empty($data['tags'])
            ? collect()
            : app(Tag::class)->insertOrRetrieve($data['tags'])->pluck('id');

        // delete tags not in the provided list
        DB::table('taggables')
            ->whereIn('taggable_id', $data['taggableIds'])
            ->where('taggable_type', $data['taggableType'])
            ->whereNotIn('tag_id', $tagIds)
            ->delete();

        // get existing tag associations to avoid duplicates
        $existingAssociations = DB::table('taggables')
            ->whereIn('taggable_id', $taggableIds)
            ->where('taggable_type', $taggableType)
            ->whereIn('tag_id', $tagIds)
            ->get(['taggable_id', 'tag_id'])
            ->groupBy('taggable_id')
            ->map(fn($items) => $items->pluck('tag_id')->toArray());

        $rowsToInsert = [];
        foreach ($taggableIds as $taggableId) {
            $existingTagIds = $existingAssociations->get($taggableId, []);
            foreach ($tagIds as $tagId) {
                if (!in_array($tagId, $existingTagIds)) {
                    $rowsToInsert[] = [
                        'taggable_id' => $taggableId,
                        'taggable_type' => $taggableType,
                        'tag_id' => $tagId,
                        'user_id' => null,
                    ];
                }
            }
        }

        if (!empty($rowsToInsert)) {
            DB::table('taggables')->insert($rowsToInsert);
        }

        return $this->success();
    }
}
