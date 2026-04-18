<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSupportTicketRequest;
use App\Http\Requests\ReplySupportTicketRequest;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->withCount('messages')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($tickets);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)
            ->with('messages')
            ->findOrFail($id);

        return response()->json(['data' => $ticket]);
    }

    public function store(CreateSupportTicketRequest $request): JsonResponse
    {
        $ticket = $this->service->create(
            $request->user(),
            $request->string('subject')->toString(),
            $request->string('message')->toString(),
        );

        return response()->json(['data' => $ticket->load('messages')], 201);
    }

    public function reply(ReplySupportTicketRequest $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            $message = $this->service->reply(
                $ticket,
                $request->string('message')->toString(),
                'user',
                (string) $request->user()->id,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $message], 201);
    }
}
