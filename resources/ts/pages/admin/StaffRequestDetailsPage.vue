<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  adminAdditionalDocumentDownloadUrl,
  adminContractDownloadUrl,
  adminRequiredDocumentDownloadUrl,
} from '@/services/adminRequests'
import {
  addStaffComment,
  answerStaffQuestion,
  getStaffRequest,
  getStaffRequestEmailOptions,
  requestAdditionalDocument,
  requestRequiredDocumentChange,
  saveUnderstudyDraft,
  sendStaffRequestEmail,
  staffAttachmentDownloadUrl,
  staffShareholderIdDownloadUrl,
  submitUnderstudy,
  type AgentOption,
  type AllowedEmailDocument,
  type BankOption,
  type RequiredDocumentChecklistItem,
  type StaffQuestionSummary,
  type StaffStudyQuestion,
} from '@/services/staffWorkspace'
import {
  intakeFullName,
  intakeRequestedAmount,
} from '@/utils/requestIntake'
import RequestAnswersList from './inc/RequestAnswersList.vue'
import RequestSummaryStatGrid from './inc/RequestSummaryStatGrid.vue'
import RequestWorkspaceShell from './inc/RequestWorkspaceShell.vue'
import RequestCoreDetailsCard from './inc/RequestCoreDetailsCard.vue'
import RequestRelatedCollectionsCard from './inc/RequestRelatedCollectionsCard.vue'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'

const route = useRoute()
const auth = useAuthStore()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const savingComment = ref(false)
const savingAdditionalDocument = ref(false)
const savingRequiredDocumentChange = ref<Record<number, boolean>>({})
const requiredDocumentChangeReason = ref<Record<number, string>>({})
const errorMessage = ref('')
const successMessage = ref('')

const requestItem = ref<any | null>(null)
const requiredDocuments = ref<RequiredDocumentChecklistItem[]>([])
const agents = ref<AgentOption[]>([])
const banks = ref<BankOption[]>([])
const allowedEmailDocuments = ref<AllowedEmailDocument[]>([])
const hasEmailAssignments = ref(false)
const canEmailAssignedAgents = ref(false)
const staffQuestionSummary = ref<StaffQuestionSummary | null>(null)
type StudyAnswerValue = string | string[]

const studyAnswers = ref<Record<number, StudyAnswerValue>>({})
const understudyNote = ref('')
const savingStudyAnswer = ref<Record<number, boolean>>({})
const savingUnderstudyDraftState = ref(false)
const submittingUnderstudyState = ref(false)

const commentText = ref('')
const commentVisibility = ref<'internal' | 'admin_only'>('internal')
const additionalDocumentTitle = ref('')
const additionalDocumentReason = ref('')

const selectedBankId = ref<number | null>(null)
const selectedAgentId = ref<number | null>(null)
const emailSubject = ref('')
const emailBody = ref('')
const selectedEmailDocumentKeys = ref<string[]>([])
const sendingEmail = ref(false)
const { t, locale } = useI18n()

const uploadedRequiredCount = computed(() => requiredDocuments.value.filter((item) => item.is_uploaded).length)
const pendingRequiredCount = computed(() => requiredDocuments.value.filter((item) => !item.is_uploaded).length)
const activeUpdateBatch = computed(() => (requestItem.value?.update_batches ?? []).find((batch: any) => ['open', 'partially_completed'].includes(String(batch.status || ''))))
const staffQuestions = computed<StaffStudyQuestion[]>(() => {
  const rows = Array.isArray(requestItem.value?.staff_questions) ? requestItem.value.staff_questions : []
  return [...rows].sort((a, b) => {
    const aOrder = Number(a?.template?.sort_order ?? 9999)
    const bOrder = Number(b?.template?.sort_order ?? 9999)
    if (aOrder !== bOrder) return aOrder - bOrder
    return Number(a?.id ?? 0) - Number(b?.id ?? 0)
  })
})
const understudyLocked = computed(() => ['submitted', 'approved'].includes(String(requestItem.value?.understudy_status || '').toLowerCase()) || String(requestItem.value?.workflow_stage || '').toLowerCase() === 'awaiting_understudy_review')
const canSubmitUnderstudyPackage = computed(() => !understudyLocked.value && Boolean(staffQuestionSummary.value?.all_required_answered) && understudyNote.value.trim().length > 0)
const emailComposerVisible = computed(() =>
  ['awaiting_agent_assignment', 'processing'].includes(String(requestItem.value?.workflow_stage || '').toLowerCase())
  || hasEmailAssignments.value,
)
const selectedAgentOption = computed(() => agents.value.find((agent) => agent.id === selectedAgentId.value) ?? null)
const mailboxReady = computed(() => Boolean(auth.user?.mailbox_settings?.smtp_enabled && auth.user?.mailbox_settings?.smtp_verified_at && auth.user?.mailbox_settings?.has_smtp_password))
const canSendEmail = computed(() => Boolean(mailboxReady.value && canEmailAssignedAgents.value && selectedAgentId.value && emailSubject.value.trim() && selectedEmailDocumentKeys.value.length > 0))

