import React, { useState } from 'react';
import { useValidateCoupon } from '../api/useValidateCoupon';
import { AppliedCoupon } from '../types';

interface DefaultCouponFormProps {
  cartItems: any[];
  cartTotal: number;
  addressId?: number | null;
  guestPhone?: string | null;
  appliedCoupon?: AppliedCoupon | null;
  onCouponApplied?: (coupon: AppliedCoupon) => void;
  onCouponRemoved?: () => void;
}

export default function DefaultCouponForm({
  cartItems,
  cartTotal,
  addressId,
  guestPhone,
  appliedCoupon,
  onCouponApplied,
  onCouponRemoved,
}: DefaultCouponFormProps) {
  const [code, setCode] = useState('');
  const [message, setMessage] = useState<{ text: string; isError: boolean } | null>(null);

  const { mutate, isPending } = useValidateCoupon();

  const handleApply = (e: React.FormEvent) => {
    e.preventDefault();
    if (!code.trim()) return;

    setMessage(null);

    mutate(
      {
        code: code.trim(),
        cart_items: cartItems,
        sub_total: cartTotal,
        address_id: addressId,
        guest_phone: guestPhone,
      },
      {
        onSuccess: (res) => {
          if (res.valid && res.coupon) {
            setMessage({ text: res.message, isError: false });
            if (onCouponApplied) onCouponApplied(res.coupon);
          } else {
            setMessage({ text: res.message || 'Invalid coupon', isError: true });
          }
        },
        onError: (err: any) => {
          setMessage({
            text: err?.response?.data?.message || 'Error validating coupon',
            isError: true,
          });
        },
      }
    );
  };

  const handleRemove = () => {
    setCode('');
    setMessage(null);
    if (onCouponRemoved) onCouponRemoved();
  };

  return (
    <div className="p-4 border rounded-lg bg-card text-card-foreground">
      <h4 className="text-sm font-semibold mb-2">Discount Coupon</h4>
      <form onSubmit={handleApply} className="flex gap-2">
        <input
          type="text"
          value={code}
          onChange={(e) => setCode(e.target.value)}
          disabled={!!appliedCoupon || isPending}
          placeholder="Coupon code"
          className="flex-1 px-3 py-2 border rounded-md text-sm bg-background"
        />
        {appliedCoupon ? (
          <button
            type="button"
            onClick={handleRemove}
            className="px-3 py-2 bg-destructive text-destructive-foreground text-sm font-medium rounded-md"
          >
            Remove
          </button>
        ) : (
          <button
            type="submit"
            disabled={!code.trim() || isPending}
            className="px-3 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-md disabled:opacity-50"
          >
            {isPending ? 'Checking...' : 'Apply'}
          </button>
        )}
      </form>
      {message && (
        <p className={`text-xs mt-2 ${message.isError ? 'text-destructive' : 'text-green-600'}`}>
          {message.text}
        </p>
      )}
    </div>
  );
}
