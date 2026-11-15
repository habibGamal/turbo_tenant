import React, { useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';
import { useTenantSafe } from '@/core/context/TenantContext';
import { useAuth } from '@/core/context/AuthContext';
import { emitCommerceEvent } from '@/core/analytics';
import { updateTrackerConfig } from '../services/meta-pixel-tracker';

/**
 * Meta Pixel Route Tracker
 *
 * Rendered inside <BrowserRouter> to observe navigation and fire PageView
 * on each pathname+search change, exactly once on first load (no double fire).
 */
export default function MetaPixelRouteTracker(): React.ReactElement | null {
  const location = useLocation();
  const tenantCtx = useTenantSafe();
  const authCtx = useAuth();
  const lastTrackedLocation = useRef<string | null>(null);

  const settings = tenantCtx?.settings;
  const isLoading = tenantCtx?.isLoading ?? false;
  const user = authCtx?.user;

  // Sync config whenever settings or auth user change
  useEffect(() => {
    if (isLoading || !settings) return;

    updateTrackerConfig(
      {
        pixelId: settings.meta_pixel_id ?? null,
        currency: settings.currency ?? null,
        debugMode: Boolean(settings.meta_debug_mode),
        serverEventsEnabled: Boolean(settings.meta_server_events_enabled),
      },
      user ? { id: user.id, email: user.email } : null
    );
  }, [
    settings?.meta_pixel_id,
    settings?.currency,
    settings?.meta_debug_mode,
    settings?.meta_server_events_enabled,
    isLoading,
    user?.id,
    user?.email,
  ]);

  // Track PageView on route change (pathname + search)
  useEffect(() => {
    // Wait until bootstrap data resolves so settings are available
    if (isLoading) return;

    const currentPath = location.pathname + location.search;

    // Guard against duplicate fire for identical location
    if (lastTrackedLocation.current === currentPath) {
      return;
    }

    lastTrackedLocation.current = currentPath;

    emitCommerceEvent('PageView', {
      path: currentPath,
    });
  }, [location.pathname, location.search, isLoading]);

  return null;
}
