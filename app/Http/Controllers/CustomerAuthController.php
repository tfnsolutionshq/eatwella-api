<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Services\LoyaltyService;
use App\Services\NewUserDiscountService;
use App\Mail\WelcomeEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
            'zone_id' => 'nullable|exists:zones,id',
            'street_address' => 'nullable|string',
            'closest_landmark' => 'nullable|string',
            'birth_month' => 'nullable|integer|min:1|max:12',
            'birthday' => 'nullable|date',
        ]);

        $user = User::create([
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'birth_month' => $validated['birth_month'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'role' => 'customer',
        ]);

        $user->addresses()->create([
            'zone_id' => $validated['zone_id'],
            'street_address' => $validated['street_address'],
            'closest_landmark' => $validated['closest_landmark'] ?? null,
        ]);

        $token = $user->createToken('customer-token')->plainTextToken;

        [$menuDiscount, $freeDeliveryDiscount] = (new NewUserDiscountService)->createForUser($user);

        Mail::to($user->email)->queue(new WelcomeEmail($user, $menuDiscount, $freeDeliveryDiscount));

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
            'user' => $user->load('addresses.zone'),
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
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'birth_month' => 'nullable|integer|min:1|max:12',
            'birthday' => 'nullable|date',
        ]);

        $data = array_filter([
            'name' => isset($validated['first_name']) || isset($validated['last_name'])
                ? trim(($validated['first_name'] ?? $request->user()->first_name) . ' ' . ($validated['last_name'] ?? $request->user()->last_name))
                : null,
            'phone' => $validated['phone'] ?? null,
            'birth_month' => $validated['birth_month'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
        ], fn($v) => !is_null($v));

        $request->user()->update($data);

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

    public function loyaltyInfo(Request $request)
    {
        if ($response = $this->requireRole($request, ['customer'])) {
            return $response;
        }

        $user = $request->user();
        $loyalty = app(LoyaltyService::class);

        $amount = (float) $request->query('amount', 0);

        return response()->json([
            'points_balance'      => $user->loyalty_points ?? 0,
            'points_value'        => $loyalty->pointsToNaira($user->loyalty_points ?? 0),
            'point_value'         => $loyalty->getPointValue(),
            'min_redemption'      => $loyalty->getMinRedemption(),
            'tiers'               => $loyalty->getTiers(),
            'points_for_amount'   => $amount > 0 ? $loyalty->calculatePointsForAmount($amount) : null,
            'points_needed'       => $amount > 0 ? $loyalty->nairaToPoints($amount) : null,
        ]);
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
