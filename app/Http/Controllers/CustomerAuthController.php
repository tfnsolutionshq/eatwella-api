<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
            'zone_id' => 'required|exists:zones,id',
            'street_address' => 'required|string',
            'closest_landmark' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'role' => 'customer',
        ]);

        $user->addresses()->create([
            'zone_id' => $validated['zone_id'],
            'street_address' => $validated['street_address'],
            'closest_landmark' => $validated['closest_landmark'] ?? null,
        ]);

        $token = $user->createToken('customer-token')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->role !== 'customer') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $token = $user->createToken('customer-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    }

    public function profile(Request $request)
    {
        if ($response = $this->requireRole($request, ['customer'])) {
            return $response;
        }
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        if ($response = $this->requireRole($request, ['customer'])) {
            return $response;
        }
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $request->user()
        ]);
    }

    public function overview(Request $request)
    {
        if ($response = $this->requireRole($request, ['customer'])) {
            return $response;
        }
        $user = $request->user();

        $totalOrders = Order::where('user_id', $user->id)->count();
        $totalSpent = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'confirmed'])
            ->sum('final_amount');

        return response()->json([
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'loyalty_points' => $user->loyalty_points ?? 0,
            'member_tier' => null
        ]);
    }

    public function recentOrders(Request $request)
    {
        if ($response = $this->requireRole($request, ['customer'])) {
            return $response;
        }
        $perPage = $request->get('per_page', 10);

        $orders = Order::where('user_id', $request->user()->id)
            ->with(['orderItems.menu', 'orderItems.packaging', 'invoice', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name'])
            ->latest()
            ->paginate($perPage);

        $orders->getCollection()->transform(function ($order) {
            return $order->makeVisible('delivery_pin');
        });

        return response()->json($orders);
    }

    public function logout(Request $request)
    {
        if ($response = $this->requireRole($request, ['customer'])) {
            return $response;
        }
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function changePassword(Request $request)
    {
        if ($response = $this->requireRole($request, ['customer'])) {
            return $response;
        }
        $validated = $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6',
        ]);

        if (!Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $request->user()->update(['password' => $validated['new_password']]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function deleteAccount(Request $request)
    {
        if ($response = $this->requireRole($request, ['customer'])) {
            return $response;
        }
        $request->validate(['password' => 'required']);

        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['message' => 'Password is incorrect'], 422);
        }

        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }
}
