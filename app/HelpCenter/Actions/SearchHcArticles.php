<?php

namespace App\HelpCenter\Actions;

use App\Core\WidgetFlags;
use App\HelpCenter\Models\HcArticle;
use Common\Database\Datasource\Datasource;
use Common\Database\Datasource\DatasourceFilters;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SearchHcArticles
{
    public function execute(array $params): array
    {
        $categoryIds = Arr::get($params, 'categoryIds')
            ? Str::of(Arr::get($params, 'categoryIds'))
                ->explode(',')
                ->map(fn(string $cat) => (int) trim($cat))
            : null;
        $tag = request('tag') ?? WidgetFlags::knowledgeScopeTag();

        $builder = HcArticle::query()->filterByVisibleToRole();
        $filtererName = request('scout.driver');

        $filters = new DatasourceFilters(Arr::get($params, 'filters'));
        $filters->where('draft', '=', false);

        if ($categoryIds?->isNotEmpty()) {
            $filters->where('categories', 'has', $categoryIds);
        }

        $datasource = new Datasource(
            $builder,
            $params,
            $filters,
            $filtererName,
        );

        // meilisearch will have category and article tags merged,
        // on mysql need to query for both article and category tags
        if ($tag) {
            if ($datasource->filtererIsMysql()) {
                $builder
                    ->whereRelation('tags', 'tags.name', $tag)
                    ->orWhereRelation('categories.tags', 'tags.name', $tag);
            } else {
                $filters->where('tags', 'has', $tag);
            }
        }

        $pagination = $datasource->paginate();
        $pagination->loadPath($categoryIds?->first());

        $pagination->through(function (HcArticle $article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'updated_at' => $article->updated_at,
                'path' => $article->path,
            ];
        });

        return [
            'pagination' => $pagination,
            'query' => Arr::get($params, 'query'),
            'categoryIds' => $categoryIds,
            'loader' => 'searchArticles',
        ];
    }
}
