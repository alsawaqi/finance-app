import api from './api'

export type CategorizationSummary = {
  total_requests: number
  submitted_requests: number
  active_requests: number
  completed_requests: number
  total_clients: number
  total_staff: number
  total_agents: number
  with_additional_document_requests: number
  pending_queue_requests: number
  contract_queue_requests: number
  assigned_queue_requests: number
}

export type CategorizedBank = {
  id: number
  name: string
  short_name?: string | null
  agents_count: number
  emails_count: number
  requests_count: number
}

export type CategorizedAgent = {
  id: number
  name: string
  email?: string | null
  phone?: string | null
  is_active: boolean
  bank_id?: number | null
  bank_name?: string | null
  bank_short_name?: string | null
  emails_count: number
  requests_count: number
  last_contact_at?: string | null
}

export type CategorizedStaff = {
  id: number
  name: string
  email: string
  phone?: string | null
  is_active: boolean
  active_assignments_count: number
  lead_requests_count: number
  comments_count: number
  permission_names: string[]
  last_assigned_at?: string | null
  last_login_at?: string | null
}

export type CategorizedClient = {
  id: number
  name: string
  email: string
  phone?: string | null
  is_active: boolean
  requests_count: number
  active_requests_count: number
  needs_action_count: number
  last_request_at?: string | null
  last_login_at?: string | null
}

export async function getAdminCategorization() {
  const { data } = await api.get('/api/admin/categorization')
  return data as {
    summary: CategorizationSummary
    status_breakdown: Record<string, number>
    stage_breakdown: Record<string, number>
    charts: {
      request_trend: {
        labels: string[]
        series: number[]
      }
      bank_email_breakdown: {
        labels: string[]
        email_series: number[]
        request_series: number[]
      }
    }
    bank_breakdown: CategorizedBank[]
    agents: CategorizedAgent[]
    staff: CategorizedStaff[]
    clients: CategorizedClient[]
  }
}
