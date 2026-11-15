import { lazy } from 'react';
import { registerPluginDefault } from '@/core/slots/slot-registry';
const DefaultPaymentCallback = lazy(() => import('./components/DefaultPaymentCallback'));
const DefaultKashierPayment = lazy(() => import('./components/DefaultKashierPayment'));

export function registerPaymentProcessingPlugin(): void {
  registerPluginDefault('payment.callback', DefaultPaymentCallback);
  registerPluginDefault('payment.kashier', DefaultKashierPayment);
}
