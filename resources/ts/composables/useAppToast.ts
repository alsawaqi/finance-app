import { readonly, ref } from 'vue'

export type AppToastType = 'success' | 'error' | 'info'

export type AppToastItem = {
  id: number
  type: AppToastType
  message: string
  durationMs: number
}

type AppToastOptions = {
  durationMs?: number
}

const DEFAULT_DURATION_MS = 3200
const MAX_TOASTS = 5

const toasts = ref<AppToastItem[]>([])
const timeoutHandles = new Map<number, number>()
let nextToastId = 1

function normalizeMessage(message: string) {
  return message.trim()
}

function dismissToast(id: number) {
  const handle = timeoutHandles.get(id)
  if (typeof handle === 'number') {
    window.clearTimeout(handle)
    timeoutHandles.delete(id)
  }

  toasts.value = toasts.value.filter((item) => item.id !== id)
}

function queueToast(type: AppToastType, message: string, options: AppToastOptions = {}) {
  const normalized = normalizeMessage(message)
  if (!normalized) return

  const id = nextToastId++
  const durationMs = Math.max(800, options.durationMs ?? DEFAULT_DURATION_MS)
  const next: AppToastItem = {
    id,
    type,
    message: normalized,
    durationMs,
  }

  const updated = [...toasts.value, next]
  const overflow = updated.length - MAX_TOASTS
  if (overflow > 0) {
    updated.slice(0, overflow).forEach((item) => {
      dismissToast(item.id)
    })
  }
  toasts.value = updated.slice(-MAX_TOASTS)

  const timeoutHandle = window.setTimeout(() => {
    dismissToast(id)
  }, durationMs)
  timeoutHandles.set(id, timeoutHandle)
}

function clearToasts() {
  Array.from(timeoutHandles.values()).forEach((handle) => {
    window.clearTimeout(handle)
  })
  timeoutHandles.clear()
  toasts.value = []
}

export function useAppToast() {
  return {
    toasts: readonly(toasts),
    dismissToast,
    clearToasts,
    showToast: queueToast,
    showSuccess: (message: string, options?: AppToastOptions) => queueToast('success', message, options),
    showError: (message: string, options?: AppToastOptions) => queueToast('error', message, options),
    showInfo: (message: string, options?: AppToastOptions) => queueToast('info', message, options),
  }
}
