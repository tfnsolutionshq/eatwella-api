@extends('emails.layout')

@section('title', 'Order Completed')

@section('heading', 'Order Completed!')
@section('subheading', 'Your order has been delivered successfully')

@section('content')
<div class="card">
    <div class="card-label">Order ID</div>
    <div class="card-value">{{ $order->order_number }}</div>
    <div style="margin-top: 10px;">
        <span class="badge badge-success">COMPLETED</span>
    </div>
</div>

<div class="section-title">Order Summary</div>
<div style="margin-bottom: 20px;">
    <div class="row">
        <span class="label">Order Type</span>
        <span class="value">{{ ucfirst($order->order_type) }}</span>
    </div>
    <div class="row">
        <span class="label">Customer</span>
        <span class="value">{{ $order->customer_name }}</span>
    </div>
    <div class="row">
        <span class="label">Email</span>
        <span class="value">{{ $order->customer_email }}</span>
    </div>
    <div class="row">
        <span class="label">Completed At</span>
        <span class="value">{{ $order->completed_at?->format('M d, Y h:i A') ?? $order->updated_at->format('M d, Y h:i A') }}</span>
    </div>
</div>

<div class="divider"></div>
@foreach($order->orderItems as $item)
<div class="item-row">
    <div class="item-price">₦{{ number_format($item->subtotal, 2) }}</div>
    <div class="item-name">{{ $item->quantity }}x {{ $item->menu->name }}</div>
    @if($item->packaging_price > 0)
    <div class="item-meta">Packaging: ₦{{ number_format($item->packaging_price, 2) }}</div>
    @endif
</div>
@endforeach
<div class="divider"></div>

@if($order->tax_amount > 0)
<div class="total-row">
    <span>Tax Charges:</span>
    <span><strong>₦{{ number_format($order->tax_amount, 2) }} (VAT)</strong></span>
</div>
@endif
@if($order->discount_amount > 0)
<div class="total-row">
    <span>Discount ({{ $order->discount_code }}):</span>
    <span>-₦{{ number_format($order->discount_amount, 2) }}</span>
</div>
@endif
<div class="total-row final">
    <span>Total Payment:</span>
    <span class="amount">₦{{ number_format($order->final_amount, 2) }}</span>
</div>
<div class="total-row" style="margin-top: 6px;">
    <span>Mode of Payment:</span>
    <span style="color: #ff6b00; font-weight: 600;">{{ ucfirst($order->payment_type) }}</span>
</div>

<div class="divider"></div>

<div class="notice">⭐ Enjoyed your meal? We'd love your feedback! Reply to this email with your rating.</div>

<a href="{{ env('FRONTEND_URL') }}" class="btn">Order Again</a>
@endsection
