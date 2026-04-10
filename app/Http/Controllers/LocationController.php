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

        if ($request->filled('active')) {
            $query->where('is_active', (bool) $request->active);
        }

        return $query->orderBy('sort_order')->get();
    }
}
