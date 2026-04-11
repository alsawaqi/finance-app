<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  adminAdditionalDocumentDownloadUrl,
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
  staffAttachmentsBundleDownloadUrl,
  staffRequiredDocumentStepBundleDownloadUrl,
  staffShareholderIdDownloadUrl,
  submitUnderstudy,
  uploadStaffRequiredDocument,
  type AgentOption,
  type AllowedEmailDocument,
  type BankOption,
  type RequiredDocumentChecklistItem,
  type StaffQuestionSummary,
  type StaffStudyQuestion,
} from '@/services/staffWorkspace'
import {
  applicantTypeLabel,
  intakeFinanceType,
  intakeFullName,
  intakeRequestedAmount,
} from '@/utils/requestIntake'
import AppFilePreviewModal from '@/components/AppFilePreviewModal.vue'
import { buildPreviewUrl } from '@/utils/filePreview'
import AdminQuickViewModal from './inc/AdminQuickViewModal.vue'
import RequestAnswersList from './inc/RequestAnswersList.vue'
import RequestSummaryStatGrid from './inc/RequestSummaryStatGrid.vue'
import RequestWorkspaceShell from './inc/RequestWorkspaceShell.vue'
import RequestCoreDetailsCard from './inc/RequestCoreDetailsCard.vue'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'
import {
  formatAdditionalDocumentStatus,
  formatEmailDeliveryStatus,
  formatRequestStatus,
  formatUnderstudyStatus,
} from '@/utils/requestStatus'
import { formatDateTime } from '@/utils/dateTime'

const route = useRoute()
const auth = useAuthStore()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const savingComment = ref(false)
const savingAdditionalDocument = ref(false)
const savingRequiredDocumentChange = ref<Record<number, boolean>>({})
const requiredDocumentChangeReason = ref<Record<number, string>>({})
const uploadingRequiredDocument = ref<Record<number, boolean>>({})
const selectedRequiredDocumentFiles = ref<Record<number, File | null>>({})
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
const understudySectionOpen = ref(true)

const commentText = ref('')
const commentVisibility = ref<'internal' | 'admin_only' | 'client_visible'>('internal')
const additionalDocumentTitle = ref('')
const additionalDocumentReason = ref('')

const selectedBankId = ref<number | null>(null)
const selectedAgentId = ref<number | null>(null)
const emailSubject = ref('')
const emailBody = ref('')
const selectedEmailDocumentKeys = ref<string[]>([])
const sendingEmail = ref(false)
const recipientPickerOpen = ref(false)
const attachmentPickerOpen = ref(false)
const emailEditorRef = ref<HTMLElement | null>(null)
const emailEditorFocused = ref(false)
const quickView = ref<'updateBatch' | 'attachments' | 'shareholders' | 'answers' | 'comments' | 'additionalDocuments' | null>(null)
const { t, locale } = useI18n()
const filePreviewOpen = ref(false)
const filePreviewName = ref('')
const filePreviewMime = ref('')
const filePreviewUrl = ref('')
const fileDownloadUrl = ref('')

function openFilePreview(fileName: string | null | undefined, downloadUrl: string, mimeType?: string | null) {
  const targetUrl = String(downloadUrl || '').trim()
  if (!targetUrl) return
  filePreviewName.value = String(fileName || t('staffRequestDetails.states.emptyValue'))
  filePreviewMime.value = String(mimeType || '')
  fileDownloadUrl.value = targetUrl
  filePreviewUrl.value = buildPreviewUrl(targetUrl)
  filePreviewOpen.value = true
}

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
const understudyQuestionsCompleted = computed(() => Boolean(staffQuestionSummary.value?.all_required_answered))
const understudyQuestionInputsLocked = computed(() => understudyLocked.value)
const canSubmitUnderstudyPackage = computed(() => !understudyLocked.value && Boolean(staffQuestionSummary.value?.all_required_answered) && understudyNote.value.trim().length > 0)
const understudySectionVisible = computed(() => {
  const stage = String(requestItem.value?.workflow_stage || '').toLowerCase()
  return ['understudy', 'awaiting_staff_answers', 'awaiting_understudy_review'].includes(stage)
})
const emailComposerVisible = computed(() =>
  ['awaiting_agent_assignment', 'processing'].includes(String(requestItem.value?.workflow_stage || '').toLowerCase())
  || hasEmailAssignments.value,
)
const selectedAgentOption = computed(() => agents.value.find((agent) => agent.id === selectedAgentId.value) ?? null)
const selectedBankOption = computed(() => banks.value.find((bank) => bank.id === selectedBankId.value) ?? null)
const mailboxReady = computed(() => Boolean(auth.user?.mailbox_settings?.smtp_enabled && auth.user?.mailbox_settings?.smtp_verified_at && auth.user?.mailbox_settings?.has_smtp_password))
const canComposeEmail = computed(() => Boolean(mailboxReady.value && canEmailAssignedAgents.value && selectedAgentId.value))
const selectedEmailAttachments = computed(() =>
  (allowedEmailDocuments.value ?? []).filter((document) => selectedEmailDocumentKeys.value.includes(document.key)),
)
const emailBodyTextLength = computed(() => stripHtml(emailBody.value).trim().length)
const canSendEmail = computed(() => Boolean(
  canComposeEmail.value
  && emailSubject.value.trim()
  && selectedEmailDocumentKeys.value.length > 0
  && emailBodyTextLength.value > 0,
))
const allAttachmentItems = computed(() => {
  const rows: Array<{
    key: string
    file_name: string
    source_label: string
    download_url: string
    mime_type?: string | null
    uploaded_at?: string | null
  }> = []

  const initialAttachments = Array.isArray(requestItem.value?.attachments) ? requestItem.value.attachments : []
  for (const attachment of initialAttachments) {
    if (!attachment?.id || !attachment?.file_name) continue
    rows.push({
      key: `initial-${attachment.id}`,
      file_name: String(attachment.file_name),
      source_label: uiText('Initial request file', 'ملف الطلب الأساسي'),
      download_url: attachmentDownloadUrl(attachment.id),
      mime_type: attachment.mime_type || attachment.file_extension || null,
      uploaded_at: attachment.uploaded_at || attachment.created_at || null,
    })
  }

  for (const requiredItem of requiredDocuments.value) {
    const uploads = normalizedRequiredUploads(requiredItem)
    for (const upload of uploads) {
      if (!upload?.id || !upload?.file_name) continue
      rows.push({
        key: `required-${requiredItem.document_upload_step_id}-${upload.id}`,
        file_name: String(upload.file_name),
        source_label: `${uiText('Required document', 'مستند مطلوب')}: ${requiredItem.name}`,
        download_url: requiredDocumentDownloadUrl(upload.id),
        mime_type: upload.mime_type || upload.file_extension || null,
        uploaded_at: upload.uploaded_at || null,
      })
    }
  }

  const additionalDocuments = Array.isArray(requestItem.value?.additional_documents) ? requestItem.value.additional_documents : []
  for (const additional of additionalDocuments) {
    if (!additional?.id || !additional?.file_name) continue
    rows.push({
      key: `additional-${additional.id}`,
      file_name: String(additional.file_name),
      source_label: `${uiText('Additional document', 'مستند إضافي')}: ${additional.title || uiText('Untitled', 'بدون عنوان')}`,
      download_url: additionalDocumentDownloadUrl(additional.id),
      mime_type: additional.mime_type || additional.file_extension || null,
      uploaded_at: additional.uploaded_at || additional.created_at || null,
    })
  }

  const shareholders = Array.isArray(requestItem.value?.shareholders) ? requestItem.value.shareholders : []
  for (const shareholder of shareholders) {
    if (!shareholder?.id || !shareholder?.id_file_name) continue
    rows.push({
      key: `shareholder-${shareholder.id}`,
      file_name: String(shareholder.id_file_name),
      source_label: `${uiText('Shareholder ID', 'هوية مساهم')}: ${shareholder.shareholder_name || uiText('Unknown', 'غير معروف')}`,
      download_url: shareholderIdDownloadUrl(shareholder.id),
      mime_type: shareholder.mime_type || shareholder.file_extension || null,
      uploaded_at: shareholder.uploaded_at || shareholder.created_at || null,
    })
  }

  return rows.sort((a, b) => {
    const aTs = a.uploaded_at ? Date.parse(a.uploaded_at) : 0
    const bTs = b.uploaded_at ? Date.parse(b.uploaded_at) : 0
    return bTs - aTs
  })
})
const activityCounts = computed(() => ({
  attachments: allAttachmentItems.value.length,
  shareholders: requestItem.value?.shareholders?.length ?? 0,
  answers: requestItem.value?.answers?.length ?? 0,
  comments: requestItem.value?.comments?.length ?? 0,
  additionalDocuments: requestItem.value?.additional_documents?.length ?? 0,
  emails: requestItem.value?.emails?.length ?? 0,
}))

