<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import AppLocaleSelect from '@/pages/public/inc/AppLocaleSelect.vue'
import AppNotificationBell from '@/components/AppNotificationBell.vue'

const emit = defineEmits<{
  (e: 'toggle-sidebar'): void
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const displayName = computed(() => auth.user?.name || t('adminSidebar.defaultUserName'))
const displayEmail = computed(() => auth.user?.email || 'admin@finance.test')
const workspaceLabel = computed(() => auth.isStaff && !auth.isAdmin ? t('adminSidebar.brand.staff') : t('adminSidebar.brand.admin'))
const currentSectionLabel = computed(() => {
  const name = String(route.name ?? '')

  if (name === 'admin-dashboard') return t('adminSidebar.menu.dashboard')
  if (name === 'admin-new-requests' || name === 'admin-request-details' || name === 'admin-request-contract') {
    return auth.isAdmin ? t('adminSidebar.menu.newRequests') : t('adminSidebar.menu.reviewQueue')
  }
  if (name === 'admin-assignments' || name === 'admin-assignment-details') return t('adminSidebar.menu.assignments')
  if (name === 'staff-requests' || name === 'staff-request-details') return t('adminSidebar.menu.assignedRequests')
  if (name === 'admin-request-questions') return t('adminSidebar.menu.requestQuestions')
  if (name === 'admin-staff-question-templates') return t('adminSidebar.menu.staffQuestionTemplates')
  if (name === 'admin-finance-request-types') return t('adminSidebar.menu.financeRequestTypes')
  if (name === 'admin-document-upload-steps') return t('adminSidebar.menu.documentSteps')
  if (name === 'admin-staff') return t('adminSidebar.menu.staff')
  if (name === 'admin-banks') return t('adminSidebar.menu.banks')
  if (name === 'admin-categorization') return t('adminSidebar.menu.categorization')
  if (name === 'admin-request-filtration') return t('adminSidebar.menu.requestFiltration')
  if (name === 'admin-clients-overview') return t('adminSidebar.menu.clientsDirectory')
  if (name === 'admin-agents') return t('adminSidebar.menu.agents')
  if (name === 'admin-inbox') return t('adminSidebar.menu.inbox')
  if (name === 'admin-mail-settings') return t('adminSidebar.menu.mailSettings')

  return t('adminTopbar.title')
})

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
        :aria-label="t('adminTopbar.toggleSidebar')"
        @click="emit('toggle-sidebar')"
      >
        <i class="fas fa-bars"></i>
      </button>

      <div class="admin-topbar__title-wrap">
        <span class="admin-topbar__eyebrow">{{ t('adminTopbar.eyebrow') }}</span>
        <h1>{{ currentSectionLabel }}</h1>
        <p>{{ t('adminTopbar.subtitle') }}</p>
      </div>
    </div>

    <div class="admin-topbar__right admin-reveal-up admin-reveal-delay-1">
      <div class="admin-topbar__workspace-chip">
        <span class="admin-topbar__workspace-dot"></span>
        <span>{{ workspaceLabel }}</span>
      </div>

      <AppNotificationBell theme="admin" />

      <div class="admin-topbar__locale">
        <AppLocaleSelect id="admin-topbar-locale" mode="admin" short-labels />
      </div>

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
        <span>{{ t('adminTopbar.logout') }}</span>
      </button>
    </div>
  </header>
</template>
