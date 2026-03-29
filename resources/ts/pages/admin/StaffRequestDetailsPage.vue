<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { adminContractDownloadUrl } from '@/services/adminRequests'
import { addStaffComment, getStaffAgents, getStaffRequest, type AgentOption, type StaffWorkspaceRequestDetails } from '@/services/staffWorkspace'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeNotes, intakeRequestedAmount } from '@/utils/requestIntake'

const route = useRoute()
const requestItem = ref<StaffWorkspaceRequestDetails | null>(null)
const agents = ref<AgentOption[]>([])
const loading = ref(true)
const savingComment = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const commentText = ref('')
const commentVisibility = ref<'internal' | 'admin_only' | 'client_visible'>('internal')

const selectedAgentIds = ref<number[]>([])
const emailSubject = ref('')
const emailBody = ref('')
const mockFiles = ref<string[]>([])

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
    const [requestResponse, agentsResponse] = await Promise.all([getStaffRequest(requestId.value), getStaffAgents()])
    requestItem.value = requestResponse.request ?? null
    agents.value = agentsResponse.agents ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load the staff workspace.'
  } finally {
    loading.value = false
  }
}

async function submitComment() {
  if (!requestItem.value || !commentText.value.trim()) return

  savingComment.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await addStaffComment(requestItem.value.id, {
      comment_text: commentText.value,
      visibility: commentVisibility.value,
    })
    requestItem.value = data.request
    commentText.value = ''
    successMessage.value = 'Comment added successfully.'
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to add the comment.'
  } finally {
    savingComment.value = false
  }
}

