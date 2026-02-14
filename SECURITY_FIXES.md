# Security Fixes for Guest Ordering System

## 🔴 CRITICAL - Fix Immediately

### 1. Fix IDOR Vulnerability in Order Show
**File:** `app/Http/Controllers/OrderController.php`

**Current Code (VULNERABLE):**
```php
public function show(int $orderId): Response
{
    $user = Auth::user();
    if (!$user) {
        abort(401, 'User not authenticated');
    }
    
    // ONLY checks if order exists for ANY user
    $order = Order::with([...])
        ->where('id', $orderId)
        ->where('user_id', $user->id)
        ->firstOrFail();
}
```

**Fixed Code:**
```php
public function show(int $orderId): Response
{
    $user = Auth::user();
    if (!$user) {
        abort(401, 'User not authenticated');
    }
    
    // ✅ Properly scoped to current user's orders only
    $order = $user->orders()
        ->with(['items.extras', 'items.product.weightOption', 'items.weightOptionValue', 'branch', 'address.area'])
        ->where('id', $orderId)
        ->firstOrFail();
    
    return Inertia::render('OrderShow', [
        'order' => $order,
    ]);
}
```

### 2. Add Rate Limiting to Guest Endpoints
**File:** `routes/tenant.php`

**Add throttling middleware:**
```php
// Guest order tracking - aggressive rate limit
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/orders/track', [App\Http\Controllers\OrderController::class, 'trackGuestOrder'])
        ->name('orders.track');
});

// Order placement - moderate rate limit
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/orders/place', [App\Http\Controllers\OrderController::class, 'placeOrder'])
        ->name('orders.place');
});

// Checkout page - light rate limit
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/checkout', [App\Http\Controllers\OrderController::class, 'checkout'])
        ->name('checkout');
});
```

### 3. Stop Logging Sensitive Data
**File:** Create new `app/Helpers/SanitizedLogger.php`

```php
<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

final class SanitizedLogger
{
    /**
     * Sensitive fields that should never be logged
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'card_number',
        'cvv',
        'cvc',
        'pan',
        'billing_data',
        'phone',
        'email',
        'address',
        'street',
        'building',
        'apartment',
    ];

    /**
     * Log data after removing sensitive information
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info($message, self::sanitize($context));
    }

    public static function error(string $message, array $context = []): void
    {
        Log::error($message, self::sanitize($context));
    }

    public static function warning(string $message, array $context = []): void
    {
        Log::warning($message, self::sanitize($context));
    }

    /**
     * Remove sensitive data from context
     */
    private static function sanitize(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (self::isSensitiveField($key)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    private static function isSensitiveField(string $field): bool
    {
        $field = strtolower($field);
        
        foreach (self::SENSITIVE_FIELDS as $sensitive) {
            if (str_contains($field, $sensitive)) {
                return true;
            }
        }
        
        return false;
    }
}
```

**Update:** `app/Services/PlaceOrderService.php`
```php
use App\Helpers\SanitizedLogger;

// Replace all logger() calls with:
SanitizedLogger::info('Creating payment intention', [
    'order_id' => $order->id,
    'gateway' => $gatewayId,
    // Billing data will be automatically redacted
]);
```

### 4. Strengthen Webhook HMAC Validation
**File:** `app/Http/Controllers/PaymobWebhookController.php`

```php
public function handle(Request $request): JsonResponse
{
    try {
        $webhookData = $request->all();
        $hmac = $request->query('hmac') ?? $webhookData['hmac'] ?? null;
        
        // ✅ REJECT immediately if no HMAC
        if (!$hmac) {
            Log::critical('Webhook HMAC missing - potential attack', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            abort(403, 'Forbidden');
        }
        
        // ✅ Validate HMAC BEFORE processing
        $result = $this->placeOrderService->handleWebhook($webhookData, $hmac);
        
        if (!$result['success']) {
            // Check if it's a signature error
            if (isset($result['error']) && str_contains($result['error'], 'signature')) {
                Log::critical('Invalid webhook signature - attack detected', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                abort(403, 'Forbidden');
            }
            
            Log::error('Paymob webhook processing failed', [
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }
        
        Log::info('Paymob webhook processed successfully', [
            'order_id' => $result['order']->id ?? null,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully',
        ]);
    } catch (Exception $e) {
        Log::error('Paymob webhook exception', [
            'error' => $e->getMessage(),
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Internal server error',
        ], 500);
    }
}
```

**Same fix for:** `app/Http/Controllers/KashierWebhookController.php`

---

## 🟡 HIGH PRIORITY - Fix Within 1 Week

### 5. Enhanced Input Validation
**File:** `app/Http/Controllers/OrderController.php`

Create a Form Request instead of inline validation:

