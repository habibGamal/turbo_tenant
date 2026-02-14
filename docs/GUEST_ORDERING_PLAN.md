# Guest Ordering Implementation Plan

**Project:** Turbo Tenant Restaurant Ordering System  
**Feature:** Guest Ordering Support  
**Date:** February 12, 2026  
**Status:** Planning Phase  

---

## 📋 Executive Summary

Add support for guest (unauthenticated) users to place orders without creating an account. This builds on the existing guest cart functionality to provide a complete guest checkout experience.

### Key Objectives
- ✅ Allow guests to place orders without registration
- ✅ Collect necessary contact information at checkout
- ✅ Support all payment methods (COD, Card, Wallet, Kiosk, Bank Transfer)
- ✅ Enable guest order tracking via email/phone + order number
- ✅ Maintain backward compatibility with authenticated users
- ✅ Follow Laravel 12 best practices

---

## 🔍 Current State Analysis

### What Works ✅
- **Guest Cart:** Fully functional session-based cart (`CartService`)
- **Payment Integration:** Paymob & Kashier gateways ready
- **Order Processing:** Robust `PlaceOrderService` with payment handling
- **Multi-tenant:** Architecture supports tenant isolation

### Blockers ❌
1. **Authentication Required:** Order routes behind `auth` middleware
2. **User Dependency:** `PlaceOrderService::placeOrder()` requires `User` object
3. **Database Constraint:** `orders.user_id` is NOT NULL with foreign key
4. **No Guest UI:** Missing checkout form for guest contact info
5. **No Tracking:** No way for guests to view order status

---

## 🏗️ Proposed Architecture

### Recommended Approach: **Guest Users Table**

Create a separate `guest_users` table to store guest contact information. This approach provides:
- ✅ Clean separation of concerns
- ✅ Easy conversion to registered users
- ✅ Better data validation
- ✅ Simpler order queries
- ✅ Future-proof for guest profiles

**Alternative (Not Recommended):** Nullable `user_id` with JSON guest data would complicate queries and validation.

### Data Flow

```
Guest Checkout
    ↓
Collect Contact Info (name, email, phone)
    ↓
Create/Find GuestUser Record
    ↓
Link Order to GuestUser
    ↓
Process Payment
    ↓
Send Tracking Info (Email + SMS)
```

---

## 📊 Database Changes

### 1. Create `guest_users` Table

**Migration:** `2026_02_12_create_guest_users_table.php`

```php
Schema::create('guest_users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->nullable();
    $table->string('phone');
    $table->string('phone_country_code')->default('+20');
    
    // Optional delivery address for guests
    $table->string('street')->nullable();
    $table->string('building')->nullable();
    $table->string('floor')->nullable();
    $table->string('apartment')->nullable();
    $table->string('city')->nullable();
    $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
    
    $table->timestamps();
    
    // Indexes for fast lookup
    $table->index(['phone', 'phone_country_code'], 'idx_guest_phone');
    $table->index('email', 'idx_guest_email');
});
```

### 2. Update `orders` Table

**Migration:** `2026_02_12_add_guest_support_to_orders_table.php`

```php
Schema::table('orders', function (Blueprint $table) {
    // Make user_id nullable
    $table->foreignId('user_id')->nullable()->change();
    
    // Add guest_user_id
    $table->foreignId('guest_user_id')
        ->nullable()
        ->after('user_id')
        ->constrained('guest_users')
        ->nullOnDelete();
    
    // Add constraint: must have either user_id OR guest_user_id
    // Note: We'll enforce this in application logic
    
    $table->index('guest_user_id', 'idx_orders_guest_user');
});
```

### 3. Update `Order` Model

Add relationship and helper methods:

```php
// Relations
public function guestUser(): BelongsTo
{
    return $this->belongsTo(GuestUser::class);
}

// Helpers
public function isGuestOrder(): bool
{
    return $this->guest_user_id !== null;
}

public function getCustomerName(): string
{
    return $this->user?->name ?? $this->guestUser?->name ?? 'Unknown';
}

public function getCustomerPhone(): ?string
{
    return $this->user?->phone ?? $this->guestUser?->phone;
}

public function getCustomerEmail(): ?string
{
    return $this->user?->email ?? $this->guestUser?->email;
}
```

---

## 💻 Backend Implementation

### Phase 1: Core Service Layer

#### 1.1 Create `GuestUser` Model

