<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;

final class TeamMemberController extends Controller
{
    public function index(): JsonResponse
    {
        $members = TeamMember::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn(TeamMember $m): array => [
                'id'        => $m->id,
                'name'      => $m->name,
                'title'     => $m->title,
                'bio'       => $m->bio,
                'photo_url' => $m->photo_url,
            ]);

        return response()->json(['data' => $members]);
    }
}
