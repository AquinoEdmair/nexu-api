<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

final class PublicConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'telegram_community_url' => SystemSetting::get('telegram_community_url'),
            'minimum_deposit_amount' => (float) SystemSetting::get('minimum_deposit_amount', '0'),
        ]);
    }
}
