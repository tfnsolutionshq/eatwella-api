<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin', 'superadmin'])) {
            return $response;
        }

        $query = Campaign::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin', 'superadmin'])) {
            return $response;
        }

        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'brief'      => 'nullable|string|max:500',
            'details'    => 'required|string',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'status'     => 'required|in:published,drafted',
            'url'        => 'nullable|url|max:500',
            'type'       => 'required|in:modal,banner',
            'image'      => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('campaign-images', 'public');
        }

        $campaign = Campaign::create($validated);

        return response()->json([
            'message'  => 'Campaign created successfully',
            'campaign' => $campaign,
        ], 201);
    }

    public function show(Request $request, Campaign $campaign)
    {
        if ($response = $this->requireRole($request, ['admin', 'superadmin'])) {
            return $response;
        }

        return response()->json($campaign);
    }

    public function update(Request $request, Campaign $campaign)
    {
        if ($response = $this->requireRole($request, ['admin', 'superadmin'])) {
            return $response;
        }

        $validated = $request->validate([
            'title'      => 'sometimes|string|max:255',
            'brief'      => 'nullable|string|max:500',
            'details'    => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after_or_equal:start_date',
            'status'     => 'sometimes|in:published,drafted',
            'url'        => 'nullable|url|max:500',
            'type'       => 'sometimes|in:modal,banner',
            'image'      => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($campaign->image_path) {
                Storage::disk('public')->delete($campaign->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('campaign-images', 'public');
        }

        $campaign->update($validated);

        return response()->json([
            'message'  => 'Campaign updated successfully',
            'campaign' => $campaign,
        ]);
    }

    public function destroy(Request $request, Campaign $campaign)
    {
        if ($response = $this->requireRole($request, ['admin', 'superadmin'])) {
            return $response;
        }

        if ($campaign->image_path) {
            Storage::disk('public')->delete($campaign->image_path);
        }

        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted successfully']);
    }

    public function publish(Request $request, Campaign $campaign)
    {
        if ($response = $this->requireRole($request, ['admin', 'superadmin'])) {
            return $response;
        }

        $campaign->update(['status' => 'published']);

        return response()->json(['message' => 'Campaign published', 'campaign' => $campaign]);
    }

    public function draft(Request $request, Campaign $campaign)
    {
        if ($response = $this->requireRole($request, ['admin', 'superadmin'])) {
            return $response;
        }

        $campaign->update(['status' => 'drafted']);

        return response()->json(['message' => 'Campaign drafted', 'campaign' => $campaign]);
    }

    public function publicIndex(Request $request)
    {
        $query = Campaign::where('status', 'published')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function publicShow(Campaign $campaign)
    {
        if ($campaign->status !== 'published') {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        return response()->json($campaign);
    }
}
