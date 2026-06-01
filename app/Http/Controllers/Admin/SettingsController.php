<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $settings = Setting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'string|nullable',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function setAvailabilityHours(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'availability_hours'                         => 'required|array|size:7',
            'availability_hours.*.day'                   => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'availability_hours.*.enabled'               => 'required|boolean',
            'availability_hours.*.open'                  => 'nullable|required_if:availability_hours.*.enabled,true|string',
            'availability_hours.*.close'                 => 'nullable|required_if:availability_hours.*.enabled,true|string',
            'availability_hours.*.order_types'           => 'nullable|array',
            'availability_hours.*.order_types.dine'      => 'nullable|boolean',
            'availability_hours.*.order_types.pickup'    => 'nullable|boolean',
            'availability_hours.*.order_types.delivery'  => 'nullable|boolean',
        ]);

        Setting::updateOrCreate(
            ['key' => 'availability_hours'],
            ['value' => json_encode($validated['availability_hours'])]
        );

        return response()->json(['message' => 'Availability hours updated successfully']);
    }

    public function getLoyaltySettings(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        return response()->json([
            'tiers'           => json_decode(Setting::where('key', 'loyalty_points_tiers')->value('value') ?? '[]', true),
            'point_value'     => (float) (Setting::where('key', 'loyalty_point_value')->value('value') ?? 1),
            'min_redemption'  => (int) (Setting::where('key', 'loyalty_min_points_redemption')->value('value') ?? 100),
        ]);
    }

    public function updateLoyaltySettings(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'tiers'                  => 'required|array|min:1',
            'tiers.*.min'            => 'required|integer|min:0',
            'tiers.*.max'            => 'nullable|integer|gt:tiers.*.min',
            'tiers.*.points'         => 'required|integer|min:1',
            'point_value'            => 'required|numeric|min:0.01',
            'min_redemption'         => 'required|integer|min:1',
        ]);

        Setting::updateOrCreate(['key' => 'loyalty_points_tiers'],       ['value' => json_encode($validated['tiers'])]);
        Setting::updateOrCreate(['key' => 'loyalty_point_value'],        ['value' => $validated['point_value']]);
        Setting::updateOrCreate(['key' => 'loyalty_min_points_redemption'], ['value' => $validated['min_redemption']]);

        return response()->json(['message' => 'Loyalty settings updated successfully']);
    }

    public function getAvailabilityHours()
    {
        $value = Setting::where('key', 'availability_hours')->value('value');
        $hours = $value ? json_decode($value, true) : [];
        return response()->json(['availability_hours' => $hours]);
    }

    public function getTaxMode(Request $request)
    {
        // if ($response = $this->requireRole($request, ['admin'])) {
        //     return $response;
        // }

        $mode = Setting::where('key', 'tax_mode')->value('value') ?? 'exclusive';
        return response()->json(['tax_mode' => $mode]);
    }

    public function setTaxMode(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'tax_mode' => 'required|in:exclusive,inclusive',
        ]);

        Setting::updateOrCreate(['key' => 'tax_mode'], ['value' => $validated['tax_mode']]);

        return response()->json(['message' => 'Tax mode updated.', 'tax_mode' => $validated['tax_mode']]);
    }
}
