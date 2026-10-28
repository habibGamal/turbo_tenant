/**
 * TypeScript Interfaces for CatalogProducts Plugin
 */

export interface Category {
  id: number;
  name: string;
  name_ar?: string;
  description?: string;
  image?: string;
  sort_order?: number;
}

export interface Variant {
  id: number;
  name: string;
  name_ar?: string;
  price: number;
  is_available: boolean;
  sort_order: number;
}

export interface ExtraOptionItem {
  id: number;
  name: string;
  name_ar?: string;
  price: number;
  is_default?: boolean;
  sort_order?: number;
  allow_quantity?: boolean;
}

export interface ExtraOption {
  id: number;
  name: string;
  name_ar?: string;
  description?: string;
  min_selections?: number;
  max_selections?: number;
  allow_multiple?: boolean;
  items: ExtraOptionItem[];
}

export interface WeightOptionValue {
  id: number;
  value: number;
  label?: string;
  sort_order?: number;
}

export interface WeightOption {
  id: number;
  name: string;
  unit: string;
  values: WeightOptionValue[];
}

export interface ProductSummary {
  id: number;
  name: string;
  name_ar?: string;
  description?: string;
  image?: string;
  base_price: number;
  price_after_discount?: number | null;
  price: number;
  is_active: boolean;
  sell_by_weight: boolean;
  has_variants?: boolean;
  has_extras?: boolean;
  has_options?: boolean;
  extra_option_id?: number | null;
  category?: {
    id: number;
    name: string;
    name_ar?: string;
    description?: string;
  } | null;
  rating?: number;
}

export interface ProductDetail extends ProductSummary {
  variants: Variant[];
  extra_option?: ExtraOption | null;
  weight_option?: WeightOption | null;
  reviews_count?: number;
}

export interface Section {
  id: number;
  title: string;
  title_ar?: string;
  location?: string;
  sort_order?: number;
  products: ProductSummary[];
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface CatalogProductsQueryParams {
  page?: number;
  per_page?: number;
  search?: string;
  category?: string | string[] | number | number[];
  min_price?: number | string;
  max_price?: number | string;
  sort_by?: 'name' | 'base_price' | 'created_at' | 'id' | string;
  sort_order?: 'asc' | 'desc' | string;
}

export interface CatalogProductsResponse {
  status: string;
  data: ProductSummary[];
  meta: PaginationMeta;
  filters?: Record<string, any>;
}

export interface ProductDetailResponse {
  status: string;
  data: ProductDetail;
  related_products?: ProductSummary[];
  promotional_products?: ProductSummary[];
}

export interface SectionsResponse {
  status: string;
  data: Section[];
}

export interface CategoriesResponse {
  status: string;
  data: Category[];
}

export interface SuggestionsResponse {
  status: string;
  data: ProductSummary[];
}
