import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { AxiosError, AxiosHeaders } from 'axios'
import UploadCsv from '@/components/UploadCsv.vue'
import { importsApi } from '@/api/imports'
import type { Import, ImportStatus } from '@/types/api'

vi.mock('@/api/imports', () => ({
  importsApi: { upload: vi.fn(), show: vi.fn() },
}))

function makeImport(status: ImportStatus, overrides: Partial<Import> = {}): Import {
  return {
    id: 42,
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

async function selectFile(wrapper: VueWrapper, file: File): Promise<void> {
  const input = wrapper.get('input[type="file"]')
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
}

const csv = () => new File(['date,description,amount,type\n'], 'transactions.csv', { type: 'text/csv' })
const statusText = (wrapper: VueWrapper) => wrapper.get('[data-test="upload-status"]').text()

describe('UploadCsv', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.mocked(importsApi.upload).mockReset()
    vi.mocked(importsApi.show).mockReset()
  })
  afterEach(() => vi.useRealTimers())

  it('has a labelled file input and a polite live region', () => {
    const wrapper = mount(UploadCsv)
    expect(wrapper.get('label[for="csv-file"]').text()).toBe('CSV file')
    expect(wrapper.get('#csv-file').attributes('accept')).toContain('.csv')
    expect(wrapper.get('[data-test="upload-status"]').attributes('aria-live')).toBe('polite')
  })

  it('rejects non-csv files before uploading', async () => {
    const wrapper = mount(UploadCsv)

    await selectFile(wrapper, new File(['x'], 'report.pdf'))

    expect(wrapper.get('[data-test="validation-error"]').text()).toMatch(/\.csv/)
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    await wrapper.get('form').trigger('submit')
    expect(importsApi.upload).not.toHaveBeenCalled()
  })

  it('shows upload progress, then queued, processing and completed states', async () => {
    let reportProgress!: (percent: number) => void
    let finishUpload!: (value: Import) => void
    vi.mocked(importsApi.upload).mockImplementation((_file, onProgress) => {
      reportProgress = onProgress!
      return new Promise((resolve) => (finishUpload = resolve))
    })
    const completed = makeImport('completed', { processed_rows: 15000, total_rows: 15000 })
    vi.mocked(importsApi.show)
      .mockResolvedValueOnce(makeImport('processing', { processed_rows: 5000, failed_rows: 2, total_rows: 15000 }))
      .mockResolvedValueOnce(completed)

    const wrapper = mount(UploadCsv)
    await selectFile(wrapper, csv())
    await wrapper.get('form').trigger('submit')

    reportProgress(40)
    await flushPromises()
    expect(wrapper.get('progress').attributes('value')).toBe('40')
    expect(statusText(wrapper)).toContain('Uploading… 40%')
    expect(wrapper.get('#csv-file').attributes('disabled')).toBeDefined()

    finishUpload(makeImport('pending'))
    await flushPromises()
    expect(wrapper.find('progress').exists()).toBe(false)
    expect(statusText(wrapper)).toMatch(/Waiting in the queue/)

    await vi.advanceTimersByTimeAsync(1000)
    expect(importsApi.show).toHaveBeenCalledWith(42)
    expect(statusText(wrapper)).toMatch(/Processing… 5\.000 of 15\.000 rows processed, 2 failed/)

    await vi.advanceTimersByTimeAsync(2000)
    expect(statusText(wrapper)).toMatch(/Import completed: 15\.000 rows imported/)
    expect(wrapper.emitted('completed')).toEqual([[completed]])
    expect(wrapper.get('#csv-file').attributes('disabled')).toBeUndefined()
  })

  it('shows the row errors when the import fails and does not emit completed', async () => {
    vi.mocked(importsApi.upload).mockResolvedValue(makeImport('pending'))
    vi.mocked(importsApi.show).mockResolvedValue(
      makeImport('failed', { errors: [{ line: 1, message: 'Invalid header.' }] }),
    )

    const wrapper = mount(UploadCsv)
    await selectFile(wrapper, csv())
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await vi.advanceTimersByTimeAsync(1000)

    expect(statusText(wrapper)).toContain('Import failed.')
    expect(wrapper.get('[data-test="row-errors"]').text()).toContain('Line 1: Invalid header.')
    expect(wrapper.emitted('completed')).toBeUndefined()
  })

  it('shows the server validation message when the upload is rejected', async () => {
    const response = {
      status: 422,
      statusText: '',
      headers: new AxiosHeaders(),
      config: { headers: new AxiosHeaders() },
      data: { message: 'Invalid.', errors: { file: ['The file field must be a file of type: csv, txt.'] } },
    }
    vi.mocked(importsApi.upload).mockRejectedValue(
      new AxiosError('422', AxiosError.ERR_BAD_REQUEST, undefined, null, response),
    )

    const wrapper = mount(UploadCsv)
    await selectFile(wrapper, csv())
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-test="upload-error"]').text()).toContain('must be a file of type')
    expect(importsApi.show).not.toHaveBeenCalled()
  })

  it('stops polling when unmounted', async () => {
    vi.mocked(importsApi.upload).mockResolvedValue(makeImport('pending'))
    vi.mocked(importsApi.show).mockResolvedValue(makeImport('processing'))

    const wrapper = mount(UploadCsv)
    await selectFile(wrapper, csv())
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    wrapper.unmount()

    await vi.advanceTimersByTimeAsync(60_000)
    expect(importsApi.show).not.toHaveBeenCalled()
  })
})
