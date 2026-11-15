import { ComboPackage, PackageGroup, PackageItem, PackageVariantOption } from '../types';
import {
  CartLineComponent,
  AddPackageToCartPayload,
  PackageCartLineSelection,
} from '@/plugins/cart-engine/types';

export interface SelectedExtra {
  id: number;
  quantity: number;
}

export interface ItemSelection {
  package_item_id: number;
  quantity: number;
  variant_id?: number | null;
  extras?: SelectedExtra[];
  notes?: string | null;
}

export type ComboSelection = Record<number, ItemSelection>;

export interface ComponentExtrasValidationResult {
  valid: boolean;
  error?: string;
}

/**
 * Returns initial default selection for a package.
 */
export function initialSelection(pkg: ComboPackage): ComboSelection {
  const selection: ComboSelection = {};

  for (const group of pkg.groups) {
    if (group.selection_type === 'all') {
      continue;
    }

    if (group.selection_type === 'choose_one') {
      // Only the admin's defaults are preselected; never pick (possibly paid) options for the customer.
      const defaultItem = group.items.find((i) => i.is_default);
      if (defaultItem) {
        const variantId =
          defaultItem.variant_mode === 'customer_choice'
            ? defaultItem.variant_options?.[0]?.id ?? defaultItem.variant_id ?? null
            : null;

        selection[defaultItem.id] = {
          package_item_id: defaultItem.id,
          quantity: 1,
          variant_id: variantId,
        };
      }
    } else if (group.selection_type === 'choose_multiple') {
      const maxAllowed = group.max_selections ?? Infinity;
      let pickedCount = 0;

      const defaultItems = group.items.filter((i) => i.is_default);
      const itemsToPick = defaultItems;

      for (const item of itemsToPick) {
        if (pickedCount >= maxAllowed) break;

        const variantId =
          item.variant_mode === 'customer_choice'
            ? item.variant_options?.[0]?.id ?? item.variant_id ?? null
            : null;

        selection[item.id] = {
          package_item_id: item.id,
          quantity: 1,
          variant_id: variantId,
        };
        pickedCount += 1;
      }
    }
  }

  return selection;
}

/**
 * Total quantity picked in a group.
 */
export function getGroupSelectionCount(group: PackageGroup, selection: ComboSelection): number {
  let count = 0;
  for (const item of group.items) {
    if (selection[item.id]) {
      count += selection[item.id].quantity;
    }
  }
  return count;
}

/**
 * Select a single item in choose_one group.
 */
export function selectChooseOne(
  prev: ComboSelection,
  group: PackageGroup,
  item: PackageItem,
  variantId?: number | null
): ComboSelection {
  const next: ComboSelection = { ...prev };

  for (const i of group.items) {
    delete next[i.id];
  }

  const chosenVariant =
    item.variant_mode === 'customer_choice'
      ? (variantId ?? item.variant_options?.[0]?.id ?? item.variant_id ?? null)
      : null;

  next[item.id] = {
    package_item_id: item.id,
    quantity: 1,
    variant_id: chosenVariant,
  };

  return next;
}

/**
 * Toggle an item in choose_multiple without repeats.
 */
export function toggleMultiple(
  prev: ComboSelection,
  group: PackageGroup,
  item: PackageItem
): ComboSelection {
  const next: ComboSelection = { ...prev };

  if (next[item.id]) {
    delete next[item.id];
    return next;
  }

  const currentCount = getGroupSelectionCount(group, next);
  if (group.max_selections != null && currentCount >= group.max_selections) {
    return prev;
  }

  const chosenVariant =
    item.variant_mode === 'customer_choice'
      ? (item.variant_options?.[0]?.id ?? item.variant_id ?? null)
      : null;

  next[item.id] = {
    package_item_id: item.id,
    quantity: 1,
    variant_id: chosenVariant,
  };

  return next;
}

/**
 * Increment quantity of an item (supports allow_repeats).
 */
