/**
 * Meta Content ID Formatter
 *
 * Must strictly equal Meta catalog feed IDs:
 * - Product without variant -> "{productId}"
 * - Product variant          -> "{productId}-{variantId}"
 */
export function metaContentId(
  productId: number | string,
  variantId?: number | string | null
): string {
  const pId = String(productId).trim();
  if (variantId !== undefined && variantId !== null && String(variantId).trim() !== '' && String(variantId).trim() !== '0') {
    return `${pId}-${String(variantId).trim()}`;
  }
  return pId;
}
