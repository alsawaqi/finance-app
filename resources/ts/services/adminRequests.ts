import api from './api'

export type AdminRequestListItem = {
  id: number
  reference_number: string
  approval_reference_number?: string | null
  intake_details_json?: Record<string, unknown> | null
  submitted_at?: string | null
  approved_at?: string | null
  status: string
  workflow_stage: string
  client?: {
    id: number
    name: string
    email: string
  }
  current_contract?: {
    id: number
    status: string
  } | null
}

export type RequestEmailLog = {
  id: number
  subject: string
  body?: string | null
  delivery_status?: string | null
  from_email?: string | null
  sent_at?: string | null
  created_at?: string | null
  sender?: { id: number; name: string; email?: string | null } | null
  agents?: Array<{
    id: number
    name: string
    email?: string | null
    bank?: { id: number; name: string; short_name?: string | null; code?: string | null } | null
  }>
  to_emails_json?: string[] | null
  attachments?: Array<{
    id: number
    file_name: string
    file_path: string
    disk?: string | null
    mime_type?: string | null
    file_extension?: string | null
    file_size?: number | null
  }>
}

export type FinanceRequestDetail = AdminRequestListItem & {
  assignments?: Array<{
    id: number
    staff_id: number
    assignment_role?: string | null
    notes?: string | null
    is_primary: boolean
    assigned_at?: string | null
    staff?: { id: number; name: string; email?: string | null } | null
    assignedBy?: { id: number; name: string; email?: string | null } | null
  }>
  comments?: Array<{
    id: number
    comment_text: string
    visibility: string
    created_at: string
    user?: { id: number; name: string; email?: string | null } | null
  }>
  latest_activity_at?: string | null
  attachments?: Array<{
    id: number
    file_name: string
    file_path: string
    category: string
    created_at: string
  }>
  answers?: Array<{
    id: number
    answer_text?: string | null
    answer_value_json?: unknown
    question?: {
      id: number
      code: string
      question_text: string
      question_type: string
      sort_order: number
    }
  }>
  timeline?: Array<{
    id: number
    event_type: string
    event_title?: string | null
    event_description?: string | null
    created_at: string
    actor?: { id: number; name: string } | null
  }>
  current_contract?: {
    id: number
    version_no: number
    status: string
    terms_json?: Record<string, unknown> | null
    contract_content?: string | null
    admin_signed_at?: string | null
    client_signed_at?: string | null
    contract_pdf_path?: string | null
  } | null
  emails?: RequestEmailLog[]
}

export type AdminRequestStaffQuestion = {
  id: number
  question_text_en?: string | null
  question_text_ar?: string | null
  question_type?: string | null
  is_required?: boolean
  status?: string | null
  answer_text?: string | null
  answer_json?: string[] | null
  template?: {
    id: number
    question_text_en?: string | null
    question_text_ar?: string | null
    question_type?: string | null
    sort_order?: number | null
    is_required?: boolean | null
  } | null
  assigned_staff?: { id: number; name: string; email?: string | null } | null
  asker?: { id: number; name: string; email?: string | null } | null
}

export async function listNewRequests(params?: { queue?: 'all' | 'pending' | 'contract' }) {
  const { data } = await api.get('/api/admin/requests/new', { params })
  return data as {
    selected_queue: 'all' | 'pending' | 'contract'
    queue_summary: {
      all: number
      pending: number
      contract: number
    }
    requests: AdminRequestListItem[]
  }
}

export async function getAdminRequestDetails(id: number | string) {
  const { data } = await api.get(`/api/admin/requests/${id}`)
  return data
}

export async function approveAdminRequest(id: number | string, payload: { approval_notes?: string }) {
  const { data } = await api.post(`/api/admin/requests/${id}/approve`, payload)
  return data
}

export async function getAdminContract(id: number | string) {
  const { data } = await api.get(`/api/admin/requests/${id}/contract`)
  return data as {
    request: FinanceRequestDetail
    contract?: FinanceRequestDetail['current_contract']
    contract_template?: {
      id: number
      name: string
      slug: string
      version_no?: number | null
    } | null
    draft_contract_html?: string
  }
}

export async function saveAdminContract(
  id: number | string,
  payload: {
    contract_template_slug: string
    contract_body_html: string
    signature_data_url: string
  },
) {
  const { data } = await api.post(`/api/admin/requests/${id}/contract`, payload)
  return data
}

export function adminContractDownloadUrl(id: number | string) {
  return `/api/admin/requests/${id}/contract/download`
}

export function adminRequestAttachmentDownloadUrl(id: number | string, attachmentId: number | string) {
  return `/api/admin/requests/${id}/attachments/${attachmentId}/download`
}

export function adminRequestShareholderIdDownloadUrl(id: number | string, shareholderId: number | string) {
  return `/api/admin/requests/${id}/shareholders/${shareholderId}/id-file/download`
}