export function incrementItem(
  prev: ComboSelection,
  group: PackageGroup,
  item: PackageItem
): ComboSelection {
  const currentCount = getGroupSelectionCount(group, prev);
  if (group.max_selections != null && currentCount >= group.max_selections) {
    return prev;
  }

  const next: ComboSelection = { ...prev };
  const existing = next[item.id];

  if (existing) {
    if (!group.allow_repeats) {
      return prev;
    }
    next[item.id] = {
      ...existing,
      quantity: existing.quantity + 1,
    };
  } else {
    const chosenVariant =
      item.variant_mode === 'customer_choice'
        ? (item.variant_options?.[0]?.id ?? item.variant_id ?? null)
        : null;

    next[item.id] = {
      package_item_id: item.id,
      quantity: 1,
      variant_id: chosenVariant,
    };
  }

  return next;
}

/**
 * Decrement quantity of an item.
 */
export function decrementItem(
  prev: ComboSelection,
  group: PackageGroup,
  item: PackageItem
): ComboSelection {
  const existing = prev[item.id];
  if (!existing) {
    return prev;
  }

  const next: ComboSelection = { ...prev };

  if (existing.quantity > 1) {
    next[item.id] = {
      ...existing,
      quantity: existing.quantity - 1,
    };
  } else {
    delete next[item.id];
  }

  return next;
}

/**
 * Update chosen variant for an item.
 */
export function setItemVariant(
  prev: ComboSelection,
  item: PackageItem,
  variantId: number
): ComboSelection {
  const existing = prev[item.id];
  if (!existing) {
    return prev;
  }

  return {
    ...prev,
    [item.id]: {
      ...existing,
      variant_id: variantId,
    },
  };
}

/**
 * Toggle an extra on a component item.
 */
export function toggleItemExtra(
  prev: ComboSelection,
  item: PackageItem,
  extraId: number
): ComboSelection {
  if (!item.allow_extras || !item.extra_option) {
    return prev;
  }

  const extraItem = item.extra_option.items?.find((i) => i.id === extraId);
  if (!extraItem) {
    return prev;
  }

  const existing: ItemSelection = prev[item.id] ?? {
    package_item_id: item.id,
    quantity: item.quantity > 0 ? item.quantity : 1,
    variant_id: item.variant_id ?? null,
  };

  const currentExtras = existing.extras ?? [];
  const exists = currentExtras.some((e) => e.id === extraId);

  if (exists) {
    const nextExtras = currentExtras.filter((e) => e.id !== extraId);
    return {
      ...prev,
      [item.id]: {
        ...existing,
        extras: nextExtras,
      },
    };
  }

  // Not selected yet
  const allowMultiple = item.extra_option.allow_multiple ?? true;
  if (!allowMultiple) {
    return {
      ...prev,
      [item.id]: {
        ...existing,
        extras: [{ id: extraId, quantity: 1 }],
      },
    };
  }

  const max = item.extra_option.max_selections;
  if (max != null && max > 0 && currentExtras.length >= max) {
    return prev;
  }

  return {
    ...prev,
    [item.id]: {
      ...existing,
      extras: [...currentExtras, { id: extraId, quantity: 1 }],
    },
  };
}

/**
 * Set exact quantity of an extra on a component item.
 */
export function setItemExtraQuantity(
  prev: ComboSelection,
  item: PackageItem,
  extraId: number,
  quantity: number
): ComboSelection {
  if (!item.allow_extras || !item.extra_option) {
    return prev;
  }

  const existing = prev[item.id];
  if (!existing || !existing.extras) {
    return prev;
  }

  const extraItem = item.extra_option.items?.find((i) => i.id === extraId);
  if (!extraItem) {
    return prev;
  }

  if (quantity <= 0) {
    return {
      ...prev,
      [item.id]: {
        ...existing,
        extras: existing.extras.filter((e) => e.id !== extraId),
      },
    };
  }

  const allowedQty = !extraItem.allow_quantity ? 1 : Math.max(1, Math.round(quantity));
  const nextExtras = existing.extras.map((e) =>
    e.id === extraId ? { ...e, quantity: allowedQty } : e
  );

  return {
    ...prev,
    [item.id]: {
      ...existing,
      extras: nextExtras,
    },
  };
}

/**
 * Change quantity of an extra by delta (+1 / -1).
 */
export function changeItemExtraQuantity(
  prev: ComboSelection,
  item: PackageItem,
  extraId: number,
  delta: number
): ComboSelection {
  const currentQty = prev[item.id]?.extras?.find((e) => e.id === extraId)?.quantity ?? 0;
  return setItemExtraQuantity(prev, item, extraId, currentQty + delta);
}

