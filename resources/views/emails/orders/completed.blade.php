<!DOCTYPE html>
<html>
<head>
    <title>Order Completed</title>
</head>
<body>
    <h1>Your order is ready!</h1>
    <p>Order Number: {{ $order->order_number }}</p>
    <p>Status: {{ ucfirst($order->status) }}</p>
    <p>Thank you for dining with us.</p>
</body>
</html>
