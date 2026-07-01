<?php

namespace App\Http\Controllers;

use App\Mail\RiderApplicationReceived;
use App\Mail\RiderApplicationReviewed;
use App\Models\RiderApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RiderApplicationController extends Controller
{
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'phone'           => 'required|string|max:20',
            'city_id'         => 'required|exists:cities,id',
            'zone_id'         => 'required|exists:zones,id',
            'street_address'  => 'required|string|max:500',
            'vehicle_type'    => 'required|string|max:100',
            'profile_picture' => 'required|image|max:2048',
        ]);

        $existing = RiderApplication::where('email', $validated['email'])->first();

        $picturePath = $request->file('profile_picture')->store('rider-applications', 'public');
        $validated['profile_picture'] = $picturePath;

        if ($existing) {
            if ($existing->status === 'pending') {
                return response()->json(['message' => 'Your application is still under review.'], 422);
            }
            if ($existing->status === 'approved') {
                return response()->json(['message' => 'Your application has already been approved. Check your email for the activation code.'], 422);
            }
            $existing->update(array_merge($validated, [
                'status'          => 'pending',
                'admin_note'      => null,
                'activation_code' => null,
                'user_id'         => null,
            ]));
            $application = $existing->fresh();
        } else {
            $application = RiderApplication::create($validated);
        }

        Mail::to($application->email)->send(new RiderApplicationReceived($application));

        return response()->json(['message' => 'Application submitted. You will be notified via email.'], 201);
    }

    public function approve(Request $request, RiderApplication $application)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        if ($application->status !== 'pending') {
            return response()->json(['message' => 'Application has already been reviewed.'], 422);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $activationCode = strtoupper(Str::random(8));
        $tempPassword   = Str::random(12);

        $user = User::create([
            'name'         => trim($application->first_name . ' ' . $application->last_name),
            'email'        => $application->email,
            'phone'        => $application->phone,
            'password'     => $tempPassword,
            'role'         => 'delivery_agent',
            'is_activated' => false,
        ]);

        $application->update([
            'status'          => 'approved',
            'admin_note'      => $validated['note'] ?? null,
            'activation_code' => $activationCode,
            'user_id'         => $user->id,
        ]);

        Mail::to($application->email)->send(new RiderApplicationReviewed($application));

        return response()->json(['message' => 'Application approved. Activation email sent to rider.']);
    }

    public function reject(Request $request, RiderApplication $application)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        if ($application->status !== 'pending') {
            return response()->json(['message' => 'Application has already been reviewed.'], 422);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $application->update([
            'status'     => 'rejected',
            'admin_note' => $validated['note'] ?? null,
        ]);

        Mail::to($application->email)->send(new RiderApplicationReviewed($application));

        return response()->json(['message' => 'Application rejected.']);
    }

    public function activate(Request $request)
    {
        $request->validate([
            'email'           => 'required|email',
            'activation_code' => 'required|string',
            'password'        => 'required|string|min:6|confirmed',
        ]);

        $application = RiderApplication::where('email', $request->email)
            ->where('status', 'approved')
            ->first();

        if (! $application) {
            return response()->json(['message' => 'No approved application found for this email.'], 404);
        }

        if ($application->activation_code !== strtoupper($request->activation_code)) {
            return response()->json(['message' => 'Invalid activation code.'], 422);
        }

        $user = $application->user;

        if (! $user) {
            return response()->json(['message' => 'Account not found. Please contact support.'], 404);
        }

        if ($user->is_activated) {
            return response()->json(['message' => 'Account is already activated.'], 422);
        }

        $user->update([
            'password'     => $request->password,
            'is_activated' => true,
        ]);

        $application->update(['activation_code' => null]);

        return response()->json(['message' => 'Account activated successfully. You can now log in.']);
    }

    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $query = RiderApplication::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->with(['city', 'zone'])->latest()->paginate($request->get('per_page', 15)));
    }
}
