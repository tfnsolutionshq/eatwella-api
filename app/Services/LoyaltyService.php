<?php

namespace App\Services;

use App\Models\Setting;

class LoyaltyService
{
    /**
     * Get loyalty tiers from settings.
     * Returns array of ['min' => int, 'max' => int|null, 'points' => int]
     */
    public function getTiers(): array
    {
        $raw = Setting::where('key', 'loyalty_points_tiers')->value('value');
        return $raw ? json_decode($raw, true) : [];
    }

    /**
     * Calculate points earned for a given order amount.
     */
    public function calculatePointsForAmount(float $amount): int
    {
        $tiers = $this->getTiers();
        $points = 0;

        foreach ($tiers as $index => $tier) {
            $min = (int) $tier['min'];
            $isLast = $index === array_key_last($tiers);
            $max = (!$isLast && isset($tier['max']) && $tier['max'] !== null)
                ? (int) $tier['max']
                : PHP_INT_MAX;

            if ($amount >= $min && $amount <= $max) {
                return (int) $tier['points'];
            }

            $points = (int) $tier['points'];
        }

        // Amount exceeds all tiers — return last tier's points
        return $points;
    }

    /**
     * Get naira value of 1 point.
     */
    public function getPointValue(): float
    {
        return (float) (Setting::where('key', 'loyalty_point_value')->value('value') ?? 1.0);
    }

    /**
     * Get minimum points required for redemption.
     */
    public function getMinRedemption(): int
    {
        return (int) (Setting::where('key', 'loyalty_min_points_redemption')->value('value') ?? 100);
    }

    /**
     * Convert points to naira value.
     */
    public function pointsToNaira(int $points): float
    {
        return round($points * $this->getPointValue(), 2);
    }

    /**
     * Convert naira amount to points needed.
     */
    public function nairaToPoints(float $amount): int
    {
        $pointValue = $this->getPointValue();
        if ($pointValue <= 0) return 0;
        return (int) ceil($amount / $pointValue);
    }
}
