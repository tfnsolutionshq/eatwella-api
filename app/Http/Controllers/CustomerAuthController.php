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
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'name' => explode('@', $validated['email'])[0],
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

        $token = $user->createToken('customer-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
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
        $user = $request->user();
        
        $totalOrders = Order::where('user_id', $user->id)->count();
        $totalSpent = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'confirmed'])
            ->sum('final_amount');

        return response()->json([
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'loyalty_points' => 0,
            'member_tier' => null
        ]);
    }

    public function recentOrders(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['orderItems.menu', 'invoice'])
            ->latest()
            ->paginate($perPage);

        return response()->json($orders);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function changePassword(Request $request)
    {
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
