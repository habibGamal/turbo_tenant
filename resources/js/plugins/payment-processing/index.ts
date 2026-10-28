import { registerPluginDefault } from '@/core/slots/slot-registry';
import DefaultPaymentCallback from './components/DefaultPaymentCallback';
import DefaultKashierPayment from './components/DefaultKashierPayment';

export function registerPaymentProcessingPlugin(): void {
  registerPluginDefault('payment.callback', DefaultPaymentCallback);
  registerPluginDefault('payment.kashier', DefaultKashierPayment);
}
