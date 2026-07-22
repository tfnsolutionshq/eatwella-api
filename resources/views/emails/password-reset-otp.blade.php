@extends('emails.layout')

@section('title', 'Password Reset OTP')
@section('heading', 'Reset Your Password')
@section('subheading', 'Use the OTP below to reset your password')

@section('content')
    <p style="font-size:15px; color:#555; margin-bottom:20px;">
        Hi {{ $user->first_name }}, we received a request to reset your EatWella password.
        Use the OTP below — it expires in <strong>10 minutes</strong>.
    </p>

    <div class="pin-box">
        <div class="pin">{{ $otp }}</div>
        <p>Do not share this code with anyone.</p>
    </div>

    <p style="font-size:13px; color:#aaa; text-align:center; margin-top:16px;">
        If you didn't request a password reset, you can safely ignore this email.
    </p>
@endsection
