<?php

namespace App\Conversations\Agent\Notifications\Ticketing\Assigned;

use App\Conversations\Agent\Notifications\Ticketing\BaseConversationNotification;
use App\Core\UrlGenerator;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConversationAssignedNotif extends BaseConversationNotification
{
    public const NOTIF_ID = '02';

    public function __construct(
        protected Collection $conversations,
        protected ?User $assigner = null,
        protected ?User $assignee = null,
    ) {}

    protected function image(): string|null
    {
        return $this->assigner?->image;
    }

    protected function lines(User $notifiable): array
    {
        return [$this->firstLine(), $this->secondLine()];
    }

    protected function firstLine(): string
    {
        $vars = [
            'assigner' => $this->assigner?->name,
            'assignee' => $this->assignee ? $this->assignee->name : __('you'),
            'conversationId' => $this->conversations->first()->id,
            'count' => $this->conversations->count(),
        ];

        if ($this->assigner) {
            return $this->conversations->count() === 1
                ? __(
                    '**:assigner** assigned :assignee conversion #:conversationId',
                    $vars,
                )
                : __(
                    '**:assigner** assigned :assignee :count conversions',
                    $vars,
                );
        }

        return $this->conversations->count() === 1
            ? __(
                'Conversation #:conversationId was assigned to :assignee',
                $vars,
            )
            : __(':count conversations were assigned to :assignee', $vars);
    }

    protected function secondLine(): string
    {
        return Str::limit(
            strip_tags($this->conversations->first()->latestMessage->body),
            150,
        );
    }

    protected function mainAction(): array
    {
        return [
            'label' => __('View conversation'),
            'action' => app(UrlGenerator::class)->conversation(
                $this->conversations->first(),
            ),
        ];
    }
}
