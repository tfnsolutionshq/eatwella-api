@extends('emails.layout')

@section('title', 'Happy Birthday!')

@section('heading', '🎂 Happy Birthday, {{ $user->name }}!')
@section('subheading', 'A special gift from all of us at EatWella')

@section('content')
<p style="font-size: 15px; color: #555; margin-bottom: 24px; text-align: center;">
    Wishing you a wonderful birthday! To celebrate with you, we've got a special treat just for you today. 🎉
</p>

@if($menuDiscount && $freeDeliveryDiscount)
    {{-- Both: menu discount + free delivery --}}
    <div class="card" style="text-align: center;">
        <div class="card-label">Your Birthday Discount Code</div>
        <div class="card-value" style="font-size: 28px; letter-spacing: 4px; color: #ff6b00;">{{ $menuDiscount->code }}</div>
        <div style="margin-top: 10px; font-size: 13px; color: #888;">
            {{ $menuDiscount->value }}% off your order total today
        </div>
    </div>
    <div class="notice">
        🚚 <strong>Free delivery</strong> on all your orders today — automatically applied at checkout!
    </div>
    <div style="margin-bottom: 20px;">
        <div class="section-title">How to use your gifts</div>
        <div class="row">
            <span class="label">1. Place your order</span>
            <span class="value">Visit EatWella and add items to your cart</span>
        </div>
        <div class="row">
            <span class="label">2. Apply discount code</span>
            <span class="value">Enter <strong style="color:#ff6b00;">{{ $menuDiscount->code }}</strong> at checkout</span>
        </div>
        <div class="row">
            <span class="label">3. Free delivery</span>
            <span class="value">Applied automatically — no code needed</span>
        </div>
        <div class="row">
            <span class="label">4. Valid until</span>
            <span class="value">End of today only</span>
        </div>
    </div>

@elseif($menuDiscount)
    {{-- Menu discount only --}}
    <div class="card" style="text-align: center;">
        <div class="card-label">Your Birthday Discount Code</div>
        <div class="card-value" style="font-size: 28px; letter-spacing: 4px; color: #ff6b00;">{{ $menuDiscount->code }}</div>
        <div style="margin-top: 10px; font-size: 13px; color: #888;">
            {{ $menuDiscount->value }}% off your order today
        </div>
    </div>
    <div style="margin-bottom: 20px;">
        <div class="section-title">How to use your gift</div>
        <div class="row">
            <span class="label">1. Place your order</span>
            <span class="value">Visit EatWella and add items to your cart</span>
        </div>
        <div class="row">
            <span class="label">2. Apply code</span>
            <span class="value">Enter <strong style="color:#ff6b00;">{{ $menuDiscount->code }}</strong> at checkout</span>
        </div>
        <div class="row">
            <span class="label">3. Valid until</span>
            <span class="value">End of today only</span>
        </div>
    </div>

@elseif($freeDeliveryDiscount)
    {{-- Free delivery only --}}
    <div class="notice" style="font-size: 15px; padding: 20px;">
        🚚 <strong>Free delivery</strong> on all your orders today — automatically applied at checkout!
    </div>
    <div style="margin-bottom: 20px;">
        <div class="section-title">How to use your gift</div>
        <div class="row">
            <span class="label">1. Place your order</span>
            <span class="value">Visit EatWella and add items to your cart</span>
        </div>
        <div class="row">
            <span class="label">2. Free delivery</span>
            <span class="value">Applied automatically at checkout — no code needed</span>
        </div>
        <div class="row">
            <span class="label">3. Valid until</span>
            <span class="value">End of today only</span>
        </div>
    </div>
@endif

<a href="{{ env('FRONTEND_URL') }}" class="btn">Order Now 🍽️</a>
@endsection
