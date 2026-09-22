import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi } from '@/api/auth'
import type { LoginCredentials, User } from '@/types/api'
import { tokenStorage } from '@/utils/tokenStorage'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(tokenStorage.get())
  const user = ref<User | null>(null)

  const isAuthenticated = computed(() => token.value !== null)

  async function login(credentials: LoginCredentials): Promise<void> {
    const response = await authApi.login(credentials)
    tokenStorage.set(response.token)
    token.value = response.token
    user.value = response.user
  }

  /** Restores the user profile after a page reload (token survives in sessionStorage). */
  async function fetchUser(): Promise<void> {
    if (!token.value) return
    user.value = await authApi.me()
  }

  /** Drops local credentials without calling the API (used after a 401). */
  function clearSession(): void {
    tokenStorage.clear()
    token.value = null
    user.value = null
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } catch {
      // Revoking server-side is best effort; the local session is cleared regardless.
    } finally {
      clearSession()
    }
  }

  return { token, user, isAuthenticated, login, fetchUser, clearSession, logout }
})
