import { ComboPackage, PackageGroup, PackageAvailability, UnavailableReason } from '../types';

export interface SavingsLabelData {
  original: number;
  savings: number;
  percent: number;
}

/**
 * Returns localized name, preferring Arabic when language is 'ar' and name_ar is provided.
 */
export function getLocalizedName(
  item: { name: string; name_ar?: string | null },
  lang: string = 'en'
): string {
  const isAr = lang === 'ar' || lang.startsWith('ar');
  if (isAr && item.name_ar && item.name_ar.trim() !== '') {
    return item.name_ar;
  }
  return item.name;
}

/**
 * Returns localized description, preferring Arabic when language is 'ar' and description_ar is provided.
 */
export function getLocalizedDescription(
  item: { description?: string | null; description_ar?: string | null },
  lang: string = 'en'
): string | null {
  const isAr = lang === 'ar' || lang.startsWith('ar');
  if (isAr && item.description_ar && item.description_ar.trim() !== '') {
    return item.description_ar;
  }
  return item.description ?? null;
}

/**
 * Returns localized badge, preferring Arabic when language is 'ar' and badge_ar is provided.
 */
export function getLocalizedBadge(
  item: { badge?: string | null; badge_ar?: string | null },
  lang: string = 'en'
): string | null {
  const isAr = lang === 'ar' || lang.startsWith('ar');
  if (isAr && item.badge_ar && item.badge_ar.trim() !== '') {
    return item.badge_ar;
  }
  return item.badge ?? null;
}

/**
 * Computes savings data for a package.
 * Returns { original, savings, percent } when original_price > price, or null.
 */
export function savingsLabelData(
  pkg: Pick<ComboPackage, 'price' | 'original_price'> & Partial<Pick<ComboPackage, 'discount_percentage' | 'savings_amount'>>
): SavingsLabelData | null {
  const price = pkg.price;
  const original = pkg.original_price;

  if (original == null || original <= price) {
    return null;
  }

  const savings = pkg.savings_amount != null && pkg.savings_amount > 0
    ? pkg.savings_amount
    : original - price;

  if (savings <= 0) {
    return null;
  }

  const percent = pkg.discount_percentage != null && pkg.discount_percentage > 0
    ? Math.round(pkg.discount_percentage)
    : Math.round((savings / original) * 100);

  return {
    original,
    savings,
    percent,
  };
}

/**
 * Returns true if valid_until is set and within 72 hours of now (and not yet expired).
 */
export function endsSoon(
  pkg: Pick<ComboPackage, 'valid_until'>,
  now: Date | number = new Date()
): boolean {
  if (!pkg.valid_until) {
    return false;
  }

  const untilTime = new Date(pkg.valid_until).getTime();
  if (Number.isNaN(untilTime)) {
    return false;
  }

  const currentTime = typeof now === 'number' ? now : now.getTime();
  const diffMs = untilTime - currentTime;
  const seventyTwoHoursMs = 72 * 60 * 60 * 1000;

  return diffMs > 0 && diffMs <= seventyTwoHoursMs;
}

/**
 * Returns a list of short content summary lines, e.g.:
 * - "2× Beef burger" for fixed groups ('all')
 * - "Choose 1 of 3: Main dish" for conditional groups
 */
