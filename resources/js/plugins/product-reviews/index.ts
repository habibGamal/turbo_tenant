import { registerPluginDefault } from '@/core/slots/slot-registry';
import DefaultReviewsSection from './components/DefaultReviewsSection';

export function registerProductReviewsPlugin(): void {
  registerPluginDefault('product.reviews', DefaultReviewsSection);
}
