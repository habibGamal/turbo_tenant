import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '@/core/context/AuthContext';
import { resolvePaymentCallbackOrderLink, usePaymentStatus } from '../api/usePaymentStatus';

export default function DefaultPaymentCallback() {
  const { id } = useParams<{ id: string }>();
  const { isAuthenticated, isLoading: isAuthLoading } = useAuth();

  const { data, isLoading } = usePaymentStatus(id);
  const { orderHref } = resolvePaymentCallbackOrderLink({
    orderId: id,
    statusData: data,
    isAuthenticated,
    isAuthLoading,
  });
  const [isTimedOut, setIsTimedOut] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      setIsTimedOut(true);
    }, 30000);
    return () => clearTimeout(timer);
  }, []);

  const isSuccess = data?.is_paid || data?.order?.payment_status === 'completed';
  const isFailed = data?.order?.payment_status === 'failed';
  const isConfirming = !isSuccess && !isFailed && isTimedOut;
  const isProcessing = !isSuccess && !isFailed && !isTimedOut;


  const title = isSuccess
    ? 'Payment Successful'
    : isConfirming
    ? 'Payment Confirmation in Progress'
    : isProcessing
    ? 'Payment Processing...'
    : 'Payment Failed';

  const titleColor = isSuccess
    ? 'text-green-600'
    : isConfirming
    ? 'text-amber-600 dark:text-amber-400'
    : isProcessing
    ? 'text-blue-600'
    : 'text-red-600';

  const description = isSuccess
    ? 'Thank you! Your payment has been received.'
    : isConfirming
    ? "Your payment is still being confirmed. We'll update your order shortly."
    : isProcessing
    ? 'We are verifying your payment status. Please wait...'
    : 'Unable to complete your transaction.';

  return (
    <div className="p-8 max-w-lg mx-auto text-center border rounded-xl shadow-sm bg-card text-card-foreground">
      <h2 className={`text-2xl font-bold mb-3 ${titleColor}`}>
        {title}
      </h2>
      <p className="text-muted-foreground mb-4">
        {description}
      </p>

      {isLoading || isProcessing ? (
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
          <Link to={orderHref} className="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm font-medium">
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
