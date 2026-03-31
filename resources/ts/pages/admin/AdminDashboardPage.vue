<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
 
import { getAdminCategorization } from '@/services/adminCategorization'

const loading = ref(true)
const errorMessage = ref('')
const { t } = useI18n()
const summary = ref({
  total_requests: 0,
  submitted_requests: 0,
  active_requests: 0,
  completed_requests: 0,
  total_clients: 0,
  total_staff: 0,
  total_agents: 0,
  with_additional_document_requests: 0,
})
const statusBreakdown = ref<Record<string, number>>({})

const cards = computed(() => [
  { label: t('adminDashboard.cards.submittedQueue'), value: summary.value.submitted_requests, tone: 'amber', route: { name: 'admin-new-requests' } },
  { label: t('adminDashboard.cards.activeRequests'), value: summary.value.active_requests, tone: 'blue', route: { name: 'admin-categorization' } },
  { label: t('adminDashboard.cards.staffMembers'), value: summary.value.total_staff, tone: 'violet', route: { name: 'admin-staff' } },
  { label: t('adminDashboard.cards.linkedAgents'), value: summary.value.total_agents, tone: 'emerald', route: { name: 'admin-agents' } },
])

const topStatuses = computed(() => Object.entries(statusBreakdown.value).sort((a, b) => b[1] - a[1]).slice(0, 5))

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await getAdminCategorization()
    summary.value = data.summary
    statusBreakdown.value = data.status_breakdown ?? {}
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminDashboard.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-dashboard-page">
    <section class="admin-hero admin-reveal-up">
      <div class="admin-hero__content">
        <span class="admin-hero__eyebrow">{{ t('adminDashboard.hero.eyebrow') }}</span>
        <h2>{{ t('adminDashboard.hero.title') }}</h2>
        <p>
          {{ t('adminDashboard.hero.subtitle') }}
        </p>
      </div>

      <div class="admin-hero__actions admin-hero__actions--stacked">
        <RouterLink :to="{ name: 'admin-new-requests' }" class="admin-primary-btn">{{ t('adminDashboard.actions.openReviewQueue') }}</RouterLink>
        <RouterLink :to="{ name: 'admin-categorization' }" class="admin-secondary-btn">{{ t('adminDashboard.actions.openCategorization') }}</RouterLink>
      </div>
    </section>

    <div v-if="errorMessage" class="admin-alert admin-alert--error">{{ errorMessage }}</div>

    <div class="admin-question-stats-grid admin-reveal-up admin-reveal-delay-1">
      <article v-for="card in cards" :key="card.label" class="admin-question-stat" :class="`tone-${card.tone}`">
        <strong>{{ loading ? '…' : card.value }}</strong>
        <span>{{ card.label }}</span>
        <RouterLink :to="card.route" class="admin-inline-link">{{ t('adminDashboard.actions.open') }}</RouterLink>
      </article>
    </div>

    <div class="admin-dashboard-grid">
      <article class="panel-card catalog-highlight-card">
        <div class="panel-head"><h2>{{ t('adminDashboard.sections.quickDistribution') }}</h2></div>
        <div class="catalog-chip-grid">
          <span v-for="entry in topStatuses" :key="entry[0]" class="soft-tag">{{ entry[0] }} · {{ entry[1] }}</span>
        </div>
      </article>

      <article class="panel-card catalog-highlight-card">
        <div class="panel-head"><h2>{{ t('adminDashboard.sections.currentTotals') }}</h2></div>
        <div class="catalog-mini-stats">
          <div>
            <span>{{ t('adminDashboard.totals.totalRequests') }}</span>
            <strong>{{ loading ? '…' : summary.total_requests }}</strong>
          </div>
          <div>
            <span>{{ t('adminDashboard.totals.completed') }}</span>
            <strong>{{ loading ? '…' : summary.completed_requests }}</strong>
          </div>
          <div>
            <span>{{ t('adminDashboard.totals.clients') }}</span>
            <strong>{{ loading ? '…' : summary.total_clients }}</strong>
          </div>
          <div>
            <span>{{ t('adminDashboard.totals.additionalDocs') }}</span>
            <strong>{{ loading ? '…' : summary.with_additional_document_requests }}</strong>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
