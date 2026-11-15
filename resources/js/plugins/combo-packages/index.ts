import { registerPluginDefault } from '@/core/slots/slot-registry';
import DefaultPackagesSection from './components/DefaultPackagesSection';
import DefaultPackageConfiguratorPage from './components/DefaultPackageConfiguratorPage';

export function registerComboPackagesPlugin(): void {
  registerPluginDefault('home.sections.combos', DefaultPackagesSection);
  registerPluginDefault('package.page', DefaultPackageConfiguratorPage);
}

export { DefaultPackageConfiguratorPage };

