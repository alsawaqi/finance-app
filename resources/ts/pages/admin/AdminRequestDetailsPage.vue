<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  addAdminRequestComment,
  adminAdditionalDocumentDownloadUrl,
  adminContractDownloadUrl,
  adminRequestAttachmentsBundleDownloadUrl,
  adminRequestEmailAttachmentDownloadUrl,
  adminRequiredDocumentDownloadUrl,
  adminRequiredDocumentStepBundleDownloadUrl,
  adminRequestAttachmentDownloadUrl,
  adminRequestShareholderIdDownloadUrl,
  approveAdminRequest,
  cancelAdminUpdateBatch,
  createAdminUpdateBatch,
  finalizeAdminRequest,
  getAdminRequestAgentAssignmentOptions,
  getAdminRequestDetails,
  patchAdminRequestWorkflowStage,
  rejectAdminRequest,
  reviewAdminStaffQuestion,
  reviewAdminUpdateItem,
  reviewAdminUnderstudy,
  storeAdminRequestAgentAssignments,
  type AdminUpdateBatchDraftItem,
} from '@/services/adminRequests'
import {
  applicantTypeLabel,
  intakeFullName,
  intakeRequestedAmount,
} from '@/utils/requestIntake'
import AppFilePreviewModal from '@/components/AppFilePreviewModal.vue'
import { buildPreviewUrl } from '@/utils/filePreview'
import AdminAgentAccessPanel from './inc/AdminAgentAccessPanel.vue'
import AdminQuickViewModal from './inc/AdminQuickViewModal.vue'
import RequestAnswersList from './inc/RequestAnswersList.vue'
import RequestCoreDetailsCard from './inc/RequestCoreDetailsCard.vue'
import RequestSummaryStatGrid from './inc/RequestSummaryStatGrid.vue'
import RequestWorkspaceShell from './inc/RequestWorkspaceShell.vue'
import { getManualWorkflowStageOptions, getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'
import { buildTimelineRows, formatTimelineDate } from '@/utils/requestTimeline'
import {
  formatAdditionalDocumentStatus,
  formatEmailDeliveryStatus,
  formatRequestStatus,
  formatUpdateBatchStatus,
  formatUnderstudyStatus,
} from '@/utils/requestStatus'
import { formatDateTime } from '@/utils/dateTime'

const route = useRoute()
const router = useRouter()
const requestItem = ref<any | null>(null)
const requiredDocuments = ref<any[]>([])
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const approving = ref(false)
const approvalNotes = ref('')
const finalizingApproval = ref(false)
const finalApprovalNotes = ref('')
const finalApprovalAttachments = ref<File[]>([])
const rejectingRequest = ref(false)
const rejectReason = ref('')
const reviewingUnderstudy = ref(false)
const understudyReviewNote = ref('')
const creatingBatch = ref(false)
const cancellingBatch = ref(false)
const savingComment = ref(false)
const commentText = ref('')
const commentVisibility = ref<'internal' | 'admin_only' | 'client_visible'>('internal')
const reviewingUpdateItems = ref<Record<number, boolean>>({})
const batchReasonEn = ref('')
const batchReasonAr = ref('')
const updateDraftItems = ref<any[]>([])
const reviewNotes = ref<Record<number, string>>({})
const reviewingStaffQuestion = ref<Record<number, boolean>>({})
const staffQuestionReviewNotes = ref<Record<number, string>>({})
const quickView = ref<
  | 'requiredDocuments'
  | 'additionalDocuments'
  | 'answers'
  | 'attachments'
  | 'shareholders'
  | 'assignments'
  | 'comments'
  | 'emails'
  | 'timeline'
  | null
>(null)
const actionDialog = ref<'comment' | 'reject' | 'approve' | 'finalApprove' | 'updateBatch' | 'agentAccess' | null>(null)
const staffQuestionSummary = ref<any | null>(null)
const emailBankOptions = ref<any[]>([])
const emailAgentOptions = ref<any[]>([])
const availableEmailDocuments = ref<any[]>([])
const selectedAssignmentBankId = ref<number | null>(null)
const selectedAgentIds = ref<number[]>([])
const selectedAgentDocumentKeys = ref<Record<number, string[]>>({})
const savingAgentAssignments = ref(false)
const agentAssignmentReviewNote = ref('')
const workflowStageDraft = ref('')
const savingWorkflowStage = ref(false)
const { t, locale } = useI18n()

const requestId = computed(() => route.params.id as string)
const filePreviewOpen = ref(false)
const filePreviewName = ref('')
const filePreviewMime = ref('')
const filePreviewUrl = ref('')
const fileDownloadUrl = ref('')

function openFilePreview(fileName: string | null | undefined, downloadUrl: string, mimeType?: string | null) {
  const targetUrl = String(downloadUrl || '').trim()
  if (!targetUrl) return
  filePreviewName.value = String(fileName || t('adminRequestDetails.states.emptyValue'))
  filePreviewMime.value = String(mimeType || '')
  fileDownloadUrl.value = targetUrl
  filePreviewUrl.value = buildPreviewUrl(targetUrl)
  filePreviewOpen.value = true
}

function localizedModelValue(entity: any, base: string, fallback = t('adminRequestDetails.states.emptyValue')) {
  const ar = entity?.[`${base}_ar`]
  const en = entity?.[`${base}_en`]
  return locale.value === 'ar' ? (ar || en || fallback) : (en || ar || fallback)
}

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

function rawWorkflowStage(stage: unknown): string {
  if (stage == null || stage === '') return ''
  if (typeof stage === 'string') return stage
  if (typeof stage === 'object' && stage !== null && 'value' in (stage as object)) {
    return String((stage as { value: string }).value ?? '')
  }
  return String(stage)
}

const workflowStageDirty = computed(() => {
  const current = rawWorkflowStage(requestItem.value?.workflow_stage)
  return workflowStageDraft.value !== '' && workflowStageDraft.value !== current
})

const workflowStageSelectOptions = computed(() => {
  const current = rawWorkflowStage(requestItem.value?.workflow_stage)
  return getManualWorkflowStageOptions(current)
})

watch(
  () => requestItem.value?.workflow_stage,
  (stage) => {
    const v = rawWorkflowStage(stage)
    if (v) workflowStageDraft.value = v
  },
  { immediate: true },
)

async function applyWorkflowStage() {
  if (!requestItem.value || !workflowStageDirty.value) return

  savingWorkflowStage.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await patchAdminRequestWorkflowStage(requestItem.value.id, {
      workflow_stage: workflowStageDraft.value,
    })
    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    successMessage.value = data.message || t('adminRequestDetails.messages.workflowStageUpdated')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.workflowStageUpdateFailed')
  } finally {
    savingWorkflowStage.value = false
  }
}

const summaryStatItems = computed(() => [
  {
    label: t('adminRequestDetails.summary.status'),
    value: formatRequestStatus(requestItem.value?.status, locale, t('adminRequestDetails.states.emptyValue')),
    hint: t('adminRequestDetails.summary.currentBusinessState'),
  },
  {
    label: t('adminRequestDetails.summary.stage'),
    value: stageMeta(requestItem.value?.workflow_stage).label,
    hint: t('adminRequestDetails.summary.operationalStage'),
  },
  {
    label: t('adminRequestDetails.summary.client'),
    value: intakeFullName(requestItem.value?.intake_details_json, requestItem.value?.client?.name || t('adminRequestDetails.states.clientFallback')),
    hint: requestItem.value?.client?.email || t('adminRequestDetails.states.noEmailSaved'),
  },
  {
    label: t('adminRequestDetails.summary.companyName'),
    value: requestItem.value?.company_name || requestItem.value?.intake_details_json?.company_name || t('adminRequestDetails.states.emptyValue'),
    hint: applicantTypeLabel(requestItem.value?.applicant_type, locale, t('adminRequestDetails.states.emptyValue')),
  },
  {
    label: t('adminRequestDetails.summary.requestedAmount'),
    value: intakeRequestedAmount(requestItem.value?.intake_details_json, t('adminRequestDetails.states.emptyValue'), true),
    hint: localizedModelValue(requestItem.value?.finance_request_type, 'name'),
  },
])
const activityCounts = computed(() => ({
  comments: requestItem.value?.comments?.length ?? 0,
  timeline: requestItem.value?.timeline?.length ?? 0,
  emails: requestItem.value?.emails?.length ?? 0,
  assignments: requestItem.value?.assignments?.length ?? 0,
  answers: requestItem.value?.answers?.length ?? 0,
  attachments: requestItem.value?.attachments?.length ?? 0,
  requiredDocuments: requiredDocuments.value?.length ?? 0,
  additionalDocuments: requestItem.value?.additional_documents?.length ?? 0,
  shareholders: requestItem.value?.shareholders?.length ?? 0,
  updateBatches: requestItem.value?.update_batches?.length ?? 0,
}))

const timelineRows = computed(() => buildTimelineRows(requestItem.value?.timeline, locale.value))
const recentTimelineRows = computed(() => timelineRows.value.slice(-3).reverse())

function timelineDateLabel(value: unknown) {
  return formatTimelineDate(value, locale.value)
}

function inlineText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

const quickViewTitle = computed(() => {
  switch (quickView.value) {
    case 'requiredDocuments':
      return t('adminRequestDetails.requiredDocuments.title')
    case 'additionalDocuments':
      return t('adminRequestDetails.additionalDocuments.title')
    case 'answers':
      return t('adminRequestDetails.modal.questionnaireAnswers')
    case 'attachments':
      return t('adminRequestDetails.modal.uploadedFiles')
    case 'shareholders':
      return t('adminRequestDetails.modal.shareholders')
    case 'assignments':
      return t('adminRequestDetails.modal.assignedStaff')
    case 'comments':
      return t('adminRequestDetails.modal.internalComments')
    case 'emails':
      return t('adminRequestDetails.emailActivity.title')
    default:
      return t('adminRequestDetails.modal.timeline')
  }
})

const actionDialogTitle = computed(() => {
  switch (actionDialog.value) {
    case 'comment':
      return uiText('Follow-up comments', 'تعليقات المتابعة')
    case 'reject':
      return t('adminRequestDetails.rejectRequest.title')
    case 'approve':
      return t('adminRequestDetails.sections.approveRequest')
    case 'finalApprove':
      return t('adminRequestDetails.sections.finalApproveRequest')
    case 'updateBatch':
      return t('adminRequestDetails.updateBatch.title')
    case 'agentAccess':
      return t('adminRequestDetails.agentAssignments.manageAccess')
    default:
      return uiText('Request action', 'إجراء الطلب')
  }
})

const intakeFieldOptions = computed(() => [
  { value: 'requested_amount', label: t('adminRequestDetails.updateBatch.fields.requestedAmount') },
  { value: 'company_name', label: t('adminRequestDetails.updateBatch.fields.companyName') },
  { value: 'company_cr_number', label: t('adminRequestDetails.updateBatch.fields.companyCrNumber') },
  { value: 'email', label: t('adminRequestDetails.updateBatch.fields.email') },
  { value: 'phone_country_code', label: t('adminRequestDetails.updateBatch.fields.phoneCountryCode') },
  { value: 'phone_number', label: t('adminRequestDetails.updateBatch.fields.phoneNumber') },
  { value: 'unified_number', label: t('adminRequestDetails.updateBatch.fields.unifiedNumber') },
  { value: 'national_address_number', label: t('adminRequestDetails.updateBatch.fields.nationalAddressNumber') },
  { value: 'address', label: t('adminRequestDetails.updateBatch.fields.address') },
  { value: 'notes', label: t('adminRequestDetails.updateBatch.fields.notes') },
  { value: 'finance_request_type_id', label: t('adminRequestDetails.updateBatch.fields.financeRequestType') },
])

const attachmentFieldOptions = computed(() => [
  { value: 'national_address_attachment', label: t('adminRequestDetails.updateBatch.fields.nationalAddressAttachment') },
  { value: 'company_cr', label: t('adminRequestDetails.updateBatch.fields.companyCrAttachment') },
  { value: 'initial_submission', label: t('adminRequestDetails.updateBatch.fields.initialSubmissionAttachment') },
])

const questionOptions = computed(() => {
  const answers = requestItem.value?.answers ?? []
  const seen = new Set<number>()

  return answers
    .map((answer: any) => answer.question)
    .filter((question: any) => question?.id)
    .filter((question: any) => {
      if (seen.has(question.id)) return false
      seen.add(question.id)
      return true
    })
})

const activeOpenBatch = computed(() =>
  (requestItem.value?.update_batches ?? []).find((batch: any) => ['open', 'partially_completed'].includes(String(batch.status || ''))),
)

const staffQuestions = computed(() => {
  const rows = Array.isArray(requestItem.value?.staff_questions) ? requestItem.value.staff_questions : []
  return [...rows].sort((a: any, b: any) => {
    const aOrder = Number(a?.template?.sort_order ?? 9999)
    const bOrder = Number(b?.template?.sort_order ?? 9999)
    if (aOrder !== bOrder) return aOrder - bOrder
    return Number(a?.id ?? 0) - Number(b?.id ?? 0)
  })
})

const understudyStage = computed(() => String(requestItem.value?.workflow_stage || '').toLowerCase())

const understudyVisible = computed(() =>
  ['understudy', 'awaiting_staff_answers', 'awaiting_understudy_review'].includes(understudyStage.value),
)

const understudyReadyForReview = computed(() =>
  ['submitted'].includes(String(requestItem.value?.understudy_status || '').toLowerCase())
  || understudyStage.value === 'awaiting_understudy_review',
)

const understudyApproveReady = computed(() =>
  understudyReadyForReview.value && Boolean(staffQuestionSummary.value?.can_advance_from_understudy),
)

const activeAgentAssignments = computed(() =>
  Array.isArray(requestItem.value?.agent_assignments)
    ? requestItem.value.agent_assignments.filter((item: any) => item?.is_active !== false)
    : [],
)

const agentAssignmentVisible = computed(() =>
  ['awaiting_agent_assignment', 'processing'].includes(understudyStage.value)
  || activeAgentAssignments.value.length > 0,
)