function localizedModelValue(entity: any, base: string, fallback = t('staffRequestDetails.states.emptyValue')) {
  const ar = entity?.[`${base}_ar`]
  const en = entity?.[`${base}_en`]
  return locale.value === 'ar' ? (ar || en || fallback) : (en || ar || fallback)
}

const summaryStatItems = computed(() => [
  {
    label: 'Status',
    value: requestItem.value?.status || t('staffRequestDetails.states.emptyValue'),
    hint: requestItem.value?.reference_number || '—',
  },
  {
    label: t('staffRequestDetails.summary.workflow'),
    value: stageMeta(requestItem.value?.workflow_stage).label,
    hint: requestItem.value?.approval_reference_number || '—',
  },
  {
    label: t('staffRequestDetails.summary.client'),
    value: intakeFullName(requestItem.value?.intake_details_json, requestItem.value?.client?.name || t('staffRequestDetails.states.clientFallback')),
    hint: requestItem.value?.client?.email || t('staffRequestDetails.states.emptyValue'),
  },
  {
    label: t('staffRequestDetails.summary.companyName'),
    value: requestItem.value?.company_name || requestItem.value?.intake_details_json?.company_name || t('staffRequestDetails.states.emptyValue'),
    hint: requestItem.value?.applicant_type || t('staffRequestDetails.states.emptyValue'),
  },
  {
    label: t('staffRequestDetails.summary.requestedAmount'),
    value: intakeRequestedAmount(requestItem.value?.intake_details_json),
    hint: localizedModelValue(requestItem.value?.finance_request_type, 'name', `${uploadedRequiredCount.value}/${requiredDocuments.value.length} ${t('staffRequestDetails.summary.docs')}`),
  },
])

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

function updateStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'updated') return 'Submitted for review'
  if (key === 'approved') return 'Approved'
  if (key === 'rejected') return 'Rejected'
  if (key === 'pending') return 'Waiting for client'
  return key || 'Unknown'
}

function updateStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return 'client-badge client-badge--green'
  if (key === 'updated') return 'client-badge client-badge--blue'
  if (key === 'rejected') return 'client-badge client-badge--rose'
  return 'client-badge client-badge--amber'
}

function answerText(answer: any) {
  if (!answer) return t('staffRequestDetails.states.emptyValue')
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return t('staffRequestDetails.states.emptyValue')
  return String(value)
}

function studyQuestionType(question: StaffStudyQuestion) {
  return String(question.question_type || question.template?.question_type || 'text').toLowerCase()
}

function studyQuestionOptions(question: StaffStudyQuestion) {
  const options = question.options_json ?? question.template?.options_json ?? []
  return Array.isArray(options) ? options : []
}

function studyQuestionPlaceholder(question: StaffStudyQuestion) {
  return locale.value === 'ar'
    ? (question.placeholder_ar || question.template?.placeholder_ar || question.placeholder_en || question.template?.placeholder_en || '')
    : (question.placeholder_en || question.template?.placeholder_en || question.placeholder_ar || question.template?.placeholder_ar || '')
}

function studyQuestionHelpText(question: StaffStudyQuestion) {
  return locale.value === 'ar'
    ? (question.help_text_ar || question.template?.help_text_ar || question.help_text_en || question.template?.help_text_en || '')
    : (question.help_text_en || question.template?.help_text_en || question.help_text_ar || question.template?.help_text_ar || '')
}

function syncUnderstudyLocalState() {
  const answers: Record<number, StudyAnswerValue> = {}
  for (const question of staffQuestions.value) {
    if (studyQuestionType(question) === 'checkbox') {
      answers[question.id] = Array.isArray(question.answer_json)
        ? [...question.answer_json]
        : (question.answer_text ? question.answer_text.split(',').map((value) => value.trim()).filter(Boolean) : [])
      continue
    }

    answers[question.id] = String(question.answer_text ?? '')
  }
  studyAnswers.value = answers
  understudyNote.value = String(requestItem.value?.understudy_note ?? '')
}

function updateStudyQuestionValue(questionId: number, value: StudyAnswerValue) {
  studyAnswers.value = {
    ...studyAnswers.value,
    [questionId]: value,
  }
}

function updateStudyCheckbox(questionId: number, option: string, checked: boolean) {
  const current = Array.isArray(studyAnswers.value[questionId]) ? [...(studyAnswers.value[questionId] as string[])] : []

  if (checked && !current.includes(option)) {
    current.push(option)
  }

  if (!checked) {
    const index = current.indexOf(option)
    if (index >= 0) current.splice(index, 1)
  }

  updateStudyQuestionValue(questionId, current)
}

function isStudyCheckboxChecked(questionId: number, option: string) {
  return Array.isArray(studyAnswers.value[questionId])
    ? (studyAnswers.value[questionId] as string[]).includes(option)
    : false
}

function studyAnswerTextValue(questionId: number) {
  const value = studyAnswers.value[questionId]
  return Array.isArray(value) ? value.join(', ') : String(value ?? '')
}

function studyQuestionTitle(question: StaffStudyQuestion) {
  return locale.value === 'ar'
    ? (question.question_text_ar || question.template?.question_text_ar || question.question_text_en || question.template?.question_text_en || 'Study question')
    : (question.question_text_en || question.template?.question_text_en || question.question_text_ar || question.template?.question_text_ar || 'Study question')
}