**File:** `app/Models/GuestUser.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GuestUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_country_code',
        'street',
        'building',
        'floor',
        'apartment',
        'city',
        'area_id',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
    
    public function getFullPhoneAttribute(): string
    {
        return $this->phone_country_code . $this->phone;
    }
}
```

#### 1.2 Create `GuestUserService`

**File:** `app/Services/GuestUserService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GuestUser;

final class GuestUserService
{
    /**
     * Find or create a guest user by phone/email
     */
    public function findOrCreate(array $guestData): GuestUser
    {
        $phone = $guestData['phone'];
        $phoneCountryCode = $guestData['phone_country_code'] ?? '+20';
        
        // Try to find existing guest by phone
        $guestUser = GuestUser::where('phone', $phone)
            ->where('phone_country_code', $phoneCountryCode)
            ->first();
        
        if ($guestUser) {
            // Update info if provided
            $guestUser->update(array_filter([
                'name' => $guestData['name'] ?? $guestUser->name,
                'email' => $guestData['email'] ?? $guestUser->email,
                'street' => $guestData['street'] ?? $guestUser->street,
                'building' => $guestData['building'] ?? $guestUser->building,
                'floor' => $guestData['floor'] ?? $guestUser->floor,
                'apartment' => $guestData['apartment'] ?? $guestUser->apartment,
                'city' => $guestData['city'] ?? $guestUser->city,
                'area_id' => $guestData['area_id'] ?? $guestUser->area_id,
            ]));
            
            return $guestUser;
        }
        
        // Create new guest user
        return GuestUser::create($guestData);
    }
    
    /**
     * Convert guest user to registered user
     */
    public function convertToUser(GuestUser $guestUser, User $user): void
    {
        // Transfer all guest orders to the user
        $guestUser->orders()->update([
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);
        
        // Optionally delete guest record
        // $guestUser->delete();
    }
}
```

#### 1.3 Update `PlaceOrderService`

**File:** `app/Services/PlaceOrderService.php`

**Key Architecture Change:**
- The `OrderController` now handles guest user creation/finding **before** calling `placeOrder()`
- `placeOrder()` always receives either a `User` or `GuestUser` instance (never null)
- No need to inject `GuestUserService` into `PlaceOrderService`
- Use `instanceof` to determine user type and set appropriate database fields

**Changes Required:**

1. **Modify `placeOrder()` signature** to accept `User|GuestUser` (union type)
2. **Update `createOrder()` to handle both user types and set appropriate IDs**
3. **Update `prepareBillingData()` for guest users**
4. **Update cart retrieval logic** to handle GuestUser instances

```php
// New signature
public function placeOrder(
    User|GuestUser $user,  // Accept either User or GuestUser
    int $branchId,
    PaymentMethod $paymentMethod,
    ?int $addressId = null,
    ?int $couponId = null,
    ?string $note = null,
    string $type = 'web_delivery',
    array $billingData = []
): array

// Inside placeOrder():
// Determine if this is a guest order
$isGuest = $user instanceof GuestUser;

// Prepare billing data based on user type
if ($isGuest) {
    $billingData = array_merge($billingData, [
        'first_name' => $user->name,
        'email' => $user->email ?? 'guest@example.com',
        'phone_number' => $user->full_phone,
        'street' => $user->street,
        'building' => $user->building,
        'floor' => $user->floor,
        'apartment' => $user->apartment,
        'city' => $user->city,
    ]);
}

// Get cart (CartService already handles both User and null for guests)
// For GuestUser, pass null to use session-based cart
$cartUser = $isGuest ? null : $user;
$cart = $this->cartService->getCart($cartUser);
```

**Update `createOrder()` method:**

```php
private function createOrder(
    User|GuestUser $user,
    int $branchId,
    ?int $addressId,
    ?int $couponId,
    ?string $note,
    string $type,
    array $totals,
    PaymentMethod $paymentMethod
): Order {
    $isGuest = $user instanceof GuestUser;
    
    return Order::create([
        'order_number' => $this->generateOrderNumber(),
        'user_id' => $isGuest ? null : $user->id,
        'guest_user_id' => $isGuest ? $user->id : null,
        'branch_id' => $branchId,
        'address_id' => $addressId,
        'coupon_id' => $couponId,
        'note' => $note,
        'type' => $type,
        'status' => 'pending',
        'payment_status' => PaymentStatus::PENDING,
        'payment_method' => $paymentMethod,
        'sub_total' => $totals['sub_total'],
        'discount' => $totals['discount'],
        'tax' => $totals['tax'],
        'service' => $totals['service'],
        'delivery_fee' => $totals['delivery_fee'],
        'total' => $totals['total'],
    ]);
}
```

