import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { setUnauthorizedHandler } from './api/client'
import { createAppRouter } from './router'
import { useAuthStore } from './stores/auth'
import './styles/main.css'

const app = createApp(App)
const pinia = createPinia()
const router = createAppRouter()

app.use(pinia)
app.use(router)

// Any 401 (expired/revoked token) resets the session and sends the user to /login.
setUnauthorizedHandler(() => {
  useAuthStore(pinia).clearSession()
  if (router.currentRoute.value.name !== 'login') {
    void router.replace({ name: 'login' })
  }
})

app.mount('#app')
