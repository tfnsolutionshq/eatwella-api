@extends('emails.layout')

@section('title', 'Welcome to EatWella!')

@section('heading', 'Welcome, {{ $user->name }}! 🎉')
@section('subheading', 'We\'re so glad you joined us')

@section('content')
<p style="font-size: 15px; color: #555; margin-bottom: 24px; text-align: center;">
    Your account has been created successfully. Delicious meals are just a few taps away!
</p>

@if($menuDiscount || $freeDeliveryDiscount)
<div class="section-title">🎁 A gift for your first order</div>

@if($menuDiscount && $freeDeliveryDiscount)
    {{-- Both --}}
    <div class="card" style="text-align: center;">
        <div class="card-label">Your Welcome Discount Code</div>
        <div class="card-value" style="font-size: 26px; letter-spacing: 4px; color: #ff6b00;">{{ $menuDiscount->code }}</div>
        <div style="margin-top: 10px; font-size: 13px; color: #888;">{{ $menuDiscount->value }}% off your first order</div>
    </div>
    <div class="notice">
        🚚 <strong>Free delivery</strong> on your first order — automatically applied at checkout!
    </div>
    <div style="margin-bottom: 20px;">
        <div class="row">
            <span class="label">Apply code:&nbsp;</span>
            <span class="value">Enter <strong style="color:#ff6b00;">{{ $menuDiscount->code }}</strong> at checkout</span>
        </div>
        <div class="row">
            <span class="label">Free delivery:&nbsp;</span>
            <span class="value">Applied automatically — no code needed</span>
        </div>
        <div class="row">
            <span class="label">Valid:&nbsp;</span>
            <span class="value">First order only</span>
        </div>
    </div>

@elseif($menuDiscount)
    {{-- Menu discount only --}}
    <div class="card" style="text-align: center;">
        <div class="card-label">Your Welcome Discount Code</div>
        <div class="card-value" style="font-size: 26px; letter-spacing: 4px; color: #ff6b00;">{{ $menuDiscount->code }}</div>
        <div style="margin-top: 10px; font-size: 13px; color: #888;">{{ $menuDiscount->value }}% off your first order</div>
    </div>
    <div style="margin-bottom: 20px;">
        <div class="row">
            <span class="label">Apply code:&nbsp;</span>
            <span class="value">Enter <strong style="color:#ff6b00;">{{ $menuDiscount->code }}</strong> at checkout</span>
        </div>
        <div class="row">
            <span class="label">Valid:&nbsp;</span>
            <span class="value">First order only</span>
        </div>
    </div>

@elseif($freeDeliveryDiscount)
    {{-- Free delivery only --}}
    <div class="notice" style="font-size: 15px; padding: 20px;">
        🚚 <strong>Free delivery</strong> on your first order — automatically applied at checkout!
    </div>
    <div style="margin-bottom: 20px;">
        <div class="row">
            <span class="label">How it works:&nbsp;</span>
            <span class="value">No code needed, applied automatically</span>
        </div>
        <div class="row">
            <span class="label">Valid:&nbsp;</span>
            <span class="value">First delivery order only</span>
        </div>
    </div>
@endif

@endif

<a href="{{ env('FRONTEND_URL') }}" class="btn">Start Ordering 🍽️</a>
@endsection
