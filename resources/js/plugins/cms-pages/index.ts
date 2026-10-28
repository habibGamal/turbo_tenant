import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
import DefaultCmsPages from './components/DefaultCmsPages';

/**
 * Register default fallback component for CmsPages
 */
export function registerCmsPagesPlugin(slot: SlotName = 'home.sections.after') {
  registerPluginDefault(slot, DefaultCmsPages);
}

export { DefaultCmsPages };
export * from './api/useCmsPages';
export * from './types';