/**
 * Set custom note for a component item (max 200 chars).
 */
export function setItemNotes(
  prev: ComboSelection,
  item: PackageItem,
  notes: string | null
): ComboSelection {
  const existing: ItemSelection = prev[item.id] ?? {
    package_item_id: item.id,
    quantity: item.quantity > 0 ? item.quantity : 1,
    variant_id: item.variant_id ?? null,
  };

  const sanitized = notes != null ? notes.slice(0, 200) : null;

  return {
    ...prev,
    [item.id]: {
      ...existing,
      notes: sanitized,
    },
  };
}

/**
 * Validate a component's extras against its extra_option rules.
 */
export function validateComponentExtras(
  item: PackageItem,
  selection: ComboSelection
): ComponentExtrasValidationResult {
  const pick = selection[item.id];
  const extras = pick?.extras ?? [];
  const count = extras.length;

  if (!item.allow_extras) {
    if (count > 0) {
      return { valid: false, error: 'Extras are not allowed for this item.' };
    }
    return { valid: true };
  }

  if (!item.extra_option) {
    if (count > 0) {
      return { valid: false, error: 'This product does not allow extras.' };
    }
    return { valid: true };
  }

  const min = item.extra_option.min_selections ?? 0;
  const max = item.extra_option.max_selections ?? 0;
  const allowMultiple = item.extra_option.allow_multiple ?? true;

  if (count === 0) {
    if (min > 0) {
      return { valid: false, error: `Minimum ${min} extra selection(s) required.` };
    }
    return { valid: true };
  }

  const ids = extras.map((e) => e.id);
  if (new Set(ids).size !== ids.length) {
    return { valid: false, error: 'Duplicate extras are not allowed.' };
  }

  if (!allowMultiple && count > 1) {
    return { valid: false, error: 'Multiple extras are not allowed for this product.' };
  }

  if (min > 0 && count < min) {
    return { valid: false, error: `Minimum ${min} extra selection(s) required.` };
  }

  if (max > 0 && count > max) {
    return { valid: false, error: `Maximum ${max} extra selection(s) allowed.` };
  }

  for (const extra of extras) {
    const found = item.extra_option.items?.find((opt) => opt.id === extra.id);
    if (!found) {
      return { valid: false, error: 'One or more selected extras do not belong to this product.' };
    }

    if (extra.quantity > 1 && !found.allow_quantity) {
      return {
        valid: false,
        error: `Quantity greater than 1 is not allowed for extra '${found.name}'.`,
      };
    }

    if (extra.quantity < 1) {
      return { valid: false, error: 'Quantity must be at least 1.' };
    }
  }

  return { valid: true };
}

/**
 * Check if a group selection is valid.
 */
export function isGroupValid(group: PackageGroup, selection: ComboSelection): boolean {
  if (group.selection_type === 'all') {
    for (const item of group.items) {
      if (!validateComponentExtras(item, selection).valid) {
        return false;
      }
    }
    return true;
  }

  const count = getGroupSelectionCount(group, selection);

  if (group.selection_type === 'choose_one') {
    if (count !== 1) {
      return false;
    }
    for (const item of group.items) {
      const pick = selection[item.id];
      if (pick) {
        if (item.variant_mode === 'customer_choice') {
          if (!pick.variant_id || !item.variant_options?.some((v) => v.id === pick.variant_id)) {
            return false;
          }
        }
        if (!validateComponentExtras(item, selection).valid) {
          return false;
        }
      }
    }
    return true;
  }

  if (group.selection_type === 'choose_multiple') {
    const min = group.min_selections ?? 0;
    if (count < min) {
      return false;
    }
    if (group.max_selections != null && count > group.max_selections) {
      return false;
    }
    if (!group.allow_repeats) {
      for (const item of group.items) {
        const pick = selection[item.id];
        if (pick && pick.quantity > 1) {
          return false;
        }
      }
    }
    for (const item of group.items) {
      const pick = selection[item.id];
      if (pick) {
        if (item.variant_mode === 'customer_choice') {
          if (!pick.variant_id || !item.variant_options?.some((v) => v.id === pick.variant_id)) {
            return false;
          }
        }
        if (!validateComponentExtras(item, selection).valid) {
          return false;
        }
      }
    }
    return true;
  }

  return true;
}

