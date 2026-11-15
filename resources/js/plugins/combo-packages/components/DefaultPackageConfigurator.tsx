import React, { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Minus, Check, AlertCircle } from 'lucide-react';
import { useFormatMoney } from '@/lib/money';
import { useRemoveCartItem } from '@/plugins/cart-engine/api/useRemoveCartItem';
import { CartItem } from '@/plugins/cart-engine/types';
import { ComboPackage, PackageGroup, PackageItem } from '../types';
import {
  ComboSelection,
  initialSelection,
  selectChooseOne,
  toggleMultiple,
  incrementItem,
  decrementItem,
  setItemVariant,
  toggleItemExtra,
  changeItemExtraQuantity,
  setItemNotes,
  isGroupValid,
  getGroupStatusMessage,
  isSelectionValid,
  calculateUnitPrice,
  toCartPayload,
  fromCartComponents,
} from '../lib/selection';
import {
  getLocalizedName,
  getLocalizedDescription,
  availabilityBadge,
} from '../lib/packageDisplay';
import {
  useAddPackageToCart,
  mapCartErrorsToGroups,
} from '../api/useAddPackageToCart';
import ComboComponentCustomizer from './ComboComponentCustomizer';

export interface PackageConfiguratorProps {
  packageData: ComboPackage;
  cartItem?: CartItem | null;
  onClose?: () => void;
  onSuccess?: () => void;
}

