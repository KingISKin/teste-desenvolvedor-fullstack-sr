import { apiClient } from './client'
import type { DashboardSummary, DataEnvelope } from '@/types/api'

export const dashboardApi = {
  async summary(): Promise<DashboardSummary> {
    const { data } = await apiClient.get<DataEnvelope<DashboardSummary>>('/dashboard')
    return data.data
  },
}
