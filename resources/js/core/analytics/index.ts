/**
 * Vendor-Neutral Commerce Event Bus
 *
 * Core decoupled event bus for e-commerce tracking and analytics.
 * Core modules and plugins emit neutral commerce events here; analytics
 * providers (such as Meta Pixel / Conversions API) subscribe independently.
 *
 * Core MUST NOT import any plugin.
 */

export type CommerceEventName =
  | 'PageView'
  | 'ViewContent'
  | 'Search'
  | 'AddToWishlist'
  | 'AddToCart'
  | 'InitiateCheckout'
  | 'AddPaymentInfo'
  | 'CompleteRegistration'
  | 'Purchase';

export interface CommerceItem {
  product_id: number | string;
  variant_id?: number | string | null;
  quantity: number;
  item_price: number;
  name?: string;
  category?: string;
}

export interface CommerceEventPayload {
  items?: CommerceItem[];
  value?: number;
  currency?: string;
  search_string?: string;
  content_type?: 'product' | 'product_group';
  content_name?: string;
  content_category?: string;
  order_id?: string | number;
  status?: boolean | string;
  path?: string;
  /**
   * Opaque server-contributed tracking payload (e.g. from order placement response)
   */
  tracking?: {
    purchase_event_id?: string;
    order_id?: string;
    value?: number;
    currency?: string;
    content_type?: 'product' | string;
    content_ids?: string[];
    contents?: Array<{ id: string; quantity: number; item_price: number }>;
    num_items?: number;
    [key: string]: any;
  } | null;
  [key: string]: any;
}

export interface CommerceEvent {
  name: CommerceEventName;
  payload?: CommerceEventPayload;
}

export type CommerceEventListener = (event: CommerceEvent) => void;

const listeners = new Set<CommerceEventListener>();

/**
 * Subscribe to commerce events.
 * Returns an unsubscribe cleanup function.
 */
export function onCommerceEvent(listener: CommerceEventListener): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

/**
 * Emit a commerce event across all registered subscribers.
 */
export function emitCommerceEvent(event: CommerceEvent): void;
export function emitCommerceEvent(name: CommerceEventName, payload?: CommerceEventPayload): void;
export function emitCommerceEvent(
  eventOrName: CommerceEvent | CommerceEventName,
  maybePayload?: CommerceEventPayload
): void {
  const event: CommerceEvent =
    typeof eventOrName === 'string'
      ? { name: eventOrName, payload: maybePayload }
      : eventOrName;

  listeners.forEach((listener) => {
    try {
      listener(event);
    } catch (err) {
      console.error('[CommerceEventBus] Listener error:', err);
    }
  });
}
