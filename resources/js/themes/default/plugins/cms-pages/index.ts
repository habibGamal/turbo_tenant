import { lazy } from 'react';
import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
const ThemedCmsPages = lazy(() => import('./ThemedCmsPages'));

export function registerCmsPagesThemeOverride(slot: SlotName = 'home.sections.after') {
  registerThemeOverride('default', slot, ThemedCmsPages);
}

export { ThemedCmsPages };