function studyQuestionStatusLabel(question: StaffStudyQuestion) {
  const key = String(question.status || '').toLowerCase()
  if (key === 'closed') return 'Reviewed'
  if (key === 'answered') return 'Saved'
  return 'Pending'
}

function studyQuestionStatusClass(question: StaffStudyQuestion) {
  const key = String(question.status || '').toLowerCase()
  if (key === 'closed') return 'client-badge client-badge--green'
  if (key === 'answered') return 'client-badge client-badge--blue'
  return 'client-badge client-badge--amber'
}

async function saveStudyQuestionAnswer(question: StaffStudyQuestion) {
  if (!requestItem.value || understudyLocked.value) return

  const type = studyQuestionType(question)
  const rawValue = studyAnswers.value[question.id]
  const payload = type === 'checkbox'
    ? {
        answer_text: null,
        answer_json: Array.isArray(rawValue) ? rawValue.filter((value) => String(value).trim() !== '') : [],
      }
    : {
        answer_text: String(Array.isArray(rawValue) ? rawValue.join(', ') : (rawValue ?? '')).trim() || null,
        answer_json: null,
      }

  savingStudyAnswer.value[question.id] = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await answerStaffQuestion(requestItem.value.id, question.id, payload)
    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    syncUnderstudyLocalState()
    successMessage.value = data.message || 'Study answer saved successfully.'
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to save the study answer.'
  } finally {
    savingStudyAnswer.value[question.id] = false
  }
}

async function saveStudyDraftNote() {
  if (!requestItem.value || understudyLocked.value) return

  savingUnderstudyDraftState.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await saveUnderstudyDraft(requestItem.value.id, {
      understudy_note: understudyNote.value,
    })
    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    syncUnderstudyLocalState()
    successMessage.value = data.message || 'Understudy draft saved successfully.'
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to save the understudy draft.'
  } finally {
    savingUnderstudyDraftState.value = false
  }
}

async function submitStudyToAdmin() {
  if (!requestItem.value || !canSubmitUnderstudyPackage.value || understudyLocked.value) return

  submittingUnderstudyState.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await submitUnderstudy(requestItem.value.id, {
      understudy_note: understudyNote.value.trim(),
    })
    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    syncUnderstudyLocalState()
    successMessage.value = data.message || 'Understudy submitted to admin successfully.'
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to submit the understudy package.'
  } finally {
    submittingUnderstudyState.value = false
  }
}

function emailStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'sent') return 'client-badge client-badge--green'
  if (key === 'failed') return 'client-badge client-badge--rose'
  return 'client-badge client-badge--amber'
}

function toggleEmailDocument(documentKey: string, checked: boolean) {
  const next = new Set(selectedEmailDocumentKeys.value)
  if (checked) next.add(documentKey)
  else next.delete(documentKey)
  selectedEmailDocumentKeys.value = Array.from(next)
}

function isEmailDocumentChecked(documentKey: string) {
  return selectedEmailDocumentKeys.value.includes(documentKey)
}

async function sendEmailToAssignedAgent() {
  if (!requestItem.value || !selectedAgentId.value || !canSendEmail.value) return

  sendingEmail.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await sendStaffRequestEmail(requestItem.value.id, {
      bank_id: selectedBankId.value,
      agent_id: selectedAgentId.value,
      document_keys: selectedEmailDocumentKeys.value,
      subject: emailSubject.value.trim(),
      body: emailBody.value.trim() || null,
    })

    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    banks.value = data.banks ?? banks.value
    agents.value = data.agents ?? agents.value
    allowedEmailDocuments.value = data.allowed_documents ?? allowedEmailDocuments.value
    hasEmailAssignments.value = Boolean(data.has_assignments)
    canEmailAssignedAgents.value = Boolean(data.can_email)
    emailSubject.value = ''
    emailBody.value = ''
    selectedEmailDocumentKeys.value = []
    successMessage.value = data.message || 'Email sent successfully.'
  } catch (error: any) {
    const validationErrors = error?.response?.data?.errors
    if (validationErrors && typeof validationErrors === 'object') {
      const firstField = Object.keys(validationErrors)[0]
      const firstMessage = Array.isArray(validationErrors[firstField]) ? validationErrors[firstField][0] : validationErrors[firstField]
      errorMessage.value = firstMessage || error?.response?.data?.message || 'Failed to send the email.'
    } else {
      errorMessage.value = error?.response?.data?.message || 'Failed to send the email.'
    }
  } finally {
    sendingEmail.value = false
  }
}