**Update `prepareBillingData()` method:**

```php
private function prepareBillingData(User|GuestUser $user, ?int $addressId, array $billingData): array
{
    $isGuest = $user instanceof GuestUser;
    
    if ($isGuest) {
        // For guest users, use GuestUser data
        return [
            'first_name' => $billingData['first_name'] ?? $user->name ?? 'Guest',
            'last_name' => $billingData['last_name'] ?? '',
            'email' => $billingData['email'] ?? $user->email ?? 'guest@example.com',
            'phone_number' => $billingData['phone_number'] ?? $user->full_phone,
            'apartment' => $billingData['apartment'] ?? $user->apartment ?? 'NA',
            'floor' => $billingData['floor'] ?? $user->floor ?? 'NA',
            'street' => $billingData['street'] ?? $user->street ?? 'NA',
            'building' => $billingData['building'] ?? $user->building ?? 'NA',
            'city' => $billingData['city'] ?? $user->city ?? 'Cairo',
            'country' => $billingData['country'] ?? 'EG',
            'postal_code' => $billingData['postal_code'] ?? 'NA',
        ];
    }
    
    // For registered users, use Address or User data
    $address = $addressId ? Address::find($addressId) : null;

    return [
        'first_name' => $billingData['first_name'] ?? $user->name ?? 'NA',
        'last_name' => $billingData['last_name'] ?? 'NA',
        'email' => $billingData['email'] ?? $user->email ?? 'customer@example.com',
        'phone_number' => $billingData['phone_number'] ?? $user->phone ?? '+201000000000',
        'apartment' => $billingData['apartment'] ?? $address?->apartment ?? 'NA',
        'floor' => $billingData['floor'] ?? $address?->floor ?? 'NA',
        'street' => $billingData['street'] ?? $address?->street ?? 'NA',
        'building' => $billingData['building'] ?? $address?->building ?? 'NA',
        'city' => $billingData['city'] ?? $address?->city ?? 'Cairo',
        'country' => $billingData['country'] ?? 'EG',
        'postal_code' => $billingData['postal_code'] ?? 'NA',
    ];
}
```

### Phase 2: Controller & Route Updates

#### 2.1 Update `OrderController`

**File:** `app/Http/Controllers/OrderController.php`

```php
public function placeOrder(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'branch_id' => 'required|integer|exists:branches,id',
        'payment_method' => 'required|string|in:card,wallet,cod,kiosk,bank_transfer,credit',
        'address_id' => 'nullable|integer|exists:addresses,id',
        'coupon_id' => 'nullable|integer|exists:coupons,id',
        'note' => 'nullable|string|max:1000',
        'type' => 'required|in:web_delivery,web_takeaway,pos',
        
        // Guest user data (required if not authenticated)
        'guest_data' => 'required_without:auth|array',
        'guest_data.name' => 'required_with:guest_data|string|max:255',
        'guest_data.phone' => 'required_with:guest_data|string|max:20',
        'guest_data.phone_country_code' => 'nullable|string|max:5',
        'guest_data.email' => 'nullable|email|max:255',
        'guest_data.street' => 'nullable|string|max:255',
        'guest_data.building' => 'nullable|string|max:255',
        'guest_data.floor' => 'nullable|string|max:255',
        'guest_data.apartment' => 'nullable|string|max:255',
        'guest_data.city' => 'nullable|string|max:255',
        'guest_data.area_id' => 'nullable|integer|exists:areas,id',
        
        // Billing data
        'billing_data' => 'nullable|array',
        // ... existing billing validation
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    $user = Auth::user();

    // If not authenticated, create/find guest user
    if (!$user) {
        $guestData = $request->input('guest_data');
        if (!$guestData) {
            return response()->json([
                'success' => false,
                'errors' => ['guest_data' => ['Guest information is required']],
            ], 422);
        }
        
        // Find or create the guest user BEFORE placing order
        $guestUserService = app(GuestUserService::class);
        $user = $guestUserService->findOrCreate($guestData);
    }

    try {
        $paymentMethod = PaymentMethod::from($request->input('payment_method'));

        $result = $this->placeOrderService->placeOrder(
            user: $user,  // Now always a User or GuestUser instance
            branchId: $request->input('branch_id'),
            paymentMethod: $paymentMethod,
            addressId: $request->input('address_id'),
            couponId: $request->input('coupon_id'),
            note: $request->input('note'),
            type: $request->input('type'),
            billingData: $request->input('billing_data', [])
        );

        // Rest of the method remains the same
        // ...
    } catch (Exception $e) {
        // Error handling
    }
}
```

