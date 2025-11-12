# Enhanced Database Schema (Normalized)

This document describes the improved database schema with JSON fields converted to relational tables for better performance, data integrity, and maintainability.

---

## Core Tables

### **users**
- default laravel users table

### **profiles**
- `id` (integer, PK)
- `user_id` (integer, FK → `users.id`, onDelete CASCADE)
- `phone_number` (string, nullable)
- `points` (integer, default: 0)
- timestamps

---

## Location & Delivery

### **branches**
- `id` (integer, PK)
- `name` (string, not null)
- `link` (text, not null) — POS API endpoint
- `is_active` (boolean, default: true)
- timestamps

### **delivery_costs**
- `id` (integer, PK)
- `area_name` (string, unique, not null)
- `cost` (integer, not null)
- `branch_id` (integer, FK → `branches.id`)
- timestamps

### **addresses**
- `id` (integer, PK)
- `area` (string, FK → `delivery_costs.area_name`, onUpdate CASCADE)
- `full_address` (string, not null)
- `profile_id` (integer, FK → `profiles.id`, onDelete CASCADE)
- timestamps

---

## Product Catalog

### **categories**
- `id` (integer, PK)
- `name` (string, unique, not null)
- `description` (text, nullable)
- `image` (string, nullable)
- `sort_order` (integer, default: 0)
- `is_active` (boolean, default: true)
- timestamps

### **products**
- `id` (integer, PK)
- `name` (string, not null)
- `description` (text, nullable)
- `image` (string, not null)
- `base_price` (double, nullable) — base price for simple products
- `price_after_discount` (double, nullable)
- `extra_option_id` (integer, FK → `extra_options.id`, nullable)
- `category_id` (integer, FK → `categories.id`, onDelete CASCADE)
- `is_active` (boolean, default: true)
- `sell_by_weight` (boolean, default: false)
- `weight_options_id` (integer, FK → `weight_options.id`, nullable, onDelete SET NULL)
- `single_pos_ref` (string, nullable) — for simple products without variants
- timestamps

**Indexes:**
- `idx_products_category` on `category_id`
- `idx_products_active` on `is_active`

### **product_variants**
- `id` (integer, PK)
- `product_id` (integer, FK → `products.id`, onDelete CASCADE)
- `name` (string, not null) — e.g., "Small", "Medium", "Large"
- `price` (double, not null)
- `is_available` (boolean, default: true)
- `sort_order` (integer, default: 0)
- timestamps

**Indexes:**
- `idx_product_variants` on `product_id`
- `idx_variant_available` on `product_id, is_available`

### **product_pos_mappings**
- `id` (integer, PK)
- `product_id` (integer, FK → `products.id`, onDelete CASCADE)
- `variant_id` (integer, nullable, FK → `product_variants.id`, onDelete CASCADE)
- `branch_id` (integer, nullable, FK → `branches.id`, onDelete CASCADE)
- `pos_item_id` (string, not null) — POS system item ID for this product/variant
- `pos_category` (string, nullable)
- timestamps

**Constraints:**
- `unique_mapping` on (`product_id`, `variant_id`, `branch_id`)

**Indexes:**
- `idx_pos_product` on `product_id`
- `idx_pos_branch` on `branch_id`

### **extra_options**
- `id` (integer, PK)
- `name` (string, not null)
- `description` (text, nullable)
- `is_active` (boolean, default: true)
- timestamps

### **extra_option_items**
- `id` (integer, PK)
- `extra_option_id` (integer, FK → `extra_options.id`, onDelete CASCADE)
- `name` (string, not null)
- `price` (double, default: 0)
- `is_default` (boolean, default: false)
- `sort_order` (integer, default: 0)
- timestamps

**Indexes:**
- `idx_extra_items` on `extra_option_id`

### **weight_options**
- `id` (integer, PK)
- `name` (string, not null)
- `min_weight` (decimal(8,3), not null)
- `max_weight` (decimal(8,3), not null)
- `step` (decimal(8,3), default: 0.5)
- `unit` (string, default: 'kg') — kg, g, lb
- timestamps

---

## Sections & Display

### **sections**
- `id` (integer, PK)
- `title` (string, not null)
- `location` (string, not null) — e.g., 'home', 'menu'
- `is_active` (boolean, default: true)
- `sort_order` (integer, default: 0)
- timestamps

### **section_products**
- `id` (integer, PK)
- `section_id` (integer, FK → `sections.id`, onDelete CASCADE)
- `product_id` (integer, FK → `products.id`, onDelete CASCADE)
- `sort_order` (integer, default: 0)
- timestamps

**Constraints:**
- `unique_section_product` on (`section_id`, `product_id`)

**Indexes:**
- `idx_section_sort` on (`section_id`, `sort_order`)

---

## Coupons & Promotions

### **coupons**
- `id` (integer, PK)
- `code` (string, unique, not null)
- `type` (enum: 'percentage', 'fixed', not null)
- `value` (double, not null)
- `expiry_date` (datetime, not null)
- `is_active` (boolean, default: true)
- `max_usage` (integer, nullable) — null = unlimited
- `usage_count` (integer, default: 0)
- `total_consumed` (double, default: 0)
- timestamps

**Indexes:**
- `idx_coupon_code` on `code`
- `idx_coupon_active` on `is_active, expiry_date`

