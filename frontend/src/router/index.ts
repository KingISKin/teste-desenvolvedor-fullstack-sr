import {
  createRouter,
  createWebHistory,
  type RouteLocationNormalized,
  type RouteLocationRaw,
  type RouteRecordRaw,
  type RouterHistory,
} from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
  }
}

export const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/HomeView.vue'),
    meta: { requiresAuth: true },
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

/**
 * Global guard: private routes need a token; guests are kept off the login page
 * once signed in. After a reload the profile is restored from `/auth/me`
 * (an invalid token triggers the 401 interceptor, which clears the session).
 */
export async function authGuard(to: RouteLocationNormalized): Promise<true | RouteLocationRaw> {
  const auth = useAuthStore()

  if (to.meta.requiresAuth) {
    if (!auth.isAuthenticated) {
      return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
    }
    if (!auth.user) {
      try {
        await auth.fetchUser()
      } catch {
        if (!auth.isAuthenticated) return { name: 'login' }
      }
    }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'home' }
  }

  return true
}

export function createAppRouter(history: RouterHistory = createWebHistory(import.meta.env.BASE_URL)) {
  const router = createRouter({ history, routes })
  router.beforeEach(authGuard)
  return router
}
