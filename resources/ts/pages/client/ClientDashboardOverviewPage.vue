<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
 
import { listClientRequests } from '@/services/clientPortal'
import { intakeFinanceType, intakeRequestedAmount } from '@/utils/requestIntake'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<any[]>([])

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await listClientRequests()
    requests.value = data.requests ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load your dashboard summary.'
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
  { label: 'Total Requests', value: totalRequests.value, badge: 'All time', badgeClass: 'client-badge--blue' },
  { label: 'Action Needed', value: actionNeeded.value, badge: 'Sign or upload', badgeClass: 'client-badge--amber' },
  { label: 'Waiting for Signature', value: awaitingSignature.value, badge: 'Contract stage', badgeClass: 'client-badge--purple' },
  { label: 'In Processing', value: processingCount.value, badge: 'With staff', badgeClass: 'client-badge--green' },
])

const statusCards = computed(() => {
  const counts = new Map<string, number>()
  requests.value.forEach((item) => {
    const key = item.status || 'unknown'
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

function requestActionLabel(item: any) {
  if (item.current_contract?.status === 'admin_signed') return 'Sign contract'
  if (['document_collection', 'awaiting_additional_documents'].includes(item.workflow_stage)) return 'Open documents'
  return 'View request'
}

onMounted(load)
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">Client Dashboard</span>
      <h1 class="client-hero-title">A simpler view of your real request activity.</h1>
      <p class="client-hero-text">
        See how many requests you have, which ones need your action, and open the most recent request without browsing through unnecessary panels.
      </p>
      <div class="client-hero-actions">
        <RouterLink :to="{ name: 'client-new-request' }" class="client-btn-primary">Create New Request</RouterLink>
        <RouterLink :to="{ name: 'client-requests' }" class="client-btn-secondary">View My Requests</RouterLink>
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
            <h3>Requests by status</h3>
            <p class="client-subtext">A real summary built from your actual submitted requests.</p>
          </div>
        </div>
        <div v-if="loading" class="client-empty-state client-empty-state--inner">
          <h3>Loading summary</h3>
        </div>
        <div v-else-if="statusCards.length" class="client-status-chip-grid">
          <div v-for="item in statusCards" :key="item.label" class="client-status-chip-card">
            <strong>{{ item.value }}</strong>
            <span>{{ item.label }}</span>
          </div>
        </div>
        <div v-else class="client-empty-state client-empty-state--inner">
          <h3>No requests yet</h3>
          <p class="client-muted">Create your first request to see live dashboard summaries.</p>
        </div>
      </article>

      <article class="client-content-card client-content-card--half">
        <div class="client-card-head">
          <div>
            <h3>Need your action</h3>
            <p class="client-subtext">These requests are waiting for a client-side response.</p>
          </div>
        </div>
        <div class="client-list">
          <div v-for="item in recentRequests.filter((entry) => requestActionLabel(entry) !== 'View request').slice(0, 3)" :key="item.id" class="client-list-item">
            <div>
              <strong>{{ item.reference_number }}</strong>
              <p class="client-meta">{{ item.workflow_stage }}</p>
            </div>
            <RouterLink :to="requestActionRoute(item)" class="client-btn-secondary">{{ requestActionLabel(item) }}</RouterLink>
          </div>
          <div v-if="!recentRequests.filter((entry) => requestActionLabel(entry) !== 'View request').length" class="client-empty-state client-empty-state--inner">
            <h3>No pending client actions</h3>
            <p class="client-muted">You are up to date right now.</p>
          </div>
        </div>
      </article>
    </section>

    <section class="client-card-grid client-reveal-up">
      <article class="client-content-card client-content-card--full">
        <div class="client-card-head">
          <div>
            <h3>Recent requests</h3>
            <p class="client-subtext">Open the most recent request directly from here.</p>
          </div>
        </div>
        <div v-if="recentRequests.length" class="client-request-summary-grid">
          <article v-for="item in recentRequests" :key="item.id" class="client-request-summary-card">
            <div class="client-card-head">
              <div>
                <h3>{{ item.reference_number }}</h3>
                <p class="client-meta">{{ intakeFinanceType(item.intake_details_json) }} · {{ intakeRequestedAmount(item.intake_details_json) }}</p>
              </div>
              <span class="client-badge client-badge--blue">{{ item.workflow_stage }}</span>
            </div>
            <div class="client-row-between">
              <span class="client-meta">{{ item.submitted_at ? new Date(item.submitted_at).toLocaleDateString() : '—' }}</span>
              <RouterLink :to="requestActionRoute(item)" class="client-btn-secondary">{{ requestActionLabel(item) }}</RouterLink>
            </div>
          </article>
        </div>
        <div v-else class="client-empty-state client-empty-state--inner">
          <h3>No recent requests yet</h3>
        </div>
      </article>
    </section>
  </div>
</template>
