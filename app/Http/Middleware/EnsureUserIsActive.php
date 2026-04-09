<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === 'blocked') {
            return response()->json([
                'message' => 'Your account has been blocked. Contact support.',
            ], 403);
        }

        return $next($request);
    }
}
