# Push notifications

## Overview

Sends Expo push notifications to customers' devices: automatically when their order is created or changes status, and as admin campaigns (now or scheduled, to everyone or chosen users). Signed-in customers also have an in-app notification inbox.

**Status:** live · **Plugin:** `push-notifications` ([plugin.json](../../../app/Plugins/PushNotifications/plugin.json)) · **Depends on:** order-management · **Related:** [Customer accounts](customer-accounts.md) (device token registration), [Checkout](checkout-and-orders.md)

## How to use

### Customer

1. In the Expo app (or a web view that provides an Expo token), sign in; the app sends its `expo_token` with login, registration or Google sign-in and the token is saved on the user.
2. Receive pushes for order updates and campaigns. The inbox API lists notifications, unread count, mark read/all read, delete, clear read.

### Store admin: send a campaign

**المحتوى والتسويق › إشعارات Push** (Content & marketing › Push notifications) → create:

1. **العنوان** (title), **الرسالة** (message), optional **بيانات إضافية** (extra data JSON for the app).
2. **نوع الإرسال** (send type): now or scheduled with **وقت الإرسال المجدول** (scheduled time).
3. **إرسال لجميع المستخدمين** (all users) or **اختر المستخدمين المستهدفين** (pick users).
4. Save. The record shows status (draft/scheduled/sending/sent/failed), recipients, successes and failures. A sent campaign can be re-sent from its view page.

## How it works

1. **Token registration:** [`AuthController`](../../../app/Http/Controllers/Api/AuthController.php) (login/register), [`GoogleNativeAuthController`](../../../app/Http/Controllers/Api/GoogleNativeAuthController.php), the web session login and the Google web callback store `users.expo_token`. The SPA keeps the token in `localStorage` (`expoPushToken`, [InitExpo.tsx](../../../resources/js/components/InitExpo.tsx)).
2. **Order notifications:** [`SendOrderStatusNotificationListener`](../../../app/Plugins/PushNotifications/src/Listeners/SendOrderStatusNotificationListener.php) on `OrderCreated` and `OrderStatusUpdated` queues [`SendOrderStatusNotificationJob`](../../../app/Plugins/PushNotifications/src/Jobs/SendOrderStatusNotificationJob.php), which sends [`OrderStatusNotification`](../../../app/Plugins/PushNotifications/src/Notifications/OrderStatusNotification.php) over the `expo` channel to the order's user. Guest orders have no user and get no push.
3. **Campaigns:** [`CreateBulkNotification`](../../../app/Plugins/PushNotifications/src/Filament/Resources/BulkNotifications/Pages/CreateBulkNotification.php) dispatches [`SendBulkNotificationJob`](../../../app/Plugins/PushNotifications/src/Jobs/SendBulkNotificationJob.php) immediately, or delayed until `scheduled_at`. The job marks the campaign `sending`, notifies each target user that has a token, and records counts; any per-user failure makes the final status `failed`.
4. **Scheduler:** `notifications:send-scheduled` ([command](../../../app/Plugins/PushNotifications/src/Console/Commands/SendScheduledBulkNotifications.php)) runs every minute and dispatches due `scheduled` campaigns. It runs without tenant context, so it queries the central DB's `bulk_notifications` table; tenant campaigns are delivered by the delayed job from step 3.
5. **Inbox:** [`NotificationController`](../../../app/Plugins/PushNotifications/src/Http/Controllers/Api/NotificationController.php) over `user_notifications` / `notifications`.

## Configuration

No store settings. Requires a queue worker and the scheduler. `php artisan expo:send-test` (inside a tenant) sends a test push to every user with a token.

## Testing

- Automated: [BulkNotificationTest](../../../tests/Feature/BulkNotificationTest.php), [OrderStatusNotificationTest](../../../tests/Feature/OrderStatusNotificationTest.php), [NotificationControllerTest](../../../tests/Feature/Api/NotificationControllerTest.php), [SendScheduledBulkNotificationsTest](../../../tests/Feature/Commands/SendScheduledBulkNotificationsTest.php), [BulkNotificationResourceTest](../../../tests/Feature/Filament/Resources/BulkNotificationResourceTest.php), [PushNotificationsUxTest](../../../tests/Feature/PushNotificationsUxTest.php).
- Browser: [push-notifications.live.spec.ts](../../../tests/e2e/plugins/push-notifications.live.spec.ts).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| No push for order updates | User has no `expo_token`, guest order, or no worker | Sign in from the app; run the worker |
| Scheduled campaign never sent | Delayed job lost (queue cleared) or worker down at the time | Re-send from the campaign view |
| Campaign status `failed` with some successes | At least one user's push failed | Check log "Failed to send bulk notification to user" |
| Central `bulk_notifications` table exists | Duplicate migration in `database/migrations` | Harmless; the tenant table is the one in use |

## History

- 2026-01/02: bulk campaigns, in-app inbox.
- 2025-12: Expo push support.
