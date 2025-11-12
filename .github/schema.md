# Project Database Schema (extracted from migrations)

This document summarizes the main database tables and columns as defined in the project's migration files.

---

## Tables

- **users**
  ....

- **profiles**
  - `id` (integer, PK)
  - `phone_number` (string, nullable)
  - `points` (integer, default: 0)
  - timestamps

- **branches**
  - `id` (integer, PK)
  - `name` (string, not null)
  - `link` (text, not null) — used by `PosApiService` to call branch POS endpoints

- **delivery_costs**
  - `area_name` (string, PK)
  - `cost` (integer, not null)
  - `branch_id` (integer, FK -> `branches.id`)

- **addresses**
  - `id` (integer, PK)
  - `area` (string, FK -> `delivery_costs.area_name`, onUpdate CASCADE)
  - `full_address` (string, not null)
  - `profile_id` (integer, FK -> `profiles.id`, onDelete CASCADE)
  - timestamps

- **extra_options**
  - `id` (integer, PK)
  - `name` (string, not null)
  - `content` (text, not null)

- **categories**
  - `name` (string, PK)

- **products**
  - `id` (integer, PK)
  - `name` (string, not null)
  - `description` (text, nullable)
  - `image` (string, not null)
  - `price` (double, nullable)
  - `price_after_discount` (double, nullable)
  - `varients_price_list_obj` (text, nullable) — JSON serialized variants/prices
  - `extra_option_id` (integer, FK -> `extra_options.id`, nullable)
  - `category_name` (string, FK -> `categories.name`, onUpdate CASCADE)
  - `is_active` (boolean, default: true)
  - `pos_ref_obj` (text, nullable) — POS mapping object
  - `single_pos_ref` (string, nullable)
  - `pos_options` (text, default: `{}`) — added in later migration
  - `sell_by_weight` (boolean, default: false)
  - `weight_options_id` (integer, FK -> `weight_options.id`, nullable, onDelete SET NULL)
  - timestamps

- **weight_options**
  - `id` (integer, PK)
  - `options` (text, not null) — JSON describing weight options
  - `created_at`, `updated_at` (timestamps)

- **sections**
  - `id` (integer, PK)
  - `title` (text, not null)
  - `location` (string, not null)
  - `products` (text, default: `[]`) — JSON array of product ids
  - `is_active` (boolean, default: true)
  - timestamps

- **coupons**
  - `id` (integer, PK)
  - `code` (string, unique, not null)
  - `type` (string, not null)
  - `value` (double, not null)
  - `expiry_date` (datetime, not null)
  - `is_active` (boolean, default: true)
  - `usage_count` (integer, default: 0)
  - `total_consumed` (double, default: 0)
  - `conditions` (text, default: `[]`) — JSON conditions
  - timestamps

- **orders**
  - `id` (integer, PK)
  - `order_number` (string, not null)
  - `shift_id` (integer, unsigned)
  - `status` (string(20), not null)
  - `type` (string(20), not null) — e.g., `web_delivery`, `web_takeaway`
  - `sub_total`, `tax`, `service`, `discount`, `total` (double, not null, defaults)
  - `note` (text, nullable)
  - `user_id` (integer, FK -> `users.id`, onDelete CASCADE)
  - `coupon_id` (integer, FK -> `coupons.id`, nullable, onDelete CASCADE)
  - `branch_id` (integer, FK -> `branches.id`)
  - `address_id` (integer, FK -> `addresses.id`, nullable)
  - timestamps

- **order_items**
  - `id` (integer, PK)
  - `order_id` (integer, FK -> `orders.id`, onDelete CASCADE)
  - `product_id` (integer, FK -> `products.id`, onDelete CASCADE)
  - `notes` (text, nullable)
  - `quantity` (decimal(8,3)) — quantity updated to decimal in later migration
  - `price` (double)
  - `total` (double)
  - timestamps

- **settings**
  - `id` (integer, PK)
  - `key` (string, unique, not null)
  - `value` (text, not null) — often JSON-serialized values (e.g., `sliders`, `menu`, `theme`)
  - timestamps

---

## Notes & Conventions

- Several columns store JSON as `text` (`settings.value`, `products.pos_options`, `sections.products`, `products.varients_price_list_obj`, etc.). The application reads/writes these as JSON.
- `products.sell_by_weight` + `weight_options_id` allow weight-based selling with options stored in `weight_options.options`.
- `order_items.quantity` was migrated from integer to `decimal(8,3)` to support fractional weights.
- Foreign keys use `onDelete` cascade or `SET NULL` where appropriate per migration definitions.

If you want, I can also produce a visual ERD (SVG/PNG) for the above schema.
