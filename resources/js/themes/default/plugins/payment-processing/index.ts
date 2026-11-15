import { lazy } from 'react';
import { registerThemeOverride } from '@/core/slots/slot-registry';
const ThemedPaymentCallback = lazy(() => import('./ThemedPaymentCallback'));
const ThemedKashierPayment = lazy(() => import('./ThemedKashierPayment'));

export function registerPaymentProcessingThemeOverride(): void {
  registerThemeOverride('default', 'payment.callback', ThemedPaymentCallback);
  registerThemeOverride('default', 'payment.kashier', ThemedKashierPayment);
}

export { ThemedPaymentCallback, ThemedKashierPayment };
