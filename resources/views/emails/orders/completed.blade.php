<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Completed</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .success-icon {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .success-icon svg {
            width: 80px;
            height: 80px;
        }
        
        .order-info {
            background-color: #d4edda;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #28a745;
        }
        
        .order-info h2 {
            color: #28a745;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #c3e6cb;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #28a745;
            color: white;
        }
        
        .message-box {
            background-color: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .message-box h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .message-box p {
            color: #666;
            font-size: 16px;
            line-height: 1.8;
        }
        
        .items-section {
            margin-bottom: 30px;
        }
        
        .items-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
            border-bottom: 2px solid #28a745;
            padding-bottom: 10px;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .item:last-child {
            border-bottom: none;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .item-qty {
            color: #666;
            font-size: 14px;
        }
        
        .item-price {
            font-weight: bold;
            color: #28a745;
            font-size: 16px;
        }
        
        .total-section {
            background-color: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        
        .total-row.final {
            border-top: 2px solid #28a745;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 20px;
            font-weight: bold;
            color: #28a745;
        }
        
        .footer {
            background-color: #343a40;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .footer p {
            margin-bottom: 10px;
            opacity: 0.8;
        }
        
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            margin: 20px 0;
            font-weight: bold;
            transition: transform 0.2s;
        }
        
        .action-button:hover {
            transform: translateY(-2px);
        }
        
        @media (max-width: 600px) {
            .container {
                margin: 0;
                box-shadow: none;
            }
            
            .header, .content, .footer {
                padding: 20px;
            }
            
            .info-row, .total-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .item-price {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="{{ asset('logo.jpg') }}" alt="EatWella" style="max-width: 150px; margin-bottom: 20px;">
            <h1>Order Completed!</h1>
            <p>Your order is ready for pickup/delivery</p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Success Icon -->
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#28a745">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
            </div>
            
            <div class="order-info">
                <h2>Order Details</h2>
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
                    <span>Completed At:</span>
                    <span>{{ $order->updated_at->format('M d, Y - h:i A') }}</span>
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
                @endif
                <div class="info-row">
                    <span>Status:</span>
                    <span class="status-badge">{{ ucfirst($order->status) }}</span>
                </div>
            </div>
            
            <!-- Message Box -->
            <div class="message-box">
                <h3>🎉 Great News!</h3>
                <p>Your order has been completed and is ready. Thank you for choosing EatWella. We hope you enjoy your meal!</p>
            </div>
            
            <!-- Order Items -->
            <div class="items-section">
                <h3>📋 Order Summary</h3>
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
            
            <!-- Feedback Section -->
            <div class="message-box" style="margin-top: 30px; border-top: 4px solid #28a745; background-color: #f1f8f3;">
                <h3 style="color: #28a745; margin-bottom: 10px; font-size: 20px;">⭐ How was your meal?</h3>
                <p style="color: #555; font-size: 16px; margin-bottom: 15px;">
                    We'd love to hear from you! Simply <strong>reply to this email</strong> with your rating (1-5) and any comments.
                </p>
                <p style="font-size: 14px; color: #777;">
                    Your feedback helps us serve you better. We read every reply!
                </p>
            </div>
            
            <!-- Action Button -->
            <div style="text-align: center;">
                <a href="https://eatwella.tfnsolutions.us" class="action-button">
                    🍽️ Order Again
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <img src="{{ asset('logo.jpg') }}" alt="EatWella" style="max-width: 120px; margin-bottom: 15px; opacity: 0.9;">
            <p>Delicious meals delivered to your doorstep</p>
            <p>Thank you for choosing EatWella!</p>
            <p style="font-size: 12px; margin-top: 20px; opacity: 0.6;">
                This is an automated email. Please do not reply to this message.
            </p>
        </div>
    </div>
</body>
</html>
