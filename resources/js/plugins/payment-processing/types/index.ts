export interface OrderPaymentStatus {
  order: {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    payment_method: string;
    total: number;
    currency: string;
  };
  is_paid: boolean;
}

export interface KashierParams {
  merchantId: string;
  orderId: string;
  amount: string;
  currency: string;
  hash: string;
  mode: string;
  merchantRedirect: string;
  serverWebhook: string;
  failureRedirect: string;
  allowedMethods: string;
  displayMode: string;
  paymentRequestId: string;
}

export interface KashierConfigData {
  kashierParams: KashierParams;
  order: {
    id: number;
    order_number: string;
    total: number;
  };
}
