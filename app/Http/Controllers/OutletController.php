<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    /**
     * GET /outlets?state_id=&city_id=
     * Public list of active outlets, filterable by state or city.
     */
    public function index(Request $request)
    {
        $query = Outlet::with('city.state', 'availabilityHours')->where('is_active', true);

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        } elseif ($request->filled('state_id')) {
            $query->whereHas('city', fn ($q) => $q->where('state_id', $request->state_id));
        }

        return response()->json($query->orderBy('name')->get());
    }

    /**
     * GET /outlets/{outlet}
     * Public detail view of a single active outlet.
     */
    public function show(Outlet $outlet)
    {
        if (! $outlet->is_active) {
            return response()->json(['message' => 'Outlet not found.'], 404);
        }

        return response()->json($outlet->load('city.state', 'availabilityHours'));
    }
}
