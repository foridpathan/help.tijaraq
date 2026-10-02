<?php

namespace App\Integrations\Tijaraq\Http\Controllers;

use App\Integrations\Tijaraq\Actions\ChangeTicketStatus;
use App\Integrations\Tijaraq\Actions\CreateTicket;
use App\Integrations\Tijaraq\Http\Requests\CreateTicketRequest;
use App\Integrations\Tijaraq\Http\Requests\ListTicketsRequest;
use App\Integrations\Tijaraq\Http\Resources\TicketPresenter;
use App\Integrations\Tijaraq\Repositories\IntegrationTicketRepository;
use App\Integrations\Tijaraq\Services\AuditLogger;
use App\Integrations\Tijaraq\Services\IdempotentExecutor;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TicketsController extends Controller
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

    public function index(ListTicketsRequest $request): JsonResponse
    {
        $paginator = $this->tickets()->paginate(
            $request->validated('status'),
            $request->validated('search'),
            (int) ($request->validated('per_page') ?? 25),
        );

        return response()->json([
            'data' => $this->presenter->tickets($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        CreateTicketRequest $request,
        IdempotentExecutor $idempotency,
    ): JsonResponse {
        $data = $request->validated();

        return $idempotency->run($this->ctx(), $request, function () use (
            $data,
            $request,
        ) {
            $ticket = (new CreateTicket())->execute($this->ctx(), $data);
            $this->audit->log('ticket.create', $this->ctx(), $request, $ticket->id);

            return [201, $this->presenter->ticket($ticket)];
        });
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(
            $this->presenter->ticket($this->tickets()->findOrFail($id)),
        );
    }

    public function close(Request $request, string $id): JsonResponse
    {
        $ticket = (new ChangeTicketStatus())->close(
            $this->ctx(),
            $this->tickets()->findOrFail($id),
        );
        $this->audit->log('ticket.close', $this->ctx(), $request, $ticket->id);

        return response()->json($this->presenter->ticket($ticket));
    }

    public function reopen(Request $request, string $id): JsonResponse
    {
        $ticket = (new ChangeTicketStatus())->reopen(
            $this->ctx(),
            $this->tickets()->findOrFail($id),
        );
        $this->audit->log('ticket.reopen', $this->ctx(), $request, $ticket->id);

        return response()->json($this->presenter->ticket($ticket));
    }
}
