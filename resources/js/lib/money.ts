import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useTenantSafe } from '@/core/context/TenantContext';

export const DEFAULT_CURRENCY = 'EGP';

/**
 * Format an amount in the store currency, e.g. "120 ج.م." / "EGP 120.50".
 * Whole amounts drop the ".00"; fractional amounts keep two decimals.
 */
export function formatMoney(
    amount: number | string | null | undefined,
    currency: string = DEFAULT_CURRENCY,
    locale: string = 'ar',
): string {
    const value = Number(amount ?? 0);
    const safe = Number.isFinite(value) ? value : 0;
    const fractionDigits = Number.isInteger(safe) ? 0 : 2;
    // Arabic text with Latin digits reads best for prices on this storefront.
    const intlLocale = locale.startsWith('ar') ? 'ar-EG-u-nu-latn' : locale;

    try {
        return new Intl.NumberFormat(intlLocale, {
            style: 'currency',
            currency,
            minimumFractionDigits: fractionDigits,
            maximumFractionDigits: 2,
        }).format(safe);
    } catch {
        // Unknown currency code: fall back to "120.50 XYZ"
        return `${safe.toFixed(fractionDigits)} ${currency}`;
    }
}

/** The tenant's currency code from Store Settings (bootstrap), defaulting to EGP. */
export function useCurrency(): string {
    const tenant = useTenantSafe();
    const currency = tenant?.settings?.currency;

    return typeof currency === 'string' && currency.length === 3 ? currency.toUpperCase() : DEFAULT_CURRENCY;
}

/** Hook returning a formatter bound to the tenant currency and the active language. */
export function useFormatMoney(): (amount: number | string | null | undefined) => string {
    const currency = useCurrency();
    const { i18n } = useTranslation();

    return useCallback(
        (amount) => formatMoney(amount, currency, i18n.language || 'ar'),
        [currency, i18n.language],
    );
}
