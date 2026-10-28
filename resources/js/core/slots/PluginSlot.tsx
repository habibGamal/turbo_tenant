import React from 'react';
import { SlotName, resolveSlotComponent } from './slot-registry';
import { useTenant } from '@/core/context/TenantContext';

export interface PluginSlotProps {
  name: SlotName;
  pluginId?: string;
  context?: Record<string, any>;
  fallback?: React.ReactNode;
  inactiveFallback?: React.ReactNode;
}

/**
 * Dynamic Extension Point Component.
 * Mounts the appropriate plugin widget using: Theme Override > Plugin Default > Fallback.
 */
export function PluginSlot({
  name,
  pluginId,
  context,
  fallback = null,
  inactiveFallback = null,
}: PluginSlotProps): React.ReactElement | null {
  let tenantTheme = 'default';
  let isPluginActive = true;
  let isLoading = false;

  try {
    const tenantCtx = useTenant();
    if (tenantCtx) {
      isLoading = tenantCtx.isLoading;
      tenantTheme = tenantCtx.tenant?.theme || 'default';
      if (pluginId && typeof tenantCtx.hasPlugin === 'function') {
        isPluginActive = tenantCtx.hasPlugin(pluginId);
      }
    }
  } catch {
    // If rendered outside TenantProvider during transitional phases, allow render
  }

  if (pluginId && isLoading) {
    return null;
  }

  // If this slot is tied to a specific plugin and tenant doesn't have it active, unmount
  if (pluginId && !isPluginActive) {
    return <>{inactiveFallback}</>;
  }

  const Component = resolveSlotComponent(name, tenantTheme);

  if (!Component) {
    return <>{fallback}</>;
  }

  return <Component {...context} />;
}
