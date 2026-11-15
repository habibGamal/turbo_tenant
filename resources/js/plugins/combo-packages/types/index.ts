import { ApiSuccess } from '@/core/api/types';
import { ExtraOption } from '@/plugins/catalog-products/types';

export type SelectionType = 'all' | 'choose_one' | 'choose_multiple';

export interface PackageAvailability {
  day_of_week: number;
  starts_at: string;
  ends_at: string;
}

export type UnavailableReason = 'outside_hours' | 'sold_out_today' | null;

export type PackageOrderType = 'web_delivery' | 'web_takeaway';

export interface PackageProduct {
  id: number;
  name: string;
  name_ar: string | null;
  image: string | null;
}

export interface PackageVariant {
  id: number;
  name: string;
  name_ar: string | null;
  price: number;
}

export interface PackageVariantOption {
  id: number;
  name: string;
  name_ar: string | null;
  price_delta: number;
}

export interface PackageItem {
  id: number;
  package_group_id: number;
  product_id: number;
  variant_id: number | null;
  quantity: number;
  price_adjustment: number;
  is_default: boolean;
  sort_order: number;
  variant_mode?: 'fixed' | 'customer_choice';
  variant_options?: PackageVariantOption[];
  product: PackageProduct;
  variant: PackageVariant | null;
  allow_extras?: boolean;
  extra_option?: ExtraOption | null;
}

/** Backward compatibility alias for PackageItem */
export type PackageGroupItem = PackageItem;

export interface PackageGroup {
  id: number;
  package_id: number;
  name: string | null;
  name_ar: string | null;
  description?: string | null;
  description_ar?: string | null;
  selection_type: SelectionType;
  allow_repeats?: boolean;
  min_selections: number | null;
  max_selections: number | null;
  sort_order: number;
  items: PackageItem[];
}

export interface ComboPackage {
  id: number;
  name: string;
  name_ar: string | null;
  description: string | null;
  description_ar: string | null;
  image: string | null;
  price: number;
  original_price: number | null;
  discount_percentage: number | null;
  savings_amount: number | null;
  badge: string | null;
  badge_ar: string | null;
  icon: string;
  gradient: string;
  is_active: boolean;
  is_featured: boolean;
  sort_order: number;
  valid_from: string | null;
  valid_until: string | null;
  allow_coupons?: boolean;
  available_now?: boolean;
  unavailable_reason?: UnavailableReason;
  next_available_at?: string | null;
  availability?: PackageAvailability[];
  branch_ids?: number[];
  order_types?: PackageOrderType[];
  max_per_order?: number | null;
  remaining_today?: number | null;
  groups: PackageGroup[];
}

export type Package = ComboPackage;

export type ComboPackagesResponse = ApiSuccess<ComboPackage[]>;
export type SingleComboPackageResponse = ApiSuccess<ComboPackage>;
export type PackagesResponse = ComboPackagesResponse;
export type SinglePackageResponse = SingleComboPackageResponse;