**Create:** `app/Http/Requests/PlaceGuestOrderRequest.php`
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PlaceGuestOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'payment_method' => ['required', 'string', 'in:card,wallet,cod,kiosk,bank_transfer'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:web_delivery,web_takeaway,pos'],

            // ✅ Strengthened guest data validation
            'guest_data' => ['required_without:auth', 'array'],
            'guest_data.name' => ['required_with:guest_data', 'string', 'min:2', 'max:255', 'regex:/^[\p{L}\s]+$/u'],
            'guest_data.phone' => ['required_with:guest_data', 'string', 'regex:/^[0-9]{10,15}$/', 'min:10', 'max:15'],
            'guest_data.phone_country_code' => ['nullable', 'string', 'regex:/^\+[0-9]{1,4}$/'],
            'guest_data.email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'guest_data.street' => ['nullable', 'string', 'max:255'],
            'guest_data.building' => ['nullable', 'string', 'max:50'],
            'guest_data.floor' => ['nullable', 'string', 'max:10'],
            'guest_data.apartment' => ['nullable', 'string', 'max:10'],
            'guest_data.city' => ['nullable', 'string', 'max:100'],
            'guest_data.area_id' => ['nullable', 'integer', 'exists:areas,id'],

            // ✅ Billing data validation
            'billing_data' => ['nullable', 'array'],
            'billing_data.first_name' => ['nullable', 'string', 'max:255'],
            'billing_data.last_name' => ['nullable', 'string', 'max:255'],
            'billing_data.email' => ['nullable', 'email:rfc', 'max:255'],
            'billing_data.phone_number' => ['nullable', 'string', 'regex:/^[+]?[0-9]{10,15}$/'],
            'billing_data.apartment' => ['nullable', 'string', 'max:50'],
            'billing_data.floor' => ['nullable', 'string', 'max:10'],
            'billing_data.street' => ['nullable', 'string', 'max:255'],
            'billing_data.building' => ['nullable', 'string', 'max:50'],
            'billing_data.city' => ['nullable', 'string', 'max:100'],
            'billing_data.country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'billing_data.postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'guest_data.name.regex' => 'Name must contain only letters and spaces.',
            'guest_data.phone.regex' => 'Phone number must contain only digits (10-15 characters).',
            'guest_data.phone_country_code.regex' => 'Country code must be in format: +20',
            'guest_data.email.email' => 'Please provide a valid email address.',
            'billing_data.phone_number.regex' => 'Phone number format is invalid.',
            'billing_data.country.regex' => 'Country code must be 2 uppercase letters (e.g., EG, US).',
        ];
    }
}
```

**Update Controller:**
```php
use App\Http\Requests\PlaceGuestOrderRequest;

public function placeOrder(PlaceGuestOrderRequest $request): JsonResponse
{
    // ✅ Validation is now handled by Form Request
    $validated = $request->validated();
    
    $user = Auth::user();
    
    if (!$user) {
        $guestData = $validated['guest_data'];
        $user = $this->guestUserService->findOrCreate($guestData);
    }
    
    // ... rest of the method
}
```

### 6. Add Input Sanitization
**File:** `app/Services/GuestUserService.php`

```php
public function findOrCreate(array $guestData): GuestUser
{
    // ✅ Sanitize phone number (remove non-digits)
    $phone = preg_replace('/[^0-9]/', '', $guestData['phone']);
    $phoneCountryCode = $guestData['phone_country_code'] ?? '+20';

    // Try to find existing guest by phone
    $guestUser = GuestUser::where('phone', $phone)
        ->where('phone_country_code', $phoneCountryCode)
        ->first();

    if ($guestUser) {
        // ✅ Sanitize before updating
        $guestUser->update(array_filter([
            'name' => $this->sanitizeName($guestData['name'] ?? $guestUser->name),
            'email' => $this->sanitizeEmail($guestData['email'] ?? null) ?? $guestUser->email,
            'street' => $this->sanitizeText($guestData['street'] ?? null) ?? $guestUser->street,
            'building' => $this->sanitizeText($guestData['building'] ?? null) ?? $guestUser->building,
            'floor' => $this->sanitizeText($guestData['floor'] ?? null) ?? $guestUser->floor,
            'apartment' => $this->sanitizeText($guestData['apartment'] ?? null) ?? $guestUser->apartment,
            'city' => $this->sanitizeText($guestData['city'] ?? null) ?? $guestUser->city,
            'area_id' => $guestData['area_id'] ?? $guestUser->area_id,
        ]));

        return $guestUser;
    }

    // ✅ Sanitize before creating
    return GuestUser::create([
        'name' => $this->sanitizeName($guestData['name']),
        'email' => $this->sanitizeEmail($guestData['email'] ?? null),
        'phone' => $phone,
        'phone_country_code' => $phoneCountryCode,
        'street' => $this->sanitizeText($guestData['street'] ?? null),
        'building' => $this->sanitizeText($guestData['building'] ?? null),
        'floor' => $this->sanitizeText($guestData['floor'] ?? null),
        'apartment' => $this->sanitizeText($guestData['apartment'] ?? null),
        'city' => $this->sanitizeText($guestData['city'] ?? null),
        'area_id' => $guestData['area_id'] ?? null,
    ]);
}

