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

    // Single Endpoint for Takeaway Price
    public function takeawayPrice(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $value = Setting::where('key', 'takeaway_price')->value('value');
        $price = (float) ($value ?? 0);
        if ($price < 0) {
            $price = 0;
        }

        return response()->json(['takeaway_price' => round($price, 2)]);
    }

    public function updateTakeawayPrice(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'takeaway_price' => 'required|numeric|min:0',
        ]);

        Setting::updateOrCreate(
            ['key' => 'takeaway_price'],
            ['value' => $validated['takeaway_price']]
        );

        return response()->json([
            'message' => 'Takeaway price updated successfully',
            'takeaway_price' => round($validated['takeaway_price'], 2),
        ]);
    }
}
