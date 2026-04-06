import api from './api'
import type { PaginationMeta } from '@/types/pagination'

export type RequestAssignment = {
  id: number
  staff_id: number
  assigned_at?: string | null
  assignment_role?: string | null
  is_primary: boolean
  notes?: string | null
  staff?: {
    id: number
    name: string
    email?: string | null
  } | null
  assigned_by?: {
    id: number
    name: string
    email?: string | null
  } | null
}

export type RequestComment = {
  id: number
  comment_text: string
  visibility: string
  created_at: string
  user?: {
    id: number
    name: string
    email?: string | null
  } | null
}

export type RequiredDocumentChecklistItem = {
  document_upload_step_id: number
  code?: string | null
  name: string
  is_required: boolean
  is_multiple?: boolean
  status: string
  is_uploaded: boolean
  can_client_upload?: boolean
  is_change_requested?: boolean
  rejection_reason?: string | null
  uploads_count?: number
  accepted_uploads_count?: number
  upload?: {
    id: number
    file_name: string
    file_path: string
    status: string
    uploaded_at?: string | null
  } | null
  uploads?: Array<{
    id: number
    file_name: string
    file_path: string
    status: string
    uploaded_at?: string | null
  }>
}

export type AdditionalDocumentItem = {
  id: number
  title: string
  reason?: string | null
  status: string
  requested_at?: string | null
  uploaded_at?: string | null
  file_name?: string | null
  file_path?: string | null
  rejection_reason?: string | null
  requester?: {
    id: number
    name: string
    email?: string | null
  } | null
  uploader?: {
    id: number
    name: string
    email?: string | null
  } | null
}


export type StaffStudyQuestion = {
  id: number
  question_text_en: string
  question_text_ar?: string | null
  answer_text?: string | null
  answer_json?: string[] | null
  question_type?: string | null
  options_json?: string[] | null
  placeholder_en?: string | null
  placeholder_ar?: string | null
  help_text_en?: string | null
  help_text_ar?: string | null
  is_required: boolean
  status: string
  answered_at?: string | null
  closed_at?: string | null
  asked_by?: number | null
  assigned_to?: number | null
  template?: {
    id: number
    code?: string | null
    question_text_en: string
    question_text_ar?: string | null
    question_type?: string | null
    options_json?: string[] | null
    placeholder_en?: string | null
    placeholder_ar?: string | null
    help_text_en?: string | null
    help_text_ar?: string | null
    is_required?: boolean
    sort_order?: number | null
  } | null
  asker?: {
    id: number
    name: string
    email?: string | null
  } | null
  assigned_staff?: {
    id: number
    name: string
    email?: string | null
  } | null
}

export type StaffQuestionSummary = {
  total: number
  required_total: number
  pending_total: number
  answered_total: number
  closed_total: number
  pending_required_total: number
  all_required_answered: boolean
  can_advance_from_understudy: boolean
}

export type StaffWorkspaceRequestSummary = {
  id: number
  reference_number: string
  approval_reference_number?: string | null
  company_name?: string | null
  country_code?: string | null
  status: string
  workflow_stage: string
  submitted_at?: string | null
  latest_activity_at?: string | null
  latest_assignment_at?: string | null
  intake_details_json?: Record<string, unknown> | null
  comments_count?: number
  client?: {
    id: number
    name: string
    email?: string | null
  } | null
  current_contract?: {
    id: number
    version_no?: number
    status: string
    client_signed_at?: string | null
  } | null
  assignments?: RequestAssignment[]
}

