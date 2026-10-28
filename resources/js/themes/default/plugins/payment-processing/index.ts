import { registerThemeOverride } from '@/core/slots/slot-registry';
import ThemedPaymentCallback from './ThemedPaymentCallback';
import ThemedKashierPayment from './ThemedKashierPayment';

export function registerPaymentProcessingThemeOverride(): void {
  registerThemeOverride('default', 'payment.callback', ThemedPaymentCallback);
  registerThemeOverride('default', 'payment.kashier', ThemedKashierPayment);
}

export { ThemedPaymentCallback, ThemedKashierPayment };
