import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { i18n, initI18n } from './i18n'
import { useAuthStore } from './stores/auth'
import VueApexCharts from 'vue3-apexcharts'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(i18n)
app.use(router)
app.use(VueApexCharts)
app.component('apexchart', VueApexCharts)

initI18n()

window.addEventListener('pageshow', async (event) => {
  if (!event.persisted) return

  const auth = useAuthStore(pinia)
  await auth.fetchUser()

  const current = router.currentRoute.value
  const meta = current.meta

  const allowedRoles = Array.isArray(meta.allowedRoles)
    ? meta.allowedRoles.filter((role) => typeof role === 'string')
    : typeof meta.role === 'string'
      ? [meta.role]
      : []

  if (meta.requiresAuth && !auth.isAuthenticated) {
    await router.replace({ name: 'login', query: { redirect: current.fullPath } })
    return
  }

  if (meta.guestOnly && auth.isAuthenticated) {
    await router.replace({ name: auth.dashboardRouteName })
    return
  }

  if (allowedRoles.length > 0) {
    const canAccessRoute = allowedRoles.some((role) => auth.roleNames.includes(role))
    if (!canAccessRoute) {
      await router.replace({ name: auth.isAuthenticated ? auth.dashboardRouteName : 'login' })
    }
  }
})

app.mount('#app')
