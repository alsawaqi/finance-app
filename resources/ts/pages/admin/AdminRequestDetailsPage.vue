<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { adminContractDownloadUrl, approveAdminRequest, getAdminRequestDetails } from '@/services/adminRequests'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeNotes, intakeRequestedAmount } from '@/utils/requestIntake'

const route = useRoute()
const router = useRouter()
const requestItem = ref<any | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const approving = ref(false)
const approvalNotes = ref('')

const requestId = computed(() => route.params.id as string)
const activityCounts = computed(() => ({
  comments: requestItem.value?.comments?.length ?? 0,
  timeline: requestItem.value?.timeline?.length ?? 0,
  emails: requestItem.value?.emails?.length ?? 0,
  assignments: requestItem.value?.assignments?.length ?? 0,
}))

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
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">Admin review</p>
        <h1>Request Details</h1>
        <p class="subtext">A cleaner review page focused on the decision, the contract state, and expandable record history.</p>
      </div>
      <div class="actions-row">
        <RouterLink :to="{ name: 'admin-new-requests' }" class="ghost-btn">Back to queue</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">Download contract PDF</a>
        <RouterLink v-if="requestItem?.approval_reference_number || requestItem?.current_contract" :to="{ name: 'admin-request-contract', params: { id: requestId } }" class="primary-btn">Go to contract</RouterLink>
        <RouterLink v-if="requestItem?.current_contract?.client_signed_at" :to="{ name: 'admin-assignments', query: { requestId } }" class="ghost-btn">Assign staff</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading request…</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <template v-else-if="requestItem">
      <div class="admin-workspace-summary-grid">
        <article class="admin-workspace-stat">
          <span>Status</span>
          <strong>{{ requestItem.status }}</strong>
          <small>Current business state</small>
        </article>
        <article class="admin-workspace-stat">
          <span>Stage</span>
          <strong>{{ requestItem.workflow_stage }}</strong>
          <small>Operational stage</small>
        </article>
        <article class="admin-workspace-stat">
          <span>Client</span>
          <strong>{{ intakeFullName(requestItem.intake_details_json, requestItem.client?.name || 'Client') }}</strong>
          <small>{{ requestItem.client?.email || 'No email saved' }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>Requested amount</span>
          <strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong>
          <small>{{ intakeFinanceType(requestItem.intake_details_json) }}</small>
        </article>
      </div>

      <div class="admin-workspace-layout">
        <div class="admin-workspace-main">
          <details class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>Client submission</h2>
                <p>Submission details, questionnaire answers, and uploaded files.</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="summary-grid">
                <div><span>Request reference</span><strong>{{ requestItem.reference_number }}</strong></div>
                <div><span>Approval reference</span><strong>{{ requestItem.approval_reference_number || 'Pending approval' }}</strong></div>
                <div><span>Country</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json)) }}</strong></div>
                <div><span>Submitted</span><strong>{{ requestItem.submitted_at ? new Date(requestItem.submitted_at).toLocaleString() : '—' }}</strong></div>
                <div><span>Applicant type</span><strong>{{ requestItem.applicant_type || 'individual' }}</strong></div>
                <div><span>Company name</span><strong>{{ requestItem.company_name || '—' }}</strong></div>
              </div>

              <div class="notes-box">
                <span>Supporting notes</span>
                <p>{{ intakeNotes(requestItem.intake_details_json) }}</p>
              </div>

              <div class="admin-inline-block-grid">
                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>Questionnaire answers</h3></div>
                  <div v-if="requestItem.answers?.length" class="qa-list compact-list">
                    <div v-for="answer in requestItem.answers" :key="answer.id" class="qa-item">
                      <h3>{{ answer.question?.question_text || 'Question' }}</h3>
                      <p>{{ answerText(answer) }}</p>
                    </div>
                  </div>
                  <p v-else class="empty-state">No answers recorded.</p>
                </article>

                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>Uploaded files</h3></div>
                  <div v-if="requestItem.attachments?.length" class="file-list compact-list">
                    <div v-for="file in requestItem.attachments" :key="file.id" class="file-item">
                      <strong>{{ file.file_name }}</strong>
                      <span>{{ file.category }}</span>
                    </div>
                  </div>
                  <p v-else class="empty-state">No initial attachments uploaded.</p>
                </article>
              </div>

              <article v-if="requestItem.shareholders?.length" class="panel-card slim-card">
                <div class="panel-head"><h3>Shareholders</h3></div>
                <div class="qa-list compact-list">
                  <div v-for="shareholder in requestItem.shareholders" :key="shareholder.id" class="qa-item">
                    <strong>{{ shareholder.shareholder_name }}</strong>
                    <p>{{ shareholder.id_file_name }}</p>
                  </div>
                </div>
              </article>
            </div>
          </details>

          <details class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>Contract & assignment state</h2>
                <p>Keep contract progress and ownership visible without mixing it with long history.</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="summary-grid">
                <div><span>Contract version</span><strong>{{ requestItem.current_contract?.version_no || '—' }}</strong></div>
                <div><span>Contract status</span><strong>{{ requestItem.current_contract?.status || '—' }}</strong></div>
                <div><span>Admin signed</span><strong>{{ requestItem.current_contract?.admin_signed_at ? new Date(requestItem.current_contract.admin_signed_at).toLocaleString() : 'Pending' }}</strong></div>
                <div><span>Client signed</span><strong>{{ requestItem.current_contract?.client_signed_at ? new Date(requestItem.current_contract.client_signed_at).toLocaleString() : 'Pending' }}</strong></div>
              </div>

              <div v-if="requestItem.assignments?.length" class="assignment-chip-list">
                <div v-for="assignment in requestItem.assignments" :key="assignment.id" class="assignment-chip">
                  <strong>{{ assignment.staff?.name || 'Staff member' }}</strong>
                  <span>{{ assignment.is_primary ? 'Lead owner' : assignment.assignment_role || 'Support' }}</span>
                </div>
              </div>
              <p v-else class="empty-state">No staff members assigned yet.</p>
            </div>
          </details>

          <details class="admin-accordion-card">
            <summary>
              <div>
                <h2>Internal activity</h2>
                <p>Comments, timeline, and communication records in one expandable section.</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="admin-inline-block-grid">
                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>Internal comments</h3></div>
                  <div v-if="requestItem.comments?.length" class="timeline-list compact-list">
                    <div v-for="comment in requestItem.comments" :key="comment.id" class="timeline-item">
                      <strong>{{ comment.user?.name || 'System' }}</strong>
                      <p>{{ comment.comment_text }}</p>
                      <span>{{ new Date(comment.created_at).toLocaleString() }} · {{ comment.visibility }}</span>
                    </div>
                  </div>
                  <p v-else class="empty-state">No comments recorded yet.</p>
                </article>

                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>Timeline</h3></div>
                  <div v-if="requestItem.timeline?.length" class="timeline-list compact-list">
                    <div v-for="entry in requestItem.timeline" :key="entry.id" class="timeline-item">
                      <strong>{{ entry.event_title || entry.event_type }}</strong>
                      <p>{{ entry.event_description || '—' }}</p>
                      <span>{{ new Date(entry.created_at).toLocaleString() }}</span>
                    </div>
                  </div>
                  <p v-else class="empty-state">No timeline events yet.</p>
                </article>
              </div>
            </div>
          </details>
        </div>

        <aside class="admin-workspace-side">
          <article class="panel-card slim-card">
            <div class="panel-head"><h2>Quick counts</h2></div>
            <div class="catalog-mini-stats">
              <div><span>Assignments</span><strong>{{ activityCounts.assignments }}</strong></div>
              <div><span>Comments</span><strong>{{ activityCounts.comments }}</strong></div>
              <div><span>Timeline events</span><strong>{{ activityCounts.timeline }}</strong></div>
              <div><span>Email logs</span><strong>{{ activityCounts.emails }}</strong></div>
            </div>
          </article>

          <article v-if="!requestItem.approval_reference_number" class="panel-card slim-card action-card">
            <div class="panel-head"><h2>Approve request</h2></div>
            <p class="subtext">Approving creates the approval reference and unlocks contract drafting.</p>
            <textarea v-model="approvalNotes" rows="5" class="admin-textarea" placeholder="Add a short review note for the approval timeline (optional)"></textarea>
            <div class="approve-actions">
              <button class="primary-btn" type="button" :disabled="approving" @click="approveRequest">
                {{ approving ? 'Approving…' : 'Approve and continue' }}
              </button>
            </div>
          </article>
        </aside>
      </div>
    </template>
  </section>
</template>
