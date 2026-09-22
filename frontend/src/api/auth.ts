import { apiClient } from './client'
import type { DataEnvelope, LoginCredentials, LoginResponse, User } from '@/types/api'

export const authApi = {
  async login(credentials: LoginCredentials): Promise<LoginResponse> {
    const { data } = await apiClient.post<LoginResponse>('/auth/login', credentials)
    return data
  },
  async logout(): Promise<void> {
    await apiClient.post('/auth/logout')
  },
  async me(): Promise<User> {
    const { data } = await apiClient.get<DataEnvelope<User>>('/auth/me')
    return data.data
  },
}
