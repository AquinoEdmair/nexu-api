<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\TransactionFilterDTO;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TransactionQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionQueryService $transactionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $filters = new TransactionFilterDTO(
            types:    $request->has('type') ? (array) $request->input('type') : null,
            statuses: $request->has('status') ? (array) $request->input('status') : null,
            dateFrom: $request->input('date_from'),
            dateTo:   $request->input('date_to'),
            userId:   $user->id,
            perPage:  min($request->integer('per_page', 20), 50),
        );

        $paginated = $this->transactionService->list($filters);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $transaction = $this->transactionService->getById($id);

        if ($transaction->user_id !== $user->id) {
            throw new NotFoundHttpException();
        }

        return response()->json([
            'data' => $transaction,
        ]);
    }
}
