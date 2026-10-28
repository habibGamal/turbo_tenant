import React, { useEffect, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useKashierConfig } from '../api/useKashierConfig';

export default function DefaultKashierPayment() {
  const { id } = useParams<{ id: string }>();
  const { data, isLoading, error } = useKashierConfig(id);
  const containerRef = useRef<HTMLDivElement>(null);
  const scriptLoaded = useRef(false);

  useEffect(() => {
    if (!data?.kashierParams || scriptLoaded.current) return;

    const script = document.createElement('script');
    script.id = 'kashier-iFrame';
    script.src = 'https://payments.kashier.io/kashier-checkout.js';

    const p = data.kashierParams;
    script.setAttribute('data-amount', p.amount);
    script.setAttribute('data-hash', p.hash);
    script.setAttribute('data-currency', p.currency);
    script.setAttribute('data-orderid', p.orderId);
    script.setAttribute('data-merchantid', p.merchantId);
    script.setAttribute('data-merchantredirect', p.merchantRedirect);
    script.setAttribute('data-serverwebhook', p.serverWebhook);
    script.setAttribute('data-failureredirect', p.failureRedirect);
    script.setAttribute('data-mode', p.mode);
    script.setAttribute('data-display', p.displayMode);
    script.setAttribute('data-allowedmethods', p.allowedMethods);
    script.setAttribute('data-paymentrequestId', p.paymentRequestId);

    script.onload = () => {
      scriptLoaded.current = true;
    };

    if (containerRef.current) {
      containerRef.current.appendChild(script);
    }

    return () => {
      if (script.parentNode) {
        script.parentNode.removeChild(script);
      }
    };
  }, [data]);

  if (isLoading) {
    return (
      <div className="p-8 text-center text-muted-foreground">
        Loading secure checkout...
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="p-8 text-center text-destructive">
        <p className="mb-4">Failed to initialize payment gateway.</p>
        <Link to="/checkout" className="text-primary underline">Return to Checkout</Link>
      </div>
    );
  }

  return (
    <div className="max-w-xl mx-auto p-6 bg-card border rounded-xl shadow-sm">
      <h2 className="text-xl font-bold mb-2">Order #{data.order.order_number}</h2>
      <p className="text-sm text-muted-foreground mb-4">Total: {data.order.total} EGP</p>
      <div id="kashier-iFrame-container" ref={containerRef} className="min-h-[350px]" />
    </div>
  );
}
