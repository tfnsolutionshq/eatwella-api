<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1a1a1a;
            background-color: #f0f4f3;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(34, 139, 34, 0.08);
        }

        .header {
            background: linear-gradient(135deg, #2d7a3e 0%, #1e5a2e 100%);
            color: white;
            padding: 50px 30px;
            text-align: center;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4ade80, #22c55e, #16a34a);
        }

        .logo {
            max-width: 220px;
            height: auto;
            margin-bottom: 25px;
            filter: brightness(1.1);
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 12px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: 17px;
            opacity: 0.95;
            font-weight: 300;
        }

        .content {
            padding: 45px 35px;
        }

        .order-info {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            padding: 28px;
            border-radius: 10px;
            margin-bottom: 35px;
            border-left: 5px solid #22c55e;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.1);
        }

        .order-info h2 {
            color: #166534;
            margin-bottom: 20px;
            font-size: 22px;
            font-weight: 600;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(34, 197, 94, 0.15);
            font-size: 15px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row span:first-child {
            color: #166534;
            font-weight: 500;
        }

        .items-section {
            margin-bottom: 35px;
        }

        .items-section h3 {
            color: #166534;
            margin-bottom: 22px;
            font-size: 20px;
            font-weight: 600;
            border-bottom: 3px solid #22c55e;
            padding-bottom: 12px;
        }

        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px;
            margin-bottom: 12px;
            background-color: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }

        .item:hover {
            background-color: #f0fdf4;
            border-color: #86efac;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 6px;
            font-size: 16px;
        }

        .item-qty {
            color: #6b7280;
            font-size: 14px;
        }

        .item-price {
            font-weight: 700;
            color: #16a34a;
            font-size: 17px;
        }

        .total-section {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            padding: 28px;
            border-radius: 10px;
            margin-bottom: 35px;
            border: 2px solid #86efac;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 8px 0;
            font-size: 15px;
        }

        .total-row.final {
            border-top: 3px solid #22c55e;
            padding-top: 18px;
            margin-top: 18px;
            font-size: 24px;
            font-weight: 700;
            color: #166534;
        }

        .status-badge {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .status-confirmed {
            background-color: #22c55e;
            color: white;
        }

        .status-pending {
            background-color: #fbbf24;
            color: #78350f;
        }

        .footer {
            background: linear-gradient(135deg, #1e5a2e 0%, #14532d 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .footer .logo {
            max-width: 180px;
            margin-bottom: 20px;
            opacity: 0.95;
        }

        .footer p {
            margin-bottom: 12px;
            opacity: 0.9;
            font-size: 15px;
        }

        .track-button {
            display: inline-block;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 16px 40px;
            text-decoration: none;
            border-radius: 30px;
            margin: 25px 0;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
            transition: all 0.3s;
            letter-spacing: 0.3px;
        }

        .track-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
        }

        .divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, #22c55e, transparent);
            margin: 30px 0;
        }

        @media (max-width: 600px) {
            .container {
                margin: 0;
                border-radius: 0;
            }

            .header, .content, .footer {
                padding: 30px 20px;
            }

            .logo {
                max-width: 180px;
            }

            .header h1 {
                font-size: 26px;
            }

            .info-row, .total-row {
                flex-direction: column;
                gap: 5px;
            }

            .item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="{{ asset('logo.jpg') }}" alt="EatWella" class="logo">
            <h1>✓ Order Confirmed!</h1>
            <p>Thank you for choosing EatWella</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="order-info">
                <h2>Order Confirmation</h2>
                <div class="info-row">
                    <span>Order Number:</span>
                    <span><strong>{{ $order->order_number }}</strong></span>
                </div>
                <div class="info-row">
                    <span>Order Type:</span>
                    <span><strong>{{ ucfirst($order->order_type) }}</strong></span>
                </div>
                <div class="info-row">
                    <span>Payment Type:</span>
                    <span><strong>{{ ucfirst($order->payment_type) }}</strong></span>
                </div>
                <div class="info-row">
                    <span>Customer Name:</span>
                    <span>{{ $order->customer_name }}</span>
                </div>
                <div class="info-row">
                    <span>Order Date:</span>
                    <span>{{ $order->created_at->format('M d, Y - h:i A') }}</span>
                </div>
                <div class="info-row">
                    <span>Customer Email:</span>
                    <span>{{ $order->customer_email }}</span>
                </div>
                @if($order->order_type === 'dine' && $order->table_number)
                <div class="info-row">
                    <span>Table Number:</span>
                    <span><strong>{{ $order->table_number }}</strong></span>
                </div>
                @endif
                @if($order->order_type === 'delivery')
                <div class="info-row">
                    <span>Phone:</span>
                    <span>{{ $order->customer_phone }}</span>
                </div>
                <div class="info-row">
                    <span>Delivery Address:</span>
                    <span>{{ $order->delivery_address }}, {{ $order->delivery_city }}, {{ $order->delivery_zip }}</span>
                </div>
                <div class="info-row">
                    <span><strong>Delivery PIN:</strong></span>
                    <span style="font-size: 1.2em; font-weight: bold; color: #166534;">{{ $order->delivery_pin }}</span>
                </div>
                <div class="info-row" style="font-size: 0.9em; color: #666; border-top: none; padding-top: 0;">
                    <span colspan="2"><em>Please provide this PIN to your delivery agent to complete the delivery.</em></span>
                </div>
                @endif
                <div class="info-row">
                    <span>Status:</span>
                    <span class="status-badge status-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                </div>
            </div>

            <!-- Order Items -->
            <div class="items-section">
                <h3>📋 Order Items</h3>
                @foreach($order->orderItems as $item)
                <div class="item">
                    <div class="item-details">
                        <div class="item-name">{{ $item->menu->name }}</div>
                        <div class="item-qty">Quantity: {{ $item->quantity }}</div>
                    </div>
                    <div class="item-price">₦{{ number_format($item->subtotal, 2) }}</div>
                </div>
                @endforeach
            </div>

            <!-- Total Section -->
            <div class="total-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>₦{{ number_format($order->total_amount, 2) }}</span>
                </div>
                @if($order->discount_amount > 0)
                <div class="total-row">
                    <span>Discount ({{ $order->discount_code }}):</span>
                    <span style="color: #28a745;">-₦{{ number_format($order->discount_amount, 2) }}</span>
                </div>
                @endif
                <div class="total-row final">
                    <span>Total Paid:</span>
                    <span>₦{{ number_format($order->final_amount, 2) }}</span>
                </div>
            </div>

            <!-- Track Order Button -->
            <div style="text-align: center;">
                <a href="https://eatwella.tfnsolutions.us/orders/track/{{ $order->order_number }}" class="track-button">
                    📱 Track Your Order
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <img src="{{ asset('logo.jpg') }}" alt="EatWella" class="logo">
            <div class="divider" style="background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);"></div>
            <p style="font-size: 17px; font-weight: 500;">🍽️ Delicious meals, delivered fresh</p>
            <p style="font-size: 14px; margin-top: 25px; opacity: 0.7;">
                Questions? Contact us at <a href="mailto:support@eatwella.ng" style="color: #86efac; text-decoration: none;">support@eatwella.ng</a>
            </p>
            <p style="font-size: 12px; margin-top: 20px; opacity: 0.6;">
                © 2024 EatWella. All rights reserved.<br>
                This is an automated message, please do not reply.
            </p>
        </div>
    </div>
</body>
</html>
