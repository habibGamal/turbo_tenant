import ReactPixel from 'react-facebook-pixel';
import { CommerceEvent } from '@/core/analytics';
import { ensureCsrfToken } from '@/core/api/client';
import { metaContentId } from '../utils/content-id';
import { MetaCustomData, MetaPixelConfig, MetaRelayPayload } from '../types';

let currentConfig: MetaPixelConfig = {
  pixelId: null,
  currency: null,
  debugMode: false,
  serverEventsEnabled: false,
};

let currentUserId: number | null = null;
let initializedPixelId: string | null = null;
let hasSyncedConfig = false;
const eventBuffer: CommerceEvent[] = [];
const MAX_BUFFER = 50;

/**
 * Checks whether debug mode is enabled via settings or URL/sessionStorage (?meta_debug=1).
 */
export function isDebugMode(settingsDebug?: boolean): boolean {
  if (typeof window === 'undefined') return false;
  if (settingsDebug) return true;
  try {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('meta_debug') === '1') {
      sessionStorage.setItem('meta_debug', '1');
      return true;
    }
    if (sessionStorage.getItem('meta_debug') === '1') {
      return true;
    }
  } catch {
    // sessionStorage might be restricted in some environments
  }
  return false;
}

/**
 * Generate a unique event ID for deduplication between Pixel and Conversions API.
 */
export function generateEventId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return 'evt_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now().toString(36);
}

/**
 * Parse XSRF-TOKEN cookie value.
 */
function getXsrfToken(): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(/(^|;)\s*XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[2]) : null;
}

/**
 * Sync tenant settings and authenticated user to the Meta tracker.
 */
export function updateTrackerConfig(
  config: MetaPixelConfig,
  user?: { id: number; email: string } | null
): void {
  const isFirstSync = !hasSyncedConfig;
  hasSyncedConfig = true;
  currentConfig = { ...currentConfig, ...config };

  const pixelId = currentConfig.pixelId;
  if (!pixelId) {
    if (isFirstSync) {
      // Discard buffered events if no pixelId is configured
      eventBuffer.length = 0;
    }
    return;
  }

  // Initialize once per pixelId or when user context changes
  const newUserId = user?.id ?? null;
  if (initializedPixelId !== pixelId || currentUserId !== newUserId) {
    initializedPixelId = pixelId;
    currentUserId = newUserId;

    const advancedMatching: Record<string, string> = {};
    if (user?.email) {
      advancedMatching.em = user.email.trim().toLowerCase();
    }
    if (user?.id) {
      advancedMatching.external_id = `u:${user.id}`;
    }

    try {
      ReactPixel.init(pixelId, advancedMatching as any, {
        autoConfig: true,
        debug: false,
      });

      if (isDebugMode(currentConfig.debugMode)) {
        console.info('[MetaPixel] Initialized Pixel:', {
          pixelId,
          advancedMatching,
        });
      }
    } catch (err) {
      if (isDebugMode(currentConfig.debugMode)) {
        console.warn('[MetaPixel] Init error:', err);
      }
    }
  }

  // Flush buffered events through the normal path on first sync
  if (isFirstSync && eventBuffer.length > 0) {
    const eventsToFlush = [...eventBuffer];
    eventBuffer.length = 0;
    for (const bufferedEvent of eventsToFlush) {
      handleCommerceEvent(bufferedEvent);
    }
  }
}

/**
 * Send event to the server relay (Conversions API).
 */
async function relayEvent(payload: MetaRelayPayload): Promise<void> {
  if (!currentConfig.serverEventsEnabled) {
    return;
  }

  try {
    await ensureCsrfToken();
    const token = getXsrfToken();

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };

    if (token) {
      headers['X-XSRF-TOKEN'] = token;
    }

    if (isDebugMode(currentConfig.debugMode)) {
      console.info('[MetaRelay] Mirroring event:', {
        name: payload.event_name,
        eventID: payload.event_id,
        body: payload,
      });
    }

    fetch('/api/meta/events', {
      method: 'POST',
      headers,
      credentials: 'same-origin',
      keepalive: true,
      body: JSON.stringify(payload),
    }).catch(() => {
      // Fire-and-forget, swallow errors
    });
  } catch {
    // Swallow errors
  }
}

/**
 * Handle incoming commerce event from the core event bus.
 */
