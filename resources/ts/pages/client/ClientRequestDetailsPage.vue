<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { clientContractDownloadUrl, getClientRequest } from '@/services/clientPortal'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeRequestedAmount } from '@/utils/requestIntake'

const route = useRoute()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const errorMessage = ref('')
const requestItem = ref<any | null>(null)

function answerText(answer: any) {
  if (!answer) return '—'
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await getClientRequest(requestId.value)
    requestItem.value = data.request ?? null
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load the request.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="client-shell">
    <div class="hero-card">
      <div>
        <p class="eyebrow">Client portal</p>
        <h1>Request Details</h1>
        <p>Review your submitted information and continue to the contract step when the request is approved.</p>
      </div>
      <div class="action-stack">
        <RouterLink :to="{ name: 'client-requests' }" class="ghost-btn">Back to requests</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="clientContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">Download contract PDF</a>
        <RouterLink v-if="requestItem?.current_contract?.status === 'admin_signed'" :to="{ name: 'client-request-sign', params: { id: requestId } }" class="primary-btn">Review and sign contract</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading request details…</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <div v-else-if="requestItem" class="detail-grid">
      <article class="panel-card">
        <h2>Request summary</h2>
        <div class="summary-grid">
          <div><span>Request reference</span><strong>{{ requestItem.reference_number }}</strong></div>
          <div><span>Approval reference</span><strong>{{ requestItem.approval_reference_number || 'Pending approval' }}</strong></div>
          <div><span>Status</span><strong>{{ requestItem.status }}</strong></div>
          <div><span>Workflow stage</span><strong>{{ requestItem.workflow_stage }}</strong></div>
          <div><span>Country</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json)) }}</strong></div>
          <div><span>Requested amount</span><strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong></div>
        </div>
      </article>

      <article v-if="requestItem.current_contract" class="panel-card">
        <h2>Contract status</h2>
        <div class="summary-grid">
          <div><span>Contract version</span><strong>{{ requestItem.current_contract.version_no || '—' }}</strong></div>
          <div><span>Contract status</span><strong>{{ requestItem.current_contract.status || '—' }}</strong></div>
          <div><span>Admin signed</span><strong>{{ requestItem.current_contract.admin_signed_at ? new Date(requestItem.current_contract.admin_signed_at).toLocaleString() : 'Pending' }}</strong></div>
          <div><span>Client signed</span><strong>{{ requestItem.current_contract.client_signed_at ? new Date(requestItem.current_contract.client_signed_at).toLocaleString() : 'Pending' }}</strong></div>
        </div>
      </article>

      <article class="panel-card wide-card">
        <h2>Your answers</h2>
        <div class="qa-list" v-if="requestItem.answers?.length">
          <div class="qa-item" v-for="answer in requestItem.answers" :key="answer.id">
            <strong>{{ answer.question?.question_text || 'Question' }}</strong>
            <p>{{ answerText(answer) }}</p>
          </div>
        </div>
        <p v-else class="empty-state">No questionnaire answers found.</p>
      </article>

      <article class="panel-card wide-card">
        <h2>Request progress</h2>
        <div class="timeline-list" v-if="requestItem.timeline?.length">
          <div v-for="entry in requestItem.timeline" :key="entry.id" class="timeline-item">
            <strong>{{ entry.event_title || entry.event_type }}</strong>
            <p>{{ entry.event_description || '—' }}</p>
            <span>{{ new Date(entry.created_at).toLocaleString() }}</span>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
