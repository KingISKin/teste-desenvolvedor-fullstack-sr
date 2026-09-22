import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { useTransactionsStore } from '@/stores/transactions'
import { authApi } from '@/api/auth'
import { tokenStorage } from '@/utils/tokenStorage'

vi.mock('@/api/auth', () => ({
  authApi: { login: vi.fn(), logout: vi.fn(), me: vi.fn() },
}))

const user = { id: 1, name: 'Demo User', email: 'demo@example.com' }

describe('auth store', () => {
  beforeEach(() => {
    sessionStorage.clear()
    setActivePinia(createPinia())
    vi.mocked(authApi.login).mockReset()
    vi.mocked(authApi.logout).mockReset()
    vi.mocked(authApi.me).mockReset()
  })

  it('stores the token and user on login', async () => {
    vi.mocked(authApi.login).mockResolvedValue({ token: 'abc', user })
    const auth = useAuthStore()

    await auth.login({ email: user.email, password: 'secret' })

    expect(authApi.login).toHaveBeenCalledWith({ email: user.email, password: 'secret' })
    expect(auth.token).toBe('abc')
    expect(auth.user).toEqual(user)
    expect(auth.isAuthenticated).toBe(true)
    expect(tokenStorage.get()).toBe('abc')
  })

  it('does not store anything when login fails', async () => {
    vi.mocked(authApi.login).mockRejectedValue(new Error('422'))
    const auth = useAuthStore()

    await expect(auth.login({ email: user.email, password: 'wrong' })).rejects.toThrow()

    expect(auth.isAuthenticated).toBe(false)
    expect(tokenStorage.get()).toBeNull()
  })

  it('restores the token from sessionStorage', () => {
    tokenStorage.set('persisted')
    expect(useAuthStore().isAuthenticated).toBe(true)
  })

  it('clears the session on logout', async () => {
    vi.mocked(authApi.login).mockResolvedValue({ token: 'abc', user })
    vi.mocked(authApi.logout).mockResolvedValue()
    const auth = useAuthStore()
    await auth.login({ email: user.email, password: 'secret' })

    await auth.logout()

    expect(authApi.logout).toHaveBeenCalledOnce()
    expect(auth.token).toBeNull()
    expect(auth.user).toBeNull()
    expect(tokenStorage.get()).toBeNull()
  })

  it('clears the session even if the logout request fails', async () => {
    tokenStorage.set('abc')
    vi.mocked(authApi.logout).mockRejectedValue(new Error('network'))
    const auth = useAuthStore()

    await auth.logout()

    expect(auth.isAuthenticated).toBe(false)
    expect(tokenStorage.get()).toBeNull()
  })

  it('fetches the current user when a token exists', async () => {
    tokenStorage.set('abc')
    vi.mocked(authApi.me).mockResolvedValue(user)
    const auth = useAuthStore()

    await auth.fetchUser()

    expect(auth.user).toEqual(user)
  })

  it('wipes dashboard and transactions data when the session ends', async () => {
    vi.mocked(authApi.login).mockResolvedValue({ token: 'abc', user })
    const auth = useAuthStore()
    await auth.login({ email: user.email, password: 'secret' })
    const dashboard = useDashboardStore()
    const transactions = useTransactionsStore()
    dashboard.summary = { income: 10, expense: 5, balance: 5 }
    transactions.items = [{ id: 1, date: '2026-01-01', description: 'X', amount: 10, type: 'income' }]
    transactions.meta = { current_page: 2, last_page: 3, per_page: 15, total: 40, from: 16, to: 30 }

    auth.clearSession()

    expect(dashboard.summary).toBeNull()
    expect(transactions.items).toEqual([])
    expect(transactions.meta).toBeNull()
  })
})
