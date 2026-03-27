<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  mobileOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close-sidebar'): void
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const displayName = computed(() => auth.user?.name || 'Admin User')
const displayEmail = computed(() => auth.user?.email || 'admin@finance.test')

const menuItems = computed(() => [
  {
    label: 'Dashboard',
    icon: 'fas fa-chart-pie',
    to: { name: 'admin-dashboard' },
    active: route.name === 'admin-dashboard',
    badge: 'Live',
  },
  {
    label: 'Request Questions',
    icon: 'fas fa-list-check',
    to: { name: 'admin-request-questions' },
    active: route.name === 'admin-request-questions',
    badge: 'Setup',
  },
  {
    label: 'Requests Queue',
    icon: 'fas fa-inbox',
    note: 'Next',
  },
  {
    label: 'Approvals',
    icon: 'fas fa-check-circle',
    note: 'Next',
  },
  {
    label: 'Contracts',
    icon: 'fas fa-file-signature',
    note: 'Next',
  },
  {
    label: 'Documents',
    icon: 'fas fa-folder-open',
    note: 'Next',
  },
  {
    label: 'Clients',
    icon: 'fas fa-users',
    note: 'Next',
  },
  {
    label: 'Settings',
    icon: 'fas fa-cog',
    note: 'Later',
  },
])

async function handleLogout() {
  await auth.logout()
  emit('close-sidebar')
  await router.replace({ name: 'login' })
}

function handleNavClick() {
  emit('close-sidebar')
}
</script>

<template>
  <aside class="admin-sidebar" :class="{ 'is-mobile-open': props.mobileOpen }">
    <div class="admin-sidebar__brand">
      <RouterLink to="/admin" class="admin-sidebar__brand-link" @click="handleNavClick">
        <span class="admin-sidebar__brand-logo">F</span>
        <div>
          <strong>Finance Admin</strong>
          <small>Operations Console</small>
        </div>
      </RouterLink>

      <button
        type="button"
        class="admin-sidebar__close"
        aria-label="Close sidebar"
        @click="emit('close-sidebar')"
      >
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="admin-sidebar__mobile-tools">
      <div class="admin-sidebar__mobile-profile">
        <span class="admin-profile-chip__avatar">
          {{ displayName.charAt(0).toUpperCase() }}
        </span>
        <div>
          <strong>{{ displayName }}</strong>
          <small>{{ displayEmail }}</small>
        </div>
      </div>

      <div class="admin-sidebar__mobile-actions">
        <button type="button" class="admin-icon-btn" aria-label="Search">
          <i class="fas fa-search"></i>
        </button>
        <button type="button" class="admin-icon-btn" aria-label="Notifications">
          <i class="fas fa-bell"></i>
        </button>
        <button type="button" class="admin-logout-btn admin-logout-btn--sidebar" @click="handleLogout">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </button>
      </div>
    </div>

    <div class="admin-sidebar__section">
      <span class="admin-sidebar__label">Main navigation</span>

      <nav class="admin-sidebar__nav">
        <template v-for="item in menuItems" :key="item.label">
          <RouterLink
            v-if="item.to"
            :to="item.to"
            class="admin-sidebar__link"
            :class="{ 'is-active': item.active }"
            @click="handleNavClick"
          >
            <span class="admin-sidebar__link-icon"><i :class="item.icon"></i></span>
            <span class="admin-sidebar__link-text">{{ item.label }}</span>
            <span v-if="item.badge" class="admin-sidebar__link-badge">{{ item.badge }}</span>
          </RouterLink>

          <button
            v-else
            type="button"
            class="admin-sidebar__link is-muted"
          >
            <span class="admin-sidebar__link-icon"><i :class="item.icon"></i></span>
            <span class="admin-sidebar__link-text">{{ item.label }}</span>
            <span v-if="item.note" class="admin-sidebar__link-note">{{ item.note }}</span>
          </button>
        </template>
      </nav>
    </div>

    <div class="admin-sidebar__card">
      <span class="admin-sidebar__card-eyebrow">Today’s focus</span>
      <h4>Review new finance requests</h4>
      <p>
        Keep approvals, contracts, and document collection flowing from a single admin workspace.
      </p>
      <RouterLink :to="{ name: 'admin-dashboard' }" class="admin-sidebar__card-btn" @click="handleNavClick">
        Open dashboard
      </RouterLink>
    </div>
  </aside>
</template>
