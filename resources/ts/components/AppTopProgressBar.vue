<script setup lang="ts">
import { computed } from 'vue'
import { useAppProgress } from '@/composables/useAppProgress'

const { visible, progress } = useAppProgress()

const ariaNow = computed(() => Math.max(0, Math.min(100, Math.round(progress.value))))
const barWidth = computed(() => `${Math.max(1, Math.min(100, progress.value))}%`)
</script>

<template>
  <div
    v-show="visible"
    class="app-top-progress"
    role="progressbar"
    aria-label="Loading progress"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-valuenow="ariaNow"
  >
    <div class="app-top-progress__bar" :style="{ width: barWidth }"></div>
  </div>
</template>

<style scoped>
.app-top-progress {
  position: fixed;
  inset-inline: 0;
  top: 0;
  z-index: 1400;
  height: 3px;
  pointer-events: none;
  background: rgba(30, 41, 59, 0.08);
  backdrop-filter: blur(1px);
}

.app-top-progress__bar {
  height: 100%;
  background: linear-gradient(90deg, #3b82f6 0%, #22d3ee 50%, #6366f1 100%);
  box-shadow: 0 0 12px rgba(59, 130, 246, 0.55);
  transition: width 180ms ease;
}
</style>
