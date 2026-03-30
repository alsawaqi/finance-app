<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { adminContractDownloadUrl } from '@/services/adminRequests'
import {
  addStaffComment,
  getStaffAgents,
  getStaffRequest,
  requestAdditionalDocument,
  type AgentOption,
  type BankOption,
  type RequiredDocumentChecklistItem,
} from '@/services/staffWorkspace'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeNotes, intakeRequestedAmount } from '@/utils/requestIntake'

const route = useRoute()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const savingComment = ref(false)
const savingAdditionalDocument = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const requestItem = ref<any | null>(null)
const requiredDocuments = ref<RequiredDocumentChecklistItem[]>([])
const agents = ref<AgentOption[]>([])
const banks = ref<BankOption[]>([])

const commentText = ref('')
const commentVisibility = ref<'internal' | 'admin_only'>('internal')
const additionalDocumentTitle = ref('')
const additionalDocumentReason = ref('')

const selectedBankId = ref<number | null>(null)
const selectedAgentIds = ref<number[]>([])
const emailSubject = ref('')
const emailBody = ref('')
const mockFiles = ref<string[]>([])

const uploadedRequiredCount = computed(() => requiredDocuments.value.filter((item) => item.is_uploaded).length)
const pendingRequiredCount = computed(() => requiredDocuments.value.filter((item) => !item.is_uploaded).length)

function answerText(answer: any) {
  if (!answer) return '—'
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

async function loadAgents() {
  const response = await getStaffAgents({ bank_id: selectedBankId.value })
  banks.value = response.banks ?? []
  agents.value = response.agents ?? []
}

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [requestResponse] = await Promise.all([getStaffRequest(requestId.value), loadAgents()])
    requestItem.value = requestResponse.request ?? null
    requiredDocuments.value = requestResponse.required_documents ?? []
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
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    commentText.value = ''
    successMessage.value = data.message || 'Comment added successfully.'
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to add the comment.'
  } finally {
    savingComment.value = false
  }
}

async function submitAdditionalDocumentRequest() {
  if (!requestItem.value || !additionalDocumentTitle.value.trim()) return

  savingAdditionalDocument.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await requestAdditionalDocument(requestItem.value.id, {
      title: additionalDocumentTitle.value,
      reason: additionalDocumentReason.value,
    })
    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    additionalDocumentTitle.value = ''
    additionalDocumentReason.value = ''
    successMessage.value = data.message || 'Additional document request created successfully.'
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to request the additional document.'
  } finally {
    savingAdditionalDocument.value = false
  }
}

function handleMockAttachments(event: Event) {
  const input = event.target as HTMLInputElement
  mockFiles.value = Array.from(input.files || []).map((file) => file.name)
}

