import api from './api'

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
  status: string
  is_uploaded: boolean
  can_client_upload?: boolean
  is_change_requested?: boolean
  rejection_reason?: string | null
  upload?: {
    id: number
    file_name: string
    file_path: string
    status: string
    uploaded_at?: string | null
  } | null
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

export type StaffWorkspaceRequestSummary = {
  id: number
  reference_number: string
  approval_reference_number?: string | null
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

export async function getStaffRequests(params?: { search?: string; workflow_stage?: string }) {
  const { data } = await api.get('/api/staff/requests', { params })
  return data as { requests: StaffWorkspaceRequestSummary[] }
}

export async function getStaffRequest(id: string | number) {
  const { data } = await api.get(`/api/staff/requests/${id}`)
  return data as {
    request: StaffWorkspaceRequestDetails
    required_documents: RequiredDocumentChecklistItem[]
  }
}

export async function addStaffComment(
  id: string | number,
  payload: { comment_text: string; visibility?: 'internal' | 'admin_only' },
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
export function staffAttachmentDownloadUrl(requestId: string | number, attachmentId: string | number) {
  return `/api/admin/requests/${requestId}/attachments/${attachmentId}/download`
}

export function staffShareholderIdDownloadUrl(requestId: string | number, shareholderId: string | number) {
  return `/api/admin/requests/${requestId}/shareholders/${shareholderId}/id-file/download`
}