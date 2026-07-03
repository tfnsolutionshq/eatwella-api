<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $outlets = Outlet::with('city.state')
            ->withCount(['supervisors', 'deliveryAgents'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json($outlets);
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'city_id'   => 'required|exists:cities,id',
            'address'   => 'required|string|max:500',
            'phone'     => 'required|string|max:20',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $outlet = Outlet::create($validated);

        return response()->json($outlet->load('city.state'), 201);
    }

    public function show(Request $request, Outlet $outlet)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $outlet->load([
            'city.state',
            'users:id,name,email,phone,role,outlet_id',
            'deliveryAgents:id,name,email,phone,role',
            'availabilityHours',
        ]);

        $outlet->loadCount(['supervisors', 'deliveryAgents']);

        return response()->json($outlet);
    }

    public function update(Request $request, Outlet $outlet)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'city_id'   => 'sometimes|exists:cities,id',
            'address'   => 'sometimes|string|max:500',
            'phone'     => 'sometimes|string|max:20',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $outlet->update($validated);

        return response()->json($outlet->load('city.state'));
    }

    public function toggle(Request $request, Outlet $outlet)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $outlet->update(['is_active' => ! $outlet->is_active]);

        return response()->json([
            'message'   => 'Outlet ' . ($outlet->is_active ? 'activated' : 'deactivated') . ' successfully.',
            'is_active' => $outlet->is_active,
        ]);
    }

    public function assignSupervisor(Request $request, Outlet $outlet)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'required|uuid|exists:users,id',
        ]);

        $assigned = [];

        foreach ($validated['user_ids'] as $userId) {
            $user = User::findOrFail($userId);

            if ($user->role !== 'supervisor') {
                return response()->json([
                    'message' => "User {$user->name} is not a supervisor.",
                ], 422);
            }

            $outlet->deliveryAgents()->syncWithoutDetaching([$user->id]);
            $assigned[] = $user->only('id', 'name', 'email', 'role');
        }

        return response()->json([
            'message'  => 'Supervisor(s) assigned successfully.',
            'assigned' => $assigned,
        ]);
    }

    public function assignDeliveryAgent(Request $request, Outlet $outlet)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'required|uuid|exists:users,id',
        ]);

        $assigned = [];

        foreach ($validated['user_ids'] as $userId) {
            $agent = User::findOrFail($userId);

            if ($agent->role !== 'delivery_agent') {
                return response()->json(['message' => "User {$agent->name} is not a delivery agent."], 422);
            }

            $agentCityIds = $agent->cities()->pluck('cities.id');

            if (! $agentCityIds->contains($outlet->city_id)) {
                return response()->json([
                    'message' => "{$agent->name} is not assigned to this outlet's city.",
                ], 422);
            }

            $outlet->deliveryAgents()->syncWithoutDetaching([$agent->id]);

            // Also ensure the agent's city list includes this outlet's city
            $agent->cities()->syncWithoutDetaching([$outlet->city_id]);

            // Set outlet_id on agent if not already set
            if (is_null($agent->outlet_id)) {
                $agent->update(['outlet_id' => $outlet->id]);
            }

            $assigned[] = $agent->only('id', 'name', 'email', 'role');
        }

        return response()->json([
            'message'  => 'Delivery agent(s) assigned successfully.',
            'assigned' => $assigned,
        ]);
    }

    public function removeUser(Request $request, Outlet $outlet, User $user)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        if ($user->role === 'delivery_agent') {
            $outlet->deliveryAgents()->detach($user->id);
        } elseif ($user->role === 'supervisor') {
            $outlet->deliveryAgents()->detach($user->id);
        } else {
            if ((string) $user->outlet_id !== (string) $outlet->id) {
                return response()->json(['message' => 'User does not belong to this outlet.'], 422);
            }
            $user->update(['outlet_id' => null]);
        }

        return response()->json(['message' => 'User removed from outlet successfully.']);
    }
}
