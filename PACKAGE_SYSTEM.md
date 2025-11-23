# Package System Documentation

## Overview

The package system allows you to create flexible product bundles with conditional selection logic and variant-specific options.

## Database Structure

### Tables

1. **packages** - Main package information
   - Basic info: name, description (EN/AR)
   - Pricing: price, original_price, discount_percentage
   - Display: badge, icon, gradient, is_active, is_featured
   - Validity: valid_from, valid_until

2. **package_groups** - Groups of items within a package
   - Selection types: `all`, `choose_one`, `choose_multiple`
   - Min/max selections for conditional groups
   - Sort order

3. **package_items** - Individual products/variants in groups
   - Product and optional variant selection
   - Quantity per item
   - Price adjustments (extra charges)
   - Default selection flag
   - Sort order

## Models

### Package
- `groups()` - HasMany relationship to PackageGroup
- `items()` - HasManyThrough relationship to PackageItem
- `products()` - Legacy BelongsToMany (deprecated)

### PackageGroup
- `package()` - BelongsTo Package
- `items()` - HasMany PackageItem
- `isConditional()` - Check if group requires user selection
- `requiresSelection()` - Check if user must make a choice

### PackageItem
- `group()` - BelongsTo PackageGroup
- `product()` - BelongsTo Product
- `variant()` - BelongsTo ProductVariant
- `hasVariant()` - Check if item has a specific variant
- `getDisplayName()` - Get formatted name with variant
- `getEffectivePrice()` - Calculate price including adjustments

## Usage Examples

### 1. Fixed Items Package (All Items)

```php
$package = Package::create([
    'name' => 'Family Meal',
    'price' => 49.99,
    // ... other fields
]);

$group = PackageGroup::create([
    'package_id' => $package->id,
    'name' => 'Included Items',
    'selection_type' => 'all', // Everyone gets all items
    'sort_order' => 1,
]);

$group->items()->create([
    'product_id' => $product1->id,
    'quantity' => 2,
]);

$group->items()->create([
    'product_id' => $product2->id,
    'quantity' => 1,
]);
```

### 2. Choose One Option

```php
$group = PackageGroup::create([
    'package_id' => $package->id,
    'name' => 'Choose Your Main Dish',
    'selection_type' => 'choose_one',
    'min_selections' => 1,
    'max_selections' => 1,
    'sort_order' => 2,
]);

// Add 3 options
$group->items()->create([
    'product_id' => $chicken->id,
    'quantity' => 1,
    'is_default' => true, // Pre-selected
]);

$group->items()->create([
    'product_id' => $beef->id,
    'quantity' => 1,
    'price_adjustment' => 3.00, // +$3 for beef
]);

$group->items()->create([
    'product_id' => $fish->id,
    'quantity' => 1,
]);
```

### 3. Choose Multiple Options

```php
$group = PackageGroup::create([
    'package_id' => $package->id,
    'name' => 'Choose 2 Sides',
    'selection_type' => 'choose_multiple',
    'min_selections' => 2,
    'max_selections' => 2,
    'sort_order' => 3,
]);

// Add 4 options, customer picks 2
foreach ($sideProducts as $index => $product) {
    $group->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'is_default' => $index < 2, // First 2 are default
    ]);
}
```

### 4. Variant-Specific Selection

```php
$group = PackageGroup::create([
    'package_id' => $package->id,
    'name' => 'Choose Your Size',
    'selection_type' => 'choose_one',
    'min_selections' => 1,
    'max_selections' => 1,
]);

// Add specific variants as options
foreach ($product->variants as $variant) {
    $group->items()->create([
        'product_id' => $product->id,
        'variant_id' => $variant->id, // Specific variant
        'quantity' => 1,
    ]);
}
```

## Filament Admin Interface

### Creating/Editing Packages

The Filament resource provides a comprehensive UI:

1. **Basic Information** - Name, description (bilingual)
2. **Status** - Active, Featured toggles
3. **Pricing** - Price, original price, discount %
4. **Display Settings** - Badge, icon, gradient, sort order
5. **Validity Period** - Valid from/until dates (collapsible)
6. **Package Contents** - Nested repeater for groups and items
   - Add multiple groups
   - Each group has multiple items
   - Live variant dropdown based on product selection
   - Conditional min/max selections
   - Price adjustments per item
   - Default selections
   - Reorderable and cloneable

### Table View

- Shows all packages with key information
- Filters: Active, Featured, With/Without discount
- Displays group count and item count
- Sortable by price, discount, sort order
- Quick edit actions

## Selection Type Reference

| Type | Description | Use Case |
|------|-------------|----------|
| `all` | Customer gets all items in this group | Fixed bundle items |
| `choose_one` | Must select exactly 1 item | Main dish selection, Size choice |
| `choose_multiple` | Select X items from options | Pick 2 sides from 4 options |

## Key Features

✅ Conditional product selection (choose 1 or multiple)
✅ Variant-specific selections (size, color, etc.)
✅ Price adjustments for premium options
✅ Default selections for conditional groups
✅ Multilingual support (English/Arabic)
✅ Nested group/item structure
✅ Live UI updates in admin panel
✅ Cloneable groups for easy duplication
✅ Flexible validation (min/max selections)

## Migration Notes

- Old `package_product` pivot table is dropped
- Data migration required if existing packages exist
- Legacy `products()` relationship kept for backward compatibility

## Example Seeder

See `database/seeders/PackageSeeder.php` for complete examples including:
- Family Feast - Fixed items + choose one main
- Lunch Special - Variant selection
- Weekend Brunch - Choose multiple sides with premium pricing
