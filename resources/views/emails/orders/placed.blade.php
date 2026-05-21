@extends('emails.layout')

@section('title', 'Order Confirmed')

@section('heading', 'Order Confirmed!')
@section('subheading', 'Thank you for your order')

@section('content')
{{-- Order ID Card --}}
<div class="card">
    <div class="card-label">Order ID</div>
    <div class="card-value">{{ $order->order_number }}</div>
    <div style="margin-top: 10px;">
        <span class="badge badge-success">{{ strtoupper($order->status) }}</span>
    </div>

</div>

{{-- Order Details --}}
<div class="section-title">Order Details</div>
<div style="margin-bottom: 20px;">
    @if($order->order_type === 'delivery' && $order->delivery_address)
    <div class="row">
        <span class="label">Delivery Axis:&nbsp;</span>
        <span class="value">
            {{ $order->delivery_address }}
            @if($order->deliveryZone)
                , {{ $order->deliveryZone->name }}
                @if($order->deliveryZone->city)
                    , {{ $order->deliveryZone->city->name }}
                    @if($order->deliveryZone->city->state)
                        , {{ $order->deliveryZone->city->state->name }}
                    @endif
                @endif
            @endif
        </span>
    </div>
    @endif
    <div class="row">
        <span class="label">Order Type:&nbsp;</span>
        <span class="value">{{ ucfirst($order->order_type) }}</span>
    </div>
    <div class="row">
        <span class="label">Customer:&nbsp;</span>
        <span class="value">{{ $order->customer_name }}</span>
    </div>
    <div class="row">
        <span class="label">Email:&nbsp;</span>
        <span class="value">{{ $order->customer_email }}</span>
    </div>
    @if($order->customer_phone)
    <div class="row">
        <span class="label">Phone:&nbsp;</span>
        <span class="value">{{ $order->customer_phone }}</span>
    </div>
    @endif
    @if($order->order_type === 'dine' && $order->table_number)
    <div class="row">
        <span class="label">Table:&nbsp;</span>
        <span class="value">{{ $order->table_number }}</span>
    </div>
    @endif
    <div class="row">
        <span class="label">Date:&nbsp;</span>
        <span class="value">{{ $order->created_at->format('M d, Y h:i A') }}</span>
    </div>
</div>

{{-- Delivery PIN --}}
@if($order->order_type === 'delivery' && $order->delivery_pin)
<div class="pin-box">
    <div style="font-size: 12px; color: #888; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Your Delivery PIN</div>
    <div class="pin">{{ $order->delivery_pin }}</div>
    <p>Share this PIN only with your delivery agent to confirm delivery.</p>
</div>
@endif

{{-- Items --}}
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

{{-- Totals --}}
@if($order->tax_amount > 0)
<div class="total-row">
    <span>Tax Charges:&nbsp;</span>
    <span><strong>₦{{ number_format($order->tax_amount, 2) }} (VAT)</strong></span>
</div>
@endif
@if($order->discount_amount > 0)
<div class="total-row">
    <span>Discount ({{ $order->discount_code }}):&nbsp;</span>
    <span>-₦{{ number_format($order->discount_amount, 2) }}</span>
</div>
@endif
@if($order->delivery_fee > 0)
<div class="total-row">
    <span>Delivery Fee:&nbsp;</span>
    <span>₦{{ number_format($order->delivery_fee, 2) }}</span>
</div>
@endif
<div class="total-row final">
    <span>Total Payment:&nbsp;</span>
    <span class="amount">₦{{ number_format($order->final_amount, 2) }}</span>
</div>
<div class="total-row" style="margin-top: 6px;">
    <span>Mode of Payment:&nbsp;</span>
    <span style="color: #ff6b00; font-weight: 600;">{{ ucfirst($order->payment_type) }}</span>
</div>

<div class="divider"></div>

{{-- CTAs --}}
<a href="{{ env('FRONTEND_URL') }}/track-order" class="btn">Track Your Order</a>
<a href="{{ env('FRONTEND_URL') }}" class="btn-link">Place Another Order</a>
@endsection