export type StaffWorkspaceRequestDetails = StaffWorkspaceRequestSummary & {
  attachments?: Array<{
    id: number
    file_name: string
    file_path: string
    category: string
    created_at?: string | null
  }>
    shareholders?: Array<{
    id: number
    shareholder_name: string
    phone_country_code?: string | null
    phone_number?: string | null
    id_number?: string | null
    id_file_name: string
    id_file_path: string
    created_at?: string | null
  }>
  answers?: Array<{
    id: number
    answer_text?: string | null
    answer_value_json?: unknown
    question?: {
      id: number
      code?: string | null
      question_text: string
      question_type?: string | null
      sort_order?: number | null
    } | null
  }>
  comments?: RequestComment[]
  timeline?: Array<{
    id: number
    event_type: string
    event_title?: string | null
    event_description?: string | null
    created_at: string
    actor?: {
      id: number
      name: string
    } | null
  }>
  current_contract?: {
    id: number
    version_no: number
    status: string
    contract_pdf_path?: string | null
    admin_signed_at?: string | null
    client_signed_at?: string | null
  } | null
  additional_documents?: AdditionalDocumentItem[]
  staff_questions?: StaffStudyQuestion[]
  understudy_status?: string | null
  understudy_note?: string | null
  understudy_submitted_at?: string | null
  understudy_reviewed_at?: string | null
  understudy_review_note?: string | null
  understudy_submitted_by?: {
    id: number
    name: string
    email?: string | null
  } | null
  understudy_reviewed_by?: {
    id: number
    name: string
    email?: string | null
  } | null
  emails?: RequestEmailLog[]
}

export type AgentOption = {
  id: number
  name: string
  email?: string | null
  phone?: string | null
  company_name?: string | null
  agent_type?: string | null
  bank_id?: number | null
  bank_name?: string | null
  bank_short_name?: string | null
  bank_code?: string | null
}

export type BankOption = {
  id: number
  name: string
  short_name?: string | null
  code?: string | null
}

export type AllowedEmailDocument = {
  key: string
  document_type: string
  document_id?: number | null
  group_label?: string | null
  label: string
  file_name: string
  download_url?: string | null
  agent_id?: number | null
  agent_name?: string | null
  bank_id?: number | null
  agent_ids?: number[]
  agent_names?: string[]
}

export type RequestEmailLog = {
  id: number
  subject: string
  body?: string | null
  delivery_status?: string | null
  from_email?: string | null
  sent_at?: string | null
  created_at?: string | null
  sender?: {
    id: number
    name: string
    email?: string | null
  } | null
  agents?: Array<{
    id: number
    name: string
    email?: string | null
    bank?: {
      id: number
      name: string
      short_name?: string | null
      code?: string | null
    } | null
  }>
  attachments?: Array<{
    id: number
    file_name: string
    file_path: string
    disk?: string | null
    mime_type?: string | null
  }>
}

export async function getStaffRequests(params?: { search?: string; workflow_stage?: string; page?: number; per_page?: number }) {
  const { data } = await api.get('/api/staff/requests', { params })
  return data as { requests: StaffWorkspaceRequestSummary[]; pagination: PaginationMeta }
}

export async function getStaffRequest(id: string | number) {
  const { data } = await api.get(`/api/staff/requests/${id}`)
  return data as {
    request: StaffWorkspaceRequestDetails
    required_documents: RequiredDocumentChecklistItem[]
    staff_question_summary: StaffQuestionSummary
  }
}

export async function answerStaffQuestion(
  id: string | number,
  questionId: string | number,
  payload: { answer_text?: string | null; answer_json?: string[] | null },
) {
  const { data } = await api.patch(`/api/staff/requests/${id}/staff-questions/${questionId}/answer`, payload)
  return data as {
    message: string
    staff_question: StaffStudyQuestion
    request: StaffWorkspaceRequestDetails
    required_documents: RequiredDocumentChecklistItem[]
    staff_question_summary: StaffQuestionSummary
  }
}

export async function saveUnderstudyDraft(
  id: string | number,
  payload: { understudy_note?: string | null },
) {
  const { data } = await api.patch(`/api/staff/requests/${id}/understudy-draft`, payload)
  return data as {
    message: string
    request: StaffWorkspaceRequestDetails
    required_documents: RequiredDocumentChecklistItem[]
    staff_question_summary: StaffQuestionSummary
  }
}

