<?php

namespace App\Integrations\Tijaraq\Http\Controllers;

use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationStatus;
use App\Integrations\Tijaraq\Http\Requests\MetaOptions;
use App\Integrations\Tijaraq\Support\TicketMaps;
use App\Team\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class MetaController extends Controller
{
    public const CACHE_KEY = 'tijaraq:meta';

    public function __invoke(): JsonResponse
    {
        return response()->json(
            Cache::remember(
                self::CACHE_KEY,
                (int) config('tijaraq-integration.meta_cache_seconds', 600),
                fn() => $this->build(),
            ),
        );
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function build(): array
    {
        return [
            'departments' => Group::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn($g) => ['id' => $g->id, 'name' => $g->name])
                ->all(),
            'categories' => app(MetaOptions::class)->categories(),
            'priorities' => collect(TicketMaps::PRIORITIES)
                ->map(fn($p) => ['value' => $p, 'label' => ucfirst($p)])
                ->all(),
            'statuses' => collect(TicketMaps::STATUS_KEYS)
                ->map(fn($key, $category) => [
                    'key' => $key,
                    'label' => $this->statusLabel($category, $key),
                ])
                ->values()
                ->all(),
        ];
    }

    protected function statusLabel(int $category, string $key): string
    {
        $status = ConversationStatus::query()
            ->where('active', true)
            ->where('category', $category)
            ->orderBy('id')
            ->first();

        return $status?->user_label ?: ($status?->label ?: ucfirst($key));
    }
}
