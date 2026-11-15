export interface AppliedCoupon {
  id: number;
  code: string;
  discount: number;
  type: string;
  value: number;
  free_shipping: boolean;
}

export interface ValidateCouponPayload {
  code: string;
  address_id?: number | null;
  guest_phone?: string | null;
}

export interface ValidateCouponData {
  coupon: AppliedCoupon;
}

export interface ValidateCouponResponse {
  coupon: AppliedCoupon;
}
