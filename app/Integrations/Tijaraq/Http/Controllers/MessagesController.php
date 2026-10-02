<?php

namespace App\Integrations\Tijaraq\Http\Controllers;

use App\Integrations\Tijaraq\Actions\ReplyToTicket;
use App\Integrations\Tijaraq\Http\Requests\ListMessagesRequest;
use App\Integrations\Tijaraq\Http\Requests\ReplyRequest;
use App\Integrations\Tijaraq\Http\Resources\TicketPresenter;
use App\Integrations\Tijaraq\Repositories\IntegrationTicketRepository;
use App\Integrations\Tijaraq\Services\AuditLogger;
use App\Integrations\Tijaraq\Services\IdempotentExecutor;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/** GET /tickets/{id}/messages and POST /tickets/{id}/replies */
class MessagesController extends Controller
{
    public function __construct(
        protected TicketPresenter $presenter,
        protected AuditLogger $audit,
    ) {}

    // resolved lazily: the router instantiates controllers before the HMAC
    // middleware has bound the context
    protected function ctx(): TijaraqContext
    {
        return app(TijaraqContext::class);
    }

    protected function tickets(): IntegrationTicketRepository
    {
        return new IntegrationTicketRepository($this->ctx());
    }

    public function index(ListMessagesRequest $request, string $id): JsonResponse
    {
        $ticket = $this->tickets()->findOrFail($id);
        $paginator = $this->tickets()->paginateMessages(
            $ticket,
            (int) ($request->validated('per_page') ?? 25),
        );

        return response()->json([
            'data' => $this->presenter->messages($paginator->items())->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        ReplyRequest $request,
        IdempotentExecutor $idempotency,
        string $id,
    ): JsonResponse {
        $ticket = $this->tickets()->findOrFail($id);
        $data = $request->validated();

        return $idempotency->run($this->ctx(), $request, function () use (
            $ticket,
            $data,
            $request,
        ) {
            $message = (new ReplyToTicket())->execute($this->ctx(), $ticket, $data);
            $this->audit->log('ticket.reply', $this->ctx(), $request, $ticket->id, [
                'message_id' => $message->id,
            ]);

            return [201, $this->presenter->message($message)];
        });
    }
}
