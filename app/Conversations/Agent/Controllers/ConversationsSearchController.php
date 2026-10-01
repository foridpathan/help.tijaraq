<?php namespace App\Conversations\Agent\Controllers;

use App\Attributes\AttributeFilters;
use App\Conversations\Actions\ConversationListBuilder;
use App\Conversations\Models\Conversation;
use Common\Core\BaseController;
use Common\Database\Datasource\Datasource;

class ConversationsSearchController extends BaseController
{
    public function __invoke()
    {
        $this->authorize('index', Conversation::class);

        $params = $this->validate(request(), [
            'query' => 'string|nullable',
            'filters' => 'string|nullable',
            'page' => 'integer|nullable',
            'orderBy' => 'string|nullable',
            'orderDir' => 'string|nullable',
        ]);

        $dataSource = new Datasource(
            Conversation::query(),
            $params,
            filtererName: config('scout.driver'),
        );

        (new AttributeFilters())->applyToDatasource($dataSource);

        $pagination = (new ConversationListBuilder())->simplePagination(
            $dataSource->paginate(),
        );

        return $this->success(['pagination' => $pagination]);
    }
}