#### 2.2 Update `checkout()` Method

```php
public function checkout(): Response
{
    $user = Auth::user();
    $cart = $this->cartService->getCart($user);

    // Get areas/governorates for guest address selection
    $areas = Area::with('governorate')->get();
    $branches = Branch::where('active', true)->get();

    return Inertia::render('Checkout/Index', [
        'cart' => $cart,
        'areas' => $areas,
        'branches' => $branches,
        'user_addresses' => $user ? $user->addresses : [],
        'is_guest' => !$user,
    ]);
}
```

#### 2.3 Create Guest Order Tracking

**New Method in OrderController:**

```php
public function trackGuestOrder(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'order_number' => 'required|string',
        'phone' => 'required|string',
        'phone_country_code' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    $orderNumber = $request->input('order_number');
    $phone = $request->input('phone');
    $phoneCountryCode = $request->input('phone_country_code', '+20');

    // Find guest user
    $guestUser = GuestUser::where('phone', $phone)
        ->where('phone_country_code', $phoneCountryCode)
        ->first();

    if (!$guestUser) {
        return response()->json([
            'success' => false,
            'error' => 'No orders found for this phone number',
        ], 404);
    }

    // Find order
    $order = Order::with(['items.extras', 'branch', 'guestUser'])
        ->where('order_number', $orderNumber)
        ->where('guest_user_id', $guestUser->id)
        ->first();

    if (!$order) {
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

#### 2.4 Update Routes

**File:** `routes/tenant.php`

```php
// Guest-accessible routes (remove auth middleware)
Route::get('/checkout', [OrderController::class, 'checkout'])->name('checkout');
Route::post('/orders/place', [OrderController::class, 'placeOrder'])->name('orders.place');
Route::get('/orders/{orderId}/payment/callback', [OrderController::class, 'paymentCallback'])->name('orders.payment.callback');

// Guest order tracking
Route::post('/orders/track', [OrderController::class, 'trackGuestOrder'])->name('orders.track');
Route::get('/track-order', function () {
    return Inertia::render('Orders/Track');
})->name('orders.track.page');

// Authenticated order history
Route::middleware('auth')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderId}', [OrderController::class, 'show'])->name('orders.show');
});
```

---

## 🎨 Frontend Implementation

### Phase 3: React Components

#### 3.1 Update Checkout Component

**File:** `resources/js/Pages/Checkout/Index.tsx`

Add guest checkout form section:

```typescript
import { useState } from 'react';
import { useForm } from '@inertiajs/react';

interface CheckoutProps {
    cart: Cart;
    areas: Area[];
    branches: Branch[];
    user_addresses: Address[];
    is_guest: boolean;
}