const filteredAssignableAgents = computed(() => {
  const rows = emailAgentOptions.value ?? []
  if (!selectedAssignmentBankId.value) return rows
  return rows.filter((agent: any) => Number(agent.bank_id ?? 0) === Number(selectedAssignmentBankId.value))
})

const selectedAssignableAgents = computed(() =>
  (emailAgentOptions.value ?? []).filter((agent: any) => selectedAgentIds.value.includes(Number(agent.id))),
)

function assignmentDocumentKeysFromAssignment(assignment: any) {
  return Array.isArray(assignment?.allowed_documents)
    ? assignment.allowed_documents.map((document: any) => String(document.document_key || document.key)).filter(Boolean)
    : []
}

function agentAssignmentSignature(rows: Array<{ agent_id: number; document_keys: string[] }>) {
  return JSON.stringify(
    rows
      .map((row) => ({
        agent_id: Number(row.agent_id),
        document_keys: [...new Set((row.document_keys ?? []).map((key) => String(key)).filter(Boolean))].sort(),
      }))
      .filter((row) => row.agent_id > 0)
      .sort((a, b) => a.agent_id - b.agent_id),
  )
}

const activeAgentAssignmentSignature = computed(() =>
  agentAssignmentSignature(activeAgentAssignments.value.map((assignment: any) => ({
    agent_id: Number(assignment?.agent_id ?? assignment?.agent?.id ?? 0),
    document_keys: assignmentDocumentKeysFromAssignment(assignment),
  }))),
)

const draftAgentAssignmentSignature = computed(() =>
  agentAssignmentSignature(selectedAgentIds.value.map((agentId) => ({
    agent_id: Number(agentId),
    document_keys: selectedAgentDocumentKeys.value[Number(agentId)] ?? [],
  }))),
)

const agentAssignmentDraftDirty = computed(() => activeAgentAssignmentSignature.value !== draftAgentAssignmentSignature.value)

const canSaveAgentAssignments = computed(() => {
  if (savingAgentAssignments.value) return false
  if (!agentAssignmentDraftDirty.value) return false
  if (!selectedAgentIds.value.length) return activeAgentAssignments.value.length > 0

  return selectedAgentIds.value.every((agentId) => (selectedAgentDocumentKeys.value[Number(agentId)] ?? []).length > 0)
})

const canRejectRequest = computed(() => {
  const status = String(requestItem.value?.status || '').toLowerCase()
  return Boolean(requestItem.value) && !['rejected', 'completed', 'cancelled'].includes(status)
})

const canFinalizeApproval = computed(() => {
  if (!requestItem.value) return false

  const status = String(requestItem.value?.status || '').toLowerCase()
  const stage = String(requestItem.value?.workflow_stage || '').toLowerCase()

  if (['rejected', 'completed', 'cancelled'].includes(status)) return false

  return ['processing', 'ready_for_processing'].includes(stage)
})

const canOpenStaffAssignment = computed(() => {
  const workflowStage = String(requestItem.value?.workflow_stage || '').toLowerCase()
  const status = String(requestItem.value?.status || '').toLowerCase()
  const contractStatus = String(requestItem.value?.current_contract?.status || '').toLowerCase()
  const hasSignedContractState = ['fully_signed', 'client_signed'].includes(contractStatus)
  const requiresCommercialRegistration = Boolean(requestItem.value?.current_contract?.requires_commercial_registration)
  const hasCommercialRegistrationFiles = !requiresCommercialRegistration
    || (Boolean(requestItem.value?.current_contract?.client_commercial_contract_path)
      && Boolean(requestItem.value?.current_contract?.admin_commercial_contract_path))

  return Boolean(requestItem.value?.current_contract)
    && hasSignedContractState
    && hasCommercialRegistrationFiles
    && !['completed', 'rejected', 'blocked'].includes(workflowStage)
    && !['completed', 'rejected', 'cancelled'].includes(status)
})

const commandWorkflowSteps = computed(() => {
  const stage = String(requestItem.value?.workflow_stage || '').toLowerCase()
  const groups = [
    {
      key: 'submitted',
      label: uiText('Submitted', 'تم الإرسال'),
      stages: ['questionnaire', 'submitted_for_review'],
    },
    {
      key: 'review',
      label: uiText('Admin Review', 'مراجعة الإدارة'),
      stages: ['review'],
    },
    {
      key: 'contract',
      label: uiText('Contract', 'العقد'),
      stages: [
        'contract',
        'admin_contract_preparation',
        'awaiting_client_signature',
        'awaiting_client_commercial_registration_upload',
        'awaiting_admin_commercial_registration_upload',
      ],
    },
    {
      key: 'staff',
      label: uiText('Staff Assignment', 'إسناد الموظفين'),
      stages: ['awaiting_staff_assignment', 'assigned_to_staff'],
    },
    {
      key: 'documents',
      label: uiText('Documents', 'المستندات'),
      stages: ['document_collection', 'awaiting_client_documents', 'awaiting_additional_documents', 'client_update_requested'],
    },
    {
      key: 'understudy',
      label: uiText('Understudy', 'الدراسة'),
      stages: ['understudy', 'awaiting_staff_answers', 'awaiting_understudy_review'],
    },
    {
      key: 'bank',
      label: uiText('Bank Agent', 'وكيل البنك'),
      stages: ['awaiting_agent_assignment', 'processing', 'ready_for_processing'],
    },
    {
      key: 'final',
      label: uiText('Final Approval', 'الاعتماد النهائي'),
      stages: ['accepted', 'completed'],
    },
  ]

  let currentIndex = groups.findIndex((group) => group.stages.includes(stage))
  if (currentIndex === -1 && ['rejected', 'blocked', 'cancelled'].includes(stage)) currentIndex = groups.length - 1
  if (currentIndex === -1) currentIndex = 0

  return groups.map((group, index) => ({
    ...group,
    state: index < currentIndex ? 'done' : index === currentIndex ? 'current' : 'waiting',
  }))
})

const commandProgressPercent = computed(() => {
  const currentIndex = commandWorkflowSteps.value.findIndex((step) => step.state === 'current')
  if (currentIndex < 0) return 0
  return Math.round((currentIndex / Math.max(commandWorkflowSteps.value.length - 1, 1)) * 100)
})

const adminNextAction = computed(() => {
  const stage = String(requestItem.value?.workflow_stage || '').toLowerCase()

  if (!requestItem.value?.approval_reference_number) {
    return {
      title: uiText('Review the new request', 'مراجعة الطلب الجديد'),
      body: uiText('Approve the request to generate the approval reference and move it into the contract stage.', 'اعتمد الطلب لإنشاء مرجع الموافقة ونقله إلى مرحلة العقد.'),
      tone: 'warning',
    }
  }

  if (stage === 'awaiting_staff_assignment' || (canOpenStaffAssignment.value && !requestItem.value?.primary_staff_id)) {
    return {
      title: uiText('Assign the lead staff member', 'إسناد الموظف الرئيسي'),
      body: uiText('Choose or change the staff owner before document collection and study work continue.', 'اختر أو غيّر الموظف المسؤول قبل استمرار جمع المستندات والدراسة.'),
      tone: 'info',
    }
  }

  if (understudyVisible.value) {
    return understudyReadyForReview.value
      ? {
          title: uiText('Review staff study answers', 'مراجعة إجابات الدراسة'),
          body: uiText('Approve the study package or return specific answers before bank-agent assignment opens.', 'اعتمد حزمة الدراسة أو أعد إجابات محددة قبل فتح مرحلة وكيل البنك.'),
          tone: 'warning',
        }
      : {
          title: uiText('Waiting for staff study', 'بانتظار دراسة الموظف'),
          body: uiText('Staff must complete the required study answers before admin can approve this stage.', 'يجب على الموظف إكمال إجابات الدراسة الإلزامية قبل اعتماد هذه المرحلة.'),
          tone: 'muted',
        }
  }

  if (stage === 'awaiting_agent_assignment') {
    return {
      title: uiText('Assign bank agents', 'إسناد وكلاء البنك'),
      body: uiText('Select the allowed bank agents and attach the approved request files for each agent.', 'حدد وكلاء البنك المسموحين واربط ملفات الطلب المعتمدة لكل وكيل.'),
      tone: 'info',
    }
  }

  if (activeOpenBatch.value) {
    return {
      title: uiText('Review client updates', 'مراجعة تحديثات العميل'),
      body: uiText('A client update batch is open. Review submitted corrections before continuing the workflow.', 'توجد دفعة تحديث مفتوحة من العميل. راجع التصحيحات المرسلة قبل متابعة سير العمل.'),
      tone: 'warning',
    }
  }

  if (canFinalizeApproval.value) {
    return {
      title: uiText('Prepare final approval', 'تجهيز الاعتماد النهائي'),
      body: uiText('The request is in processing. Finalize once the bank communication result is ready.', 'الطلب قيد المعالجة. قم بالاعتماد النهائي عند جاهزية نتيجة التواصل مع البنك.'),
      tone: 'success',
    }
  }

  return {
    title: uiText('Monitor request progress', 'متابعة تقدم الطلب'),
    body: uiText('No urgent admin action is required right now. Use quick views for files, timeline, comments, and emails.', 'لا يوجد إجراء إداري عاجل حالياً. استخدم العروض السريعة للملفات والمخطط والتعليقات والبريد.'),
    tone: 'muted',
  }
})

const draftItemTypeOptions = computed(() => [
  { value: 'intake_field', label: t('adminRequestDetails.updateBatch.itemTypes.intakeField') },
  { value: 'request_answer', label: t('adminRequestDetails.updateBatch.itemTypes.questionAnswer') },
  { value: 'attachment', label: t('adminRequestDetails.updateBatch.itemTypes.attachment') },
])

function isDraftItemComplete(item: any) {
  const itemType = String(item?.item_type || '').trim()

  if (!itemType) return false
  if (itemType === 'request_answer') return Number.isInteger(Number(item?.question_id)) && Number(item.question_id) > 0
  if (itemType === 'intake_field' || itemType === 'attachment') return Boolean(String(item?.field_key || '').trim())

  return false
}

const canSubmitUpdateBatch = computed(() => {
  if (creatingBatch.value) return false
  if (!updateDraftItems.value.length) return false

  return updateDraftItems.value.every((item) => isDraftItemComplete(item))
})

function localizedText(en?: string | null, ar?: string | null, fallback = '—') {
  if (locale.value === 'ar') return ar || en || fallback
  return en || ar || fallback
}

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

function answerText(answer: any) {
  if (!answer) return t('adminRequestDetails.states.emptyValue')
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return t('adminRequestDetails.states.emptyValue')
  return String(value)
}

function formatUpdateValue(item: any, mode: 'old' | 'new') {
  const payload = mode === 'old' ? item?.old_value_json : item?.new_value_json
  if (!payload) return '—'
  if (Object.prototype.hasOwnProperty.call(payload, 'value')) {
    const value = payload.value
    return Array.isArray(value) ? value.join(', ') : value === null || value === undefined || value === '' ? '—' : String(value)
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'answer_value_json') && payload.answer_value_json !== null && payload.answer_value_json !== undefined) {
    return Array.isArray(payload.answer_value_json) ? payload.answer_value_json.join(', ') : String(payload.answer_value_json)
  }
  if (payload.answer_text) return String(payload.answer_text)
  if (payload.file_name) return String(payload.file_name)
  return '—'
}

function updateStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'updated') return t('adminRequestDetails.updateBatch.states.submittedForReview')
  if (key === 'approved') return t('adminRequestDetails.updateBatch.states.approved')
  if (key === 'rejected') return t('adminRequestDetails.updateBatch.states.rejected')
  if (key === 'pending') return t('adminRequestDetails.updateBatch.states.waitingForClient')
  return key || t('adminRequestDetails.states.unknown')
}

function updateStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return 'client-badge client-badge--green'
  if (key === 'updated') return 'client-badge client-badge--blue'
  if (key === 'rejected') return 'client-badge client-badge--rose'
  return 'client-badge client-badge--amber'
}

function emailStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'delivered') return 'client-badge client-badge--green'
  if (key === 'sent') return 'client-badge client-badge--green'
  if (key === 'failed') return 'client-badge client-badge--rose'
  return 'client-badge client-badge--amber'
}

function emailStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'sent') return uiText('Sent', 'تم الإرسال')
  if (key === 'failed') return uiText('Failed', 'فشل الإرسال')
  if (key === 'queued') return uiText('Queued', 'قيد الانتظار')
  return uiText('Pending', 'قيد المعالجة')
}

function emailRecipients(email: any) {
  const named = Array.isArray(email?.agents)
    ? email.agents.map((agent: any) => [agent?.name, agent?.email].filter(Boolean).join(' · ')).filter(Boolean)
    : []

  if (named.length) return named.join(', ')

  const raw = Array.isArray(email?.to_emails_json) ? email.to_emails_json.filter(Boolean) : []
  return raw.length ? raw.join(', ') : t('adminRequestDetails.states.emptyValue')
}

function formatFileSize(value: number | null | undefined) {
  if (!value || value <= 0) return t('adminRequestDetails.states.sizeUnavailable')

  const units = ['B', 'KB', 'MB', 'GB']
  let size = value
  let unitIndex = 0

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex += 1
  }

  return `${size >= 10 || unitIndex === 0 ? size.toFixed(0) : size.toFixed(1)} ${units[unitIndex]}`
}

function emailAttachmentDownloadUrl(emailId: number | string, attachmentId: number | string) {
  return adminRequestEmailAttachmentDownloadUrl(requestId.value, emailId, attachmentId)
}

function studyQuestionTitle(question: any) {
  return locale.value === 'ar'
    ? (question?.question_text_ar || question?.template?.question_text_ar || question?.question_text_en || question?.template?.question_text_en || t('adminRequestDetails.states.studyQuestion'))
    : (question?.question_text_en || question?.template?.question_text_en || question?.question_text_ar || question?.template?.question_text_ar || t('adminRequestDetails.states.studyQuestion'))
}

function studyQuestionAnswer(question: any) {
  if (Array.isArray(question?.answer_json) && question.answer_json.length) return question.answer_json.join(', ')
  if (question?.answer_text) return question.answer_text
  return t('adminRequestDetails.states.noAnswerSavedYet')
}

