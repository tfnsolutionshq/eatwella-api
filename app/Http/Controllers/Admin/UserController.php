<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $query = User::with('addresses');

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

        return $user->load(['addresses', 'orders.orderItems.menu']);
    }

    public function storeStaff(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:attendant,supervisor,delivery_agent,kitchen',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
        ]);

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

    private const SUSPENDABLE_ROLES = ['attendant', 'supervisor', 'delivery_agent', 'kitchen'];

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
            'name'     => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'email'    => 'nullable|email|unique:users,email,' . $user->id,
            'role'     => 'nullable|in:customer,attendant,supervisor,delivery_agent,kitchen,admin',
        ]);

        $user->update(array_filter($validated, fn($v) => !is_null($v)));

        return response()->json([
            'message' => 'User profile updated successfully',
            'user'    => $user->fresh(),
        ]);
    }
}
