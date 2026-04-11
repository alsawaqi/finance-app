<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { t } = useI18n()

const visible = computed(() => auth.sessionLoading && !auth.initialized)
</script>

<template>
  <Teleport to="body">
    <div v-if="visible" class="session-overlay" role="status" aria-live="polite">
      <div class="session-overlay__card">
        <div class="session-overlay__spinner" aria-hidden="true" />
        <div class="session-overlay__title">{{ t('common.session.loadingTitle') }}</div>
        <div class="session-overlay__text">{{ t('common.session.loadingBody') }}</div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.session-overlay {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: grid;
  place-items: center;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
}

.session-overlay__card {
  width: min(520px, calc(100vw - 2rem));
  border-radius: 18px;
  padding: 1.35rem 1.25rem 1.25rem;
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 18px 60px rgba(0, 0, 0, 0.22);
  display: grid;
  justify-items: center;
  gap: 0.8rem;
  text-align: center;
}

.session-overlay__spinner {
  width: 54px;
  height: 54px;
  border-radius: 999px;
  border: 5px solid rgba(148, 163, 184, 0.5);
  border-top-color: rgba(124, 58, 237, 0.95);
  animation: sessionspin 1s linear infinite;
}

.session-overlay__title {
  color: rgba(15, 23, 42, 0.92);
  font-weight: 800;
  font-size: 1.05rem;
}

.session-overlay__text {
  color: rgba(51, 65, 85, 0.92);
  line-height: 1.45;
}

@keyframes sessionspin {
  to {
    transform: rotate(360deg);
  }
}
</style>

