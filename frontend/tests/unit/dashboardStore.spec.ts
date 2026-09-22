import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { dashboardApi } from '@/api/dashboard'
import { useDashboardStore } from '@/stores/dashboard'
import type { DashboardSummary } from '@/types/api'

vi.mock('@/api/dashboard', () => ({ dashboardApi: { summary: vi.fn() } }))

function deferred() {
  let resolve!: (value: DashboardSummary) => void
  let reject!: (reason: unknown) => void
  const promise = new Promise<DashboardSummary>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

describe('dashboard store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.mocked(dashboardApi.summary).mockReset()
  })

  it('ignores a response that arrives after reset (e.g. after logout)', async () => {
    const pending = deferred()
    vi.mocked(dashboardApi.summary).mockReturnValue(pending.promise)
    const store = useDashboardStore()

    const request = store.fetchSummary()
    store.reset()
    pending.resolve({ income: 10, expense: 5, balance: 5 })
    await request

    expect(store.summary).toBeNull()
    expect(store.loading).toBe(false)
  })

  it('ignores a failure that arrives after reset', async () => {
    const pending = deferred()
    vi.mocked(dashboardApi.summary).mockReturnValue(pending.promise)
    const store = useDashboardStore()

    const request = store.fetchSummary()
    store.reset()
    pending.reject(new Error('boom'))
    await request

    expect(store.error).toBeNull()
  })

  it('keeps only the latest of overlapping requests', async () => {
    const first = deferred()
    const second = deferred()
    vi.mocked(dashboardApi.summary).mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise)
    const store = useDashboardStore()

    const older = store.fetchSummary()
    const newer = store.fetchSummary()
    second.resolve({ income: 2, expense: 0, balance: 2 })
    await newer
    first.resolve({ income: 1, expense: 0, balance: 1 })
    await older

    expect(store.summary).toEqual({ income: 2, expense: 0, balance: 2 })
    expect(store.loading).toBe(false)
  })
})
