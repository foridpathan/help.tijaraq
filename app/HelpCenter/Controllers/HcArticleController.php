<?php namespace App\HelpCenter\Controllers;

use App\Core\Modules;
use App\Core\WidgetFlags;
use App\HelpCenter\Actions\CrupdateArticle;
use App\HelpCenter\Actions\DeleteMultipleArticles;
use App\HelpCenter\Actions\HcArticleLoader;
use App\HelpCenter\Actions\PerformArticleBatchAction;
use App\HelpCenter\Jobs\IncrementArticleViews;
use App\HelpCenter\Models\HcArticle;
use App\HelpCenter\Requests\ModifyHcArticle;
use Common\Core\BaseController;
use Common\Database\Datasource\Datasource;
use Common\Database\Datasource\DatasourceFilters;
use Common\Files\FileEntry;
use Common\Files\Response\DownloadFilesResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class HcArticleController extends BaseController
{
    public function index()
    {
        $this->authorize('index', HcArticle::class);

        $builder = HcArticle::query()
            ->with(['author', 'tags'])
            ->when(
                Modules::aiInstalled() && request('aiAgentId'),
                fn($q) => $q->with([
                    'aiAgents' => fn($q) => $q->select(['ai_agents.id']),
                ]),
            )
            ->when(
                request('articleIds'),
                fn($q) => $q->whereIn(
                    'id',
                    explode(',', request('articleIds')),
                ),
            )
            ->select([
                'articles.id',
                'articles.title',
                'articles.slug',
                'articles.draft',
                'articles.author_id',
                'articles.updated_at',
            ]);

        $filters = new DatasourceFilters(request('filters'));

        if ($filter = $filters->getAndRemove('used_by_ai_agent')) {
            $builder->whereRelation(
                'aiAgents',
                'ai_agent_id',
                $filter['value'] ? '=' : '!=',
                request('aiAgentId'),
            );
        }

        $datasource = new Datasource(
            $builder,
            request()->except('with'),
            filters: $filters,
        );

        $pagination = $datasource->paginate();

        $pagination->setCollection(
            $pagination->getCollection()->loadPath()->map(
                fn(HcArticle $article) => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'draft' => $article->draft,
                    'author' => $article->author
                        ? [
                            'id' => $article->author->id,
                            'name' => $article->author->name,
                            'image' => $article->author->image,
                            'email' => $article->author->email,
                        ]
                        : null,
                    'tags' => $article->tags->pluck('name'),
                    'updated_at' => $article->updated_at,
                    'used_by_ai_agent' =>
                        request('aiAgentId') &&
                        $article->aiAgents->contains(request('aiAgentId')),
                    'path' => $article->path,
                ],
            ),
        );

        return $this->success([
            'pagination' => $pagination,
        ]);
    }

    public function show()
    {
        $data = (new HcArticleLoader())->loadData(request('loader'));
        $articleId = $data['article']['id'];

        $this->authorize('index', HcArticle::class);

        if (Arr::get($data, 'article.draft') && !Auth::user()?->isAgent()) {
            abort(403);
        }

        if (
            request('loader') === 'articlePage' &&
            IncrementArticleViews::shouldIncrement($articleId)
        ) {
            $timestamp = now()->timestamp;
            dispatch(
                new IncrementArticleViews(
                    $data['article']['id'],
                    Auth::id(),
                    $timestamp,
                ),
            );
            Session::put("articleViews.$articleId", $timestamp);
        }

        return $this->renderClientOrApi([
            'data' => $data,
            'pageName' => 'article-page',
        ]);
    }

    public function update(HcArticle $article, ModifyHcArticle $request)
    {
        $this->authorize('update', $article);

        $article = (new CrupdateArticle())->execute(
            $request->validated(),
            $article,
        );

        return $this->success(['article' => $article]);
    }

    public function store(ModifyHcArticle $request)
    {
        $this->authorize('store', HcArticle::class);

        $article = (new CrupdateArticle())->execute($request->validated());

        return $this->success(['article' => $article], 201);
    }

    public function destroy(string $ids)
    {
        $ids = explode(',', $ids);
        $this->authorize('destroy', HcArticle::class);

        $this->blockOnDemoSite();

        (new DeleteMultipleArticles())->execute($ids);

        return $this->success();
    }

    public function performBatchAction()
    {
        $this->authorize('update', HcArticle::class);

        $data = request()->validate([
            'articleIds' => 'required|array',
            'action' => 'required|string',
            'data' => 'required|array',
        ]);

        (new PerformArticleBatchAction())->execute(
            $data['articleIds'],
            $data['action'],
            $data['data'],
        );

        return $this->success();
    }
}
