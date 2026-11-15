import { registerPluginDefault } from '@/core/slots/slot-registry';
import { onCommerceEvent } from '@/core/analytics';
import MetaPixelRouteTracker from './components/MetaPixelRouteTracker';
import { handleCommerceEvent } from './services/meta-pixel-tracker';

export * from './utils/content-id';
export * from './types';

let registered = false;

/**
 * Register the Meta Marketing frontend plugin.
 * Subscribes to the commerce event bus and registers the route tracker slot.
 */
export function registerMetaMarketingPlugin(): void {
  if (registered) return;
  registered = true;

  // Subscribe to commerce event bus
  onCommerceEvent(handleCommerceEvent);

  // Expose route tracker via standard plugin slot
  registerPluginDefault('router.tracking', MetaPixelRouteTracker);
}