function studyQuestionAnswerAudit(question: any) {
  const answererName = String(question?.answerer?.name || '').trim()
  const answeredAt = question?.answered_at
    ? formatDateTime(question.answered_at, locale, t('adminRequestDetails.states.emptyValue'))
    : ''

  if (answererName && answeredAt) {
    return uiText(`Answered by ${answererName} · ${answeredAt}`, `تمت الإجابة بواسطة ${answererName} · ${answeredAt}`)
  }

  if (answererName) {
    return uiText(`Answered by ${answererName}`, `تمت الإجابة بواسطة ${answererName}`)
  }

  if (answeredAt) {
    return uiText(`Answered on ${answeredAt}`, `تمت الإجابة بتاريخ ${answeredAt}`)
  }

  return ''
}

function studyQuestionStatusLabel(question: any) {
  const key = String(question?.status || '').toLowerCase()
  if (key === 'closed') return t('adminRequestDetails.understudy.status.reviewed')
  if (key === 'answered') return t('adminRequestDetails.understudy.status.answered')
  return t('adminRequestDetails.states.pending')
}

function studyQuestionStatusClass(question: any) {
  const key = String(question?.status || '').toLowerCase()
  if (key === 'closed') return 'client-badge client-badge--green'
  if (key === 'answered') return 'client-badge client-badge--blue'
  return 'client-badge client-badge--amber'
}

function understudyStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return uiText('Approved', 'تمت الموافقة')
  if (key === 'submitted') return uiText('Submitted', 'مُرسل')
  if (key === 'rejected') return uiText('Rejected', 'مرفوض')
  return uiText('Draft', 'مسودة')
}

function readableUnderstudyStatus(status: string | null | undefined) {
  return formatUnderstudyStatus(status, locale, t('adminRequestDetails.states.emptyValue'))
}

function readableAdditionalDocumentStatus(status: string | null | undefined) {
  return formatAdditionalDocumentStatus(status, locale, t('adminRequestDetails.states.emptyValue'))
}

function readableEmailDeliveryStatus(status: string | null | undefined) {
  return formatEmailDeliveryStatus(status, locale, t('adminRequestDetails.emailActivity.queued'))
}

function readableCommentVisibility(value: string | null | undefined) {
  const key = String(value || '').trim().toLowerCase()
  if (key === 'internal') return uiText('Internal', '\u062f\u0627\u062e\u0644\u064a')
  if (key === 'admin_only') return uiText('Admin only', '\u0644\u0644\u0625\u062f\u0627\u0631\u0629 \u0641\u0642\u0637')
  if (key === 'client_visible') return uiText('Client visible', '\u0645\u0631\u0626\u064a \u0644\u0644\u0639\u0645\u064a\u0644')
  return key || t('adminRequestDetails.states.emptyValue')
}

function addUpdateDraftItem() {
  updateDraftItems.value.push({
    local_id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
    item_type: '',
    field_key: null,
    question_id: null,
    label_en: '',
    instruction_en: '',
    is_required: true,
  })
}

function removeUpdateDraftItem(localId: string) {
  updateDraftItems.value = updateDraftItems.value.filter((item) => item.local_id !== localId)
}

function onDraftTypeChange(item: any) {
  if (item.item_type === 'intake_field' || item.item_type === 'attachment') {
    item.field_key = null
    item.question_id = null
    return
  }

  if (item.item_type === 'request_answer') {
    item.question_id = null
    item.field_key = null
    return
  }

  item.field_key = null
  item.question_id = null
}

function syncAgentAssignmentDraftFromRequest() {
  const activeAssignments = Array.isArray(requestItem.value?.agent_assignments)
    ? requestItem.value.agent_assignments.filter((item: any) => item?.is_active !== false)
    : []

  selectedAgentIds.value = activeAssignments
    .map((item: any) => Number(item?.agent_id ?? item?.agent?.id ?? 0))
    .filter((id: number) => id > 0)

  selectedAgentDocumentKeys.value = activeAssignments.reduce((carry: Record<number, string[]>, item: any) => {
    const agentId = Number(item?.agent_id ?? item?.agent?.id ?? 0)
    if (!agentId) return carry

    carry[agentId] = Array.isArray(item?.allowed_documents)
      ? item.allowed_documents.map((document: any) => String(document.document_key || document.key)).filter(Boolean)
      : []

    return carry
  }, {})
}

async function loadAgentAssignmentOptions() {
  const data = await getAdminRequestAgentAssignmentOptions(requestId.value)
  emailBankOptions.value = data.banks ?? []
  emailAgentOptions.value = data.agents ?? []
  availableEmailDocuments.value = data.available_documents ?? []
}

function toggleAgentSelection(agentId: number, checked: boolean) {
  agentId = Number(agentId)

  if (checked) {
    if (!selectedAgentIds.value.includes(agentId)) {
      selectedAgentIds.value = [...selectedAgentIds.value, agentId]
    }

    if (!selectedAgentDocumentKeys.value[agentId]) {
      selectedAgentDocumentKeys.value = {
        ...selectedAgentDocumentKeys.value,
        [agentId]: [],
      }
    }

    return
  }

  selectedAgentIds.value = selectedAgentIds.value.filter((id) => id !== agentId)
  const next = { ...selectedAgentDocumentKeys.value }
  delete next[agentId]
  selectedAgentDocumentKeys.value = next
}

function isAgentSelected(agentId: number) {
  return selectedAgentIds.value.includes(agentId)
}

function toggleAgentDocument(agentId: number, documentKey: string, checked: boolean) {
  agentId = Number(agentId)
  const current = Array.isArray(selectedAgentDocumentKeys.value[agentId])
    ? [...selectedAgentDocumentKeys.value[agentId]]
    : []

  if (checked && !current.includes(documentKey)) {
    current.push(documentKey)
  }

  if (!checked) {
    const index = current.indexOf(documentKey)
    if (index >= 0) current.splice(index, 1)
  }

  selectedAgentDocumentKeys.value = {
    ...selectedAgentDocumentKeys.value,
    [agentId]: current,
  }
}

function isAgentDocumentSelected(agentId: number, documentKey: string) {
  return (selectedAgentDocumentKeys.value[agentId] ?? []).includes(documentKey)
}

function selectAllAgentDocuments(agentId: number) {
  const documentKeys = (availableEmailDocuments.value ?? [])
    .map((document: any) => String(document.key || ''))
    .filter(Boolean)

  selectedAgentDocumentKeys.value = {
    ...selectedAgentDocumentKeys.value,
    [Number(agentId)]: documentKeys,
  }
}

function clearAgentDocuments(agentId: number) {
  selectedAgentDocumentKeys.value = {
    ...selectedAgentDocumentKeys.value,
    [Number(agentId)]: [],
  }
}

function removeAgentFromDraft(agentId: number) {
  toggleAgentSelection(Number(agentId), false)
}

function previewAgentAssignmentDocument(document: any) {
  if (!document?.download_url) return
  openFilePreview(document.file_name, document.download_url, document.mime_type)
}

function onFinalApprovalAttachmentChange(event: Event) {
  const input = event.target as HTMLInputElement | null
  finalApprovalAttachments.value = Array.from(input?.files ?? [])
}

function removeFinalApprovalAttachment(index: number) {
  finalApprovalAttachments.value = finalApprovalAttachments.value.filter((_, fileIndex) => fileIndex !== index)
}

async function submitAgentAssignments() {
  if (!requestItem.value || !canSaveAgentAssignments.value || savingAgentAssignments.value) return

  savingAgentAssignments.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload = {
      review_note: agentAssignmentReviewNote.value || undefined,
      assignments: selectedAgentIds.value.map((agentId) => ({
        agent_id: Number(agentId),
        document_keys: (selectedAgentDocumentKeys.value[Number(agentId)] ?? []).filter(Boolean),
      })),
    }

    const data = await storeAdminRequestAgentAssignments(requestItem.value.id, payload)
    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    emailBankOptions.value = data.banks ?? emailBankOptions.value
    emailAgentOptions.value = data.agents ?? emailAgentOptions.value
    availableEmailDocuments.value = data.available_documents ?? availableEmailDocuments.value
    syncAgentAssignmentDraftFromRequest()
    successMessage.value = data.message || t('adminRequestDetails.messages.allowedAgentsSaved')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.allowedAgentsSaveFailed')
  } finally {
    savingAgentAssignments.value = false
  }
}

function resetUpdateBatchForm() {
  batchReasonEn.value = ''
  batchReasonAr.value = ''
  updateDraftItems.value = []
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    const [data, agentOptions] = await Promise.all([
      getAdminRequestDetails(requestId.value),
      getAdminRequestAgentAssignmentOptions(requestId.value),
    ])
    requestItem.value = data.request ?? null
    requiredDocuments.value = data.required_documents ?? []
    staffQuestionSummary.value = data.staff_question_summary ?? null
    emailBankOptions.value = agentOptions.banks ?? []
    emailAgentOptions.value = agentOptions.agents ?? []
    availableEmailDocuments.value = agentOptions.available_documents ?? []
    syncAgentAssignmentDraftFromRequest()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function attachmentDownloadUrl(attachmentId: number | string) {
  return adminRequestAttachmentDownloadUrl(requestId.value, attachmentId)
}

function attachmentBundleDownloadUrl() {
  return adminRequestAttachmentsBundleDownloadUrl(requestId.value)
}

function shareholderIdDownloadUrl(shareholderId: number | string) {
  return adminRequestShareholderIdDownloadUrl(requestId.value, shareholderId)
}

async function submitUnderstudyReview(action: 'approve' | 'reject') {
  if (!requestItem.value || reviewingUnderstudy.value) return

  reviewingUnderstudy.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await reviewAdminUnderstudy(requestItem.value.id, {
      action,
      review_note: understudyReviewNote.value || undefined,
    })
    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    if (String(requestItem.value?.workflow_stage || '').toLowerCase() === 'awaiting_agent_assignment') {
      await loadAgentAssignmentOptions()
      syncAgentAssignmentDraftFromRequest()
    }
    successMessage.value = data.message || (action === 'approve' ? 'Understudy approved successfully.' : 'Understudy returned to staff successfully.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.understudyReviewFailed')
  } finally {
    reviewingUnderstudy.value = false
  }
}

async function submitStaffQuestionRevision(question: any) {
  if (!requestItem.value) return
  const questionId = Number(question?.id ?? 0)
  if (!questionId) return
  if (reviewingStaffQuestion.value[questionId]) return

  reviewingStaffQuestion.value = {
    ...reviewingStaffQuestion.value,
    [questionId]: true,
  }
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const reviewNote = String(staffQuestionReviewNotes.value[questionId] || '').trim()
    const data = await reviewAdminStaffQuestion(requestItem.value.id, questionId, {
      action: 'reopen',
      review_note: reviewNote || undefined,
    })

    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value

    successMessage.value = uiText('Question sent back to staff for revision.', 'تم إرسال السؤال إلى الموظف للمراجعة.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Failed to review this study question.', 'تعذر مراجعة سؤال الدراسة هذا.')
  } finally {
    reviewingStaffQuestion.value = {
      ...reviewingStaffQuestion.value,
      [questionId]: false,
    }
  }
}

async function submitComment() {
  if (!requestItem.value || !commentText.value.trim() || savingComment.value) return

  savingComment.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await addAdminRequestComment(requestItem.value.id, {
      comment_text: commentText.value.trim(),
      visibility: commentVisibility.value,
    })
    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    commentText.value = ''
    successMessage.value = data.message || uiText('Comment added successfully.', 'تمت إضافة التعليق بنجاح.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Failed to add the comment.', 'تعذر إضافة التعليق.')
  } finally {
    savingComment.value = false
  }
}

async function rejectRequest() {
  if (!requestItem.value || !canRejectRequest.value || rejectingRequest.value) return
  if (!window.confirm(t('adminRequestDetails.confirm.rejectRequest'))) return

  rejectingRequest.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await rejectAdminRequest(requestItem.value.id, {
      reason: rejectReason.value || undefined,
    })
    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    successMessage.value = data.message || t('adminRequestDetails.messages.requestRejected')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.rejectFailed')
  } finally {
    rejectingRequest.value = false
  }
}

async function finalizeRequest() {
  if (!requestItem.value || !canFinalizeApproval.value || finalizingApproval.value) return
  if (!window.confirm(t('adminRequestDetails.confirm.finalApproveRequest'))) return

  finalizingApproval.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await finalizeAdminRequest(requestItem.value.id, {
      final_approval_notes: finalApprovalNotes.value || undefined,
      final_approval_attachments: finalApprovalAttachments.value,
    })
    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    finalApprovalAttachments.value = []
    actionDialog.value = null
    successMessage.value = data.message || t('adminRequestDetails.messages.requestFinalApproved')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.finalApproveFailed')
  } finally {
    finalizingApproval.value = false
  }
}

function requiredDocumentDownloadUrl(uploadId: number | string) {
  return adminRequiredDocumentDownloadUrl(requestId.value, uploadId)
}

function requiredDocumentStepBundleDownloadUrl(stepId: number | string) {
  return adminRequiredDocumentStepBundleDownloadUrl(requestId.value, stepId)
}

function normalizedRequiredUploads(item: any) {
  const uploads = Array.isArray(item?.uploads) ? [...item.uploads] : []
  if (!uploads.length && item?.upload?.id) {
    uploads.push(item.upload)
  }

  return uploads
}

function latestRequiredUpload(item: any) {
  return normalizedRequiredUploads(item)[0] ?? null
}

function requiredUploadStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return inlineText('Approved', 'معتمد')
  if (key === 'rejected') return inlineText('Change requested', 'طُلب تعديل')
  if (key === 'uploaded') return inlineText('Uploaded', 'مرفوع')
  if (key === 'pending') return inlineText('Pending', 'قيد الانتظار')
  return key || t('adminRequestDetails.states.emptyValue')
}

function additionalDocumentDownloadUrl(additionalDocumentId: number | string) {
  return adminAdditionalDocumentDownloadUrl(requestId.value, additionalDocumentId)
}