export default function Checkout({ cart, areas, branches, user_addresses, is_guest }: CheckoutProps) {
    const { data, setData, post, processing, errors } = useForm({
        branch_id: '',
        payment_method: 'cod',
        address_id: '',
        coupon_id: '',
        note: '',
        type: 'web_delivery',
        guest_data: is_guest ? {
            name: '',
            phone: '',
            phone_country_code: '+20',
            email: '',
            street: '',
            building: '',
            floor: '',
            apartment: '',
            city: '',
            area_id: '',
        } : undefined,
        billing_data: {},
    });

    return (
        <div className="checkout-container">
            {is_guest && (
                <section className="guest-info-section">
                    <h2 className="text-xl font-bold mb-4">
                        {t('checkout.contact_information')}
                    </h2>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>{t('checkout.name')}</label>
                            <input
                                type="text"
                                value={data.guest_data?.name}
                                onChange={(e) => setData('guest_data', {
                                    ...data.guest_data!,
                                    name: e.target.value
                                })}
                                required
                            />
                            {errors['guest_data.name'] && (
                                <p className="text-red-500 text-sm">{errors['guest_data.name']}</p>
                            )}
                        </div>

                        <div>
                            <label>{t('checkout.phone')}</label>
                            <div className="flex gap-2">
                                <select
                                    value={data.guest_data?.phone_country_code}
                                    onChange={(e) => setData('guest_data', {
                                        ...data.guest_data!,
                                        phone_country_code: e.target.value
                                    })}
                                    className="w-24"
                                >
                                    <option value="+20">+20</option>
                                    <option value="+966">+966</option>
                                </select>
                                <input
                                    type="tel"
                                    value={data.guest_data?.phone}
                                    onChange={(e) => setData('guest_data', {
                                        ...data.guest_data!,
                                        phone: e.target.value
                                    })}
                                    required
                                />
                            </div>
                            {errors['guest_data.phone'] && (
                                <p className="text-red-500 text-sm">{errors['guest_data.phone']}</p>
                            )}
                        </div>

                        <div>
                            <label>{t('checkout.email')} ({t('optional')})</label>
                            <input
                                type="email"
                                value={data.guest_data?.email}
                                onChange={(e) => setData('guest_data', {
                                    ...data.guest_data!,
                                    email: e.target.value
                                })}
                            />
                        </div>

                        {/* Delivery address fields if type is delivery */}
                        {data.type === 'web_delivery' && (
                            <>
                                <div>
                                    <label>{t('checkout.area')}</label>
                                    <select
                                        value={data.guest_data?.area_id}
                                        onChange={(e) => setData('guest_data', {
                                            ...data.guest_data!,
                                            area_id: e.target.value
                                        })}
                                        required
                                    >
                                        <option value="">{t('checkout.select_area')}</option>
                                        {areas.map((area) => (
                                            <option key={area.id} value={area.id}>
                                                {area.name} ({area.governorate.name})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label>{t('checkout.street')}</label>
                                    <input
                                        type="text"
                                        value={data.guest_data?.street}
                                        onChange={(e) => setData('guest_data', {
                                            ...data.guest_data!,
                                            street: e.target.value
                                        })}
                                        required
                                    />
                                </div>

                                <div>
                                    <label>{t('checkout.building')}</label>
                                    <input
                                        type="text"
                                        value={data.guest_data?.building}
                                        onChange={(e) => setData('guest_data', {
                                            ...data.guest_data!,
                                            building: e.target.value
                                        })}
                                    />
                                </div>

                                <div>
                                    <label>{t('checkout.floor')}</label>
                                    <input
                                        type="text"
                                        value={data.guest_data?.floor}
                                        onChange={(e) => setData('guest_data', {
                                            ...data.guest_data!,
                                            floor: e.target.value
                                        })}
                                    />
                                </div>

                                <div>
                                    <label>{t('checkout.apartment')}</label>
                                    <input
                                        type="text"
                                        value={data.guest_data?.apartment}
                                        onChange={(e) => setData('guest_data', {
                                            ...data.guest_data!,
                                            apartment: e.target.value
                                        })}
                                    />
                                </div>
                            </>
                        )}
                    </div>
                </section>
            )}

            {/* Rest of checkout form (payment, branch selection, etc.) */}
        </div>
    );
}
```

#### 3.2 Create Order Tracking Page

**New File:** `resources/js/Pages/Orders/Track.tsx`

```typescript
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';

export default function TrackOrder() {
    const { t } = useTranslation();
    const [orderNumber, setOrderNumber] = useState('');
    const [phone, setPhone] = useState('');
    const [phoneCountryCode, setPhoneCountryCode] = useState('+20');
    const [order, setOrder] = useState<Order | null>(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleTrack = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError('');
        setOrder(null);

        try {
            const response = await axios.post(route('orders.track'), {
                order_number: orderNumber,
                phone,
                phone_country_code: phoneCountryCode,
            });

            if (response.data.success) {
                setOrder(response.data.order);
            }
        } catch (err: any) {
            setError(err.response?.data?.error || t('orders.track_error'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="track-order-container max-w-2xl mx-auto p-6">
            <h1 className="text-3xl font-bold mb-8">{t('orders.track_title')}</h1>

            <form onSubmit={handleTrack} className="space-y-4">
                <div>
                    <label className="block mb-2">{t('orders.order_number')}</label>
                    <input
                        type="text"
                        value={orderNumber}
                        onChange={(e) => setOrderNumber(e.target.value)}
                        required
                        className="w-full px-4 py-2 border rounded"
                        placeholder="ORD-XXXXXXXX"
                    />
                </div>

                <div>
                    <label className="block mb-2">{t('orders.phone')}</label>
                    <div className="flex gap-2">
                        <select
                            value={phoneCountryCode}
                            onChange={(e) => setPhoneCountryCode(e.target.value)}
                            className="w-24 px-2 py-2 border rounded"
                        >
                            <option value="+20">+20</option>
                            <option value="+966">+966</option>
                        </select>
                        <input
                            type="tel"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            required
                            className="flex-1 px-4 py-2 border rounded"
                            placeholder="1234567890"
                        />
                    </div>
                </div>

                {error && (
                    <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        {error}
                    </div>
                )}

                <button
                    type="submit"
                    disabled={loading}
                    className="w-full bg-primary text-white py-3 rounded font-bold hover:bg-primary-dark disabled:opacity-50"
                >
                    {loading ? t('orders.tracking') : t('orders.track_button')}
                </button>
            </form>

            {order && (
                <div className="mt-8 border rounded-lg p-6">
                    <h2 className="text-2xl font-bold mb-4">
                        {t('orders.order')} #{order.order_number}
                    </h2>
                    
                    <div className="space-y-3">
                        <p><strong>{t('orders.status')}:</strong> {t(`orders.status_${order.status}`)}</p>
                        <p><strong>{t('orders.payment_status')}:</strong> {t(`orders.payment_${order.payment_status}`)}</p>
                        <p><strong>{t('orders.total')}:</strong> {formatCurrency(order.total)}</p>
                        <p><strong>{t('orders.created_at')}:</strong> {formatDate(order.created_at)}</p>
                        
                        <div className="mt-6">
                            <h3 className="font-bold mb-2">{t('orders.items')}</h3>
                            <ul className="space-y-2">
                                {order.items.map((item) => (
                                    <li key={item.id} className="border-b pb-2">
                                        <div className="flex justify-between">
                                            <span>{item.product_name} x{item.quantity}</span>
                                            <span>{formatCurrency(item.total)}</span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
```

---

## 🔒 Security Measures

### Rate Limiting

**File:** `bootstrap/app.php` or route middleware

```php
Route::middleware(['throttle:orders'])->group(function () {
    Route::post('/orders/place', [OrderController::class, 'placeOrder']);
});

// In RouteServiceProvider or app config:
RateLimiter::for('orders', function (Request $request) {
    $identifier = $request->user()?->id ?? $request->ip();
    
    // 10 orders per hour for guests, 20 for authenticated users
    $limit = $request->user() ? 20 : 10;
    
    return Limit::perHour($limit)->by($identifier);
});
```

### Validation

1. **Phone Number Validation:** Use libphonenumber for proper validation
2. **Email Verification:** Send confirmation emails
3. **Address Validation:** Ensure area_id exists and is active

### Data Privacy

1. **GDPR Compliance:** Allow guests to request data deletion
2. **Data Retention:** Auto-delete guest records after X months of inactivity
3. **Email Opt-in:** Ask permission for marketing emails

---

## 🧪 Testing Requirements

### Unit Tests

**File:** `tests/Unit/GuestUserServiceTest.php`

```php
it('creates a new guest user', function () {
    $guestData = [
        'name' => 'John Doe',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
        'email' => 'john@example.com',
    ];

    $service = app(GuestUserService::class);
    $guestUser = $service->findOrCreate($guestData);

    expect($guestUser)->toBeInstanceOf(GuestUser::class);
    expect($guestUser->name)->toBe('John Doe');
    expect($guestUser->phone)->toBe('1234567890');
});

it('finds existing guest user by phone', function () {
    $guestUser = GuestUser::factory()->create([
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $service = app(GuestUserService::class);
    $found = $service->findOrCreate([
        'name' => 'Updated Name',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    expect($found->id)->toBe($guestUser->id);
    expect($found->name)->toBe('Updated Name'); // Should update
});
```

### Feature Tests

**File:** `tests/Feature/GuestOrderTest.php`

```php
it('allows guest to place COD order', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create();
    
    // Add to guest cart
    $response = $this->postJson(route('cart.add'), [
        'product_id' => $product->id,
        'variant_id' => null,
        'quantity' => 1,
    ]);
    
    $response->assertSuccessful();
    
    // Place order as guest
    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_takeaway',
        'guest_data' => [
            'name' => 'Guest Customer',
            'phone' => '1234567890',
            'phone_country_code' => '+20',
            'email' => 'guest@test.com',
        ],
    ]);
    
    $response->assertSuccessful();
    $response->assertJsonStructure([
        'success',
        'redirect_url',
        'order_id',
    ]);
    
    $this->assertDatabaseHas('orders', [
        'order_number' => $response->json('order.order_number'),
        'user_id' => null,
    ]);
    
    $this->assertDatabaseHas('guest_users', [
        'phone' => '1234567890',
        'name' => 'Guest Customer',
    ]);
});

it('allows guest to track order', function () {
    $guestUser = GuestUser::factory()->create([
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);
    
    $order = Order::factory()->create([
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
    ]);
    
    $response = $this->postJson(route('orders.track'), [
        'order_number' => $order->order_number,
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);
    
    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'order' => [
            'id' => $order->id,
            'order_number' => $order->order_number,
        ],
    ]);
});

it('prevents guest from accessing other guest orders', function () {
    $guestUser1 = GuestUser::factory()->create(['phone' => '1111111111']);
    $guestUser2 = GuestUser::factory()->create(['phone' => '2222222222']);
    
    $order = Order::factory()->create([
        'guest_user_id' => $guestUser1->id,
    ]);
    
    $response = $this->postJson(route('orders.track'), [
        'order_number' => $order->order_number,
        'phone' => '2222222222',
    ]);
    
    $response->assertNotFound();
});
```

### Browser Tests

**File:** `tests/Browser/GuestCheckoutTest.php`

```php
it('guest can complete checkout flow', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create();
    
    visit('/')
        ->click('Products')
        ->assertSee($product->name)
        ->click('Add to Cart')
        ->assertSee('Item added to cart')
        ->click('Cart')
        ->click('Checkout')
        ->fill('guest_data.name', 'Test Guest')
        ->fill('guest_data.phone', '1234567890')
        ->fill('guest_data.email', 'guest@test.com')
        ->select('payment_method', 'cod')
        ->select('branch_id', $branch->id)
        ->click('Place Order')
        ->assertSee('Order placed successfully');
});
```

---

## 📅 Implementation Phases

### Phase 1: Database & Models (Day 1)
- [ ] Create `guest_users` migration
- [ ] Update `orders` table migration
- [ ] Create `GuestUser` model
- [ ] Update `Order` model with relationships
- [ ] Create `GuestUser` factory
- [ ] Run migrations in development

### Phase 2: Backend Services (Days 2-3)
- [ ] Create `GuestUserService`
- [ ] Update `PlaceOrderService`
  - [ ] Modify `placeOrder()` signature
  - [ ] Update `createOrder()` method
  - [ ] Update `prepareBillingData()`
  - [ ] Handle null user scenarios
- [ ] Update `CartService` (verify null user support)
- [ ] Add unit tests for services

### Phase 3: Controllers & Routes (Day 4)
- [ ] Update `OrderController::placeOrder()`
- [ ] Update `OrderController::checkout()`
- [ ] Create `OrderController::trackGuestOrder()`
- [ ] Update routes in `tenant.php`
- [ ] Add rate limiting
- [ ] Add feature tests

### Phase 4: Frontend UI (Days 5-6)
- [ ] Update Checkout page
  - [ ] Add guest info form
  - [ ] Add validation
  - [ ] Handle submission
- [ ] Create Track Order page
- [ ] Add translations (EN/AR)
- [ ] Test RTL layout
- [ ] Mobile responsive design
- [ ] Add browser tests

### Phase 5: Filament Admin (Day 7)
- [ ] Create `GuestUserResource`
- [ ] Update `OrderResource` to show guest orders
- [ ] Add filters for guest vs user orders
- [ ] Add guest-to-user conversion action

### Phase 6: Testing & Polish (Days 8-9)
- [ ] Run full test suite
- [ ] Fix failing tests
- [ ] Code formatting (Pint)
- [ ] Static analysis (PHPStan)
- [ ] Manual QA testing
- [ ] Security review

### Phase 7: Deployment (Day 10)
- [ ] Deploy to staging
- [ ] Test on staging
- [ ] Deploy to production
- [ ] Monitor for errors
- [ ] Collect feedback

---

## ⚠️ Potential Challenges & Solutions

### Challenge 1: Duplicate Guest Users
**Problem:** Same person with multiple phone numbers/emails  
**Solution:** 
- Use phone as primary identifier
- Merge functionality in admin panel
- Send verification codes (future)

### Challenge 2: Cart Persistence
**Problem:** Guest cart lost if session expires  
**Solution:**
- Store cart in localStorage as backup
- Show warning when session near expiry
- Allow cart recovery via phone/email

### Challenge 3: Address Management
**Problem:** Guests can't save multiple addresses  
**Solution:**
- Store last used address in `guest_users`
- Future: Allow address book for returning guests
- Prompt to register after order

### Challenge 4: Payment Gateway Issues
**Problem:** Guest orders failing webhook validation  
**Solution:**
- Ensure guest_user data in billing_data
- Test all payment methods thoroughly
- Add comprehensive logging

### Challenge 5: Conversion to Registered Users
**Problem:** Guest wants account after ordering  
**Solution:**
- Auto-detect email match on registration
- Offer account creation on order confirmation page
- Transfer orders via `GuestUserService::convertToUser()`

---

## 📊 Success Metrics

### Technical Metrics
- [ ] 100% test coverage for guest ordering flow
- [ ] <500ms checkout page load time
- [ ] <2s order placement time
- [ ] Zero database constraint violations
- [ ] Pass PHPStan level 8
- [ ] Pass all Pint checks

### Business Metrics
- [ ] % of orders from guests vs registered users
- [ ] Guest-to-user conversion rate
- [ ] Average order value: guest vs user
- [ ] Order completion rate
- [ ] Payment failure rate by user type

---

## 🔄 Future Enhancements

### Phase 2 Features
1. **Email/SMS Notifications:** Order confirmations to guest email/phone
2. **Guest Account Creation Prompt:** After successful order
3. **Address Book:** Allow guests to save multiple addresses
4. **Order History:** Via email link without registration
5. **Phone Verification:** OTP for guest checkouts
6. **Social Login:** Quick checkout via Google/Facebook
7. **Guest Favorites:** Save favorite items (session/localStorage)

### Admin Enhancements
1. **Guest Analytics Dashboard:** Track guest behavior
2. **Bulk Guest-to-User Migration:** Convert guests to users
3. **Guest Cleanup Job:** Auto-delete old guest records
4. **Marketing Automation:** Email campaigns to guest emails

---

## 📚 Documentation Updates

### Files to Update
1. **README.md:** Add guest ordering to features
2. **API Documentation:** Document guest endpoints
3. **User Guide:** Guest checkout instructions
4. **Admin Guide:** Managing guest users in Filament

---

## ✅ Acceptance Criteria

### Must Have
- [x] Guests can add items to cart
- [ ] Guests can complete checkout without registration
- [ ] Guest contact info collected (name, phone, optional email)
- [ ] Guest orders stored with `guest_user_id`
- [ ] Guests can track orders via phone + order number
- [ ] All payment methods work for guests
- [ ] Backward compatibility with auth users
- [ ] Comprehensive test coverage (>90%)
- [ ] RTL/LTR support
- [ ] English/Arabic translations

### Nice to Have
- [ ] Email order confirmation
- [ ] SMS order confirmation
- [ ] Guest-to-user conversion flow
- [ ] Phone verification (OTP)

---

## 🎯 Rollout Strategy

### Development
1. Feature branch: `feature/guest-ordering`
2. Daily commits with clear messages
3. PR review before merge to `develop`

### Staging
1. Deploy to staging environment
2. QA testing (manual + automated)
3. Stakeholder review
4. Performance testing

### Production
1. Deploy during low-traffic window
2. Enable feature flag (if applicable)
3. Monitor error logs
4. Gradual rollout (10% → 50% → 100%)

### Rollback Plan
1. Keep old routes active for 48 hours
2. Database migrations are reversible
3. Feature flag to disable guest ordering
4. Alert on increased error rates

---

## 📞 Stakeholder Communication

### Key Stakeholders
- Product Manager
- Marketing Team (guest analytics)
- Customer Support (guest order tracking)
- DevOps (deployment)

### Communication Plan
- **Kickoff:** Share plan, get approval
- **Weekly Updates:** Progress reports
- **Demo:** Show staging environment
- **Training:** Customer support on guest orders
- **Launch:** Announce feature

---

**Document Version:** 1.0  
**Last Updated:** February 12, 2026  
**Next Review:** After Phase 1 completion
