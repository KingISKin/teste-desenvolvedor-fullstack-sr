import { ref } from 'vue'
import { defineStore } from 'pinia'
import { dashboardApi } from '@/api/dashboard'
import { extractErrorMessage } from '@/api/errors'
import type { DashboardSummary } from '@/types/api'

export const useDashboardStore = defineStore('dashboard', () => {
  const summary = ref<DashboardSummary | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchSummary(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      summary.value = await dashboardApi.summary()
    } catch (e) {
      error.value = extractErrorMessage(e, 'Could not load the summary.')
    } finally {
      loading.value = false
    }
  }

  return { summary, loading, error, fetchSummary }
})
