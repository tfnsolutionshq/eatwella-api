<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\State;
use App\Models\Zone;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function getStates()
    {
        return State::all();
    }

    public function storeState(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) return $response;

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:states,name',
            'code' => 'required|string|max:10|unique:states,code',
        ]);

        $state = State::create($validated);

        return response()->json(['status' => true, 'data' => $state], 201);
    }

    public function updateState(Request $request, State $state)
    {
        if ($response = $this->requireRole($request, ['admin'])) return $response;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:states,name,' . $state->id,
            'code' => 'sometimes|string|max:10|unique:states,code,' . $state->id,
        ]);

        $state->update($validated);

        return response()->json(['status' => true, 'data' => $state]);
    }

    public function destroyState(Request $request, State $state)
    {
        if ($response = $this->requireRole($request, ['admin'])) return $response;

        $state->delete();

        return response()->json(['status' => true, 'message' => 'State deleted successfully.']);
    }

    public function getCities(Request $request)
    {
        $query = City::query();
        if ($request->filled('state_id')) {
            $query->where('state_id', $request->state_id);
        }
        return $query->get();
    }

    public function storeCity(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) return $response;

        $validated = $request->validate([
            'state_id' => 'required|exists:states,id',
            'name'     => 'required|string|max:255',
        ]);

        $city = City::create($validated);

        return response()->json(['status' => true, 'data' => $city->load('state')], 201);
    }

    public function updateCity(Request $request, City $city)
    {
        if ($response = $this->requireRole($request, ['admin'])) return $response;

        $validated = $request->validate([
            'state_id' => 'sometimes|exists:states,id',
            'name'     => 'sometimes|string|max:255',
        ]);

        $city->update($validated);

        return response()->json(['status' => true, 'data' => $city->load('state')]);
    }

    public function destroyCity(Request $request, City $city)
    {
        if ($response = $this->requireRole($request, ['admin'])) return $response;

        $city->delete();

        return response()->json(['status' => true, 'message' => 'City deleted successfully.']);
    }

    public function getZones(Request $request)
    {
        $query = Zone::with('city');

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->filled('active')) {
            $query->where('is_active', (bool) $request->active);
        }

        return $query->orderBy('sort_order')->get();
    }
}
