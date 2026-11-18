import { Area, Governorate } from "@/types";

/**
 * Formats a governorate/area pair in the active language and collapses identical names.
 */
export function formatPlaceLabel(
    governorateName?: string | null,
    governorateNameAr?: string | null,
    areaName?: string | null,
    areaNameAr?: string | null,
    language = "ar",
    separator = " - "
): string {
    const isArabic = language.startsWith("ar");
    const govLabel = (isArabic ? governorateNameAr || governorateName : governorateName)?.trim() || "";
    const areaLabel = (isArabic ? areaNameAr || areaName : areaName)?.trim() || "";

    if (!govLabel) return areaLabel;
    if (!areaLabel) return govLabel;

    const rawMatch =
        Boolean(governorateName?.trim() && areaName?.trim()) &&
        governorateName!.trim().toLowerCase() === areaName!.trim().toLowerCase();
    const localizedMatch = govLabel.toLowerCase() === areaLabel.toLowerCase();

    if (localizedMatch || rawMatch) {
        return govLabel;
    }

    return `${govLabel}${separator}${areaLabel}`;
}

/**
 * Formats an area select option with localized place name and delivery cost.
 */
export function formatAreaOptionLabel(
    governorate: Pick<Governorate, "name" | "name_ar">,
    area: Pick<Area, "name" | "name_ar" | "shipping_cost">,
    language: string,
    formatMoney: (value: number) => string,
    freeLabel: string
): string {
    const placeLabel = formatPlaceLabel(
        governorate.name,
        governorate.name_ar,
        area.name,
        area.name_ar,
        language,
        " - "
    );
    const cost = Number(area.shipping_cost) || 0;

    return `${placeLabel} (${cost > 0 ? formatMoney(cost) : freeLabel})`;
}

/**
 * Joins populated address segments so simplified addresses omit empty building/floor/apartment parts.
 */
export function formatSavedAddressLines(
    address: {
        street?: string | null;
        building?: string | null;
        floor?: string | null;
        apartment?: string | null;
    },
    labels: { floor: string; apt: string },
    isRTL = false
): string {
    const street = address.street?.trim();
    const building = address.building?.trim();
    const floor = address.floor?.trim();
    const apartment = address.apartment?.trim();

    return [
        street,
        building,
        floor ? `${labels.floor} ${floor}` : null,
        apartment ? `${labels.apt} ${apartment}` : null,
    ]
        .filter(Boolean)
        .join(isRTL ? "، " : ", ");
}