export function adminRequiredDocumentDownloadUrl(id: number | string, uploadId: number | string) {
  return `/api/admin/requests/${id}/required-documents/${uploadId}/download`
}

export function adminAdditionalDocumentDownloadUrl(id: number | string, additionalDocumentId: number | string) {
  return `/api/admin/requests/${id}/additional-documents/${additionalDocumentId}/download`
}

export function adminRequestEmailAttachmentDownloadUrl(
  id: number | string,
  requestEmailId: number | string,
  requestEmailAttachmentId: number | string,
) {
  return `/api/admin/requests/${id}/emails/${requestEmailId}/attachments/${requestEmailAttachmentId}/download`
}

export type AssignmentReadyRequest = AdminRequestListItem & {
  latest_assignment_at?: string | null
  latest_activity_at?: string | null
  assignments?: Array<{
    id: number
    staff_id: number
    assignment_role?: string | null
    is_primary: boolean
    assigned_at?: string | null
    staff?: { id: number; name: string; email?: string | null } | null
  }>
  current_contract?: {
    id: number
    version_no?: number
    status: string
    client_signed_at?: string | null
  } | null
}

export type StaffDirectoryMember = {
  id: number
  name: string
  email: string
  phone?: string | null
  permission_names?: string[]
  role_names?: string[]
}

export async function listReadyForAssignment() {
  const { data } = await api.get('/api/admin/requests/ready-to-assign')
  return data as { requests: AssignmentReadyRequest[] }
}

export async function getStaffDirectory() {
  const { data } = await api.get('/api/admin/staff-directory')
  return data as { staff: StaffDirectoryMember[] }
}

export async function assignRequestStaff(
  id: number | string,
  payload: { staff_ids: number[]; primary_staff_id?: number | null; notes?: string },
) {
  const { data } = await api.post(`/api/admin/requests/${id}/assign-staff`, payload)
  return data
}


export type AdminUpdateBatchDraftItem = {
  item_type: 'intake_field' | 'request_answer' | 'attachment'
  field_key?: string | null
  question_id?: number | null
  label_en?: string | null
  label_ar?: string | null
  instruction_en?: string | null
  instruction_ar?: string | null
  editable_by?: 'client' | 'both'
  is_required?: boolean
}

export async function createAdminUpdateBatch(
  id: number | string,
  payload: { reason_en?: string; reason_ar?: string; items: AdminUpdateBatchDraftItem[] },
) {
  const { data } = await api.post(`/api/admin/requests/${id}/update-batches`, payload)
  return data
}

export async function cancelAdminUpdateBatch(
  id: number | string,
  updateBatchId: number | string,
) {
  const { data } = await api.patch(`/api/admin/requests/${id}/update-batches/${updateBatchId}/cancel`)
  return data
}

export async function reviewAdminUpdateItem(
  id: number | string,
  updateItemId: number | string,
  payload: { action: 'approve' | 'reject'; review_note?: string },
) {
  const { data } = await api.patch(`/api/admin/requests/${id}/update-items/${updateItemId}/review`, payload)
  return data
}


export async function reviewAdminUnderstudy(
  id: number | string,
  payload: { action: 'approve' | 'reject'; review_note?: string },
) {
  const { data } = await api.post(`/api/admin/requests/${id}/understudy-review`, payload)
  return data
}

export async function rejectAdminRequest(
  id: number | string,
  payload: { reason?: string },
) {
  const { data } = await api.post(`/api/admin/requests/${id}/reject`, payload)
  return data
}


export type RequestAssignmentBankOption = {
  id: number
  name: string
  short_name?: string | null
  code?: string | null
}

export type RequestAssignmentAgentOption = {
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

export type RequestEmailDocumentOption = {
  key: string
  document_type: string
  document_id?: number | null
  group_label?: string | null
  label: string
  file_name: string
  download_url?: string | null
}

export async function getAdminRequestAgentAssignmentOptions(id: number | string) {
  const { data } = await api.get(`/api/admin/requests/${id}/agent-assignment-options`)
  return data as {
    banks: RequestAssignmentBankOption[]
    agents: RequestAssignmentAgentOption[]
    available_documents: RequestEmailDocumentOption[]
  }
}

export async function storeAdminRequestAgentAssignments(
  id: number | string,
  payload: {
    review_note?: string
    assignments: Array<{ agent_id: number; document_keys: string[] }>
  },
) {
  const { data } = await api.post(`/api/admin/requests/${id}/agent-assignments`, payload)
  return data as {
    message: string
    request: FinanceRequestDetail
    required_documents: unknown[]
    staff_question_summary: unknown
    banks: RequestAssignmentBankOption[]
    agents: RequestAssignmentAgentOption[]
    available_documents: RequestEmailDocumentOption[]
  }
}
