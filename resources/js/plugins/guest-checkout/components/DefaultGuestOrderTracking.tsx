import React, { useState } from 'react';
import { useTrackGuestOrder } from '../api/useGuestOrderTracking';
import type { Order } from '../types';

export interface DefaultGuestOrderTrackingProps {
  initialOrderNumber?: string;
  initialPhone?: string;
  initialCountryCode?: string;
  onOrderFound?: (order: Order) => void;
  className?: string;
}

export default function DefaultGuestOrderTracking({
  initialOrderNumber = '',
  initialPhone = '',
  initialCountryCode = '+20',
  onOrderFound,
  className = '',
}: DefaultGuestOrderTrackingProps) {
  const [orderNumber, setOrderNumber] = useState(initialOrderNumber);
  const [phone, setPhone] = useState(initialPhone);
  const [countryCode, setCountryCode] = useState(initialCountryCode);
  const [order, setOrder] = useState<Order | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const trackMutation = useTrackGuestOrder();

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    if (!orderNumber.trim() || !phone.trim()) {
      setErrorMessage('Please provide both Order Number and Phone Number.');
      return;
    }

    trackMutation.mutate(
      {
        order_number: orderNumber.trim(),
        phone: phone.trim(),
        phone_country_code: countryCode.trim(),
      },
      {
        onSuccess: (data) => {
          setOrder(data);
          onOrderFound?.(data);
        },
        onError: (err: any) => {
          const apiError =
            err.response?.data?.message ||
            (err.response?.data?.errors
              ? Object.values(err.response.data.errors).flat().join(', ')
              : 'Failed to track order.');
          setErrorMessage(apiError);
        },
      }
    );
  };

  return (
    <div className={`default-guest-tracking border rounded-lg p-4 bg-card text-card-foreground shadow-sm ${className}`}>
      <h3 className="text-lg font-semibold mb-2">Track Guest Order</h3>
      <p className="text-sm text-muted-foreground mb-4">
        Enter your order number and phone number to inspect delivery progress.
      </p>

      <form onSubmit={handleSubmit} className="space-y-3">
        <div>
          <label className="block text-xs font-medium mb-1">Order Number</label>
          <input
            type="text"
            value={orderNumber}
            onChange={(e) => setOrderNumber(e.target.value)}
            placeholder="e.g. ORD-12345"
            disabled={trackMutation.isPending}
            className="w-full px-3 py-2 border rounded-md text-sm bg-background text-foreground"
          />
        </div>

        <div className="grid grid-cols-3 gap-2">
          <div>
            <label className="block text-xs font-medium mb-1">Code</label>
            <input
              type="text"
              value={countryCode}
              onChange={(e) => setCountryCode(e.target.value)}
              placeholder="+20"
              disabled={trackMutation.isPending}
              className="w-full px-3 py-2 border rounded-md text-sm bg-background text-foreground"
            />
          </div>
          <div className="col-span-2">
            <label className="block text-xs font-medium mb-1">Phone Number</label>
            <input
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="e.g. 01012345678"
              disabled={trackMutation.isPending}
              className="w-full px-3 py-2 border rounded-md text-sm bg-background text-foreground"
            />
          </div>
        </div>

        {errorMessage && (
          <div className="p-2 text-xs text-destructive bg-destructive/10 rounded-md">
            {errorMessage}
          </div>
        )}

        <button
          type="submit"
          disabled={trackMutation.isPending}
          className="w-full py-2 px-4 rounded-md bg-primary text-primary-foreground text-sm font-medium hover:opacity-90 transition disabled:opacity-50"
        >
          {trackMutation.isPending ? 'Tracking...' : 'Track Order'}
        </button>
      </form>

      {order && (
        <div className="mt-4 pt-4 border-t space-y-2 text-sm">
          <div className="flex justify-between font-medium">
            <span>Order #{order.order_number}</span>
            <span className="capitalize text-primary">{order.status}</span>
          </div>
          <div className="flex justify-between text-muted-foreground text-xs">
            <span>Total:</span>
            <span className="font-semibold text-foreground">{order.total}</span>
          </div>
          {order.items && (
            <div className="mt-2 text-xs space-y-1">
              <span className="font-semibold">Items ({order.items.length}):</span>
              {order.items.map((item) => (
                <div key={item.id} className="flex justify-between text-muted-foreground">
                  <span>{item.product_name} × {parseFloat(item.quantity).toFixed(0)}</span>
                  <span>{item.total}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