/**
 * Formats a user-friendly instruction / counter message for a group.
 * Example: "Choose 2 · 1 selected"
 */
export function getGroupStatusMessage(
  group: PackageGroup,
  selection: ComboSelection,
  t?: (key: string, opts?: any) => string
): string {
  if (group.selection_type === 'all') {
    return t ? t('included', 'Included') : 'Included';
  }

  const count = getGroupSelectionCount(group, selection);
  const selectedText = t
    ? t('selectedCount', { count, defaultValue: `${count} selected` })
    : `${count} selected`;

  if (group.selection_type === 'choose_one') {
    const chooseText = t ? t('chooseOne', 'Choose 1') : 'Choose 1';
    return `${chooseText} · ${selectedText}`;
  }

  const min = group.min_selections;
  const max = group.max_selections;

  let ruleText = '';
  if (min != null && max != null && min === max) {
    ruleText = t
      ? t('chooseExact', { count: min, defaultValue: `Choose ${min}` })
      : `Choose ${min}`;
  } else if (min != null && max != null) {
    ruleText = t
      ? t('chooseRange', { min, max, defaultValue: `Choose ${min}–${max}` })
      : `Choose ${min}–${max}`;
  } else if (min != null && min > 0) {
    ruleText = t
      ? t('chooseAtLeast', { min, defaultValue: `Choose at least ${min}` })
      : `Choose at least ${min}`;
  } else if (max != null) {
    ruleText = t
      ? t('chooseUpTo', { max, defaultValue: `Choose up to ${max}` })
      : `Choose up to ${max}`;
  } else {
    ruleText = t ? t('optional', 'Optional') : 'Optional';
  }

  return `${ruleText} · ${selectedText}`;
}

/**
 * Overall package selection validity.
 */
export function isSelectionValid(pkg: ComboPackage, selection: ComboSelection): boolean {
  return pkg.groups.every((group) => isGroupValid(group, selection));
}

/**
 * Live unit price of one combo bundle according to contract formula:
 * package.price + Σ components (unitPriceAdjustment + extrasTotal())
 * Component extras are charged ONCE per parent combo unit: Σ extra.price × extra.quantity.
 */
export function calculateUnitPrice(pkg: ComboPackage, selection: ComboSelection): number {
  let total = Number(pkg.price) || 0;

  for (const group of pkg.groups) {
    if (group.selection_type === 'all') {
      for (const item of group.items) {
        const itemAdj = Number(item.price_adjustment) || 0;
        if (itemAdj > 0) {
          // The server charges an included item's adjustment once per combo, not per unit of its quantity.
          total += itemAdj;
        }

        // Component extras for included items
        const pick = selection[item.id];
        if (pick && pick.extras && pick.extras.length > 0 && item.extra_option?.items) {
          for (const extra of pick.extras) {
            const opt = item.extra_option.items.find((o) => o.id === extra.id);
            if (opt) {
              total += (Number(opt.price) || 0) * (extra.quantity || 1);
            }
          }
        }
      }
      continue;
    }

    for (const item of group.items) {
      const pick = selection[item.id];
      if (pick && pick.quantity > 0) {
        const itemAdj = Number(item.price_adjustment) || 0;
        let variantDelta = 0;
        if (item.variant_mode === 'customer_choice' && pick.variant_id) {
          const opt = item.variant_options?.find((v) => v.id === pick.variant_id);
          if (opt) {
            variantDelta = Number(opt.price_delta) || 0;
          }
        }
        total += (itemAdj + variantDelta) * pick.quantity;

        // Component extras for picked items
        if (pick.extras && pick.extras.length > 0 && item.extra_option?.items) {
          for (const extra of pick.extras) {
            const opt = item.extra_option.items.find((o) => o.id === extra.id);
            if (opt) {
              total += (Number(opt.price) || 0) * (extra.quantity || 1);
            }
          }
        }
      }
    }
  }

  return Math.round(total * 100) / 100;
}

/**
 * Convert user selection into the POST /api/cart/items composite payload.
 */
