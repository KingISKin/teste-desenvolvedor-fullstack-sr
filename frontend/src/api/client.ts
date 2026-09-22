import axios, { type AxiosError, type AxiosInstance } from 'axios'
import { tokenStorage } from '@/utils/tokenStorage'

type UnauthorizedHandler = () => void

let onUnauthorized: UnauthorizedHandler | null = null

/**
 * Registers what happens after a 401 (reset auth state and go to /login).
 * Injected from the app bootstrap so this module stays free of router/store imports.
 */
export function setUnauthorizedHandler(handler: UnauthorizedHandler | null): void {
  onUnauthorized = handler
}

export function createApiClient(): AxiosInstance {
  const client = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
  })

  client.interceptors.request.use((config) => {
    const token = tokenStorage.get()
    if (token) {
      config.headers.set('Authorization', `Bearer ${token}`)
    }
    return config
  })

  client.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
      if (error.response?.status === 401) {
        tokenStorage.clear()
        onUnauthorized?.()
      }
      return Promise.reject(error)
    },
  )

  return client
}

export const apiClient = createApiClient()
