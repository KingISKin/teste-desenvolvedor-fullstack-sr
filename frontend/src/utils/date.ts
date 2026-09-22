/**
 * Formats an ISO calendar date (`YYYY-MM-DD`) as pt-BR (`DD/MM/YYYY`).
 * Parsed by hand on purpose: `new Date('YYYY-MM-DD')` is read as UTC midnight and
 * shifts one day back in negative-offset time zones such as America/Sao_Paulo.
 */
export function formatDate(isoDate: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate)
  if (!match) return isoDate
  const [, year, month, day] = match
  return `${day}/${month}/${year}`
}
