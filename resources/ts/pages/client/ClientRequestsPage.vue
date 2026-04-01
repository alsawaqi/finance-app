<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { listClientRequests } from '@/services/clientPortal'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeRequestedAmount } from '@/utils/requestIntake'
import { getClientWorkflowStageMeta } from '@/utils/clientRequestStage'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<any[]>([])
const { t, locale } = useI18n()

const stats = computed(() => ({
  total: requests.value.length,
  active: requests.value.filter((item) => item.status === 'active').length,
  needsAction: requests.value.filter((item) => item.current_contract?.status === 'admin_signed' || ['document_collection', 'awaiting_additional_documents'].includes(item.workflow_stage)).length,
}))

function stageMeta(stage: string | null | undefined) {
  return getClientWorkflowStageMeta(stage)
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await listClientRequests()
    requests.value = data.requests ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('clientRequests.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function contractActionLabel(item: any) {
  if (item.current_contract?.status === 'admin_signed') return t('clientRequests.actions.reviewContract')
  if (item.current_contract?.status === 'fully_signed') return t('clientRequests.actions.viewSignedRequest')
  if (['document_collection', 'awaiting_additional_documents'].includes(item.workflow_stage)) return t('clientRequests.actions.openDocuments')
  return t('clientRequests.actions.viewDetails')
}

function contractActionRoute(item: any) {
  if (item.current_contract?.status === 'admin_signed') return { name: 'client-request-sign', params: { id: item.id } }
  if (['document_collection', 'awaiting_additional_documents'].includes(item.workflow_stage)) return { name: 'client-request-documents', params: { id: item.id } }
  return { name: 'client-request-details', params: { id: item.id } }
}

onMounted(load)
</script>

<template>
  <section class="client-shell client-request-detail-shell">
    <div class="hero-card">
      <div>
        <p class="eyebrow">{{ t('clientRequests.hero.eyebrow') }}</p>
        <h1>{{ t('clientRequests.hero.title') }}</h1>
        <p>{{ t('clientRequests.hero.subtitle') }}</p>
      </div>
      <RouterLink to="/dashboard" class="ghost-btn">{{ t('clientRequests.hero.backToDashboard') }}</RouterLink>
    </div>

    <div class="client-status-chip-grid client-status-chip-grid--summary">
      <div class="client-status-chip-card">
        <strong>{{ loading ? '…' : stats.total }}</strong>
        <span>{{ t('clientRequests.stats.totalRequests') }}</span>
      </div>
      <div class="client-status-chip-card">
        <strong>{{ loading ? '…' : stats.active }}</strong>
        <span>{{ t('clientRequests.stats.active') }}</span>
      </div>
      <div class="client-status-chip-card">
        <strong>{{ loading ? '…' : stats.needsAction }}</strong>
        <span>{{ t('clientRequests.stats.needAction') }}</span>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-head">
        <h2>{{ t('clientRequests.table.title') }}</h2>
        <button class="ghost-btn" type="button" @click="load">{{ t('clientRequests.table.refresh') }}</button>
      </div>
      <p v-if="loading" class="empty-state">{{ t('clientRequests.states.loading') }}</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!requests.length" class="empty-state">{{ t('clientRequests.states.empty') }}</p>
      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('clientRequests.table.reference') }}</th>
              <th>{{ t('clientRequests.table.country') }}</th>
              <th>{{ t('clientRequests.table.requestedAmount') }}</th>
              <th>{{ t('clientRequests.table.stage') }}</th>
              <th>{{ t('clientRequests.table.submitted') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests" :key="item.id">
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || t('clientRequests.states.awaitingApproval') }}</div>
              </td>
              <td>{{ countryNameFromCode(intakeCountryCode(item.intake_details_json), locale) }}</td>
              <td>{{ intakeRequestedAmount(item.intake_details_json) }}</td>
              <td>
                <span class="client-stage-badge" :class="stageMeta(item.workflow_stage).className">{{ stageMeta(item.workflow_stage).label }}</span>
              </td>
              <td>{{ item.submitted_at ? new Date(item.submitted_at).toLocaleString() : t('clientRequests.states.emptyDate') }}</td>
              <td class="request-table__action">
                <RouterLink :to="contractActionRoute(item)" class="primary-btn small-btn">{{ contractActionLabel(item) }}</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="requests.length" class="client-table-scroll-hint">{{ t('clientRequests.table.scrollHint') }}</p>
    </div>
  </section>
</template>
