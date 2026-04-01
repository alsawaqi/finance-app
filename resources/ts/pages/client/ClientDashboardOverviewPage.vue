<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppLocaleSelect from '@/pages/public/inc/AppLocaleSelect.vue'
 
import { listClientRequests } from '@/services/clientPortal'
import { intakeFinanceType, intakeRequestedAmount } from '@/utils/requestIntake'
import { getClientWorkflowStageMeta } from '@/utils/clientRequestStage'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<any[]>([])
const { t } = useI18n()

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await listClientRequests()
    requests.value = data.requests ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('clientDashboard.errors.loadSummary')
  } finally {
    loading.value = false
  }
}

const totalRequests = computed(() => requests.value.length)
const awaitingSignature = computed(() => requests.value.filter((item) => item.current_contract?.status === 'admin_signed').length)
const awaitingDocuments = computed(() => requests.value.filter((item) => ['document_collection', 'awaiting_additional_documents'].includes(item.workflow_stage)).length)
const processingCount = computed(() => requests.value.filter((item) => item.workflow_stage === 'processing').length)
const actionNeeded = computed(() => awaitingSignature.value + awaitingDocuments.value)

const kpis = computed(() => [
  { label: t('clientDashboard.kpi.totalRequests'), value: totalRequests.value, badge: t('clientDashboard.kpi.allTime'), badgeClass: 'client-badge--blue' },
  { label: t('clientDashboard.kpi.actionNeeded'), value: actionNeeded.value, badge: t('clientDashboard.kpi.signOrUpload'), badgeClass: 'client-badge--amber' },
  { label: t('clientDashboard.kpi.waitingForSignature'), value: awaitingSignature.value, badge: t('clientDashboard.kpi.contractStage'), badgeClass: 'client-badge--purple' },
  { label: t('clientDashboard.kpi.inProcessing'), value: processingCount.value, badge: t('clientDashboard.kpi.withStaff'), badgeClass: 'client-badge--green' },
])

const statusCards = computed(() => {
  const counts = new Map<string, number>()
  requests.value.forEach((item) => {
    const key = item.status || t('clientDashboard.states.unknownStatus')
    counts.set(key, (counts.get(key) ?? 0) + 1)
  })
  return Array.from(counts.entries()).map(([label, value]) => ({ label, value }))
})

const recentRequests = computed(() => {
  return [...requests.value]
    .sort((a, b) => new Date(b.latest_activity_at || b.submitted_at || 0).getTime() - new Date(a.latest_activity_at || a.submitted_at || 0).getTime())
    .slice(0, 4)
})

function requestActionRoute(item: any) {
  if (item.current_contract?.status === 'admin_signed') {
    return { name: 'client-request-sign', params: { id: item.id } }
  }

  if (['document_collection', 'awaiting_additional_documents'].includes(item.workflow_stage)) {
    return { name: 'client-request-documents', params: { id: item.id } }
  }

  return { name: 'client-request-details', params: { id: item.id } }
}

function stageMeta(stage: string | null | undefined) {
  return getClientWorkflowStageMeta(stage)
}

function requestActionLabel(item: any) {
  if (item.current_contract?.status === 'admin_signed') return t('clientDashboard.actions.signContract')
  if (['document_collection', 'awaiting_additional_documents'].includes(item.workflow_stage)) return t('clientDashboard.actions.openDocuments')
  return t('clientDashboard.actions.viewRequest')
}

