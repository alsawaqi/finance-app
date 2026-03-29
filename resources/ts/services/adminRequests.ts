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
    admin_signed_at?: string | null
    client_signed_at?: string | null
    contract_pdf_path?: string | null
  } | null
}

export async function listNewRequests() {
  const { data } = await api.get('/api/admin/requests/new')
  return data
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
  return data
}

export async function saveAdminContract(
  id: number | string,
  payload: {
    commission: string
    interest: string
    payment_period: string
    general_terms: string[]
    special_terms?: string
    signature_data_url: string
  },
) {
  const { data } = await api.post(`/api/admin/requests/${id}/contract`, payload)
  return data
}

export function adminContractDownloadUrl(id: number | string) {
  return `/api/admin/requests/${id}/contract/download`
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
