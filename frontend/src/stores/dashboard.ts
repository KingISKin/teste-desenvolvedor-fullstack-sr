import { ref } from 'vue'
import { defineStore } from 'pinia'
import { dashboardApi } from '@/api/dashboard'
import { extractErrorMessage } from '@/api/errors'
import type { DashboardSummary } from '@/types/api'

export const useDashboardStore = defineStore('dashboard', () => {
  const summary = ref<DashboardSummary | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  /**
   * Guards against out-of-order responses: only the latest request may write
   * state, and reset() invalidates any request still in flight (e.g. a summary
   * that arrives after logout must not reappear for the next user).
   */
  let latestRequest = 0

  async function fetchSummary(): Promise<void> {
    const requestId = ++latestRequest
    loading.value = true
    error.value = null
    try {
      const result = await dashboardApi.summary()
      if (requestId !== latestRequest) return
      summary.value = result
    } catch (e) {
      if (requestId !== latestRequest) return
      error.value = extractErrorMessage(e, 'Could not load the summary.')
    } finally {
      if (requestId === latestRequest) loading.value = false
    }
  }

  /** Forgets the previous user's data (called when the session ends). */
  function reset(): void {
    latestRequest++ // any in-flight response is discarded
    summary.value = null
    loading.value = false
    error.value = null
  }

  return { summary, loading, error, fetchSummary, reset }
})
