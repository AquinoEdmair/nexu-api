<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\YieldLogUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class YieldController extends Controller
{
    /**
     * Display a listing of the user's yield history.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 20), 500);
        $days    = $request->integer('days', 0);
        $hours   = $request->integer('hours', 0);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = YieldLogUser::where('user_id', $user->id)
            ->with('yieldLog')
            ->latest();

        if ($hours > 0) {
            $query->where('created_at', '>=', now()->subHours($hours));
        } elseif ($days > 0) {
            $since = $days === 1
                ? now()->startOfDay()
                : now()->subDays($days);
            $query->where('created_at', '>=', $since);
        }

        return response()->json($query->paginate($perPage));
    }
}