export function toCartPayload(
  pkg: ComboPackage,
  selection: ComboSelection,
  quantity: number = 1,
  notes?: string | null
): AddPackageToCartPayload {
  const selections: PackageCartLineSelection[] = [];

  for (const group of pkg.groups) {
    if (group.selection_type === 'all') {
      for (const item of group.items) {
        const pick = selection[item.id];
        if (pick) {
          const hasExtras = Boolean(pick.extras && pick.extras.length > 0);
          const hasNotes = Boolean(pick.notes && pick.notes.trim() !== '');
          if (hasExtras || hasNotes) {
            selections.push({
              package_item_id: item.id,
              quantity: 1,
              variant_id:
                item.variant_mode === 'customer_choice'
                  ? (pick.variant_id ?? null)
                  : null,
              extras: hasExtras ? pick.extras : undefined,
              notes: hasNotes ? pick.notes!.trim().slice(0, 200) : undefined,
            });
          }
        }
      }
      continue;
    }

    for (const item of group.items) {
      const pick = selection[item.id];
      if (pick && pick.quantity > 0) {
        const hasExtras = Boolean(pick.extras && pick.extras.length > 0);
        const hasNotes = Boolean(pick.notes && pick.notes.trim() !== '');
        selections.push({
          package_item_id: item.id,
          quantity: pick.quantity,
          variant_id:
            item.variant_mode === 'customer_choice'
              ? (pick.variant_id ?? null)
              : null,
          extras: hasExtras ? pick.extras : undefined,
          notes: hasNotes ? pick.notes!.trim().slice(0, 200) : undefined,
        });
      }
    }
  }

  const payload: AddPackageToCartPayload = {
    type: 'package',
    package_id: pkg.id,
    quantity: Math.max(1, Math.min(99, Math.round(quantity))),
    selections,
  };

  if (notes && notes.trim() !== '') {
    payload.notes = notes.trim().slice(0, 500);
  }

  return payload;
}

/**
 * Rebuild a selection from an existing cart line's components for edit mode.
 */
export function fromCartComponents(
  pkg: ComboPackage,
  components: CartLineComponent[] = []
): ComboSelection {
  const selection: ComboSelection = {};

  for (const group of pkg.groups) {
    if (group.selection_type === 'all') {
      for (const item of group.items) {
        const matchedComp = components.find(
          (c) =>
            (c.source_id != null && Number(c.source_id) === item.id) ||
            (c.product_id === item.product_id &&
              (c.group_name === group.name || (group.name_ar && c.group_name_ar === group.name_ar)))
        );

        if (matchedComp) {
          const rawExtras = matchedComp.extras;
          const extras =
            Array.isArray(rawExtras) && rawExtras.length > 0
              ? rawExtras.map((e: any) => ({
                  id: Number(e.id || e.extra_option_item_id),
                  quantity: Number(e.quantity) || 1,
                }))
              : undefined;
          const notes = matchedComp.notes || null;

          if (extras || notes) {
            selection[item.id] = {
              package_item_id: item.id,
              quantity: item.quantity > 0 ? item.quantity : 1,
              variant_id:
                item.variant_mode === 'customer_choice'
                  ? matchedComp.variant_id ?? null
                  : null,
              extras,
              notes,
            };
          }
        }
      }
      continue;
    }

    for (const item of group.items) {
      const matchedComp = components.find(
        (c) =>
          (c.source_id != null && Number(c.source_id) === item.id) ||
          (c.product_id === item.product_id &&
            (c.group_name === group.name || (group.name_ar && c.group_name_ar === group.name_ar)))
      );

      if (matchedComp) {
        const itemBaseQty = item.quantity > 0 ? item.quantity : 1;
        const compQty = Number(matchedComp.quantity) || 1;
        const pickedQuantity = Math.max(1, Math.round(compQty / itemBaseQty));

        const rawExtras = matchedComp.extras;
        const extras =
          Array.isArray(rawExtras) && rawExtras.length > 0
            ? rawExtras.map((e: any) => ({
                id: Number(e.id || e.extra_option_item_id),
                quantity: Number(e.quantity) || 1,
              }))
            : undefined;
        const notes = matchedComp.notes || null;

        selection[item.id] = {
          package_item_id: item.id,
          quantity: pickedQuantity,
          variant_id:
            item.variant_mode === 'customer_choice'
              ? matchedComp.variant_id ?? null
              : null,
          extras,
          notes,
        };
      }
    }
  }

  return selection;
}
