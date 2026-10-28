import { registerPluginDefault } from '@/core/slots/slot-registry';
import DefaultNotificationBell from './components/DefaultNotificationBell';

export function registerPushNotificationsPlugin(): void {
  registerPluginDefault('header.actions.notifications', DefaultNotificationBell);
}
