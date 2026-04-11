<script setup lang="ts">
import { computed } from 'vue'
import { useTransactionOverlay } from '@/composables/useTransactionOverlay'

const overlay = useTransactionOverlay()

const isSuccess = computed(() => overlay.mode.value === 'success')
</script>

<template>
  <Teleport to="body">
    <div v-if="overlay.visible.value" class="tx-overlay" role="status" aria-live="polite">
      <div class="tx-overlay__card">
        <div v-if="!isSuccess" class="tx-overlay__spinner" aria-hidden="true" />
        <div v-else class="tx-overlay__success" aria-hidden="true">✓</div>
        <div class="tx-overlay__text">{{ overlay.message.value }}</div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.tx-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: grid;
  place-items: center;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(2px);
}

.tx-overlay__card {
  width: min(460px, calc(100vw - 2rem));
  border-radius: 18px;
  padding: 1.25rem 1.25rem 1.15rem;
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 18px 60px rgba(0, 0, 0, 0.24);
  display: grid;
  justify-items: center;
  gap: 0.85rem;
  text-align: center;
}

.tx-overlay__spinner {
  width: 54px;
  height: 54px;
  border-radius: 999px;
  border: 5px solid rgba(148, 163, 184, 0.5);
  border-top-color: rgba(37, 99, 235, 0.95);
  animation: txspin 1s linear infinite;
}

.tx-overlay__success {
  width: 54px;
  height: 54px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgba(16, 185, 129, 0.12);
  color: rgba(16, 185, 129, 1);
  font-weight: 800;
  font-size: 28px;
  border: 2px solid rgba(16, 185, 129, 0.35);
}

.tx-overlay__text {
  color: rgba(15, 23, 42, 0.92);
  font-weight: 650;
  line-height: 1.35;
}

@keyframes txspin {
  to {
    transform: rotate(360deg);
  }
}
</style>

