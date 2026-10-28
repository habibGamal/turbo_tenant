import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
import ThemedHeroBanners from './ThemedHeroBanners';

export function registerHeroBannersThemeOverride(slot: SlotName = 'home.hero') {
  registerThemeOverride('default', slot, ThemedHeroBanners);
}

export { ThemedHeroBanners };
