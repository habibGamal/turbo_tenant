import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
import ThemedCmsPages from './ThemedCmsPages';

export function registerCmsPagesThemeOverride(slot: SlotName = 'home.sections.after') {
  registerThemeOverride('default', slot, ThemedCmsPages);
}

export { ThemedCmsPages };
