<?php

namespace App\Http\Controllers;

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
}
