import React from 'react';
import { useSearchParams, useParams, Link } from 'react-router-dom';
import { usePaymentStatus } from '../api/usePaymentStatus';

export default function DefaultPaymentCallback() {
  const { id } = useParams<{ id: string }>();
  const [searchParams] = useSearchParams();
  const statusParam = searchParams.get('status') || 'unknown';
  const messageParam = searchParams.get('message') || '';

  const { data, isLoading } = usePaymentStatus(id);

  const isSuccess = statusParam === 'success' || data?.is_paid;

  return (
    <div className="p-8 max-w-lg mx-auto text-center border rounded-xl shadow-sm bg-card text-card-foreground">
      <h2 className={`text-2xl font-bold mb-3 ${isSuccess ? 'text-green-600' : 'text-red-600'}`}>
        {isSuccess ? 'Payment Successful' : 'Payment Failed'}
      </h2>
      <p className="text-muted-foreground mb-4">
        {messageParam ? decodeURIComponent(messageParam) : (isSuccess ? 'Thank you! Your payment has been received.' : 'Unable to complete your transaction.')}
      </p>

      {isLoading ? (
        <p className="text-sm text-muted-foreground">Verifying order status...</p>
      ) : data?.order ? (
        <div className="bg-muted p-4 rounded-lg mb-6 text-sm">
          <p>Order: #{data.order.order_number}</p>
          <p>Status: {data.order.status} ({data.order.payment_status})</p>
          <p>Total: {data.order.total} {data.order.currency}</p>
        </div>
      ) : null}

      <div className="flex justify-center gap-4">
        {id && (
          <Link to={`/orders/${id}`} className="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm font-medium">
            View Order
          </Link>
        )}
        <Link to="/" className="px-4 py-2 border rounded-md text-sm font-medium">
          Home
        </Link>
      </div>
    </div>
  );
}
