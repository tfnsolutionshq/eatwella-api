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
}
