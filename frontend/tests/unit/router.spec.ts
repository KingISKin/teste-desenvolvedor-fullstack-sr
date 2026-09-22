import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory } from 'vue-router'
import { createAppRouter } from '@/router'
import { authApi } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import { tokenStorage } from '@/utils/tokenStorage'

vi.mock('@/api/auth', () => ({
  authApi: { login: vi.fn(), logout: vi.fn(), me: vi.fn() },
}))

const user = { id: 1, name: 'Demo User', email: 'demo@example.com' }

describe('router guard', () => {
  beforeEach(() => {
    sessionStorage.clear()
    setActivePinia(createPinia())
    vi.mocked(authApi.me).mockReset()
  })

  it('redirects guests from private routes to /login', async () => {
    const router = createAppRouter(createMemoryHistory())
    await router.push('/')
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('keeps the requested path as a redirect query', async () => {
    const router = createAppRouter(createMemoryHistory())
    await router.push('/?page=2')
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/?page=2')
  })

  it('lets authenticated users in and restores the profile after a reload', async () => {
    tokenStorage.set('abc')
    vi.mocked(authApi.me).mockResolvedValue(user)
    const router = createAppRouter(createMemoryHistory())

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('home')
    expect(useAuthStore().user).toEqual(user)
  })

  it('sends authenticated users away from /login', async () => {
    tokenStorage.set('abc')
    vi.mocked(authApi.me).mockResolvedValue(user)
    const router = createAppRouter(createMemoryHistory())

    await router.push('/login')

    expect(router.currentRoute.value.name).toBe('home')
  })

  it('falls back to /login when the stored token is rejected', async () => {
    tokenStorage.set('revoked')
    vi.mocked(authApi.me).mockImplementation(async () => {
      // Mirrors what the 401 interceptor + handler do.
      useAuthStore().clearSession()
      throw new Error('401')
    })
    const router = createAppRouter(createMemoryHistory())

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('redirects unknown paths home', async () => {
    tokenStorage.set('abc')
    vi.mocked(authApi.me).mockResolvedValue(user)
    const router = createAppRouter(createMemoryHistory())

    await router.push('/does-not-exist')

    expect(router.currentRoute.value.name).toBe('home')
  })
})
