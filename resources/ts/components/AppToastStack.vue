<script setup lang="ts">
import { useAppToast } from '@/composables/useAppToast'

const { toasts, dismissToast } = useAppToast()
</script>

<template>
  <div class="app-toast-stack" aria-live="polite" aria-atomic="true">
    <transition-group name="app-toast-list" tag="div" class="app-toast-stack__list">
      <article
        v-for="toast in toasts"
        :key="toast.id"
        class="app-toast"
        :class="`is-${toast.type}`"
        role="status"
      >
        <p class="app-toast__message">{{ toast.message }}</p>
        <button
          type="button"
          class="app-toast__dismiss"
          aria-label="Dismiss notification"
          @click="dismissToast(toast.id)"
        >
          &times;
        </button>
      </article>
    </transition-group>
  </div>
</template>

<style scoped>
.app-toast-stack {
  position: fixed;
  inset-block-start: 16px;
  inset-inline-end: 16px;
  z-index: 1300;
  width: min(420px, calc(100vw - 24px));
  pointer-events: none;
}

.app-toast-stack__list {
  display: grid;
  gap: 10px;
}

.app-toast {
  pointer-events: auto;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: start;
  gap: 10px;
  border-radius: 14px;
  padding: 12px 14px;
  background: #ffffff;
  border: 1px solid #d6dbe9;
  box-shadow: 0 16px 32px rgba(15, 23, 42, 0.14);
}

.app-toast__message {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.45;
  color: #0f172a;
}

.app-toast__dismiss {
  border: 0;
  background: transparent;
  color: #64748b;
  font-size: 1.15rem;
  line-height: 1;
  border-radius: 8px;
  cursor: pointer;
  padding: 3px 6px;
}

.app-toast__dismiss:hover {
  background: rgba(15, 23, 42, 0.08);
  color: #0f172a;
}

.app-toast.is-success {
  background: linear-gradient(145deg, #ffffff 0%, #f2fcf6 100%);
  border-color: #86efac;
}

.app-toast.is-error {
  background: linear-gradient(145deg, #ffffff 0%, #fef2f2 100%);
  border-color: #fca5a5;
}

.app-toast.is-info {
  background: linear-gradient(145deg, #ffffff 0%, #eff6ff 100%);
  border-color: #93c5fd;
}

.app-toast-list-enter-active,
.app-toast-list-leave-active {
  transition: all 180ms ease;
}

.app-toast-list-enter-from,
.app-toast-list-leave-to {
  opacity: 0;
  transform: translate3d(0, -8px, 0);
}

@media (max-width: 768px) {
  .app-toast-stack {
    inset-block-start: 10px;
    inset-inline-end: 10px;
    width: calc(100vw - 20px);
  }
}
</style>