async function approveRequest() {
  if (!requestItem.value) return
  approving.value = true
  try {
    await approveAdminRequest(requestItem.value.id, { approval_notes: approvalNotes.value })
    await router.push({ name: 'admin-request-contract', params: { id: requestItem.value.id } })
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.approveFailed')
  } finally {
    approving.value = false
  }
}

async function submitUpdateBatch() {
  if (!requestItem.value || !canSubmitUpdateBatch.value) return

  creatingBatch.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload: { reason_en?: string; reason_ar?: string; items: AdminUpdateBatchDraftItem[] } = {
      reason_en: batchReasonEn.value || undefined,
      reason_ar: batchReasonAr.value || undefined,
      items: updateDraftItems.value.map((item) => ({
        item_type: item.item_type,
        field_key: item.item_type === 'request_answer' ? null : item.field_key,
        question_id: item.item_type === 'request_answer' ? Number(item.question_id) : null,
        label_en: item.label_en || null,
        instruction_en: item.instruction_en || null,
        is_required: Boolean(item.is_required),
        editable_by: 'client',
      })),
    }

    const data = await createAdminUpdateBatch(requestItem.value.id, payload)
    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    resetUpdateBatchForm()
    successMessage.value = data.message || t('adminRequestDetails.messages.updateBatchCreated')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.updateBatchCreateFailed')
  } finally {
    creatingBatch.value = false
  }
}


async function cancelActiveBatch() {
  if (!requestItem.value || !activeOpenBatch.value || cancellingBatch.value) return
  if (!window.confirm(t('adminRequestDetails.confirm.cancelUpdateBatch'))) return

  cancellingBatch.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await cancelAdminUpdateBatch(requestItem.value.id, activeOpenBatch.value.id)
    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    successMessage.value = data.message || t('adminRequestDetails.messages.updateBatchCancelled')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.updateBatchCancelFailed')
  } finally {
    cancellingBatch.value = false
  }
}

async function reviewUpdateItem(item: any, action: 'approve' | 'reject') {
  if (!requestItem.value) return

  reviewingUpdateItems.value = {
    ...reviewingUpdateItems.value,
    [item.id]: true,
  }
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await reviewAdminUpdateItem(requestItem.value.id, item.id, {
      action,
      review_note: reviewNotes.value[item.id] || undefined,
    })
    requestItem.value = data.request ?? requestItem.value
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    staffQuestionSummary.value = data.staff_question_summary ?? staffQuestionSummary.value
    successMessage.value = data.message || t('adminRequestDetails.messages.updateItemReviewed')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.updateItemReviewFailed')
  } finally {
    reviewingUpdateItems.value = {
      ...reviewingUpdateItems.value,
      [item.id]: false,
    }
  }
}

onMounted(() => {
  load()
})
</script>

