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
        $perPage = $request->integer('per_page', 20);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $yields = YieldLogUser::where('user_id', $user->id)
            ->with('yieldLog')
            ->latest()
            ->paginate($perPage);

        return response()->json($yields);
    }
}