export function handleCommerceEvent(event: CommerceEvent): void {
  // Before the first settings sync, buffer events up to MAX_BUFFER
  if (!hasSyncedConfig) {
    if (eventBuffer.length < MAX_BUFFER) {
      eventBuffer.push(event);
    }
    return;
  }

  const pixelId = currentConfig.pixelId;
  const debug = isDebugMode(currentConfig.debugMode);

  // If no pixel ID configured, do nothing cleanly
  if (!pixelId) {
    if (debug) {
      console.info('[MetaPixel] Skipped event (no pixelId):', event.name);
    }
    return;
  }

  const { name, payload = {} } = event;

  // 1. Purchase Event Handling
  if (name === 'Purchase') {
    const tracking = payload.tracking;
    // If tracking object is absent (e.g. plugin inactive on backend), send no Purchase
    if (!tracking || !tracking.purchase_event_id) {
      if (debug) {
        console.info('[MetaPixel] Skipped Purchase (no tracking data in placement response)');
      }
      return;
    }

    const eventID = tracking.purchase_event_id;
    const purchaseData: MetaCustomData = {
      value: Number(tracking.value) || 0,
      currency: tracking.currency || currentConfig.currency || 'EGP',
      content_type: (tracking.content_type as any) || 'product',
      content_ids: tracking.content_ids ? tracking.content_ids.map(String) : [],
      contents: tracking.contents ? tracking.contents.map((c) => ({
        id: String(c.id),
        quantity: Number(c.quantity) || 1,
        item_price: Number(c.item_price) || 0,
      })) : [],
      num_items: Number(tracking.num_items) || (tracking.contents?.length ?? 1),
      order_id: tracking.order_id,
    };

    if (debug) {
      console.info('[MetaPixel] Tracking Purchase:', {
        name: 'Purchase',
        eventID,
        payload: purchaseData,
      });
    }

    try {
      ReactPixel.fbq('track', 'Purchase', purchaseData, { eventID });
    } catch (err) {
      if (debug) console.warn('[MetaPixel] Purchase track error:', err);
    }

    // Purchase is NEVER mirrored to the relay endpoint (the backend sends Purchase itself)
    return;
  }

  // 2. Funnel Events (PageView, ViewContent, Search, AddToWishlist, AddToCart, InitiateCheckout, AddPaymentInfo, CompleteRegistration)
  const eventID = generateEventId();
  const customData: MetaCustomData = {};

  if (payload.items && payload.items.length > 0) {
    if (payload.content_type === 'product_group') {
      customData.content_type = 'product_group';
      customData.content_ids = [String(payload.items[0].product_id)];
      customData.contents = [{
        id: String(payload.items[0].product_id),
        quantity: Number(payload.items[0].quantity) || 1,
        item_price: Number(payload.items[0].item_price) || 0,
      }];
    } else {
      customData.content_type = 'product';
      customData.content_ids = payload.items.map((i) =>
        metaContentId(i.product_id, i.variant_id)
      );
      customData.contents = payload.items.map((i) => ({
        id: metaContentId(i.product_id, i.variant_id),
        quantity: Number(i.quantity) || 1,
        item_price: Number(i.item_price) || 0,
      }));
    }

    customData.num_items = payload.num_items ?? payload.items.reduce(
      (sum, i) => sum + (Number(i.quantity) || 1),
      0
    );
  }

  if (payload.value !== undefined) {
    customData.value = Number(payload.value);
    const defaultCurrency = currentConfig.currency || undefined;
    if (payload.currency || defaultCurrency) {
      customData.currency = payload.currency || defaultCurrency;
    }
  } else if (payload.currency) {
    customData.currency = payload.currency;
  }
  if (payload.search_string) {
    customData.search_string = payload.search_string;
  }
  if (payload.content_name) {
    customData.content_name = payload.content_name;
  }
  if (payload.content_category) {
    customData.content_category = payload.content_category;
  }
  if (payload.status !== undefined) {
    customData.status = payload.status;
  }

  if (debug) {
    console.info('[MetaPixel] Tracking event:', {
      name,
      eventID,
      payload: customData,
    });
  }

  try {
    ReactPixel.fbq('track', name, customData, { eventID });
  } catch (err) {
    if (debug) console.warn('[MetaPixel] track error:', err);
  }

  // Relay to Conversions API server endpoint
  const acceptedRelayEvents = [
    'PageView',
    'ViewContent',
    'Search',
    'AddToWishlist',
    'AddToCart',
    'InitiateCheckout',
    'AddPaymentInfo',
    'CompleteRegistration',
  ];

  if (acceptedRelayEvents.includes(name)) {
    relayEvent({
      event_name: name,
      event_id: eventID,
      event_source_url: typeof window !== 'undefined' ? window.location.href : '',
      custom_data: Object.keys(customData).length > 0 ? customData : undefined,
    });
  }
}
