export interface WeightValueLike {
  value?: string | number | null;
  label?: string | null;
}

const ARABIC_CHAR_REGEX = /[\u0600-\u06FF]/;

/**
 * Formats a weight option value in Arabic or English, handling Arabic-only DB labels
 * gracefully when the storefront is switched to English (e.g. "250 جرام" -> "250g").
 */
export function formatWeightLabel(
  weightValue: WeightValueLike | null | undefined,
  unit: string = 'kg',
  isArabic: boolean = false
): string {
  if (!weightValue) return '';

  const rawLabel = (weightValue.label ?? '').trim();
  const num = Number(weightValue.value ?? 0);
  const normalizedUnit = (unit || 'kg').trim().toLowerCase();
  const hasArabicLabel = ARABIC_CHAR_REGEX.test(rawLabel);

  if (isArabic) {
    if (rawLabel && hasArabicLabel) {
      return rawLabel;
    }
    if (Number.isFinite(num) && num > 0) {
      if (normalizedUnit === 'kg' && num < 1) {
        return `${Math.round(num * 1000)} جرام`;
      }
      if (normalizedUnit === 'kg' && num === 1) {
        return 'كيلو';
      }
      const displayNum = Number.isInteger(num) ? num : parseFloat(num.toFixed(3));
      return `${displayNum} ${normalizedUnit === 'g' ? 'جرام' : 'كجم'}`;
    }
    return rawLabel;
  }

  if (rawLabel && !hasArabicLabel) {
    return rawLabel;
  }

  if (Number.isFinite(num) && num > 0) {
    if (normalizedUnit === 'kg' && num < 1) {
      return `${Math.round(num * 1000)}g`;
    }
    const displayNum = Number.isInteger(num) ? num : parseFloat(num.toFixed(3));
    return normalizedUnit === 'g' ? `${displayNum}g` : `${displayNum} ${normalizedUnit}`;
  }

  return rawLabel;
}
