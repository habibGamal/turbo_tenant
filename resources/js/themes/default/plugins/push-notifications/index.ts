import { registerThemeOverride } from '@/core/slots/slot-registry';
import ThemedNotificationBell from './ThemedNotificationBell';

export function registerPushNotificationsThemeOverride(): void {
  registerThemeOverride('default', 'header.actions.notifications', ThemedNotificationBell);
}

export { ThemedNotificationBell };
