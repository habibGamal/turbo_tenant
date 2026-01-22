import React, { useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { AlertCircle, Loader2, CreditCard, Shield, Info } from 'lucide-react';

interface KashierParams {
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

interface Order {
    id: number;
    order_number: string;
    total: number;
}

interface KashierProps {
    kashierParams: KashierParams;
    order: Order;
}

export default function Kashier({ kashierParams, order }: KashierProps) {
    const iframeLoaded = useRef(false);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (iframeLoaded.current) return;

        // Load the Kashier checkout script
        const script = document.createElement('script');
        script.id = 'kashier-iFrame';
        script.src = 'https://payments.kashier.io/kashier-checkout.js';

        // Set data attributes for Kashier SDK
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
            console.log('Kashier script loaded successfully');
            iframeLoaded.current = true;
        };

        script.onerror = () => {
            console.error('Failed to load Kashier script');
        };

        // Append to container
        if (containerRef.current) {
            containerRef.current.appendChild(script);
        }

        // Cleanup
        return () => {
            if (script.parentNode) {
                script.parentNode.removeChild(script);
            }
        };
    }, [kashierParams]);

    return (
        <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 py-8 px-4">
            <Head title="Complete Payment" />

            <div className="max-w-2xl mx-auto space-y-6">
                {/* Header */}
                <div className="text-center space-y-2">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                        <CreditCard className="h-8 w-8 text-primary" />
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Complete Your Payment
                    </h1>
                    <p className="text-gray-600 dark:text-gray-400">
                        Order #{order.order_number}
                    </p>
                </div>

                {/* Payment Card */}
                <Card className="p-6 shadow-lg border-0 bg-white dark:bg-gray-800">
                    <div className="space-y-6">
                        {/* Order Summary */}
                        <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Amount to Pay
                                </p>
                                <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                    {kashierParams.amount} {kashierParams.currency}
                                </p>
                            </div>
                            <div className="flex items-center gap-2 text-green-600">
                                <Shield className="h-5 w-5" />
                                <span className="text-sm font-medium">Secure</span>
                            </div>
                        </div>

                        {/* Loading State */}
                        <div className="flex flex-col items-center justify-center py-8">
                            <Loader2 className="h-8 w-8 animate-spin text-primary mb-4" />
                            <p className="text-gray-600 dark:text-gray-400">
                                Loading secure payment form...
                            </p>
                        </div>

                        {/* Kashier iFrame Container */}
                        <div
                            id="kashier-iFrame-container"
                            ref={containerRef}
                            className="min-h-[400px]"
                        />
                    </div>
                </Card>

                {/* Help Info */}
                <div className="flex gap-3 p-4 rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                    <Info className="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                    <div>
                        <p className="font-medium text-blue-800 dark:text-blue-300">
                            Having trouble with payment?
                        </p>
                        <p className="text-sm text-blue-700 dark:text-blue-400 mt-1">
                            If the payment form does not appear, please try refreshing this page.
                            For assistance, contact our support team.
                        </p>
                    </div>
                </div>

                {/* Security Info */}
                <div className="text-center text-xs text-gray-500 dark:text-gray-400">
                    <p>
                        Your payment is secured by Kashier payment gateway.
                        We never store your card details.
                    </p>
                </div>
            </div>
        </div>
    );
}
