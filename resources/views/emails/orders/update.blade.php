<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; text-align: center; border-bottom: 2px solid #e9ecef; }
        .order-details { margin: 20px 0; }
        .order-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th, .table td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        .table th { background-color: #f8f9fa; }
        .total-row { font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6c757d; }
        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 4px; background-color: #f97316; color: white; font-size: 14px; }
        .text-muted { color: #6c757d; }
        .small { font-size: 0.875em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Update</h1>
            <p>Order #{{ $order->order_number }}</p>
        </div>

        <div class="order-details">
            <p>Hello {{ $order->getCustomerName() }},</p>
            <p>Your order status has been updated to: <span class="status-badge">{{ $order->status->label() ?? $order->status }}</span></p>

            <div class="order-info">
                <h3>Order Information</h3>
                <p><strong>Date:</strong> {{ $order->created_at->format('M d, Y H:i') }}</p>
                <p><strong>Payment Method:</strong> {{ $order->payment_method->label() ?? $order->payment_method }}</p>
                <p><strong>Payment Status:</strong> {{ $order->payment_status->label() ?? $order->payment_status }}</p>

                @if($order->address)
                <h3>Shipping Address</h3>
                <p>
                    {{ $order->address->street }}<br>
                    {{ $order->address->building }}, {{ $order->address->floor }}, {{ $order->address->apartment }}<br>
                    {{ $order->address->area->name ?? '' }}<br>
                    {{ $order->address->phone_number }}
                </p>
                @endif
            </div>

            @if($order->note)
            <div class="order-info">
                <h3>Order Notes</h3>
                <p>{{ $order->note }}</p>
            </div>
            @endif

            <h3>Order Items</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->product_name }}
                            @if($item->variant_name)
                                <br><span class="text-muted small">{{ $item->variant_name }}</span>
                            @endif
                            @if($item->extras->isNotEmpty())
                                @foreach($item->extras as $extra)
                                    <br><span class="text-muted small">+ {{ $extra->extra_name }} ({{ number_format($extra->extra_price, 2) }})</span>
                                @endforeach
                            @endif
                        </td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" align="right">Subtotal:</td>
                        <td>{{ number_format($order->sub_total, 2) }}</td>
                    </tr>
                    @if($order->tax > 0)
                    <tr>
                        <td colspan="3" align="right">Tax:</td>
                        <td>{{ number_format($order->tax, 2) }}</td>
                    </tr>
                    @endif
                    @if($order->delivery_fee > 0)
                    <tr>
                        <td colspan="3" align="right">Delivery Fee:</td>
                        <td>{{ number_format($order->delivery_fee, 2) }}</td>
                    </tr>
                    @endif
                    @if($order->discount > 0)
                    <tr>
                        <td colspan="3" align="right">Discount:</td>
                        <td>-{{ number_format($order->discount, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td colspan="3" align="right">Total:</td>
                        <td>{{ number_format($order->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="footer">
            <p>Thank you for shopping with us!</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
