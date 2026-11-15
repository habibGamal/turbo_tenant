import { lazy } from 'react';
import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
import ThemedProductsSection from './ThemedProductsSection';
const ThemedProductDetails = lazy(() => import('./ThemedProductDetails'));
const ThemedProductShow = lazy(() => import('./ThemedProductShow'));
const ThemedMenuPage = lazy(() => import('./ThemedMenuPage'));
import ProductPageSkeleton from './components/ProductPageSkeleton';

/**
 * Register theme-specific overrides for CatalogProducts in 'default' theme
 */
export function registerCatalogProductsThemeOverride(): void {
  registerThemeOverride('default', 'home.sections.after' as SlotName, ThemedProductsSection);
  registerThemeOverride('default', 'product.details.after' as SlotName, ThemedProductDetails);
  registerThemeOverride('default', 'product.page' as SlotName, ThemedProductShow);
  registerThemeOverride('default', 'menu.page' as SlotName, ThemedMenuPage);
}

export { ThemedProductsSection, ThemedProductDetails, ThemedProductShow, ThemedMenuPage, ProductPageSkeleton };
