<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { extractErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '' })
const submitting = ref(false)
const error = ref<string | null>(null)

/** Only follow internal paths to avoid open redirects via `?redirect=`. */
function redirectTarget(): string {
  const redirect = route.query.redirect
  return typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
    ? redirect
    : '/'
}

async function submit(): Promise<void> {
  if (submitting.value) return
  submitting.value = true
  error.value = null
  try {
    await auth.login({ email: form.email.trim(), password: form.password })
    await router.replace(redirectTarget())
  } catch (e) {
    error.value = extractErrorMessage(e, 'Unable to sign in. Please try again.')
    form.password = ''
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="login">
    <form class="login-card" novalidate @submit.prevent="submit">
      <h1 class="login-title">Sign in</h1>
      <p class="muted login-subtitle">Access your financial imports.</p>

      <p v-if="error" id="login-error" class="alert alert-error" role="alert" data-test="login-error">
        {{ error }}
      </p>

      <div class="field">
        <label for="email" class="field-label">Email</label>
        <input
          id="email"
          v-model="form.email"
          class="input"
          type="email"
          name="email"
          autocomplete="username"
          required
          :aria-invalid="error ? 'true' : undefined"
          :aria-describedby="error ? 'login-error' : undefined"
        />
      </div>

      <div class="field">
        <label for="password" class="field-label">Password</label>
        <input
          id="password"
          v-model="form.password"
          class="input"
          type="password"
          name="password"
          autocomplete="current-password"
          required
          :aria-invalid="error ? 'true' : undefined"
          :aria-describedby="error ? 'login-error' : undefined"
        />
      </div>

      <button
        type="submit"
        class="btn btn-primary btn-block"
        :disabled="submitting || !form.email || !form.password"
        :aria-busy="submitting"
      >
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </main>
</template>

<style scoped>
.login {
  min-height: 100vh;
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 1rem;
}

.login-card {
  width: 100%;
  max-width: 24rem;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 2rem 1.5rem;
  box-shadow: var(--shadow);
}

.login-title {
  margin: 0;
  font-size: 1.5rem;
}

.login-subtitle {
  margin: 0.25rem 0 1.5rem;
}

.field {
  margin-bottom: 1rem;
}

.btn-block {
  width: 100%;
  margin-top: 0.5rem;
}
</style>