export default function DefaultPackageConfigurator({
  packageData,
  cartItem,
  onClose,
  onSuccess,
}: PackageConfiguratorProps) {
  const { t, i18n } = useTranslation();
  const formatMoney = useFormatMoney();
  const isRTL = i18n.language === 'ar' || i18n.language?.startsWith('ar');

  const isEditMode = Boolean(cartItem);
  const isAvailable = packageData.available_now !== false;
  const unavailableBadge = availabilityBadge(packageData, undefined, i18n.language);

  const maxQuantity = useMemo(() => {
    const limits: number[] = [];
    if (typeof packageData.max_per_order === 'number' && packageData.max_per_order > 0) {
      limits.push(packageData.max_per_order);
    }
    if (typeof packageData.remaining_today === 'number' && packageData.remaining_today >= 0) {
      limits.push(packageData.remaining_today);
    }
    return limits.length > 0 ? Math.min(...limits) : 99;
  }, [packageData.max_per_order, packageData.remaining_today]);

  const [selection, setSelection] = useState<ComboSelection>(() => {
    if (cartItem && cartItem.components) {
      return fromCartComponents(packageData, cartItem.components);
    }
    return initialSelection(packageData);
  });

  const [quantity, setQuantity] = useState<number>(() => {
    if (cartItem && cartItem.quantity) {
      const q = Math.max(1, Math.round(Number(cartItem.quantity)));
      return maxQuantity > 0 ? Math.min(q, maxQuantity) : q;
    }
    return maxQuantity > 0 ? Math.min(1, maxQuantity) : 1;
  });

  const [notes, setNotes] = useState<string>(() => {
    return cartItem?.notes || '';
  });

  const [expandedCustomizers, setExpandedCustomizers] = useState<Record<number, boolean>>({});

  const [serverErrors, setServerErrors] = useState<{
    general?: string;
    groupErrors: Record<number, string>;
    itemErrors: Record<number, string>;
  }>({ groupErrors: {}, itemErrors: {} });

  const addPackageMutation = useAddPackageToCart({
    package: packageData,
    suppressToast: false,
  });
  const removeCartItemMutation = useRemoveCartItem();

  const isSubmitting = addPackageMutation.isPending || removeCartItemMutation.isPending;

  const unitPrice = useMemo(() => {
    return calculateUnitPrice(packageData, selection);
  }, [packageData, selection]);

  const totalPrice = useMemo(() => {
    return unitPrice * quantity;
  }, [unitPrice, quantity]);

  const isValid = useMemo(() => {
    return isSelectionValid(packageData, selection);
  }, [packageData, selection]);

  const sortedGroups = useMemo(() => {
    return [...(packageData.groups || [])].sort((a, b) => a.sort_order - b.sort_order);
  }, [packageData.groups]);

  const toggleCustomizer = (itemId: number) => {
    setExpandedCustomizers((prev) => ({
      ...prev,
      [itemId]: !prev[itemId],
    }));
  };

  const handleToggleExtra = (item: PackageItem, extraId: number) => {
    setSelection((prev) => toggleItemExtra(prev, item, extraId));
  };

  const handleChangeExtraQty = (item: PackageItem, extraId: number, delta: number) => {
    setSelection((prev) => changeItemExtraQuantity(prev, item, extraId, delta));
  };

  const handleSetNotes = (item: PackageItem, itemNotes: string) => {
    setSelection((prev) => setItemNotes(prev, item, itemNotes));
  };

  const handleSubmit = async () => {
    if (!isValid || isSubmitting || !isAvailable) return;

    setServerErrors({ groupErrors: {}, itemErrors: {} });
    const payload = toCartPayload(packageData, selection, quantity, notes);

    try {
      if (isEditMode && cartItem) {
        await removeCartItemMutation.mutateAsync(cartItem.id);
        try {
          await addPackageMutation.mutateAsync(payload);
        } catch (addError) {
          // Put the original line back so a rejected edit never loses the customer's combo.
          const original = fromCartComponents(packageData, cartItem.components ?? []);
          await addPackageMutation
            .mutateAsync(toCartPayload(packageData, original, Number(cartItem.quantity) || 1, cartItem.notes ?? ''))
            .catch(() => undefined);
          throw addError;
        }
      } else {
        await addPackageMutation.mutateAsync(payload);
      }
      onSuccess?.();
      onClose?.();
    } catch (err: any) {
      const mapped = mapCartErrorsToGroups(err, packageData, payload);
      setServerErrors(mapped);
      if (mapped.itemErrors && Object.keys(mapped.itemErrors).length > 0) {
        setExpandedCustomizers((prev) => {
          const next = { ...prev };
          for (const k of Object.keys(mapped.itemErrors)) {
            next[Number(k)] = true;
          }
          return next;
        });
      }
    }
  };

  return (
    <div
      data-testid="combo-configurator"
      className="flex flex-col h-full max-h-[85vh] bg-background text-foreground"
      dir={isRTL ? 'rtl' : 'ltr'}
    >
      {/* Header */}
      <div className="p-4 border-b shrink-0 flex items-start justify-between gap-4">
        <div>
          <h2 className="text-lg font-bold">
            {getLocalizedName(packageData, i18n.language)}
          </h2>
          {packageData.description && (
            <p className="text-xs text-muted-foreground mt-0.5">
              {getLocalizedDescription(packageData, i18n.language)}
            </p>
          )}
        </div>
      </div>

      {/* Groups List */}
      <div className="flex-1 overflow-y-auto p-4 space-y-6">
        {!isAvailable && (
          <div
            data-testid="combo-unavailable-banner"
            className="p-3 rounded-md bg-destructive/10 text-destructive text-xs flex items-center gap-2 border border-destructive/20 font-medium"
          >
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>
              {unavailableBadge.text || t('packageUnavailable', 'This combo package is currently unavailable.')}
            </span>
          </div>
        )}

        {serverErrors.general && (
          <div className="p-3 rounded-md bg-destructive/10 text-destructive text-xs flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{serverErrors.general}</span>
          </div>
        )}

        {sortedGroups.map((group) => {
          const sortedItems = [...group.items].sort((a, b) => a.sort_order - b.sort_order);
          const groupError = serverErrors.groupErrors[group.id];
          const isGroupCurrentlyValid = isGroupValid(group, selection);
          const statusMsg = getGroupStatusMessage(group, selection, t);

          return (
            <div
              key={group.id}
              data-testid={`combo-group-${group.id}`}
              className="border rounded-lg p-3 space-y-3 bg-card"
            >
              {/* Group Title and Info */}
              <div className="flex items-start justify-between gap-2 border-b pb-2">
                <div>
                  <h3 className="font-semibold text-sm">
                    {getLocalizedName({ name: group.name || '', name_ar: group.name_ar }, i18n.language)}
                  </h3>
                  {group.description && (
                    <p className="text-xs text-muted-foreground mt-0.5">
                      {getLocalizedDescription(group, i18n.language)}
                    </p>
                  )}
                </div>
                <span
                  className={`text-xs px-2 py-0.5 rounded-full font-medium shrink-0 ${
                    isGroupCurrentlyValid
                      ? 'bg-secondary text-secondary-foreground'
                      : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                  }`}
                >
                  {statusMsg}
                </span>
              </div>

              {groupError && (
                <div
                  data-testid={`combo-group-error-${group.id}`}
                  className="text-xs text-destructive font-medium"
                >
                  {groupError}
                </div>
              )}

              {/* Group Items */}
              <div className="space-y-2">
                {/* 1. 'all' items */}
                {group.selection_type === 'all' && (
                  <div className="space-y-1.5">
                    {sortedItems.map((item) => {
                      const itemName = getLocalizedName(item.product, i18n.language);
                      const variantName = item.variant
                        ? getLocalizedName(item.variant, i18n.language)
                        : null;
                      const priceAdj = Number(item.price_adjustment) || 0;

                      return (
                        <div
                          key={item.id}
                          className="p-2.5 rounded-md bg-muted/40 text-xs border border-border/40"
                        >
                          <div
                            data-testid={`combo-option-${item.id}`}
                            className="flex items-center justify-between"
                          >
                            <div className="flex items-center gap-2">
                              <Check className="w-3.5 h-3.5 text-primary shrink-0" />
                              <span className="font-medium">
                                {item.quantity > 1 ? `${item.quantity}× ` : ''}
                                {itemName}
                                {variantName ? ` (${variantName})` : ''}
                              </span>
                            </div>
                            {priceAdj > 0 && (
                              <span className="text-muted-foreground font-semibold">
                                +{formatMoney(priceAdj)}
                              </span>
                            )}
                          </div>

                          {item.allow_extras && (
                            <ComboComponentCustomizer
                              item={item}
                              selection={selection}
                              isExpanded={Boolean(expandedCustomizers[item.id])}
                              onToggleExpand={() => toggleCustomizer(item.id)}
                              onToggleExtra={(extraId) => handleToggleExtra(item, extraId)}
                              onChangeExtraQty={(extraId, delta) => handleChangeExtraQty(item, extraId, delta)}
                              onSetNotes={(n) => handleSetNotes(item, n)}
                              serverError={serverErrors.itemErrors[item.id]}
                            />
                          )}
                        </div>
                      );
                    })}
                  </div>
                )}

                {/* 2. 'choose_one' items (Radio) */}
                {group.selection_type === 'choose_one' && (
                  <div className="space-y-2">
                    {sortedItems.map((item) => {
                      const isSelected = Boolean(selection[item.id]);
                      const currentPick = selection[item.id];
                      const itemName = getLocalizedName(item.product, i18n.language);
                      const fixedVariantName = item.variant
                        ? getLocalizedName(item.variant, i18n.language)
                        : null;
                      const priceAdj = Number(item.price_adjustment) || 0;

                      return (
                        <div
                          key={item.id}
                          className={`p-2.5 rounded-lg border transition-colors ${
                            isSelected
                              ? 'border-primary bg-primary/5'
                              : 'border-border hover:bg-muted/30'
                          }`}
                        >
                          <div
                            data-testid={`combo-option-${item.id}`}
                            className="cursor-pointer"
                            onClick={() => {
                              setSelection((prev) => selectChooseOne(prev, group, item));
                            }}
                          >
                            <div className="flex items-center justify-between gap-2">
                              <div className="flex items-center gap-2.5">
                                <div
                                  className={`w-4 h-4 rounded-full border flex items-center justify-center shrink-0 ${
                                    isSelected ? 'border-primary' : 'border-muted-foreground'
                                  }`}
                                >
                                  {isSelected && (
                                    <div className="w-2 h-2 rounded-full bg-primary" />
                                  )}
                                </div>
                                <span className="text-xs font-medium">
                                  {itemName}
                                  {fixedVariantName ? ` (${fixedVariantName})` : ''}
                                </span>
                              </div>
                              {priceAdj > 0 && (
                                <span className="text-xs text-primary font-semibold shrink-0">
                                  +{formatMoney(priceAdj)}
                                </span>
                              )}
                            </div>
                          </div>

                          {/* Customer choice variant picker */}
                          {isSelected &&
                            item.variant_mode === 'customer_choice' &&
                            item.variant_options &&
                            item.variant_options.length > 0 && (
                              <div
                                className="mt-2.5 pt-2 border-t flex flex-wrap gap-1.5"
                                onClick={(e) => e.stopPropagation()}
                              >
                                {item.variant_options.map((variant) => {
                                  const isVariantSelected =
                                    currentPick?.variant_id === variant.id;
                                  const vName = getLocalizedName(variant, i18n.language);
                                  const delta = Number(variant.price_delta) || 0;

                                  return (
                                    <button
                                      type="button"
                                      key={variant.id}
                                      data-testid={`combo-variant-${item.id}-${variant.id}`}
                                      onClick={() => {
                                        setSelection((prev) =>
                                          setItemVariant(prev, item, variant.id)
                                        );
                                      }}
                                      className={`px-2 py-1 rounded text-[11px] font-medium border transition-colors ${
                                        isVariantSelected
                                          ? 'bg-primary text-primary-foreground border-primary'
                                          : 'bg-background hover:bg-muted border-border text-foreground'
                                      }`}
                                    >
                                      {vName}
                                      {delta > 0 ? ` (+${formatMoney(delta)})` : ''}
                                    </button>
                                  );
                                })}
                              </div>
                            )}

                          {/* Customizer if selected and allow_extras */}
                          {isSelected && item.allow_extras && (
                            <ComboComponentCustomizer
                              item={item}
                              selection={selection}
                              isExpanded={Boolean(expandedCustomizers[item.id])}
                              onToggleExpand={() => toggleCustomizer(item.id)}
                              onToggleExtra={(extraId) => handleToggleExtra(item, extraId)}
                              onChangeExtraQty={(extraId, delta) => handleChangeExtraQty(item, extraId, delta)}
                              onSetNotes={(n) => handleSetNotes(item, n)}
                              serverError={serverErrors.itemErrors[item.id]}
                            />
                          )}
                        </div>
                      );
                    })}
                  </div>
                )}

                {/* 3. 'choose_multiple' items */}
                {group.selection_type === 'choose_multiple' && (
                  <div className="space-y-2">
                    {sortedItems.map((item) => {
                      const isSelected = Boolean(selection[item.id]);
                      const currentPick = selection[item.id];
                      const pickQty = currentPick?.quantity || 0;
                      const itemName = getLocalizedName(item.product, i18n.language);
                      const fixedVariantName = item.variant
                        ? getLocalizedName(item.variant, i18n.language)
                        : null;
                      const priceAdj = Number(item.price_adjustment) || 0;

                      return (
                        <div
                          key={item.id}
                          className={`p-2.5 rounded-lg border transition-colors ${
                            isSelected
                              ? 'border-primary bg-primary/5'
                              : 'border-border'
                          }`}
                        >
                          <div
                            data-testid={`combo-option-${item.id}`}
                            className="flex items-center justify-between gap-2"
                          >
                            <div
                              className="flex items-center gap-2.5 flex-1 cursor-pointer"
                              onClick={() => {
                                if (!group.allow_repeats) {
                                  setSelection((prev) => toggleMultiple(prev, group, item));
                                }
                              }}
                            >
                              {!group.allow_repeats && (
                                <div
                                  className={`w-4 h-4 rounded border flex items-center justify-center shrink-0 ${
                                    isSelected
                                      ? 'bg-primary border-primary text-primary-foreground'
                                      : 'border-muted-foreground'
                                  }`}
                                >
                                  {isSelected && <Check className="w-3 h-3" />}
                                </div>
                              )}
                              <span className="text-xs font-medium">
                                {itemName}
                                {fixedVariantName ? ` (${fixedVariantName})` : ''}
                              </span>
                            </div>

                            <div className="flex items-center gap-2 shrink-0">
                              {priceAdj > 0 && (
                                <span className="text-xs text-primary font-semibold">
                                  +{formatMoney(priceAdj)}
                                </span>
                              )}

                              {/* Stepper when allow_repeats */}
                              {group.allow_repeats && (
                                <div className="flex items-center border rounded-md">
                                  <button
                                    type="button"
                                    disabled={pickQty <= 0}
                                    onClick={() =>
                                      setSelection((prev) => decrementItem(prev, group, item))
                                    }
                                    className="p-1 hover:bg-muted disabled:opacity-30"
                                  >
                                    <Minus className="w-3 h-3" />
                                  </button>
                                  <span className="px-2 text-xs font-semibold min-w-[1.5rem] text-center">
                                    {pickQty}
                                  </span>
                                  <button
                                    type="button"
                                    onClick={() =>
                                      setSelection((prev) => incrementItem(prev, group, item))
                                    }
                                    className="p-1 hover:bg-muted"
                                  >
                                    <Plus className="w-3 h-3" />
                                  </button>
                                </div>
                              )}
                            </div>
                          </div>

                          {/* Customer choice variant picker */}
                          {isSelected &&
                            item.variant_mode === 'customer_choice' &&
                            item.variant_options &&
                            item.variant_options.length > 0 && (
                              <div className="mt-2.5 pt-2 border-t flex flex-wrap gap-1.5">
                                {item.variant_options.map((variant) => {
                                  const isVariantSelected =
                                    currentPick?.variant_id === variant.id;
                                  const vName = getLocalizedName(variant, i18n.language);
                                  const delta = Number(variant.price_delta) || 0;

                                  return (
                                    <button
                                      type="button"
                                      key={variant.id}
                                      data-testid={`combo-variant-${item.id}-${variant.id}`}
                                      onClick={() => {
                                        setSelection((prev) =>
                                          setItemVariant(prev, item, variant.id)
                                        );
                                      }}
                                      className={`px-2 py-1 rounded text-[11px] font-medium border transition-colors ${
                                        isVariantSelected
                                          ? 'bg-primary text-primary-foreground border-primary'
                                          : 'bg-background hover:bg-muted border-border text-foreground'
                                      }`}
                                    >
                                      {vName}
                                      {delta > 0 ? ` (+${formatMoney(delta)})` : ''}
                                    </button>
                                  );
                                })}
                              </div>
                            )}

                          {/* Customizer if selected and allow_extras */}
                          {isSelected && item.allow_extras && (
                            <ComboComponentCustomizer
                              item={item}
                              selection={selection}
                              isExpanded={Boolean(expandedCustomizers[item.id])}
                              onToggleExpand={() => toggleCustomizer(item.id)}
                              onToggleExtra={(extraId) => handleToggleExtra(item, extraId)}
                              onChangeExtraQty={(extraId, delta) => handleChangeExtraQty(item, extraId, delta)}
                              onSetNotes={(n) => handleSetNotes(item, n)}
                              serverError={serverErrors.itemErrors[item.id]}
                            />
                          )}
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>
          );
        })}

        {/* Special Instructions / Notes */}
        <div className="space-y-1.5">
          <label htmlFor="combo-notes" className="text-xs font-medium text-foreground">
            {t('specialInstructions', 'Special instructions or notes')}
          </label>
          <textarea
            id="combo-notes"
            data-testid="combo-notes"
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            maxLength={500}
            rows={2}
            placeholder={t(
              'specialInstructionsPlaceholder',
              'Any special requests or preparation notes...'
            )}
            className="w-full text-xs p-2.5 rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
          />
        </div>
      </div>

      {/* Sticky Footer */}
      <div className="p-4 border-t bg-card shrink-0 space-y-3">
        <div className="flex items-center justify-between">
          <div>
            <span className="text-xs text-muted-foreground block">{t('total', 'Total')}</span>
            <span data-testid="combo-total" className="text-lg font-bold text-primary">
              {formatMoney(totalPrice)}
            </span>
          </div>

          {/* Quantity Stepper & Limits */}
          <div className="flex flex-col items-end gap-1">
            <div className="flex items-center border rounded-md p-0.5">
              <button
                type="button"
                data-testid="combo-qty-dec"
                disabled={quantity <= 1 || isSubmitting || !isAvailable}
                onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                className="p-1.5 hover:bg-muted rounded disabled:opacity-30"
                aria-label="Decrease combo quantity"
              >
                <Minus className="w-3.5 h-3.5" />
              </button>
              <span className="w-8 text-center text-xs font-semibold">{quantity}</span>
              <button
                type="button"
                data-testid="combo-qty-inc"
                disabled={quantity >= maxQuantity || isSubmitting || !isAvailable}
                onClick={() => setQuantity((q) => Math.min(maxQuantity, q + 1))}
                className="p-1.5 hover:bg-muted rounded disabled:opacity-30"
                aria-label="Increase combo quantity"
              >
                <Plus className="w-3.5 h-3.5" />
              </button>
            </div>
            {packageData.max_per_order && packageData.max_per_order > 0 && (
              <span className="text-[10px] text-muted-foreground font-medium">
                {t('maxPerOrder', 'Max {{count}} per order', { count: packageData.max_per_order })}
              </span>
            )}
          </div>
        </div>

        <button
          type="button"
          data-testid="combo-submit"
          disabled={!isValid || isSubmitting || !isAvailable}
          onClick={handleSubmit}
          className="w-full py-2.5 px-4 rounded-md bg-primary text-primary-foreground font-medium text-xs hover:bg-primary/90 disabled:opacity-50 transition-colors shadow-sm"
        >
          {isSubmitting
            ? t('loading', 'Loading...')
            : !isAvailable
            ? (unavailableBadge.text || t('packageUnavailable', 'Unavailable'))
            : isEditMode
            ? t('updateCombo', 'Update Combo')
            : t('addToCart', 'Add to Cart')}
        </button>
      </div>
    </div>
  );
}
