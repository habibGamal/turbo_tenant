# Facebook Pixel Implementation Documentation

## Overview

This document describes the complete implementation of Facebook Pixel (Meta Pixel) tracking across the Moda Corner e-commerce application using the `react-facebook-pixel` library.

## Architecture

### Initialization


The Facebook Pixel is initialized at the application root level with the Facebook App ID stored in settings.

```tsx
ReactPixel.init(fbID, undefined, { autoConfig: true, debug: false });
```

**Key Features:**
- Conditional initialization (only if `fbID` is provided)
- Auto-configuration enabled for automatic event tracking
- Debug mode disabled for production
- Initialized once on component mount

---

## Tracked Events

### 1. ViewContent Event


**Trigger:** When a user views a product detail page

**Implementation:**
```tsx
useEffect(() => {
    if (selectedVariant)
        ReactPixel.track("ViewContent", {
            content_ids: [selectedVariant.id],
            contents: [{ id: selectedVariant.id, quantity: 1 }],
            content_type: "product",
            value: selectedVariant?.sale_price,
            currency: "EGP",
        });
    else
        ReactPixel.track("ViewContent", {
            content_ids: [product.id],
            contents: [{ id: product.id, quantity: 1 }],
            content_type: "product",
            value: product?.sale_price,
            currency: "EGP",
        });
}, [selectedVariant]);
```

**Parameters:**
- `content_ids`: Array containing variant ID or product ID
- `contents`: Array of objects with id and quantity
- `content_type`: Always "product"
- `value`: Sale price of the variant or product
- `currency`: "EGP" (Egyptian Pound)

**Behavior:**
- Tracks when a product page loads
- Re-tracks when user switches variants
- Uses variant data if available, otherwise falls back to product data

---

### 2. AddToCart Event


**Trigger:** When a user successfully adds a product to their cart

**Implementation:**
```tsx
ReactPixel.track("AddToCart", {
    content_ids: [variantId || productId],
    content_type: "product",
    contents: [{id: variantId || productId, quantity}],
    value: product?.sale_price,
    currency: "EGP",
});
```

**Used In:**
- Product card quick add button (`ProductCard.tsx`)
- Product detail page add to cart (`ProductActions.tsx`)
- Cart hook after successful API call

**Parameters:**
- `content_ids`: Variant ID (preferred) or product ID
- `content_type`: Always "product"
- `contents`: Array with id and actual quantity being added
- `value`: Sale price of the product/variant
- `currency`: "EGP"

---

### 3. AddToWishlist Event

**Location:** Multiple components

**Trigger:** When a user adds a product to their wishlist

**Implementation:**

**In ProductCard.tsx:**
```tsx
const addToWishlist = () => {
    router.post(
        route("wishlist.add"),
        { product_id: product.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                const variantId = displayData.variantId || product.id;
                ReactPixel.track("AddToWishlist", {
                    content_ids: [variantId],
                    contents: [{ id: variantId, quantity: 1 }],
                    value: displayData.salePrice || product?.sale_price,
                    currency: "EGP",
                });
            },
        }
    );
};
```

**In ProductActions.tsx:**
```tsx
const addToWishlist = () => {
    router.post(
        route("wishlist.add"),
        { product_id: product.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                const variantId = selectedVariant?.id || product.id;
                const value = selectedVariant?.sale_price || product?.sale_price;
                ReactPixel.track("AddToWishlist", {
                    content_ids: [variantId],
                    contents: [{ id: variantId, quantity: 1 }],
                    value: value,
                    currency: "EGP",
                });
            },
        }
    );
};
```

**Parameters:**
- `content_ids`: Variant ID (if selected) or product ID
- `contents`: Array with id and quantity (always 1 for wishlist)
- `value`: Sale price of the variant or product
- `currency`: "EGP"

**Behavior:**
- Only fires on successful server response
- Tracks the specific variant if one is selected
- Falls back to product data if no variant

---

### 4. InitiateCheckout Event

**Location:** `resources/js/Components/cart/OrderSummary.tsx`

**Trigger:** When a user clicks "Proceed to Checkout" from the cart page

**Implementation:**
```tsx
const checkout = () => {
    router.get(route("checkout.index"));
    ReactPixel.track("InitiateCheckout", {
        content_ids: items.map((item) => item.variant_id || item.product_id),
        contents: items.map((item) => ({
            id: item.variant_id || item.product_id, 
            quantity: item.quantity
        })),
        value: cartSummary.totalPrice,
        num_items: cartSummary.totalItems,
        currency: "EGP",
    });
};
```

**Parameters:**
- `content_ids`: Array of all variant IDs or product IDs in cart
- `contents`: Array of objects with id and quantity for each item
- `value`: Total cart price
- `num_items`: Total number of items in cart
- `currency`: "EGP"

**Behavior:**
- Tracks entire cart contents
- Includes total value and item count
- Fires before navigation to checkout page

---

## Event Flow Through Customer Journey

### 1. Product Discovery
```
User browses → Views product → ViewContent event fired
```

### 2. Product Interest
```
User views product details → ViewContent event fired
User changes variant → ViewContent event re-fired with new variant
```

### 3. Add to Cart
```
User clicks "Add to Cart" → API call → Success → AddToCart event fired
```

### 4. Wishlist Addition
```
User clicks heart icon → API call → Success → AddToWishlist event fired
```

### 5. Checkout Initiation
```
User in cart → Clicks "Proceed to Checkout" → InitiateCheckout event fired → Navigate
```

---

## Data Consistency

### Product vs Variant Tracking

