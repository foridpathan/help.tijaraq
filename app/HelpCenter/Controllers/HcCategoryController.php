<?php namespace App\HelpCenter\Controllers;

use App\HelpCenter\Actions\HcCategoryLoader;
use App\HelpCenter\Models\HcArticle;
use App\HelpCenter\Models\HcCategory;
use App\HelpCenter\Requests\ModifyHcCategory;
use Common\Core\BaseController;
use Common\Tags\Tag;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

class HcCategoryController extends BaseController
{
    public function show()
    {
        $this->authorize('index', HcArticle::class);

        $data = (new HcCategoryLoader())->loadData(request('loader'));

        return $this->renderClientOrApi([
            'data' => $data,
            'pageName' => 'category-page',
        ]);
    }

    public function store(ModifyHcCategory $request)
    {
        $this->authorize('store', HcArticle::class);

        $last = HcCategory::orderBy('position', 'desc')->first();

        $data = $request->validated();

        $category = HcCategory::create([
            ...Arr::except($data, ['tags']),
            'position' => $last ? $last->position + 1 : 1,
        ]);

        if (array_key_exists('tags', $data)) {
            $tags = app(Tag::class)->insertOrRetrieve($data['tags']);
            $category->tags()->sync($tags->pluck('id'));
        }

        $category->syncImage();

        return $this->success(['category' => $category]);
    }

    public function update(int $id, ModifyHcCategory $request)
    {
        $this->authorize('update', HcArticle::class);

        $category = HcCategory::findOrFail($id);

        $data = $request->validated();

        $category->fill(Arr::except($data, ['tags']))->save();

        if (array_key_exists('tags', $data)) {
            $tags = app(Tag::class)->insertOrRetrieve($data['tags']);
            $category->tags()->sync($tags->pluck('id'));
        }

        $category->syncImage();

        return $this->success(['category' => $category]);
    }

    public function destroy(int $id)
    {
        $this->authorize('destroy', HcArticle::class);

        $category = HcCategory::findOrFail($id);

        $this->blockOnDemoSite();

        $category
            ->where('parent_id', $category->id)
            ->update(['parent_id' => null]);

        $category->articles()->detach();

        $category->images()->detach();

        $category->delete();

        return $this->success();
    }

    public function sidenavContent(int $categoryId)
    {
        $this->authorize('index', HcArticle::class);

        $categories = HcCategory::where('parent_id', $categoryId)
            ->orderByPosition()
            ->with([
                'articles' => function (BelongsToMany $query) {
                    $query->select('id', 'title', 'position', 'slug');
                },
            ])
            ->limit(20)
            ->get();

        return $this->success(['sections' => $categories]);
    }
}
