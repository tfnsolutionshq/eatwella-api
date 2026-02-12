<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation</title>
</head>
<body>
    <h1>Thank you for your order!</h1>
    <p>Order Number: {{ $order->order_number }}</p>
    <p>Total Amount: ${{ number_format($order->final_amount, 2) }}</p>

    <h2>Items:</h2>
    <ul>
        @foreach($order->orderItems as $item)
            <li>{{ $item->menu->name }} x {{ $item->quantity }} - ${{ number_format($item->subtotal, 2) }}</li>
        @endforeach
    </ul>

    <p>You can track your order using your order number.</p>
</body>
</html>
