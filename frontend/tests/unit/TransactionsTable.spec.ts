import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import TransactionsTable from '@/components/TransactionsTable.vue'
import { transactionsApi } from '@/api/transactions'
import { DEFAULT_PER_PAGE } from '@/stores/transactions'
import type { Paginated, Transaction } from '@/types/api'

vi.mock('@/api/transactions', () => ({
  transactionsApi: { list: vi.fn() },
}))

function page(current: number, last: number, data: Transaction[]): Paginated<Transaction> {
  return {
    data,
    meta: { current_page: current, last_page: last, per_page: DEFAULT_PER_PAGE, total: last * DEFAULT_PER_PAGE, from: 1, to: data.length },
  }
}

const income: Transaction = { id: 1, date: '2024-03-05', description: 'Salary', amount: 500000, type: 'income' }
const expense: Transaction = { id: 2, date: '2024-03-04', description: 'Rent', amount: 150050, type: 'expense' }

const text = (value: string) => value.replace(/\u00a0/g, ' ')

describe('TransactionsTable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.mocked(transactionsApi.list).mockReset()
  })

  it('loads the first page and renders formatted rows', async () => {
    vi.mocked(transactionsApi.list).mockResolvedValue(page(1, 3, [income, expense]))
    const wrapper = mount(TransactionsTable)
    await flushPromises()

    expect(transactionsApi.list).toHaveBeenCalledWith(1, DEFAULT_PER_PAGE)
    const rows = wrapper.findAll('[data-test="transaction-row"]')
    expect(rows).toHaveLength(2)
    expect(text(rows[0]!.text())).toContain('05/03/2024')
    expect(text(rows[0]!.text())).toContain('+R$ 5.000,00')
    expect(rows[0]!.find('.badge-income').text()).toBe('Income')
    expect(text(rows[1]!.text())).toContain('\u2212R$ 1.500,50')
    expect(rows[1]!.find('.badge-expense').exists()).toBe(true)
    expect(wrapper.get('[data-test="page-info"]').text()).toContain('Page 1 of 3')
  })

  it('navigates with next and previous, disabling at the edges', async () => {
    vi.mocked(transactionsApi.list)
      .mockResolvedValueOnce(page(1, 2, [income]))
      .mockResolvedValueOnce(page(2, 2, [expense]))
      .mockResolvedValueOnce(page(1, 2, [income]))
    const wrapper = mount(TransactionsTable)
    await flushPromises()

    expect(wrapper.get('[data-test="prev-page"]').attributes('disabled')).toBeDefined()
    await wrapper.get('[data-test="next-page"]').trigger('click')
    await flushPromises()

    expect(transactionsApi.list).toHaveBeenLastCalledWith(2, DEFAULT_PER_PAGE)
    expect(wrapper.get('[data-test="page-info"]').text()).toContain('Page 2 of 2')
    expect(wrapper.get('[data-test="next-page"]').attributes('disabled')).toBeDefined()

    await wrapper.get('[data-test="prev-page"]').trigger('click')
    await flushPromises()
    expect(transactionsApi.list).toHaveBeenLastCalledWith(1, DEFAULT_PER_PAGE)
    expect(wrapper.text()).toContain('Salary')
  })

  it('shows a loading state while the request is pending', async () => {
    vi.mocked(transactionsApi.list).mockReturnValue(new Promise(() => {}))
    const wrapper = mount(TransactionsTable)
    await flushPromises()

    expect(wrapper.find('[data-test="table-loading"]').exists()).toBe(true)
  })

  it('shows an empty state', async () => {
    vi.mocked(transactionsApi.list).mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: DEFAULT_PER_PAGE, total: 0, from: null, to: null },
    })
    const wrapper = mount(TransactionsTable)
    await flushPromises()

    expect(wrapper.find('[data-test="table-empty"]').exists()).toBe(true)
    expect(wrapper.find('nav').exists()).toBe(false)
  })

  it('shows an error state with retry', async () => {
    vi.mocked(transactionsApi.list)
      .mockRejectedValueOnce(new Error('boom'))
      .mockResolvedValueOnce(page(1, 1, [income]))
    const wrapper = mount(TransactionsTable)
    await flushPromises()

    expect(wrapper.get('[data-test="table-error"]').text()).toContain('Could not load transactions.')
    await wrapper.get('[data-test="table-error"] button').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('[data-test="transaction-row"]')).toHaveLength(1)
  })
})
