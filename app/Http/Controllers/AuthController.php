<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if (! in_array($user->role, ['admin', 'attendant', 'supervisor', 'delivery_agent', 'kitchen'], true)) {
                Auth::logout();
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if ($user->is_suspended) {
                Auth::logout();
                return response()->json(['message' => 'Your account has been suspended.'], 403);
            }

            $token = $user->createToken('admin-token')->plainTextToken;

            return response()->json(['token' => $token, 'user' => $user]);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
