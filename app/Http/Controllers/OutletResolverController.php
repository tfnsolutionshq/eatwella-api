<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Zone;
use Illuminate\Http\Request;

class OutletResolverController extends Controller
{
    public function resolve(Request $request)
    {
        $request->validate([
            'zone_id' => 'required|exists:zones,id',
        ]);

        $zone = Zone::with('city')->findOrFail($request->zone_id);

        if (! $zone->is_active) {
            return response()->json(['message' => 'We do not deliver to this zone.'], 422);
        }

        $outlets = Outlet::where('city_id', $zone->city_id)
            ->where('is_active', true)
            ->get();

        if ($outlets->isEmpty()) {
            return response()->json(['message' => 'We do not deliver to your area yet.'], 404);
        }

        // If zone has no coordinates, fall back to first outlet in city
        if (is_null($zone->latitude) || is_null($zone->longitude)) {
            $nearest = $outlets->first();
        } else {
            $nearest = $outlets
                ->filter(fn ($o) => ! is_null($o->latitude) && ! is_null($o->longitude))
                ->sortBy(fn ($o) => $this->haversine($zone->latitude, $zone->longitude, $o->latitude, $o->longitude))
                ->first() ?? $outlets->first(); // fallback if no outlet has coordinates yet
        }

        return response()->json([
            'outlet'       => $nearest->load('city.state', 'availabilityHours'),
            'zone'         => $zone,
            'delivery_fee' => $zone->delivery_fee,
        ]);
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * asin(sqrt($a));
    }

    /**
     * POST /outlets/nearest
     * Accept user coordinates and return the nearest active outlet.
     */
    public function nearest(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;

        $outlets = Outlet::with('city.state', 'availabilityHours')
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($outlets->isEmpty()) {
            // Fall back to any active outlet if none have coordinates set
            $fallback = Outlet::with('city.state', 'availabilityHours')
                ->where('is_active', true)
                ->first();

            if (! $fallback) {
                return response()->json(['message' => 'No outlets available.'], 404);
            }

            return response()->json(['outlet' => $fallback, 'distance' => null]);
        }

        $nearest = $outlets
            ->sortBy(fn ($o) => $this->haversine($lat, $lng, $o->latitude, $o->longitude))
            ->first();

        return response()->json([
            'outlet'   => $nearest,
            'distance' => round($this->haversine($lat, $lng, $nearest->latitude, $nearest->longitude), 2),
        ]);
    }
}