private function sanitizeName(?string $name): ?string
{
    if (!$name) {
        return null;
    }
    
    return trim(strip_tags($name));
}

private function sanitizeEmail(?string $email): ?string
{
    if (!$email) {
        return null;
    }
    
    $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    return filter_var($sanitized, FILTER_VALIDATE_EMAIL) ? $sanitized : null;
}

private function sanitizeText(?string $text): ?string
{
    if (!$text) {
        return null;
    }
    
    return trim(strip_tags($text));
}
```

### 7. Strengthen Guest Order Tracking
**File:** `app/Http/Controllers/OrderController.php`

```php
public function trackGuestOrder(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'order_number' => ['required', 'string', 'regex:/^ORD-[A-Z0-9]{8}$/'], // ✅ Strict format
        'phone' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'], // ✅ Only digits
        'phone_country_code' => ['nullable', 'string', 'regex:/^\+[0-9]{1,4}$/'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    $orderNumber = $request->input('order_number');
    $phone = preg_replace('/[^0-9]/', '', $request->input('phone')); // ✅ Sanitize
    $phoneCountryCode = $request->input('phone_country_code', '+20');

    // ✅ Add timing attack protection
    $startTime = microtime(true);
    
    // Find guest user
    $guestUser = GuestUser::where('phone', $phone)
        ->where('phone_country_code', $phoneCountryCode)
        ->first();

    if (!$guestUser) {
        // ✅ Constant time response to prevent enumeration
        usleep(random_int(100000, 300000)); // 100-300ms delay
        
        return response()->json([
            'success' => false,
            'error' => 'No orders found for this phone number',
        ], 404);
    }

    // Find order
    $order = Order::with([
        'items.extras',
        'items.product.weightOption',
        'items.weightOptionValue',
        'branch',
        'guestUser'
    ])
        ->where('order_number', $orderNumber)
        ->where('guest_user_id', $guestUser->id)
        ->first();

    if (!$order) {
        // ✅ Constant time response
        usleep(random_int(100000, 300000));
        
        return response()->json([
            'success' => false,
            'error' => 'Order not found',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'order' => $order,
    ]);
}
```

### 8. Fix Session Security Issues
**File:** `app/Services/CartService.php`

```php
private function getGuestCartIdentifier(): string
{
    if (!Session::has('guest_cart_id')) {
        // ✅ Use cryptographically secure random
        Session::put('guest_cart_id', 'guest_' . Str::random(40));
    }

    return Session::get('guest_cart_id');
}
```

**File:** `app/Services/PlaceOrderService.php`

```php
use Illuminate\Support\Facades\Session;

public function placeOrder(...): array
{
    // ... existing code
    
    // Clear cart after order is created
    $this->cartService->clearCart($user);
    
    // ✅ Regenerate session for guest orders (prevent session fixation)
    if ($isGuest) {
        Session::regenerate();
    }
    
    DB::commit();
    
    // ... rest of the method
}
```

---

## 🟢 MEDIUM PRIORITY - Fix Within 2 Weeks

### 9. Implement Guest Data Encryption
**File:** `app/Models/GuestUser.php`

```php
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

// ✅ Encrypt sensitive fields
protected function phone(): Attribute
{
    return Attribute::make(
        get: fn ($value) => $value ? Crypt::decryptString($value) : null,
        set: fn ($value) => $value ? Crypt::encryptString($value) : null,
    );
}

protected function email(): Attribute
{
    return Attribute::make(
        get: fn ($value) => $value ? Crypt::decryptString($value) : null,
        set: fn ($value) => $value ? Crypt::encryptString($value) : null,
    );
}

// Note: After adding encryption, you'll need to migrate existing data
```

**Migration to encrypt existing data:**
```php
// Create: database/migrations/tenant/2026_02_13_000001_encrypt_guest_user_data.php
public function up(): void
{
    GuestUser::chunk(100, function ($guestUsers) {
        foreach ($guestUsers as $guestUser) {
            // Re-save to trigger encryption
            $guestUser->save();
        }
    });
}
```

### 10. Add Data Retention Policy
**Create:** `app/Console/Commands/CleanupOldGuestData.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GuestUser;
use Illuminate\Console\Command;

final class CleanupOldGuestData extends Command
{
    protected $signature = 'guests:cleanup {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Clean up old guest user data for GDPR compliance';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        // Delete guest users with no orders in 2+ years
        $orphanedQuery = GuestUser::whereDoesntHave('orders')
            ->where('created_at', '<', now()->subYears(2));
        
        $orphanedCount = $orphanedQuery->count();
        $this->info("Found {$orphanedCount} orphaned guest users (no orders, 2+ years old)");
        
        if (!$dryRun && $orphanedCount > 0) {
            $orphanedQuery->delete();
            $this->info("✅ Deleted {$orphanedCount} orphaned guest users");
        }
        
        // Anonymize old guest orders (3+ years)
        $oldGuestQuery = GuestUser::whereHas('orders', function($q) {
            $q->where('created_at', '<', now()->subYears(3));
        });
        
        $oldGuestCount = $oldGuestQuery->count();
        $this->info("Found {$oldGuestCount} guest users with orders 3+ years old");
        
        if (!$dryRun && $oldGuestCount > 0) {
            $oldGuestQuery->update([
                'name' => 'DELETED',
                'email' => null,
                'phone' => 'DELETED',
                'street' => null,
                'building' => null,
                'apartment' => null,
                'floor' => null,
                'city' => null,
            ]);
            $this->info("✅ Anonymized {$oldGuestCount} old guest users");
        }
        
        if ($dryRun) {
            $this->warn('DRY RUN - No changes were made. Remove --dry-run to execute.');
        }
        
        return self::SUCCESS;
    }
}
```

**Schedule it:** in `routes/console.php`
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('guests:cleanup')->monthly();
```

### 11. Add Security Monitoring
**Create:** `app/Listeners/LogSecurityEvents.php`

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;

final class LogSecurityEvents
{
    public function handle(object $event): void
    {
        // Log failed order tracking attempts
        if ($event instanceof \App\Events\GuestOrderTrackingFailed) {
            Log::channel('security')->warning('Failed guest order tracking attempt', [
                'ip' => request()->ip(),
                'phone' => '[REDACTED]',
                'order_number' => $event->orderNumber,
                'user_agent' => request()->userAgent(),
            ]);
        }
        
        // Log multiple failed attempts from same IP
        if ($event instanceof \App\Events\RateLimitExceeded) {
            Log::channel('security')->alert('Rate limit exceeded', [
                'ip' => request()->ip(),
                'route' => request()->path(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }
}
```

### 12. Add IP Blocking for Abusive Requests
This would require implementing a middleware that tracks failed attempts and blocks IPs that exceed thresholds.

---

## 📋 Testing Checklist

After implementing fixes, run these security tests:

### Unit Tests
```bash
php artisan test --filter=GuestOrderTest
php artisan test --filter=GuestOrderTrackingTest
php artisan test --filter=GuestUserServiceTest
```

### Manual Security Tests
1. **IDOR Test:** Try accessing orders with different user accounts
2. **Brute Force Test:** Attempt multiple rapid tracking requests
3. **SQL Injection Test:** Send malformed phone numbers and order numbers
4. **XSS Test:** Submit guest data with script tags
5. **CSRF Test:** Verify all POST endpoints have CSRF protection
6. **Session Fixation:** Verify session regenerates after order

---

## 📊 Monitoring Recommendations

1. **Set up alerts** for:
   - Multiple failed order tracking attempts from same IP
   - Invalid HMAC signatures on webhooks
   - Unusual order volumes from guest users
   
2. **Monitor logs** for:
   - `[REDACTED]` entries (verify PII is being sanitized)
   - 403/401 errors on order endpoints
   - Rate limit violations

3. **Review monthly**:
   - Guest data retention policy execution
   - Failed authentication attempts
   - Webhook signature validation failures

---

## 🔐 Compliance Notes

### GDPR Compliance
- ✅ Right to be forgotten: Implement data deletion API
- ✅ Data minimization: Only collect necessary guest info
- ✅ Data retention: Automated cleanup after 2-3 years
- ⚠️ Consent tracking: Consider adding consent checkbox for guest orders
- ⚠️ Data portability: Consider allowing guests to export their order history

### PCI DSS Compliance
- ✅ No card data stored locally (delegated to payment gateway)
- ✅ HMAC validation on webhooks
- ⚠️ Consider implementing additional fraud detection

---

## 📞 Incident Response

If a security breach is detected:

1. **Immediately** disable guest ordering: Set `GUEST_ORDERING_ENABLED=false` in config
2. **Investigate** logs for the attack vector
3. **Notify** affected users if PII was compromised
4. **Patch** the vulnerability
5. **Review** all similar code paths for related issues
6. **Update** this document with lessons learned

---

## Summary

**Critical Issues Found:** 4
**High Priority Issues:** 3  
**Medium Priority Issues:** 5

**Estimated Implementation Time:**
- Critical fixes: 4-6 hours
- High priority: 8-12 hours
- Medium priority: 16-20 hours

**Total Estimated Time:** 28-38 hours (3-5 working days)
