<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { adminContractDownloadUrl, approveAdminRequest, getAdminRequestDetails, type FinanceRequestDetail } from '@/services/adminRequests'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeNotes, intakeRequestedAmount } from '@/utils/requestIntake'

const route = useRoute()
const router = useRouter()
const requestItem = ref<FinanceRequestDetail | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const approving = ref(false)
const approvalNotes = ref('')

const requestId = computed(() => route.params.id as string)

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
    const data = await getAdminRequestDetails(requestId.value)
    requestItem.value = data.request ?? null
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load request details.'
  } finally {
    loading.value = false
  }
}

async function approveRequest() {
  if (!requestItem.value) return
  approving.value = true
  try {
    await approveAdminRequest(requestItem.value.id, { approval_notes: approvalNotes.value })
    await router.push({ name: 'admin-request-contract', params: { id: requestItem.value.id } })
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to approve the request.'
  } finally {
    approving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">Admin review</p>
        <h1>Request Details</h1>
        <p class="subtext">Review the request answers and intake details before moving into contract drafting.</p>
      </div>
      <div class="actions-row">
        <RouterLink :to="{ name: 'admin-new-requests' }" class="ghost-btn">Back to queue</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">Download contract PDF</a>
        <RouterLink v-if="requestItem?.approval_reference_number || requestItem?.current_contract" :to="{ name: 'admin-request-contract', params: { id: requestId } }" class="primary-btn">Go to contract</RouterLink>
        <RouterLink
          v-if="requestItem?.current_contract?.client_signed_at"
          :to="{ name: 'admin-assignments', query: { requestId } }"
          class="ghost-btn"
        >Assign staff</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading request…</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <div v-else-if="requestItem" class="details-grid">
      <article class="panel-card info-card">
        <div class="panel-head"><h2>Request summary</h2></div>
        <div class="summary-grid">
          <div><span>Request Reference</span><strong>{{ requestItem.reference_number }}</strong></div>
          <div><span>Approval Reference</span><strong>{{ requestItem.approval_reference_number || 'Pending approval' }}</strong></div>
          <div><span>Status</span><strong>{{ requestItem.status }}</strong></div>
          <div><span>Workflow Stage</span><strong>{{ requestItem.workflow_stage }}</strong></div>
          <div><span>Submitted</span><strong>{{ requestItem.submitted_at ? new Date(requestItem.submitted_at).toLocaleString() : '—' }}</strong></div>
          <div><span>Client</span><strong>{{ requestItem.client?.name || 'Client' }}</strong></div>
        </div>
      </article>

      <article class="panel-card info-card">
        <div class="panel-head"><h2>Intake details</h2></div>
        <div class="summary-grid">
          <div><span>Full name</span><strong>{{ intakeFullName(requestItem.intake_details_json, requestItem.client?.name || '—') }}</strong></div>
          <div><span>Country</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json)) }}</strong></div>
          <div><span>Requested amount</span><strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong></div>
          <div><span>Finance type</span><strong>{{ intakeFinanceType(requestItem.intake_details_json) }}</strong></div>
        </div>
        <div class="notes-box">
          <span>Supporting notes</span>
          <p>{{ intakeNotes(requestItem.intake_details_json) }}</p>
        </div>
      </article>

      <article v-if="requestItem.current_contract" class="panel-card info-card">
        <div class="panel-head"><h2>Contract status</h2></div>
        <div class="summary-grid">
          <div><span>Version</span><strong>{{ requestItem.current_contract.version_no }}</strong></div>
          <div><span>Status</span><strong>{{ requestItem.current_contract.status }}</strong></div>
          <div><span>Admin signed</span><strong>{{ requestItem.current_contract.admin_signed_at ? new Date(requestItem.current_contract.admin_signed_at).toLocaleString() : 'Pending' }}</strong></div>
          <div><span>Client signed</span><strong>{{ requestItem.current_contract.client_signed_at ? new Date(requestItem.current_contract.client_signed_at).toLocaleString() : 'Pending' }}</strong></div>
        </div>
      </article>

      <article class="panel-card info-card">
        <div class="panel-head"><h2>Assigned staff</h2></div>
        <div v-if="requestItem.assignments?.length" class="timeline-list">
          <div v-for="assignment in requestItem.assignments" :key="assignment.id" class="timeline-item">
            <strong>{{ assignment.staff?.name || 'Staff member' }}</strong>
            <p>{{ assignment.notes || 'No assignment note added.' }}</p>
            <span>
              {{ assignment.is_primary ? 'Lead owner' : assignment.assignment_role || 'Support' }}
              <template v-if="assignment.assigned_at"> · {{ new Date(assignment.assigned_at).toLocaleString() }}</template>
            </span>
          </div>
        </div>
        <p v-else class="empty-state">No staff members assigned yet.</p>
      </article>

      <article class="panel-card wide-card">
        <div class="panel-head"><h2>Questionnaire answers</h2></div>
        <div v-if="requestItem.answers?.length" class="qa-list">
          <div v-for="answer in requestItem.answers" :key="answer.id" class="qa-item">
            <h3>{{ answer.question?.question_text || 'Question' }}</h3>
            <p>{{ answerText(answer) }}</p>
          </div>
        </div>
        <p v-else class="empty-state">No answers recorded for this request.</p>
      </article>

      <article class="panel-card">
        <div class="panel-head"><h2>Initial attachments</h2></div>
        <div v-if="requestItem.attachments?.length" class="file-list">
          <div v-for="file in requestItem.attachments" :key="file.id" class="file-item">
            <strong>{{ file.file_name }}</strong>
            <span>{{ file.category }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No initial attachments uploaded.</p>
      </article>

      <article class="panel-card">
        <div class="panel-head"><h2>Internal comments</h2></div>
        <div v-if="requestItem.comments?.length" class="timeline-list">
          <div v-for="comment in requestItem.comments" :key="comment.id" class="timeline-item">
            <strong>{{ comment.user?.name || 'System' }}</strong>
            <p>{{ comment.comment_text }}</p>
            <span>{{ new Date(comment.created_at).toLocaleString() }} · {{ comment.visibility }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No comments have been recorded yet.</p>
      </article>

      <article class="panel-card">
        <div class="panel-head"><h2>Timeline</h2></div>
        <div v-if="requestItem.timeline?.length" class="timeline-list">
          <div v-for="entry in requestItem.timeline" :key="entry.id" class="timeline-item">
            <strong>{{ entry.event_title || entry.event_type }}</strong>
            <p>{{ entry.event_description || '—' }}</p>
            <span>{{ new Date(entry.created_at).toLocaleString() }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No timeline events yet.</p>
      </article>

      <article v-if="!requestItem.approval_reference_number" class="panel-card wide-card action-card">
        <div class="panel-head"><h2>Approve request</h2></div>
        <p class="subtext">Approving this request will generate the second approval reference number and unlock contract drafting.</p>
        <textarea v-model="approvalNotes" rows="4" class="admin-textarea" placeholder="Add a short review note for the approval timeline (optional)"></textarea>
        <div class="approve-actions">
          <button class="primary-btn" type="button" :disabled="approving" @click="approveRequest">
            {{ approving ? 'Approving…' : 'Approve and continue to contract' }}
          </button>
        </div>
      </article>
    </div>
  </section>
</template>
