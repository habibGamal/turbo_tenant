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
  cart_items: any[];
  sub_total: number;
  address_id?: number | null;
  guest_phone?: string | null;
}

export interface ValidateCouponResponse {
  valid: boolean;
  message: string;
  coupon?: AppliedCoupon;
}