<template>
  <RequestWorkspaceShell
    :eyebrow="t('adminRequestDetails.hero.eyebrow')"
    :title="t('adminRequestDetails.hero.title')"
    :subtitle="t('adminRequestDetails.hero.subtitle')"
    :loading="loading"
    :error-message="errorMessage"
    :success-message="successMessage"
    :has-record="Boolean(requestItem)"
  >
    <template #topbar-actions>
      <RouterLink :to="{ name: 'admin-new-requests' }" class="ghost-btn">{{ t('adminRequestDetails.hero.backToQueue') }}</RouterLink>
      <button
        v-if="requestItem?.current_contract?.contract_pdf_path"
        type="button"
        class="ghost-btn"
        @click="openFilePreview(`contract-${requestId}.pdf`, adminContractDownloadUrl(requestId), 'application/pdf')"
      >
        {{ t('adminRequestDetails.agentAssignments.previewFile') }}
      </button>
      <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.hero.downloadContractPdf') }}</a>
      <RouterLink v-if="requestItem?.approval_reference_number || requestItem?.current_contract" :to="{ name: 'admin-request-contract', params: { id: requestId } }" class="primary-btn">{{ t('adminRequestDetails.hero.goToContract') }}</RouterLink>
      <RouterLink v-if="canOpenStaffAssignment" :to="{ name: 'admin-assignment-details', params: { id: requestId }, query: { return_to: String(route.fullPath || `/admin/requests/${requestId}`) } }" class="ghost-btn">{{ t('adminRequestDetails.hero.assignStaff') }}</RouterLink>
    </template>

    <template #loading>{{ t('adminRequestDetails.states.loading') }}</template>

    <template #workflow>
      <section class="request-command-workflow" aria-label="Request workflow">
        <div class="request-command-workflow__bar">
          <div>
            <span>{{ uiText('Workflow progress', 'تقدم سير العمل') }}</span>
            <strong>{{ commandProgressPercent }}%</strong>
          </div>
          <p>{{ uiText('The active request stage stays visible so admin can see exactly what has happened and what comes next.', 'تبقى مرحلة الطلب النشطة ظاهرة حتى تعرف الإدارة ما تم وما هو التالي بوضوح.') }}</p>
        </div>
        <ol class="request-command-stepper">
          <li
            v-for="step in commandWorkflowSteps"
            :key="step.key"
            class="request-command-step"
            :class="`is-${step.state}`"
          >
            <span>{{ step.state === 'done' ? uiText('Done', 'تم') : step.state === 'current' ? uiText('Current', 'الحالي') : uiText('Next', 'التالي') }}</span>
            <strong>{{ step.label }}</strong>
          </li>
        </ol>
      </section>
    </template>

    <template #summary>
      <div class="request-summary-stack">
        <article v-if="requestItem" class="panel-card slim-card admin-workflow-stage-card">
          <div class="panel-head">
            <div>
              <h2>{{ t('adminRequestDetails.sections.workflowStageTitle') }}</h2>
              <p class="subtext">{{ t('adminRequestDetails.sections.workflowStageSubtitle') }}</p>
            </div>
          </div>
          <div class="admin-workflow-stage-row">
            <label class="client-form-group admin-workflow-stage-field">
              <span class="client-form-label">{{ t('adminRequestDetails.sections.workflowStageSelectLabel') }}</span>
              <select v-model="workflowStageDraft" class="admin-select">
                <option
                  v-for="stage in workflowStageSelectOptions"
                  :key="stage"
                  :value="stage"
                >
                  {{ stageMeta(stage).label }}
                </option>
              </select>
            </label>
            <button
              type="button"
              class="primary-btn admin-workflow-stage-apply"
              :disabled="!workflowStageDirty || savingWorkflowStage"
              @click="applyWorkflowStage"
            >
              {{ savingWorkflowStage ? t('adminRequestDetails.sections.applyingWorkflowStage') : t('adminRequestDetails.sections.applyWorkflowStage') }}
            </button>
          </div>
        </article>

        <RequestSummaryStatGrid :items="summaryStatItems" />

        <div class="request-top-panel-grid">
          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ t('adminRequestDetails.sections.quickCounts') }}</h2></div>
            <div class="catalog-mini-stats request-kpi-grid request-kpi-grid--two">
              <div><span>{{ t('adminRequestDetails.summary.assignments') }}</span><strong>{{ activityCounts.assignments }}</strong></div>
              <div><span>{{ t('adminRequestDetails.summary.comments') }}</span><strong>{{ activityCounts.comments }}</strong></div>
              <div><span>{{ t('adminRequestDetails.summary.timelineEvents') }}</span><strong>{{ activityCounts.timeline }}</strong></div>
              <div><span>{{ t('adminRequestDetails.summary.emailLogs') }}</span><strong>{{ activityCounts.emails }}</strong></div>
            </div>
            <div class="approve-actions request-footer-actions">
              <RouterLink class="ghost-btn" :to="{ name: 'admin-request-emails', params: { id: requestId } }">{{ t('adminRequestDetails.emailActivity.title') }}</RouterLink>
            </div>
          </article>

          <article class="panel-card slim-card request-action-dock-card">
            <div class="panel-head"><h2>{{ uiText('Actions', 'الإجراءات') }}</h2></div>
            <div class="request-action-dock">
              <button type="button" class="ghost-btn" @click="actionDialog = 'comment'">
                {{ uiText('Follow-up comment', 'تعليق متابعة') }}
              </button>
              <button type="button" class="ghost-btn" @click="actionDialog = 'updateBatch'">
                {{ t('adminRequestDetails.updateBatch.title') }}
              </button>
              <button v-if="agentAssignmentVisible" type="button" class="ghost-btn" @click="actionDialog = 'agentAccess'">
                {{ t('adminRequestDetails.agentAssignments.manageAccess') }}
              </button>
              <button v-if="canRejectRequest" type="button" class="ghost-btn" @click="actionDialog = 'reject'">
                {{ t('adminRequestDetails.rejectRequest.title') }}
              </button>
              <button v-if="canFinalizeApproval" type="button" class="primary-btn" @click="actionDialog = 'finalApprove'">
                {{ t('adminRequestDetails.sections.finalApproveRequest') }}
              </button>
              <button v-else-if="!requestItem?.approval_reference_number" type="button" class="primary-btn" @click="actionDialog = 'approve'">
                {{ t('adminRequestDetails.sections.approveRequest') }}
              </button>
            </div>
          </article>

          <article v-if="false && canRejectRequest" class="panel-card slim-card action-card">
            <div class="panel-head"><h2>{{ t('adminRequestDetails.rejectRequest.title') }}</h2></div>
            <p class="subtext">{{ t('adminRequestDetails.rejectRequest.subtitle') }}</p>
            <textarea v-model="rejectReason" rows="4" class="admin-textarea" :placeholder="t('adminRequestDetails.rejectRequest.placeholder')"></textarea>
            <div class="approve-actions">
              <button class="ghost-btn" type="button" :disabled="rejectingRequest" @click="rejectRequest">
                {{ rejectingRequest ? t('adminRequestDetails.rejectRequest.rejecting') : t('adminRequestDetails.rejectRequest.rejectNow') }}
              </button>
            </div>
          </article>

          <article v-if="false && canFinalizeApproval" class="panel-card slim-card action-card">
            <div class="panel-head"><h2>{{ t('adminRequestDetails.sections.finalApproveRequest') }}</h2></div>
            <p class="subtext">{{ t('adminRequestDetails.sections.finalApproveSubtitle') }}</p>
            <textarea v-model="finalApprovalNotes" rows="4" class="admin-textarea" :placeholder="t('adminRequestDetails.sections.finalApprovePlaceholder')"></textarea>
            <div class="approve-actions">
              <button class="primary-btn" type="button" :disabled="finalizingApproval" @click="finalizeRequest">
                {{ finalizingApproval ? t('adminRequestDetails.actions.finalizingApproval') : t('adminRequestDetails.actions.finalApproveNow') }}
              </button>
            </div>
          </article>

          <article v-if="false && !requestItem?.approval_reference_number" class="panel-card slim-card action-card request-top-panel--span-2">
            <div class="panel-head"><h2>{{ t('adminRequestDetails.sections.approveRequest') }}</h2></div>
            <p class="subtext">{{ t('adminRequestDetails.sections.approveSubtitle') }}</p>
            <textarea v-model="approvalNotes" rows="5" class="admin-textarea" :placeholder="t('adminRequestDetails.sections.approvePlaceholder')"></textarea>
            <div class="approve-actions">
              <button class="primary-btn" type="button" :disabled="approving" @click="approveRequest">
                {{ approving ? t('adminRequestDetails.actions.approving') : t('adminRequestDetails.actions.approveAndContinue') }}
              </button>
            </div>
          </article>
        </div>
      </div>
    </template>

    <template #main>
      <div class="request-workspace-stack">
      <article class="request-command-active-stage">
        <div>
          <span>{{ stageMeta(requestItem?.workflow_stage).label }}</span>
          <h2>{{ adminNextAction.title }}</h2>
          <p>{{ adminNextAction.body }}</p>
        </div>
        <div class="request-command-active-actions">
          <RouterLink v-if="canOpenStaffAssignment" :to="{ name: 'admin-assignment-details', params: { id: requestId }, query: { return_to: String(route.fullPath || `/admin/requests/${requestId}`) } }" class="ghost-btn">{{ t('adminRequestDetails.hero.assignStaff') }}</RouterLink>
          <button type="button" class="ghost-btn" @click="actionDialog = 'comment'">
            {{ uiText('Follow-up', 'متابعة') }}
          </button>
          <button type="button" class="ghost-btn" @click="actionDialog = 'updateBatch'">
            {{ t('adminRequestDetails.updateBatch.title') }}
          </button>
          <button v-if="agentAssignmentVisible" type="button" class="ghost-btn" @click="actionDialog = 'agentAccess'">
            {{ t('adminRequestDetails.agentAssignments.manageAccess') }}
          </button>
          <button v-if="canRejectRequest" type="button" class="ghost-btn" @click="actionDialog = 'reject'">
            {{ t('adminRequestDetails.rejectRequest.title') }}
          </button>
          <button v-if="understudyReadyForReview" type="button" class="primary-btn" :disabled="reviewingUnderstudy || !understudyApproveReady" @click="submitUnderstudyReview('approve')">
            {{ reviewingUnderstudy ? t('adminRequestDetails.actions.saving') : t('adminRequestDetails.understudy.approveForNextStep') }}
          </button>
          <button v-else-if="canFinalizeApproval" class="primary-btn" type="button" :disabled="finalizingApproval" @click="actionDialog = 'finalApprove'">
            {{ finalizingApproval ? t('adminRequestDetails.actions.finalizingApproval') : t('adminRequestDetails.actions.finalApproveNow') }}
          </button>
          <button v-else-if="!requestItem?.approval_reference_number" class="primary-btn" type="button" :disabled="approving" @click="actionDialog = 'approve'">
            {{ approving ? t('adminRequestDetails.actions.approving') : t('adminRequestDetails.actions.approveAndContinue') }}
          </button>
        </div>
      </article>

      <AdminAgentAccessPanel
        v-if="agentAssignmentVisible && actionDialog !== 'agentAccess'"
        :active-assignments="activeAgentAssignments"
        :bank-options="emailBankOptions"
        :agent-options="emailAgentOptions"
        :available-documents="availableEmailDocuments"
        :selected-bank-id="selectedAssignmentBankId"
        :selected-agent-ids="selectedAgentIds"
        :selected-agent-document-keys="selectedAgentDocumentKeys"
        :review-note="agentAssignmentReviewNote"
        :saving="savingAgentAssignments"
        :can-save="canSaveAgentAssignments"
        :dirty="agentAssignmentDraftDirty"
        :stage-after-save-label="selectedAgentIds.length ? stageMeta('processing').label : stageMeta('awaiting_agent_assignment').label"
        @update:selected-bank-id="selectedAssignmentBankId = $event"
        @update:review-note="agentAssignmentReviewNote = $event"
        @toggle-agent="toggleAgentSelection"
        @toggle-document="toggleAgentDocument"
        @select-all-documents="selectAllAgentDocuments"
        @clear-documents="clearAgentDocuments"
        @remove-agent="removeAgentFromDraft"
        @reset-draft="syncAgentAssignmentDraftFromRequest"
        @submit="submitAgentAssignments"
        @preview-file="previewAgentAssignmentDocument"
      />

      <RequestCoreDetailsCard v-if="false" :request="requestItem" :required-documents="requiredDocuments" />

      <article v-if="false" class="panel-card request-quick-panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('adminRequestDetails.quick.title') }}</h2>
            <p class="subtext">{{ t('adminRequestDetails.quick.subtitle') }}</p>
          </div>
        </div>

        <div class="catalog-mini-stats request-kpi-grid">
          <div>
            <span>{{ t('adminRequestDetails.requiredDocuments.title') }}</span>
            <strong>{{ requiredDocuments.filter((item) => item.is_uploaded).length }}/{{ activityCounts.requiredDocuments }}</strong>
          </div>
          <div>
            <span>{{ t('adminRequestDetails.additionalDocuments.title') }}</span>
            <strong>{{ activityCounts.additionalDocuments }}</strong>
          </div>
          <div>
            <span>{{ t('adminRequestDetails.emailActivity.title') }}</span>
            <strong>{{ activityCounts.emails }}</strong>
          </div>
          <div>
            <span>{{ t('adminRequestDetails.updateBatch.totalBatches') }}</span>
            <strong>{{ activityCounts.updateBatches }}</strong>
          </div>
        </div>

        <div class="admin-quick-actions request-quick-actions-grid">
          <button type="button" class="admin-quick-action" @click="quickView = 'requiredDocuments'">
            <strong>{{ t('adminRequestDetails.requiredDocuments.title') }}</strong>
            <span>{{ t('adminRequestDetails.quick.filesCount', { count: activityCounts.requiredDocuments }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'additionalDocuments'">
            <strong>{{ t('adminRequestDetails.additionalDocuments.title') }}</strong>
            <span>{{ t('adminRequestDetails.quick.filesCount', { count: activityCounts.additionalDocuments }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'answers'">
            <strong>{{ t('adminRequestDetails.quick.questionnaire') }}</strong>
            <span>{{ t('adminRequestDetails.quick.answersCount', { count: activityCounts.answers }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'attachments'">
            <strong>{{ t('adminRequestDetails.quick.uploadedFiles') }}</strong>
            <span>{{ t('adminRequestDetails.quick.filesCount', { count: activityCounts.attachments }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'shareholders'">
            <strong>{{ t('adminRequestDetails.quick.shareholders') }}</strong>
            <span>{{ t('adminRequestDetails.quick.recordsCount', { count: activityCounts.shareholders }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'assignments'">
            <strong>{{ t('adminRequestDetails.quick.assignedStaff') }}</strong>
            <span>{{ t('adminRequestDetails.quick.ownersCount', { count: activityCounts.assignments }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'comments'">
            <strong>{{ t('adminRequestDetails.quick.comments') }}</strong>
            <span>{{ t('adminRequestDetails.quick.notesCount', { count: activityCounts.comments }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'emails'">
            <strong>{{ t('adminRequestDetails.emailActivity.title') }}</strong>
            <span>{{ t('adminRequestDetails.quick.eventsCount', { count: activityCounts.emails }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'timeline'">
            <strong>{{ t('adminRequestDetails.quick.timeline') }}</strong>
            <span>{{ t('adminRequestDetails.quick.eventsCount', { count: activityCounts.timeline }) }}</span>
          </button>
        </div>
      </article>

      <article v-if="false" class="panel-card slim-card">
        <div class="panel-head">
          <div>
            <h2>{{ uiText('Follow-up comments', 'تعليقات المتابعة') }}</h2>
            <p class="subtext">{{ uiText('Send comments internally, to admins only, or directly to the client.', 'أرسل التعليقات داخلياً أو للإدارة فقط أو بشكل مرئي للعميل.') }}</p>
          </div>
        </div>

        <div class="admin-inline-block-grid">
          <div class="field-block field-block--grow">
            <span>{{ uiText('Visibility', 'الظهور') }}</span>
            <select v-model="commentVisibility" class="admin-select">
              <option value="internal">{{ uiText('Internal', 'داخلي') }}</option>
              <option value="admin_only">{{ uiText('Admin only', 'للإدارة فقط') }}</option>
              <option value="client_visible">{{ uiText('Client visible', 'مرئي للعميل') }}</option>
            </select>
          </div>
        </div>

        <textarea
          v-model="commentText"
          rows="4"
          class="admin-textarea"
          :placeholder="uiText('Write a clear follow-up comment for this request.', 'اكتب تعليق متابعة واضح لهذا الطلب.')"
        />

        <div class="approve-actions">
          <button class="primary-btn" type="button" :disabled="savingComment || !commentText.trim()" @click="submitComment">
            {{ savingComment ? t('adminRequestDetails.actions.saving') : uiText('Save comment', 'حفظ التعليق') }}
          </button>
          <button type="button" class="ghost-btn" @click="quickView = 'comments'">
            {{ uiText('Open all comments', 'فتح جميع التعليقات') }}
          </button>
        </div>

        <div v-if="requestItem.comments?.length" class="timeline-list compact-list" style="margin-top: 1rem;">
          <div
            v-for="comment in (requestItem.comments || []).slice(0, 3)"
            :key="`comment-inline-${comment.id}`"
            class="timeline-item"
          >
            <strong>{{ comment.user?.name || t('adminRequestDetails.states.system') }}</strong>
            <p>{{ comment.comment_text }}</p>
            <span>{{ formatDateTime(comment.created_at, locale, t('adminRequestDetails.states.emptyValue')) }} · {{ readableCommentVisibility(comment.visibility) }}</span>
          </div>
        </div>
      </article>

      <details v-if="understudyVisible && !requestItem?.understudy_reviewed_at" class="admin-accordion-card" open>
        <summary>
          <div>
            <h2>{{ t('adminRequestDetails.understudy.title') }}</h2>
            <p>{{ t('adminRequestDetails.understudy.subtitle') }}</p>
          </div>
        </summary>
        <div class="admin-accordion-card__body">
          <div class="catalog-mini-stats" style="margin-bottom: 1rem;">
            <div><span>{{ t('adminRequestDetails.understudy.stats.studyStatus') }}</span><strong>{{ readableUnderstudyStatus(requestItem?.understudy_status || 'draft') }}</strong></div>
            <div><span>{{ t('adminRequestDetails.understudy.stats.requiredAnswered') }}</span><strong>{{ staffQuestionSummary?.required_answered_count ?? 0 }}/{{ staffQuestionSummary?.required_count ?? 0 }}</strong></div>
            <div><span>{{ t('adminRequestDetails.understudy.stats.allRequiredAnswered') }}</span><strong>{{ staffQuestionSummary?.all_required_answered ? t('adminRequestDetails.states.yes') : t('adminRequestDetails.states.no') }}</strong></div>
          </div>

          <div v-if="requestItem?.understudy_submitted_at" class="notes-box" style="margin-bottom: 1rem;">
            <span>{{ t('adminRequestDetails.understudy.studySubmission') }}</span>
            <p>
              {{ t('adminRequestDetails.understudy.submittedText') }}
              <template v-if="requestItem?.understudy_submitted_by?.name"> · {{ requestItem.understudy_submitted_by.name }}</template>
              <template v-if="requestItem?.understudy_submitted_at"> · {{ formatDateTime(requestItem.understudy_submitted_at, locale, t('adminRequestDetails.states.emptyValue')) }}</template>
            </p>
          </div>

          <div v-if="requestItem?.understudy_note" class="notes-box" style="margin-bottom: 1rem;">
            <span>{{ t('adminRequestDetails.understudy.staffUnderstanding') }}</span>
            <p>{{ requestItem.understudy_note }}</p>
          </div>

          <div v-if="staffQuestions.length" class="qa-list" style="margin-bottom: 1rem;">
            <article v-for="question in staffQuestions" :key="question.id" class="qa-item">
              <div class="client-card-head" style="margin-bottom: 0.75rem; align-items: flex-start;">
                <div>
                  <strong>{{ studyQuestionTitle(question) }}</strong>
                  <p class="client-subtext">{{ question.question_type || question.template?.question_type || 'text' }}<template v-if="question.is_required || question.template?.is_required"> · {{ t('adminRequestDetails.understudy.required') }}</template></p>
                </div>
                <span :class="studyQuestionStatusClass(question)">{{ studyQuestionStatusLabel(question) }}</span>
              </div>
              <p>{{ studyQuestionAnswer(question) }}</p>
              <p v-if="studyQuestionAnswerAudit(question)" class="client-subtext" style="margin-top: 0.35rem;">{{ studyQuestionAnswerAudit(question) }}</p>
              <template v-if="understudyReadyForReview">
                <div class="client-form-group" style="margin-top: 0.75rem;">
                  <label class="client-form-label">{{ uiText('Revision note for staff (optional)', 'ملاحظة المراجعة للموظف (اختياري)') }}</label>
                  <textarea
                    v-model="staffQuestionReviewNotes[question.id]"
                    rows="2"
                    class="client-form-control client-form-control--textarea"
                    :placeholder="uiText('Explain exactly what this answer needs revised.', 'اكتب ما الذي يحتاج إلى مراجعة في هذه الإجابة.')"
                  />
                </div>
                <div class="approve-actions" style="margin-top: 0.75rem; gap: 0.75rem; flex-wrap: wrap;">
                  <button
                    type="button"
                    class="ghost-btn"
                    :disabled="Boolean(reviewingStaffQuestion[question.id]) || String(question.status || '').toLowerCase() === 'pending'"
                    @click="submitStaffQuestionRevision(question)"
                  >
                    {{ reviewingStaffQuestion[question.id] ? t('adminRequestDetails.actions.saving') : uiText('Request revision', 'طلب مراجعة') }}
                  </button>
                </div>
              </template>
            </article>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.understudy.noQuestions') }}</p>

          <div class="client-form-group" style="margin-top: 1rem;">
            <label class="client-form-label">{{ t('adminRequestDetails.understudy.adminReviewNote') }}</label>
            <textarea v-model="understudyReviewNote" rows="4" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.understudy.adminReviewPlaceholder')" />
          </div>

          <div v-if="understudyReadyForReview" class="approve-actions" style="margin-top: 0.75rem; gap: 0.75rem; flex-wrap: wrap;">
            <button type="button" class="primary-btn" :disabled="reviewingUnderstudy || !understudyApproveReady" @click="submitUnderstudyReview('approve')">
              {{ reviewingUnderstudy ? t('adminRequestDetails.actions.saving') : t('adminRequestDetails.understudy.approveForNextStep') }}
            </button>
          </div>

          <p v-if="understudyVisible && understudyReadyForReview && !staffQuestionSummary?.can_advance_from_understudy" class="form-help form-help--error" style="margin-top: 1rem;">
            {{ t('adminRequestDetails.understudy.requiredBeforeReview') }}
          </p>

          <div v-else-if="requestItem?.understudy_reviewed_at" class="notes-box" style="margin-top: 1rem;">
            <span>{{ t('adminRequestDetails.understudy.adminReviewResult') }}</span>
            <p>
              {{ readableUnderstudyStatus(requestItem?.understudy_status || 'draft') }}
              <template v-if="requestItem?.understudy_reviewed_by?.name"> · {{ requestItem.understudy_reviewed_by.name }}</template>
              <template v-if="requestItem?.understudy_reviewed_at"> · {{ formatDateTime(requestItem.understudy_reviewed_at, locale, t('adminRequestDetails.states.emptyValue')) }}</template>
            </p>
            <p v-if="requestItem?.understudy_review_note" style="margin-top: 0.5rem;">{{ requestItem.understudy_review_note }}</p>
          </div>
        </div>
      </details>

      <AdminAgentAccessPanel
        v-if="false && agentAssignmentVisible"
        :active-assignments="activeAgentAssignments"
        :bank-options="emailBankOptions"
        :agent-options="emailAgentOptions"
        :available-documents="availableEmailDocuments"
        :selected-bank-id="selectedAssignmentBankId"
        :selected-agent-ids="selectedAgentIds"
        :selected-agent-document-keys="selectedAgentDocumentKeys"
        :review-note="agentAssignmentReviewNote"
        :saving="savingAgentAssignments"
        :can-save="canSaveAgentAssignments"
        :dirty="agentAssignmentDraftDirty"
        :stage-after-save-label="selectedAgentIds.length ? stageMeta('processing').label : stageMeta('awaiting_agent_assignment').label"
        @update:selected-bank-id="selectedAssignmentBankId = $event"
        @update:review-note="agentAssignmentReviewNote = $event"
        @toggle-agent="toggleAgentSelection"
        @toggle-document="toggleAgentDocument"
        @select-all-documents="selectAllAgentDocuments"
        @clear-documents="clearAgentDocuments"
        @remove-agent="removeAgentFromDraft"
        @reset-draft="syncAgentAssignmentDraftFromRequest"
        @submit="submitAgentAssignments"
        @preview-file="previewAgentAssignmentDocument"
      />

      <details v-if="false && agentAssignmentVisible" class="admin-accordion-card" :open="understudyStage === 'awaiting_agent_assignment' || activeAgentAssignments.length > 0">
        <summary>
          <div>
            <h2>{{ t('adminRequestDetails.agentAssignments.title') }}</h2>
            <p>{{ t('adminRequestDetails.agentAssignments.subtitle') }}</p>
          </div>
        </summary>
        <div class="admin-accordion-card__body">
          <div class="catalog-mini-stats" style="margin-bottom: 1rem;">
            <div><span>{{ t('adminRequestDetails.agentAssignments.stats.activeAgents') }}</span><strong>{{ activeAgentAssignments.length }}</strong></div>
            <div><span>{{ t('adminRequestDetails.agentAssignments.stats.availableFiles') }}</span><strong>{{ availableEmailDocuments.length }}</strong></div>
            <div><span>{{ t('adminRequestDetails.agentAssignments.stats.stageAfterSave') }}</span><strong>{{ stageMeta('processing').label }}</strong></div>
          </div>

          <div class="notes-box" style="margin-bottom: 1rem;">
            <span>{{ t('adminRequestDetails.agentAssignments.howItWorksTitle') }}</span>
            <p>{{ t('adminRequestDetails.agentAssignments.howItWorksBody') }}</p>
          </div>

          <div class="field-block field-block--grow" style="max-width: 22rem; margin-bottom: 1rem;">
            <span>{{ t('adminRequestDetails.agentAssignments.bankFilter') }}</span>
            <select v-model="selectedAssignmentBankId" class="admin-select">
              <option :value="null">{{ t('adminRequestDetails.agentAssignments.allBanks') }}</option>
              <option v-for="bank in emailBankOptions" :key="bank.id" :value="bank.id">
                {{ bank.name }}<template v-if="bank.short_name"> · {{ bank.short_name }}</template>
              </option>
            </select>
          </div>

          <details class="admin-accordion-card slim-accordion request-collapsible">
            <summary>
              <div>
                <h3>{{ t('adminRequestDetails.agentAssignments.selectAgents') }}</h3>
                <p>{{ filteredAssignableAgents.length }} {{ t('adminRequestDetails.agentAssignments.stats.activeAgents') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="admin-inline-block-grid">
            <article class="panel-card slim-card">
              <div class="panel-head"><h3>{{ t('adminRequestDetails.agentAssignments.selectAgents') }}</h3></div>
              <div v-if="filteredAssignableAgents.length" class="timeline-list compact-list">
                <label
                  v-for="agent in filteredAssignableAgents"
                  :key="agent.id"
                  class="timeline-item"
                  style="display: block; cursor: pointer;"
                >
                  <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                    <input
                      :checked="isAgentSelected(agent.id)"
                      type="checkbox"
                      style="margin-top: 0.2rem;"
                      @change="toggleAgentSelection(agent.id, ($event.target as HTMLInputElement).checked)"
                    />
                    <div>
                      <strong>{{ agent.name }}</strong>
                      <p>{{ agent.bank_name || t('adminRequestDetails.agentAssignments.noBankLinked') }}</p>
                      <span>{{ agent.email || agent.phone || agent.company_name || t('adminRequestDetails.agentAssignments.noContactDetails') }}</span>
                    </div>
                  </div>
                </label>
              </div>
              <p v-else class="empty-state">{{ t('adminRequestDetails.agentAssignments.noActiveAgentsForFilter') }}</p>
            </article>

            <article class="panel-card slim-card">
              <div class="panel-head"><h3>{{ t('adminRequestDetails.agentAssignments.linkFilesPerAgent') }}</h3></div>
              <div v-if="selectedAssignableAgents.length" class="timeline-list compact-list">
                <div v-for="agent in selectedAssignableAgents" :key="agent.id" class="timeline-item">
                  <strong>{{ agent.name }}</strong>
                  <p>{{ agent.bank_name || t('adminRequestDetails.agentAssignments.noBankLinked') }}</p>
                  <span>{{ t('adminRequestDetails.agentAssignments.filesSelectedCount', { count: (selectedAgentDocumentKeys[agent.id] ?? []).length }) }}</span>

                  <div class="qa-list" style="margin-top: 0.85rem; gap: 0.5rem;">
                    <label
                      v-for="document in availableEmailDocuments"
                      :key="`${agent.id}-${document.key}`"
                      class="qa-item"
                      style="display: block; cursor: pointer;"
                    >
                      <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                        <input
                          :checked="isAgentDocumentSelected(agent.id, document.key)"
                          type="checkbox"
                          style="margin-top: 0.2rem;"
                          @change="toggleAgentDocument(agent.id, document.key, ($event.target as HTMLInputElement).checked)"
                        />
                        <div>
                          <strong>{{ document.label }}</strong>
                          <p>{{ document.group_label || t('adminRequestDetails.agentAssignments.requestFile') }}</p>
                          <span>{{ document.file_name }}</span>
                          <div v-if="document.download_url" class="approve-actions" style="margin-top: 0.45rem;">
                            <button
                              type="button"
                              class="ghost-btn"
                              @click="openFilePreview(document.file_name, document.download_url)"
                            >
                              {{ t('adminRequestDetails.agentAssignments.previewFile') }}
                            </button>
                            <a :href="document.download_url" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.actions.download') }}</a>
                          </div>
                        </div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
              <p v-else class="empty-state">{{ t('adminRequestDetails.agentAssignments.selectAgentsFirst') }}</p>
            </article>
              </div>
            </div>
          </details>

          <div class="client-form-group" style="margin-top: 1rem;">
            <label class="client-form-label">{{ t('adminRequestDetails.agentAssignments.adminNote') }}</label>
            <textarea v-model="agentAssignmentReviewNote" rows="3" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.agentAssignments.adminNotePlaceholder')" />
          </div>

          <div class="approve-actions" style="margin-top: 0.75rem; gap: 0.75rem; flex-wrap: wrap;">
            <button type="button" class="primary-btn" :disabled="!canSaveAgentAssignments" @click="submitAgentAssignments">
              {{ savingAgentAssignments ? t('adminRequestDetails.actions.saving') : t('adminRequestDetails.agentAssignments.saveAllowedAgents') }}
            </button>
          </div>

          <p v-if="selectedAssignableAgents.length && !canSaveAgentAssignments" class="form-help form-help--error" style="margin-top: 0.75rem;">
            {{ t('adminRequestDetails.agentAssignments.mustLinkAtLeastOneFile') }}
          </p>
        </div>
      </details>

      <div v-if="false" class="admin-workspace-layout admin-workspace-layout--compact-side">
        <div class="admin-workspace-main">
          <article class="panel-card slim-card" style="margin-top: 1.25rem;">
            <div class="panel-head">
              <div>
                <h2>{{ t('adminRequestDetails.updateBatch.title') }}</h2>
                <p class="subtext">{{ t('adminRequestDetails.updateBatch.subtitle') }}</p>
              </div>
            </div>

            <div class="catalog-mini-stats" style="margin-bottom: 1rem;">
              <div><span>{{ t('adminRequestDetails.updateBatch.openBatch') }}</span><strong>{{ activeOpenBatch ? formatUpdateBatchStatus(activeOpenBatch.status, locale, t('adminRequestDetails.states.none')) : t('adminRequestDetails.states.none') }}</strong></div>
              <div><span>{{ t('adminRequestDetails.updateBatch.totalBatches') }}</span><strong>{{ activityCounts.updateBatches }}</strong></div>
            </div>

            <div v-if="!activeOpenBatch" class="qa-list" style="margin-bottom: 1rem;">
              <div class="client-form-group">
                <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.reasonInEnglish') }}</label>
                <textarea v-model="batchReasonEn" rows="3" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.updateBatch.reasonEnPlaceholder')" />
              </div>

              <div class="client-form-group">
                <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.reasonInArabic') }}</label>
                <textarea v-model="batchReasonAr" rows="3" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.updateBatch.reasonArPlaceholder')" />
              </div>

              <div v-for="item in updateDraftItems" :key="item.local_id" class="panel-card slim-card" style="padding: 1rem;">
                <div class="summary-grid summary-grid--compact">
                  <div class="client-form-group">
                    <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.itemType') }}</label>
                    <select v-model="item.item_type" class="client-form-control" @change="onDraftTypeChange(item)">
                      <option value="" disabled>{{ t('adminRequestDetails.updateBatch.selectItemType') }}</option>
                      <option v-for="option in draftItemTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                  </div>

                  <div class="client-form-group" v-if="item.item_type === 'intake_field'">
                    <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.field') }}</label>
                    <select v-model="item.field_key" class="client-form-control">
                      <option :value="null" disabled>{{ t('adminRequestDetails.updateBatch.selectField') }}</option>
                      <option v-for="field in intakeFieldOptions" :key="field.value" :value="field.value">{{ field.label }}</option>
                    </select>
                  </div>

                  <div class="client-form-group" v-else-if="item.item_type === 'request_answer'">
                    <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.question') }}</label>
                    <select v-model="item.question_id" class="client-form-control">
                      <option :value="null" disabled>{{ t('adminRequestDetails.updateBatch.selectQuestion') }}</option>
                      <option v-for="question in questionOptions" :key="question.id" :value="question.id">
                        {{ question.question_text }}
                      </option>
                    </select>
                  </div>

                  <div class="client-form-group" v-else-if="item.item_type === 'attachment'">
                    <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.attachment') }}</label>
                    <select v-model="item.field_key" class="client-form-control">
                      <option :value="null" disabled>{{ t('adminRequestDetails.updateBatch.selectAttachment') }}</option>
                      <option v-for="field in attachmentFieldOptions" :key="field.value" :value="field.value">{{ field.label }}</option>
                    </select>
                  </div>

                  <div class="client-form-group">
                    <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.requiredLabel') }}</label>
                    <select v-model="item.is_required" class="client-form-control">
                      <option :value="true">{{ t('adminRequestDetails.updateBatch.required') }}</option>
                      <option :value="false">{{ t('adminRequestDetails.updateBatch.optional') }}</option>
                    </select>
                  </div>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.customLabel') }}</label>
                  <input v-model="item.label_en" type="text" class="client-form-control" :placeholder="t('adminRequestDetails.updateBatch.customLabelPlaceholder')" />
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.instruction') }}</label>
                  <textarea v-model="item.instruction_en" rows="3" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.updateBatch.instructionPlaceholder')" />
                </div>

                <div class="approve-actions">
                  <button type="button" class="ghost-btn" @click="removeUpdateDraftItem(item.local_id)">{{ t('adminRequestDetails.updateBatch.removeItem') }}</button>
                </div>
              </div>

              <div class="approve-actions">
                <button type="button" class="ghost-btn" @click="addUpdateDraftItem">{{ updateDraftItems.length ? t('adminRequestDetails.updateBatch.addAnotherItem') : t('adminRequestDetails.updateBatch.addFirstItem') }}</button>
                <button type="button" class="primary-btn" :disabled="!canSubmitUpdateBatch" @click="submitUpdateBatch">
                  {{ creatingBatch ? t('adminRequestDetails.updateBatch.creatingBatch') : t('adminRequestDetails.updateBatch.openClientUpdateBatch') }}
                </button>
              </div>
            </div>

            <div v-else class="qa-list">
              <div class="notes-box">
                <span>{{ t('adminRequestDetails.updateBatch.currentBatchReason') }}</span>
                <p>{{ localizedText(activeOpenBatch.reason_en, activeOpenBatch.reason_ar, t('adminRequestDetails.updateBatch.noReasonAdded')) }}</p>
              </div>

              <div class="approve-actions" style="margin-bottom: 1rem;">
                <button type="button" class="ghost-btn" :disabled="cancellingBatch" @click="cancelActiveBatch">
                  {{ cancellingBatch ? t('adminRequestDetails.updateBatch.cancellingBatch') : t('adminRequestDetails.updateBatch.cancelThisUpdateBatch') }}
                </button>
              </div>

              <div v-for="item in activeOpenBatch.items || []" :key="item.id" class="panel-card slim-card" style="padding: 1rem; margin-bottom: 1rem;">
                <div class="client-card-head">
                  <div>
                    <h3>{{ localizedText(item.label_en, item.label_ar, t('adminRequestDetails.updateBatch.requestedUpdate')) }}</h3>
                    <p class="client-subtext">{{ localizedText(item.instruction_en, item.instruction_ar, t('adminRequestDetails.updateBatch.noExtraInstruction')) }}</p>
                  </div>
                  <span :class="updateStatusClass(item.status)">{{ updateStatusLabel(item.status) }}</span>
                </div>

                <div class="summary-grid summary-grid--compact">
                  <div>
                    <span>{{ t('adminRequestDetails.updateBatch.previousValue') }}</span>
                    <strong>{{ formatUpdateValue(item, 'old') }}</strong>
                  </div>
                  <div>
                    <span>{{ t('adminRequestDetails.updateBatch.clientSubmission') }}</span>
                    <strong>{{ formatUpdateValue(item, 'new') }}</strong>
                  </div>
                </div>

                <div class="client-form-group" style="margin-top: 1rem;">
                  <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.reviewNote') }}</label>
                  <textarea v-model="reviewNotes[item.id]" rows="3" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.updateBatch.reviewNotePlaceholder')" />
                </div>

                <div class="approve-actions">
                  <button type="button" class="ghost-btn" :disabled="reviewingUpdateItems[item.id] || item.status !== 'updated'" @click="reviewUpdateItem(item, 'reject')">
                    {{ reviewingUpdateItems[item.id] ? t('adminRequestDetails.actions.saving') : t('adminRequestDetails.actions.reject') }}
                  </button>
                  <button type="button" class="primary-btn" :disabled="reviewingUpdateItems[item.id] || item.status !== 'updated'" @click="reviewUpdateItem(item, 'approve')">
                    {{ reviewingUpdateItems[item.id] ? t('adminRequestDetails.actions.saving') : t('adminRequestDetails.actions.approve') }}
                  </button>
                </div>
              </div>
            </div>
          </article>
        </div>
      </div>
      </div>
    </template>

    <template #support>
      <div class="request-command-support-stack">
        <details class="request-command-rail-card request-command-collapsible request-command-support-card request-command-support-card--overview">
          <summary class="request-command-rail-head">
            <h2>{{ t('adminRequestDetails.core.title') }}</h2>
            <p>{{ t('adminRequestDetails.core.subtitle') }}</p>
          </summary>
          <div class="request-command-support-body">
            <RequestCoreDetailsCard :request="requestItem" :required-documents="requiredDocuments" />
          </div>
        </details>

        <details class="request-command-next request-command-collapsible request-command-support-card" :class="`is-${adminNextAction.tone}`">
          <summary class="request-command-rail-head">
            <span>{{ uiText('Next required action', 'الإجراء المطلوب التالي') }}</span>
            <h2>{{ adminNextAction.title }}</h2>
          </summary>
          <div class="request-command-support-body">
            <p>{{ adminNextAction.body }}</p>
            <div class="request-command-pulse">
              {{ uiText('Stage-aware guidance', 'إرشاد حسب المرحلة') }}
            </div>
          </div>
        </details>

        <details class="request-command-rail-card request-command-collapsible request-command-support-card">
          <summary class="request-command-rail-head">
            <h2>{{ uiText('Quick views', 'عروض سريعة') }}</h2>
            <p>{{ uiText('Open supporting information without stretching the page.', 'افتح المعلومات الداعمة بدون إطالة الصفحة.') }}</p>
          </summary>
          <div class="request-command-quick-grid">
            <button type="button" @click="quickView = 'requiredDocuments'">
              <strong>{{ t('adminRequestDetails.requiredDocuments.title') }}</strong>
              <span>{{ activityCounts.requiredDocuments }}</span>
            </button>
            <button type="button" @click="quickView = 'answers'">
              <strong>{{ t('adminRequestDetails.quick.questionnaire') }}</strong>
              <span>{{ activityCounts.answers }}</span>
            </button>
            <button type="button" @click="quickView = 'comments'">
              <strong>{{ t('adminRequestDetails.quick.comments') }}</strong>
              <span>{{ activityCounts.comments }}</span>
            </button>
            <button type="button" @click="quickView = 'timeline'">
              <strong>{{ t('adminRequestDetails.quick.timeline') }}</strong>
              <span>{{ activityCounts.timeline }}</span>
            </button>
            <button type="button" @click="quickView = 'emails'">
              <strong>{{ t('adminRequestDetails.emailActivity.title') }}</strong>
              <span>{{ activityCounts.emails }}</span>
            </button>
            <button type="button" @click="quickView = 'assignments'">
              <strong>{{ t('adminRequestDetails.quick.assignedStaff') }}</strong>
              <span>{{ activityCounts.assignments }}</span>
            </button>
          </div>
        </details>

        <details class="request-command-rail-card request-command-collapsible request-command-support-card">
          <summary class="request-command-rail-head">
            <h2>{{ uiText('Recent activity', 'آخر النشاط') }}</h2>
            <p>{{ uiText('Latest timeline events for this request.', 'أحدث أحداث المخطط الزمني لهذا الطلب.') }}</p>
          </summary>
          <div class="request-command-support-body">
            <div v-if="recentTimelineRows.length" class="request-command-activity-list">
              <div v-for="(row, index) in recentTimelineRows" :key="row.entry.id ?? `rail-timeline-${index}`" class="request-command-activity">
                <strong>{{ row.entry.title || row.entry.event_type || t('adminRequestDetails.states.system') }}</strong>
                <span>{{ timelineDateLabel(row.entry.created_at) }}</span>
              </div>
            </div>
            <p v-else class="empty-state">{{ t('adminRequestDetails.states.noTimelineEvents') }}</p>
          </div>
        </details>
      </div>
    </template>

    <template #after>
      <AdminQuickViewModal
        :model-value="actionDialog !== null"
        @update:model-value="(value) => { if (!value) actionDialog = null }"
        :title="actionDialogTitle"
        :subtitle="uiText('Complete the action without losing your place in the request details.', 'أنجز الإجراء بدون فقدان مكانك في تفاصيل الطلب.')"
        :wide="actionDialog === 'updateBatch' || actionDialog === 'comment' || actionDialog === 'agentAccess'"
      >
        <div v-if="actionDialog === 'comment'" class="request-dialog-stack">
          <div class="admin-inline-block-grid">
            <div class="field-block field-block--grow">
              <span>{{ uiText('Visibility', 'الظهور') }}</span>
              <select v-model="commentVisibility" class="admin-select">
                <option value="internal">{{ uiText('Internal', 'داخلي') }}</option>
                <option value="admin_only">{{ uiText('Admin only', 'للإدارة فقط') }}</option>
                <option value="client_visible">{{ uiText('Client visible', 'مرئي للعميل') }}</option>
              </select>
            </div>
          </div>

          <textarea
            v-model="commentText"
            rows="4"
            class="admin-textarea"
            :placeholder="uiText('Write a clear follow-up comment for this request.', 'اكتب تعليق متابعة واضح لهذا الطلب.')"
          />

          <div class="approve-actions">
            <button class="primary-btn" type="button" :disabled="savingComment || !commentText.trim()" @click="submitComment">
              {{ savingComment ? t('adminRequestDetails.actions.saving') : uiText('Save comment', 'حفظ التعليق') }}
            </button>
            <button type="button" class="ghost-btn" @click="actionDialog = null; quickView = 'comments'">
              {{ uiText('Open all comments', 'فتح جميع التعليقات') }}
            </button>
          </div>

          <div v-if="requestItem.comments?.length" class="timeline-list compact-list">
            <div
              v-for="comment in (requestItem.comments || []).slice(0, 3)"
              :key="`comment-dialog-${comment.id}`"
              class="timeline-item"
            >
              <strong>{{ comment.user?.name || t('adminRequestDetails.states.system') }}</strong>
              <p>{{ comment.comment_text }}</p>
              <span>{{ formatDateTime(comment.created_at, locale, t('adminRequestDetails.states.emptyValue')) }} · {{ readableCommentVisibility(comment.visibility) }}</span>
            </div>
          </div>
        </div>

        <div v-else-if="actionDialog === 'reject'" class="request-dialog-stack">
          <p class="subtext">{{ t('adminRequestDetails.rejectRequest.subtitle') }}</p>
          <textarea v-model="rejectReason" rows="4" class="admin-textarea" :placeholder="t('adminRequestDetails.rejectRequest.placeholder')"></textarea>
          <div class="approve-actions">
            <button class="ghost-btn" type="button" :disabled="rejectingRequest" @click="rejectRequest">
              {{ rejectingRequest ? t('adminRequestDetails.rejectRequest.rejecting') : t('adminRequestDetails.rejectRequest.rejectNow') }}
            </button>
          </div>
        </div>

        <div v-else-if="actionDialog === 'approve'" class="request-dialog-stack">
          <p class="subtext">{{ t('adminRequestDetails.sections.approveSubtitle') }}</p>
          <textarea v-model="approvalNotes" rows="5" class="admin-textarea" :placeholder="t('adminRequestDetails.sections.approvePlaceholder')"></textarea>
          <div class="approve-actions">
            <button class="primary-btn" type="button" :disabled="approving" @click="approveRequest">
              {{ approving ? t('adminRequestDetails.actions.approving') : t('adminRequestDetails.actions.approveAndContinue') }}
            </button>
          </div>
        </div>

        <div v-else-if="actionDialog === 'finalApprove'" class="request-dialog-stack">
          <p class="subtext">{{ t('adminRequestDetails.sections.finalApproveSubtitle') }}</p>
          <textarea v-model="finalApprovalNotes" rows="4" class="admin-textarea" :placeholder="t('adminRequestDetails.sections.finalApprovePlaceholder')"></textarea>
          <div class="client-form-group">
            <label class="client-form-label">{{ uiText('Final approval attachment', 'مرفق الاعتماد النهائي') }}</label>
            <input
              type="file"
              multiple
              class="client-form-control"
              accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
              @change="onFinalApprovalAttachmentChange"
            />
            <p class="form-help">{{ uiText('Attach the bank final approval file that the client should receive.', 'أرفق ملف الاعتماد النهائي من البنك ليتمكن العميل من الاطلاع عليه.') }}</p>
            <div v-if="finalApprovalAttachments.length" class="file-list compact-list" style="margin-top: 0.75rem;">
              <div v-for="(file, index) in finalApprovalAttachments" :key="`${file.name}-${index}`" class="file-item">
                <div>
                  <strong>{{ file.name }}</strong>
                  <span>{{ file.type || uiText('Selected file', 'ملف محدد') }}</span>
                </div>
                <button type="button" class="ghost-btn" @click="removeFinalApprovalAttachment(index)">
                  {{ uiText('Remove', 'إزالة') }}
                </button>
              </div>
            </div>
          </div>
          <div class="approve-actions">
            <button class="primary-btn" type="button" :disabled="finalizingApproval" @click="finalizeRequest">
              {{ finalizingApproval ? t('adminRequestDetails.actions.finalizingApproval') : t('adminRequestDetails.actions.finalApproveNow') }}
            </button>
          </div>
        </div>

        <div v-else-if="actionDialog === 'updateBatch'" class="request-dialog-stack">
          <div class="catalog-mini-stats request-kpi-grid request-kpi-grid--two">
            <div><span>{{ t('adminRequestDetails.updateBatch.openBatch') }}</span><strong>{{ activeOpenBatch ? formatUpdateBatchStatus(activeOpenBatch.status, locale, t('adminRequestDetails.states.none')) : t('adminRequestDetails.states.none') }}</strong></div>
            <div><span>{{ t('adminRequestDetails.updateBatch.totalBatches') }}</span><strong>{{ activityCounts.updateBatches }}</strong></div>
          </div>

          <div v-if="!activeOpenBatch" class="request-dialog-stack">
            <div class="request-dialog-grid">
              <div class="client-form-group">
                <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.reasonInEnglish') }}</label>
                <textarea v-model="batchReasonEn" rows="3" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.updateBatch.reasonEnPlaceholder')" />
              </div>

              <div class="client-form-group">
                <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.reasonInArabic') }}</label>
                <textarea v-model="batchReasonAr" rows="3" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.updateBatch.reasonArPlaceholder')" />
              </div>
            </div>

            <div v-for="item in updateDraftItems" :key="item.local_id" class="panel-card slim-card request-draft-item">
              <div class="summary-grid summary-grid--compact">
                <div class="client-form-group">
                  <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.itemType') }}</label>
                  <select v-model="item.item_type" class="client-form-control" @change="onDraftTypeChange(item)">
                    <option value="" disabled>{{ t('adminRequestDetails.updateBatch.selectItemType') }}</option>
                    <option v-for="option in draftItemTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </div>

                <div class="client-form-group" v-if="item.item_type === 'intake_field'">
                  <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.field') }}</label>
                  <select v-model="item.field_key" class="client-form-control">
                    <option :value="null" disabled>{{ t('adminRequestDetails.updateBatch.selectField') }}</option>
                    <option v-for="field in intakeFieldOptions" :key="field.value" :value="field.value">{{ field.label }}</option>
                  </select>
                </div>

                <div class="client-form-group" v-else-if="item.item_type === 'request_answer'">
                  <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.question') }}</label>
                  <select v-model="item.question_id" class="client-form-control">
                    <option :value="null" disabled>{{ t('adminRequestDetails.updateBatch.selectQuestion') }}</option>
                    <option v-for="question in questionOptions" :key="question.id" :value="question.id">
                      {{ question.question_text }}
                    </option>
                  </select>
                </div>

                <div class="client-form-group" v-else-if="item.item_type === 'attachment'">
                  <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.attachment') }}</label>
                  <select v-model="item.field_key" class="client-form-control">
                    <option :value="null" disabled>{{ t('adminRequestDetails.updateBatch.selectAttachment') }}</option>
                    <option v-for="field in attachmentFieldOptions" :key="field.value" :value="field.value">{{ field.label }}</option>
                  </select>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.requiredLabel') }}</label>
                  <select v-model="item.is_required" class="client-form-control">
                    <option :value="true">{{ t('adminRequestDetails.updateBatch.required') }}</option>
                    <option :value="false">{{ t('adminRequestDetails.updateBatch.optional') }}</option>
                  </select>
                </div>
              </div>

              <div class="client-form-group">
                <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.customLabel') }}</label>
                <input v-model="item.label_en" type="text" class="client-form-control" :placeholder="t('adminRequestDetails.updateBatch.customLabelPlaceholder')" />
              </div>

              <div class="client-form-group">
                <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.instruction') }}</label>
                <textarea v-model="item.instruction_en" rows="3" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.updateBatch.instructionPlaceholder')" />
              </div>

              <div class="approve-actions">
                <button type="button" class="ghost-btn" @click="removeUpdateDraftItem(item.local_id)">{{ t('adminRequestDetails.updateBatch.removeItem') }}</button>
              </div>
            </div>

            <div class="approve-actions">
              <button type="button" class="ghost-btn" @click="addUpdateDraftItem">{{ updateDraftItems.length ? t('adminRequestDetails.updateBatch.addAnotherItem') : t('adminRequestDetails.updateBatch.addFirstItem') }}</button>
              <button type="button" class="primary-btn" :disabled="!canSubmitUpdateBatch" @click="submitUpdateBatch">
                {{ creatingBatch ? t('adminRequestDetails.updateBatch.creatingBatch') : t('adminRequestDetails.updateBatch.openClientUpdateBatch') }}
              </button>
            </div>
          </div>

          <div v-else class="request-dialog-stack">
            <div class="notes-box">
              <span>{{ t('adminRequestDetails.updateBatch.currentBatchReason') }}</span>
              <p>{{ localizedText(activeOpenBatch.reason_en, activeOpenBatch.reason_ar, t('adminRequestDetails.updateBatch.noReasonAdded')) }}</p>
            </div>

            <div class="approve-actions">
              <button type="button" class="ghost-btn" :disabled="cancellingBatch" @click="cancelActiveBatch">
                {{ cancellingBatch ? t('adminRequestDetails.updateBatch.cancellingBatch') : t('adminRequestDetails.updateBatch.cancelThisUpdateBatch') }}
              </button>
            </div>

            <div v-for="item in activeOpenBatch.items || []" :key="item.id" class="panel-card slim-card request-draft-item">
              <div class="client-card-head">
                <div>
                  <h3>{{ localizedText(item.label_en, item.label_ar, t('adminRequestDetails.updateBatch.requestedUpdate')) }}</h3>
                  <p class="client-subtext">{{ localizedText(item.instruction_en, item.instruction_ar, t('adminRequestDetails.updateBatch.noExtraInstruction')) }}</p>
                </div>
                <span :class="updateStatusClass(item.status)">{{ updateStatusLabel(item.status) }}</span>
              </div>

              <div class="summary-grid summary-grid--compact">
                <div>
                  <span>{{ t('adminRequestDetails.updateBatch.previousValue') }}</span>
                  <strong>{{ formatUpdateValue(item, 'old') }}</strong>
                </div>
                <div>
                  <span>{{ t('adminRequestDetails.updateBatch.clientSubmission') }}</span>
                  <strong>{{ formatUpdateValue(item, 'new') }}</strong>
                </div>
              </div>

              <div class="client-form-group">
                <label class="client-form-label">{{ t('adminRequestDetails.updateBatch.reviewNote') }}</label>
                <textarea v-model="reviewNotes[item.id]" rows="3" class="client-form-control client-form-control--textarea" :placeholder="t('adminRequestDetails.updateBatch.reviewNotePlaceholder')" />
              </div>

              <div class="approve-actions">
                <button type="button" class="ghost-btn" :disabled="reviewingUpdateItems[item.id] || item.status !== 'updated'" @click="reviewUpdateItem(item, 'reject')">
                  {{ reviewingUpdateItems[item.id] ? t('adminRequestDetails.actions.saving') : t('adminRequestDetails.actions.reject') }}
                </button>
                <button type="button" class="primary-btn" :disabled="reviewingUpdateItems[item.id] || item.status !== 'updated'" @click="reviewUpdateItem(item, 'approve')">
                  {{ reviewingUpdateItems[item.id] ? t('adminRequestDetails.actions.saving') : t('adminRequestDetails.actions.approve') }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="actionDialog === 'agentAccess'" class="request-dialog-stack">
          <AdminAgentAccessPanel
            :active-assignments="activeAgentAssignments"
            :bank-options="emailBankOptions"
            :agent-options="emailAgentOptions"
            :available-documents="availableEmailDocuments"
            :selected-bank-id="selectedAssignmentBankId"
            :selected-agent-ids="selectedAgentIds"
            :selected-agent-document-keys="selectedAgentDocumentKeys"
            :review-note="agentAssignmentReviewNote"
            :saving="savingAgentAssignments"
            :can-save="canSaveAgentAssignments"
            :dirty="agentAssignmentDraftDirty"
            :stage-after-save-label="selectedAgentIds.length ? stageMeta('processing').label : stageMeta('awaiting_agent_assignment').label"
            @update:selected-bank-id="selectedAssignmentBankId = $event"
            @update:review-note="agentAssignmentReviewNote = $event"
            @toggle-agent="toggleAgentSelection"
            @toggle-document="toggleAgentDocument"
            @select-all-documents="selectAllAgentDocuments"
            @clear-documents="clearAgentDocuments"
            @remove-agent="removeAgentFromDraft"
            @reset-draft="syncAgentAssignmentDraftFromRequest"
            @submit="submitAgentAssignments"
            @preview-file="previewAgentAssignmentDocument"
          />
        </div>
      </AdminQuickViewModal>

      <AdminQuickViewModal
        :model-value="quickView !== null"
        @update:model-value="(value) => { if (!value) quickView = null }"
        :title="quickViewTitle"
        :subtitle="t('adminRequestDetails.modal.subtitle')"
        wide
      >
        <div v-if="quickView === 'requiredDocuments'" class="checklist-grid">
          <article v-if="requiredDocuments.length" v-for="item in requiredDocuments" :key="item.document_upload_step_id" class="checklist-card" :class="{ 'is-complete': item.is_uploaded && !item.is_change_requested }">
            <div class="checklist-card__head">
              <strong>{{ item.name }}</strong>
              <span class="status-badge">
                {{ item.is_change_requested ? t('adminRequestDetails.requiredDocuments.status.changeRequested') : item.is_uploaded ? t('adminRequestDetails.requiredDocuments.status.uploaded') : t('adminRequestDetails.requiredDocuments.status.pending') }}
              </span>
            </div>
            <p>{{ item.is_uploaded || item.is_change_requested ? t('adminRequestDetails.requiredDocuments.latestFileLabel', { file: latestRequiredUpload(item)?.file_name || t('adminRequestDetails.requiredDocuments.uploadedFileFallback') }) : t('adminRequestDetails.requiredDocuments.waitingForClient') }}</p>
            <p v-if="item.rejection_reason" class="form-help form-help--error">{{ t('adminRequestDetails.requiredDocuments.reasonLabel', { reason: item.rejection_reason }) }}</p>

            <div v-if="normalizedRequiredUploads(item).length" class="file-list request-inline-stack">
              <div v-for="upload in normalizedRequiredUploads(item)" :key="`required-upload-${item.document_upload_step_id}-${upload.id}`" class="file-item">
                <div>
                  <strong>{{ upload.file_name || t('adminRequestDetails.requiredDocuments.uploadedFileFallback') }}</strong>
                  <span>
                    {{ inlineText('Status', 'الحالة') }}: {{ requiredUploadStatusLabel(upload.status) }}
                    <template v-if="upload.uploaded_at"> · {{ formatDateTime(upload.uploaded_at, locale, t('adminRequestDetails.states.emptyValue')) }}</template>
                  </span>
                </div>
                <div class="approve-actions">
                  <button
                    type="button"
                    class="ghost-btn"
                    @click="openFilePreview(upload.file_name, requiredDocumentDownloadUrl(upload.id), upload.mime_type || upload.file_extension)"
                  >
                    {{ t('adminRequestDetails.agentAssignments.previewFile') }}
                  </button>
                  <a :href="requiredDocumentDownloadUrl(upload.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.actions.download') }}</a>
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
                {{ inlineText('Download all files', 'تنزيل جميع الملفات') }}
              </a>
            </div>
          </article>
          <p v-else class="empty-state">{{ t('adminRequestDetails.requiredDocuments.empty') }}</p>
        </div>

        <div v-else-if="quickView === 'additionalDocuments'" class="timeline-list compact-list">
          <div v-if="requestItem.additional_documents?.length" v-for="item in requestItem.additional_documents" :key="item.id" class="timeline-item">
            <strong>{{ item.title || t('adminRequestDetails.additionalDocuments.fallbackTitle') }}</strong>
            <p>{{ item.reason || t('adminRequestDetails.additionalDocuments.noReason') }}</p>
            <span>{{ readableAdditionalDocumentStatus(item.status) }}<template v-if="item.file_name"> · {{ item.file_name }}</template></span>
            <div v-if="item.file_name" class="approve-actions">
              <button
                type="button"
                class="ghost-btn"
                @click="openFilePreview(item.file_name, additionalDocumentDownloadUrl(item.id))"
              >
                {{ t('adminRequestDetails.agentAssignments.previewFile') }}
              </button>
              <a :href="additionalDocumentDownloadUrl(item.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.additionalDocuments.downloadFile') }}</a>
            </div>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.additionalDocuments.empty') }}</p>
        </div>

        <RequestAnswersList
          v-else-if="quickView === 'answers'"
          :answers="requestItem.answers || []"
          :empty-text="t('adminRequestDetails.states.noAnswersRecorded')"
          :question-fallback="t('adminRequestDetails.states.questionFallback')"
          :format-answer="answerText"
        />

        <div v-else-if="quickView === 'attachments'" class="file-list">
          <template v-if="requestItem.attachments?.length">
            <div v-if="requestItem.attachments.length > 1" class="approve-actions" style="margin-bottom: 0.7rem;">
              <a :href="attachmentBundleDownloadUrl()" target="_blank" rel="noopener" class="ghost-btn">
                {{ inlineText('Download all files', 'تنزيل جميع الملفات') }}
              </a>
            </div>
            <div v-for="file in requestItem.attachments" :key="file.id" class="file-item">
              <div>
                <strong>{{ file.file_name }}</strong>
                <span>{{ file.category }}</span>
              </div>
              <div class="approve-actions">
                <button
                  type="button"
                  class="ghost-btn"
                  @click="openFilePreview(file.file_name, attachmentDownloadUrl(file.id))"
                >
                  {{ t('adminRequestDetails.agentAssignments.previewFile') }}
                </button>
                <a :href="attachmentDownloadUrl(file.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.actions.download') }}</a>
              </div>
            </div>
          </template>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noInitialAttachments') }}</p>
        </div>

        <div v-else-if="quickView === 'shareholders'" class="qa-list">
          <div v-if="requestItem.shareholders?.length" v-for="shareholder in requestItem.shareholders" :key="shareholder.id" class="qa-item">
            <strong>{{ shareholder.shareholder_name }}</strong>
            <p v-if="shareholder.phone_number">{{ [shareholder.phone_country_code, shareholder.phone_number].filter(Boolean).join(' ') }}</p>
            <p v-if="shareholder.id_number">{{ t('adminRequestDetails.states.idNumberLabel', { id: shareholder.id_number }) }}</p>
            <p>{{ shareholder.id_file_name }}</p>
            <div class="approve-actions">
              <button
                type="button"
                class="ghost-btn"
                @click="openFilePreview(shareholder.id_file_name, shareholderIdDownloadUrl(shareholder.id))"
              >
                {{ t('adminRequestDetails.agentAssignments.previewFile') }}
              </button>
              <a :href="shareholderIdDownloadUrl(shareholder.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.actions.downloadIdFile') }}</a>
            </div>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noShareholdersRecorded') }}</p>
        </div>

        <div v-else-if="quickView === 'assignments'" class="assignment-chip-list assignment-chip-list--stacked">
          <div v-if="requestItem.assignments?.length" v-for="assignment in requestItem.assignments" :key="assignment.id" class="assignment-chip">
            <strong>{{ assignment.staff?.name || t('adminRequestDetails.states.staffMemberFallback') }}</strong>
            <span>{{ assignment.is_primary ? t('adminRequestDetails.states.leadOwner') : assignment.assignment_role || t('adminRequestDetails.states.support') }}</span>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noStaffAssigned') }}</p>
        </div>

        <div v-else-if="quickView === 'comments'" class="timeline-list">
          <div v-if="requestItem.comments?.length" v-for="comment in requestItem.comments" :key="comment.id" class="timeline-item">
            <strong>{{ comment.user?.name || t('adminRequestDetails.states.system') }}</strong>
            <p>{{ comment.comment_text }}</p>
            <span>{{ formatDateTime(comment.created_at, locale, t('adminRequestDetails.states.emptyValue')) }} · {{ readableCommentVisibility(comment.visibility) }}</span>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noCommentsRecorded') }}</p>
        </div>

        <div v-else-if="quickView === 'emails'" class="timeline-list compact-list">
          <div v-if="requestItem.emails?.length" v-for="email in requestItem.emails" :key="email.id" class="timeline-item">
            <div class="request-modal-meta">
              <div>
                <strong>{{ email.subject }}</strong>
                <p>{{ t('adminRequestDetails.emailActivity.fromLabel') }} {{ email.sender?.name || t('adminRequestDetails.states.system') }} · {{ email.from_email || email.sender?.email || t('adminRequestDetails.states.emptyValue') }}</p>
                <p>{{ t('adminRequestDetails.emailActivity.toLabel') }} {{ emailRecipients(email) }}</p>
                <p v-if="email.body" class="request-prewrap-text">{{ email.body }}</p>
                <span>{{ t('adminRequestDetails.emailActivity.attachmentsCount', { count: email.attachments?.length || 0 }) }} · {{ formatDateTime(email.sent_at || email.created_at, locale, t('adminRequestDetails.states.emptyValue')) }}</span>
              </div>
              <span :class="emailStatusClass(email.delivery_status)">{{ readableEmailDeliveryStatus(email.delivery_status) }}</span>
            </div>

            <div v-if="email.attachments?.length" class="file-list request-inline-stack">
              <div v-for="attachment in email.attachments" :key="attachment.id" class="file-item">
                <div>
                  <strong>{{ attachment.file_name }}</strong>
                  <span>{{ attachment.mime_type || attachment.file_extension || t('adminRequestDetails.emailActivity.storedRequestFile') }} · {{ formatFileSize(attachment.file_size) }}</span>
                </div>
                <div class="approve-actions">
                  <button
                    type="button"
                    class="ghost-btn"
                    @click="openFilePreview(attachment.file_name, emailAttachmentDownloadUrl(email.id, attachment.id), attachment.mime_type || attachment.file_extension)"
                  >
                    {{ t('adminRequestDetails.agentAssignments.previewFile') }}
                  </button>
                  <a :href="emailAttachmentDownloadUrl(email.id, attachment.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.actions.download') }}</a>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.emailActivity.empty') }}</p>
        </div>

        <div v-else class="timeline-list">
          <template v-if="timelineRows.length">
            <template v-for="(row, index) in timelineRows" :key="row.entry.id ?? `${row.entry.event_type ?? 'timeline'}-${index}`">
              <div v-if="row.gapLabel" class="timeline-gap-indicator">{{ row.gapLabel }}</div>
              <div class="timeline-item timeline-item--event">
                <strong>{{ row.entry.event_title || row.entry.event_type }}</strong>
                <p>{{ row.entry.event_description || t('adminRequestDetails.states.emptyValue') }}</p>
                <span>{{ timelineDateLabel(row.entry.created_at) }}</span>
              </div>
            </template>
          </template>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noTimelineEvents') }}</p>
        </div>
      </AdminQuickViewModal>
    </template>

    <AppFilePreviewModal
      :model-value="filePreviewOpen"
      @update:model-value="(value) => { filePreviewOpen = value }"
      title="File preview"
      :file-name="filePreviewName"
      :mime-type="filePreviewMime"
      :preview-url="filePreviewUrl"
      :download-url="fileDownloadUrl"
    />
  </RequestWorkspaceShell>
</template>

<style scoped>
.admin-workflow-stage-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 1rem;
}

.admin-workflow-stage-field {
  flex: 1 1 240px;
  margin: 0;
}

.admin-workflow-stage-apply {
  flex: 0 0 auto;
}
</style>
