import api from './api'
import type { PaginationMeta } from '@/types/pagination'

export interface BankItem {
  id: number
  name: string
  code: string | null
  short_name: string | null
  is_active: boolean
  agents_count: number
  created_by: number | null
  creator_name: string | null
  created_at: string | null
  updated_at: string | null
}

export interface BankPayload {
  name: string
  code?: string | null
  short_name?: string | null
  is_active?: boolean
}

export async function listBanks(params?: { page?: number; per_page?: number }) {
  return api.get<{ data: BankItem[]; pagination: PaginationMeta }>('/api/admin/banks', { params })
}

export async function createBank(payload: BankPayload) {
  return api.post<{ message: string; data: BankItem }>('/api/admin/banks', payload)
}

export async function updateBank(id: number, payload: BankPayload) {
  return api.put<{ message: string; data: BankItem }>(`/api/admin/banks/${id}`, payload)
}

export async function toggleBankActive(id: number) {
  return api.patch<{ message: string; data: BankItem }>(`/api/admin/banks/${id}/toggle-active`)
}
