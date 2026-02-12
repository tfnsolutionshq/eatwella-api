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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .order-info {
            background-color: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        
        .order-info h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            color: #28a745;
        }
        
        .items-section {
            margin-bottom: 30px;
        }
        
        .items-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
            border-bottom: 2px solid #667eea;
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
            border-top: 2px solid #667eea;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 20px;
            font-weight: bold;
            color: #28a745;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .footer {
            background-color: #343a40;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .footer h3 {
            margin-bottom: 15px;
            color: #667eea;
        }
        
        .footer p {
            margin-bottom: 10px;
            opacity: 0.8;
        }
        
        .track-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            margin: 20px 0;
            font-weight: bold;
            transition: transform 0.2s;
        }
        
        .track-button:hover {
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
            <h1>🍽️ EatWella</h1>
            <p>Thank you for your order!</p>
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
                    <span>Order Date:</span>
                    <span>{{ $order->created_at->format('M d, Y - h:i A') }}</span>
                </div>
                <div class="info-row">
                    <span>Customer Email:</span>
                    <span>{{ $order->customer_email }}</span>
                </div>
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
                    <span>Discount:</span>
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
                <a href="https://eatwella.tfnsolutions.us/api/orders/track/{{ $order->order_number }}" class="track-button">
                    📱 Track Your Order
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <h3>🍽️ EatWella</h3>
            <p>Delicious meals delivered to your doorstep</p>
            <p>Thank you for choosing EatWella!</p>
            <p style="font-size: 12px; margin-top: 20px;">
                This is an automated email. Please do not reply to this message.
            </p>
        </div>
    </div>
</body>
</html>