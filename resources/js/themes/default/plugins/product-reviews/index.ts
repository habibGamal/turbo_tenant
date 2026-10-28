import { registerThemeOverride } from '@/core/slots/slot-registry';
import ThemedReviews from './ThemedReviews';

export function registerProductReviewsThemeOverride(): void {
  registerThemeOverride('default', 'product.reviews', ThemedReviews);
}

export { ThemedReviews };