### **coupon_conditions**
- `id` (integer, PK)
- `coupon_id` (integer, FK → `coupons.id`, onDelete CASCADE)
- `condition_type` (enum: 'min_order', 'category', 'product', 'user_type', 'first_order', 'day_of_week', 'time_range', not null)
- `operator` (enum: 'equals', 'greater_than', 'less_than', 'in', 'not_in', default: 'equals')
- `value` (string, not null) — stores various data types as string
- timestamps

**Indexes:**
- `idx_coupon_conditions` on `coupon_id`

**Examples:**
- `min_order` / `greater_than` / `100` → minimum order $100
- `category` / `in` / `1,2,3` → valid for category IDs 1,2,3
- `first_order` / `equals` / `true` → first order only
- `day_of_week` / `in` / `1,5,6` → Monday, Friday, Saturday only

---

## Orders

### **orders**
- `id` (integer, PK)
- `order_number` (string, unique, not null)
- `shift_id` (integer, unsigned, nullable)
- `status` (enum: 'pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled', not null)
- `type` (enum: 'web_delivery', 'web_takeaway', 'pos', not null)
- `sub_total` (double, not null, default: 0)
- `tax` (double, not null, default: 0)
- `service` (double, not null, default: 0)
- `delivery_fee` (double, not null, default: 0)
- `discount` (double, not null, default: 0)
- `total` (double, not null, default: 0)
- `note` (text, nullable)
- `user_id` (integer, FK → `users.id`, onDelete CASCADE)
- `coupon_id` (integer, nullable, FK → `coupons.id`, onDelete SET NULL)
- `branch_id` (integer, FK → `branches.id`)
- `address_id` (integer, nullable, FK → `addresses.id`, onDelete SET NULL)
- timestamps

**Constraints:**
- `chk_total_positive` CHECK (`total` >= 0)

**Indexes:**
- `idx_orders_status` on `status`
- `idx_orders_user` on `user_id`
- `idx_orders_branch_date` on (`branch_id`, `created_at`)

### **order_items**
- `id` (integer, PK)
- `order_id` (integer, FK → `orders.id`, onDelete CASCADE)
- `product_id` (integer, FK → `products.id`, onDelete RESTRICT)
- `variant_id` (integer, nullable, FK → `product_variants.id`, onDelete SET NULL)
- `product_name` (string, not null) — snapshot at order time
- `variant_name` (string, nullable) — snapshot at order time
- `notes` (text, nullable)
- `quantity` (decimal(8,3), not null)
- `unit_price` (double, not null)
- `total` (double, not null)
- timestamps

**Indexes:**
- `idx_order_items_order` on `order_id`
- `idx_order_items_product` on `product_id`

### **order_item_extras**
- `id` (integer, PK)
- `order_item_id` (integer, FK → `order_items.id`, onDelete CASCADE)
- `extra_name` (string, not null) — snapshot at order time
- `extra_price` (double, not null)
- timestamps

---

## Settings & Configuration

### **settings**
- `id` (integer, PK)
- `key` (string, unique, not null)
- `value` (text, not null) — JSON for complex settings
- `type` (enum: 'string', 'number', 'boolean', 'json', default: 'string')
- `description` (text, nullable)
- timestamps

**Note:** Keep as JSON for truly dynamic configuration (theme colors, feature flags, API keys)

### **slider_images** *(optional - if sliders are frequently queried)*
- `id` (integer, PK)
- `image_url` (string, not null)
- `link_url` (string, nullable)
- `title` (string, nullable)
- `description` (text, nullable)
- `sort_order` (integer, default: 0)
- `is_active` (boolean, default: true)
- timestamps

---

## Multi-Tenancy

### **tenants**
- `id` (string, PK)
- `name` (string, not null)
- `domain` (string, unique, nullable)
- `data` (json, nullable) — tenant-specific configuration
- timestamps

### **domains**
- `id` (integer, PK)
- `domain` (string, unique, not null)
- `tenant_id` (string, FK → `tenants.id`, onDelete CASCADE)
- timestamps

---

## Key Improvements Summary

### ✅ **Normalized JSON Fields**
- ❌ `products.varients_price_list_obj` → ✅ `product_variants` table
- ❌ `products.pos_ref_obj` / `pos_options` → ✅ `product_pos_mappings` table
- ❌ `sections.products` → ✅ `section_products` junction table
- ❌ `coupons.conditions` → ✅ `coupon_conditions` table
- ❌ `extra_options.content` → ✅ `extra_option_items` table

### 📊 **Data Integrity**
- Proper foreign key constraints
- Check constraints for positive values
- Unique constraints preventing duplicates
- Cascading deletes where appropriate

### 🚀 **Performance Optimizations**
- Strategic indexes on frequently queried columns
- Composite indexes for common query patterns
- Denormalized snapshots in `order_items` for historical accuracy

### 🔧 **Better Query Capability**
- Easy filtering by variant, branch, section
- Efficient coupon validation queries
- Product availability tracking
- Order status tracking and reporting

### 📝 **Maintainability**
- Clear relationships between entities
- Easier to update prices, variants, POS mappings
- Better support for multi-branch operations
- Simplified business logic in application code

---

## Migration Path

1. **Create new tables** with proper structure
2. **Migrate existing JSON data** to relational tables
3. **Update application code** to use new tables
4. **Add indexes** for performance
5. **Test thoroughly** with existing data
6. **Deprecate old JSON columns** (mark as nullable)
7. **Drop old columns** after validation period

---

## Notes

- All timestamps use Laravel's `created_at` and `updated_at` conventions
- Foreign keys maintain referential integrity
- Indexes are added based on common query patterns
- Enums provide data validation at database level
- Snapshot fields in `order_items` preserve historical data even if products change
