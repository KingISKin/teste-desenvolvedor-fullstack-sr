<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loggingOut = ref(false)

async function logout(): Promise<void> {
  loggingOut.value = true
  try {
    await auth.logout()
    await router.replace({ name: 'login' })
  } finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <header class="app-header">
    <div class="container app-header-inner">
      <span class="brand">Finance Import</span>
      <div class="user">
        <span v-if="auth.user" class="user-name" data-test="user-name">{{ auth.user.name }}</span>
        <button type="button" class="btn btn-ghost" :disabled="loggingOut" @click="logout">
          {{ loggingOut ? 'Signing out…' : 'Sign out' }}
        </button>
      </div>
    </div>
  </header>
</template>

<style scoped>
.app-header {
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
}

.app-header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  min-height: 3.75rem;
}

.brand {
  font-weight: 700;
  letter-spacing: -0.01em;
}

.user {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.user-name {
  color: var(--color-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
