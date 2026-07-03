<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffWelcomeEmail;
use App\Models\City;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin', 'attendant'])) {
            return $response;
        }
        $query = User::with('addresses')
            ->where('email', '!=', 'eatwella@gmail.com');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%')
                    ->orWhere('phone', 'like', '%'.$request->search.'%');
            });
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function show(Request $request, User $user)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $relations = ['addresses', 'orders.orderItems.menu'];

        if ($user->role === 'delivery_agent') {
            $relations[] = 'bankDetails';
        }

        return $user->load($relations);
    }

    public function storeStaff(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $singleOutletRoles = ['attendant', 'kitchen', 'store_keeper'];

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'nullable|string|min:6',
            'role'      => 'required|in:attendant,supervisor,delivery_agent,kitchen,store_keeper',
            'outlet_id' => [
                in_array($request->role, $singleOutletRoles) ? 'required' : 'nullable',
                'uuid',
                'exists:outlets,id',
            ],
        ]);

        $plainPassword = $validated['password'] ?? \Illuminate\Support\Str::password(12);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => $plainPassword,
            'role'      => $validated['role'],
            'outlet_id' => $validated['outlet_id'] ?? null,
        ]);

        Mail::to($user->email)->send(new StaffWelcomeEmail($user->load('outlet'), $plainPassword));

        return response()->json($user, 201);
    }

    public function storeAttendant(Request $request)
    {
        $request->merge(['role' => 'attendant']);

        return $this->storeStaff($request);
    }

    public function updateProfile(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'email'    => 'nullable|email|unique:users,email,' . $request->user()->id,
        ]);

        $request->user()->update(array_filter($validated, fn($v) => !is_null($v)));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $request->user()->fresh(),
        ]);
    }

    private const SUSPENDABLE_ROLES = ['attendant', 'supervisor', 'delivery_agent', 'kitchen', 'store_keeper'];

    public function suspend(Request $request, User $user)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        if (! in_array($user->role, self::SUSPENDABLE_ROLES, true)) {
            return response()->json(['status' => false, 'message' => 'This user cannot be suspended.'], 422);
        }

        $user->update(['is_suspended' => true]);
        $user->tokens()->delete();

        return response()->json(['status' => true, 'message' => 'User suspended successfully.']);
    }

    public function unsuspend(Request $request, User $user)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $user->update(['is_suspended' => false]);

        return response()->json(['status' => true, 'message' => 'User unsuspended successfully.']);
    }

    public function updateUserProfile(Request $request, User $user)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name'      => 'nullable|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'birthday'  => 'nullable|date',
            'email'     => 'nullable|email|unique:users,email,' . $user->id,
            'role'      => 'nullable|in:customer,attendant,supervisor,delivery_agent,kitchen,admin,store_keeper',
            'outlet_id' => 'nullable|uuid|exists:outlets,id',
        ]);

        $user->update(array_filter($validated, fn($v) => !is_null($v)));

        return response()->json([
            'message' => 'User profile updated successfully',
            'user'    => $user->fresh(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Delivery Agent City Management
    // -------------------------------------------------------------------------

    public function listCities(Request $request, User $user)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        if ($user->role !== 'delivery_agent') {
            return response()->json(['message' => 'User is not a delivery agent.'], 422);
        }

        return response()->json($user->cities()->with('state')->get());
    }

    public function assignCities(Request $request, User $user)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        if ($user->role !== 'delivery_agent') {
            return response()->json(['message' => 'User is not a delivery agent.'], 422);
        }

        $validated = $request->validate([
            'city_ids'   => 'required|array|min:1',
            'city_ids.*' => 'required|integer|exists:cities,id',
        ]);

        $user->cities()->syncWithoutDetaching($validated['city_ids']);

        // Auto-assign outlet_id from the first active outlet found in any of the assigned cities
        if (is_null($user->outlet_id)) {
            $outlet = \App\Models\Outlet::whereIn('city_id', $validated['city_ids'])
                ->where('is_active', true)
                ->first();

            if ($outlet) {
                $user->update(['outlet_id' => $outlet->id]);
                $outlet->deliveryAgents()->syncWithoutDetaching([$user->id]);
            }
        }

        return response()->json([
            'message' => 'Cities assigned successfully.',
            'cities'  => $user->cities()->with('state')->get(),
        ]);
    }

    public function removeCity(Request $request, User $user, City $city)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        if ($user->role !== 'delivery_agent') {
            return response()->json(['message' => 'User is not a delivery agent.'], 422);
        }

        $user->cities()->detach($city->id);

        return response()->json(['message' => 'City removed from delivery agent successfully.']);
    }
}
