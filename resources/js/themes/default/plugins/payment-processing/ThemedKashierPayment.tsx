import React, { useEffect, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Card } from '@/components/ui/card';
import { CreditCard, Shield, Info } from 'lucide-react';
import { PageTransition } from '@/components/PageTransition';
import { PaymentGatewaySkeleton } from '@/components/ui/page-skeletons';
import { useKashierConfig } from '@/plugins/payment-processing/api/useKashierConfig';

export default function ThemedKashierPayment() {
  const { id } = useParams<{ id: string }>();
  const { data, isLoading, error } = useKashierConfig(id);
  const iframeLoaded = useRef(false);
  const containerRef = useRef<HTMLDivElement>(null);

  const kashierParams = data?.kashierParams;
  const order = data?.order;

  useEffect(() => {
    if (!kashierParams || iframeLoaded.current) return;

    const script = document.createElement('script');
    script.id = 'kashier-iFrame';
    script.src = 'https://payments.kashier.io/kashier-checkout.js';

    script.setAttribute('data-amount', kashierParams.amount);
    script.setAttribute('data-hash', kashierParams.hash);
    script.setAttribute('data-currency', kashierParams.currency);
    script.setAttribute('data-orderid', kashierParams.orderId);
    script.setAttribute('data-merchantid', kashierParams.merchantId);
    script.setAttribute('data-merchantredirect', kashierParams.merchantRedirect);
    script.setAttribute('data-serverwebhook', kashierParams.serverWebhook);
    script.setAttribute('data-failureredirect', kashierParams.failureRedirect);
    script.setAttribute('data-mode', kashierParams.mode);
    script.setAttribute('data-display', kashierParams.displayMode);
    script.setAttribute('data-allowedmethods', kashierParams.allowedMethods);
    script.setAttribute('data-paymentrequestId', kashierParams.paymentRequestId);

    script.onload = () => {
      iframeLoaded.current = true;
    };

    if (containerRef.current) {
      containerRef.current.appendChild(script);
    }

    return () => {
      if (script.parentNode) {
        script.parentNode.removeChild(script);
      }
    };
  }, [kashierParams]);

  if (isLoading) {
    return <PaymentGatewaySkeleton />;
  }

  if (error || !data || !order || !kashierParams) {
    return (
      <div className="min-h-[60vh] flex flex-col items-center justify-center p-4 text-center">
        <h2 className="text-xl font-bold text-destructive mb-2">Payment Initialization Failed</h2>
        <p className="text-muted-foreground mb-4">Could not load the Kashier payment gateway session.</p>
        <Link to="/checkout" className="px-4 py-2 bg-primary text-primary-foreground rounded-lg">
          Return to Checkout
        </Link>
      </div>
    );
  }

  return (
    <PageTransition>
      <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 py-8 px-4">
        <div className="max-w-2xl mx-auto space-y-6">
          <div className="text-center space-y-2">
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
              <CreditCard className="h-8 w-8 text-primary" />
            </div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Complete Your Payment</h1>
            <p className="text-gray-600 dark:text-gray-400">Order #{order.order_number}</p>
          </div>

          <Card className="p-6 shadow-lg border-0 bg-white dark:bg-gray-800">
            <div className="space-y-6">
              <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div>
                  <p className="text-sm text-gray-500 dark:text-gray-400">Amount to Pay</p>
                  <p className="text-2xl font-bold text-gray-900 dark:text-white">
                    {kashierParams.amount} {kashierParams.currency}
                  </p>
                </div>
                <div className="flex items-center gap-2 text-green-600">
                  <Shield className="h-5 w-5" />
                  <span className="text-sm font-medium">Secure</span>
                </div>
              </div>

              <div id="kashier-iFrame-container" ref={containerRef} className="min-h-[400px]" />
            </div>
          </Card>

          <div className="flex gap-3 p-4 rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
            <Info className="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
            <div>
              <p className="font-medium text-blue-800 dark:text-blue-300">Having trouble with payment?</p>
              <p className="text-sm text-blue-700 dark:text-blue-400 mt-1">
                If the payment form does not appear, please try refreshing this page or contact support.
              </p>
            </div>
          </div>
        </div>
      </div>
    </PageTransition>
  );
}
