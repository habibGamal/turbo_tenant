import React, { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useGuestOrderDetails, useTrackGuestOrder } from '../api/useGuestOrderTracking';
import type { Order, TrackOrderPayload } from '../types';

export interface DefaultGuestOrderTrackingProps {
  initialOrderNumber?: string;
  initialPhone?: string;
  initialCountryCode?: string;
  onOrderFound?: (order: Order) => void;
  className?: string;
}

export default function DefaultGuestOrderTracking({
  initialOrderNumber,
  initialPhone,
  initialCountryCode = '+20',
  onOrderFound,
  className = '',
}: DefaultGuestOrderTrackingProps) {
  const [searchParams] = useSearchParams();
  const paramOrderNumber = searchParams.get('order_number') ?? '';
  const paramToken = searchParams.get('token') ?? '';
  const paramPhone = searchParams.get('phone') ?? '';

  const defaultOrderNumber = initialOrderNumber ?? paramOrderNumber;
  const defaultPhone = initialPhone ?? paramPhone;

  const [orderNumber, setOrderNumber] = useState(defaultOrderNumber);
  const [phone, setPhone] = useState(defaultPhone);
  const [countryCode, setCountryCode] = useState(initialCountryCode);
  const [order, setOrder] = useState<Order | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [searchPayload, setSearchPayload] = useState<TrackOrderPayload | null>(() => {
    if (defaultOrderNumber.trim() && paramToken.trim()) {
      return { order_number: defaultOrderNumber.trim(), token: paramToken.trim() };
    }
    if (defaultOrderNumber.trim() && defaultPhone.trim()) {
      return { order_number: defaultOrderNumber.trim(), phone: defaultPhone.trim() };
    }
    return null;
  });

  useEffect(() => {
    const nextOrderNumber = initialOrderNumber ?? paramOrderNumber;
    const nextPhone = initialPhone ?? paramPhone;

    if (nextOrderNumber) {
      setOrderNumber(nextOrderNumber);
    }
    if (nextPhone) {
      setPhone(nextPhone);
    }

    const trimmedOrder = nextOrderNumber.trim();
    const trimmedToken = paramToken.trim();
    const trimmedPhone = nextPhone.trim();

    if (trimmedOrder && trimmedToken) {
      setOrder(null);
      setErrorMessage(null);
      setSearchPayload({ order_number: trimmedOrder, token: trimmedToken });
    } else if (trimmedOrder && trimmedPhone) {
      setOrder(null);
      setErrorMessage(null);
      setSearchPayload({ order_number: trimmedOrder, phone: trimmedPhone });
    }
  }, [initialOrderNumber, initialPhone, paramOrderNumber, paramToken, paramPhone]);

  const { data: queryOrder, isLoading: isQueryLoading, error: queryError } = useGuestOrderDetails(searchPayload);
  const trackMutation = useTrackGuestOrder();

  useEffect(() => {
    if (queryOrder) {
      onOrderFound?.(queryOrder);
    }
  }, [queryOrder, onOrderFound]);

  const displayedOrder = order ?? queryOrder ?? null;
  const displayedError =
    errorMessage ||
    (queryError
      ? (queryError as any)?.response?.data?.message || 'Failed to track order.'
      : null);
  const isSubmitting = trackMutation.isPending || (isQueryLoading && !displayedOrder);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    if (!orderNumber.trim() || !phone.trim()) {
      setErrorMessage('Please provide both Order Number and Phone Number.');
      return;
    }

    setOrder(null);
    setSearchPayload(null);

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
            disabled={isSubmitting}
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
              disabled={isSubmitting}
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
              disabled={isSubmitting}
              className="w-full px-3 py-2 border rounded-md text-sm bg-background text-foreground"
            />
          </div>
        </div>

        {displayedError && (
          <div className="p-2 text-xs text-destructive bg-destructive/10 rounded-md">
            {displayedError}
          </div>
        )}

        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full py-2 px-4 rounded-md bg-primary text-primary-foreground text-sm font-medium hover:opacity-90 transition disabled:opacity-50"
        >
          {isSubmitting ? 'Tracking...' : 'Track Order'}
        </button>
      </form>

      {displayedOrder && (
        <div className="mt-4 pt-4 border-t space-y-2 text-sm">
          <div className="flex justify-between font-medium">
            <span>Order #{displayedOrder.order_number}</span>
            <span className="capitalize text-primary">{displayedOrder.status}</span>
          </div>
          <div className="flex justify-between text-muted-foreground text-xs">
            <span>Total:</span>
            <span className="font-semibold text-foreground">{displayedOrder.total}</span>
          </div>
          {displayedOrder.items && (
            <div className="mt-2 text-xs space-y-1">
              <span className="font-semibold">Items ({displayedOrder.items.length}):</span>
              {displayedOrder.items.map((item) => (
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