export async function submitUnderstudy(
  id: string | number,
  payload: { understudy_note: string },
) {
  const { data } = await api.post(`/api/staff/requests/${id}/understudy-submit`, payload)
  return data as {
    message: string
    request: StaffWorkspaceRequestDetails
    required_documents: RequiredDocumentChecklistItem[]
    staff_question_summary: StaffQuestionSummary
  }
}

export async function addStaffComment(
  id: string | number,
  payload: { comment_text: string; visibility?: 'internal' | 'admin_only' | 'client_visible' },
) {
  const { data } = await api.post(`/api/staff/requests/${id}/comments`, payload)
  return data as {
    message: string
    comment: RequestComment
    request: StaffWorkspaceRequestDetails
    required_documents: RequiredDocumentChecklistItem[]
  }
}


export async function requestRequiredDocumentChange(
  id: string | number,
  stepId: string | number,
  payload: { reason: string },
) {
  const { data } = await api.post(`/api/staff/requests/${id}/required-documents/${stepId}/request-change`, payload)
  return data as {
    message: string
    request: StaffWorkspaceRequestDetails
    required_documents: RequiredDocumentChecklistItem[]
  }
}

export async function requestAdditionalDocument(
  id: string | number,
  payload: { title: string; reason?: string | null },
) {
  const { data } = await api.post(`/api/staff/requests/${id}/additional-documents`, payload)
  return data as {
    message: string
    request: StaffWorkspaceRequestDetails
    required_documents: RequiredDocumentChecklistItem[]
  }
}

export async function getStaffAgents(params?: { bank_id?: number | null }) {
  const { data } = await api.get('/api/staff/agents', { params })
  return data as { banks: BankOption[]; agents: AgentOption[] }
}

export async function getStaffRequestEmailOptions(
  id: string | number,
  params?: { bank_id?: number | null; agent_id?: number | null },
) {
  const { data } = await api.get(`/api/staff/requests/${id}/email-options`, { params })
  return data as {
    banks: BankOption[]
    agents: AgentOption[]
    allowed_documents: AllowedEmailDocument[]
    has_assignments: boolean
    can_email: boolean
  }
}

export async function sendStaffRequestEmail(
  id: string | number,
  payload: {
    bank_id?: number | null
    agent_id: number
    document_keys?: string[]
    subject: string
    body?: string | null
  },
) {
  const { data } = await api.post(`/api/staff/requests/${id}/send-email`, payload)
  return data as {
    message: string
    email: RequestEmailLog
    request: StaffWorkspaceRequestDetails
    required_documents: RequiredDocumentChecklistItem[]
    staff_question_summary: StaffQuestionSummary
    banks: BankOption[]
    agents: AgentOption[]
    allowed_documents: AllowedEmailDocument[]
    has_assignments: boolean
    can_email: boolean
  }
}
export function staffAttachmentDownloadUrl(requestId: string | number, attachmentId: string | number) {
  return `/api/admin/requests/${requestId}/attachments/${attachmentId}/download`
}

export function staffAttachmentsBundleDownloadUrl(requestId: string | number) {
  return `/api/admin/requests/${requestId}/attachments/download-all`
}

export function staffShareholderIdDownloadUrl(requestId: string | number, shareholderId: string | number) {
  return `/api/admin/requests/${requestId}/shareholders/${shareholderId}/id-file/download`
}

export function staffRequiredDocumentStepBundleDownloadUrl(
  requestId: string | number,
  stepId: string | number,
) {
  return `/api/admin/requests/${requestId}/required-documents/steps/${stepId}/download-all`
}

export function staffRequestEmailAttachmentDownloadUrl(
  requestId: string | number,
  requestEmailId: string | number,
  attachmentId: string | number,
) {
  return `/api/admin/requests/${requestId}/emails/${requestEmailId}/attachments/${attachmentId}/download`
}
