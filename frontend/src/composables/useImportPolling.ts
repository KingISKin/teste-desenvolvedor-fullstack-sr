import { getCurrentScope, onScopeDispose, readonly, ref } from 'vue'
import { importsApi } from '@/api/imports'
import { extractErrorMessage } from '@/api/errors'
import type { Import, ImportStatus } from '@/types/api'

const TERMINAL_STATUSES: readonly ImportStatus[] = ['completed', 'failed']

export function isTerminalStatus(status: ImportStatus): boolean {
  return TERMINAL_STATUSES.includes(status)
}

export interface ImportPollingOptions {
  /** Delay before the first poll, in ms. */
  initialDelay?: number
  /** Upper bound for the delay between polls, in ms. */
  maxDelay?: number
  /** Multiplier applied to the delay after every non-terminal poll. */
  factor?: number
  /** Consecutive request failures tolerated before giving up. */
  maxConsecutiveErrors?: number
  fetchImport?: (id: number) => Promise<Import>
  /** Called once when the import reaches `completed` or `failed`. */
  onSettled?: (result: Import) => void
}

/**
 * Polls `GET /imports/{id}` with exponential backoff (1s, 2s, 4s, 8s, 10s, 10s...)
 * until the import reaches a terminal status. Backoff keeps load on the API low for
 * long imports while still reacting quickly to small files. Timers are cleared when
 * the owning component/effect scope is disposed, so no request outlives the view.
 */
export function useImportPolling(options: ImportPollingOptions = {}) {
  const {
    initialDelay = 1000,
    maxDelay = 10000,
    factor = 2,
    maxConsecutiveErrors = 5,
    fetchImport = importsApi.show,
    onSettled,
  } = options

  const current = ref<Import | null>(null)
  const isPolling = ref(false)
  const error = ref<string | null>(null)

  let timer: ReturnType<typeof setTimeout> | null = null
  let delay = initialDelay
  let consecutiveErrors = 0
  // Incremented on every stop/start so responses from a previous run are ignored.
  let generation = 0

  function clearTimer(): void {
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
  }

  function stop(): void {
    generation++
    clearTimer()
    isPolling.value = false
  }

  function settle(result: Import): void {
    stop()
    onSettled?.(result)
  }

  function schedule(id: number, run: number): void {
    timer = setTimeout(() => void tick(id, run), delay)
  }

  async function tick(id: number, run: number): Promise<void> {
    timer = null
    try {
      const result = await fetchImport(id)
      if (run !== generation) return
      consecutiveErrors = 0
      current.value = result
      if (isTerminalStatus(result.status)) {
        settle(result)
        return
      }
    } catch (e) {
      if (run !== generation) return
      consecutiveErrors++
      if (consecutiveErrors >= maxConsecutiveErrors) {
        error.value = extractErrorMessage(e, 'Could not check the import status.')
        stop()
        return
      }
    }
    delay = Math.min(delay * factor, maxDelay)
    schedule(id, run)
  }

  /** Starts tracking an import (typically the 202 response of the upload). */
  function start(initial: Import): void {
    stop()
    current.value = initial
    error.value = null
    if (isTerminalStatus(initial.status)) {
      onSettled?.(initial)
      return
    }
    delay = initialDelay
    consecutiveErrors = 0
    isPolling.value = true
    schedule(initial.id, generation)
  }

  /** Stops polling and forgets the tracked import (before starting a new upload). */
  function reset(): void {
    stop()
    current.value = null
    error.value = null
  }

  if (getCurrentScope()) {
    onScopeDispose(stop)
  }

  return {
    current: readonly(current),
    isPolling: readonly(isPolling),
    error: readonly(error),
    start,
    stop,
    reset,
  }
}
