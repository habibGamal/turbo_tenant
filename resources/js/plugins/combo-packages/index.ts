import { registerPluginDefault } from '@/core/slots/slot-registry';
import DefaultPackagesSection from './components/DefaultPackagesSection';

export function registerComboPackagesPlugin(): void {
  registerPluginDefault('home.sections.combos', DefaultPackagesSection);
}