export function summarizeContents(
  pkg: { groups?: PackageGroup[] | null },
  lang: string = 'en'
): string[] {
  if (!pkg.groups || pkg.groups.length === 0) {
    return [];
  }

  const isAr = lang === 'ar' || lang.startsWith('ar');
  const sortedGroups = [...pkg.groups].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
  const lines: string[] = [];

  for (const group of sortedGroups) {
    if (group.selection_type === 'all') {
      const items = [...(group.items ?? [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
      for (const item of items) {
        const prodName = isAr && item.product?.name_ar ? item.product.name_ar : (item.product?.name ?? '');
        const variantName = isAr && item.variant?.name_ar ? item.variant.name_ar : (item.variant?.name ?? null);
        const fullName = variantName ? `${prodName} (${variantName})` : prodName;

        if (!fullName) {
          continue;
        }

        const qty = item.quantity ?? 1;
        lines.push(qty > 1 ? `${qty}× ${fullName}` : fullName);
      }
    } else {
      const total = group.items?.length ?? 0;
      if (total === 0) {
        continue;
      }

      const rawGroupName = isAr && group.name_ar ? group.name_ar : (group.name ?? '');
      const groupName = rawGroupName.trim();

      let countStr: string | number;
      if (group.selection_type === 'choose_one') {
        countStr = 1;
      } else if (
        group.min_selections != null &&
        group.max_selections != null &&
        group.min_selections !== group.max_selections
      ) {
        countStr = `${group.min_selections}-${group.max_selections}`;
      } else {
        countStr = group.max_selections ?? group.min_selections ?? 1;
      }

      if (isAr) {
        lines.push(groupName ? `اختر ${countStr} من ${total}: ${groupName}` : `اختر ${countStr} من ${total}`);
      } else {
        lines.push(groupName ? `Choose ${countStr} of ${total}: ${groupName}` : `Choose ${countStr} of ${total}`);
      }
    }
  }

  return lines;
}

const enDayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const arDayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

function formatTimeOnly(timeStr: string): string {
  return timeStr.slice(0, 5);
}

function formatDayRanges(days: number[], isAr: boolean): string {
  if (days.length === 7) {
    return isAr ? 'يومياً' : 'Daily';
  }
  const dayNames = isAr ? arDayNames : enDayNames;
  const separator = isAr ? '، ' : ', ';
  const ranges: { start: number; end: number }[] = [];
  let currentStart = days[0];
  let currentEnd = days[0];

  for (let i = 1; i < days.length; i++) {
    if (days[i] === currentEnd + 1) {
      currentEnd = days[i];
    } else {
      ranges.push({ start: currentStart, end: currentEnd });
      currentStart = days[i];
      currentEnd = days[i];
    }
  }
  ranges.push({ start: currentStart, end: currentEnd });

  return ranges
    .map((r) => {
      if (r.start === r.end) {
        return dayNames[r.start];
      }
      return `${dayNames[r.start]}–${dayNames[r.end]}`;
    })
    .join(separator);
}

/**
 * Formats dayparts / availability windows into a short human-readable label.
 * Returns null when package has no availability restrictions (always available).
 * e.g. "Sun–Thu 12:00–16:00" or the Arabic equivalent.
 */
export function formatAvailability(
  pkg: { availability?: PackageAvailability[] | null },
  lang: string = 'en'
): string | null {
  if (!pkg.availability || pkg.availability.length === 0) {
    return null;
  }

  const isAr = lang === 'ar' || lang.startsWith('ar');

  // Group windows by starts_at - ends_at
  const groups: Record<string, number[]> = {};
  for (const item of pkg.availability) {
    if (typeof item.day_of_week !== 'number' || item.day_of_week < 0 || item.day_of_week > 6) {
      continue;
    }
    const timeKey = `${formatTimeOnly(item.starts_at)}–${formatTimeOnly(item.ends_at)}`;
    if (!groups[timeKey]) {
      groups[timeKey] = [];
    }
    if (!groups[timeKey].includes(item.day_of_week)) {
      groups[timeKey].push(item.day_of_week);
    }
  }

  const keys = Object.keys(groups);
  if (keys.length === 0) {
    return null;
  }

  const labels = keys.map((timeKey) => {
    const days = groups[timeKey].sort((a, b) => a - b);
    const dayLabel = formatDayRanges(days, isAr);
    return `${dayLabel} ${timeKey}`;
  });

  return labels.join(isAr ? '، ' : ', ');
}

/**
 * Returns a short order type restriction label, or null when all order types are allowed.
 * e.g. "Delivery only" / "Pickup only" or Arabic equivalent.
 */
export function orderTypeLabel(
  pkg: { order_types?: ('web_delivery' | 'web_takeaway' | string)[] | null },
  lang: string = 'en'
): string | null {
  const types = pkg.order_types;
  if (!types || types.length === 0) {
    return null;
  }

  const hasDelivery = types.includes('web_delivery');
  const hasTakeaway = types.includes('web_takeaway');
  if (hasDelivery && hasTakeaway) {
    return null;
  }

  const isAr = lang === 'ar' || lang.startsWith('ar');
  if (hasDelivery) {
    return isAr ? 'توصيل فقط' : 'Delivery only';
  }
  if (hasTakeaway) {
    return isAr ? 'استلام فقط' : 'Pickup only';
  }

  return null;
}

export interface AvailabilityBadgeResult {
  kind: 'sold_out' | 'outside_hours' | 'few_left' | null;
  text: string | null;
}

/**
 * Computes availability badge state and textual reason.
 * - outside_hours: "Available from 12:00" using next_available_at or "Outside available hours"
 * - sold_out: "Sold out today"
 * - few_left: "Only N left today" when remaining_today <= 5
 */
export function availabilityBadge(
  pkg: {
    available_now?: boolean;
    unavailable_reason?: UnavailableReason;
    next_available_at?: string | null;
    remaining_today?: number | null;
  },
  _now?: Date | number,
  lang: string = 'en'
): AvailabilityBadgeResult {
  const isAr = lang === 'ar' || lang.startsWith('ar');

  // Check unavailable first
  if (pkg.available_now === false) {
    if (pkg.unavailable_reason === 'sold_out_today' || pkg.remaining_today === 0) {
      return {
        kind: 'sold_out',
        text: isAr ? 'نفدت اليوم' : 'Sold out today',
      };
    }

    if (pkg.unavailable_reason === 'outside_hours') {
      let timeStr: string | null = null;
      if (pkg.next_available_at) {
        // next_available_at is an ISO instant (often UTC); render it in the customer's local time,
        // and name the weekday when the next window is not today.
        const d = new Date(pkg.next_available_at);
        if (!isNaN(d.getTime())) {
          const h = String(d.getHours()).padStart(2, '0');
          const m = String(d.getMinutes()).padStart(2, '0');
          timeStr = `${h}:${m}`;
          const today = _now === undefined ? new Date() : new Date(_now);
          if (d.toDateString() !== today.toDateString()) {
            const day = d.toLocaleDateString(isAr ? 'ar-EG' : 'en-US', { weekday: 'long' });
            timeStr = `${day} ${timeStr}`;
          }
        }
      }

      if (timeStr) {
        return {
          kind: 'outside_hours',
          text: isAr ? `متاح من ${timeStr}` : `Available from ${timeStr}`,
        };
      }

      return {
        kind: 'outside_hours',
        text: isAr ? 'خارج أوقات التوفر' : 'Outside available hours',
      };
    }

    return {
      kind: pkg.remaining_today === 0 ? 'sold_out' : 'outside_hours',
      text: isAr ? 'غير متاح حالياً' : 'Currently unavailable',
    };
  }

  // If available, check remaining_today <= 5
  if (
    typeof pkg.remaining_today === 'number' &&
    pkg.remaining_today <= 5 &&
    pkg.remaining_today > 0
  ) {
    return {
      kind: 'few_left',
      text: isAr ? `متبقي ${pkg.remaining_today} اليوم فقط` : `Only ${pkg.remaining_today} left today`,
    };
  }

  if (pkg.remaining_today === 0) {
    return {
      kind: 'sold_out',
      text: isAr ? 'نفدت اليوم' : 'Sold out today',
    };
  }

  return {
    kind: null,
    text: null,
  };
}
