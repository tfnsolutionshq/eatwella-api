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
     * For all other staff: first checks token ability (outlet:<id>), then falls back to user's outlet_id.
     */
    protected function outletContext(Request $request): ?Outlet
    {
        $user = $request->user();

        if (! $user || $user->role === 'admin') {
            return null;
        }

        $token = $user->currentAccessToken();

        if ($token) {
            $abilities = $token->abilities ?? [];
            if (is_string($abilities)) {
                $abilities = json_decode($abilities, true) ?? [];
            }

            foreach ($abilities as $ability) {
                if (str_starts_with((string) $ability, 'outlet:')) {
                    $outletId = substr($ability, 7);
                    $outlet = Outlet::find($outletId);
                    if ($outlet) {
                        return $outlet;
                    }
                }
            }
        }

        if ($user->outlet_id) {
            return Outlet::find($user->outlet_id);
        }

        return null;
    }
}
