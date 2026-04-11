import axios from 'axios'
import { i18n } from '@/i18n'
import { useAppToast } from '@/composables/useAppToast'
import { useAppProgress } from '@/composables/useAppProgress'
import { useTransactionOverlay } from '@/composables/useTransactionOverlay'

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
  timeout: 25000,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

const MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete'])
const TOAST_PATH_PREFIXES = ['/api/admin/', '/api/staff/', '/api/client/']

function resolveRequestPath(url: string | undefined) {
  if (!url) return ''

  try {
    return new URL(url, baseURL).pathname
  } catch {
    return url.split('?')[0] ?? ''
  }
}

function shouldToast(config: { method?: string; url?: string } | undefined) {
  if (!config) return false

  const method = typeof config.method === 'string' ? config.method.toLowerCase() : 'get'
  if (!MUTATING_METHODS.has(method)) return false

  const path = resolveRequestPath(config.url)
  return TOAST_PATH_PREFIXES.some((prefix) => path.startsWith(prefix))
}

function shouldTrackProgress(config: { headers?: unknown } | undefined) {
  if (!config) return true

  const headers = (config.headers ?? {}) as Record<string, unknown>
  const rawSkip = headers['X-Skip-Progress']

  if (typeof rawSkip === 'string' && ['1', 'true', 'yes'].includes(rawSkip.toLowerCase())) {
    return false
  }

  if (typeof rawSkip === 'number' && rawSkip === 1) {
    return false
  }

  if (typeof rawSkip === 'boolean' && rawSkip) {
    return false
  }

  return true
}

function shouldShowTransactionOverlay(config: { method?: string; headers?: unknown } | undefined) {
  if (!config) return false

  const method = typeof config.method === 'string' ? config.method.toLowerCase() : 'get'
  if (!MUTATING_METHODS.has(method)) return false

  const headers = (config.headers ?? {}) as Record<string, unknown>
  const rawSkip = headers['X-Skip-Transaction-Overlay']

  if (typeof rawSkip === 'string' && ['1', 'true', 'yes'].includes(rawSkip.toLowerCase())) {
    return false
  }
  if (typeof rawSkip === 'number' && rawSkip === 1) {
    return false
  }
  if (typeof rawSkip === 'boolean' && rawSkip) {
    return false
  }

  return true
}

function extractMessage(payload: unknown) {
  if (!payload || typeof payload !== 'object') return ''
  const raw = (payload as { message?: unknown }).message
  return typeof raw === 'string' ? raw.trim() : ''
}

function extractValidationMessage(payload: unknown) {
  if (!payload || typeof payload !== 'object') return ''

  const errors = (payload as { errors?: unknown }).errors
  if (!errors || typeof errors !== 'object') return ''

  for (const value of Object.values(errors as Record<string, unknown>)) {
    if (typeof value === 'string' && value.trim()) {
      return value.trim()
    }
    if (Array.isArray(value)) {
      const first = value.find((item) => typeof item === 'string' && item.trim())
      if (typeof first === 'string') {
        return first.trim()
      }
    }
  }

  return ''
}

function defaultSuccessMessage() {
  return i18n.global.t('common.api.defaultSuccess')
}

function defaultErrorMessage() {
  return i18n.global.t('common.api.defaultError')
}

const appProgress = useAppProgress()
const txOverlay = useTransactionOverlay()

api.interceptors.request.use(
  (config) => {
    if (shouldTrackProgress(config)) {
      ;(config as unknown as Record<string, unknown>).__trackProgress = true
      appProgress.beginRequest()
    }

    if (shouldShowTransactionOverlay(config)) {
      ;(config as unknown as Record<string, unknown>).__txOverlay = true
      txOverlay.beginMutation()
    }

    return config
  },
  (error) => {
    const config = (error as { config?: Record<string, unknown> })?.config
    if (config?.__trackProgress) {
      appProgress.endRequest()
    }
    if (config?.__txOverlay) {
      txOverlay.endMutationError()
    }
    return Promise.reject(error)
  },
)

api.interceptors.response.use(
  (response) => {
    if ((response.config as unknown as Record<string, unknown>).__trackProgress) {
      appProgress.endRequest()
    }

    if ((response.config as unknown as Record<string, unknown>).__txOverlay) {
      const message = extractMessage(response.data) || defaultSuccessMessage()
      txOverlay.endMutationSuccess(message)
    }

    if (shouldToast(response.config)) {
      const { showSuccess } = useAppToast()
      const message = extractMessage(response.data) || defaultSuccessMessage()
      showSuccess(message)
    }

    return response
  },
  (error) => {
    const config = (error as { config?: Record<string, unknown> })?.config
    if (config?.__trackProgress) {
      appProgress.endRequest()
    }
    if (config?.__txOverlay) {
      txOverlay.endMutationError()
    }

    if (axios.isAxiosError(error) && error.code !== 'ERR_CANCELED' && shouldToast(error.config)) {
      const { showError } = useAppToast()
      const message = extractMessage(error.response?.data)
        || extractValidationMessage(error.response?.data)
        || defaultErrorMessage()
      showError(message, { durationMs: 4200 })
    }

    return Promise.reject(error)
  },
)

export default api
