const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

/**
 * Formats an integer amount of cents as Brazilian Real (e.g. 115346 -> "R$ 1.153,46").
 * Arithmetic stays in integer cents; the division happens only at the display edge.
 */
export function formatCents(cents: number): string {
  return brl.format(cents / 100)
}

/** Formats a non-negative amount with an explicit sign: "+R$ 10,00" or "−R$ 10,00". */
export function formatSignedCents(cents: number, negative: boolean): string {
  const sign = negative ? '\u2212' : '+'
  return `${sign}${formatCents(Math.abs(cents))}`
}
