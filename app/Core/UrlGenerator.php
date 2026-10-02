<?php

namespace App\Core;

use App\Conversations\Models\Conversation;
use App\HelpCenter\Models\HcArticle;
use App\HelpCenter\Models\HcCategory;
use Common\Core\Prerender\BaseUrlGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class UrlGenerator extends BaseUrlGenerator
{
    public function conversation(Conversation|array $conversation): string
    {
        $viewId =
            Arr::get($conversation, 'assignee_id') === Auth::id()
                ? 'mine'
                : 'all';
        return url(
            "dashboard/conversations/{$conversation['id']}?viewId={$viewId}",
        );
    }

    public function article(array|HcArticle $article): string
    {
        return url('hc/articles') .
            '/' . (!empty($article['slug']) ? $article['slug'] : slugify($article['title']));
    }

    public function category(HcCategory|array $category): string
    {
        $parentName = $category['parent_name'] ?? null;
        if (!$parentName && !empty($category['parent_id'])) {
            $parentName = HcCategory::find($category['parent_id'])?->name;
        }
        return url('hc/categories') .
            ($parentName ? '/' . slugify($parentName) : '') .
            '/' . slugify($category['name']);
    }

    public function search(string|null $query = null): string
    {
        return url($query ? "hc/search/$query" : 'hc/search');
    }
}
