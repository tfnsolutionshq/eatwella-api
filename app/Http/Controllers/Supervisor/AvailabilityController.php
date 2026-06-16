<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\OutletAvailabilityHour;
use App\Models\Setting;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * GET /supervisor/outlet
     * Returns the supervisor's outlet with staff, delivery agents,
     * and resolved availability hours (outlet override or global fallback).
     */
    public function outlet(Request $request)
    {
        if ($response = $this->requireRole($request, ['supervisor'])) {
            return $response;
        }

        $outlet = $this->outletContext($request);

        if (! $outlet) {
            return response()->json(['message' => 'You are not assigned to any outlet.'], 403);
        }

        $outlet->load([
            'city.state',
            'users:id,name,email,phone,role,outlet_id',
            'deliveryAgents:id,name,email,phone,role',
        ]);

        $hours = $this->resolveHours($outlet->id);

        return response()->json([
            'outlet'             => $outlet,
            'availability_hours' => $hours,
        ]);
    }

    /**
     * PUT /supervisor/availability-hours
     * Writes outlet-level overrides to outlet_availability_hours.
     * Accepts a full 7-day array and upserts each day.
     */
    public function update(Request $request)
    {
        if ($response = $this->requireRole($request, ['supervisor'])) {
            return $response;
        }

        $outlet = $this->outletContext($request);

        if (! $outlet) {
            return response()->json(['message' => 'You are not assigned to any outlet.'], 403);
        }

        $validated = $request->validate([
            'availability_hours'                        => 'required|array|size:7',
            'availability_hours.*.day'                  => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'availability_hours.*.enabled'              => 'required|boolean',
            'availability_hours.*.open'                 => 'nullable|string',
            'availability_hours.*.close'                => 'nullable|string',
            'availability_hours.*.order_types'          => 'nullable|array',
            'availability_hours.*.order_types.dine'     => 'nullable|boolean',
            'availability_hours.*.order_types.pickup'   => 'nullable|boolean',
            'availability_hours.*.order_types.delivery' => 'nullable|boolean',
        ]);

        foreach ($validated['availability_hours'] as $entry) {
            OutletAvailabilityHour::updateOrCreate(
                ['outlet_id' => $outlet->id, 'day' => $entry['day']],
                [
                    'enabled'     => $entry['enabled'],
                    'open'        => $entry['open'] ?? null,
                    'close'       => $entry['close'] ?? null,
                    'order_types' => $entry['order_types'] ?? null,
                ]
            );
        }

        return response()->json([
            'message'            => 'Availability hours updated successfully.',
            'availability_hours' => $this->resolveHours($outlet->id),
        ]);
    }

    /**
     * Resolve availability hours for an outlet:
     * outlet override → global Setting fallback.
     */
    private function resolveHours(string $outletId): array
    {
        $overrides = OutletAvailabilityHour::where('outlet_id', $outletId)
            ->get()
            ->keyBy('day');

        if ($overrides->isNotEmpty()) {
            return $overrides->values()->map(fn ($h) => [
                'day'         => $h->day,
                'enabled'     => $h->enabled,
                'open'        => $h->open,
                'close'       => $h->close,
                'order_types' => $h->order_types,
            ])->values()->toArray();
        }

        $global = Setting::where('key', 'availability_hours')->value('value');

        return $global ? json_decode($global, true) : [];
    }
}
