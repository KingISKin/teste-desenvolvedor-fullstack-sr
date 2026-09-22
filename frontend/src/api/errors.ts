import { isAxiosError } from 'axios'
import type { ValidationErrorBody } from '@/types/api'

const DEFAULT_MESSAGE = 'Something went wrong. Please try again.'

/** Turns any API or network failure into a single human-readable message. */
export function extractErrorMessage(error: unknown, fallback = DEFAULT_MESSAGE): string {
  if (!isAxiosError<ValidationErrorBody>(error)) return fallback
  if (!error.response) return 'Network error. Check your connection and try again.'

  const { status, data } = error.response
  if (status === 429) return 'Too many attempts. Please wait a moment and try again.'

  const firstFieldError = data?.errors ? Object.values(data.errors).flat()[0] : undefined
  return firstFieldError ?? data?.message ?? fallback
}
