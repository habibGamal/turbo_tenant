/** Match one displayed amount without allowing a partial match inside another number. */
export function pricePattern(amount: number): RegExp {
  const [whole, cents] = amount.toFixed(2).split('.');
  const decimals = cents === '00' ? '(?:\\.00)?' : `\\.${cents}`;
  return new RegExp(`(?<![\\d.,])${whole}${decimals}(?![\\d.,])`);
}
