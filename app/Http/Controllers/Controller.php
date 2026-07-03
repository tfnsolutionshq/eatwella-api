<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function requireRole(Request $request, array $roles): ?JsonResponse
    {
        $user = $request->user();

        $role         = is_string($user?->role) ? strtolower(trim($user->role)) : null;
        $allowedRoles = array_map(static fn ($r) => strtolower(trim((string) $r)), $roles);

        if (! $user || ! in_array($role, $allowedRoles, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    /**
     * Resolve the outlet context for the authenticated user.
     * For admin: returns null (global scope).
     * For all other staff: reads the outlet baked into the token ability (outlet:<id>).
     */
    protected function outletContext(Request $request): ?Outlet
    {
        $user = $request->user();

        if (! $user || $user->role === 'admin') {
            return null;
        }

        $token = $user->currentAccessToken();

        if (! $token || ! property_exists($token, 'abilities')) {
            return null;
        }

        $abilities = is_array($token->abilities) ? $token->abilities : [];

        foreach ($abilities as $ability) {
            if (str_starts_with($ability, 'outlet:')) {
                $outletId = substr($ability, 7);
                return Outlet::find($outletId);
            }
        }

        return null;
    }
}
