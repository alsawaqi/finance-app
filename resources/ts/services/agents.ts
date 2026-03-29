import api from './api'

export interface AgentItem {
  id: number
  name: string
  email: string | null
  phone: string | null
  company_name: string | null
  agent_type: string | null
  notes: string | null
  is_active: boolean
  created_by: number | null
  creator_name: string | null
  created_at: string | null
  updated_at: string | null
}

export interface AgentPayload {
  name: string
  email?: string | null
  phone?: string | null
  company_name?: string | null
  agent_type?: string | null
  notes?: string | null
  is_active?: boolean
}

export async function listAgents() {
  return api.get<{ data: AgentItem[] }>('/api/admin/agents')
}

export async function createAgent(payload: AgentPayload) {
  return api.post<{ message: string; data: AgentItem }>('/api/admin/agents', payload)
}

export async function updateAgent(id: number, payload: AgentPayload) {
  return api.put<{ message: string; data: AgentItem }>(`/api/admin/agents/${id}`, payload)
}

export async function toggleAgentActive(id: number) {
  return api.patch<{ message: string; data: AgentItem }>(`/api/admin/agents/${id}/toggle-active`)
}
