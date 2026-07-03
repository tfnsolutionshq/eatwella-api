<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'     => 'required|email',
            'password'  => 'required',
            'outlet_id' => 'nullable|uuid|exists:outlets,id',
        ]);

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();

        if (! in_array($user->role, ['admin', 'attendant', 'supervisor', 'delivery_agent', 'kitchen', 'store_keeper'], true)) {
            Auth::logout();
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->is_suspended) {
            Auth::logout();
            return response()->json(['message' => 'Your account has been suspended.'], 403);
        }

        if ($user->role === 'delivery_agent' && ! $user->is_activated) {
            Auth::logout();
            return response()->json(['message' => 'Your account is not activated. Please check your email for the activation code.'], 403);
        }

        $outletId  = $request->outlet_id ?? null;
        $abilities = ['*'];

        if ($user->role === 'admin') {
            // Admin sees everything — no outlet scoping
            $token = $user->createToken('staff-token', ['*'])->plainTextToken;
            return response()->json(['token' => $token, 'user' => $user]);
        }

        // All other staff must provide an outlet_id
        if (! $outletId) {
            Auth::logout();
            return response()->json(['message' => 'Please select an outlet to log in.'], 422);
        }

        $outlet = Outlet::find($outletId);

        if (! $outlet || ! $outlet->is_active) {
            Auth::logout();
            return response()->json(['message' => 'The selected outlet is not available.'], 422);
        }

        // Validate the staff member belongs to the requested outlet
        $allowed = match ($user->role) {
            'supervisor' => $user->outlets()->where('outlets.id', $outletId)->exists(),
            default      => (string) $user->outlet_id === (string) $outletId,
        };

        if (! $allowed) {
            Auth::logout();
            return response()->json(['message' => 'You are not assigned to this outlet.'], 403);
        }

        $token = $user->createToken('staff-token', ['outlet:' . $outletId])->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user->load('outlet'), 'outlet' => $outlet]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
