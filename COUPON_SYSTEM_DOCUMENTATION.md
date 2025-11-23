# Comprehensive Coupon System Documentation

## Overview

This document describes the comprehensive coupon conditions logic implemented for the TurboTenant e-commerce system. The system supports various coupon types with flexible conditions including product/category restrictions, order value limits, shipping benefits, user restrictions, and time-based validations.

## Database Schema

### Migration: `add_conditions_to_coupons_table`

Added the following fields to the `coupons` table:
- `name` (string, nullable): User-friendly name for the coupon
- `description` (text, nullable): Detailed description of the coupon
- `conditions` (json, nullable): JSON column storing all coupon conditions

### Coupon Table Structure

```sql
- id
- code (unique, indexed)
- name
- description
- type ('percentage' | 'fixed')
- value
- expiry_date
- is_active (indexed)
- max_usage
- usage_count
- total_consumed
- conditions (JSON)
- created_at
- updated_at
```

## Conditions Schema

The `conditions` JSON column supports the following structure:

```json
{
  "min_order_total": 100.00,
  "max_order_total": 500.00,
  "applicable_to": {
    "type": "all|products|categories",
    "product_ids": [1, 2, 3],
    "category_ids": [1, 2]
  },
  "shipping": {
    "free_shipping": true,
    "free_shipping_threshold": 200.00,
    "applicable_governorates": [1, 2, 3],
    "applicable_areas": [1, 2, 3]
  },
  "usage_restrictions": {
    "first_order_only": false,
    "user_specific": false,
    "user_ids": []
  },
  "valid_days": [0, 1, 2, 3, 4, 5, 6],
  "valid_hours": {
    "start": "09:00",
    "end": "23:00"
  }
}
```

### Condition Fields Explained

#### Order Value Restrictions
- **min_order_total**: Minimum subtotal required to apply coupon
- **max_order_total**: Maximum subtotal allowed to apply coupon

#### Product/Category Applicability
- **applicable_to.type**:
  - `all`: Applies to all products
  - `products`: Applies only to specific products
  - `categories`: Applies only to products in specific categories
- **applicable_to.product_ids**: Array of product IDs when type is 'products'
- **applicable_to.category_ids**: Array of category IDs when type is 'categories'

#### Shipping Conditions
- **shipping.free_shipping**: Enable free shipping with this coupon
- **shipping.free_shipping_threshold**: Order total required for free shipping (optional)
- **shipping.applicable_governorates**: Coupon only valid for specific governorates
- **shipping.applicable_areas**: Coupon only valid for specific areas

#### User Restrictions
- **usage_restrictions.first_order_only**: Coupon only for users' first order
- **usage_restrictions.user_specific**: Limit coupon to specific users
- **usage_restrictions.user_ids**: Array of user IDs when user_specific is true

#### Time Restrictions
- **valid_days**: Array of valid days (0=Sunday, 6=Saturday), null means all days
- **valid_hours.start**: Valid from time (HH:mm format)
- **valid_hours.end**: Valid until time (HH:mm format)

## Service Layer

### CouponService

Located at: [app/Services/CouponService.php](app/Services/CouponService.php)

#### Main Methods

##### `validateCoupon()`
Validates if a coupon can be applied to an order.

```php
public function validateCoupon(
    Coupon $coupon,
    User $user,
    array $cartItems,
    float $subTotal,
    ?int $addressId = null,
    ?int $areaId = null,
    ?int $governorateId = null
): array
```

**Returns:**
```php
[
    'valid' => bool,
    'message' => string
]
```

**Validation Checks:**
1. Coupon is active
2. Not expired
3. Maximum usage not exceeded
4. Minimum order total met
5. Maximum order total not exceeded
6. Applicable to items in cart
7. Valid for shipping location
8. Meets user restrictions
9. Valid for current day/time

##### `calculateDiscount()`
Calculates the discount amount based on coupon type and applicable items.

```php
public function calculateDiscount(
    Coupon $coupon,
    array $cartItems,
    float $subTotal
): float
```

**Logic:**
- For `percentage` type: `(applicable_amount * value) / 100`
- For `fixed` type: `min(value, applicable_amount)`
- Only applies to applicable products/categories if specified

##### `calculateShippingFee()`
Calculates shipping fee considering coupon free shipping conditions.

```php
public function calculateShippingFee(
    ?Coupon $coupon,
    float $originalShippingFee,
    float $subTotal,
    float $discount
): float
```

**Logic:**
- Returns 0 if free shipping enabled and threshold met (or no threshold)
- Returns original fee otherwise

##### `applyCoupon()`
Increments coupon usage statistics.

```php
public function applyCoupon(Coupon $coupon, float $discountAmount): void
```

