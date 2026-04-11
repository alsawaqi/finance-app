import { computed, readonly, ref } from 'vue'
import { i18n } from '@/i18n'

type OverlayMode = 'idle' | 'loading' | 'success'

const activeMutationCount = ref(0)
const overlayMode = ref<OverlayMode>('idle')
const overlayVisible = ref(false)
const overlayMessage = ref('')

let hideTimer: number | null = null

function clearHideTimer() {
  if (hideTimer !== null) {
    window.clearTimeout(hideTimer)
    hideTimer = null
  }
}

function defaultLoadingMessage() {
  return i18n.global.t('common.transactionOverlay.loading')
}

function defaultSuccessMessage() {
  return i18n.global.t('common.transactionOverlay.success')
}

function showLoading(message?: string) {
  clearHideTimer()
  overlayMode.value = 'loading'
  overlayMessage.value = (message && message.trim()) || defaultLoadingMessage()
  overlayVisible.value = true
}

function showSuccess(message?: string) {
  clearHideTimer()
  overlayMode.value = 'success'
  overlayMessage.value = (message && message.trim()) || defaultSuccessMessage()
  overlayVisible.value = true

  hideTimer = window.setTimeout(() => {
    overlayVisible.value = false
    overlayMode.value = 'idle'
    overlayMessage.value = ''
    hideTimer = null
  }, 900)
}

function beginMutation(message?: string) {
  activeMutationCount.value += 1
  showLoading(message)
}

function endMutationSuccess(message?: string) {
  if (activeMutationCount.value > 0) {
    activeMutationCount.value -= 1
  }

  if (activeMutationCount.value === 0) {
    showSuccess(message)
  }
}

function endMutationError() {
  if (activeMutationCount.value > 0) {
    activeMutationCount.value -= 1
  }

  if (activeMutationCount.value === 0) {
    clearHideTimer()
    overlayVisible.value = false
    overlayMode.value = 'idle'
    overlayMessage.value = ''
  }
}

export function useTransactionOverlay() {
  return {
    visible: readonly(overlayVisible),
    mode: readonly(overlayMode),
    message: readonly(overlayMessage),
    isBusy: computed(() => overlayVisible.value && overlayMode.value === 'loading'),
    beginMutation,
    endMutationSuccess,
    endMutationError,
  }
}

