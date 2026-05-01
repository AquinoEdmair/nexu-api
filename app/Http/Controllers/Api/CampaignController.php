<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Campaigns\CampaignDeliveryService;
use App\Services\Campaigns\CampaignTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignDeliveryService $deliveryService,
        private readonly CampaignTrackingService $trackingService
    ) {}

    /**
     * Get all active campaigns for the current user.
     */
    public function active(Request $request): JsonResponse
    {
        $user      = $request->user();
        $campaigns = $this->deliveryService->getActiveCampaignsForUser($user);

        $data = $campaigns->map(fn(Campaign $campaign) => [
            'id'          => $campaign->id,
            'title'       => $campaign->title,
            'description' => $campaign->description,
            'image_url'   => $campaign->image_url,
            'type'        => $campaign->type,
            'cta_text'    => $campaign->cta_text,
            'cta_url'     => $campaign->cta_url,
            'cta_type'    => $campaign->cta_type,
            'priority'    => $campaign->priority,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Mark a campaign as viewed by the current user.
     */
    public function view(Request $request, string $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $user     = $request->user();
        $metadata = ['ip' => $request->ip(), 'user_agent' => $request->userAgent()];

        $this->trackingService->markAsViewed($campaign, $user, $metadata);

        return response()->json(null, 204);
    }

    /**
     * Record an action (accepted or rejected) for a campaign.
     */
    public function action(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:accepted,rejected',
        ]);

        $campaign = Campaign::findOrFail($id);
        $user     = $request->user();
        $metadata = ['ip' => $request->ip(), 'user_agent' => $request->userAgent()];

        $this->trackingService->recordAction($campaign, $user, $request->input('action'), $metadata);

        return response()->json(null, 204);
    }
}
