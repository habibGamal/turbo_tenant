import React from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, ChevronUp, SlidersHorizontal, Plus, Minus } from 'lucide-react';
import { useFormatMoney } from '@/lib/money';
import { PackageItem } from '../types';
import {
  ComboSelection,
  validateComponentExtras,
} from '../lib/selection';
import { getLocalizedName } from '../lib/packageDisplay';

export interface ComboComponentCustomizerProps {
  item: PackageItem;
  selection: ComboSelection;
  isExpanded: boolean;
  onToggleExpand: () => void;
  onToggleExtra: (extraId: number) => void;
  onChangeExtraQty: (extraId: number, delta: number) => void;
  onSetNotes: (notes: string) => void;
  serverError?: string;
}

export default function ComboComponentCustomizer({
  item,
  selection,
  isExpanded,
  onToggleExpand,
  onToggleExtra,
  onChangeExtraQty,
  onSetNotes,
  serverError,
}: ComboComponentCustomizerProps) {
  const { t, i18n } = useTranslation();
  const formatMoney = useFormatMoney();

  const pick = selection[item.id];
  const selectedExtrasList = pick?.extras ?? [];
  const currentNotes = pick?.notes ?? '';

  const validation = validateComponentExtras(item, selection);
  const errorMessage = serverError || (!validation.valid ? validation.error : undefined);

  const extraCount = selectedExtrasList.length;
  const hasNote = currentNotes.trim().length > 0;
  const isCustomized = extraCount > 0 || hasNote;

  const extraOption = item.extra_option;
  const minSelections = extraOption?.min_selections ?? 0;
  const maxSelections = extraOption?.max_selections ?? 0;

  let extraRuleText = '';
  if (minSelections > 0 && maxSelections > 0) {
    extraRuleText = t('selectBetween', {
      min: minSelections,
      max: maxSelections,
      defaultValue: `Select ${minSelections}–${maxSelections}`,
    });
  } else if (minSelections > 0) {
    extraRuleText = t('selectAtLeast', {
      min: minSelections,
      defaultValue: `Select at least ${minSelections}`,
    });
  } else if (maxSelections > 0) {
    extraRuleText = t('selectUpTo', {
      max: maxSelections,
      defaultValue: `Select up to ${maxSelections}`,
    });
  }

  return (
    <div className="mt-2 pt-2 border-t border-border/50 space-y-2">
      {/* Customizer Toggle Header */}
      <div className="flex items-center justify-between">
        <button
          type="button"
          data-testid={`combo-component-customize-${item.id}`}
          onClick={(e) => {
            e.stopPropagation();
            onToggleExpand();
          }}
          className="text-xs font-medium text-primary hover:text-primary/80 flex items-center gap-1.5 transition-colors py-1"
        >
          <SlidersHorizontal className="w-3.5 h-3.5 shrink-0" />
          <span>{t('customize', 'Customize')}</span>
          {isCustomized && (
            <span className="text-[10px] bg-primary/10 text-primary px-1.5 py-0.5 rounded-full font-semibold">
              {extraCount > 0 ? `${extraCount}` : '✓'}
            </span>
          )}
          {isExpanded ? (
            <ChevronUp className="w-3.5 h-3.5 ms-0.5 shrink-0" />
          ) : (
            <ChevronDown className="w-3.5 h-3.5 ms-0.5 shrink-0" />
          )}
        </button>
      </div>

      {/* Component-level Error (Server or Validation) */}
      {errorMessage && (
        <div
          data-testid={`combo-component-error-${item.id}`}
          className="text-xs text-destructive font-medium"
        >
          {errorMessage}
        </div>
      )}

      {/* Collapsible Customization Section */}
      {isExpanded && (
        <div className="space-y-3 pt-1">
          {/* Extras Picker */}
          {extraOption && extraOption.items && extraOption.items.length > 0 && (
            <div className="space-y-1.5">
              <div className="text-[11px] font-semibold text-muted-foreground flex items-center justify-between">
                <span>
                  {getLocalizedName(extraOption, i18n.language) || t('extras', 'Extras')}
                </span>
                {extraRuleText && (
                  <span className="text-[10px] font-normal">{extraRuleText}</span>
                )}
              </div>

              <div className="space-y-1.5">
                {extraOption.items.map((extra) => {
                  const isExtraSelected = selectedExtrasList.some((e) => e.id === extra.id);
                  const currentExtra = selectedExtrasList.find((e) => e.id === extra.id);
                  const extraQty = currentExtra?.quantity || 1;
                  const extraName = getLocalizedName(extra, i18n.language);
                  const extraPrice = Number(extra.price) || 0;

                  return (
                    <div
                      key={extra.id}
                      className={`flex items-center justify-between p-2 rounded-md border text-xs transition-colors shadow-none ${
                        isExtraSelected
                          ? 'border-primary/60 bg-primary/5'
                          : 'border-border/80 bg-background/70 dark:bg-card/70 hover:bg-muted/30'
                      }`}
                    >
                      <label className="flex items-center gap-2 flex-1 cursor-pointer select-none">
                        <input
                          type="checkbox"
                          data-testid={`combo-extra-${item.id}-${extra.id}`}
                          checked={isExtraSelected}
                          onChange={() => onToggleExtra(extra.id)}
                          className="w-4 h-4 rounded border-primary text-primary focus:ring-primary"
                        />
                        <div className="flex-1">
                          <span className="font-medium text-foreground">{extraName}</span>
                          {extraPrice > 0 && (
                            <span className="ms-2 text-muted-foreground font-semibold">
                              +{formatMoney(extraPrice)}
                            </span>
                          )}
                        </div>
                      </label>

                      {isExtraSelected && extra.allow_quantity && (
                        <div className="flex items-center gap-1 border border-border/90 rounded px-1 py-0.5 bg-background dark:bg-card shadow-none">
                          <button
                            type="button"
                            onClick={() => onChangeExtraQty(extra.id, -1)}
                            disabled={extraQty <= 1}
                            className="p-0.5 hover:bg-muted rounded disabled:opacity-30 transition-colors"
                            aria-label="Decrease extra quantity"
                          >
                            <Minus className="w-3 h-3" />
                          </button>
                          <span className="w-5 text-center font-semibold text-xs text-foreground">
                            {extraQty}
                          </span>
                          <button
                            type="button"
                            data-testid={`combo-extra-qty-inc-${item.id}-${extra.id}`}
                            onClick={() => onChangeExtraQty(extra.id, 1)}
                            className="p-0.5 hover:bg-muted rounded transition-colors"
                            aria-label="Increase extra quantity"
                          >
                            <Plus className="w-3 h-3" />
                          </button>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Component Notes */}
          <div className="space-y-1">
            <label className="text-[11px] font-medium text-muted-foreground block">
              {t('componentNotes', 'Special instructions for this item')}
            </label>
            <div className="relative">
              <textarea
                data-testid={`combo-component-notes-${item.id}`}
                value={currentNotes}
                onChange={(e) => onSetNotes(e.target.value)}
                maxLength={200}
                rows={2}
                placeholder={t(
                  'componentNotesPlaceholder',
                  'e.g. no onions, sauce on the side...'
                )}
                className="w-full text-xs p-2 rounded-md border border-input bg-background text-foreground focus:outline-none focus:ring-1 focus:ring-primary resize-none placeholder:text-muted-foreground/60"
              />
              <span className="text-[10px] text-muted-foreground absolute bottom-1.5 end-2 select-none">
                {currentNotes.length}/200
              </span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
