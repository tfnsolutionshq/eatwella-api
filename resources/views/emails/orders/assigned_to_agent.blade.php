<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Delivery Assigned</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a1a1a; background-color: #f0f4f3; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(34, 139, 34, 0.08); }
        .header { background: linear-gradient(135deg, #2d7a3e 0%, #1e5a2e 100%); color: white; padding: 40px 30px; text-align: center; position: relative; }
        .header::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #4ade80, #22c55e, #16a34a); }
        .header h1 { font-size: 28px; margin-bottom: 12px; font-weight: 600; }
        .header p { font-size: 16px; opacity: 0.95; font-weight: 300; }
        .content { padding: 45px 35px; }
        .greeting { font-size: 18px; margin-bottom: 20px; font-weight: 500; color: #166534; }
        .order-info { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); padding: 28px; border-radius: 10px; margin-bottom: 35px; border-left: 5px solid #22c55e; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 12px; padding: 10px 0; border-bottom: 1px solid rgba(34, 197, 94, 0.15); font-size: 15px; }
        .info-row:last-child { border-bottom: none; }
        .info-row span:first-child { color: #166534; font-weight: 500; }
        .action-button { display: inline-block; background-color: #22c55e; color: white; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 16px; margin-top: 20px; text-align: center; transition: background-color 0.2s; }
        .action-button:hover { background-color: #16a34a; }
        .footer { background-color: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Delivery Assigned!</h1>
            <p>Order #{{ $order->order_number }}</p>
        </div>

        <div class="content">
            <div class="greeting">
                Hello {{ $agent->name }},
            </div>

            <p style="margin-bottom: 25px; color: #4b5563;">
                You have been assigned a new delivery. Please log in to your delivery agent dashboard to view the full details and proceed with the delivery.
            </p>

            <div class="order-info">
                <div class="info-row">
                    <span>Order Number:</span>
                    <span>#{{ $order->order_number }}</span>
                </div>
                <div class="info-row">
                    <span>Customer Name:</span>
                    <span>{{ $order->customer_name }}</span>
                </div>
                <div class="info-row">
                    <span>Customer Phone:</span>
                    <span>{{ $order->customer_phone }}</span>
                </div>
                <div class="info-row">
                    <span>Delivery Address:</span>
                    <span style="text-align: right; max-width: 60%;">{{ $order->delivery_address ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span>Payment Method:</span>
                    <span style="text-transform: uppercase;">{{ $order->payment_type }}</span>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="{{ config('app.url') }}/delivery-agent/orders" class="action-button">View Delivery Dashboard</a>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} EatWella. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
