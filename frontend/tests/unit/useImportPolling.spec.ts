import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope } from 'vue'
import { useImportPolling } from '@/composables/useImportPolling'
import type { Import, ImportStatus } from '@/types/api'

function makeImport(status: ImportStatus, overrides: Partial<Import> = {}): Import {
  return {
    id: 7,
    status,
    total_rows: null,
    processed_rows: 0,
    failed_rows: 0,
    errors: [],
    created_at: '2024-01-01T00:00:00Z',
    finished_at: null,
    ...overrides,
  }
}

describe('useImportPolling', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('backs off exponentially up to the cap: 1s, 2s, 4s, 8s, 10s, 10s', async () => {
    const fetchImport = vi.fn().mockResolvedValue(makeImport('processing'))
    const polling = useImportPolling({ fetchImport })

    polling.start(makeImport('pending'))

    const expectedGaps = [1000, 2000, 4000, 8000, 10000, 10000]
    for (const [index, gap] of expectedGaps.entries()) {
      await vi.advanceTimersByTimeAsync(gap - 1)
      expect(fetchImport).toHaveBeenCalledTimes(index)
      await vi.advanceTimersByTimeAsync(1)
      expect(fetchImport).toHaveBeenCalledTimes(index + 1)
    }
    expect(fetchImport).toHaveBeenCalledWith(7)
    polling.stop()
  })

  it('exposes progress and stops on completion, notifying once', async () => {
    const completed = makeImport('completed', { processed_rows: 10, total_rows: 10 })
    const fetchImport = vi
      .fn()
      .mockResolvedValueOnce(makeImport('processing', { processed_rows: 4 }))
      .mockResolvedValueOnce(completed)
    const onSettled = vi.fn()
    const polling = useImportPolling({ fetchImport, onSettled })

    polling.start(makeImport('pending'))
    expect(polling.isPolling.value).toBe(true)

    await vi.advanceTimersByTimeAsync(1000)
    expect(polling.current.value?.processed_rows).toBe(4)

    await vi.advanceTimersByTimeAsync(2000)
    expect(polling.current.value?.status).toBe('completed')
    expect(polling.isPolling.value).toBe(false)
    expect(onSettled).toHaveBeenCalledExactlyOnceWith(completed)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(fetchImport).toHaveBeenCalledTimes(2)
  })

  it('stops on failure as a terminal status', async () => {
    const failed = makeImport('failed', { errors: [{ line: 1, message: 'Invalid header.' }] })
    const fetchImport = vi.fn().mockResolvedValue(failed)
    const onSettled = vi.fn()
    const polling = useImportPolling({ fetchImport, onSettled })

    polling.start(makeImport('pending'))
    await vi.advanceTimersByTimeAsync(1000)

    expect(onSettled).toHaveBeenCalledWith(failed)
    await vi.advanceTimersByTimeAsync(60_000)
    expect(fetchImport).toHaveBeenCalledOnce()
    expect(vi.getTimerCount()).toBe(0)
  })

  it('does not poll when the initial import is already terminal', async () => {
    const fetchImport = vi.fn()
    const onSettled = vi.fn()
    const polling = useImportPolling({ fetchImport, onSettled })

    polling.start(makeImport('completed'))

    await vi.advanceTimersByTimeAsync(30_000)
    expect(fetchImport).not.toHaveBeenCalled()
    expect(onSettled).toHaveBeenCalledOnce()
  })

  it('keeps retrying transient errors, then gives up', async () => {
    const fetchImport = vi.fn().mockRejectedValue(new Error('offline'))
    const polling = useImportPolling({ fetchImport, maxConsecutiveErrors: 3 })

    polling.start(makeImport('pending'))
    await vi.advanceTimersByTimeAsync(1000 + 2000 + 4000)

    expect(fetchImport).toHaveBeenCalledTimes(3)
    expect(polling.isPolling.value).toBe(false)
    expect(polling.error.value).toBeTruthy()
    await vi.advanceTimersByTimeAsync(60_000)
    expect(fetchImport).toHaveBeenCalledTimes(3)
  })

  it('cleans up timers when the owning scope is disposed (component unmount)', async () => {
    const fetchImport = vi.fn().mockResolvedValue(makeImport('processing'))
    const scope = effectScope()
    const polling = scope.run(() => useImportPolling({ fetchImport }))!

    polling.start(makeImport('pending'))
    expect(vi.getTimerCount()).toBe(1)

    scope.stop()

    expect(vi.getTimerCount()).toBe(0)
    expect(polling.isPolling.value).toBe(false)
    await vi.advanceTimersByTimeAsync(60_000)
    expect(fetchImport).not.toHaveBeenCalled()
  })

  it('ignores an in-flight response that resolves after stop()', async () => {
    let resolve!: (value: Import) => void
    const fetchImport = vi.fn(() => new Promise<Import>((r) => (resolve = r)))
    const onSettled = vi.fn()
    const polling = useImportPolling({ fetchImport, onSettled })

    polling.start(makeImport('pending'))
    await vi.advanceTimersByTimeAsync(1000)
    polling.stop()
    resolve(makeImport('completed'))
    await vi.runAllTimersAsync()

    expect(onSettled).not.toHaveBeenCalled()
    expect(polling.current.value?.status).toBe('pending')
  })
})
