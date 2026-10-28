import React from 'react';
import CouponForm from './components/CouponForm';
import { AppliedCoupon } from '@/plugins/coupons-promotions/types';

interface ThemedCouponFormProps {
  cartItems?: any[];
  cartTotal?: number;
  addressId?: number | null;
  guestPhone?: string | null;
  appliedCoupon?: AppliedCoupon | null;
  onCouponApplied?: (coupon: AppliedCoupon) => void;
  onCouponRemoved?: () => void;
  context?: {
    cartItems?: any[];
    cartTotal?: number;
    addressId?: number | null;
    guestPhone?: string | null;
    appliedCoupon?: AppliedCoupon | null;
    onCouponApplied?: (coupon: AppliedCoupon) => void;
    onCouponRemoved?: () => void;
  };
}

export default function ThemedCouponForm(props: ThemedCouponFormProps) {
  const ctx = props.context || {};
  const cartItems = props.cartItems || ctx.cartItems || [];
  const cartTotal = props.cartTotal ?? ctx.cartTotal ?? 0;
  const addressId = props.addressId ?? ctx.addressId ?? null;
  const guestPhone = props.guestPhone ?? ctx.guestPhone ?? null;
  const appliedCoupon = props.appliedCoupon ?? ctx.appliedCoupon ?? null;
  const onCouponApplied = props.onCouponApplied || ctx.onCouponApplied;
  const onCouponRemoved = props.onCouponRemoved || ctx.onCouponRemoved;

  return (
    <CouponForm
      cartItems={cartItems}
      cartTotal={cartTotal}
      addressId={addressId}
      guestPhone={guestPhone}
      appliedCoupon={appliedCoupon}
      onCouponApplied={onCouponApplied}
      onCouponRemoved={onCouponRemoved}
    />
  );
}
