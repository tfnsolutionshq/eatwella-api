<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function requireRole(Request $request, array $roles): ?JsonResponse
    {
        $user = auth('sanctum')->user() ?? $request->user();

        $role         = is_string($user?->role) ? strtolower(trim($user->role)) : null;
        $allowedRoles = array_map(static fn ($r) => strtolower(trim((string) $r)), $roles);

        if (! $user || ! in_array($role, $allowedRoles, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    /**
     * Resolve the outlet context for the authenticated user or request.
     * First checks X-Outlet-ID header, then token ability (outlet:<id>),
     * and finally user's outlet_id for non-admin staff.
     */
    protected function outletContext(Request $request): ?Outlet
    {
        // 1. Explicit header (works for guest or any authenticated user)
        if ($headerOutletId = $request->header('X-Outlet-ID')) {
            if ($outlet = Outlet::find($headerOutletId)) {
                return $outlet;
            }
        }

        $user = auth('sanctum')->user() ?? $request->user();

        if (! $user) {
            return null;
        }

        // 2. Token abilities (e.g. staff or admin logged into a specific outlet)
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

        // 3. Fall back to user's assigned outlet_id for non-admin staff
        if ($user->role !== 'admin' && $user->outlet_id) {
            return Outlet::find($user->outlet_id);
        }

        return null;
    }
}