##### `findByCode()`
Finds an active coupon by code (case-insensitive).

```php
public function findByCode(string $code): ?Coupon
```

## Integration with Order System

### PlaceOrderService Updates

Located at: [app/Services/PlaceOrderService.php](app/Services/PlaceOrderService.php)

The `PlaceOrderService` has been updated to:

1. **Validate Coupon Before Order Creation**
   - Fetches address details for location validation
   - Validates coupon using `CouponService::validateCoupon()`
   - Returns error if validation fails

2. **Calculate Discounts**
   - Uses `CouponService::calculateDiscount()` for accurate discount calculation
   - Considers only applicable products/categories

3. **Calculate Shipping**
   - Uses `CouponService::calculateShippingFee()` for free shipping logic
   - Applies thresholds correctly

4. **Track Usage**
   - Increments usage count and total consumed after successful order
   - Only increments if discount > 0

## Filament Admin Interface

### Resource Files

- **Resource**: [app/Filament/Resources/Coupons/CouponResource.php](app/Filament/Resources/Coupons/CouponResource.php)
- **Form Schema**: [app/Filament/Resources/Coupons/Schemas/CouponForm.php](app/Filament/Resources/Coupons/Schemas/CouponForm.php)
- **Table Schema**: [app/Filament/Resources/Coupons/Tables/CouponsTable.php](app/Filament/Resources/Coupons/Tables/CouponsTable.php)

### Form Sections

1. **Basic Information**
   - Code (required, unique, auto-uppercase)
   - Name
   - Description

2. **Discount Configuration**
   - Type (percentage/fixed)
   - Value (with dynamic suffix)
   - Expiry date
   - Active status
   - Maximum usage limit

3. **Order Value Restrictions** (collapsible)
   - Minimum order total
   - Maximum order total

4. **Applicable Products/Categories** (collapsible)
   - Apply to: All/Specific Products/Specific Categories
   - Dynamic product/category selector

5. **Shipping Conditions** (collapsible)
   - Free shipping toggle
   - Free shipping threshold
   - Applicable governorates (multi-select)
   - Applicable areas (multi-select)

6. **User Restrictions** (collapsible)
   - First order only
   - Specific users only (with user selector)

7. **Time Restrictions** (collapsible)
   - Valid days of week (multi-select)
   - Valid hours (start/end time)

8. **Usage Statistics** (collapsible, collapsed by default)
   - Times used (read-only)
   - Total discount given (read-only)

### Table Columns

- Code (searchable, sortable, copyable)
- Name (searchable, sortable)
- Type (badge with color)
- Value (formatted with type suffix)
- Active status (icon)
- Expiry date (with color coding)
- Usage count (with max usage display)
- Total consumed (money format)
- Created at (toggleable, hidden by default)

### Filters

- Active/Inactive status
- Type (percentage/fixed)
- Expired/Active status

## Testing

### Test File

Located at: [tests/Unit/Services/CouponServiceTest.php](tests/Unit/Services/CouponServiceTest.php)

### Test Coverage

The test suite includes 22 comprehensive tests covering:

1. **Validation Tests**
   - Inactive coupon
   - Expired coupon
   - Max usage limit exceeded
   - Minimum order total not met
   - Maximum order total exceeded
   - Specific product restrictions
   - Specific category restrictions
   - Governorate restrictions
   - Area restrictions
   - First order only restriction
   - User-specific restrictions
   - Valid days restriction
   - Valid hours restriction

2. **Calculation Tests**
   - Percentage discount calculation
   - Fixed discount calculation
   - Fixed discount not exceeding subtotal
   - Discount for specific products only
   - Free shipping calculation
   - Free shipping with threshold

3. **Usage Tests**
   - Coupon usage increment
   - Find coupon by code (case-insensitive)
   - Valid coupon passing all checks

### Running Tests

```bash
php artisan test --filter=CouponServiceTest
```

## Usage Examples

### Example 1: Percentage Discount on All Products

```php
$coupon = Coupon::create([
    'code' => 'SAVE10',
    'name' => '10% Off Everything',
    'type' => 'percentage',
    'value' => 10,
    'expiry_date' => now()->addMonth(),
    'is_active' => true,
    'conditions' => [
        'min_order_total' => 50,
    ],
]);
```

### Example 2: Fixed Discount on Specific Category

```php
$coupon = Coupon::create([
    'code' => 'ELECTRONICS20',
    'name' => '20 EGP Off Electronics',
    'type' => 'fixed',
    'value' => 20,
    'expiry_date' => now()->addWeek(),
    'is_active' => true,
    'conditions' => [
        'applicable_to' => [
            'type' => 'categories',
            'category_ids' => [1, 2],  // Electronics categories
        ],
    ],
]);
```

