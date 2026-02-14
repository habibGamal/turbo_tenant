# 🔐 Guest Ordering Security Fix Checklist

Quick reference for implementing security fixes identified in the audit.

---

## 🔴 CRITICAL - Deploy Within 24 Hours

### ☐ 1. Fix IDOR Vulnerability (30 minutes)
**File:** `app/Http/Controllers/OrderController.php`

**Line 199-214:** Replace order query in `show()` method

```diff
- $order = Order::with([...])
-     ->where('id', $orderId)
-     ->where('user_id', $user->id)
-     ->firstOrFail();

+ $order = $user->orders()
+     ->with(['items.extras', 'items.product.weightOption', 'items.weightOptionValue', 'branch', 'address.area'])
+     ->where('id', $orderId)
+     ->firstOrFail();
```

**Test:**
```bash
php artisan test --filter=testUserCannotAccessOtherUsersOrders
```

---

### ☐ 2. Add Rate Limiting (15 minutes)
**File:** `routes/tenant.php`

**Line 101-109:** Wrap guest endpoints in throttle middleware

```diff
+ // Guest order tracking - strict rate limit (5 requests per minute)
+ Route::middleware('throttle:5,1')->group(function () {
      Route::post('/orders/track', [OrderController::class, 'trackGuestOrder'])
          ->name('orders.track');
+ });

+ // Order placement - moderate rate limit (10 per minute)
+ Route::middleware('throttle:10,1')->group(function () {
      Route::post('/orders/place', [OrderController::class, 'placeOrder'])
          ->name('orders.place');
+ });

  Route::get('/checkout', [OrderController::class, 'checkout'])
      ->name('checkout');
```

**Test:**
```bash
# Manual test - rapid requests should be throttled
for i in {1..20}; do curl -X POST http://app.test/orders/track; done
```

---

### ☐ 3. Stop Logging PII (90 minutes)

**Step 3.1:** Create sanitized logger helper
**File:** `app/Helpers/SanitizedLogger.php` (NEW FILE)

See full implementation in `SECURITY_FIXES.md` section 3.

**Step 3.2:** Update PlaceOrderService
**File:** `app/Services/PlaceOrderService.php`

```diff
+ use App\Helpers\SanitizedLogger;

  // Line 172-176
- logger()->info('Creating payment intention', [
+ SanitizedLogger::info('Creating payment intention', [
      'order_id' => $order->id,
      'gateway' => $gatewayId,
-     'billing_data' => $billingData, // REMOVE THIS
  ]);

  // Line 200-202
- logger()->info('Payment intention created', [
+ SanitizedLogger::info('Payment intention created', [
      'order_id' => $order->id,
-     'paymentResult' => $paymentResult, // REMOVE THIS
  ]);
```

**Step 3.3:** Update OrderController
**File:** `app/Http/Controllers/OrderController.php`

```diff
+ use App\Helpers\SanitizedLogger;

  // Line 130
- logger()->info('Payment callback data', ['data' => $callbackData]);
+ SanitizedLogger::info('Payment callback received', [
+     'order_id' => $orderId,
+     'success' => $callbackData['success'] ?? null,
+ ]);
```

**Test:**
```bash
# Check logs don't contain PII
grep -r "phone\|email\|billing_data" storage/logs/
# Should return: [REDACTED]
```

---

### ☐ 4. Strengthen Webhook HMAC Validation (30 minutes)

**File:** `app/Http/Controllers/PaymobWebhookController.php`

**Line 27-37:** Make HMAC validation stricter

