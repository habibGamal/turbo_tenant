export interface NamedLocation {
  name?: string | null;
  name_ar?: string | null;
}

/**
 * Formats a governorate + area display string in the active locale and avoids
 * redundant duplicates such as "Cairo - Cairo" or "القاهره - القاهره".
 */
export function formatAreaLabel(
  governorate: NamedLocation | null | undefined,
  area: NamedLocation | null | undefined,
  isArabic: boolean
): string {
  const govName = (isArabic ? (governorate?.name_ar || governorate?.name) : (governorate?.name || governorate?.name_ar))?.trim() || '';
  const areaName = (isArabic ? (area?.name_ar || area?.name) : (area?.name || area?.name_ar))?.trim() || '';

  if (!govName) return areaName;
  if (!areaName) return govName;
  if (govName.toLowerCase() === areaName.toLowerCase()) {
    return areaName;
  }

  return `${govName} - ${areaName}`;
}
