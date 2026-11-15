import React, { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Minus, Check, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useFormatMoney } from '@/lib/money';
import { useRemoveCartItem } from '@/plugins/cart-engine/api/useRemoveCartItem';
import { CartItem } from '@/plugins/cart-engine/types';
import { ComboPackage, PackageGroup, PackageItem } from '@/plugins/combo-packages/types';
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
} from '@/plugins/combo-packages/lib/selection';
import {
  getLocalizedName,
  getLocalizedDescription,
  availabilityBadge,
} from '@/plugins/combo-packages/lib/packageDisplay';
import {
  useAddPackageToCart,
  mapCartErrorsToGroups,
} from '@/plugins/combo-packages/api/useAddPackageToCart';
import ComboComponentCustomizer from '@/plugins/combo-packages/components/ComboComponentCustomizer';

export interface ThemedPackageConfiguratorProps {
  packageData: ComboPackage;
  cartItem?: CartItem | null;
  onClose?: () => void;
  onSuccess?: () => void;
}

export default function ThemedPackageConfigurator({
  packageData,
  cartItem,
  onClose,
  onSuccess,
}: ThemedPackageConfiguratorProps) {
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
      <div className="p-5 border-b shrink-0 flex items-start justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <h2 className="text-xl font-bold tracking-tight">
              {getLocalizedName(packageData, i18n.language)}
            </h2>
            {packageData.badge && (
              <Badge variant="secondary" className="text-xs font-semibold">
                {getLocalizedName({ name: packageData.badge, name_ar: packageData.badge_ar }, i18n.language)}
              </Badge>
            )}
          </div>
          {packageData.description && (
            <p className="text-sm text-muted-foreground">
              {getLocalizedDescription(packageData, i18n.language)}
            </p>
          )}
        </div>
      </div>

      {/* Groups List */}
      <div className="flex-1 overflow-y-auto p-5 space-y-6">
        {!isAvailable && (
          <div
            data-testid="combo-unavailable-banner"
            className="p-3.5 rounded-lg bg-destructive/10 text-destructive text-sm flex items-center gap-2 border border-destructive/20 font-medium"
          >
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>
              {unavailableBadge.text || t('packageUnavailable', 'This combo package is currently unavailable.')}
            </span>
          </div>
        )}

        {serverErrors.general && (
          <div className="p-3.5 rounded-lg bg-destructive/10 text-destructive text-sm flex items-center gap-2 border border-destructive/20">
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
            <Card
              key={group.id}
              data-testid={`combo-group-${group.id}`}
              className="border shadow-none overflow-hidden"
            >
              <div className="p-4 bg-muted/30 border-b flex items-start justify-between gap-3">
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
                <Badge
                  variant={isGroupCurrentlyValid ? 'outline' : 'secondary'}
                  className={`text-xs shrink-0 ${
                    !isGroupCurrentlyValid
                      ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20'
                      : ''
                  }`}
                >
                  {statusMsg}
                </Badge>
              </div>

              {groupError && (
                <div
                  data-testid={`combo-group-error-${group.id}`}
                  className="px-4 pt-3 text-xs text-destructive font-medium"
                >
                  {groupError}
                </div>
              )}

              <CardContent className="p-4 space-y-2">
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
                          className="p-3 rounded-lg bg-muted/40 text-xs border border-border/40"
                        >
                          <div
                            data-testid={`combo-option-${item.id}`}
                            className="flex items-center justify-between"
                          >
                            <div className="flex items-center gap-2">
                              <Check className="w-4 h-4 text-primary shrink-0" />
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

                {/* 2. 'choose_one' items */}
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
                          className={`p-3 rounded-lg border transition-all ${
                            isSelected
                              ? 'border-primary bg-primary/5 shadow-xs'
                              : 'border-border/60 hover:border-border hover:bg-muted/20'
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
                              <div className="flex items-center gap-3">
                                <div
                                  className={`w-4 h-4 rounded-full border flex items-center justify-center shrink-0 transition-colors ${
                                    isSelected
                                      ? 'border-primary bg-primary text-primary-foreground'
                                      : 'border-muted-foreground/60'
                                  }`}
                                >
                                  {isSelected && (
                                    <div className="w-1.5 h-1.5 rounded-full bg-white" />
                                  )}
                                </div>
                                <span className="text-sm font-medium">
                                  {itemName}
                                  {fixedVariantName ? ` (${fixedVariantName})` : ''}
                                </span>
                              </div>
                              {priceAdj > 0 && (
                                <span className="text-xs text-primary font-bold shrink-0">
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
                                className="mt-3 pt-2.5 border-t border-border/50 flex flex-wrap gap-2"
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
                                      className={`px-2.5 py-1 rounded-md text-xs font-medium border transition-colors ${
                                        isVariantSelected
                                          ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                                          : 'bg-background hover:bg-muted/50 border-input text-foreground'
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
                          className={`p-3 rounded-lg border transition-all ${
                            isSelected
                              ? 'border-primary bg-primary/5 shadow-xs'
                              : 'border-border/60 hover:border-border'
                          }`}
                        >
                          <div
                            data-testid={`combo-option-${item.id}`}
                            className="flex items-center justify-between gap-2"
                          >
                            <div
                              className="flex items-center gap-3 flex-1 cursor-pointer"
                              onClick={() => {
                                if (!group.allow_repeats) {
                                  setSelection((prev) => toggleMultiple(prev, group, item));
                                }
                              }}
                            >
                              {!group.allow_repeats && (
                                <div
                                  className={`w-4 h-4 rounded border flex items-center justify-center shrink-0 transition-colors ${
                                    isSelected
                                      ? 'bg-primary border-primary text-primary-foreground'
                                      : 'border-muted-foreground/60'
                                  }`}
                                >
                                  {isSelected && <Check className="w-3 h-3" />}
                                </div>
                              )}
                              <span className="text-sm font-medium">
                                {itemName}
                                {fixedVariantName ? ` (${fixedVariantName})` : ''}
                              </span>
                            </div>

                            <div className="flex items-center gap-3 shrink-0">
                              {priceAdj > 0 && (
                                <span className="text-xs text-primary font-bold">
                                  +{formatMoney(priceAdj)}
                                </span>
                              )}

                              {group.allow_repeats && (
                                <div className="flex items-center border rounded-md">
                                  <button
                                    type="button"
                                    disabled={pickQty <= 0}
                                    onClick={() =>
                                      setSelection((prev) => decrementItem(prev, group, item))
                                    }
                                    className="p-1.5 hover:bg-muted disabled:opacity-30 rounded-s"
                                    aria-label="Decrease option quantity"
                                  >
                                    <Minus className="w-3 h-3" />
                                  </button>
                                  <span className="px-2.5 text-xs font-bold min-w-[1.75rem] text-center">
                                    {pickQty}
                                  </span>
                                  <button
                                    type="button"
                                    onClick={() =>
                                      setSelection((prev) => incrementItem(prev, group, item))
                                    }
                                    className="p-1.5 hover:bg-muted rounded-e"
                                    aria-label="Increase option quantity"
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
                              <div className="mt-3 pt-2.5 border-t border-border/50 flex flex-wrap gap-2">
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
                                      className={`px-2.5 py-1 rounded-md text-xs font-medium border transition-colors ${
                                        isVariantSelected
                                          ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                                          : 'bg-background hover:bg-muted/50 border-input text-foreground'
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
              </CardContent>
            </Card>
          );
        })}

        {/* Special Instructions / Notes */}
        <div className="space-y-2 pt-2">
          <Label htmlFor="combo-notes" className="text-xs font-medium">
            {t('specialInstructions', 'Special instructions or notes')}
          </Label>
          <Textarea
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
            className="text-xs resize-none"
          />
        </div>
      </div>

      {/* Sticky Footer */}
      <div className="p-5 border-t bg-card shrink-0 space-y-4 shadow-lg">
        <div className="flex items-center justify-between">
          <div>
            <span className="text-xs text-muted-foreground block">{t('total', 'Total')}</span>
            <span data-testid="combo-total" className="text-xl font-bold text-primary">
              {formatMoney(totalPrice)}
            </span>
          </div>

          {/* Combo Quantity Stepper & Limits */}
          <div className="flex flex-col items-end gap-1">
            <div className="flex items-center border border-input rounded-md p-0.5 bg-background">
              <Button
                type="button"
                variant="ghost"
                size="icon"
                data-testid="combo-qty-dec"
                disabled={quantity <= 1 || isSubmitting || !isAvailable}
                onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                className="h-8 w-8"
                aria-label="Decrease combo quantity"
              >
                <Minus className="w-3.5 h-3.5" />
              </Button>
              <span className="w-10 text-center text-sm font-semibold">{quantity}</span>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                data-testid="combo-qty-inc"
                disabled={quantity >= maxQuantity || isSubmitting || !isAvailable}
                onClick={() => setQuantity((q) => Math.min(maxQuantity, q + 1))}
                className="h-8 w-8"
                aria-label="Increase combo quantity"
              >
                <Plus className="w-3.5 h-3.5" />
              </Button>
            </div>
            {packageData.max_per_order && packageData.max_per_order > 0 && (
              <span className="text-[11px] text-muted-foreground font-medium">
                {t('maxPerOrder', 'Max {{count}} per order', { count: packageData.max_per_order })}
              </span>
            )}
          </div>
        </div>

        <Button
          type="button"
          data-testid="combo-submit"
          disabled={!isValid || isSubmitting || !isAvailable}
          onClick={handleSubmit}
          className="w-full font-semibold shadow-sm"
          size="lg"
        >
          {isSubmitting
            ? t('loading', 'Loading...')
            : !isAvailable
            ? (unavailableBadge.text || t('packageUnavailable', 'Unavailable'))
            : isEditMode
            ? t('updateCombo', 'Update Combo')
            : t('addToCart', 'Add to Cart')}
        </Button>
      </div>
    </div>
  );
}
