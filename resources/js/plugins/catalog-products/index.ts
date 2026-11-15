import { lazy } from 'react';
import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
import DefaultProductsSection from './components/DefaultProductsSection';
const DefaultProductDetails = lazy(() => import('./components/DefaultProductDetails'));
const DefaultMenuPage = lazy(() => import('./components/DefaultMenuPage'));

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