function handleMockAttachments(event: Event) {
  const input = event.target as HTMLInputElement
  mockFiles.value = Array.from(input.files || []).map((file) => file.name)
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">Staff request view</p>
        <h1>Request Workspace</h1>
        <p class="subtext">Review the request, keep the internal follow-up notes updated, and prepare outbound email drafts to agents when you are ready.</p>
      </div>
      <div class="actions-row">
        <RouterLink :to="{ name: 'staff-requests' }" class="ghost-btn">Back to assigned requests</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="primary-btn">Download contract PDF</a>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading request workspace…</p>
    <p v-else-if="errorMessage && !requestItem" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>

    <div v-else-if="requestItem" class="details-grid">
      <article class="panel-card info-card">
        <div class="panel-head"><h2>Request summary</h2></div>
        <div class="summary-grid">
          <div><span>Request</span><strong>{{ requestItem.reference_number }}</strong></div>
          <div><span>Approval Ref</span><strong>{{ requestItem.approval_reference_number || '—' }}</strong></div>
          <div><span>Client</span><strong>{{ intakeFullName(requestItem.intake_details_json, requestItem.client?.name || 'Client') }}</strong></div>
          <div><span>Country</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json)) }}</strong></div>
          <div><span>Requested Amount</span><strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong></div>
          <div><span>Workflow Stage</span><strong>{{ requestItem.workflow_stage }}</strong></div>
        </div>
        <div class="notes-box">
          <span>Request notes</span>
          <p>{{ intakeNotes(requestItem.intake_details_json) }}</p>
        </div>
      </article>

      <article class="panel-card info-card">
        <div class="panel-head"><h2>Assignment and contract</h2></div>
        <div class="summary-grid">
          <div><span>Finance Type</span><strong>{{ intakeFinanceType(requestItem.intake_details_json) }}</strong></div>
          <div><span>Current Contract</span><strong>{{ requestItem.current_contract?.status || '—' }}</strong></div>
          <div><span>Admin Signed</span><strong>{{ requestItem.current_contract?.admin_signed_at ? new Date(requestItem.current_contract.admin_signed_at).toLocaleString() : '—' }}</strong></div>
          <div><span>Client Signed</span><strong>{{ requestItem.current_contract?.client_signed_at ? new Date(requestItem.current_contract.client_signed_at).toLocaleString() : '—' }}</strong></div>
        </div>
        <div class="assignment-chip-list">
          <div v-for="assignment in requestItem.assignments || []" :key="assignment.id" class="assignment-chip">
            <strong>{{ assignment.staff?.name || 'Staff member' }}</strong>
            <span>{{ assignment.is_primary ? 'Lead owner' : assignment.assignment_role || 'Support' }}</span>
          </div>
        </div>
      </article>

      <article class="panel-card wide-card">
        <div class="panel-head"><h2>Questionnaire answers</h2></div>
        <div v-if="requestItem.answers?.length" class="qa-list">
          <div v-for="answer in requestItem.answers" :key="answer.id" class="qa-item">
            <h3>{{ answer.question?.question_text || 'Question' }}</h3>
            <p>{{ answerText(answer) }}</p>
          </div>
        </div>
        <p v-else class="empty-state">No questionnaire answers are stored for this request.</p>
      </article>

      <article class="panel-card wide-card">
        <div class="panel-head"><h2>Internal comments</h2></div>
        <div class="comment-composer">
          <div class="field-block">
            <span>Visibility</span>
            <select v-model="commentVisibility" class="admin-select">
              <option value="internal">Internal</option>
              <option value="admin_only">Admin only</option>
              <option value="client_visible">Client visible</option>
            </select>
          </div>
          <div class="field-block field-block--grow">
            <span>New comment</span>
            <textarea v-model="commentText" rows="4" class="admin-textarea" placeholder="Add a follow-up note for the request history."></textarea>
          </div>
          <div class="approve-actions">
            <button class="primary-btn" type="button" :disabled="savingComment || !commentText.trim()" @click="submitComment">
              {{ savingComment ? 'Saving comment…' : 'Add comment' }}
            </button>
          </div>
        </div>

        <p v-if="errorMessage && requestItem" class="error-state">{{ errorMessage }}</p>

        <div v-if="requestItem.comments?.length" class="timeline-list">
          <div v-for="comment in requestItem.comments" :key="comment.id" class="timeline-item">
            <strong>{{ comment.user?.name || 'System' }}</strong>
            <p>{{ comment.comment_text }}</p>
            <span>{{ new Date(comment.created_at).toLocaleString() }} · {{ comment.visibility }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No comments have been added yet.</p>
      </article>

      <article class="panel-card wide-card">
        <div class="panel-head">
          <div>
            <h2>Email composer</h2>
            <p class="subtext">Design only for now. This prepares the staff follow-up screen without sending actual email yet.</p>
          </div>
          <span class="count-pill">Mock UI</span>
        </div>

        <div class="email-composer-grid">
          <div class="field-block field-block--grow">
            <span>To (agents)</span>
            <select v-model="selectedAgentIds" class="admin-select" multiple>
              <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                {{ agent.name }}{{ agent.email ? ` — ${agent.email}` : '' }}
              </option>
            </select>
          </div>
          <div class="field-block field-block--grow">
            <span>Subject</span>
            <input v-model="emailSubject" type="text" class="admin-input" placeholder="Request follow-up subject" />
          </div>
          <div class="field-block field-block--grow full-span">
            <span>Message</span>
            <textarea v-model="emailBody" rows="7" class="admin-textarea" placeholder="Draft the message body that will later be sent to the selected agents."></textarea>
          </div>
          <div class="field-block field-block--grow">
            <span>Attachments</span>
            <input type="file" multiple class="admin-input admin-input--file" @change="handleMockAttachments" />
            <div v-if="mockFiles.length" class="tag-row">
              <span v-for="name in mockFiles" :key="name" class="soft-tag">{{ name }}</span>
            </div>
          </div>
        </div>

        <div class="approve-actions">
          <button class="ghost-btn" type="button">Save draft later</button>
          <button class="primary-btn is-disabled" type="button" disabled>Send email (coming next)</button>
        </div>
      </article>

      <article class="panel-card">
        <div class="panel-head"><h2>Attachments</h2></div>
        <div v-if="requestItem.attachments?.length" class="file-list">
          <div v-for="file in requestItem.attachments" :key="file.id" class="file-item">
            <strong>{{ file.file_name }}</strong>
            <span>{{ file.category }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No uploaded attachments found for this request.</p>
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
    </div>
  </section>
</template>
