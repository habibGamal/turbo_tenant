import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
import DefaultHeroBanners from './components/DefaultHeroBanners';

/**
 * Register default fallback component for HeroBanners plugin
 */
export function registerHeroBannersPlugin(slot: SlotName = 'home.hero') {
  registerPluginDefault(slot, DefaultHeroBanners);
}

export { DefaultHeroBanners };
export * from './api/useHeroBanners';
export * from './types';
