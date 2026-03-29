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

const menuItems = computed(() => {
  const items = [
    {
      label: 'Dashboard',
      icon: 'fas fa-chart-pie',
      to: { name: 'admin-dashboard' },
      active: route.name === 'admin-dashboard',
      badge: 'Live',
      show: auth.isAdmin,
    },
    {
      label: auth.isAdmin ? 'New Requests' : 'Review Queue',
      icon: 'fas fa-inbox',
      to: { name: 'admin-new-requests' },
      active: route.name === 'admin-new-requests' || route.name === 'admin-request-details' || route.name === 'admin-request-contract',
      badge: 'Queue',
      show: auth.isAdmin,
    },
    {
      label: 'Assignments',
      icon: 'fas fa-user-check',
      to: { name: 'admin-assignments' },
      active: route.name === 'admin-assignments',
      badge: 'Next',
      show: auth.isAdmin && auth.can('assign staff'),
    },
    {
      label: 'Assigned Requests',
      icon: 'fas fa-clipboard-check',
      to: { name: 'staff-requests' },
      active: route.name === 'staff-requests' || route.name === 'staff-request-details',
      badge: auth.isStaff && !auth.isAdmin ? 'My queue' : 'Workspace',
      show: auth.isAdmin || auth.can('view assigned requests'),
    },
    {
      label: 'Request Questions',
      icon: 'fas fa-list-check',
      to: { name: 'admin-request-questions' },
      active: route.name === 'admin-request-questions',
      badge: 'Setup',
      show: auth.can('manage questions'),
    },
    {
      label: 'Document Steps',
      icon: 'fas fa-folder-open',
      to: { name: 'admin-document-upload-steps' },
      active: route.name === 'admin-document-upload-steps',
      badge: 'Setup',
      show: auth.can('manage document steps'),
    },
    {
      label: 'Staff',
      icon: 'fas fa-user-shield',
      to: { name: 'admin-staff' },
      active: route.name === 'admin-staff',
      badge: 'Control',
      show: auth.can('manage staff'),
    },
    {
      label: 'Agents',
      icon: 'fas fa-user-tie',
      to: { name: 'admin-agents' },
      active: route.name === 'admin-agents',
      badge: 'Setup',
      show: auth.can('manage agents'),
    },
  ]

  return items.filter((item) => item.show)
})

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
          <strong>{{ auth.isStaff && !auth.isAdmin ? 'Finance Staff' : 'Finance Admin' }}</strong>
          <small>{{ auth.isStaff && !auth.isAdmin ? 'Assigned Workspace' : 'Operations Console' }}</small>
        </div>
      </RouterLink>

      <button type="button" class="admin-sidebar__close" aria-label="Close sidebar" @click="emit('close-sidebar')">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="admin-sidebar__mobile-tools">
      <div class="admin-sidebar__mobile-profile">
        <span class="admin-profile-chip__avatar">{{ displayName.charAt(0).toUpperCase() }}</span>
        <div>
          <strong>{{ displayName }}</strong>
          <small>{{ displayEmail }}</small>
        </div>
      </div>

      <div class="admin-sidebar__mobile-actions">
        <button type="button" class="admin-icon-btn" aria-label="Search"><i class="fas fa-search"></i></button>
        <button type="button" class="admin-icon-btn" aria-label="Notifications"><i class="fas fa-bell"></i></button>
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
          <RouterLink v-if="item.to" :to="item.to" class="admin-sidebar__link" :class="{ 'is-active': item.active }" @click="handleNavClick">
            <span class="admin-sidebar__link-icon"><i :class="item.icon"></i></span>
            <span class="admin-sidebar__link-text">{{ item.label }}</span>
            <span v-if="item.badge" class="admin-sidebar__link-badge">{{ item.badge }}</span>
          </RouterLink>
        </template>
      </nav>
    </div>
  </aside>
</template>
