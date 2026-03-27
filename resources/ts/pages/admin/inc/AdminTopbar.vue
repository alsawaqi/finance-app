<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits<{
  (e: 'toggle-sidebar'): void
}>()

const router = useRouter()
const auth = useAuthStore()

const displayName = computed(() => auth.user?.name || 'Admin User')
const displayEmail = computed(() => auth.user?.email || 'admin@finance.test')

async function handleLogout() {
  await auth.logout()
  await router.replace({ name: 'login' })
}
</script>

<template>
  <header class="admin-topbar">
    <div class="admin-topbar__left admin-reveal-up">
      <button
        type="button"
        class="admin-topbar__toggle"
        aria-label="Toggle sidebar"
        @click="emit('toggle-sidebar')"
      >
        <i class="fas fa-bars"></i>
      </button>

      <div class="admin-topbar__title-wrap">
        <span class="admin-topbar__eyebrow">Admin workspace</span>
        <h1>Finance control center</h1>
        <p>Monitor approvals, signatures, and request processing.</p>
      </div>
    </div>

    <div class="admin-topbar__right admin-reveal-up admin-reveal-delay-1">
      <button type="button" class="admin-icon-btn" aria-label="Search">
        <i class="fas fa-search"></i>
      </button>
      <button type="button" class="admin-icon-btn" aria-label="Notifications">
        <i class="fas fa-bell"></i>
      </button>

      <div class="admin-profile-chip">
        <span class="admin-profile-chip__avatar">
          {{ displayName.charAt(0).toUpperCase() }}
        </span>
        <div>
          <strong>{{ displayName }}</strong>
          <small>{{ displayEmail }}</small>
        </div>
      </div>

      <button type="button" class="admin-logout-btn" @click="handleLogout">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </button>
    </div>
  </header>
</template>
