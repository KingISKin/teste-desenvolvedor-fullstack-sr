/**
 * Persists the API bearer token.
 *
 * Trade-off: sessionStorage is scoped to the tab and cleared when it closes, which
 * shortens the exposure window compared to localStorage while surviving reloads.
 * Like any Web Storage it is readable by JavaScript, so an XSS bug could leak the token.
 * An httpOnly cookie (Sanctum SPA mode) would avoid that but couples the SPA to
 * cookie/CSRF handling; we keep the API stateless and mitigate with expiring tokens,
 * no `v-html`, and dropping the token on any 401.
 */
const TOKEN_KEY = 'auth_token'

function storage(): Storage | null {
  try {
    return window.sessionStorage
  } catch {
    return null
  }
}

export const tokenStorage = {
  get(): string | null {
    return storage()?.getItem(TOKEN_KEY) ?? null
  },
  set(token: string): void {
    storage()?.setItem(TOKEN_KEY, token)
  },
  clear(): void {
    storage()?.removeItem(TOKEN_KEY)
  },
}
