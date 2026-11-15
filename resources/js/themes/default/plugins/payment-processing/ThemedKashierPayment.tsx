import React, { useEffect, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CreditCard, Shield, Info } from 'lucide-react';
import { PageTransition } from '@/components/PageTransition';
import { PaymentGatewaySkeleton } from '@/components/ui/page-skeletons';
import { useKashierConfig } from '@/plugins/payment-processing/api/useKashierConfig';
import { useFormatMoney } from '@/lib/money';
import { useTranslation } from 'react-i18next';

export default function ThemedKashierPayment() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const { data, isLoading, error } = useKashierConfig(id);
  const formatMoney = useFormatMoney();
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
        <h2 className="text-xl font-bold text-destructive mb-2">
          {t('paymentInitializationFailed', 'Payment Initialization Failed')}
        </h2>
        <p className="text-muted-foreground mb-4">
          {t('kashierSessionError', 'Could not load the Kashier payment gateway session.')}
        </p>
        <Button asChild>
          <Link to="/checkout">
            {t('returnToCheckout', 'Return to Checkout')}
          </Link>
        </Button>
      </div>
    );
  }

  return (
    <PageTransition>
      <div className="min-h-screen bg-background py-8 px-4">
        <div className="max-w-2xl mx-auto space-y-6">
          <div className="text-center space-y-2">
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
              <CreditCard className="h-8 w-8 text-primary" />
            </div>
            <h1 className="text-2xl font-bold text-foreground">
              {t('completeYourPayment', 'Complete Your Payment')}
            </h1>
            <p className="text-muted-foreground">
              {t('order', 'Order')} #{order.order_number}
            </p>
          </div>

          <Card className="p-6 shadow-sm border border-border bg-card">
            <div className="space-y-6">
              <div className="flex items-center justify-between p-4 bg-muted/50 rounded-lg">
                <div>
                  <p className="text-sm text-muted-foreground">
                    {t('amountToPay', 'Amount to Pay')}
                  </p>
                  <p className="text-2xl font-bold text-foreground">
                    {formatMoney(kashierParams.amount)}
                  </p>
                </div>
                <div className="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                  <Shield className="h-5 w-5" />
                  <span className="text-sm font-medium">{t('secure', 'Secure')}</span>
                </div>
              </div>

              <div id="kashier-iFrame-container" ref={containerRef} className="min-h-[400px]" />
            </div>
          </Card>

          <div className="flex gap-3 p-4 rounded-lg border border-border bg-muted/40">
            <Info className="h-5 w-5 text-primary shrink-0 mt-0.5" />
            <div>
              <p className="font-medium text-foreground">
                {t('havingTroubleWithPayment', 'Having trouble with payment?')}
              </p>
              <p className="text-sm text-muted-foreground mt-1">
                {t(
                  'paymentFormTroubleHelp',
                  'If the payment form does not appear, please try refreshing this page or contact support.'
                )}
              </p>
            </div>
          </div>
        </div>
      </div>
    </PageTransition>
  );
}