```diff
  if (!$hmac) {
-     Log::warning('Paymob webhook received without HMAC', ['data' => $webhookData]);
-     return response()->json(['success' => false, 'error' => 'Missing HMAC'], 400);
+     Log::critical('Webhook HMAC missing - potential attack', [
+         'ip' => $request->ip(),
+         'user_agent' => $request->userAgent(),
+     ]);
+     abort(403, 'Forbidden');
  }
  
  // ... existing code
  
  $result = $this->placeOrderService->handleWebhook($webhookData, $hmac);
  
  if (!$result['success']) {
+     // Detect signature validation failures
+     if (str_contains($result['error'] ?? '', 'signature')) {
+         Log::critical('Invalid webhook signature - attack detected', [
+             'ip' => $request->ip(),
+         ]);
+         abort(403, 'Forbidden');
+     }
```

**File:** `app/Http/Controllers/KashierWebhookController.php` (SAME CHANGES)

**Test:**
```bash
# Send webhook without signature
curl -X POST http://app.test/api/webhooks/paymob
# Should return: 403 Forbidden
```

---

## 🟠 HIGH PRIORITY - Deploy Within 1 Week

### ☐ 5. Create Form Request for Validation (60 minutes)

**Step 5.1:** Create Form Request class
```bash
php artisan make:request PlaceGuestOrderRequest
```

**File:** `app/Http/Requests/PlaceGuestOrderRequest.php`

See full implementation in `SECURITY_FIXES.md` section 5.

**Step 5.2:** Update OrderController
**File:** `app/Http/Controllers/OrderController.php`

```diff
+ use App\Http\Requests\PlaceGuestOrderRequest;

- public function placeOrder(Request $request): JsonResponse
+ public function placeOrder(PlaceGuestOrderRequest $request): JsonResponse
  {
-     $validator = Validator::make($request->all(), [...]); // REMOVE ALL VALIDATION
-     if ($validator->fails()) { return ...; } // REMOVE
      
+     $validated = $request->validated();
      $user = Auth::user();
      
      if (!$user) {
-         $guestData = $request->input('guest_data');
+         $guestData = $validated['guest_data'];
```

**Test:**
```bash
php artisan test --filter=testGuestOrderValidation
```

---

### ☐ 6. Add Input Sanitization (45 minutes)

**File:** `app/Services/GuestUserService.php`

Add private methods for sanitization (see `SECURITY_FIXES.md` section 6):
- `sanitizeName()`
- `sanitizeEmail()`
- `sanitizeText()`

Update `findOrCreate()` to use sanitization.

**Test:**
```bash
php artisan test --filter=testGuestUserSanitization
```

---

### ☐ 7. Prevent Timing Attacks (30 minutes)

**File:** `app/Http/Controllers/OrderController.php`

**Line 234-260:** Add constant-time responses in `trackGuestOrder()`

```diff
  $orderNumber = $request->input('order_number');
- $phone = $request->input('phone');
+ $phone = preg_replace('/[^0-9]/', '', $request->input('phone'));
  
+ // Add timing attack protection
+ $startTime = microtime(true);
  
  $guestUser = GuestUser::where('phone', $phone)
      ->where('phone_country_code', $phoneCountryCode)
      ->first();

  if (!$guestUser) {
+     // Constant time response to prevent enumeration
+     usleep(random_int(100000, 300000)); // 100-300ms delay
      return response()->json([...], 404);
  }
  
  $order = Order::with([...])
      ->where('order_number', $orderNumber)
      ->where('guest_user_id', $guestUser->id)
      ->first();

  if (!$order) {
+     usleep(random_int(100000, 300000));
      return response()->json([...], 404);
  }
```

**Test:**
```bash
# Measure response times
time curl -X POST http://app.test/orders/track -d "phone=invalid"
time curl -X POST http://app.test/orders/track -d "phone=valid"
# Should be similar (within 50ms)
```

---

## 🟡 MEDIUM PRIORITY - Deploy Within 2 Weeks

### ☐ 8. Fix Session Security (30 minutes)

**File:** `app/Services/CartService.php`

**Line 133:** Use cryptographically secure random
```diff
  if (!Session::has('guest_cart_id')) {
-     Session::put('guest_cart_id', 'guest_' . uniqid());
+     Session::put('guest_cart_id', 'guest_' . Str::random(40));
  }
```

