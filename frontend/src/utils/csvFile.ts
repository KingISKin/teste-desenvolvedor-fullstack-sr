/** Must match the backend upload rule (`max:20480` KB). */
export const MAX_CSV_SIZE_BYTES = 20 * 1024 * 1024

/**
 * Client-side pre-check for fast feedback only; the API re-validates everything.
 * Returns an error message, or null when the file looks acceptable.
 */
export function validateCsvFile(file: File): string | null {
  if (!file.name.toLowerCase().endsWith('.csv')) {
    return 'Please choose a .csv file.'
  }
  if (file.size === 0) {
    return 'The selected file is empty.'
  }
  if (file.size > MAX_CSV_SIZE_BYTES) {
    return 'The file is larger than 20 MB.'
  }
  return null
}
