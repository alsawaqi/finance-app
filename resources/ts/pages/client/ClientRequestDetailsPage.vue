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
  <section class="client-shell client-request-detail-shell">
    <div class="hero-card">
      <div>
        <p class="eyebrow">Client portal</p>
        <h1>Request Details</h1>
        <p>Only the essential client-facing parts stay visible here: your submitted details, contract state, and upload actions.</p>
      </div>
      <div class="action-stack">
        <RouterLink :to="{ name: 'client-requests' }" class="ghost-btn">Back to requests</RouterLink>
        <RouterLink v-if="requestItem?.can_upload_documents" :to="{ name: 'client-request-documents', params: { id: requestId } }" class="ghost-btn">Open documents</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="clientContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">Download contract PDF</a>
        <RouterLink v-if="requestItem?.can_sign" :to="{ name: 'client-request-sign', params: { id: requestId } }" class="primary-btn">Review and sign contract</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading request details…</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <template v-else-if="requestItem">
      <div class="client-status-chip-grid client-status-chip-grid--summary">
        <div class="client-status-chip-card">
          <strong>{{ requestItem.reference_number }}</strong>
          <span>Request reference</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ requestItem.status }}</strong>
          <span>Status</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ requestItem.workflow_stage }}</strong>
          <span>Workflow stage</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ requestItem.current_contract?.status || 'No contract yet' }}</strong>
          <span>Contract state</span>
        </div>
      </div>

      <div class="client-accordion-stack">
        <details class="client-accordion-card" open>
          <summary>
            <div>
              <h2>Submission summary</h2>
              <p>Your original request details and submitted files.</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div class="summary-grid">
              <div><span>Approval reference</span><strong>{{ requestItem.approval_reference_number || 'Pending approval' }}</strong></div>
              <div><span>Applicant type</span><strong>{{ requestItem.applicant_type || 'individual' }}</strong></div>
              <div><span>Company name</span><strong>{{ requestItem.company_name || '—' }}</strong></div>
              <div><span>Country</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json)) }}</strong></div>
              <div><span>Requested amount</span><strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong></div>
              <div><span>Submitted</span><strong>{{ requestItem.submitted_at ? new Date(requestItem.submitted_at).toLocaleString() : '—' }}</strong></div>
            </div>

            <div class="client-two-col-grid">
              <article class="panel-card slim-card">
                <div class="panel-head"><h3>Your answers</h3></div>
                <div class="qa-list compact-list" v-if="requestItem.answers?.length">
                  <div class="qa-item" v-for="answer in requestItem.answers" :key="answer.id">
                    <strong>{{ answer.question_text || answer.question?.question_text || 'Question' }}</strong>
                    <p>{{ answerText(answer) }}</p>
                  </div>
                </div>
                <p v-else class="empty-state">No questionnaire answers found.</p>
              </article>

              <article class="panel-card slim-card">
                <div class="panel-head"><h3>Submitted files</h3></div>
                <div v-if="requestItem.attachments?.length" class="file-list compact-list">
                  <div v-for="file in requestItem.attachments" :key="file.id" class="file-item">
                    <strong>{{ file.file_name }}</strong>
                    <span>{{ file.category }}</span>
                  </div>
                </div>
                <p v-else class="empty-state">No uploaded request files found.</p>
              </article>
            </div>

            <article v-if="requestItem.shareholders?.length" class="panel-card slim-card">
              <div class="panel-head"><h3>Shareholders</h3></div>
              <div class="qa-list compact-list">
                <div class="qa-item" v-for="shareholder in requestItem.shareholders" :key="shareholder.id">
                  <strong>{{ shareholder.shareholder_name }}</strong>
                  <p>{{ shareholder.id_file_name }}</p>
                </div>
              </div>
            </article>
          </div>
        </details>

        <details class="client-accordion-card" open>
          <summary>
            <div>
              <h2>Contract & upload status</h2>
              <p>Keep only the current contract and document progress visible.</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div class="summary-grid">
              <div><span>Contract version</span><strong>{{ requestItem.current_contract?.version_no || '—' }}</strong></div>
              <div><span>Contract status</span><strong>{{ requestItem.current_contract?.status || '—' }}</strong></div>
              <div><span>Admin signed</span><strong>{{ requestItem.current_contract?.admin_signed_at ? new Date(requestItem.current_contract.admin_signed_at).toLocaleString() : 'Pending' }}</strong></div>
              <div><span>Client signed</span><strong>{{ requestItem.current_contract?.client_signed_at ? new Date(requestItem.current_contract.client_signed_at).toLocaleString() : 'Pending' }}</strong></div>
            </div>

            <div class="client-two-col-grid">
              <article class="panel-card slim-card">
                <div class="panel-head"><h3>Required documents</h3></div>
                <div v-if="requestItem.required_documents?.length" class="qa-list compact-list">
                  <div v-for="item in requestItem.required_documents" :key="item.document_upload_step_id" class="qa-item">
                    <strong>{{ item.name }}</strong>
                    <p>{{ item.is_uploaded ? 'Uploaded' : 'Pending' }}</p>
                  </div>
                </div>
                <p v-else class="empty-state">No required documents configured yet.</p>
              </article>

              <article class="panel-card slim-card">
                <div class="panel-head"><h3>Additional requested documents</h3></div>
                <div v-if="requestItem.additional_document_requests?.length" class="qa-list compact-list">
                  <div v-for="item in requestItem.additional_document_requests" :key="item.id" class="qa-item">
                    <strong>{{ item.title }}</strong>
                    <p>{{ item.reason || 'No reason added.' }}</p>
                    <span>{{ item.status }}</span>
                  </div>
                </div>
                <p v-else class="empty-state">No additional requested documents right now.</p>
              </article>
            </div>
          </div>
        </details>
      </div>
    </template>
  </section>
</template>
