import axios from 'axios'

/**
 * Vite inlines VITE_* at build time. A local URL in .env (e.g. http://127.0.0.1:8000)
 * would otherwise ship in production and break the live site. In production builds,
 * always use the browser origin (same host as the Laravel app).
 */
function resolveApiBaseURL(): string {
  if (import.meta.env.PROD) {
    return window.location.origin.replace(/\/$/, '')
  }
  const fromEnv = import.meta.env.VITE_API_BASE_URL
  if (typeof fromEnv === 'string' && fromEnv.trim() !== '') {
    return fromEnv.replace(/\/$/, '')
  }
  return window.location.origin.replace(/\/$/, '')
}

const baseURL = resolveApiBaseURL()

const api = axios.create({
  baseURL,
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export default api