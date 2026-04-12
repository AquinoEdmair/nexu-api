<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ElitePoint;
use App\Models\EliteTier;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class EliteTierService
{
    /**
     * Re-evaluate the user's tier based on their accumulated points.
     * Skips users with a manual override. Idempotent.
     */
    public function recalculateForUser(User $user): void
    {
        if ($user->elite_tier_manual_override) {
            return;
        }

        $totalPoints = (float) ElitePoint::where('user_id', $user->id)->sum('points');
        $newTier     = $this->getTierForPoints($totalPoints);

        if ($newTier?->id !== $user->elite_tier_id) {
            $user->update(['elite_tier_id' => $newTier?->id]);
        }
    }

    /**
     * Returns the active tier that contains the given points value, or null
     * if no active tiers are configured.
     */
    public function getTierForPoints(float $points): ?EliteTier
    {
        // Highest sort_order wins when ranges overlap.
        return EliteTier::active()
            ->where('min_points', '<=', $points)
            ->where(function ($q) use ($points): void {
                $q->whereNull('max_points')
                  ->orWhere('max_points', '>=', $points);
            })
            ->orderByDesc('sort_order')
            ->first();
    }

    /**
     * All active tiers ordered by progression (lowest first).
     *
     * @return Collection<int, EliteTier>
     */
    public function getAll(): Collection
    {
        return EliteTier::active()->ordered()->get();
    }

    /**
     * The tier immediately above $tier in sort_order, or null if at the top.
     */
    public function getNextTier(EliteTier $tier): ?EliteTier
    {
        return EliteTier::active()
            ->where('sort_order', '>', $tier->sort_order)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Manually assign a tier to a user and lock it from auto-recalculation.
     */
    public function assignManually(User $user, EliteTier $tier): void
    {
        $user->update([
            'elite_tier_id'              => $tier->id,
            'elite_tier_manual_override' => true,
        ]);
    }

    /**
     * Release the manual override and recalculate from points.
     */
    public function releaseOverride(User $user): void
    {
        $user->update(['elite_tier_manual_override' => false]);
        $this->recalculateForUser($user);
    }
}