watch(
  understudyLocked,
  (locked) => {
    if (locked) {
      understudySectionOpen.value = false
      return
    }

    understudySectionOpen.value = true
  },
)

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

const quickViewTitle = computed(() => {
  switch (quickView.value) {
    case 'updateBatch':
      return uiText('Client update batch', 'دفعة تحديث العميل')
    case 'attachments':
      return uiText('All attachments', 'كل المرفقات')
    case 'shareholders':
      return t('staffRequestDetails.sections.shareholdersTitle')
    case 'answers':
      return t('staffRequestDetails.sections.questionnaireAnswers')
    case 'comments':
      return t('staffRequestDetails.sections.recentInternalHistory')
    case 'additionalDocuments':
      return t('staffRequestDetails.sections.requestedAdditionalDocuments')
    default:
      return uiText('Workspace details', 'تفاصيل مساحة العمل')
  }
})

function localizedModelValue(entity: any, base: string, fallback = t('staffRequestDetails.states.emptyValue')) {
  const ar = entity?.[`${base}_ar`]
  const en = entity?.[`${base}_en`]
  return locale.value === 'ar' ? (ar || en || fallback) : (en || ar || fallback)
}

const summaryStatItems = computed(() => [
  {
    label: uiText('Status', 'الحالة'),
    value: formatRequestStatus(requestItem.value?.status, locale, t('staffRequestDetails.states.emptyValue')),
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
    hint: applicantTypeLabel(requestItem.value?.applicant_type, locale, t('staffRequestDetails.states.emptyValue')),
  },
  {
    label: t('staffRequestDetails.summary.requestedAmount'),
    value: intakeRequestedAmount(requestItem.value?.intake_details_json, t('staffRequestDetails.states.emptyValue'), true),
    hint: localizedModelValue(
      requestItem.value?.finance_request_type,
      'name',
      `${intakeFinanceType(requestItem.value?.intake_details_json, t('staffRequestDetails.states.emptyValue'), locale)} · ${uploadedRequiredCount.value}/${requiredDocuments.value.length} ${t('staffRequestDetails.summary.docs')}`,
    ),
  },
])

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

function syncUnderstudySectionState(event: Event) {
  const target = event.target as HTMLDetailsElement | null
  if (!target) return
  understudySectionOpen.value = target.open
}

function updateStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'updated') return uiText('Submitted for review', 'تم الإرسال للمراجعة')
  if (key === 'approved') return uiText('Approved', 'تمت الموافقة')
  if (key === 'rejected') return uiText('Rejected', 'مرفوض')
  if (key === 'pending') return uiText('Waiting for client', 'بانتظار العميل')
  return key || uiText('Unknown', 'غير معروف')
}

function updateStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return 'client-badge client-badge--green'
  if (key === 'updated') return 'client-badge client-badge--blue'
  if (key === 'rejected') return 'client-badge client-badge--rose'
  return 'client-badge client-badge--amber'
}

function readableCommentVisibility(value: string | null | undefined) {
  const key = String(value || '').trim().toLowerCase()
  if (key === 'internal') return uiText('Internal', 'داخلي')
  if (key === 'admin_only') return uiText('Admin only', 'للإدارة فقط')
  if (key === 'client_visible') return uiText('Client visible', 'مرئي للعميل')
  return key || t('staffRequestDetails.states.emptyValue')
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
    ? (question.question_text_ar || question.template?.question_text_ar || question.question_text_en || question.template?.question_text_en || uiText('Study question', 'سؤال الدراسة'))
    : (question.question_text_en || question.template?.question_text_en || question.question_text_ar || question.template?.question_text_ar || uiText('Study question', 'سؤال الدراسة'))
}

function studyQuestionStatusLabel(question: StaffStudyQuestion) {
  const key = String(question.status || '').toLowerCase()
  if (key === 'closed') return uiText('Reviewed', 'تمت المراجعة')
  if (key === 'answered') return uiText('Saved', 'تم الحفظ')
  return uiText('Pending', 'قيد الانتظار')
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
    successMessage.value = data.message || uiText('Study answer saved successfully.', 'تم حفظ إجابة الدراسة بنجاح.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Failed to save the study answer.', 'تعذر حفظ إجابة الدراسة.')
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
    successMessage.value = data.message || uiText('Understudy draft saved successfully.', 'تم حفظ مسودة الدراسة بنجاح.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Failed to save the understudy draft.', 'تعذر حفظ مسودة الدراسة.')
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
    successMessage.value = data.message || uiText('Understudy submitted to admin successfully.', 'تم إرسال الدراسة إلى الإدارة بنجاح.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Failed to submit the understudy package.', 'تعذر إرسال حزمة الدراسة.')
  } finally {
    submittingUnderstudyState.value = false
  }
}

function emailStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'delivered') return 'client-badge client-badge--green'
  if (key === 'sent') return 'client-badge client-badge--green'
  if (key === 'failed') return 'client-badge client-badge--rose'
  return 'client-badge client-badge--amber'
}

function readableDateTime(value: unknown) {
  return formatDateTime(value, locale, t('staffRequestDetails.states.emptyValue'))
}

function readableUnderstudyStatus(status: string | null | undefined) {
  return formatUnderstudyStatus(status, locale, t('staffRequestDetails.states.emptyValue'))
}

function readableEmailDeliveryStatus(status: string | null | undefined) {
  return formatEmailDeliveryStatus(status, locale, uiText('queued', '\u0642\u064a\u062f \u0627\u0644\u0627\u0646\u062a\u0638\u0627\u0631'))
}

function readableAdditionalDocumentStatus(status: string | null | undefined) {
  return formatAdditionalDocumentStatus(status, locale, t('staffRequestDetails.states.emptyValue'))
}

function stripHtml(value: string) {
  return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')
}

function normalizeEditorHtml(value: string) {
  const normalized = String(value || '').replace(/\u200B/g, '').trim()
  if (!normalized || normalized === '<br>' || normalized === '<div><br></div>') {
    return ''
  }

  return normalized
}

function syncEmailBodyFromEditor() {
  if (!emailEditorRef.value) return
  emailBody.value = normalizeEditorHtml(emailEditorRef.value.innerHTML)
}

function applyEmailEditorCommand(command: string, value?: string) {
  if (!canComposeEmail.value || !emailEditorRef.value) return
  emailEditorRef.value.focus()
  document.execCommand(command, false, value)
  syncEmailBodyFromEditor()
}

function clearEmailComposer() {
  emailSubject.value = ''
  emailBody.value = ''
  selectedEmailDocumentKeys.value = []

  if (emailEditorRef.value) {
    emailEditorRef.value.innerHTML = ''
  }
}

function openRecipientPicker() {
  recipientPickerOpen.value = true
}

