import api from './api'

export type FilterStatusOption = {
  value: string
  label: string
}

export type FilterStaffOption = {
  id: number
  name: string
  email?: string | null
}

export type FilterBankOption = {
  id: number
  name: string
  short_name?: string | null
  code?: string | null
  agents_count: number
}

export type FilterAgentOption = {
  id: number
  name: string
  email?: string | null
  bank_id?: number | null
  bank_name?: string | null
  bank_short_name?: string | null
  is_active: boolean
}

export type FilteredRequestItem = {
  id: number
  reference_number: string
  approval_reference_number?: string | null
  status: string
  workflow_stage: string
  submitted_at?: string | null
  latest_activity_at?: string | null
  latest_email_at?: string | null
  emails_count: number
  client?: {
    id: number
    name: string
    email?: string | null
    phone?: string | null
  } | null
  primary_staff?: {
    id: number
    name: string
    email?: string | null
  } | null
  active_staff?: Array<{
    id: number
    name: string
    email?: string | null
    is_primary: boolean
  }>
  agents?: Array<{
    id: number
    name: string
    email?: string | null
    bank_id?: number | null
    bank_name?: string | null
    bank_short_name?: string | null
  }>
}

export type FilterBreakdownBank = {
  id: number
  name: string
  short_name?: string | null
  agents_count: number
  emails_count: number
  requests_count: number
}

export type FilterBreakdownAgent = {
  id: number
  name: string
  email?: string | null
  bank_id?: number | null
  bank_name?: string | null
  bank_short_name?: string | null
  emails_count: number
  requests_count: number
}

export type ClientOverviewItem = {
  id: number
  name: string
  email: string
  phone?: string | null
  is_active: boolean
  requests_count: number
  active_requests_count: number
  last_request_at?: string | null
  last_login_at?: string | null
}

export type ClientOverviewRequest = {
  id: number
  reference_number: string
  approval_reference_number?: string | null
  status: string
  workflow_stage: string
  submitted_at?: string | null
  latest_activity_at?: string | null
  emails_count: number
  primary_staff?: {
    id: number
    name: string
    email?: string | null
  } | null
  active_staff?: Array<{
    id: number
    name: string
    email?: string | null
    is_primary: boolean
  }>
}

export async function getAdminRequestFilterData(params?: {
  status?: string
  staff_id?: number | null
  bank_id?: number | null
  agent_id?: number | null
}) {
  const { data } = await api.get('/api/admin/request-filters', { params })
  return data as {
    filters: {
      statuses: FilterStatusOption[]
      staff: FilterStaffOption[]
      banks: FilterBankOption[]
      agents: FilterAgentOption[]
    }
    summary: {
      total_requests: number
      unique_clients: number
      unique_staff: number
      unique_agents: number
      total_emails: number
    }
    bank_breakdown: FilterBreakdownBank[]
    agent_breakdown: FilterBreakdownAgent[]
    requests: FilteredRequestItem[]
  }
}

export async function getAdminClientsOverview(params?: { search?: string }) {
  const { data } = await api.get('/api/admin/clients-overview', { params })
  return data as {
    summary: {
      total_clients: number
      clients_with_requests: number
      clients_with_active_requests: number
    }
    clients: ClientOverviewItem[]
  }
}

export async function getAdminClientRequests(clientId: number | string) {
  const { data } = await api.get(`/api/admin/clients-overview/${clientId}/requests`)
  return data as {
    client: {
      id: number
      name: string
      email: string
      phone?: string | null
    }
    requests: ClientOverviewRequest[]
  }
}
