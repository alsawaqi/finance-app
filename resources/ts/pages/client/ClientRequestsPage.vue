<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { listClientRequests } from '@/services/clientPortal'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeRequestedAmount } from '@/utils/requestIntake'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<any[]>([])

const stats = computed(() => ({
  total: requests.value.length,
  active: requests.value.filter((item) => item.status === 'active').length,
  needsAction: requests.value.filter((item) => item.current_contract?.status === 'admin_signed' || ['document_collection', 'awaiting_additional_documents'].includes(item.workflow_stage)).length,
}))

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await listClientRequests()
    requests.value = data.requests ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load your requests.'
  } finally {
    loading.value = false
  }
}

function contractActionLabel(item: any) {
  if (item.current_contract?.status === 'admin_signed') return 'Review contract'
  if (item.current_contract?.status === 'fully_signed') return 'View signed request'
  if (['document_collection', 'awaiting_additional_documents'].includes(item.workflow_stage)) return 'Open documents'
  return 'View details'
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
        <p class="eyebrow">Client portal</p>
        <h1>My Requests</h1>
        <p>Track request status, open contract actions, and move directly to the next client-side step when required.</p>
      </div>
      <RouterLink to="/dashboard" class="ghost-btn">Back to dashboard</RouterLink>
    </div>

    <div class="client-status-chip-grid client-status-chip-grid--summary">
      <div class="client-status-chip-card">
        <strong>{{ loading ? '…' : stats.total }}</strong>
        <span>Total requests</span>
      </div>
      <div class="client-status-chip-card">
        <strong>{{ loading ? '…' : stats.active }}</strong>
        <span>Active</span>
      </div>
      <div class="client-status-chip-card">
        <strong>{{ loading ? '…' : stats.needsAction }}</strong>
        <span>Need action</span>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-head">
        <h2>Request list</h2>
        <button class="ghost-btn" type="button" @click="load">Refresh</button>
      </div>
      <p v-if="loading" class="empty-state">Loading your requests…</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!requests.length" class="empty-state">You have not submitted any requests yet.</p>
      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Country</th>
              <th>Requested Amount</th>
              <th>Stage</th>
              <th>Submitted</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests" :key="item.id">
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || 'Awaiting approval' }}</div>
              </td>
              <td>{{ countryNameFromCode(intakeCountryCode(item.intake_details_json)) }}</td>
              <td>{{ intakeRequestedAmount(item.intake_details_json) }}</td>
              <td>{{ item.workflow_stage }}</td>
              <td>{{ item.submitted_at ? new Date(item.submitted_at).toLocaleString() : '—' }}</td>
              <td>
                <RouterLink :to="contractActionRoute(item)" class="primary-btn small-btn">{{ contractActionLabel(item) }}</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
