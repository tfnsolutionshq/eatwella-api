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

        return Setting::all();
    }

    public function update(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:settings,key',
            'settings.*.value' => 'required|string',
        ]);

        foreach ($validated['settings'] as $item) {
            Setting::where('key', $item['key'])->update(['value' => $item['value']]);
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
