import { NormalizedProduct, ProductDetail } from '../types';

/**
 * Normalizes raw product detail data from the catalog API.
 * Accepts single snake_case API shape (ProductDetail) and populates
 * camelCase convenience properties for components.
 */
export function normalizeProduct(raw: null | undefined): null;
export function normalizeProduct(raw: ProductDetail | Record<string, any>): NormalizedProduct;
export function normalizeProduct(raw: any): NormalizedProduct | null;
export function normalizeProduct(raw: any): NormalizedProduct | null {
  if (!raw) {
    return null;
  }

  const extraOption = raw.extra_option ?? null;
  const weightOption = raw.weight_option ?? null;
  const reviewsCount = typeof raw.reviews_count === 'number' ? raw.reviews_count : (raw.reviews_count ? Number(raw.reviews_count) : 0);
  const comboPackages = Array.isArray(raw.combo_packages)
    ? raw.combo_packages
    : (Array.isArray(raw.comboPackages) ? raw.comboPackages : undefined);

  return {
    ...raw,
    category: raw.category ?? null,
    variants: Array.isArray(raw.variants) ? raw.variants : [],
    extraOption,
    extra_option: extraOption,
    weightOption,
    weight_option: weightOption,
    reviewsCount,
    reviews_count: reviewsCount,
    combo_packages: comboPackages,
    comboPackages,
  };
}
