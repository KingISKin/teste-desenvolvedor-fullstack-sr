import { apiClient } from './client'
import type { Paginated, Transaction } from '@/types/api'

export const transactionsApi = {
  async list(page: number, perPage: number): Promise<Paginated<Transaction>> {
    const { data } = await apiClient.get<Paginated<Transaction>>('/transactions', {
      params: { page, per_page: perPage },
    })
    return data
  },
}
