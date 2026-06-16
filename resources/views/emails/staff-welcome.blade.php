@extends('emails.layout')

@section('title', 'Welcome to the EatWella Team!')

@section('heading')
Welcome, {{ $user->name }}! 👋
@endsection

@section('subheading', 'Your staff account has been created')

@section('content')

@php
$roles = [
    'attendant' => [
        'label' => 'Attendant',
        'icon'  => '🛎️',
        'responsibilities' => [
            'Take and manage dine-in, takeaway, and POS orders',
            'Assist customers at the counter',
            'Process payments and issue receipts',
            'Coordinate with kitchen staff on order status',
        ],
    ],
    'supervisor' => [
        'label' => 'Supervisor',
        'icon'  => '📋',
        'responsibilities' => [
            'Oversee daily outlet operations',
            'Manage and assign staff tasks',
            'Monitor order flow and resolve escalations',
            'Review inventory and stock levels',
            'Manage outlet availability hours',
        ],
    ],
    'kitchen' => [
        'label' => 'Kitchen Staff',
        'icon'  => '👨‍🍳',
        'responsibilities' => [
            'Receive and prepare incoming orders',
            'Mark orders as preparing and ready',
            'Maintain food quality and kitchen hygiene',
            'Communicate delays or issues to the supervisor',
        ],
    ],
    'store_keeper' => [
        'label' => 'Store Keeper',
        'icon'  => '📦',
        'responsibilities' => [
            'Manage and update menu stock quantities',
            'Track inventory and flag low-stock items',
            'Receive and log incoming stock deliveries',
            'Ensure accurate stock records per outlet',
        ],
    ],
    'delivery_agent' => [
        'label' => 'Delivery Agent',
        'icon'  => '🛵',
        'responsibilities' => [
            'Accept and fulfil delivery order assignments',
            'Pick up orders from the outlet promptly',
            'Deliver orders to customers within your assigned zones',
            'Update delivery status in real time',
            'Maintain professionalism with customers',
        ],
    ],
];

$role = $roles[$user->role] ?? null;
@endphp

<p style="font-size: 15px; color: #555; margin-bottom: 24px; text-align: center;">
    You have been added to the EatWella team. Below are your login credentials and responsibilities.
</p>

{{-- Login Credentials --}}
<div class="section-title">🔐 Your Login Details</div>
<div class="card">
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px;">
        <tr>
            <td style="color: #888; padding: 10px 0; border-bottom: 1px solid #f0f0f0; width: 40%;">Email</td>
            <td style="color: #1a1a1a; font-weight: 500; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">{{ $user->email }}</td>
        </tr>
        <tr>
            <td style="color: #888; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">Password</td>
            <td style="color: #ff6b00; font-weight: 700; letter-spacing: 1px; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">{{ $plainPassword }}</td>
        </tr>
        @if($user->outlet)
        <tr>
            <td style="color: #888; padding: 10px 0;">Assigned Outlet</td>
            <td style="color: #1a1a1a; font-weight: 500; padding: 10px 0;">{{ $user->outlet->name }}</td>
        </tr>
        @endif
    </table>
</div>
<p style="font-size: 12px; color: #aaa; text-align: center; margin-top: -12px; margin-bottom: 24px;">
    Please change your password after your first login.
</p>

{{-- Role & Responsibilities --}}
@if($role)
<div class="section-title">{{ $role['icon'] }} Your Role: {{ $role['label'] }}</div>
<div class="card">
    @foreach($role['responsibilities'] as $item)
    <div class="row" style="border-bottom: {{ !$loop->last ? '1px solid #f0f0f0' : 'none' }}; padding: 10px 0;">
        <span style="font-size: 14px; color: #555;">✓ &nbsp;{{ $item }}</span>
    </div>
    @endforeach
</div>
@endif

<div class="notice">
    Keep your credentials safe and do not share your password with anyone.
</div>

<a href="{{ env('FRONTEND_URL') }}/staff/login" class="btn">Go to Staff Portal 🚀</a>

@endsection
