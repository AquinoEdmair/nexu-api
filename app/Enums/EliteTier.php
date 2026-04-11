<?php

declare(strict_types=1);

namespace App\Enums;

enum EliteTier: string
{
    case Bronze   = 'bronze';
    case Silver   = 'silver';
    case Gold     = 'gold';
    case Platinum = 'platinum';

    /** Resolve tier from an accumulated points amount. */
    public static function fromPoints(float|string $points): self
    {
        $points = (float) $points;
        $tiers  = config('referrals.tiers');

        // Traverse from highest to lowest so Platinum matches first.
        foreach (array_reverse(self::cases()) as $tier) {
            if ($points >= $tiers[$tier->value]['min']) {
                return $tier;
            }
        }

        return self::Bronze;
    }

    /** Human-readable Spanish label. */
    public function label(): string
    {
        return match ($this) {
            self::Bronze   => 'Bronce',
            self::Silver   => 'Plata',
            self::Gold     => 'Oro',
            self::Platinum => 'Platino',
        };
    }

    /** Next tier, or null if already at Platinum. */
    public function next(): ?self
    {
        return match ($this) {
            self::Bronze   => self::Silver,
            self::Silver   => self::Gold,
            self::Gold     => self::Platinum,
            self::Platinum => null,
        };
    }

    /** Minimum points required for this tier. */
    public function minPoints(): int
    {
        return (int) config("referrals.tiers.{$this->value}.min");
    }

    /** Maximum points for this tier (null = no upper bound). */
    public function maxPoints(): ?int
    {
        $max = config("referrals.tiers.{$this->value}.max");

        return $max !== null ? (int) $max : null;
    }

    /**
     * Progress percentage (0-100) toward the next tier.
     * Returns 100 when at Platinum.
     */
    public function progressPct(float|string $currentPoints): int
    {
        $points = (float) $currentPoints;
        $max    = $this->maxPoints();

        if ($max === null) {
            return 100;
        }

        $min   = $this->minPoints();
        $range = $max - $min + 1;

        if ($range <= 0) {
            return 100;
        }

        return (int) min(100, round((($points - $min) / $range) * 100));
    }
}