### Example 3: Free Shipping Above Threshold

```php
$coupon = Coupon::create([
    'code' => 'FREESHIP200',
    'name' => 'Free Shipping on Orders Over 200',
    'type' => 'percentage',
    'value' => 0,  // No discount on products
    'expiry_date' => now()->addMonth(),
    'is_active' => true,
    'conditions' => [
        'shipping' => [
            'free_shipping' => true,
            'free_shipping_threshold' => 200,
        ],
    ],
]);
```

### Example 4: First Order Only

```php
$coupon = Coupon::create([
    'code' => 'WELCOME15',
    'name' => 'Welcome Coupon - 15% Off First Order',
    'type' => 'percentage',
    'value' => 15,
    'expiry_date' => now()->addYear(),
    'is_active' => true,
    'conditions' => [
        'usage_restrictions' => [
            'first_order_only' => true,
        ],
    ],
]);
```

### Example 5: Weekend Only Discount

```php
$coupon = Coupon::create([
    'code' => 'WEEKEND20',
    'name' => 'Weekend Special - 20% Off',
    'type' => 'percentage',
    'value' => 20,
    'expiry_date' => now()->addMonth(),
    'is_active' => true,
    'conditions' => [
        'valid_days' => [5, 6],  // Friday and Saturday
    ],
]);
```

### Example 6: Location-Specific Coupon

```php
$coupon = Coupon::create([
    'code' => 'CAIRO10',
    'name' => 'Cairo Only - 10% Off',
    'type' => 'percentage',
    'value' => 10,
    'expiry_date' => now()->addMonth(),
    'is_active' => true,
    'conditions' => [
        'shipping' => [
            'applicable_governorates' => [1],  // Cairo governorate
        ],
    ],
]);
```

## API Endpoints

### Apply Coupon During Checkout

The coupon is validated and applied through the existing order placement endpoint:

**Endpoint:** `POST /api/orders`

**Request:**
```json
{
    "branch_id": 1,
    "payment_method": "card",
    "address_id": 1,
    "coupon_id": 1,
    "note": "Optional note",
    "type": "web_delivery"
}
```

**Response (Success):**
```json
{
    "success": true,
    "order": {
        "id": 123,
        "sub_total": 200.00,
        "discount": 20.00,
        "tax": 25.20,
        "service": 0.00,
        "delivery_fee": 0.00,
        "total": 205.20
    }
}
```

**Response (Coupon Invalid):**
```json
{
    "success": false,
    "error": "Minimum order total of 100 required"
}
```

## Best Practices

1. **Always Validate Before Applying**
   - Never apply a coupon without validation
   - Check all conditions including time-based restrictions

2. **Handle Edge Cases**
   - Fixed discounts shouldn't exceed order total
   - Free shipping threshold calculated on subtotal after discount

3. **Track Usage Accurately**
   - Only increment usage after successful order
   - Track both count and total consumed amount

4. **User Experience**
   - Provide clear error messages for invalid coupons
   - Show coupon details and savings in checkout

5. **Security**
   - Validate coupon server-side, never trust client data
   - Check for expired or inactive coupons
   - Enforce usage limits strictly

## Future Enhancements

Potential improvements to consider:

1. **Per-User Usage Limits**
   - Limit how many times each user can use a coupon

2. **Combination Rules**
   - Allow/disallow combining with other coupons
   - Stackable discounts

3. **Minimum Quantity Requirements**
   - Require minimum quantity of specific products

4. **Buy X Get Y**
   - More complex promotional logic

5. **Coupon Groups**
   - Organize coupons into campaigns

6. **Auto-Apply Logic**
   - Automatically apply best available coupon

7. **Referral Coupons**
   - Link coupons to referral system

8. **Analytics Dashboard**
   - Track coupon performance
   - ROI calculations

## Troubleshooting

### Common Issues

**Issue:** Coupon not applying discount
- Check if coupon is active
- Verify expiry date hasn't passed
- Ensure minimum order total is met
- Confirm products in cart match applicable conditions

**Issue:** Free shipping not working
- Check if free_shipping is enabled
- Verify threshold is met (subtotal after discount)
- Ensure user's location matches governorate/area restrictions

**Issue:** Time-based restrictions not working
- Verify server timezone is correct
- Check valid_days uses correct day numbers (0=Sunday)
- Ensure valid_hours format is correct (HH:mm)

## Support

For questions or issues with the coupon system:
1. Review this documentation
2. Check the test suite for examples
3. Review service layer code
4. Contact development team

---

**Last Updated:** November 23, 2025
**Version:** 1.0.0
