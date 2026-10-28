import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
import DefaultProductsSection from './components/DefaultProductsSection';
import DefaultProductDetails from './components/DefaultProductDetails';
import DefaultMenuPage from './components/DefaultMenuPage';

/**
 * Register default fallback components for CatalogProducts plugin
 */
export function registerCatalogProductsPlugin(): void {
  registerPluginDefault('home.sections.after' as SlotName, DefaultProductsSection);
  registerPluginDefault('product.details.after' as SlotName, DefaultProductDetails);
  registerPluginDefault('product.page' as SlotName, DefaultProductDetails);
  registerPluginDefault('menu.page' as SlotName, DefaultMenuPage);
}

export * from './api';
export * from './types';
export { DefaultProductsSection, DefaultProductDetails, DefaultMenuPage };