The implementation intelligently handles products with and without variants:

**With Variants:**
- Uses `variant.id` as content_id
- Uses `variant.sale_price` as value
- Uses `variant.images[0]` for display

**Without Variants:**
- Uses `product.id` as content_id
- Uses `product.sale_price` as value
- Uses `product.featured_image` for display

### Priority Logic
```
variantId || productId
variant?.sale_price || product?.sale_price
variant?.quantity || product?.quantity
```

---

## Currency

All events consistently use **"EGP"** (Egyptian Pound) as the currency parameter.

---

## Error Handling

### Initialization
- Pixel only initializes if `fbID` is provided
- Graceful failure if Facebook SDK fails to load
- No blocking of application functionality

### Event Tracking
- Events fire in `onSuccess` callbacks to ensure data accuracy
- No events fired for failed operations
- Silent failure doesn't affect user experience

---

## Testing & Debugging

### Enable Debug Mode
Change in `InitPixel.tsx`:
```tsx
ReactPixel.init(fbID, undefined, { autoConfig: true, debug: true });
```

### Facebook Pixel Helper
- Install Chrome extension: "Facebook Pixel Helper"
- Events will appear in the extension popup
- Verify parameters are being sent correctly

### Console Logging
Add before event tracking:
```tsx
console.log('Tracking event:', eventName, parameters);
ReactPixel.track(eventName, parameters);
```

---

## Best Practices Implemented

1. **Event Deduplication:** Events only fire once per action using proper React hooks
2. **Accurate Values:** All monetary values use actual sale prices
3. **Proper IDs:** Variant IDs preferred over product IDs for accurate tracking
4. **Currency Consistency:** All events use "EGP"
5. **Success-Based Tracking:** Events only fire after successful operations
6. **Quantity Tracking:** Actual quantities tracked, not just "1"
7. **Cart Context:** InitiateCheckout includes full cart details

---

## Future Enhancements

### Potential Additional Events

1. **Purchase Event** - Track completed orders
   ```tsx
   ReactPixel.track("Purchase", {
       content_ids: orderItems.map(item => item.variant_id),
       contents: orderItems.map(item => ({
           id: item.variant_id,
           quantity: item.quantity
       })),
       value: order.total,
       currency: "EGP",
       order_id: order.id
   });
   ```

2. **Search Event** - Track product searches
   ```tsx
   ReactPixel.track("Search", {
       search_string: searchQuery,
       content_category: category,
       currency: "EGP"
   });
   ```

3. **CompleteRegistration** - Track user signups
   ```tsx
   ReactPixel.track("CompleteRegistration", {
       status: "registered",
       currency: "EGP"
   });
   ```

---

## Integration Points

### Components Using Pixel Tracking

| Component | Events | Purpose |
|-----------|--------|---------|
| `InitPixel.tsx` | Initialization | Root-level pixel setup |
| `Show.tsx` | ViewContent | Product page views |
| `use-cart.tsx` | AddToCart | Cart additions |
| `ProductCard.tsx` | AddToCart, AddToWishlist | Quick actions from product cards |
| `ProductActions.tsx` | AddToCart, AddToWishlist | Product detail actions |
| `OrderSummary.tsx` | InitiateCheckout | Checkout initiation |

---

## Configuration

### Environment Setup

Facebook App ID should be stored in application settings and passed to `InitPixel` component:

```tsx
<InitPixel fbID={settings?.facebook_app_id}>
    {children}
</InitPixel>
```

### Settings Structure
The `fbID` is expected to be available from the application's settings system, likely retrieved from:
- Database settings table
- Environment variables via backend
- Admin panel configuration

---

## Troubleshooting

### Events Not Firing
1. Check Facebook App ID is configured
2. Verify Facebook SDK loaded (check Network tab)
3. Enable debug mode and check console
4. Verify user has accepted tracking cookies

### Incorrect Values
1. Ensure product/variant has `sale_price` set
2. Check cart summary calculations
3. Verify currency is consistently "EGP"

### Duplicate Events
1. Check for multiple component mounts
2. Verify useEffect dependencies
3. Ensure events only fire in `onSuccess` callbacks

---

## Compliance & Privacy

### GDPR/Privacy Considerations
- Pixel only initializes with explicit Facebook App ID
- Consider cookie consent implementation
- Users should be able to opt-out of tracking
- Privacy policy should mention Facebook Pixel usage

### Data Collected
- Product IDs and variant IDs
- Product prices and quantities
- Cart totals
- User actions (views, adds, checkouts)
- No personally identifiable information (PII) sent directly

---

## Maintenance

### Regular Checks
- Verify events are still firing after updates
- Test pixel on staging before production deploys
- Monitor Facebook Events Manager for data quality
- Update event parameters if product schema changes

### Version Compatibility
- Current library: `react-facebook-pixel`
- Facebook Pixel API version: Latest (auto-updated by SDK)
- Compatible with React 18+
- Works with Inertia.js SSR

---

## Summary

The Facebook Pixel implementation provides comprehensive e-commerce tracking across the customer journey, from product discovery to checkout initiation. The implementation prioritizes:

- **Accuracy:** Only tracks successful operations
- **Consistency:** Uniform data structure across all events
- **Flexibility:** Handles both variant-based and simple products
- **Performance:** Minimal overhead, non-blocking
- **Maintainability:** Centralized tracking logic in custom hooks

This tracking foundation enables powerful Facebook advertising capabilities including:
- Dynamic product ads
- Conversion tracking
- Custom audience creation
- Lookalike audience generation
- Ad performance optimization