async function loadEmailOptions() {
  const response = await getStaffRequestEmailOptions(requestId.value, {
    bank_id: selectedBankId.value,
    agent_id: selectedAgentId.value,
  })
  banks.value = response.banks ?? []
  agents.value = response.agents ?? []
  allowedEmailDocuments.value = response.allowed_documents ?? []
  hasEmailAssignments.value = Boolean(response.has_assignments)
  canEmailAssignedAgents.value = Boolean(response.can_email)
}

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [requestResponse] = await Promise.all([getStaffRequest(requestId.value), loadEmailOptions()])
    requestItem.value = requestResponse.request ?? null
    requiredDocuments.value = requestResponse.required_documents ?? []
    staffQuestionSummary.value = requestResponse.staff_question_summary ?? null
    syncUnderstudyLocalState()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequestDetails.errors.loadFailed')
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
    successMessage.value = data.message || t('staffRequestDetails.success.commentAdded')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequestDetails.errors.commentFailed')
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
    successMessage.value = data.message || t('staffRequestDetails.success.additionalRequestCreated')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequestDetails.errors.additionalRequestFailed')
  } finally {
    savingAdditionalDocument.value = false
  }
}


async function submitRequiredDocumentChange(stepId: number) {
  if (!requestItem.value) return

  const reason = (requiredDocumentChangeReason.value[stepId] || '').trim()
  if (!reason) return

  savingRequiredDocumentChange.value[stepId] = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await requestRequiredDocumentChange(requestItem.value.id, stepId, { reason })
    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    requiredDocumentChangeReason.value[stepId] = ''
    successMessage.value = data.message || t('staffRequestDetails.success.changeRequestSent')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequestDetails.errors.changeRequestFailed')
  } finally {
    savingRequiredDocumentChange.value[stepId] = false
  }
}


function requiredDocumentDownloadUrl(uploadId: number | string) {
  return adminRequiredDocumentDownloadUrl(requestId.value, uploadId)
}

function additionalDocumentDownloadUrl(additionalDocumentId: number | string) {
  return adminAdditionalDocumentDownloadUrl(requestId.value, additionalDocumentId)
}

function attachmentDownloadUrl(attachmentId: number | string) {
  return staffAttachmentDownloadUrl(requestId.value, attachmentId)
}

function shareholderIdDownloadUrl(shareholderId: number | string) {
  return staffShareholderIdDownloadUrl(requestId.value, shareholderId)
}

 
 

watch(selectedBankId, async () => {
  selectedAgentId.value = null
  selectedEmailDocumentKeys.value = []
  await loadEmailOptions()
})

watch(selectedAgentId, async () => {
  selectedEmailDocumentKeys.value = []
  await loadEmailOptions()
})

onMounted(load)
</script>

