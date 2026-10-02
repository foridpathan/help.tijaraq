<?php

namespace App\Integrations\Tijaraq\Http\Resources;

use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationItem;
use App\Integrations\Tijaraq\Support\TicketMaps;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Produces the exact contract shapes for Ticket and Message.
 */
class TicketPresenter
{
    /**
     * @param iterable<Conversation> $tickets
     * @return array<int, array>
     */
    public function tickets(iterable $tickets): array
    {
        $tickets = new EloquentCollection(collect($tickets)->all());
        $tickets->loadMissing(['user', 'assignee', 'group', 'status']);

        $ids = $tickets->pluck('id')->all();
        $categories = $this->categories($ids);
        $lastReplies = $this->lastReplies($ids);

        return $tickets
            ->map(
                fn(Conversation $t) => $this->shape(
                    $t,
                    $categories[$t->id] ?? null,
                    $lastReplies[$t->id] ?? null,
                ),
            )
            ->values()
            ->all();
    }

    public function ticket(Conversation $ticket): array
    {
        return $this->tickets([$ticket])[0];
    }

    public function message(ConversationItem $item): array
    {
        $item->loadMissing(['user', 'attachments']);

        $authorType = TicketMaps::authorType($item->author);

        return [
            'id' => $item->id,
            'author_type' => $authorType,
            'author_name' => $item->user?->name ??
                ($authorType === 'system' ? 'System' : null),
            'body_html' => is_string($item->body) ? $item->body : '',
            'attachments' => $item->attachments
                ->map(fn($file) => $this->attachment($file))
                ->values()
                ->all(),
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }

    public function attachment($file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'size' => (int) $file->file_size,
            'mime' => $file->mime,
        ];
    }

    protected function shape(
        Conversation $t,
        ?string $category,
        ?ConversationItem $lastReply,
    ): array {
        $statusKey = TicketMaps::statusKey($t->status_category);

        return [
            'id' => $t->id,
            'subject' => $t->subject,
            'status' => $statusKey,
            'status_label' => $t->status->user_label ?:
                ($t->status->label ?? ucfirst($statusKey)),
            'priority' => TicketMaps::priorityKey($t->priority),
            'category' => $category,
            'department' => $t->group
                ? ['id' => $t->group->id, 'name' => $t->group->name]
                : null,
            'assignee_name' => $t->assignee?->name,
            'requester' => [
                'external_user_id' => $t->user?->external_user_id,
                'name' => $t->user?->name,
            ],
            'last_reply_by' => $lastReply
                ? TicketMaps::authorType($lastReply->author)
                : null,
            'last_reply_at' => $lastReply?->created_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<int, string> conversation id => category option value */
    protected function categories(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $attributeId = DB::table('attributes')
            ->where('key', 'category')
            ->where('type', Conversation::MODEL_TYPE)
            ->value('id');
        if (!$attributeId) {
            return [];
        }

        return DB::table('attributables')
            ->where('attribute_id', $attributeId)
            ->where('attributable_type', (new Conversation())->getMorphClass())
            ->whereIn('attributable_id', $ids)
            ->pluck('value', 'attributable_id')
            ->map(fn($value) => is_string($value) ? trim($value, '"') : $value)
            ->all();
    }

    /** @return array<int, ConversationItem> conversation id => latest reply */
    protected function lastReplies(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $latestIds = ConversationItem::query()
            ->selectRaw('max(id) as id')
            ->where('type', 'message')
            ->whereIn('author', [
                Conversation::AUTHOR_USER,
                Conversation::AUTHOR_AGENT,
            ])
            ->whereIn('conversation_id', $ids)
            ->groupBy('conversation_id')
            ->pluck('id');

        return ConversationItem::query()
            ->whereIn('id', $latestIds)
            ->get(['id', 'conversation_id', 'author', 'created_at'])
            ->keyBy('conversation_id')
            ->all();
    }

    /** @return Collection<int, array> */
    public function messages(iterable $items): Collection
    {
        return collect($items)->map(fn($item) => $this->message($item));
    }
}
