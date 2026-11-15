import React from 'react';

/**
 * Standard Named Extension Points in Turbo Tenant Storefront.
 */
export type SlotName =
  | 'home.hero'
  | 'home.sections.after'
  | 'product.details.after'
  | 'product.tabs'
  | 'product.upsell'
  | 'product.card.favorite'
  | 'cart.page'
  | 'header.actions.cart'
  | 'cart.summary.coupon'
  | 'checkout.payment.methods'
  | 'checkout.guest.form'
  | 'order.tracking'
  | 'customer.orders'
  | 'favorites.page'
  | 'cms.page'
  | 'product.reviews'
  | 'checkout.coupon.form'
  | 'header.actions.notifications'
  | 'home.sections.combos'
  | 'payment.callback'
  | 'payment.kashier'
  | 'router.tracking'
  | (string & {});

export type SlotComponent = React.ComponentType<any>;

// 1. Defaults registered by plugins
const pluginDefaultRegistry: Partial<Record<string, SlotComponent>> = {};

// 2. Overrides registered by active themes: themeName -> slotName -> Component
const themeOverrideRegistry: Record<string, Partial<Record<string, SlotComponent>>> = {};

/**
 * Register a default fallback component for a slot (invoked by plugin modules).
 */
export function registerPluginDefault(slot: SlotName, component: SlotComponent): void {
  pluginDefaultRegistry[slot] = component;
}

/**
 * Register a theme-specific override for a slot (invoked by theme modules).
 */
export function registerThemeOverride(themeName: string, slot: SlotName, component: SlotComponent): void {
  if (!themeOverrideRegistry[themeName]) {
    themeOverrideRegistry[themeName] = {};
  }
  themeOverrideRegistry[themeName][slot] = component;
}

/**
 * Resolve slot component according to: Theme Override > Plugin Default Fallback > null.
 */
export function resolveSlotComponent(slot: SlotName, currentTheme: string = 'default'): SlotComponent | null {
  // 1. Check if the active theme registered an override
  const themeOverride = themeOverrideRegistry[currentTheme]?.[slot];
  if (themeOverride) {
    return themeOverride;
  }

  // 2. Fall back to the default plugin component
  const defaultComponent = pluginDefaultRegistry[slot];
  if (defaultComponent) {
    return defaultComponent;
  }

  // 3. No component registered for this slot
  return null;
}
