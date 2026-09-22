import { describe, expect, it } from 'vitest'
import { formatCents, formatSignedCents } from '@/utils/money'
import { formatDate } from '@/utils/date'
import { MAX_CSV_SIZE_BYTES, validateCsvFile } from '@/utils/csvFile'

// Intl uses a no-break space between the symbol and the number.
const normalize = (value: string) => value.replace(/\u00a0/g, ' ')

describe('formatCents', () => {
  it('formats integer cents as BRL', () => {
    expect(normalize(formatCents(115346))).toBe('R$ 1.153,46')
    expect(normalize(formatCents(5))).toBe('R$ 0,05')
    expect(normalize(formatCents(0))).toBe('R$ 0,00')
  })

  it('formats negative balances', () => {
    expect(normalize(formatCents(-2550))).toBe('-R$ 25,50')
  })

  it('handles large totals without precision loss', () => {
    expect(normalize(formatCents(123456789012))).toBe('R$ 1.234.567.890,12')
  })
})

describe('formatSignedCents', () => {
  it('prefixes income with + and expense with a minus sign', () => {
    expect(normalize(formatSignedCents(1000, false))).toBe('+R$ 10,00')
    expect(normalize(formatSignedCents(1000, true))).toBe('\u2212R$ 10,00')
  })
})

describe('formatDate', () => {
  it('formats ISO dates as pt-BR without time zone shifts', () => {
    expect(formatDate('2024-01-01')).toBe('01/01/2024')
    expect(formatDate('2023-12-31')).toBe('31/12/2023')
  })

  it('returns unknown formats untouched', () => {
    expect(formatDate('not-a-date')).toBe('not-a-date')
  })
})

describe('validateCsvFile', () => {
  it('accepts a non-empty .csv file', () => {
    expect(validateCsvFile(new File(['date'], 'data.CSV'))).toBeNull()
  })

  it('rejects other extensions, empty and oversized files', () => {
    expect(validateCsvFile(new File(['x'], 'data.xlsx'))).toMatch(/\.csv/)
    expect(validateCsvFile(new File([], 'empty.csv'))).toMatch(/empty/)
    const big = new File(['x'], 'big.csv')
    Object.defineProperty(big, 'size', { value: MAX_CSV_SIZE_BYTES + 1 })
    expect(validateCsvFile(big)).toMatch(/20 MB/)
  })
})
