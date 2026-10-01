<?php

namespace App\Conversations\Agent\Actions;

use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InboxViewsLoader
{
    public function getAll(): array
    {
        $allViews = ConversationView::query()
            ->where('active', true)
            ->where(function (Builder $query) {
                $query
                    ->where('owner_id', Auth::id())
                    ->orWhere('access', ConversationView::VIEW_ACCESS_ANYONE)
                    ->orWhere(function (Builder $query) {
                        $query->where(
                            'access',
                            ConversationView::VIEW_ACCESS_GROUP,
                        );
                        $query->whereIn(
                            'group_id',
                            Auth::user()->groups->pluck('id')->toArray(),
                        );
                    });
            })
            ->orderBy('order', 'asc')
            ->get();
        $userViews = $allViews->filter(fn($view) => $view['key'] !== 'groups');
        $nativeGroupView = $allViews->first(
            fn($view) => $view['key'] === 'groups',
        );

        $views = [
            ...$userViews->toArray(),
            ...$this->getGroupViews($nativeGroupView),
        ];

        $views = $this->loadCounts($views);

        return array_map(
            fn(array $view) => [
                'id' => $view['id'],
                'key' => $view['key'] ?? null,
                'name' => $view['name'],
                'pinned' => $view['pinned'] ?? false,
                'icon' => $view['icon'] ?? null,
                'count' => $view['count'] ?? 0,
                'isGroupView' => $view['isGroupView'] ?? false,
            ],
            $views,
        );
    }

    public function getById(string|int $viewId)
    {
        if (Str::startsWith($viewId, 'group:')) {
            // this will override columns and ordering for static group view config
            $internalGroupView = ConversationView::where(
                'key',
                'groups',
            )->first();

            $groupViews = $this->getGroupViews($internalGroupView);

            return array_merge(
                $internalGroupView->toArray(),
                Arr::first($groupViews, fn($view) => $view['id'] === $viewId),
            );
        }

        return ConversationView::query()
            ->where('key', $viewId)
            ->orWhere('id', $viewId)
            ->first();
    }

    protected function getGroupViews(ConversationView $nativeGroupView): array
    {
        return Auth::user()
            ->groups->map(
                fn($group) => [
                    'id' => "group:$group->id",
                    'key' => "group:$group->id",
                    'name' => $group->name,
                    'isGroupView' => true,
                    'pinned' => $nativeGroupView->pinned ?? false,
                    'conditions' => [
                        [
                            'key' => 'group_id',
                            'operator' => '=',
                            'value' => $group->id,
                        ],
                        ...$nativeGroupView->conditions,
                    ],
                ],
            )
            ->toArray();
    }

    protected function loadCounts(array $views)
    {
        $openConversations = Conversation::query()
            ->where('assigned_to', Conversation::ASSIGNED_AGENT)
            ->where('mode', Conversation::MODE_NORMAL)
            ->whereNotClosed()
            ->with([
                'tags' => fn($query) => $query->select([
                    'tags.id',
                    'tags.name',
                ]),
                'user' => fn($query) => $query->select([
                    'id',
                    'name',
                    'email',
                    'country',
                ]),
                'customAttributes' => fn($query) => $query->select([
                    'attributes.id',
                    'key',
                    'format',
                    'value',
                ]),
            ])
            ->limit(100)
            ->get();

        foreach ($views as $key => $view) {
            $views[$key]['count'] = 0;
        }

        foreach ($openConversations as $conversation) {
            foreach ($views as &$view) {
                // don't show view counts for "all" and "closed" views at all
                if ($view['key'] === 'all' || $view['key'] === 'closed') {
                    continue;
                }

                if (!isset($view['conditions']) || empty($view['conditions'])) {
                    $view['count']++;
                    continue;
                }

                $allConditions = collect($view['conditions'])->filter(
                    fn($c) => Arr::get($c, 'match_type', 'all') === 'all',
                );
                $allMatch =
                    $allConditions->isEmpty() ||
                    $allConditions->every(
                        fn($c) => $this->conditionMatches($conversation, $c),
                    );
                $anyConditions = collect($view['conditions'])->filter(
                    fn($c) => Arr::get($c, 'match_type', 'all') === 'any',
                );
                $anyMatch =
                    $anyConditions->isEmpty() ||
                    $anyConditions->some(
                        fn($c) => $this->conditionMatches($conversation, $c),
                    );

                if ($allMatch && $anyMatch) {
                    $view['count']++;
                }
            }
        }

        return $views;
    }

    protected function conditionMatches(
        Conversation $conversation,
        array $condition,
    ): bool {
        $conversationValue = $this->conversationValue(
            $conversation,
            $condition,
        );
        $conditionValue = $this->conditionValue($condition);
        $operator = $condition['operator'];

        if (Str::endsWith($condition['key'], '_hours')) {
            $hoursAgo = now()->subHours($conditionValue);
            return match ($operator) {
                '>' => $conversationValue->lt($hoursAgo),
                '<' => $conversationValue->gt($hoursAgo),
            };
        }

        return match ($operator) {
            '=' => $conversationValue === $conditionValue,
            '!=' => $conversationValue !== $conditionValue,
            '>' => $conversationValue > $conditionValue,
            '<' => $conversationValue < $conditionValue,
            '<=' => $conversationValue <= $conditionValue,
            '>=' => $conversationValue >= $conditionValue,
            'notNull' => $conversationValue !== null,
            'contains' => Str::contains($conversationValue, $conditionValue),
            'notContains' => !Str::contains(
                $conversationValue,
                $conditionValue,
            ),
            'startsWith' => Str::startsWith(
                $conversationValue,
                $conditionValue,
            ),
            'endsWith' => Str::endsWith($conversationValue, $conditionValue),
            'has' => count(
                array_intersect($conversationValue, Arr::wrap($conditionValue)),
            ) > 0,
            'doesntHave' => count(
                array_intersect($conversationValue, Arr::wrap($conditionValue)),
            ) === 0,
        };
    }

    protected function conditionValue(array $condition)
    {
        if ($condition['key'] === 'tags') {
            return array_map(
                fn($tag) => is_array($tag) ? $tag['id'] : $tag,
                $condition['value'],
            );
        }

        return match ($condition['value']) {
            'currentUser' => Auth::id(),
            'null' => null,
            default => $condition['value'],
        };
    }

    protected function conversationValue(
        Conversation $conversation,
        array $condition,
    ) {
        if (Str::endsWith($condition['key'], '_hours')) {
            $propertyName = str_replace('_hours', '', $condition['key']);
            return $conversation->{$propertyName};
        }

        if (Str::startsWith($condition['key'], 'ca_')) {
            return $conversation->customAttributes
                ->where('key', str_replace('ca_', '', $condition['key']))
                ->first()?->value;
        }

        return match ($condition['key']) {
            'country' => $conversation->user?->country,
            'tags' => $conversation->tags->pluck('id')->toArray(),
            default => $conversation->{$condition['key']},
        };
    }
}
