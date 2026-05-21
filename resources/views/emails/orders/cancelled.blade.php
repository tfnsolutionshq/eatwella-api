@extends('emails.layout')

@section('title', 'Order Cancelled')

@section('heading', 'Order Cancelled')
@section('subheading', 'Your order has been cancelled')

@section('content')
<div class="card">
    <div class="card-label">Order ID</div>
    <div class="card-value">{{ $order->order_number }}</div>
    <div style="margin-top: 10px;">
        <span class="badge badge-danger">CANCELLED</span>
    </div>
</div>

<div class="section-title">Order Details</div>
<div style="margin-bottom: 20px;">
    <div class="row">
        <span class="label">Order Type:&nbsp;</span>
        <span class="value">{{ ucfirst($order->order_type) }}</span>
    </div>
    <div class="row">
        <span class="label">Customer:&nbsp;</span>
        <span class="value">{{ $order->customer_name }}</span>
    </div>
    <div class="row">
        <span class="label">Date:&nbsp;</span>
        <span class="value">{{ $order->created_at->format('M d, Y h:i A') }}</span>
    </div>
    <div class="row">
        <span class="label">Total Amount:&nbsp;</span>
        <span class="value">₦{{ number_format($order->final_amount, 2) }}</span>
    </div>
    <div class="row">
        <span class="label">Mode of Payment:&nbsp;</span>
        <span class="value">{{ ucfirst($order->payment_type) }}</span>
    </div>
</div>

<div class="divider"></div>
@foreach($order->orderItems as $item)
<div class="item-row">
    <div class="item-price">₦{{ number_format($item->subtotal, 2) }}</div>
    <div class="item-name">{{ $item->quantity }}x {{ $item->menu->name }}</div>
</div>
@endforeach
<div class="divider"></div>

@if($order->payment_type === 'loyalty_points')
<div class="notice">🎁 Your loyalty points have been restored to your account.</div>
@endif

<div class="notice">If you did not request this cancellation or have questions, please contact us at <a href="mailto:support@eatwella.ng" style="color: #ff6b00;">support@eatwella.ng</a></div>

<a href="{{ env('FRONTEND_URL') }}" class="btn">Place a New Order</a>
@endsection
