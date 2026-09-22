import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { extractErrorMessage } from '@/api/errors'
import { transactionsApi } from '@/api/transactions'
import type { PaginationMeta, Transaction } from '@/types/api'

export const DEFAULT_PER_PAGE = 15

export const useTransactionsStore = defineStore('transactions', () => {
  const items = ref<Transaction[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const perPage = ref(DEFAULT_PER_PAGE)
  const loading = ref(false)
  const error = ref<string | null>(null)

  /** Guards against out-of-order responses when the user pages quickly. */
  let latestRequest = 0

  const currentPage = computed(() => meta.value?.current_page ?? 1)
  const lastPage = computed(() => meta.value?.last_page ?? 1)
  const hasPrevious = computed(() => currentPage.value > 1)
  const hasNext = computed(() => currentPage.value < lastPage.value)

  async function fetchPage(page = 1): Promise<void> {
    const requestId = ++latestRequest
    loading.value = true
    error.value = null
    try {
      const result = await transactionsApi.list(page, perPage.value)
      if (requestId !== latestRequest) return
      items.value = result.data
      meta.value = result.meta
    } catch (e) {
      if (requestId !== latestRequest) return
      error.value = extractErrorMessage(e, 'Could not load transactions.')
    } finally {
      if (requestId === latestRequest) loading.value = false
    }
  }

  const refresh = () => fetchPage(currentPage.value)
  const nextPage = () => (hasNext.value ? fetchPage(currentPage.value + 1) : Promise.resolve())
  const previousPage = () => (hasPrevious.value ? fetchPage(currentPage.value - 1) : Promise.resolve())

  return {
    items,
    meta,
    perPage,
    loading,
    error,
    currentPage,
    lastPage,
    hasPrevious,
    hasNext,
    fetchPage,
    refresh,
    nextPage,
    previousPage,
  }
})