watch(selectedBankId, async () => {
  await loadAgents()
  selectedAgentIds.value = selectedAgentIds.value.filter((id) => agents.value.some((agent) => agent.id === id))
})

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">Staff request view</p>
        <h1>Request Workspace</h1>
        <p class="subtext">Use compact sections to follow up, request new documents, and keep internal collaboration tidy.</p>
      </div>
      <div class="actions-row">
        <RouterLink :to="{ name: 'staff-requests' }" class="ghost-btn">Back to assigned requests</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="primary-btn">Download contract PDF</a>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading request workspace…</p>
    <p v-else-if="errorMessage && !requestItem" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>

    <template v-else-if="requestItem">
      <div class="admin-workspace-summary-grid">
        <article class="admin-workspace-stat">
          <span>Client</span>
          <strong>{{ intakeFullName(requestItem.intake_details_json, requestItem.client?.name || 'Client') }}</strong>
          <small>{{ requestItem.reference_number }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>Workflow</span>
          <strong>{{ requestItem.workflow_stage }}</strong>
          <small>{{ intakeFinanceType(requestItem.intake_details_json) }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>Required docs uploaded</span>
          <strong>{{ uploadedRequiredCount }}/{{ requiredDocuments.length }}</strong>
          <small>{{ pendingRequiredCount }} pending</small>
        </article>
        <article class="admin-workspace-stat">
          <span>Additional requests</span>
          <strong>{{ requestItem.additional_documents?.length || 0 }}</strong>
          <small>Custom follow-up items</small>
        </article>
      </div>

      <div class="admin-workspace-layout">
        <div class="admin-workspace-main">
          <details class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>Required document checklist</h2>
                <p>Keep the main checklist visible without mixing it with comments and email drafts.</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div v-if="requiredDocuments.length" class="checklist-grid">
                <article v-for="item in requiredDocuments" :key="item.document_upload_step_id" class="checklist-card" :class="{ 'is-complete': item.is_uploaded }">
                  <div class="checklist-card__head">
                    <strong>{{ item.name }}</strong>
                    <span class="status-badge">{{ item.is_uploaded ? 'Uploaded' : 'Pending' }}</span>
                  </div>
                  <p>{{ item.is_uploaded ? `Latest file: ${item.upload?.file_name || 'uploaded file'}` : 'Waiting for the client upload.' }}</p>
                </article>
              </div>
              <p v-else class="empty-state">No required documents are configured yet.</p>
            </div>
          </details>

          <details class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>Follow-up workspace</h2>
                <p>Comments and extra document requests are separated into tidy sub-sections.</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="admin-inline-block-grid">
                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>Add internal comment</h3></div>
                  <div class="field-block field-block--grow">
                    <span>Visibility</span>
                    <select v-model="commentVisibility" class="admin-select">
                      <option value="internal">Internal</option>
                      <option value="admin_only">Admin only</option>
                    </select>
                  </div>
                  <textarea v-model="commentText" rows="5" class="admin-textarea" placeholder="Add a progress note, instruction, or internal follow-up update."></textarea>
                  <div class="approve-actions">
                    <button class="primary-btn" type="button" :disabled="savingComment || !commentText.trim()" @click="submitComment">
                      {{ savingComment ? 'Saving…' : 'Save comment' }}
                    </button>
                  </div>
                </article>

                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>Request additional document</h3></div>
                  <input v-model="additionalDocumentTitle" type="text" class="admin-input" placeholder="Document title" />
                  <textarea v-model="additionalDocumentReason" rows="5" class="admin-textarea" placeholder="Explain what the client should upload and why it is needed."></textarea>
                  <div class="approve-actions">
                    <button class="primary-btn" type="button" :disabled="savingAdditionalDocument || !additionalDocumentTitle.trim()" @click="submitAdditionalDocumentRequest">
                      {{ savingAdditionalDocument ? 'Saving…' : 'Create request' }}
                    </button>
                  </div>
                </article>
              </div>

              <article class="panel-card slim-card">
                <div class="panel-head"><h3>Requested additional documents</h3></div>
                <div v-if="requestItem.additional_documents?.length" class="timeline-list compact-list">
                  <div v-for="item in requestItem.additional_documents" :key="item.id" class="timeline-item">
                    <strong>{{ item.title }}</strong>
                    <p>{{ item.reason || 'No reason added.' }}</p>
                    <span>{{ item.status }}<template v-if="item.file_name"> · {{ item.file_name }}</template></span>
                  </div>
                </div>
                <p v-else class="empty-state">No additional documents requested yet.</p>
              </article>
            </div>
          </details>

          <details class="admin-accordion-card">
            <summary>
              <div>
                <h2>Email composer preview</h2>
                <p>Design only for now, but already organized by bank first and then agents.</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="admin-inline-block-grid">
                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>Recipients</h3></div>
                  <div class="field-block field-block--grow">
                    <span>Bank</span>
                    <select v-model="selectedBankId" class="admin-select">
                      <option :value="null">All banks</option>
                      <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                    </select>
                  </div>
                  <div class="field-block field-block--grow">
                    <span>Agents</span>
                    <select v-model="selectedAgentIds" class="admin-select" multiple size="6">
                      <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                        {{ agent.name }}<template v-if="agent.bank_name"> · {{ agent.bank_name }}</template>
                      </option>
                    </select>
                  </div>
                </article>

                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>Email body</h3></div>
                  <input v-model="emailSubject" type="text" class="admin-input" placeholder="Email subject" />
                  <textarea v-model="emailBody" rows="7" class="admin-textarea" placeholder="Compose the future email template here."></textarea>
                  <input type="file" class="admin-input" multiple @change="handleMockAttachments" />
                  <div class="tag-row">
                    <span v-for="fileName in mockFiles" :key="fileName" class="soft-tag">{{ fileName }}</span>
                  </div>
                </article>
              </div>
            </div>
          </details>
        </div>

        <aside class="admin-workspace-side">
          <article class="panel-card slim-card">
            <div class="panel-head"><h2>Request summary</h2></div>
            <div class="summary-grid">
              <div><span>Country</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json)) }}</strong></div>
              <div><span>Requested amount</span><strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong></div>
              <div><span>Current contract</span><strong>{{ requestItem.current_contract?.status || '—' }}</strong></div>
              <div><span>Assignments</span><strong>{{ requestItem.assignments?.length || 0 }}</strong></div>
            </div>
            <div class="notes-box">
              <span>Request notes</span>
              <p>{{ intakeNotes(requestItem.intake_details_json) }}</p>
            </div>
          </article>

          <article class="panel-card slim-card">
            <div class="panel-head"><h2>Recent internal history</h2></div>
            <div v-if="requestItem.comments?.length" class="timeline-list compact-list">
              <div v-for="comment in requestItem.comments.slice(0, 4)" :key="comment.id" class="timeline-item">
                <strong>{{ comment.user?.name || 'System' }}</strong>
                <p>{{ comment.comment_text }}</p>
                <span>{{ new Date(comment.created_at).toLocaleString() }}</span>
              </div>
            </div>
            <p v-else class="empty-state">No internal comments yet.</p>
          </article>

          <details class="admin-accordion-card slim-accordion">
            <summary>
              <div>
                <h2>Questionnaire answers</h2>
                <p>Expand only when needed.</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div v-if="requestItem.answers?.length" class="qa-list compact-list">
                <div v-for="answer in requestItem.answers" :key="answer.id" class="qa-item">
                  <h3>{{ answer.question?.question_text || 'Question' }}</h3>
                  <p>{{ answerText(answer) }}</p>
                </div>
              </div>
              <p v-else class="empty-state">No answers recorded.</p>
            </div>
          </details>
        </aside>
      </div>
    </template>
  </section>
</template>
