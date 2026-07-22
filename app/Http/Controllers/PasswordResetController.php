<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\PasswordResetOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordResetController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'No account found with this email.'], 404);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put('password_reset_otp_' . $user->email, $otp, now()->addMinutes(10));

        Mail::to($user->email)->send(new PasswordResetOtp($user, $otp));

        return response()->json(['message' => 'OTP sent to your email address.']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $cached = Cache::get('password_reset_otp_' . $request->email);

        if (!$cached || $cached !== $request->otp) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        // Exchange OTP for a short-lived reset token
        $resetToken = bin2hex(random_bytes(32));
        Cache::put('password_reset_token_' . $request->email, $resetToken, now()->addMinutes(15));
        Cache::forget('password_reset_otp_' . $request->email);

        return response()->json(['reset_token' => $resetToken]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'       => 'required|email',
            'reset_token' => 'required|string',
            'password'    => 'required|string|min:6|confirmed',
        ]);

        $cached = Cache::get('password_reset_token_' . $request->email);

        if (!$cached || $cached !== $request->reset_token) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->update(['password' => $request->password]);
        Cache::forget('password_reset_token_' . $request->email);

        // Revoke all existing tokens so old sessions are invalidated
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
