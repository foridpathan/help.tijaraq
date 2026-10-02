<?php

namespace App\Integrations\Tijaraq\Repositories;

use App\Conversations\Models\Conversation;
use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Support\TicketMaps;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The ONLY class that queries Conversation for integration requests. Every
 * query is limited to type=ticket + the verified tenant, and to the caller's
 * own tickets when scope=own. Anything else resolves to a 404 so the
 * existence of a ticket never leaks across tenants.
 */
class IntegrationTicketRepository
{
    public const RELATIONS = ['user', 'assignee', 'group', 'status'];

    public function __construct(protected TijaraqContext $ctx) {}

    public function query(): Builder
    {
        return Conversation::query()
            ->where('type', 'ticket')
            ->where('external_company_id', $this->ctx->tenant)
            ->when(
                !$this->ctx->isCompanyScope(),
                fn(Builder $q) => $q->where('user_id', $this->ctx->user->id),
            );
    }

    public function findOrFail(int|string $id): Conversation
    {
        if (!ctype_digit((string) $id)) {
            throw IntegrationException::notFound();
        }

        $ticket = $this->query()
            ->with(self::RELATIONS)
            ->find((int) $id);

        if (!$ticket) {
            throw IntegrationException::notFound();
        }

        return $ticket;
    }

    public function paginate(
        ?string $status,
        ?string $search,
        int $perPage,
    ): LengthAwarePaginator {
        $category = $status ? TicketMaps::statusCategory($status) : null;

        return $this->query()
            ->with(self::RELATIONS)
            ->when($category, fn(Builder $q) => $q->where('status_category', $category))
            ->when(
                $search !== null && $search !== '',
                fn(Builder $q) => $q->where(
                    'subject',
                    'like',
                    '%' . addcslashes($search, '%_\\') . '%',
                ),
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    // customer visible messages only: internal notes and events are excluded
    public function paginateMessages(
        Conversation $ticket,
        int $perPage,
    ): LengthAwarePaginator {
        return $ticket
            ->items()
            ->where('type', 'message')
            ->with(['user', 'attachments'])
            ->orderBy('id')
            ->paginate($perPage);
    }
}
