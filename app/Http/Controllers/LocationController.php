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

    public function getCities(Request $request)
    {
        $query = City::query();
        if ($request->filled('state_id')) {
            $query->where('state_id', $request->state_id);
        }
        return $query->get();
    }

    public function getZones(Request $request)
    {
        $query = Zone::with('city');

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        // Default to active only for public
        if ($request->query('active', 1)) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')->get();
    }
}
