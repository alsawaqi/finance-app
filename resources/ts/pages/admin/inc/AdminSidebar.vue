<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import AppLocaleSelect from '@/pages/public/inc/AppLocaleSelect.vue'

const props = defineProps<{
  mobileOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close-sidebar'): void
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const displayName = computed(() => auth.user?.name || t('adminSidebar.defaultUserName'))
const displayEmail = computed(() => auth.user?.email || 'admin@finance.test')
const isStaffWorkspace = computed(() => auth.isStaff && !auth.isAdmin)
const workspaceTitle = computed(() => isStaffWorkspace.value ? t('adminSidebar.brand.staff') : t('adminSidebar.brand.admin'))
const workspaceSubtitle = computed(() => isStaffWorkspace.value ? t('adminSidebar.brand.staffSubtitle') : t('adminSidebar.brand.adminSubtitle'))

const menuItems = computed(() => {
  const items = [
    {
      group: 'workspace',
      label: t('adminSidebar.menu.dashboard'),
      icon: 'fas fa-chart-pie',
      to: { name: 'admin-dashboard' },
      active: route.name === 'admin-dashboard',
      badge: t('adminSidebar.badges.live'),
      show: auth.isAdmin,
    },
    {
      group: 'workspace',
      label: auth.isAdmin ? t('adminSidebar.menu.newRequests') : t('adminSidebar.menu.reviewQueue'),
      icon: 'fas fa-inbox',
      to: { name: 'admin-new-requests' },
      active: route.name === 'admin-new-requests' || route.name === 'admin-request-details' || route.name === 'admin-request-contract',
      badge: t('adminSidebar.badges.queue'),
      show: auth.isAdmin,
    },
    {
      group: 'workspace',
      label: t('adminSidebar.menu.assignments'),
      icon: 'fas fa-user-check',
      to: { name: 'admin-assignments' },
      active: route.name === 'admin-assignments',
      badge: t('adminSidebar.badges.next'),
      show: auth.isAdmin && auth.can('assign staff'),
    },
    {
      group: 'workspace',
      label: t('adminSidebar.menu.assignedRequests'),
      icon: 'fas fa-clipboard-check',
      to: { name: 'staff-requests' },
      active: route.name === 'staff-requests' || route.name === 'staff-request-details',
      badge: auth.isStaff && !auth.isAdmin ? t('adminSidebar.badges.myQueue') : t('adminSidebar.badges.workspace'),
      show:   auth.can('view assigned requests'),
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.requestQuestions'),
      icon: 'fas fa-list-check',
      to: { name: 'admin-request-questions' },
      active: route.name === 'admin-request-questions',
      badge: t('adminSidebar.badges.setup'),
      show: auth.can('manage questions'),
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.staffQuestionTemplates'),
      icon: 'fas fa-clipboard-question',
      to: { name: 'admin-staff-question-templates' },
      active: route.name === 'admin-staff-question-templates',
      badge: t('adminSidebar.badges.setup'),
      show: auth.isAdmin,
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.financeRequestTypes'),
      icon: 'fas fa-tags',
      to: { name: 'admin-finance-request-types' },
      active: route.name === 'admin-finance-request-types',
      badge: t('adminSidebar.badges.master'),
      show: auth.isAdmin,
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.documentSteps'),
      icon: 'fas fa-folder-open',
      to: { name: 'admin-document-upload-steps' },
      active: route.name === 'admin-document-upload-steps',
      badge: t('adminSidebar.badges.setup'),
      show: auth.can('manage document steps'),
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.staff'),
      icon: 'fas fa-user-shield',
      to: { name: 'admin-staff' },
      active: route.name === 'admin-staff',
      badge: t('adminSidebar.badges.control'),
      show: auth.can('manage staff'),
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.banks'),
      icon: 'fas fa-building-columns',
      to: { name: 'admin-banks' },
      active: route.name === 'admin-banks',
      badge: t('adminSidebar.badges.master'),
      show: auth.isAdmin,
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.categorization'),
      icon: 'fas fa-layer-group',
      to: { name: 'admin-categorization' },
      active: route.name === 'admin-categorization',
      badge: t('adminSidebar.badges.insights'),
      show: auth.isAdmin,
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.requestFiltration'),
      icon: 'fas fa-filter',
      to: { name: 'admin-request-filtration' },
      active: route.name === 'admin-request-filtration',
      badge: t('adminSidebar.badges.smart'),
      show: auth.isAdmin,
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.clientsDirectory'),
      icon: 'fas fa-users',
      to: { name: 'admin-clients-overview' },
      active: route.name === 'admin-clients-overview',
      badge: t('adminSidebar.badges.clients'),
      show: auth.isAdmin,
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.agents'),
      icon: 'fas fa-user-tie',
      to: { name: 'admin-agents' },
      active: route.name === 'admin-agents',
      badge: t('adminSidebar.badges.setup'),
      show: auth.can('manage agents'),
    },
    {
      group: 'workspace',
      label: t('adminSidebar.menu.inbox'),
      icon: 'fas fa-envelope-open-text',
      to: { name: 'admin-inbox' },
      active: route.name === 'admin-inbox',
      badge: auth.isAdmin ? t('adminSidebar.badges.workspace') : t('adminSidebar.badges.myQueue'),
      show: auth.isAdmin || auth.isStaff,
    },
    {
      group: 'setup',
      label: t('adminSidebar.menu.mailSettings'),
      icon: 'fas fa-envelope-circle-check',
      to: { name: 'admin-mail-settings' },
      active: route.name === 'admin-mail-settings',
      badge: t('adminSidebar.badges.setup'),
      show: auth.isAdmin,
    },
  ]

  return items.filter((item) => item.show)
})

const primaryItems = computed(() => menuItems.value.filter((item) => item.group === 'workspace'))
const setupItems = computed(() => menuItems.value.filter((item) => item.group === 'setup'))

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
          <strong>{{ workspaceTitle }}</strong>
          <small>{{ workspaceSubtitle }}</small>
        </div>
      </RouterLink>

      <button type="button" class="admin-sidebar__close" :aria-label="t('adminSidebar.closeSidebar')" @click="emit('close-sidebar')">
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
        <AppLocaleSelect id="admin-mobile-locale" mode="admin" short-labels />
        <button type="button" class="admin-logout-btn admin-logout-btn--sidebar" @click="handleLogout">
          <i class="fas fa-sign-out-alt"></i>
          <span>{{ t('adminTopbar.logout') }}</span>
        </button>
      </div>
    </div>

    <div class="admin-sidebar__body">
      <section class="admin-sidebar__intro">
        <span class="admin-sidebar__intro-kicker">{{ t('adminTopbar.eyebrow') }}</span>
        <h2>{{ workspaceTitle }}</h2>
        <p>{{ workspaceSubtitle }}</p>
      </section>

      <div class="admin-sidebar__section">
        <span class="admin-sidebar__label">{{ t('adminSidebar.mainNavigation') }}</span>

        <nav class="admin-sidebar__nav">
          <template v-for="item in primaryItems" :key="item.label">
            <RouterLink v-if="item.to" :to="item.to" class="admin-sidebar__link" :class="{ 'is-active': item.active }" @click="handleNavClick">
              <span class="admin-sidebar__link-icon"><i :class="item.icon"></i></span>
              <span class="admin-sidebar__link-text">{{ item.label }}</span>
              <span v-if="item.badge" class="admin-sidebar__link-badge">{{ item.badge }}</span>
            </RouterLink>
          </template>
        </nav>
      </div>

      <div v-if="setupItems.length" class="admin-sidebar__section admin-sidebar__section--secondary">
        <span class="admin-sidebar__label">{{ t('adminSidebar.badges.setup') }}</span>

        <nav class="admin-sidebar__nav">
          <template v-for="item in setupItems" :key="`${item.label}-setup`">
            <RouterLink v-if="item.to" :to="item.to" class="admin-sidebar__link" :class="{ 'is-active': item.active }" @click="handleNavClick">
              <span class="admin-sidebar__link-icon"><i :class="item.icon"></i></span>
              <span class="admin-sidebar__link-text">{{ item.label }}</span>
              <span v-if="item.badge" class="admin-sidebar__link-badge">{{ item.badge }}</span>
            </RouterLink>
          </template>
        </nav>
      </div>
    </div>

    <div class="admin-sidebar__footer">
      <div class="admin-sidebar__user-card">
        <div class="admin-sidebar__user-main">
          <span class="admin-profile-chip__avatar">{{ displayName.charAt(0).toUpperCase() }}</span>
          <div>
            <strong>{{ displayName }}</strong>
            <small>{{ displayEmail }}</small>
          </div>
        </div>

        <div class="admin-sidebar__footer-actions">
          <AppLocaleSelect id="admin-sidebar-locale" mode="admin" short-labels />
          <button type="button" class="admin-logout-btn admin-logout-btn--sidebar" @click="handleLogout">
            <i class="fas fa-sign-out-alt"></i>
            <span>{{ t('adminTopbar.logout') }}</span>
          </button>
        </div>
      </div>
    </div>
  </aside>
</template>