function openAttachmentPicker() {
  if (!canComposeEmail.value) return
  attachmentPickerOpen.value = true
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
    clearEmailComposer()
    successMessage.value = data.message || uiText('Email sent successfully.', 'تم إرسال البريد بنجاح.')
  } catch (error: any) {
    const validationErrors = error?.response?.data?.errors
    if (validationErrors && typeof validationErrors === 'object') {
      const firstField = Object.keys(validationErrors)[0]
      const firstMessage = Array.isArray(validationErrors[firstField]) ? validationErrors[firstField][0] : validationErrors[firstField]
      errorMessage.value = firstMessage || error?.response?.data?.message || uiText('Failed to send the email.', 'تعذر إرسال البريد.')
    } else {
      errorMessage.value = error?.response?.data?.message || uiText('Failed to send the email.', 'تعذر إرسال البريد.')
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
    const requestResponse = await getStaffRequest(requestId.value)
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

function requiredDocumentFileName(stepId: number) {
  return selectedRequiredDocumentFiles.value[stepId]?.name || ''
}

function onRequiredDocumentFileChange(stepId: number, event: Event) {
  const target = event.target as HTMLInputElement | null
  const file = target?.files?.[0] ?? null
  selectedRequiredDocumentFiles.value = {
    ...selectedRequiredDocumentFiles.value,
    [stepId]: file,
  }
}

async function uploadRequiredDocumentForClient(stepId: number) {
  if (!requestItem.value) return

  const file = selectedRequiredDocumentFiles.value[stepId]
  if (!file) return

  uploadingRequiredDocument.value[stepId] = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await uploadStaffRequiredDocument(requestItem.value.id, {
      document_upload_step_id: stepId,
      file,
    })

    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    selectedRequiredDocumentFiles.value = {
      ...selectedRequiredDocumentFiles.value,
      [stepId]: null,
    }
    successMessage.value = data.message || uiText('Required document uploaded successfully.', 'تم رفع المستند المطلوب بنجاح.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Failed to upload the required document.', 'تعذر رفع المستند المطلوب.')
  } finally {
    uploadingRequiredDocument.value[stepId] = false
  }
}


function requiredDocumentDownloadUrl(uploadId: number | string) {
  return adminRequiredDocumentDownloadUrl(requestId.value, uploadId)
}

function requiredDocumentStepBundleDownloadUrl(stepId: number | string) {
  return staffRequiredDocumentStepBundleDownloadUrl(requestId.value, stepId)
}

function additionalDocumentDownloadUrl(additionalDocumentId: number | string) {
  return adminAdditionalDocumentDownloadUrl(requestId.value, additionalDocumentId)
}

function attachmentDownloadUrl(attachmentId: number | string) {
  return staffAttachmentDownloadUrl(requestId.value, attachmentId)
}

function attachmentBundleDownloadUrl() {
  return staffAttachmentsBundleDownloadUrl(requestId.value)
}

function shareholderIdDownloadUrl(shareholderId: number | string) {
  return staffShareholderIdDownloadUrl(requestId.value, shareholderId)
}

function normalizedRequiredUploads(item: RequiredDocumentChecklistItem | any) {
  const uploads = Array.isArray(item?.uploads) ? [...item.uploads] : []
  if (!uploads.length && item?.upload?.id) {
    uploads.push(item.upload)
  }

  return uploads
}

function latestRequiredUpload(item: RequiredDocumentChecklistItem | any) {
  return normalizedRequiredUploads(item)[0] ?? null
}

function requiredUploadStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return uiText('Approved', 'معتمد')
  if (key === 'rejected') return uiText('Change requested', 'طُلب تعديل')
  if (key === 'uploaded') return uiText('Uploaded', 'مرفوع')
  if (key === 'pending') return uiText('Pending', 'قيد الانتظار')
  return key || t('staffRequestDetails.states.emptyValue')
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

watch(emailComposerVisible, async (visible) => {
  if (!visible) return

  await nextTick()
  if (emailEditorRef.value && emailEditorRef.value.innerHTML !== emailBody.value) {
    emailEditorRef.value.innerHTML = emailBody.value
  }
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
  >
    <template #topbar-actions>
      <RouterLink :to="{ name: 'staff-requests' }" class="ghost-btn">{{ t('staffRequestDetails.hero.backToAssignedRequests') }}</RouterLink>
      <RouterLink :to="{ name: 'staff-request-send-email', params: { id: requestId } }" class="primary-btn">{{ uiText('Send email', 'إرسال بريد') }}</RouterLink>
    </template>

    <template #loading>{{ t('staffRequestDetails.states.loading') }}</template>

    <template #summary>
      <div class="request-summary-stack">
        <RequestSummaryStatGrid :items="summaryStatItems" />

        <div class="request-top-panel-grid">
          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ uiText('Workspace snapshot', 'ملخص مساحة العمل') }}</h2></div>
            <div class="catalog-mini-stats request-kpi-grid request-kpi-grid--two">
              <div><span>{{ uiText('Pending docs', 'المستندات المعلقة') }}</span><strong>{{ pendingRequiredCount }}</strong></div>
              <div><span>{{ uiText('Study ready', 'جاهزية الدراسة') }}</span><strong>{{ staffQuestionSummary?.all_required_answered ? uiText('Yes', 'نعم') : uiText('No', 'لا') }}</strong></div>
              <div><span>{{ t('staffRequestDetails.sections.recentInternalHistory') }}</span><strong>{{ activityCounts.comments }}</strong></div>
              <div><span>{{ uiText('Sent emails', 'الرسائل المرسلة') }}</span><strong>{{ activityCounts.emails }}</strong></div>
            </div>
          </article>

          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ uiText('Read-only details', 'تفاصيل للعرض') }}</h2></div>
            <p class="subtext">{{ uiText('Use the quick views to inspect uploads, shareholders, answers, comments, and email history without stretching the page.', 'استخدم العروض السريعة لمراجعة الملفات والمساهمين والإجابات والتعليقات وسجل البريد دون إطالة الصفحة.') }}</p>
            <div class="approve-actions request-footer-actions">
              <button type="button" class="ghost-btn" @click="quickView = 'answers'">{{ t('staffRequestDetails.sections.questionnaireAnswers') }}</button>
              <button type="button" class="ghost-btn" @click="quickView = 'comments'">{{ t('staffRequestDetails.sections.recentInternalHistory') }}</button>
              <RouterLink class="ghost-btn" :to="{ name: 'staff-request-emails', params: { id: requestId } }">{{ uiText('Sent email history', 'سجل الرسائل المرسلة') }}</RouterLink>
            </div>
          </article>
        </div>
      </div>
    </template>

    <template #main>
      <div class="request-workspace-stack">
      <RequestCoreDetailsCard :request="requestItem" :required-documents="requiredDocuments" />

      <article class="panel-card request-quick-panel">
        <div class="panel-head">
          <div>
            <h2>{{ uiText('Quick access', 'وصول سريع') }}</h2>
            <p class="subtext">{{ uiText('Keep the long read-only details one tap away while you work through the request.', 'احتفظ بالتفاصيل الطويلة للقراءة فقط على بعد ضغطة واحدة أثناء إنجاز الطلب.') }}</p>
          </div>
        </div>

        <div class="catalog-mini-stats request-kpi-grid">
          <div>
            <span>{{ t('staffRequestDetails.sections.requiredChecklistTitle') }}</span>
            <strong>{{ uploadedRequiredCount }}/{{ requiredDocuments.length }}</strong>
          </div>
          <div>
            <span>{{ uiText('Study progress', 'تقدم الدراسة') }}</span>
            <strong>{{ staffQuestionSummary?.answered_total || 0 }}/{{ staffQuestionSummary?.total || 0 }}</strong>
          </div>
          <div>
            <span>{{ t('staffRequestDetails.sections.recentInternalHistory') }}</span>
            <strong>{{ activityCounts.comments }}</strong>
          </div>
          <div>
            <span>{{ t('staffRequestDetails.sections.emailComposerTitle') }}</span>
            <strong>{{ activityCounts.emails }}</strong>
          </div>
        </div>

        <div class="admin-quick-actions request-quick-actions-grid">
          <button v-if="activeUpdateBatch" type="button" class="admin-quick-action" @click="quickView = 'updateBatch'">
            <strong>{{ uiText('Client update batch', 'دفعة تحديث العميل') }}</strong>
            <span>{{ activeUpdateBatch.items?.length || 0 }} {{ uiText('items', 'عنصر') }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'attachments'">
            <strong>{{ uiText('All attachments', 'كل المرفقات') }}</strong>
            <span>{{ activityCounts.attachments }} {{ uiText('files', 'ملفات') }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'shareholders'">
            <strong>{{ t('staffRequestDetails.sections.shareholdersTitle') }}</strong>
            <span>{{ activityCounts.shareholders }} {{ uiText('records', 'سجلات') }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'answers'">
            <strong>{{ t('staffRequestDetails.sections.questionnaireAnswers') }}</strong>
            <span>{{ activityCounts.answers }} {{ uiText('answers', 'إجابات') }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'comments'">
            <strong>{{ t('staffRequestDetails.sections.recentInternalHistory') }}</strong>
            <span>{{ activityCounts.comments }} {{ uiText('notes', 'ملاحظات') }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'additionalDocuments'">
            <strong>{{ t('staffRequestDetails.sections.requestedAdditionalDocuments') }}</strong>
            <span>{{ activityCounts.additionalDocuments }} {{ uiText('requests', 'طلبات') }}</span>
          </button>
          <RouterLink class="admin-quick-action" :to="{ name: 'staff-request-emails', params: { id: requestId } }">
            <strong>{{ uiText('Sent email history', 'سجل الرسائل المرسلة') }}</strong>
            <span>{{ activityCounts.emails }} {{ uiText('emails', 'رسائل') }}</span>
          </RouterLink>
        </div>
      </article>

          <details v-if="false && activeUpdateBatch" class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>{{ uiText('Client update batch', 'دفعة تحديث العميل') }}</h2>
                <p>{{ uiText('Read-only view of the currently open client corrections and submitted items.', 'عرض للقراءة فقط للتصحيحات المفتوحة حاليًا التي طُلبت من العميل والعناصر المرسلة.') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="notes-box" v-if="activeUpdateBatch.reason_en || activeUpdateBatch.reason_ar">
                <span>{{ uiText('Reason', 'السبب') }}</span>
                <p>{{ locale === 'ar' ? (activeUpdateBatch.reason_ar || activeUpdateBatch.reason_en) : (activeUpdateBatch.reason_en || activeUpdateBatch.reason_ar) }}</p>
              </div>

              <div class="qa-list" v-if="activeUpdateBatch.items?.length">
                <div v-for="item in activeUpdateBatch.items" :key="item.id" class="panel-card slim-card" style="padding: 1rem; margin-bottom: 1rem;">
                  <div class="client-card-head">
                    <div>
                      <h3>{{ locale === 'ar' ? (item.label_ar || item.label_en || uiText('Requested update', 'التحديث المطلوب')) : (item.label_en || item.label_ar || uiText('Requested update', 'التحديث المطلوب')) }}</h3>
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
                <h2>{{ uiText('All attachments', 'كل المرفقات') }}</h2>
                <p>{{ uiText('View every request file in one place for faster review.', 'اعرض جميع ملفات الطلب في مكان واحد لمراجعة أسرع.') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div v-if="allAttachmentItems.length" class="file-list request-inline-stack">
                <div v-for="file in allAttachmentItems" :key="file.key" class="file-item">
                  <div>
                    <strong>{{ file.file_name }}</strong>
                    <span>{{ file.source_label }}<template v-if="file.uploaded_at"> · {{ readableDateTime(file.uploaded_at) }}</template></span>
                  </div>
                  <div class="approve-actions">
                    <button
                      type="button"
                      class="ghost-btn"
                      @click="openFilePreview(file.file_name, file.download_url, file.mime_type || undefined)"
                    >
                      {{ uiText('Preview', 'معاينة') }}
                    </button>
                    <a :href="file.download_url" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.download') }}</a>
                  </div>
                </div>
              </div>
              <p v-else class="empty-state">{{ uiText('No attachments are available yet for this request.', 'لا توجد مرفقات متاحة لهذا الطلب بعد.') }}</p>
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
                  <p>{{ item.is_uploaded || item.is_change_requested ? t('staffRequestDetails.states.latestFileLabel', { file: latestRequiredUpload(item)?.file_name || t('staffRequestDetails.states.uploadedFile') }) : t('staffRequestDetails.states.waitingForClientUpload') }}</p>
                  <div v-if="normalizedRequiredUploads(item).length" class="file-list request-inline-stack">
                    <div
                      v-for="upload in normalizedRequiredUploads(item)"
                      :key="`staff-required-upload-${item.document_upload_step_id}-${upload.id}`"
                      class="file-item"
                    >
                      <div>
                        <strong>{{ upload.file_name || t('staffRequestDetails.states.uploadedFile') }}</strong>
                        <span>
                          {{ uiText('Status', 'الحالة') }}: {{ requiredUploadStatusLabel(upload.status) }}
                          <template v-if="upload.uploaded_at"> · {{ readableDateTime(upload.uploaded_at) }}</template>
                        </span>
                      </div>
                      <div class="approve-actions">
                        <button
                          type="button"
                          class="ghost-btn"
                          @click="openFilePreview(upload.file_name, requiredDocumentDownloadUrl(upload.id), upload.mime_type || upload.file_extension)"
                        >
                          {{ uiText('Preview', 'معاينة') }}
                        </button>
                        <a :href="requiredDocumentDownloadUrl(upload.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.download') }}</a>
                      </div>
                    </div>
                  </div>
                  <div v-if="Number(item.uploads_count || normalizedRequiredUploads(item).length) > 1" class="approve-actions">
                    <a
                      :href="requiredDocumentStepBundleDownloadUrl(item.document_upload_step_id)"
                      target="_blank"
                      rel="noopener"
                      class="ghost-btn"
                    >
                      {{ uiText('Download all files', 'تنزيل جميع الملفات') }}
                    </a>
                  </div>
                  <p v-if="item.rejection_reason" class="form-help form-help--error">{{ t('staffRequestDetails.states.reasonLabel') }}: {{ item.rejection_reason }}</p>

                  <div class="field-stack">
                    <label class="client-form-group">
                      <span class="client-form-label">{{ uiText('Upload on behalf of client', 'رفع نيابةً عن العميل') }}</span>
                      <input
                        type="file"
                        class="admin-input"
                        @change="onRequiredDocumentFileChange(item.document_upload_step_id, $event)"
                      />
                    </label>
                    <div class="approve-actions">
                      <button
                        class="ghost-btn"
                        type="button"
                        :disabled="uploadingRequiredDocument[item.document_upload_step_id] || !selectedRequiredDocumentFiles[item.document_upload_step_id]"
                        @click="uploadRequiredDocumentForClient(item.document_upload_step_id)"
                      >
                        {{ uploadingRequiredDocument[item.document_upload_step_id] ? uiText('Uploading...', 'جارٍ الرفع...') : uiText('Upload document', 'رفع المستند') }}
                      </button>
                      <span v-if="requiredDocumentFileName(item.document_upload_step_id)" class="client-subtext">
                        {{ requiredDocumentFileName(item.document_upload_step_id) }}
                      </span>
                    </div>
                  </div>

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

          <details v-if="understudySectionVisible" class="admin-accordion-card" :open="understudySectionOpen" @toggle="syncUnderstudySectionState">
            <summary>
              <div>
                <h2>{{ uiText('Understudy package', 'حزمة الدراسة') }}</h2>
                <p>{{ uiText('Answer the study questions, save your understanding note, then submit the full package to admin.', 'أجب عن أسئلة الدراسة، ثم احفظ ملاحظتك، وبعدها أرسل الحزمة الكاملة إلى الإدارة.') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="notes-box" style="margin-bottom: 1rem;">
                <span>{{ uiText('Progress', 'التقدم') }}</span>
                <p>
                  {{ staffQuestionSummary?.answered_total || 0 }}/{{ staffQuestionSummary?.total || 0 }} {{ uiText('answered', 'تمت الإجابة') }} ·
                  {{ staffQuestionSummary?.pending_required_total || 0 }} {{ uiText('required questions still pending', 'أسئلة إلزامية ما زالت بانتظار الإجابة') }}
                </p>
              </div>

              <p v-if="understudyQuestionsCompleted && !understudyLocked" class="client-subtext" style="margin-bottom: 1rem;">
                {{ uiText('All required study questions are answered. You can still edit any answer until you submit the final study package to admin.', 'تمت الإجابة على جميع أسئلة الدراسة الإلزامية. ما زال بإمكانك تعديل أي إجابة حتى تقوم بإرسال الحزمة النهائية إلى الإدارة.') }}
              </p>

              <div v-if="requestItem?.understudy_submitted_at" class="notes-box" style="margin-bottom: 1rem;">
                <span>{{ uiText('Submission status', 'حالة الإرسال') }}</span>
                <p>
                  {{ readableUnderstudyStatus(requestItem?.understudy_status || 'draft') }}
                  <template v-if="requestItem?.understudy_submitted_by?.name"> · Submitted by {{ requestItem.understudy_submitted_by.name }}</template>
                  <template v-if="requestItem?.understudy_submitted_at"> · {{ readableDateTime(requestItem.understudy_submitted_at) }}</template>
                </p>
              </div>

              <div v-if="staffQuestions.length" class="qa-list">
                <article v-for="question in staffQuestions" :key="question.id" class="panel-card slim-card" style="padding: 1rem; margin-bottom: 1rem;">
                  <div class="client-card-head">
                    <div>
                      <h3>{{ studyQuestionTitle(question) }}</h3>
                      <p class="client-subtext">
                        <template v-if="question.is_required">{{ uiText('Required question', 'سؤال إلزامي') }}</template>
                        <template v-else>{{ uiText('Optional question', 'سؤال اختياري') }}</template>
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
                    :disabled="understudyQuestionInputsLocked"
                    :placeholder="studyQuestionPlaceholder(question) || 'Write your answer here'"
                    :value="studyAnswerTextValue(question.id)"
                    @input="updateStudyQuestionValue(question.id, ($event.target as HTMLInputElement).value)"
                  />

                  <textarea
                    v-else-if="studyQuestionType(question) === 'textarea'"
                    :value="studyAnswerTextValue(question.id)"
                    rows="4"
                    class="admin-textarea"
                    :disabled="understudyQuestionInputsLocked"
                    :placeholder="studyQuestionPlaceholder(question) || 'Write your answer here'"
                    @input="updateStudyQuestionValue(question.id, ($event.target as HTMLTextAreaElement).value)"
                  ></textarea>

                  <select
                    v-else-if="studyQuestionType(question) === 'select'"
                    class="admin-select"
                    :disabled="understudyQuestionInputsLocked"
                    :value="Array.isArray(studyAnswers[question.id]) ? '' : studyAnswerTextValue(question.id)"
                    @change="updateStudyQuestionValue(question.id, ($event.target as HTMLSelectElement).value)"
                  >
                    <option value="">{{ uiText('Choose an option', 'اختر خيارًا') }}</option>
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
                        :disabled="understudyQuestionInputsLocked"
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
                        :disabled="understudyQuestionInputsLocked"
                        @change="updateStudyCheckbox(question.id, option, ($event.target as HTMLInputElement).checked)"
                      />
                      <span>{{ option }}</span>
                    </label>
                  </div>

                  <input
                    v-else
                    type="text"
                    class="admin-input"
                    :disabled="understudyQuestionInputsLocked"
                    :placeholder="studyQuestionPlaceholder(question) || 'Write your answer here'"
                    :value="studyAnswerTextValue(question.id)"
                    @input="updateStudyQuestionValue(question.id, ($event.target as HTMLInputElement).value)"
                  />

                  <p v-if="studyQuestionHelpText(question)" class="client-subtext" style="margin-top: 0.5rem;">{{ studyQuestionHelpText(question) }}</p>

                  <div class="approve-actions" style="margin-top: 0.75rem;">
                    <button
                      type="button"
                      class="ghost-btn"
                      :disabled="understudyQuestionInputsLocked || savingStudyAnswer[question.id]"
                      @click="saveStudyQuestionAnswer(question)"
                    >
                      {{ savingStudyAnswer[question.id] ? 'Saving...' : 'Save answer' }}
                    </button>
                  </div>
                </article>
              </div>
              <p v-else class="empty-state">{{ uiText('No staff study questions are available for this request yet.', 'لا توجد أسئلة دراسة متاحة للموظف لهذا الطلب حتى الآن.') }}</p>

              <article class="panel-card slim-card" style="margin-top: 1rem;">
                <div class="panel-head"><h3>{{ uiText('What you understood', 'ما الذي فهمته') }}</h3></div>
                <textarea
                  v-model="understudyNote"
                  rows="5"
                  class="admin-textarea"
                  :disabled="understudyLocked || !understudyQuestionsCompleted"
                  :placeholder="understudyQuestionsCompleted ? uiText('Add a short study note for the admin', 'أضف ملاحظة دراسة قصيرة للإدارة') : uiText('Answer all study questions above first', 'أجب عن جميع أسئلة الدراسة أعلاه أولاً')"
                ></textarea>
                <p class="client-subtext" style="margin-top: 0.5rem;">
                  <template v-if="!understudyQuestionsCompleted">{{ uiText('You must answer all required study questions before writing your note.', 'يجب الإجابة على جميع أسئلة الدراسة الإلزامية قبل كتابة ملاحظتك.') }}</template>
                  <template v-else>{{ uiText('This note is internal between staff and admin. The client will only continue seeing Understudy.', 'هذه الملاحظة داخلية بين الموظف والإدارة. وسيستمر العميل في رؤية مرحلة الدراسة فقط.') }}</template>
                </p>
                <div class="approve-actions" style="margin-top: 0.75rem; gap: 0.75rem; flex-wrap: wrap;">
                  <button type="button" class="ghost-btn" :disabled="understudyLocked || savingUnderstudyDraftState || !understudyQuestionsCompleted" @click="saveStudyDraftNote">
                    {{ savingUnderstudyDraftState ? 'Saving...' : uiText('Save draft note', 'حفظ المسودة') }}
                  </button>
                  <button type="button" class="primary-btn" :disabled="!canSubmitUnderstudyPackage || submittingUnderstudyState || understudyLocked" @click="submitStudyToAdmin">
                    {{ submittingUnderstudyState ? 'Submitting...' : uiText('Submit study to admin', 'إرسال الدراسة إلى الإدارة') }}
                  </button>
                </div>
              </article>
            </div>
          </details>

          <details class="admin-accordion-card followup-workspace-card" open>
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.followUpTitle') }}</h2>
                <p>{{ t('staffRequestDetails.sections.followUpSubtitle') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="admin-inline-block-grid followup-workspace-grid">
                <article class="panel-card slim-card followup-card">
                  <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.addInternalComment') }}</h3></div>
                  <div class="field-block field-block--grow">
                    <span>{{ t('staffRequestDetails.form.visibility') }}</span>
                    <select v-model="commentVisibility" class="admin-select">
                      <option value="internal">{{ t('staffRequestDetails.form.internal') }}</option>
                      <option value="admin_only">{{ t('staffRequestDetails.form.adminOnly') }}</option>
                      <option value="client_visible">{{ t('staffRequestDetails.form.clientVisible') }}</option>
                    </select>
                  </div>
                  <textarea v-model="commentText" rows="5" class="admin-textarea" :placeholder="t('staffRequestDetails.placeholders.commentText')"></textarea>
                  <div class="approve-actions">
                    <button class="primary-btn" type="button" :disabled="savingComment || !commentText.trim()" @click="submitComment">
                      {{ savingComment ? t('staffRequestDetails.actions.saving') : t('staffRequestDetails.actions.saveComment') }}
                    </button>
                  </div>
                </article>

                <article class="panel-card slim-card followup-card">
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

              <article v-if="false" class="panel-card slim-card">
                <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.requestedAdditionalDocuments') }}</h3></div>
                <div v-if="requestItem.additional_documents?.length" class="timeline-list compact-list">
                  <div v-for="item in requestItem.additional_documents" :key="item.id" class="timeline-item">
                    <strong>{{ item.title }}</strong>
                    <p>{{ item.reason || t('staffRequestDetails.states.noReasonAdded') }}</p>
                    <span>{{ readableAdditionalDocumentStatus(item.status) }}<template v-if="item.file_name"> · {{ item.file_name }}</template></span>
                    <div v-if="item.file_name" class="approve-actions">
                      <button
                        type="button"
                        class="ghost-btn"
                        @click="openFilePreview(item.file_name, additionalDocumentDownloadUrl(item.id))"
                      >
                        Preview
                      </button>
                      <a :href="additionalDocumentDownloadUrl(item.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.downloadFile') }}</a>
                    </div>
                  </div>
                </div>
                <p v-else class="empty-state">{{ t('staffRequestDetails.states.noAdditionalDocumentsRequested') }}</p>
              </article>
            </div>
          </details>

          <details v-if="false && emailComposerVisible" class="admin-accordion-card">
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.emailComposerTitle') }}</h2>
                <p>{{ t('staffRequestDetails.sections.emailComposerSubtitle') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div v-if="!mailboxReady" class="notes-box" style="margin-bottom: 1rem;">
                <span>{{ t('staffRequestDetails.mailboxSetup.title') }}</span>
                <p>{{ uiText('The admin still needs to save and verify your mailbox before you can send request emails from this workspace.', 'لا تزال الإدارة بحاجة إلى حفظ بريدك والتحقق منه قبل أن تتمكن من إرسال رسائل الطلب من مساحة العمل هذه.') }}</p>
              </div>

              <div v-if="!hasEmailAssignments" class="notes-box" style="margin-bottom: 1rem;">
                <span>{{ uiText('Waiting for admin setup', 'بانتظار إعداد الإدارة') }}</span>
                <p>{{ uiText('The admin still needs to approve the bank-agent assignment phase before you can prepare a controlled email for this request.', 'لا تزال الإدارة بحاجة لاعتماد مرحلة تعيين البنك والوكيل قبل أن تتمكن من إعداد بريد مُتحكم به لهذا الطلب.') }}</p>
              </div>

              <div v-if="false" class="admin-inline-block-grid">
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
                      <option :value="null">{{ uiText('Select an assigned agent', 'اختر وكيلاً مُسندًا') }}</option>
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
                    <span>{{ uiText('Allowed request files', 'ملفات الطلب المسموح بها') }}</span>
                    <p v-if="selectedAgentOption">Choose the approved files that should be attached when sending to {{ selectedAgentOption?.name }}.</p>
                    <p v-else>{{ uiText('Select an assigned agent to see the exact files approved by the admin.', 'اختر وكيلاً مُسندًا لرؤية الملفات المعتمدة من الإدارة.') }}</p>
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
                            <a :href="document.download_url ?? undefined" target="_blank" rel="noopener" class="ghost-btn">{{ uiText('Preview file', 'معاينة الملف') }}</a>
                          </div>
                        </div>
                      </div>
                    </label>
                  </div>
                  <p v-else class="empty-state" style="margin-top: 0.75rem;">{{ uiText('No files have been assigned to the selected agent yet.', 'لم يتم تعيين ملفات للوكيل المحدد حتى الآن.') }}</p>

                  <div class="approve-actions" style="margin-top: 1rem;">
                    <button class="primary-btn" type="button" :disabled="sendingEmail || !canSendEmail" @click="sendEmailToAssignedAgent">
                      {{ sendingEmail ? uiText('Sending...', 'جارٍ الإرسال...') : uiText('Send email now', 'إرسال البريد الآن') }}
                    </button>
                  </div>
                </article>
              </div>

              <article class="panel-card slim-card staff-email-composer-card">
                <div class="panel-head staff-email-composer-head">
                  <div>
                    <h3>{{ t('staffRequestDetails.sections.emailBody') }}</h3>
                    <p class="client-subtext">{{ uiText('Set recipients first, then compose a complete request email with your selected attachments.', 'حدّد المستلمين أولاً ثم اكتب رسالة الطلب كاملة مع المرفقات المحددة.') }}</p>
                  </div>
                  <div class="staff-email-composer-actions">
                    <button type="button" class="ghost-btn" @click="openRecipientPicker">
                      <i class="fas fa-user-check"></i>
                      {{ t('staffRequestDetails.sections.recipients') }}
                    </button>
                    <button type="button" class="ghost-btn" :disabled="!canComposeEmail" @click="openAttachmentPicker">
                      <i class="fas fa-paperclip"></i>
                      Attachments ({{ selectedEmailAttachments.length }})
                    </button>
                  </div>
                </div>

                <div class="staff-email-recipient-summary" :class="{ 'is-ready': canComposeEmail }">
                  <p v-if="canComposeEmail">
                    Sending to:
                    <strong>{{ selectedAgentOption?.name || 'Selected agent' }}</strong>
                    <template v-if="selectedBankOption?.name"> - {{ selectedBankOption?.name }}</template>
                  </p>
                  <p v-else>{{ uiText('Select a recipient before entering your email content.', 'اختر مستلمًا قبل إدخال محتوى البريد.') }}</p>
                </div>

                <div class="staff-email-input-stack">
                  <label class="client-form-group">
                    <span class="client-form-label">{{ t('staffRequestDetails.placeholders.emailSubject') }}</span>
                    <input
                      v-model="emailSubject"
                      type="text"
                      class="admin-input"
                      :placeholder="t('staffRequestDetails.placeholders.emailSubject')"
                      :disabled="!canComposeEmail"
                    />
                  </label>

                  <div class="staff-email-editor-shell" :class="{ 'is-disabled': !canComposeEmail, 'is-focused': emailEditorFocused }">
                    <div class="staff-email-editor-toolbar">
                      <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('bold')"><strong>B</strong></button>
                      <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('italic')"><em>I</em></button>
                      <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('insertUnorderedList')"><i class="fas fa-list-ul"></i></button>
                      <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('insertOrderedList')"><i class="fas fa-list-ol"></i></button>
                      <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('removeFormat')"><i class="fas fa-eraser"></i></button>
                    </div>

                    <div
                      ref="emailEditorRef"
                      class="staff-email-editor-surface"
                      :contenteditable="canComposeEmail ? 'true' : 'false'"
                      :data-placeholder="t('staffRequestDetails.placeholders.emailBody')"
                      @focus="emailEditorFocused = true"
                      @blur="emailEditorFocused = false; syncEmailBodyFromEditor()"
                      @input="syncEmailBodyFromEditor"
                    ></div>
                  </div>

                  <div class="staff-email-attachments-meta">
                    <span class="count-pill">{{ selectedEmailAttachments.length }} selected</span>
                    <p v-if="selectedEmailAttachments.length" class="client-subtext">
                      {{ selectedEmailAttachments.map((document) => document.label).join(', ') }}
                    </p>
                    <p v-else class="client-subtext">{{ uiText('No attachments selected yet.', 'لم يتم اختيار أي مرفقات بعد.') }}</p>
                  </div>
                </div>

                <div class="approve-actions staff-email-send-row">
                  <button class="primary-btn" type="button" :disabled="sendingEmail || !canSendEmail" @click="sendEmailToAssignedAgent">
                    {{ sendingEmail ? uiText('Sending...', 'جارٍ الإرسال...') : uiText('Send email now', 'إرسال البريد الآن') }}
                  </button>
                </div>
              </article>

              <article v-if="false" class="panel-card slim-card" style="margin-top: 1rem;">
                <div class="panel-head"><h3>{{ uiText('Sent email history', 'سجل الرسائل المرسلة') }}</h3></div>
                <div v-if="requestItem.emails?.length" class="timeline-list compact-list">
                  <div v-for="email in requestItem.emails" :key="email.id" class="timeline-item">
                    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;">
                      <div>
                        <strong>{{ email.subject }}</strong>
                        <p>{{ uiText('From', 'من') }}: {{ email.sender?.name || uiText('System', 'النظام') }} · {{ email.from_email || email.sender?.email || '—' }}</p>
                        <p>{{ uiText('To', 'إلى') }}: {{ email.agents?.map((agent: any) => agent.name).join(', ') || '—' }}</p>
                        <p v-if="email.body">{{ email.body }}</p>
                        <span>{{ email.attachments?.length || 0 }} {{ uiText('attachments', 'مرفقات') }} · {{ readableDateTime(email.sent_at || email.created_at) }}</span>
                      </div>
                      <span :class="emailStatusClass(email.delivery_status)">{{ readableEmailDeliveryStatus(email.delivery_status) }}</span>
                    </div>
                  </div>
                </div>
                <p v-else class="empty-state">{{ uiText('No outbound emails have been sent for this request yet.', 'لم يتم إرسال أي رسائل خارجية لهذا الطلب بعد.') }}</p>
              </article>
            </div>
          </details>

          <AdminQuickViewModal
            v-if="false"
            :model-value="recipientPickerOpen"
            @update:model-value="(value) => { recipientPickerOpen = value }"
            :title="t('staffRequestDetails.sections.recipients')"
            :subtitle="uiText('Pick bank and agent before composing.', 'اختر البنك والوكيل قبل بدء كتابة البريد.')"
            wide
          >
            <div class="staff-picker-grid">
              <label class="client-form-group">
                <span class="client-form-label">{{ t('staffRequestDetails.form.bank') }}</span>
                <select v-model="selectedBankId" class="admin-select">
                  <option :value="null">{{ t('staffRequestDetails.form.allBanks') }}</option>
                  <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                </select>
              </label>

              <label class="client-form-group">
                <span class="client-form-label">{{ t('staffRequestDetails.form.agents') }}</span>
                <select v-model="selectedAgentId" class="admin-select">
                  <option :value="null">{{ uiText('Select an assigned agent', 'اختر وكيلاً مُسندًا') }}</option>
                  <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                    {{ agent.name }}<template v-if="agent.bank_name"> - {{ agent.bank_name }}</template>
                  </option>
                </select>
              </label>

              <p class="client-subtext">{{ uiText('Only admin-approved agents appear in this list.', 'تظهر في هذه القائمة فقط الوكلاء المعتمدون من الإدارة.') }}</p>
              <div class="approve-actions">
                <button type="button" class="primary-btn" :disabled="!selectedAgentId" @click="recipientPickerOpen = false">{{ uiText('Use this recipient', 'استخدام هذا المستلم') }}</button>
              </div>
            </div>
          </AdminQuickViewModal>

          <AdminQuickViewModal
            v-if="false"
            :model-value="attachmentPickerOpen"
            @update:model-value="(value) => { attachmentPickerOpen = value }"
            :title="uiText('Choose attachments', 'اختيار المرفقات')"
            :subtitle="uiText('Select approved files to include with this email.', 'اختر الملفات المعتمدة لإرفاقها مع هذا البريد.')"
            wide
          >
            <div v-if="allowedEmailDocuments.length" class="timeline-list compact-list">
              <label v-for="document in allowedEmailDocuments" :key="document.key" class="timeline-item staff-picker-item">
                <input
                  type="checkbox"
                  :checked="isEmailDocumentChecked(document.key)"
                  :disabled="!canComposeEmail"
                  @change="toggleEmailDocument(document.key, ($event.target as HTMLInputElement).checked)"
                >
                <div>
                  <strong>{{ document.label }}</strong>
                  <p>{{ document.group_label || 'Request file' }}</p>
                  <span>{{ document.file_name }}</span>
                  <div v-if="document.download_url" class="approve-actions staff-picker-preview">
                    <a :href="document.download_url ?? undefined" target="_blank" rel="noopener" class="ghost-btn">{{ uiText('Preview file', 'معاينة الملف') }}</a>
                  </div>
                </div>
              </label>
            </div>
            <p v-else class="empty-state">{{ uiText('No files are currently assigned to this agent.', 'لا توجد ملفات مُسندة لهذا الوكيل حالياً.') }}</p>
            <div class="approve-actions" style="margin-top: 0.85rem;">
              <button type="button" class="primary-btn" @click="attachmentPickerOpen = false">{{ uiText('Done', 'تم') }}</button>
            </div>
          </AdminQuickViewModal>
      </div>
    </template>

    <template #after>
      <div v-if="false" class="request-side-stack">
          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ uiText('Workspace snapshot', 'ملخص مساحة العمل') }}</h2></div>
            <div class="catalog-mini-stats request-kpi-grid request-kpi-grid--two">
              <div><span>{{ uiText('Pending docs', 'المستندات المعلقة') }}</span><strong>{{ pendingRequiredCount }}</strong></div>
              <div><span>{{ uiText('Study ready', 'جاهزية الدراسة') }}</span><strong>{{ staffQuestionSummary?.all_required_answered ? uiText('Yes', 'نعم') : uiText('No', 'لا') }}</strong></div>
              <div><span>{{ t('staffRequestDetails.sections.recentInternalHistory') }}</span><strong>{{ activityCounts.comments }}</strong></div>
              <div><span>{{ uiText('Sent emails', 'الرسائل المرسلة') }}</span><strong>{{ activityCounts.emails }}</strong></div>
            </div>
          </article>

          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ uiText('Read-only details', 'تفاصيل للعرض') }}</h2></div>
            <p class="subtext">{{ uiText('Use the quick views to inspect uploads, shareholders, answers, comments, and email history without stretching the page.', 'استخدم العروض السريعة لمراجعة الملفات والمساهمين والإجابات والتعليقات وسجل البريد دون إطالة الصفحة.') }}</p>
            <div class="approve-actions request-footer-actions">
              <button type="button" class="ghost-btn" @click="quickView = 'answers'">{{ t('staffRequestDetails.sections.questionnaireAnswers') }}</button>
              <button type="button" class="ghost-btn" @click="quickView = 'comments'">{{ t('staffRequestDetails.sections.recentInternalHistory') }}</button>
              <RouterLink class="ghost-btn" :to="{ name: 'staff-request-emails', params: { id: requestId } }">{{ uiText('Sent email history', 'سجل الرسائل المرسلة') }}</RouterLink>
            </div>
          </article>
      </div>

      <AdminQuickViewModal
        :model-value="quickView !== null"
        @update:model-value="(value) => { if (!value) quickView = null }"
        :title="quickViewTitle"
        :subtitle="uiText('Reference information for this request.', 'معلومات مرجعية لهذا الطلب.')"
        wide
      >
        <div v-if="quickView === 'updateBatch'" class="qa-list">
          <div v-if="activeUpdateBatch?.reason_en || activeUpdateBatch?.reason_ar" class="notes-box">
            <span>{{ uiText('Reason', 'السبب') }}</span>
            <p>{{ locale === 'ar' ? (activeUpdateBatch?.reason_ar || activeUpdateBatch?.reason_en) : (activeUpdateBatch?.reason_en || activeUpdateBatch?.reason_ar) }}</p>
          </div>
          <div v-if="activeUpdateBatch?.items?.length" v-for="item in activeUpdateBatch.items" :key="item.id" class="panel-card slim-card request-draft-item">
            <div class="client-card-head request-card-head-compact">
              <div>
                <h3>{{ locale === 'ar' ? (item.label_ar || item.label_en || uiText('Requested update', 'التحديث المطلوب')) : (item.label_en || item.label_ar || uiText('Requested update', 'التحديث المطلوب')) }}</h3>
                <p class="client-subtext">{{ locale === 'ar' ? (item.instruction_ar || item.instruction_en || '—') : (item.instruction_en || item.instruction_ar || '—') }}</p>
              </div>
              <span :class="updateStatusClass(item.status)">{{ updateStatusLabel(item.status) }}</span>
            </div>
          </div>
          <p v-else class="empty-state">{{ uiText('No active client update batch.', 'لا توجد دفعة تحديث عميل نشطة.') }}</p>
        </div>

        <div v-else-if="quickView === 'attachments'" class="file-list">
          <template v-if="allAttachmentItems.length">
            <div v-if="false" class="approve-actions" style="margin-bottom: 0.7rem;">
              <a :href="attachmentBundleDownloadUrl()" target="_blank" rel="noopener" class="ghost-btn">
                {{ uiText('Download all files', 'تنزيل جميع الملفات') }}
              </a>
            </div>
            <div v-for="file in allAttachmentItems" :key="file.key" class="file-item">
              <div>
                <strong>{{ file.file_name }}</strong>
                <span>{{ file.source_label }}<template v-if="file.uploaded_at"> · {{ readableDateTime(file.uploaded_at) }}</template></span>
              </div>
              <div class="approve-actions">
                <button
                  type="button"
                  class="ghost-btn"
                  @click="openFilePreview(file.file_name, file.download_url, file.mime_type || undefined)"
                >
                  {{ uiText('Preview', 'معاينة') }}
                </button>
                <a :href="file.download_url" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.download') }}</a>
              </div>
            </div>
          </template>
          <p v-else class="empty-state">{{ uiText('No attachments are available yet for this request.', 'لا توجد مرفقات متاحة لهذا الطلب بعد.') }}</p>
        </div>

        <div v-else-if="quickView === 'shareholders'" class="file-list">
          <div v-if="requestItem.shareholders?.length" v-for="shareholder in requestItem.shareholders" :key="shareholder.id" class="file-item">
            <div>
              <strong>{{ shareholder.shareholder_name }}</strong>
              <span v-if="shareholder.phone_number">{{ [shareholder.phone_country_code, shareholder.phone_number].filter(Boolean).join(' ') }}</span>
              <span v-if="shareholder.id_number">{{ t('staffRequestDetails.states.idNumberLabel', { id: shareholder.id_number }) }}</span>
              <span>{{ shareholder.id_file_name }}</span>
            </div>
            <div class="approve-actions">
              <button
                type="button"
                class="ghost-btn"
                @click="openFilePreview(shareholder.id_file_name, shareholderIdDownloadUrl(shareholder.id))"
              >
                Preview
              </button>
              <a :href="shareholderIdDownloadUrl(shareholder.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.downloadIdFile') }}</a>
            </div>
          </div>
          <p v-else class="empty-state">{{ t('staffRequestDetails.states.noShareholdersRecorded') }}</p>
        </div>

        <RequestAnswersList
          v-else-if="quickView === 'answers'"
          :answers="requestItem.answers || []"
          :empty-text="t('staffRequestDetails.states.noAnswersRecorded')"
          :question-fallback="t('staffRequestDetails.states.questionFallback')"
          :format-answer="answerText"
        />

        <div v-else-if="quickView === 'comments'" class="timeline-list compact-list">
          <div v-if="requestItem.comments?.length" v-for="comment in requestItem.comments" :key="comment.id" class="timeline-item">
            <strong>{{ comment.user?.name || t('staffRequestDetails.states.system') }}</strong>
            <p>{{ comment.comment_text }}</p>
            <span>{{ readableDateTime(comment.created_at) }} · {{ readableCommentVisibility(comment.visibility) }}</span>
          </div>
          <p v-else class="empty-state">{{ t('staffRequestDetails.states.noInternalComments') }}</p>
        </div>

        <div v-else-if="quickView === 'additionalDocuments'" class="timeline-list compact-list">
          <div v-if="requestItem.additional_documents?.length" v-for="item in requestItem.additional_documents" :key="item.id" class="timeline-item">
            <strong>{{ item.title }}</strong>
            <p>{{ item.reason || t('staffRequestDetails.states.noReasonAdded') }}</p>
            <span>{{ readableAdditionalDocumentStatus(item.status) }}<template v-if="item.file_name"> · {{ item.file_name }}</template></span>
            <div v-if="item.file_name" class="approve-actions">
              <button
                type="button"
                class="ghost-btn"
                @click="openFilePreview(item.file_name, additionalDocumentDownloadUrl(item.id))"
              >
                Preview
              </button>
              <a :href="additionalDocumentDownloadUrl(item.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.downloadFile') }}</a>
            </div>
          </div>
          <p v-else class="empty-state">{{ t('staffRequestDetails.states.noAdditionalDocumentsRequested') }}</p>
        </div>

        <div v-else class="timeline-list compact-list">
          <div v-if="requestItem.emails?.length" v-for="email in requestItem.emails" :key="email.id" class="timeline-item">
            <div class="request-modal-meta">
              <div>
                <strong>{{ email.subject }}</strong>
                <p>{{ uiText('From', 'من') }}: {{ email.sender?.name || uiText('System', 'النظام') }} · {{ email.from_email || email.sender?.email || '—' }}</p>
                <p>{{ uiText('To', 'إلى') }}: {{ email.agents?.map((agent: any) => agent.name).join(', ') || '—' }}</p>
                <p v-if="email.body" class="request-prewrap-text">{{ email.body }}</p>
                <span>{{ email.attachments?.length || 0 }} {{ uiText('attachments', 'مرفقات') }} · {{ readableDateTime(email.sent_at || email.created_at) }}</span>
              </div>
              <span :class="emailStatusClass(email.delivery_status)">{{ readableEmailDeliveryStatus(email.delivery_status) }}</span>
            </div>
          </div>
          <p v-if="!requestItem.emails?.length" class="empty-state">{{ uiText('No outbound emails have been sent for this request yet.', 'لم يتم إرسال أي رسائل خارجية لهذا الطلب بعد.') }}</p>
        </div>
      </AdminQuickViewModal>
    </template>

    <AppFilePreviewModal
      :model-value="filePreviewOpen"
      @update:model-value="(value) => { filePreviewOpen = value }"
      :title="uiText('File preview', 'معاينة الملف')"
      :file-name="filePreviewName"
      :mime-type="filePreviewMime"
      :preview-url="filePreviewUrl"
      :download-url="fileDownloadUrl"
    />
  </RequestWorkspaceShell>
</template>

<style scoped>
.followup-workspace-grid {
  gap: 1.1rem;
}

.followup-card {
  display: grid;
  gap: 0.85rem;
}

.staff-email-composer-card {
  display: grid;
  gap: 1rem;
}

.staff-email-composer-head {
  align-items: flex-start;
}

.staff-email-composer-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.staff-email-recipient-summary {
  padding: 0.85rem 1rem;
  border-radius: 14px;
  border: 1px dashed rgba(148, 163, 184, 0.35);
  background: rgba(248, 250, 252, 0.72);
}

.staff-email-recipient-summary.is-ready {
  border-style: solid;
  border-color: rgba(16, 185, 129, 0.28);
  background: rgba(236, 253, 245, 0.8);
}

.staff-email-recipient-summary p {
  margin: 0;
}

.staff-email-input-stack {
  display: grid;
  gap: 0.85rem;
}

.staff-email-editor-shell {
  border: 1px solid rgba(148, 163, 184, 0.3);
  border-radius: 16px;
  overflow: hidden;
  background: #ffffff;
}

.staff-email-editor-shell.is-focused {
  border-color: rgba(79, 70, 229, 0.45);
  box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
}

.staff-email-editor-shell.is-disabled {
  opacity: 0.78;
  background: rgba(248, 250, 252, 0.95);
}

.staff-email-editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding: 0.6rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.2);
  background: rgba(248, 250, 252, 0.9);
}

.staff-email-editor-surface {
  min-height: 260px;
  padding: 1rem;
  outline: none;
  line-height: 1.6;
  color: var(--admin-text);
}

.staff-email-editor-surface:empty::before {
  content: attr(data-placeholder);
  color: var(--admin-text-light);
}

.staff-email-attachments-meta {
  display: grid;
  gap: 0.45rem;
}

.staff-email-send-row {
  margin-top: 0.4rem;
}

.staff-picker-grid {
  display: grid;
  gap: 0.9rem;
}

.staff-picker-item {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: start;
  gap: 0.8rem;
  cursor: pointer;
}

.staff-picker-item > input {
  margin-top: 0.2rem;
  width: 18px;
  height: 18px;
  accent-color: var(--admin-primary);
}

.staff-picker-preview {
  margin-top: 0.45rem;
}

@media (max-width: 768px) {
  .staff-email-editor-surface {
    min-height: 200px;
  }

  .staff-email-composer-actions {
    width: 100%;
  }

  .staff-email-composer-actions .ghost-btn {
    width: 100%;
  }
}
</style>
