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
            'availability_hours'                => 'required|array|size:7',
            'availability_hours.*.day'          => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'availability_hours.*.enabled'      => 'required|boolean',
            'availability_hours.*.open'         => 'nullable|required_if:availability_hours.*.enabled,true|string',
            'availability_hours.*.close'        => 'nullable|required_if:availability_hours.*.enabled,true|string',
        ]);

        Setting::updateOrCreate(
            ['key' => 'availability_hours'],
            ['value' => json_encode($validated['availability_hours'])]
        );

        return response()->json(['message' => 'Availability hours updated successfully']);
    }

    public function getAvailabilityHours()
    {
        $value = Setting::where('key', 'availability_hours')->value('value');
        $hours = $value ? json_decode($value, true) : [];
        return response()->json(['availability_hours' => $hours]);
    }
}