<template>
  <RequestWorkspaceShell
    :eyebrow="t('staffRequestDetails.hero.eyebrow')"
    :title="t('staffRequestDetails.hero.title')"
    :subtitle="t('staffRequestDetails.hero.subtitle')"
    :loading="loading"
    :error-message="errorMessage"
    :success-message="successMessage"
    :has-record="Boolean(requestItem)"
    layout-class=""
  >
    <template #topbar-actions>
      <RouterLink :to="{ name: 'staff-requests' }" class="ghost-btn">{{ t('staffRequestDetails.hero.backToAssignedRequests') }}</RouterLink>
      <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="primary-btn">{{ t('staffRequestDetails.hero.downloadContractPdf') }}</a>
    </template>

    <template #loading>{{ t('staffRequestDetails.states.loading') }}</template>

    <template #summary>
      <RequestSummaryStatGrid :items="summaryStatItems" />
    </template>

    <template #main>
      <RequestCoreDetailsCard :request="requestItem" :required-documents="requiredDocuments" />

      <article style="margin: 1.25rem 0;">
        <RequestRelatedCollectionsCard :request="requestItem" :required-documents="requiredDocuments" />
      </article>
          <details v-if="activeUpdateBatch" class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>Client update batch</h2>
                <p>Read-only view of the currently open client corrections and submitted items.</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="notes-box" v-if="activeUpdateBatch.reason_en || activeUpdateBatch.reason_ar">
                <span>Reason</span>
                <p>{{ locale === 'ar' ? (activeUpdateBatch.reason_ar || activeUpdateBatch.reason_en) : (activeUpdateBatch.reason_en || activeUpdateBatch.reason_ar) }}</p>
              </div>

              <div class="qa-list" v-if="activeUpdateBatch.items?.length">
                <div v-for="item in activeUpdateBatch.items" :key="item.id" class="panel-card slim-card" style="padding: 1rem; margin-bottom: 1rem;">
                  <div class="client-card-head">
                    <div>
                      <h3>{{ locale === 'ar' ? (item.label_ar || item.label_en || 'Requested update') : (item.label_en || item.label_ar || 'Requested update') }}</h3>
                      <p class="client-subtext">{{ locale === 'ar' ? (item.instruction_ar || item.instruction_en || '—') : (item.instruction_en || item.instruction_ar || '—') }}</p>
                    </div>
                    <span :class="updateStatusClass(item.status)">{{ updateStatusLabel(item.status) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </details>
          <details class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.requiredChecklistTitle') }}</h2>
                <p>{{ t('staffRequestDetails.sections.requiredChecklistSubtitle') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div v-if="requiredDocuments.length" class="checklist-grid">
                <article v-for="item in requiredDocuments" :key="item.document_upload_step_id" class="checklist-card" :class="{ 'is-complete': item.is_uploaded && !item.is_change_requested }">
                  <div class="checklist-card__head">
                    <strong>{{ item.name }}</strong>
                    <span class="status-badge">
                      {{ item.is_change_requested ? t('staffRequestDetails.states.changeRequested') : item.is_uploaded ? t('staffRequestDetails.states.uploaded') : t('staffRequestDetails.states.pending') }}
                    </span>
                  </div>
                  <p>{{ item.is_uploaded || item.is_change_requested ? t('staffRequestDetails.states.latestFileLabel', { file: item.upload?.file_name || t('staffRequestDetails.states.uploadedFile') }) : t('staffRequestDetails.states.waitingForClientUpload') }}</p>
                  <div v-if="item.upload?.id" class="approve-actions">
                    <a :href="requiredDocumentDownloadUrl(item.upload.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.downloadLatestFile') }}</a>
                  </div>
                  <p v-if="item.rejection_reason" class="form-help form-help--error">{{ t('staffRequestDetails.states.reasonLabel') }}: {{ item.rejection_reason }}</p>

                  <div v-if="item.is_uploaded && !item.is_change_requested" class="field-stack">
                    <textarea
                      v-model="requiredDocumentChangeReason[item.document_upload_step_id]"
                      rows="3"
                      class="admin-textarea"
                      :placeholder="t('staffRequestDetails.placeholders.changeReason')"
                    ></textarea>
                    <div class="approve-actions">
                      <button
                        class="ghost-btn"
                        type="button"
                        :disabled="savingRequiredDocumentChange[item.document_upload_step_id] || !(requiredDocumentChangeReason[item.document_upload_step_id] || '').trim()"
                        @click="submitRequiredDocumentChange(item.document_upload_step_id)"
                      >
                        {{ savingRequiredDocumentChange[item.document_upload_step_id] ? t('staffRequestDetails.actions.sending') : t('staffRequestDetails.actions.requestChanges') }}
                      </button>
                    </div>
                  </div>
                </article>
              </div>
              <p v-else class="empty-state">{{ t('staffRequestDetails.states.noRequiredDocuments') }}</p>
            </div>
          </details>


          <details class="admin-accordion-card">
  <summary>
    <div>
      <h2>{{ t('staffRequestDetails.sections.initialUploadedFilesTitle') }}</h2>
      <p>{{ t('staffRequestDetails.sections.initialUploadedFilesSubtitle') }}</p>
    </div>
  </summary>
  <div class="admin-accordion-card__body">
    <div v-if="requestItem.attachments?.length" class="file-list">
      <div v-for="file in requestItem.attachments" :key="file.id" class="file-item">
        <div>
          <strong>{{ file.file_name }}</strong>
          <span>{{ file.category }}</span>
        </div>
        <a :href="attachmentDownloadUrl(file.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.download') }}</a>
      </div>
    </div>
    <p v-else class="empty-state">{{ t('staffRequestDetails.states.noInitialFilesUploaded') }}</p>
  </div>
</details>

<details class="admin-accordion-card">
  <summary>
    <div>
      <h2>{{ t('staffRequestDetails.sections.shareholdersTitle') }}</h2>
      <p>{{ t('staffRequestDetails.sections.shareholdersSubtitle') }}</p>
    </div>
  </summary>
  <div class="admin-accordion-card__body">
    <div v-if="requestItem.shareholders?.length" class="file-list">
      <div v-for="shareholder in requestItem.shareholders" :key="shareholder.id" class="file-item">
        <div>
          <strong>{{ shareholder.shareholder_name }}</strong>
          <span v-if="shareholder.phone_number">{{ [shareholder.phone_country_code, shareholder.phone_number].filter(Boolean).join(' ') }}</span>
          <span v-if="shareholder.id_number">{{ t('staffRequestDetails.states.idNumberLabel', { id: shareholder.id_number }) }}</span>
          <span>{{ shareholder.id_file_name }}</span>
        </div>
        <a :href="shareholderIdDownloadUrl(shareholder.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.downloadIdFile') }}</a>
      </div>
    </div>
    <p v-else class="empty-state">{{ t('staffRequestDetails.states.noShareholdersRecorded') }}</p>
  </div>
</details>

          <details class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>Understudy package</h2>
                <p>Answer the study questions, save your understanding note, then submit the full package to admin.</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="notes-box" style="margin-bottom: 1rem;">
                <span>Progress</span>
                <p>
                  {{ staffQuestionSummary?.answered_total || 0 }}/{{ staffQuestionSummary?.total || 0 }} answered ·
                  {{ staffQuestionSummary?.pending_required_total || 0 }} required questions still pending
                </p>
              </div>

              <div v-if="requestItem?.understudy_submitted_at" class="notes-box" style="margin-bottom: 1rem;">
                <span>Submission status</span>
                <p>
                  {{ String(requestItem?.understudy_status || 'draft').toUpperCase() }}
                  <template v-if="requestItem?.understudy_submitted_by?.name"> · Submitted by {{ requestItem.understudy_submitted_by.name }}</template>
                  <template v-if="requestItem?.understudy_submitted_at"> · {{ new Date(requestItem.understudy_submitted_at).toLocaleString() }}</template>
                </p>
              </div>

              <div v-if="staffQuestions.length" class="qa-list">
                <article v-for="question in staffQuestions" :key="question.id" class="panel-card slim-card" style="padding: 1rem; margin-bottom: 1rem;">
                  <div class="client-card-head">
                    <div>
                      <h3>{{ studyQuestionTitle(question) }}</h3>
                      <p class="client-subtext">
                        <template v-if="question.is_required">Required question</template>
                        <template v-else>Optional question</template>
                        <template v-if="question.assigned_staff?.name"> · Assigned to {{ question.assigned_staff.name }}</template>
                      </p>
                    </div>
                    <span :class="studyQuestionStatusClass(question)">{{ studyQuestionStatusLabel(question) }}</span>
                  </div>

                  <input
                    v-if="['text', 'email', 'phone', 'number', 'currency', 'date'].includes(studyQuestionType(question))"
                    :type="studyQuestionType(question) === 'currency' ? 'number' : studyQuestionType(question) === 'phone' ? 'tel' : studyQuestionType(question)"
                    class="admin-input"
                    :step="studyQuestionType(question) === 'currency' ? '0.01' : undefined"
                    :disabled="understudyLocked"
                    :placeholder="studyQuestionPlaceholder(question) || 'Write your answer here'"
                    :value="studyAnswerTextValue(question.id)"
                    @input="updateStudyQuestionValue(question.id, ($event.target as HTMLInputElement).value)"
                  />

                  <textarea
                    v-else-if="studyQuestionType(question) === 'textarea'"
                    :value="studyAnswerTextValue(question.id)"
                    rows="4"
                    class="admin-textarea"
                    :disabled="understudyLocked"
                    :placeholder="studyQuestionPlaceholder(question) || 'Write your answer here'"
                    @input="updateStudyQuestionValue(question.id, ($event.target as HTMLTextAreaElement).value)"
                  ></textarea>

                  <select
                    v-else-if="studyQuestionType(question) === 'select'"
                    class="admin-select"
                    :disabled="understudyLocked"
                    :value="Array.isArray(studyAnswers[question.id]) ? '' : studyAnswerTextValue(question.id)"
                    @change="updateStudyQuestionValue(question.id, ($event.target as HTMLSelectElement).value)"
                  >
                    <option value="">Choose an option</option>
                    <option v-for="option in studyQuestionOptions(question)" :key="option" :value="option">
                      {{ option }}
                    </option>
                  </select>

                  <div v-else-if="studyQuestionType(question) === 'radio'" class="client-choice-grid">
                    <label v-for="option in studyQuestionOptions(question)" :key="option" class="client-choice-card">
                      <input
                        type="radio"
                        :name="`staff-study-question-${question.id}`"
                        :checked="studyAnswers[question.id] === option"
                        :value="option"
                        :disabled="understudyLocked"
                        @change="updateStudyQuestionValue(question.id, option)"
                      />
                      <span>{{ option }}</span>
                    </label>
                  </div>

                  <div v-else-if="studyQuestionType(question) === 'checkbox'" class="client-choice-grid">
                    <label v-for="option in studyQuestionOptions(question)" :key="option" class="client-choice-card">
                      <input
                        type="checkbox"
                        :checked="isStudyCheckboxChecked(question.id, option)"
                        :disabled="understudyLocked"
                        @change="updateStudyCheckbox(question.id, option, ($event.target as HTMLInputElement).checked)"
                      />
                      <span>{{ option }}</span>
                    </label>
                  </div>

                  <input
                    v-else
                    type="text"
                    class="admin-input"
                    :disabled="understudyLocked"
                    :placeholder="studyQuestionPlaceholder(question) || 'Write your answer here'"
                    :value="studyAnswerTextValue(question.id)"
                    @input="updateStudyQuestionValue(question.id, ($event.target as HTMLInputElement).value)"
                  />

                  <p v-if="studyQuestionHelpText(question)" class="client-subtext" style="margin-top: 0.5rem;">{{ studyQuestionHelpText(question) }}</p>

                  <div class="approve-actions" style="margin-top: 0.75rem;">
                    <button
                      type="button"
                      class="ghost-btn"
                      :disabled="understudyLocked || savingStudyAnswer[question.id]"
                      @click="saveStudyQuestionAnswer(question)"
                    >
                      {{ savingStudyAnswer[question.id] ? 'Saving...' : 'Save answer' }}
                    </button>
                  </div>
                </article>
              </div>
              <p v-else class="empty-state">No staff study questions are available for this request yet.</p>

              <article class="panel-card slim-card" style="margin-top: 1rem;">
                <div class="panel-head"><h3>What you understood</h3></div>
                <textarea
                  v-model="understudyNote"
                  rows="5"
                  class="admin-textarea"
                  :disabled="understudyLocked"
                  placeholder="Add a short study note for the admin"
                ></textarea>
                <p class="client-subtext" style="margin-top: 0.5rem;">This note is internal between staff and admin. The client will only continue seeing Understudy.</p>
                <div class="approve-actions" style="margin-top: 0.75rem; gap: 0.75rem; flex-wrap: wrap;">
                  <button type="button" class="ghost-btn" :disabled="understudyLocked || savingUnderstudyDraftState" @click="saveStudyDraftNote">
                    {{ savingUnderstudyDraftState ? 'Saving...' : 'Save draft note' }}
                  </button>
                  <button type="button" class="primary-btn" :disabled="!canSubmitUnderstudyPackage || submittingUnderstudyState || understudyLocked" @click="submitStudyToAdmin">
                    {{ submittingUnderstudyState ? 'Submitting...' : 'Submit study to admin' }}
                  </button>
                </div>
              </article>
            </div>
          </details>

          <details class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.followUpTitle') }}</h2>
                <p>{{ t('staffRequestDetails.sections.followUpSubtitle') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div v-if="!mailboxReady" class="notes-box" style="margin-bottom: 1rem;">
                <span>{{ t('staffRequestDetails.mailboxSetup.title') }}</span>
                <p>The admin still needs to save and verify your mailbox before you can send request emails from this workspace.</p>
              </div>

              <div v-if="!hasEmailAssignments" class="notes-box" style="margin-bottom: 1rem;">
                <span>Waiting for admin setup</span>
                <p>The admin still needs to approve the bank-agent assignment phase before you can prepare a controlled email for this request.</p>
              </div>

              <div class="admin-inline-block-grid">
                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.addInternalComment') }}</h3></div>
                  <div class="field-block field-block--grow">
                    <span>{{ t('staffRequestDetails.form.visibility') }}</span>
                    <select v-model="commentVisibility" class="admin-select">
                      <option value="internal">{{ t('staffRequestDetails.form.internal') }}</option>
                      <option value="admin_only">{{ t('staffRequestDetails.form.adminOnly') }}</option>
                    </select>
                  </div>
                  <textarea v-model="commentText" rows="5" class="admin-textarea" :placeholder="t('staffRequestDetails.placeholders.commentText')"></textarea>
                  <div class="approve-actions">
                    <button class="primary-btn" type="button" :disabled="savingComment || !commentText.trim()" @click="submitComment">
                      {{ savingComment ? t('staffRequestDetails.actions.saving') : t('staffRequestDetails.actions.saveComment') }}
                    </button>
                  </div>
                </article>

                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.requestAdditionalDocument') }}</h3></div>
                  <input v-model="additionalDocumentTitle" type="text" class="admin-input" :placeholder="t('staffRequestDetails.placeholders.documentTitle')" />
                  <textarea v-model="additionalDocumentReason" rows="5" class="admin-textarea" :placeholder="t('staffRequestDetails.placeholders.additionalReason')"></textarea>
                  <div class="approve-actions">
                    <button class="primary-btn" type="button" :disabled="savingAdditionalDocument || !additionalDocumentTitle.trim()" @click="submitAdditionalDocumentRequest">
                      {{ savingAdditionalDocument ? t('staffRequestDetails.actions.saving') : t('staffRequestDetails.actions.createRequest') }}
                    </button>
                  </div>
                </article>
              </div>

              <article class="panel-card slim-card">
                <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.requestedAdditionalDocuments') }}</h3></div>
                <div v-if="requestItem.additional_documents?.length" class="timeline-list compact-list">
                  <div v-for="item in requestItem.additional_documents" :key="item.id" class="timeline-item">
                    <strong>{{ item.title }}</strong>
                    <p>{{ item.reason || t('staffRequestDetails.states.noReasonAdded') }}</p>
                    <span>{{ item.status }}<template v-if="item.file_name"> · {{ item.file_name }}</template></span>
                    <div v-if="item.file_name" class="approve-actions">
                      <a :href="additionalDocumentDownloadUrl(item.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.downloadFile') }}</a>
                    </div>
                  </div>
                </div>
                <p v-else class="empty-state">{{ t('staffRequestDetails.states.noAdditionalDocumentsRequested') }}</p>
              </article>
            </div>
          </details>

          <details v-if="emailComposerVisible" class="admin-accordion-card">
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.emailComposerTitle') }}</h2>
                <p>{{ t('staffRequestDetails.sections.emailComposerSubtitle') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div v-if="!mailboxReady" class="notes-box" style="margin-bottom: 1rem;">
                <span>{{ t('staffRequestDetails.mailboxSetup.title') }}</span>
                <p>The admin still needs to save and verify your mailbox before you can send request emails from this workspace.</p>
              </div>

              <div v-if="!hasEmailAssignments" class="notes-box" style="margin-bottom: 1rem;">
                <span>Waiting for admin setup</span>
                <p>The admin still needs to approve the bank-agent assignment phase before you can prepare a controlled email for this request.</p>
              </div>

              <div class="admin-inline-block-grid">
                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.recipients') }}</h3></div>
                  <div class="field-block field-block--grow">
                    <span>{{ t('staffRequestDetails.form.bank') }}</span>
                    <select v-model="selectedBankId" class="admin-select">
                      <option :value="null">{{ t('staffRequestDetails.form.allBanks') }}</option>
                      <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                    </select>
                  </div>
                  <div class="field-block field-block--grow">
                    <span>{{ t('staffRequestDetails.form.agents') }}</span>
                    <select v-model="selectedAgentId" class="admin-select">
                      <option :value="null">Select an assigned agent</option>
                      <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                        {{ agent.name }}<template v-if="agent.bank_name"> · {{ agent.bank_name }}</template>
                      </option>
                    </select>
                  </div>
                  <p class="client-subtext" style="margin-top: 0.75rem;">
                    Only the bank agents approved by the admin for this request appear here.
                  </p>
                </article>

                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.emailBody') }}</h3></div>
                  <input v-model="emailSubject" type="text" class="admin-input" :placeholder="t('staffRequestDetails.placeholders.emailSubject')" :disabled="!canEmailAssignedAgents || !selectedAgentId" />
                  <textarea v-model="emailBody" rows="7" class="admin-textarea" :placeholder="t('staffRequestDetails.placeholders.emailBody')" :disabled="!canEmailAssignedAgents || !selectedAgentId"></textarea>

                  <div class="notes-box" style="margin-top: 0.75rem;">
                    <span>Allowed request files</span>
                    <p v-if="selectedAgentOption">Choose the approved files that should be attached when sending to {{ selectedAgentOption.name }}.</p>
                    <p v-else>Select an assigned agent to see the exact files approved by the admin.</p>
                  </div>

                  <div v-if="allowedEmailDocuments.length" class="timeline-list compact-list" style="margin-top: 0.75rem;">
                    <label v-for="document in allowedEmailDocuments" :key="document.key" class="timeline-item" style="cursor: pointer;">
                      <div style="display:flex;gap:0.75rem;align-items:flex-start;">
                        <input
                          type="checkbox"
                          :checked="isEmailDocumentChecked(document.key)"
                          :disabled="!canEmailAssignedAgents || !selectedAgentId"
                          @change="toggleEmailDocument(document.key, ($event.target as HTMLInputElement).checked)"
                        >
                        <div style="flex:1;">
                          <strong>{{ document.label }}</strong>
                          <p>{{ document.group_label || 'Request file' }}</p>
                          <span>{{ document.file_name }}</span>
                          <div v-if="document.download_url" class="approve-actions" style="margin-top: 0.45rem;">
                            <a :href="document.download_url" target="_blank" rel="noopener" class="ghost-btn">Preview file</a>
                          </div>
                        </div>
                      </div>
                    </label>
                  </div>
                  <p v-else class="empty-state" style="margin-top: 0.75rem;">No files have been assigned to the selected agent yet.</p>

                  <div class="approve-actions" style="margin-top: 1rem;">
                    <button class="primary-btn" type="button" :disabled="sendingEmail || !canSendEmail" @click="sendEmailToAssignedAgent">
                      {{ sendingEmail ? 'Sending...' : 'Send email now' }}
                    </button>
                  </div>
                </article>
              </div>

              <article class="panel-card slim-card" style="margin-top: 1rem;">
                <div class="panel-head"><h3>Sent email history</h3></div>
                <div v-if="requestItem.emails?.length" class="timeline-list compact-list">
                  <div v-for="email in requestItem.emails" :key="email.id" class="timeline-item">
                    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;">
                      <div>
                        <strong>{{ email.subject }}</strong>
                        <p>From: {{ email.sender?.name || 'System' }} · {{ email.from_email || email.sender?.email || '—' }}</p>
                        <p>To: {{ email.agents?.map((agent: any) => agent.name).join(', ') || '—' }}</p>
                        <p v-if="email.body">{{ email.body }}</p>
                        <span>{{ email.attachments?.length || 0 }} attachment(s) · {{ email.sent_at ? new Date(email.sent_at).toLocaleString() : new Date(email.created_at || '').toLocaleString() }}</span>
                      </div>
                      <span :class="emailStatusClass(email.delivery_status)">{{ email.delivery_status || 'queued' }}</span>
                    </div>
                  </div>
                </div>
                <p v-else class="empty-state">No outbound emails have been sent for this request yet.</p>
              </article>
            </div>
          </details>
    </template>

    <template #side>
          <article class="panel-card slim-card">
            <div class="panel-head"><h2>Request information parity</h2></div>
            <p class="subtext">The shared overview cards now show the same request information for admin and staff. Role-specific actions remain below.</p>
          </article>

          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ t('staffRequestDetails.sections.recentInternalHistory') }}</h2></div>
            <div v-if="requestItem.comments?.length" class="timeline-list compact-list">
              <div v-for="comment in requestItem.comments.slice(0, 4)" :key="comment.id" class="timeline-item">
                <strong>{{ comment.user?.name || t('staffRequestDetails.states.system') }}</strong>
                <p>{{ comment.comment_text }}</p>
                <span>{{ new Date(comment.created_at).toLocaleString() }}</span>
              </div>
            </div>
            <p v-else class="empty-state">{{ t('staffRequestDetails.states.noInternalComments') }}</p>
          </article>

          <details class="admin-accordion-card slim-accordion">
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.questionnaireAnswers') }}</h2>
                <p>{{ t('staffRequestDetails.sections.expandWhenNeeded') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <RequestAnswersList
                :answers="requestItem.answers || []"
                :empty-text="t('staffRequestDetails.states.noAnswersRecorded')"
                :question-fallback="t('staffRequestDetails.states.questionFallback')"
                :format-answer="answerText"
                compact
              />
            </div>
          </details>
    </template>
  </RequestWorkspaceShell>
</template>
