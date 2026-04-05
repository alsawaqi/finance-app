import api from './api'
import type { PaginationMeta } from '@/types/pagination'

export interface AgentItem {
  id: number
  name: string
  email: string | null
  phone: string | null
  company_name: string | null
  bank_id: number | null
  bank_name: string | null
  bank_short_name: string | null
  bank_code: string | null
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
  bank_id?: number | null
  agent_type?: string | null
  notes?: string | null
  is_active?: boolean
}

export async function listAgents(params?: { page?: number; per_page?: number }) {
  return api.get<{ data: AgentItem[]; pagination: PaginationMeta }>('/api/admin/agents', { params })
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
