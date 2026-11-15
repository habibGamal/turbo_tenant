import { registerThemeOverride } from '@/core/slots/slot-registry';
import ThemedPackagesSection from './ThemedPackagesSection';
import ThemedPackageConfiguratorPage from './ThemedPackageConfiguratorPage';

export function registerComboPackagesThemeOverride(): void {
  registerThemeOverride('default', 'home.sections.combos', ThemedPackagesSection);
  registerThemeOverride('default', 'package.page', ThemedPackageConfiguratorPage);
}

export { ThemedPackagesSection, ThemedPackageConfiguratorPage };