**File:** `app/Services/PlaceOrderService.php`

**After line 142:** Regenerate session for guests
```diff
+ use Illuminate\Support\Facades\Session;

  // Clear cart after order is created
  $this->cartService->clearCart($user);
  
+ // Regenerate session for guest orders (prevent session fixation)
+ if ($isGuest) {
+     Session::regenerate();
+ }

  DB::commit();
```

---

### ☐ 9. Implement Data Retention Policy (90 minutes)

**Step 9.1:** Create cleanup command
```bash
php artisan make:command CleanupOldGuestData
```

**File:** `app/Console/Commands/CleanupOldGuestData.php`

See full implementation in `SECURITY_FIXES.md` section 10.

**Step 9.2:** Schedule it
**File:** `routes/console.php`

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('guests:cleanup')->monthly();
```

**Test:**
```bash
php artisan guests:cleanup --dry-run
```

---

### ☐ 10. Encrypt Guest PII (120 minutes)

**File:** `app/Models/GuestUser.php`

Add encrypted attributes for `phone` and `email` (see `SECURITY_FIXES.md` section 9).

**Create migration to encrypt existing data:**
```bash
php artisan make:migration encrypt_guest_user_data
```

**⚠️ IMPORTANT:** Test on staging first! Encryption is irreversible.

---

### ☐ 11. Add Security Event Logging (60 minutes)

Create event listener for security events (see `SECURITY_FIXES.md` section 11).

**Files to create:**
- `app/Events/GuestOrderTrackingFailed.php`
- `app/Events/RateLimitExceeded.php`
- `app/Listeners/LogSecurityEvents.php`

---

## 📋 Post-Deployment Checklist

After deploying all fixes:

### Testing
- [ ] Run full test suite: `php artisan test`
- [ ] Manual IDOR test (try accessing other user's orders)
- [ ] Rate limit test (rapid-fire requests)
- [ ] Check logs for `[REDACTED]` entries
- [ ] Test webhook with invalid HMAC
- [ ] Test guest order tracking timing

### Monitoring
- [ ] Set up alert for rate limit violations
- [ ] Monitor logs for `[REDACTED]` to verify PII sanitization
- [ ] Check for 403 errors on webhook endpoints
- [ ] Monitor failed guest tracking attempts

### Documentation
- [ ] Update API documentation with new rate limits
- [ ] Update privacy policy for data retention
- [ ] Document incident response procedure
- [ ] Create runbook for security alerts

### Compliance
- [ ] Verify GDPR compliance (data retention policy)
- [ ] Verify PCI-DSS compliance (no plaintext PII in logs)
- [ ] Schedule quarterly security review
- [ ] Document all changes in change log

---

## 🆘 Quick Reference

### If Something Breaks

**Emergency Rollback:**
```bash
# Disable guest ordering
php artisan down --message="Security maintenance in progress"

# Rollback last deployment
git revert HEAD
php artisan migrate:rollback --step=1
composer install
npm run build

php artisan up
```

**Check Deployment Status:**
```bash
# Verify rate limiting is active
curl -I http://app.test/orders/track | grep "X-RateLimit"

# Verify CSRF protection
curl -X POST http://app.test/orders/place
# Should return: 419 (CSRF token mismatch)

# Check logs for PII
grep -r "phone\|email" storage/logs/laravel-$(date +%Y-%m-%d).log
# Should only show "[REDACTED]"
```

---

## 📞 Support Contacts

**Security Issues:**
- Security Team: security@turbotenant.com
- On-Call Dev: +1-XXX-XXX-XXXX
- Incident Manager: incidents@turbotenant.com

**Escalation:**
- L1: Development Team (Response: 30 min)
- L2: Security Team (Response: 15 min)
- L3: CTO / CISO (Response: Immediate)

---

**Last Updated:** February 12, 2026  
**Next Review:** After all fixes deployed

✅ **Track Progress:** Update this checklist as you complete each item.
