import { registerThemeOverride } from '@/core/slots/slot-registry';
import ThemedPackagesSection from './ThemedPackagesSection';

export function registerComboPackagesThemeOverride(): void {
  registerThemeOverride('default', 'home.sections.combos', ThemedPackagesSection);
}

export { ThemedPackagesSection };
