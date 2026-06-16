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

        $role = is_string($user?->role) ? strtolower(trim($user->role)) : null;
        $allowedRoles = array_map(static fn ($r) => strtolower(trim((string) $r)), $roles);

        if (! $user || ! in_array($role, $allowedRoles, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    /**
     * Resolve the outlet context for the authenticated user.
     * Returns the user's outlet for single-outlet roles.
     * Returns null for admin (global) and customer (no outlet).
     */
    protected function outletContext(Request $request): ?Outlet
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $singleOutletRoles = ['supervisor', 'attendant', 'kitchen', 'store_keeper'];

        if (in_array($user->role, $singleOutletRoles, true)) {
            return $user->outlet;
        }

        return null;
    }
}
