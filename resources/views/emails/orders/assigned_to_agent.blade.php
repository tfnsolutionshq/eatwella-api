@extends('emails.layout')

@section('title', 'New Delivery Assigned')

@section('heading', 'New Delivery Assigned!')
@section('subheading', 'You have a new order to deliver')

@section('content')
<div class="card">
    <div class="card-label">Order ID</div>
    <div class="card-value">{{ $order->order_number }}</div>
    <div style="margin-top: 10px;">
        <span class="badge badge-info">DISPATCHED</span>
    </div>
</div>

<div class="section-title">Delivery Details</div>
<div style="margin-bottom: 20px;">
    <div class="row">
        <span class="label">Agent</span>
        <span class="value">{{ $agent->name }}</span>
    </div>
    <div class="row">
        <span class="label">Customer</span>
        <span class="value">{{ $order->customer_name }}</span>
    </div>
    <div class="row">
        <span class="label">Phone</span>
        <span class="value">{{ $order->customer_phone }}</span>
    </div>
    <div class="row">
        <span class="label">Address</span>
        <span class="value">{{ $order->delivery_address ?? 'N/A' }}</span>
    </div>
    <div class="row">
        <span class="label">Mode of Payment</span>
        <span class="value">{{ ucfirst($order->payment_type) }}</span>
    </div>
    <div class="row">
        <span class="label">Total Amount</span>
        <span class="value orange">₦{{ number_format($order->final_amount, 2) }}</span>
    </div>
</div>

<div class="notice">Please log in to your dashboard to view full order details and proceed with the delivery.</div>

<a href="{{ config('app.url') }}/delivery-agent/orders" class="btn">View Delivery Dashboard</a>
@endsection
