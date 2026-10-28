import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
import ThemedProductsSection from './ThemedProductsSection';
import ThemedProductDetails from './ThemedProductDetails';
import ThemedProductShow from './ThemedProductShow';
import ThemedMenuPage from './ThemedMenuPage';
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
