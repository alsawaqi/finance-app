<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import './inc/admin-theme.css'
import './inc/admin-theme-refresh.css'
import AdminSidebar from './inc/AdminSidebar.vue'
import AdminTopbar from './inc/AdminTopbar.vue'
import AppToastStack from '@/components/AppToastStack.vue'

const route = useRoute()
const sidebarOpen = ref(false)
const { t } = useI18n()

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function closeSidebar() {
  sidebarOpen.value = false
}

function syncBodyScrollLock(isOpen: boolean) {
  document.body.classList.toggle('admin-sidebar-open', isOpen)
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeSidebar()
  }
}

watch(
  () => route.fullPath,
  () => {
    closeSidebar()
  },
)

watch(sidebarOpen, (isOpen) => {
  syncBodyScrollLock(isOpen)
}, { immediate: true })

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  syncBodyScrollLock(false)
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="admin-shell" :class="{ 'is-sidebar-open': sidebarOpen }">
    <AdminSidebar :mobile-open="sidebarOpen" @close-sidebar="closeSidebar" />

    <button
      v-if="sidebarOpen"
      type="button"
      class="admin-shell__overlay"
      :aria-label="t('adminSidebar.closeSidebar')"
      @click="closeSidebar"
    ></button>

    <div class="admin-shell__main">
      <AdminTopbar @toggle-sidebar="toggleSidebar" />

      <main class="admin-shell__content">
        <router-view />
      </main>
    </div>

    <AppToastStack />
  </div>
</template>
