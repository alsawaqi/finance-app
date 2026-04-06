import { computed, readonly, ref } from 'vue'

const activeRequestCount = ref(0)
const navigationActive = ref(false)
const progressValue = ref(0)
const progressVisible = ref(false)

let trickleTimer: number | null = null
let settleTimer: number | null = null

function clearTimers() {
  if (trickleTimer !== null) {
    window.clearInterval(trickleTimer)
    trickleTimer = null
  }

  if (settleTimer !== null) {
    window.clearTimeout(settleTimer)
    settleTimer = null
  }
}

function startTrickle() {
  if (trickleTimer !== null) return

  trickleTimer = window.setInterval(() => {
    if (!progressVisible.value) return

    const ceiling = activeRequestCount.value > 0 || navigationActive.value ? 92 : 96
    if (progressValue.value >= ceiling) return

    const remaining = 100 - progressValue.value
    const delta = Math.max(0.6, remaining / 22)
    progressValue.value = Math.min(ceiling, progressValue.value + delta)
  }, 140)
}

function showProgress() {
  if (settleTimer !== null) {
    window.clearTimeout(settleTimer)
    settleTimer = null
  }

  if (!progressVisible.value) {
    progressVisible.value = true
  }

  if (progressValue.value < 8) {
    progressValue.value = 8
  }

  startTrickle()
}

function completeIfIdle() {
  if (activeRequestCount.value > 0 || navigationActive.value) return

  if (trickleTimer !== null) {
    window.clearInterval(trickleTimer)
    trickleTimer = null
  }

  progressValue.value = 100

  if (settleTimer !== null) {
    window.clearTimeout(settleTimer)
  }

  settleTimer = window.setTimeout(() => {
    progressVisible.value = false
    progressValue.value = 0
    settleTimer = null
  }, 230)
}

function beginRequest() {
  activeRequestCount.value += 1
  showProgress()
}

function endRequest() {
  if (activeRequestCount.value > 0) {
    activeRequestCount.value -= 1
  }

  completeIfIdle()
}

function startNavigation() {
  navigationActive.value = true
  showProgress()
}

function finishNavigation() {
  navigationActive.value = false
  completeIfIdle()
}

function resetProgress() {
  activeRequestCount.value = 0
  navigationActive.value = false
  clearTimers()
  progressVisible.value = false
  progressValue.value = 0
}

export function useAppProgress() {
  return {
    visible: readonly(progressVisible),
    progress: readonly(progressValue),
    isBusy: computed(() => progressVisible.value),
    beginRequest,
    endRequest,
    startNavigation,
    finishNavigation,
    resetProgress,
  }
}
