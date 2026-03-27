<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import './inc/admin-theme.css'
import AdminSidebar from './inc/AdminSidebar.vue'
import AdminTopbar from './inc/AdminTopbar.vue'

const route = useRoute()
const sidebarOpen = ref(false)

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function closeSidebar() {
  sidebarOpen.value = false
}

watch(
  () => route.fullPath,
  () => {
    closeSidebar()
  },
)
</script>

<template>
  <div class="admin-shell" :class="{ 'is-sidebar-open': sidebarOpen }">
    <AdminSidebar :mobile-open="sidebarOpen" @close-sidebar="closeSidebar" />

    <button
      v-if="sidebarOpen"
      type="button"
      class="admin-shell__overlay"
      aria-label="Close sidebar"
      @click="closeSidebar"
    ></button>

    <div class="admin-shell__main">
      <AdminTopbar @toggle-sidebar="toggleSidebar" />

      <main class="admin-shell__content">
        <router-view />
      </main>
    </div>
  </div>
</template>