onMounted(load)
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <div class="client-dashboard-hero-top">
        <span class="client-eyebrow">{{ t('clientDashboard.hero.eyebrow') }}</span>
        <div class="client-dashboard-locale">
          <AppLocaleSelect id="client-dashboard-locale" mode="client" />
        </div>
      </div>

      <h1 class="client-hero-title">{{ t('clientDashboard.hero.title') }}</h1>
      <p class="client-hero-text">
        {{ t('clientDashboard.hero.subtitle') }}
      </p>
      <div class="client-hero-actions">
        <RouterLink :to="{ name: 'client-new-request' }" class="client-btn-primary">{{ t('clientDashboard.hero.createRequest') }}</RouterLink>
        <RouterLink :to="{ name: 'client-requests' }" class="client-btn-secondary">{{ t('clientDashboard.hero.viewRequests') }}</RouterLink>
      </div>
    </section>

    <div v-if="errorMessage" class="client-alert client-alert--error">{{ errorMessage }}</div>

    <section class="client-kpi-grid client-reveal-up">
      <article v-for="kpi in kpis" :key="kpi.label" class="client-kpi-card">
        <div class="client-kpi-value">{{ loading ? '…' : kpi.value }}</div>
        <h3>{{ kpi.label }}</h3>
        <span class="client-badge" :class="kpi.badgeClass">{{ kpi.badge }}</span>
      </article>
    </section>

    <section class="client-card-grid client-reveal-left">
      <article class="client-content-card client-content-card--half">
        <div class="client-card-head">
          <div>
            <h3>{{ t('clientDashboard.sections.requestsByStatusTitle') }}</h3>
            <p class="client-subtext">{{ t('clientDashboard.sections.requestsByStatusSubtitle') }}</p>
          </div>
        </div>
        <div v-if="loading" class="client-empty-state client-empty-state--inner">
          <h3>{{ t('clientDashboard.states.loadingSummary') }}</h3>
        </div>
        <div v-else-if="statusCards.length" class="client-status-chip-grid">
          <div v-for="item in statusCards" :key="item.label" class="client-status-chip-card">
            <strong>{{ item.value }}</strong>
            <span>{{ item.label }}</span>
          </div>
        </div>
        <div v-else class="client-empty-state client-empty-state--inner">
          <h3>{{ t('clientDashboard.states.noRequestsYet') }}</h3>
          <p class="client-muted">{{ t('clientDashboard.states.createFirstRequest') }}</p>
        </div>
      </article>

      <article class="client-content-card client-content-card--half">
        <div class="client-card-head">
          <div>
            <h3>{{ t('clientDashboard.sections.needActionTitle') }}</h3>
            <p class="client-subtext">{{ t('clientDashboard.sections.needActionSubtitle') }}</p>
          </div>
        </div>
        <div class="client-list">
          <div v-for="item in recentRequests.filter((entry) => requestActionLabel(entry) !== t('clientDashboard.actions.viewRequest')).slice(0, 3)" :key="item.id" class="client-list-item">
            <div>
              <strong>{{ item.reference_number }}</strong>
              <div class="client-list-item__meta">
                <span class="client-stage-badge" :class="stageMeta(item.workflow_stage).className">{{ stageMeta(item.workflow_stage).label }}</span>
              </div>
            </div>
            <RouterLink :to="requestActionRoute(item)" class="client-btn-secondary">{{ requestActionLabel(item) }}</RouterLink>
          </div>
          <div v-if="!recentRequests.filter((entry) => requestActionLabel(entry) !== t('clientDashboard.actions.viewRequest')).length" class="client-empty-state client-empty-state--inner">
            <h3>{{ t('clientDashboard.states.noPendingActions') }}</h3>
            <p class="client-muted">{{ t('clientDashboard.states.upToDate') }}</p>
          </div>
        </div>
      </article>
    </section>

    <section class="client-card-grid client-reveal-up">
      <article class="client-content-card client-content-card--full">
        <div class="client-card-head">
          <div>
            <h3>{{ t('clientDashboard.sections.recentRequestsTitle') }}</h3>
            <p class="client-subtext">{{ t('clientDashboard.sections.recentRequestsSubtitle') }}</p>
          </div>
        </div>
        <div v-if="recentRequests.length" class="client-request-summary-grid">
          <article v-for="item in recentRequests" :key="item.id" class="client-request-summary-card">
            <div class="client-card-head">
              <div>
                <h3>{{ item.reference_number }}</h3>
                <p class="client-meta">{{ intakeFinanceType(item.intake_details_json) }} · {{ intakeRequestedAmount(item.intake_details_json) }}</p>
              </div>
              <span class="client-stage-badge" :class="stageMeta(item.workflow_stage).className">{{ stageMeta(item.workflow_stage).label }}</span>
            </div>
            <div class="client-row-between">
              <span class="client-meta">{{ item.submitted_at ? new Date(item.submitted_at).toLocaleDateString() : t('clientDashboard.states.emptyDate') }}</span>
              <RouterLink :to="requestActionRoute(item)" class="client-btn-secondary">{{ requestActionLabel(item) }}</RouterLink>
            </div>
          </article>
        </div>
        <div v-else class="client-empty-state client-empty-state--inner">
          <h3>{{ t('clientDashboard.states.noRecentRequestsYet') }}</h3>
        </div>
      </article>
    </section>
  </div>
</template>
