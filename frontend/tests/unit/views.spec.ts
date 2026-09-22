import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory } from 'vue-router'
import { AxiosError, AxiosHeaders } from 'axios'
import SummaryCards from '@/components/SummaryCards.vue'
import LoginView from '@/views/LoginView.vue'
import { authApi } from '@/api/auth'
import { dashboardApi } from '@/api/dashboard'
import { createAppRouter } from '@/router'
import { useDashboardStore } from '@/stores/dashboard'

vi.mock('@/api/auth', () => ({ authApi: { login: vi.fn(), logout: vi.fn(), me: vi.fn() } }))
vi.mock('@/api/dashboard', () => ({ dashboardApi: { summary: vi.fn() } }))
// The login flow only needs to reach the home route; a stub keeps the lazy
// route chunk (and its whole component tree) out of this test.
vi.mock('@/views/HomeView.vue', () => ({ default: { name: 'HomeViewStub', render: () => null } }))

const text = (value: string) => value.replace(/\u00a0/g, ' ')

describe('SummaryCards', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders totals in BRL and colors a negative balance', async () => {
    vi.mocked(dashboardApi.summary).mockResolvedValue({ income: 100000, expense: 250050, balance: -150050 })
    const wrapper = mount(SummaryCards)
    await useDashboardStore().fetchSummary()
    await flushPromises()

    expect(text(wrapper.get('[data-test="income"]').text())).toBe('R$ 1.000,00')
    expect(text(wrapper.get('[data-test="expense"]').text())).toBe('R$ 2.500,50')
    const balance = wrapper.get('[data-test="balance"]')
    expect(text(balance.text())).toBe('-R$ 1.500,50')
    expect(balance.classes()).toContain('negative')
  })

  it('colors a positive balance', async () => {
    vi.mocked(dashboardApi.summary).mockResolvedValue({ income: 300, expense: 100, balance: 200 })
    const wrapper = mount(SummaryCards)
    await useDashboardStore().fetchSummary()
    await flushPromises()

    expect(wrapper.get('[data-test="balance"]').classes()).toContain('positive')
  })
})

describe('LoginView', () => {
  beforeEach(() => {
    sessionStorage.clear()
    setActivePinia(createPinia())
    vi.mocked(authApi.login).mockReset()
  })

  async function mountLogin() {
    const router = createAppRouter(createMemoryHistory())
    await router.push('/login')
    const wrapper = mount(LoginView, { global: { plugins: [router] } })
    return { router, wrapper }
  }

  it('signs in, shows a loading state and navigates home', async () => {
    let finish!: () => void
    vi.mocked(authApi.login).mockImplementation(
      () =>
        new Promise((resolve) => {
          finish = () => resolve({ token: 'abc', user: { id: 1, name: 'Demo', email: 'demo@example.com' } })
        }),
    )
    vi.mocked(authApi.me).mockResolvedValue({ id: 1, name: 'Demo', email: 'demo@example.com' })
    const { router, wrapper } = await mountLogin()

    await wrapper.get('#email').setValue('demo@example.com')
    await wrapper.get('#password').setValue('secret')
    await wrapper.get('form').trigger('submit')

    const button = wrapper.get('button[type="submit"]')
    expect(button.text()).toBe('Signing in…')
    expect(button.attributes('disabled')).toBeDefined()

    finish()
    // Navigation lazy-loads the home view chunk, so wait for it to settle.
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('home'), { timeout: 5000 })
  })

  it('shows the validation error returned by the API', async () => {
    const response = {
      status: 422,
      statusText: '',
      headers: new AxiosHeaders(),
      config: { headers: new AxiosHeaders() },
      data: { message: 'Invalid.', errors: { email: ['These credentials do not match our records.'] } },
    }
    vi.mocked(authApi.login).mockRejectedValue(new AxiosError('422', 'ERR_BAD_REQUEST', undefined, null, response))
    const { router, wrapper } = await mountLogin()

    await wrapper.get('#email').setValue('demo@example.com')
    await wrapper.get('#password').setValue('wrong')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-test="login-error"]').text()).toBe('These credentials do not match our records.')
    expect(wrapper.get('#email').attributes('aria-invalid')).toBe('true')
    expect((wrapper.get('#password').element as HTMLInputElement).value).toBe('')
    expect(router.currentRoute.value.name).toBe('login')
  })
})
