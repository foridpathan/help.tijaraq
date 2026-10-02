<?php

namespace App\HelpCenter\Actions;

use App\HelpCenter\Models\HcArticle;
use App\HelpCenter\Models\HcCategory;

class ResolveHcSlug
{
    public static function category(string $slug, ?int $parentId = null): HcCategory
    {
        return HcCategory::query()
            ->where('parent_id', $parentId)
            ->get()
            ->first(fn (HcCategory $category) => slugify($category->name) === $slug)
            ?? abort(404);
    }

    public static function article(string $slug, ?HcCategory $section = null): HcArticle
    {
        $query = HcArticle::query();
        if ($section) {
            $query->whereHas('sections', fn ($q) => $q->where('categories.id', $section->id));
        }

        $article = (clone $query)->where('slug', $slug)->first();
        if ($article) {
            return $article;
        }

        $match = $query->where(fn ($q) => $q->whereNull('slug')->orWhere('slug', ''))
            ->get(['articles.id', 'title'])
            ->first(fn (HcArticle $item) => slugify($item->title) === $slug);

        return $match ? HcArticle::findOrFail($match->id) : abort(404);
    }
}
